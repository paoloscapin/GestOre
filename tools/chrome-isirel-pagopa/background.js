chrome.webRequest.onBeforeSendHeaders.addListener(
    (details) => {
        const headers = Array.isArray(details.requestHeaders) ? details.requestHeaders : [];
        const authHeader = headers.find((header) => {
            return header && header.name && header.name.toLowerCase() === 'authorization';
        });

        if (!authHeader || !authHeader.value || !authHeader.value.startsWith('Bearer ')) {
            return;
        }

        chrome.storage.session.set({
            isirelBearerToken: authHeader.value,
            isirelBearerTokenSeenAt: new Date().toISOString(),
            isirelBearerTokenSourceUrl: details.url
        });
    },
    {
        urls: [
            'https://istruzione.cloud.provincia.tn.it/services/*'
        ]
    },
    [
        'requestHeaders',
        'extraHeaders'
    ]
);

let pagopaWindowId = null;
let importRunning = false;
let lastFocusRequestAt = 0;
const canceledRunIds = new Set();
const DEBUGGER_VERSION = '1.3';

async function rememberSourceTab(tab) {
    if (!tab || !tab.id || !tab.url || !tab.url.startsWith('https://istruzione.cloud.provincia.tn.it/')) {
        return;
    }

    await chrome.storage.session.set({
        isirelSourceTabId: tab.id,
        isirelSourceTabUrl: tab.url,
        isirelSourceTabSeenAt: new Date().toISOString()
    });
}

async function openPagopaWindow() {
    const popupUrl = chrome.runtime.getURL('popup.html');

    if (pagopaWindowId !== null) {
        try {
            await chrome.windows.update(pagopaWindowId, {
                focused: true,
                drawAttention: true
            });
            return;
        } catch {
            pagopaWindowId = null;
        }
    }

    const win = await chrome.windows.create({
        url: popupUrl,
        type: 'popup',
        width: 820,
        height: 860,
        focused: true
    });

    pagopaWindowId = win && win.id ? win.id : null;
}

chrome.action.onClicked.addListener(async (tab) => {
    await rememberSourceTab(tab);
    await openPagopaWindow();
});

chrome.windows.onRemoved.addListener((windowId) => {
    if (windowId === pagopaWindowId) {
        pagopaWindowId = null;
    }
});

async function focusPagopaWindow() {
    if (!importRunning || pagopaWindowId === null) {
        return;
    }

    const now = Date.now();

    if (now - lastFocusRequestAt < 800) {
        return;
    }

    lastFocusRequestAt = now;

    try {
        await chrome.windows.update(pagopaWindowId, {
            focused: true,
            drawAttention: true
        });
    } catch {
        pagopaWindowId = null;
    }
}

chrome.windows.onFocusChanged.addListener((windowId) => {
    if (!importRunning || pagopaWindowId === null || windowId === pagopaWindowId) {
        return;
    }

    focusPagopaWindow();
});

function chromeCall(apiFn, ...args) {
    return new Promise((resolve, reject) => {
        apiFn(...args, (result) => {
            const err = chrome.runtime.lastError;

            if (err) {
                reject(new Error(err.message));
                return;
            }

            resolve(result);
        });
    });
}

function textToBase64(text) {
    return btoa(unescape(encodeURIComponent(String(text || ''))));
}

function arrayBufferToBase64(buffer) {
    const bytes = new Uint8Array(buffer);
    const chunkSize = 0x8000;
    let binary = '';

    for (let i = 0; i < bytes.length; i += chunkSize) {
        binary += String.fromCharCode.apply(null, bytes.subarray(i, i + chunkSize));
    }

    return btoa(binary);
}

