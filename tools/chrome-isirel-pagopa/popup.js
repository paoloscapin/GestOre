const logEl = document.getElementById('log');
const importBtn = document.getElementById('importBtn');
const statusPill = document.getElementById('statusPill');
const statusText = document.getElementById('statusText');
const activitiesCount = document.getElementById('activitiesCount');
const studentsCount = document.getElementById('studentsCount');
const logTime = document.getElementById('logTime');
const closeTopBtn = document.getElementById('closeTopBtn');
const closeBottomBtn = document.getElementById('closeBottomBtn');
const stopTopBtn = document.getElementById('stopTopBtn');
const stopBottomBtn = document.getElementById('stopBottomBtn');

function log(msg, options = {}) {
    const shouldSendRemote = options.remote !== false;

    logEl.textContent += msg + "\n";
    logEl.scrollTop = logEl.scrollHeight;
    window.scrollTo({
        top: document.documentElement.scrollHeight,
        behavior: 'auto'
    });
    logTime.textContent = new Date().toLocaleTimeString('it-IT', {
        hour: '2-digit',
        minute: '2-digit'
    });

    if (shouldSendRemote) {
        sendLogToGestOre(msg);
    }
}

function setStatus(kind, text) {
    statusPill.className = `status-pill status-${kind}`;
    statusText.textContent = text;
}

function setCloseButtonsVisible(visible) {
    closeTopBtn.classList.toggle('hidden', !visible);
    closeBottomBtn.classList.toggle('hidden', !visible);
}

function setStopButtonsVisible(visible) {
    stopTopBtn.classList.toggle('hidden', !visible);
    stopBottomBtn.classList.toggle('hidden', !visible);
}

function closePopup() {
    window.close();
}

function scrollPopupToBottom() {
    requestAnimationFrame(() => {
        logEl.scrollTop = logEl.scrollHeight;
        window.scrollTo({
            top: document.documentElement.scrollHeight,
            behavior: 'auto'
        });
    });
}

function formatDateItalian(value) {
    if (!value) {
        return '-';
    }

    const parts = String(value).split('-').map(Number);

    if (parts.length !== 3 || parts.some(Number.isNaN)) {
        return String(value);
    }

    const [year, month, day] = parts;
    return `${String(day).padStart(2, '0')}/${String(month).padStart(2, '0')}/${year}`;
}

function logTrace(trace, options = {}) {
    if (!Array.isArray(trace) || trace.length === 0) {
        return;
    }

    for (const item of trace) {
        log(item, options);
    }
}

let activeRunId = null;
let streamedLogCount = 0;
let importRunning = false;
let cancelRequested = false;

function sendRuntimeMessage(message) {
    if (typeof chrome === 'undefined' || !chrome.runtime || !chrome.runtime.sendMessage) {
        return Promise.resolve(null);
    }

    return chrome.runtime.sendMessage(message).catch(() => null);
}

function getGestoreLogEndpoint() {
    const endpoint = document.getElementById('gestoreEndpoint')?.value || '';

    if (!endpoint) {
        return '';
    }

    return endpoint.replace(/importaPagopa\.php(?:\?.*)?$/i, 'importaPagopaExtensionLog.php');
}

function sendLogToGestOre(message) {
    const endpoint = getGestoreLogEndpoint();

    if (!endpoint || !message) {
        return;
    }

    fetch(endpoint, {
        method: 'POST',
        headers: {
            'content-type': 'application/json'
        },
        body: JSON.stringify({
            source: 'EXTENSION',
            runId: activeRunId || '',
            at: new Date().toISOString(),
            message: String(message)
        })
    }).catch(() => {
        // Il log a video deve restare fluido anche se il log remoto non risponde.
    });
}

window.addEventListener('beforeunload', (event) => {
    if (!importRunning) {
        return;
    }

    event.preventDefault();
    event.returnValue = '';
});

chrome.runtime.onMessage.addListener((message) => {
    if (!message || message.type !== 'pagopa-import-log' || message.runId !== activeRunId) {
        return;
    }

    streamedLogCount++;
    log(message.message, {
        remote: false
    });
});