function fileNameFromDisposition(disposition, fallback) {
    const text = String(disposition || '');
    const starMatch = text.match(/filename\*=UTF-8''([^;]+)/i);

    if (starMatch && starMatch[1]) {
        try {
            return decodeURIComponent(starMatch[1].replace(/"/g, '').trim());
        } catch {
            return starMatch[1].replace(/"/g, '').trim();
        }
    }

    const match = text.match(/filename="?([^";]+)"?/i);

    if (match && match[1]) {
        return match[1].trim();
    }

    return fallback;
}

async function debuggerCommand(target, method, params = {}) {
    return await chromeCall(chrome.debugger.sendCommand, target, method, params);
}

async function downloadPdfViaPlainFetch(url, fallbackName) {
    const response = await fetch(url, {
        method: 'GET',
        credentials: 'omit',
        cache: 'no-store',
        redirect: 'follow',
        headers: {
            'accept': '*/*',
            'user-agent': 'PostmanRuntime/7.53.0'
        }
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    const buffer = await response.arrayBuffer();
    const contentType = response.headers.get('content-type') || 'application/pdf';

    if (!buffer || buffer.byteLength < 20) {
        throw new Error('PDF vuoto');
    }

    return {
        base64: arrayBufferToBase64(buffer),
        contentType,
        fileName: fileNameFromDisposition(
            response.headers.get('content-disposition'),
            fallbackName || 'avviso_pagopa.pdf'
        )
    };
}

async function downloadPdfViaDebugger(url, fallbackName, sourceTabId) {
    const pdfUrl = String(url || '');

    if (!/^https:\/\/mypay\.provincia\.tn\.it\//i.test(pdfUrl) && !/^https:\/\/istruzione\.cloud\.provincia\.tn\.it\//i.test(pdfUrl)) {
        throw new Error('URL PDF non consentito');
    }

    try {
        return await downloadPdfViaPlainFetch(pdfUrl, fallbackName);
    } catch (plainError) {
        if (!/HTTP\s+418/i.test(plainError.message || String(plainError))) {
            throw plainError;
        }
    }

    let tab = null;
    let attached = false;

    try {
        const createOptions = {
            url: 'about:blank',
            active: false
        };

        if (sourceTabId) {
            try {
                const sourceTab = await chromeCall(chrome.tabs.get, sourceTabId);
                if (sourceTab && sourceTab.windowId) {
                    createOptions.windowId = sourceTab.windowId;
                    createOptions.openerTabId = sourceTabId;
                }
            } catch {
                // Se la scheda ISIREL non e piu disponibile, apro comunque una scheda temporanea senza opener.
            }
        }

        tab = await chromeCall(chrome.tabs.create, createOptions);

        const target = {
            tabId: tab.id
        };

        await chromeCall(chrome.debugger.attach, target, DEBUGGER_VERSION);
        attached = true;
        await debuggerCommand(target, 'Network.enable');
        await debuggerCommand(target, 'Page.enable');

        const downloaded = await new Promise(async (resolve, reject) => {
            let settled = false;
            const requests = new Map();
            const timeout = setTimeout(() => {
                finish(null, new Error('Timeout download PDF MyPay'));
            }, 60000);

            function finish(value, error) {
                if (settled) {
                    return;
                }

                settled = true;
                clearTimeout(timeout);
                chrome.debugger.onEvent.removeListener(onEvent);

                if (error) {
                    reject(error);
                } else {
                    resolve(value);
                }
            }

            async function onEvent(source, method, params) {
                if (!source || source.tabId !== tab.id) {
                    return;
                }

                if (method === 'Network.responseReceived') {
                    const response = params.response || {};
                    const responseUrl = String(response.url || '');
                    const mimeType = String(response.mimeType || '');
                    const headers = response.headers || {};
                    const status = Number(response.status || 0);
                    const isCandidate = responseUrl === pdfUrl || /pdf|octet-stream/i.test(mimeType);

                    if (isCandidate && status >= 400) {
                        finish(null, new Error(`HTTP ${status}`));
                        return;
                    }

                    if (isCandidate) {
                        requests.set(params.requestId, {
                            url: responseUrl,
                            mimeType: mimeType || 'application/pdf',
                            headers
                        });
                    }
                }

                if (method === 'Network.loadingFinished' && requests.has(params.requestId)) {
                    const meta = requests.get(params.requestId);

                    try {
                        const body = await debuggerCommand(target, 'Network.getResponseBody', {
                            requestId: params.requestId
                        });

                        let base64 = body.body || '';
                        if (!body.base64Encoded) {
                            base64 = textToBase64(base64);
                        }

                        if (!base64 || base64.length < 20) {
                            throw new Error('PDF vuoto');
                        }

                        finish({
                            base64,
                            contentType: meta.mimeType || 'application/pdf',
                            fileName: fileNameFromDisposition(
                                meta.headers['content-disposition'] || meta.headers['Content-Disposition'] || '',
                                fallbackName || 'avviso_pagopa.pdf'
                            )
                        });
                    } catch (error) {
                        finish(null, error);
                    }
                }
            }

            chrome.debugger.onEvent.addListener(onEvent);

            try {
                await chromeCall(chrome.tabs.update, tab.id, {
                    url: pdfUrl
                });
            } catch (error) {
                finish(null, error);
            }
        });

        return downloaded;
    } finally {
        if (attached && tab && tab.id) {
            try {
                await chromeCall(chrome.debugger.detach, {
                    tabId: tab.id
                });
            } catch {
                // La scheda potrebbe essere gia stata chiusa.
            }
        }

        if (tab && tab.id) {
            try {
                await chromeCall(chrome.tabs.remove, tab.id);
            } catch {
                // Nulla da ripulire.
            }
        }
    }
}

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (!message || !message.type) {
        return false;
    }

    if (message.type === 'pagopa-import-state') {
        importRunning = message.running === true;

        if (message.runId && importRunning) {
            canceledRunIds.delete(String(message.runId));
        }

        if (message.runId && !importRunning) {
            canceledRunIds.delete(String(message.runId));
        }

        if (importRunning) {
            focusPagopaWindow();
        }

        sendResponse({
            ok: true
        });
        return false;
    }

    if (message.type === 'pagopa-cancel-import') {
        if (message.runId) {
            canceledRunIds.add(String(message.runId));
        }

        sendResponse({
            ok: true
        });
        return false;
    }

    if (message.type === 'pagopa-is-canceled') {
        sendResponse({
            ok: true,
            canceled: message.runId ? canceledRunIds.has(String(message.runId)) : false
        });
        return false;
    }

    if (message.type === 'pagopa-download-pdf-tab') {
        (async () => {
            try {
                const sourceTabId = sender && sender.tab && sender.tab.id ? sender.tab.id : null;
                const pdf = await downloadPdfViaDebugger(message.url, message.fallbackName || 'avviso_pagopa.pdf', sourceTabId);
                sendResponse(Object.assign({
                    ok: true
                }, pdf));
            } catch (error) {
                sendResponse({
                    ok: false,
                    error: error && error.message ? error.message : String(error)
                });
            }
        })();

        return true;
    }

    return false;
});