function schoolYearDefault() {
    const now = new Date();
    const y = now.getFullYear();
    const m = now.getMonth() + 1;
    return m >= 9 ? `${y}/${y + 1}` : `${y - 1}/${y}`;
}

function setDefaultDates() {
    const sy = schoolYearDefault();
    document.getElementById('schoolYear').value = sy;

    const [a, b] = sy.split('/').map(Number);
    document.getElementById('startDate').value = `${a}-09-01`;
    document.getElementById('endDate').value = `${b}-08-31`;
}

async function getStoredBearerInfo() {
    if (!chrome.storage || !chrome.storage.session) {
        return {};
    }

    return await chrome.storage.session.get([
        'isirelBearerToken',
        'isirelBearerTokenSeenAt',
        'isirelBearerTokenSourceUrl'
    ]);
}

async function getStudentRegistryCache() {
    if (!chrome.storage || !chrome.storage.local) {
        return {};
    }

    const stored = await chrome.storage.local.get(['isirelStudentRegistryById']);
    const cache = stored.isirelStudentRegistryById;

    return cache && typeof cache === 'object' ? cache : {};
}

async function mergeStudentRegistryCache(updates) {
    if (!chrome.storage || !chrome.storage.local || !updates || typeof updates !== 'object') {
        return 0;
    }

    const updateKeys = Object.keys(updates);

    if (updateKeys.length === 0) {
        return 0;
    }

    const cache = await getStudentRegistryCache();

    for (const key of updateKeys) {
        cache[key] = updates[key];
    }

    await chrome.storage.local.set({
        isirelStudentRegistryById: cache,
        isirelStudentRegistryUpdatedAt: new Date().toISOString()
    });

    return updateKeys.length;
}

async function getActiveTab() {
    if (chrome.storage && chrome.storage.session) {
        const stored = await chrome.storage.session.get([
            'isirelSourceTabId',
            'isirelSourceTabUrl'
        ]);

        if (stored.isirelSourceTabId) {
            try {
                const sourceTab = await chrome.tabs.get(stored.isirelSourceTabId);

                if (sourceTab && sourceTab.url && sourceTab.url.startsWith('https://istruzione.cloud.provincia.tn.it/')) {
                    return sourceTab;
                }
            } catch {
                // La scheda ISIREL memorizzata non esiste piu: si passa al fallback.
            }
        }
    }

    const [tab] = await chrome.tabs.query({
        active: true,
        lastFocusedWindow: true
    });

    return tab;
}

async function runImportInPage(args) {
    const tab = await getActiveTab();

    if (!tab.url || !tab.url.startsWith('https://istruzione.cloud.provincia.tn.it/')) {
        throw new Error('Apri prima la pagina ISIREL/Segreteria gia autenticata.');
    }

    const injection = await chrome.scripting.executeScript({
        target: {
            tabId: tab.id
        },
        func: async (args) => {
            const trace = [];

            function traceLog(message) {
                trace.push(message);

                try {
                    if (args.gestoreLogEndpoint) {
                        fetch(args.gestoreLogEndpoint, {
                            method: 'POST',
                            headers: {
                                'content-type': 'application/json'
                            },
                            body: JSON.stringify({
                                source: 'ISIREL_PAGE',
                                runId: args.runId || '',
                                at: new Date().toISOString(),
                                message: String(message)
                            })
                        }).catch(() => {});
                    }
                } catch (e) {
                    // Il log remoto non deve interrompere l'importazione.
                }

                try {
                    if (typeof chrome !== 'undefined' && chrome.runtime && chrome.runtime.sendMessage) {
                        chrome.runtime.sendMessage({
                            type: 'pagopa-import-log',
                            runId: args.runId,
                            message: message
                        });
                    }
                } catch (e) {
                    // Il trace resta disponibile nel risultato finale anche se il messaggio live fallisce.
                }
            }

            async function throwIfCanceled() {
                if (!args.runId || typeof chrome === 'undefined' || !chrome.runtime || !chrome.runtime.sendMessage) {
                    return;
                }

                let result = null;

                try {
                    result = await chrome.runtime.sendMessage({
                        type: 'pagopa-is-canceled',
                        runId: args.runId
                    });
                } catch (e) {
                    result = null;
                }

                if (result && result.canceled) {
                    throw new Error('Importazione interrotta dall utente');
                }
            }

            function formatDateItalianInPage(value) {
                if (!value) {
                    return '-';
                }

                const parts = String(value).split('-').map(Number);

                if (parts.length !== 3 || parts.some(Number.isNaN)) {
                    return String(value);
                }

                const [year, month, day] = parts;
                return `${String(day).padStart(2, '0')}/${String(month).padStart(2, '0')}/${year}`;
            }

            function shortText(value, maxLength) {
                const text = String(value || '').trim();

                if (text.length <= maxLength) {
                    return text;
                }

                return `${text.slice(0, maxLength - 3)}...`;
            }

            function compactStudent(student) {
                if (!student || !student.id) {
                    return null;
                }

                return {
                    id: student.id,
                    fiscalCode: student.fiscalCode || null,
                    lastName: student.lastName || null,
                    firstName: student.firstName || null,
                    email: student.email || null
                };
            }

            try {
                const API_BASE = 'https://istruzione.cloud.provincia.tn.it';

                function isirelHeaders(extraHeaders) {
                    const headers = {
                        'accept': 'application/json, text/plain, */*',
                        'content-type': 'application/json',
                        'from-client': 'APP_SEGRETERIA'
                    };

                    if (args.bearerToken) {
                        headers.authorization = args.bearerToken;
                    }

                    return Object.assign(headers, extraHeaders || {});
                }

                async function apiGet(path) {
                    await throwIfCanceled();
                    const r = await fetch(API_BASE + path, {
                        method: 'GET',
                        credentials: 'include',
                        headers: isirelHeaders()
                    });

                    if (!r.ok) {
                        throw new Error(`GET ${path} -> HTTP ${r.status}`);
                    }

                    await throwIfCanceled();
                    return await r.json();
                }

                async function apiPost(path, body) {
                    await throwIfCanceled();
                    const r = await fetch(API_BASE + path, {
                        method: 'POST',
                        credentials: 'include',
                        headers: isirelHeaders(),
                        body: JSON.stringify(body)
                    });

                    if (!r.ok) {
                        throw new Error(`POST ${path} -> HTTP ${r.status}`);
                    }

                    await throwIfCanceled();
                    return await r.json();
                }

                async function sendToGestore(endpoint, payload) {
                    await throwIfCanceled();
                    const r = await fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'content-type': 'application/json'
                        },
                        credentials: 'include',
                        body: JSON.stringify(payload)
                    });

                    const text = await r.text();

                    let json;
                    try {
                        json = JSON.parse(text);
                    } catch {
                        json = {
                            raw: text
                        };
                    }

                    if (!r.ok || json.ok === false) {
                        throw new Error(`GestOre HTTP ${r.status}: ${text}`);
                    }

                    await throwIfCanceled();
                    return json;
                }

                async function downloadPdfViaBrowser(pdfUrl, fallbackName) {
                    await throwIfCanceled();

                    if (typeof chrome === 'undefined' || !chrome.runtime || !chrome.runtime.sendMessage) {
                        throw new Error('Background estensione non disponibile per scaricare il PDF');
                    }

                    const result = await chrome.runtime.sendMessage({
                        type: 'pagopa-download-pdf-tab',
                        url: pdfUrl,
                        fallbackName
                    });

                    if (!result || result.ok !== true) {
                        throw new Error((result && result.error) ? result.error : 'Download PDF non riuscito');
                    }

                    await throwIfCanceled();
                    return result;
                }

                async function archiveAvailablePdfs(activities) {
                    const pdfEndpoint = args.gestorePdfEndpoint;

                    if (!pdfEndpoint) {
                        return {
                            candidates: 0,
                            saved: 0,
                            skipped: 0,
                            errors: 0
                        };
                    }

                    function isRecipientPaid(recipient) {
                        const state = String(recipient && recipient.paymentState ? recipient.paymentState : '').toUpperCase();
                        return state === 'PAGOPA_PAYMENT_VERIFICATION_OK'
                            || state === 'PAGATO'
                            || state === 'PAID'
                            || state.indexOf('PAYMENT_VERIFICATION_OK') !== -1;
                    }

                    function pdfJobLabel(job) {
                        const recipient = job.recipient || {};
                        const activity = job.activity || {};
                        const student = recipient.student || {};
                        const studentName = `${student.lastName || ''} ${student.firstName || ''}`.trim() || `idStudent ${recipient.idStudent || '-'}`;
                        const activityText = shortText(activity.causal || activity.description || '-', 70);

                        return `recipient ${recipient.idRecipient || '-'} | stato=${recipient.paymentState || '-'} | studente=${studentName} | idClass=${recipient.idClass || '-'} | attivita=${activity.id || '-'} | viaggio=${activityText}`;
                    }

                    const jobs = [];
                    let paidSkipped = 0;
                    let cancelledCandidates = 0;
                    let noLinkSkipped = 0;

                    for (const activity of activities) {
                        await throwIfCanceled();
                        const recipients = Array.isArray(activity.recipients) ? activity.recipients : [];

                        for (const recipient of recipients) {
                            if (isRecipientPaid(recipient)) {
                                paidSkipped++;
                                continue;
                            }

                            const link = recipient && recipient.paymentLink ? String(recipient.paymentLink) : '';

                            if (!link) {
                                noLinkSkipped++;
                                continue;
                            }

                            if (recipient.cancelled === true || recipient.cancelled === 1 || recipient.cancelled === '1') {
                                cancelledCandidates++;
                            }

                            jobs.push({
                                activity,
                                recipient,
                                link
                            });
                        }
                    }

                    const stats = {
                        candidates: jobs.length,
                        saved: 0,
                        skipped: 0,
                        errors: 0
                    };

                    if (jobs.length === 0) {
                        traceLog(`   Nessun PDF da archiviare. Gia pagati saltati: ${paidSkipped}; senza link: ${noLinkSkipped}.`);
                        return stats;
                    }

                    traceLog(`   PDF disponibili da verificare: ${jobs.length}. Gia pagati saltati: ${paidSkipped}; annullati con link da verificare: ${cancelledCandidates}; senza link: ${noLinkSkipped}.`);
                    let consecutiveBlockedDownloads = 0;

                    for (let i = 0; i < jobs.length; i++) {
                        await throwIfCanceled();
                        const job = jobs[i];
                        const recipient = job.recipient;
                        const activity = job.activity;

                        try {
                            const checkResult = await sendToGestore(pdfEndpoint, {
                                source: 'ISIREL',
                                idIsirelActivity: activity.id,
                                idRecipientIsirel: recipient.idRecipient,
                                checkOnly: true
                            });

                            if (checkResult.skipped) {
                                stats.skipped++;
                                continue;
                            }

                            const pdfUrl = new URL(job.link, API_BASE).toString();
                            const label = pdfJobLabel(job);
                            traceLog(`   PDF ${i + 1}/${jobs.length}: ${label} | url=${pdfUrl}`);
                            const pdf = await downloadPdfViaBrowser(
                                pdfUrl,
                                `avviso_pagopa_${recipient.idRecipient || 'isirel'}.pdf`
                            );

                            const result = await sendToGestore(pdfEndpoint, {
                                source: 'ISIREL',
                                idIsirelActivity: activity.id,
                                idRecipientIsirel: recipient.idRecipient,
                                idStudentIsirel: recipient.idStudent,
                                paymentIuv: recipient.paymentIuv || null,
                                fileName: pdf.fileName || `avviso_pagopa_${recipient.idRecipient || 'isirel'}.pdf`,
                                contentType: pdf.contentType || 'application/pdf',
                                sourceUrl: pdfUrl,
                                base64: pdf.base64
                            });

                            if (result.skipped) {
                                stats.skipped++;
                            } else {
                                stats.saved++;
                            }
                            consecutiveBlockedDownloads = 0;
                        } catch (e) {
                            const errorMessage = e.message || String(e);

                            if (/HTTP\s+418/i.test(errorMessage)) {
                                stats.skipped++;
                                consecutiveBlockedDownloads++;
                                traceLog(`   PDF ${i + 1}/${jobs.length} non disponibile su MyPay: ${pdfJobLabel(job)} - HTTP 418`);

                                if (consecutiveBlockedDownloads >= 5) {
                                    traceLog('   Download PDF sospeso: MyPay ha risposto HTTP 418 per 5 PDF consecutivi.');
                                    break;
                                }
                            } else {
                                stats.errors++;
                                traceLog(`   PDF ${i + 1}/${jobs.length} non archiviato: ${pdfJobLabel(job)} - ${errorMessage}`);
                                consecutiveBlockedDownloads = 0;
                            }
                        }

                        if (i === 0 || i === jobs.length - 1 || (i + 1) % 25 === 0) {
                            traceLog(`   PDF ${i + 1}/${jobs.length}: salvati ${stats.saved}, gia presenti/saltati ${stats.skipped}, errori ${stats.errors}.`);
                        }
                    }

                    return stats;
                }

                traceLog('');
                traceLog('1. Lettura elenco avvisi da ISIREL');
                traceLog(`   Periodo: ${formatDateItalianInPage(args.startDate)} -> ${formatDateItalianInPage(args.endDate)}`);
                await throwIfCanceled();
                const list = await apiGet(
                    `/services/mie/api/v1/paymentRequests/byPeriodAndCurrentUser?startDate=${encodeURIComponent(args.startDate)}&endDate=${encodeURIComponent(args.endDate)}`
                );

                if (!Array.isArray(list)) {
                    throw new Error('La risposta elenco avvisi non e un array.');
                }

                traceLog(`   Trovate ${list.length} attivita pagoPA.`);
                traceLog('');
                traceLog('2. Lettura dettagli delle attivita');
                const activities = [];
                const studentIds = new Set();
                let activityIndex = 0;
                let recipientCount = 0;

                for (const item of list) {
                    await throwIfCanceled();
                    if (!item || !item.id) {
                        continue;
                    }

                    activityIndex++;
                    const detail = await apiGet(
                        `/services/mie/api/v1/paymentRequests/byId?id=${encodeURIComponent(item.id)}`
                    );

                    const recipients = Array.isArray(detail.recipients) ? detail.recipients : [];
                    recipientCount += recipients.length;
                    traceLog(`   ${String(activityIndex).padStart(2, '0')}/${list.length} - ISIREL ${item.id} - ${recipients.length} destinatari${detail.causal || detail.description ? ' - ' + shortText((detail.causal || detail.description), 58) : ''}`);

                    for (const r of recipients) {
                        if (r && r.idStudent) {
                            studentIds.add(r.idStudent);
                        }
                    }

                    activities.push(detail);
                }

                const ids = Array.from(studentIds);
                const cachedStudentsById = args.studentRegistryCache && typeof args.studentRegistryCache === 'object'
                    ? args.studentRegistryCache
                    : {};
                const studentsById = {};
                const studentCacheUpdates = {};

                for (const id of ids) {
                    if (cachedStudentsById[id]) {
                        studentsById[id] = cachedStudentsById[id];
                    }
                }

                const missingIds = ids.filter((id) => !studentsById[id]);
                traceLog(`   Totale destinatari negli avvisi: ${recipientCount}`);
                traceLog('');
                traceLog('3. Lettura anagrafiche studenti');
                traceLog(`   Studenti distinti da caricare: ${ids.length}`);
                traceLog(`   Gia in cache: ${ids.length - missingIds.length}`);
                traceLog(`   Nuovi o mancanti da leggere: ${missingIds.length}`);

                if (missingIds.length > 0) {
                    const batchSize = 20;
                    const totalBatches = Math.ceil(missingIds.length / batchSize);
                    traceLog(`   Caricamento in ${totalBatches} blocchi da massimo ${batchSize} studenti.`);

                    for (let i = 0; i < missingIds.length; i += batchSize) {
                        await throwIfCanceled();
                        const batch = missingIds.slice(i, i + batchSize);
                        const batchIndex = Math.floor(i / batchSize) + 1;
                        const students = await apiPost('/services/rel/api/v1/person-registry/list', batch);

                        if (Array.isArray(students)) {
                            for (const s of students) {
                                const compact = compactStudent(s);

                                if (compact && compact.id) {
                                    studentsById[compact.id] = compact;
                                    studentCacheUpdates[compact.id] = compact;
                                }
                            }
                        }

                        if (batchIndex === 1 || batchIndex === totalBatches || batchIndex % 10 === 0) {
                            traceLog(`   Blocco ${batchIndex}/${totalBatches} completato (${Math.min(i + batch.length, missingIds.length)}/${missingIds.length} studenti nuovi).`);
                        }
                    }
                } else {
                    traceLog('   Nessuna anagrafica da scaricare: uso la cache locale.');
                }

                traceLog('');
                traceLog('4. Preparazione dati per GestOre');
                traceLog('   Associazione anagrafiche studenti agli avvisi.');
                for (const activity of activities) {
                    await throwIfCanceled();
                    const recipients = Array.isArray(activity.recipients) ? activity.recipients : [];

                    for (const r of recipients) {
                        r.student = studentsById[r.idStudent] || null;
                    }
                }

                const payload = {
                    source: 'ISIREL',
                    schoolYear: args.schoolYear,
                    importedAt: new Date().toISOString(),
                    activities: activities
                };

                traceLog('');
                traceLog('5. Invio dati a GestOre');
                traceLog(`   Invio ${activities.length} attivita e ${recipientCount} avvisi studenti.`);
                await throwIfCanceled();
                const gestoreResult = await sendToGestore(args.gestoreEndpoint, payload);
                traceLog(`   Risposta GestOre OK: ${gestoreResult.activities ?? activities.length} attivita, ${gestoreResult.recipients ?? recipientCount} avvisi, ${gestoreResult.mappedStudents ?? 0} studenti mappati.`);
                traceLog('');
                traceLog('6. Archivio PDF disponibili');
                await throwIfCanceled();
                const pdfArchive = await archiveAvailablePdfs(activities);
                traceLog(`   Archivio PDF: ${pdfArchive.saved} salvati, ${pdfArchive.skipped} gia presenti/saltati, ${pdfArchive.errors} errori.`);
                traceLog('');
                traceLog('Importazione conclusa correttamente.');

                return {
                    ok: true,
                    activities: activities.length,
                    students: ids.length,
                    gestore: gestoreResult,
                    pdfArchive: pdfArchive,
                    studentCacheUpdates: studentCacheUpdates,
                    trace: trace
                };

            } catch (e) {
                traceLog(`Errore: ${e.message || String(e)}`);
                return {
                    ok: false,
                    error: e.message || String(e),
                    trace: trace
                };
            }
        },
        args: [args]
    });

    if (!injection || !injection[0]) {
        throw new Error('Nessun risultato restituito da chrome.scripting.executeScript.');
    }

    const result = injection[0].result;

    if (!result) {
        throw new Error('Risultato nullo dallo script eseguito nella pagina ISIREL.');
    }

    if (result.ok === false) {
        const error = new Error(result.error || 'Errore sconosciuto nello script ISIREL.');
        error.trace = result.trace || [];
        error.studentCacheUpdates = result.studentCacheUpdates || {};
        throw error;
    }

    return result;
}

document.addEventListener('DOMContentLoaded', setDefaultDates);
closeTopBtn.addEventListener('click', closePopup);
closeBottomBtn.addEventListener('click', closePopup);

async function requestStopImport() {
    if (!importRunning || !activeRunId || cancelRequested) {
        return;
    }

    cancelRequested = true;
    stopTopBtn.disabled = true;
    stopBottomBtn.disabled = true;
    setStatus('error', 'Interruzione richiesta');
    log('Interruzione richiesta: attendo la fine della chiamata corrente e poi fermo il sync.');

    await sendRuntimeMessage({
        type: 'pagopa-cancel-import',
        runId: activeRunId
    });
}

stopTopBtn.addEventListener('click', requestStopImport);
stopBottomBtn.addEventListener('click', requestStopImport);

importBtn.addEventListener('click', async () => {
    logEl.textContent = '';
    activitiesCount.textContent = '-';
    studentsCount.textContent = '-';
    setCloseButtonsVisible(false);
    setStopButtonsVisible(true);
    stopTopBtn.disabled = false;
    stopBottomBtn.disabled = false;
    streamedLogCount = 0;
    activeRunId = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    importRunning = true;
    cancelRequested = false;
    importBtn.disabled = true;
    setStatus('running', 'Importazione in corso');

    try {
        await sendRuntimeMessage({
            type: 'pagopa-import-state',
            running: true,
            runId: activeRunId
        });

        const args = {
            startDate: document.getElementById('startDate').value,
            endDate: document.getElementById('endDate').value,
            schoolYear: document.getElementById('schoolYear').value,
            gestoreEndpoint: document.getElementById('gestoreEndpoint').value,
            runId: activeRunId
        };

        args.gestorePdfEndpoint = args.gestoreEndpoint.replace(/importaPagopa\.php(?:\?.*)?$/i, 'importaPagopaPdf.php');
        args.gestoreLogEndpoint = args.gestoreEndpoint.replace(/importaPagopa\.php(?:\?.*)?$/i, 'importaPagopaExtensionLog.php');

        const bearerInfo = await getStoredBearerInfo();
        args.bearerToken = bearerInfo.isirelBearerToken || '';
        args.studentRegistryCache = await getStudentRegistryCache();

        log('Import pagoPA ISIREL');
        log('--------------------');
        log(`Periodo: ${formatDateItalian(args.startDate)} -> ${formatDateItalian(args.endDate)}`);
        log(`Anno scolastico: ${args.schoolYear}`);
        if (args.bearerToken) {
            log('Token ISIREL: trovato dalle richieste del portale.');
            if (bearerInfo.isirelBearerTokenSeenAt) {
                log(`Token rilevato alle: ${new Date(bearerInfo.isirelBearerTokenSeenAt).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' })}`);
            }
        } else {
            log('Token ISIREL: non ancora rilevato. Se sei in home, attendi qualche secondo o ricarica la pagina ISIREL.');
        }
        log('Controllo scheda ISIREL attiva...');

        const res = await runImportInPage(args);
        if (streamedLogCount === 0) {
            logTrace(res.trace, {
                remote: false
            });
        }

        const cachedUpdates = await mergeStudentRegistryCache(res.studentCacheUpdates);
        if (cachedUpdates > 0) {
            log(`Cache studenti aggiornata: ${cachedUpdates} nuove anagrafiche salvate.`);
        }

        log('Riepilogo finale');
        log(`- Attivita lette da ISIREL: ${res.activities}`);
        log(`- Studenti distinti letti da ISIREL: ${res.students}`);
        log(`- Avvisi salvati in GestOre: ${res.gestore && res.gestore.recipients !== undefined ? res.gestore.recipients : '-'}`);
        log(`- Studenti mappati in GestOre: ${res.gestore && res.gestore.mappedStudents !== undefined ? res.gestore.mappedStudents : '-'}`);
        if (res.pdfArchive) {
        log(`- PDF archiviati: ${res.pdfArchive.saved} salvati, ${res.pdfArchive.skipped} gia presenti/saltati, ${res.pdfArchive.errors} errori`);
        }

        activitiesCount.textContent = String(res.activities);
        studentsCount.textContent = String(res.students);
        setStatus('ok', 'Import completato');
        setStopButtonsVisible(false);
        setCloseButtonsVisible(true);
        scrollPopupToBottom();

    } catch (e) {
        if (streamedLogCount === 0) {
            logTrace(e.trace);
        }
        if (cancelRequested || /interrotta/i.test(e.message || '')) {
            log('Importazione interrotta.');
            setStatus('error', 'Interrotto');
        } else {
            log('ERRORE: ' + (e.message || String(e)));
            setStatus('error', 'Errore');
        }
        setStopButtonsVisible(false);
        setCloseButtonsVisible(true);
        scrollPopupToBottom();
    } finally {
        await sendRuntimeMessage({
            type: 'pagopa-import-state',
            running: false,
            runId: activeRunId
        });
        activeRunId = null;
        importRunning = false;
        cancelRequested = false;
        importBtn.disabled = false;
    }
});
