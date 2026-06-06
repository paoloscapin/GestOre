const logEl = document.getElementById('log');
const importBtn = document.getElementById('importBtn');
const statusPill = document.getElementById('statusPill');
const statusText = document.getElementById('statusText');
const activitiesCount = document.getElementById('activitiesCount');
const studentsCount = document.getElementById('studentsCount');
const logTime = document.getElementById('logTime');
const closeTopBtn = document.getElementById('closeTopBtn');
const closeBottomBtn = document.getElementById('closeBottomBtn');

function log(msg) {
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
}

function setStatus(kind, text) {
    statusPill.className = `status-pill status-${kind}`;
    statusText.textContent = text;
}

function setCloseButtonsVisible(visible) {
    closeTopBtn.classList.toggle('hidden', !visible);
    closeBottomBtn.classList.toggle('hidden', !visible);
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

function logTrace(trace) {
    if (!Array.isArray(trace) || trace.length === 0) {
        return;
    }

    for (const item of trace) {
        log(item);
    }
}

let activeRunId = null;
let streamedLogCount = 0;

chrome.runtime.onMessage.addListener((message) => {
    if (!message || message.type !== 'pagopa-import-log' || message.runId !== activeRunId) {
        return;
    }

    streamedLogCount++;
    log(message.message);
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
    const [tab] = await chrome.tabs.query({
        active: true,
        currentWindow: true
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
                    const r = await fetch(API_BASE + path, {
                        method: 'GET',
                        credentials: 'include',
                        headers: isirelHeaders()
                    });

                    if (!r.ok) {
                        throw new Error(`GET ${path} -> HTTP ${r.status}`);
                    }

                    return await r.json();
                }

                async function apiPost(path, body) {
                    const r = await fetch(API_BASE + path, {
                        method: 'POST',
                        credentials: 'include',
                        headers: isirelHeaders(),
                        body: JSON.stringify(body)
                    });

                    if (!r.ok) {
                        throw new Error(`POST ${path} -> HTTP ${r.status}`);
                    }

                    return await r.json();
                }

                async function sendToGestore(endpoint, payload) {
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

                    return json;
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

                async function blobToBase64(blob) {
                    const buffer = await blob.arrayBuffer();
                    const bytes = new Uint8Array(buffer);
                    const chunkSize = 0x8000;
                    let binary = '';

                    for (let i = 0; i < bytes.length; i += chunkSize) {
                        binary += String.fromCharCode.apply(null, bytes.subarray(i, i + chunkSize));
                    }

                    return btoa(binary);
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

                    const jobs = [];

                    for (const activity of activities) {
                        const recipients = Array.isArray(activity.recipients) ? activity.recipients : [];

                        for (const recipient of recipients) {
                            const link = recipient && recipient.paymentLink ? String(recipient.paymentLink) : '';

                            if (!link) {
                                continue;
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
                        traceLog('   Nessun link PDF disponibile negli avvisi letti.');
                        return stats;
                    }

                    traceLog(`   PDF disponibili da archiviare: ${jobs.length}`);

                    for (let i = 0; i < jobs.length; i++) {
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
                            const response = await fetch(pdfUrl, {
                                method: 'GET',
                                credentials: 'include',
                                headers: isirelHeaders({
                                    accept: 'application/pdf,application/octet-stream,*/*'
                                })
                            });

                            if (!response.ok) {
                                throw new Error(`ISIREL HTTP ${response.status}`);
                            }

                            const blob = await response.blob();
                            const contentType = response.headers.get('content-type') || blob.type || 'application/pdf';

                            if (!blob || blob.size < 20) {
                                throw new Error('PDF vuoto');
                            }

                            if (!/pdf|octet-stream/i.test(contentType)) {
                                throw new Error(`contenuto non PDF (${contentType})`);
                            }

                            const base64 = await blobToBase64(blob);
                            const fileName = fileNameFromDisposition(
                                response.headers.get('content-disposition'),
                                `avviso_pagopa_${recipient.idRecipient || 'isirel'}.pdf`
                            );

                            const result = await sendToGestore(pdfEndpoint, {
                                source: 'ISIREL',
                                idIsirelActivity: activity.id,
                                idRecipientIsirel: recipient.idRecipient,
                                idStudentIsirel: recipient.idStudent,
                                paymentIuv: recipient.paymentIuv || null,
                                fileName,
                                contentType,
                                sourceUrl: pdfUrl,
                                base64
                            });

                            if (result.skipped) {
                                stats.skipped++;
                            } else {
                                stats.saved++;
                            }
                        } catch (e) {
                            stats.errors++;
                            traceLog(`   PDF ${i + 1}/${jobs.length} non archiviato: recipient ${recipient.idRecipient || '-'} - ${e.message || String(e)}`);
                        }

                        if (i === 0 || i === jobs.length - 1 || (i + 1) % 25 === 0) {
                            traceLog(`   PDF ${i + 1}/${jobs.length}: salvati ${stats.saved}, gia presenti ${stats.skipped}, errori ${stats.errors}.`);
                        }
                    }

                    return stats;
                }

                traceLog('');
                traceLog('1. Lettura elenco avvisi da ISIREL');
                traceLog(`   Periodo: ${formatDateItalianInPage(args.startDate)} -> ${formatDateItalianInPage(args.endDate)}`);
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
                const gestoreResult = await sendToGestore(args.gestoreEndpoint, payload);
                traceLog(`   Risposta GestOre OK: ${gestoreResult.activities ?? activities.length} attivita, ${gestoreResult.recipients ?? recipientCount} avvisi, ${gestoreResult.mappedStudents ?? 0} studenti mappati.`);
                traceLog('');
                traceLog('6. Archivio PDF disponibili');
                const pdfArchive = await archiveAvailablePdfs(activities);
                traceLog(`   Archivio PDF: ${pdfArchive.saved} salvati, ${pdfArchive.skipped} gia presenti, ${pdfArchive.errors} errori.`);
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

importBtn.addEventListener('click', async () => {
    logEl.textContent = '';
    activitiesCount.textContent = '-';
    studentsCount.textContent = '-';
    setCloseButtonsVisible(false);
    streamedLogCount = 0;
    activeRunId = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    importBtn.disabled = true;
    setStatus('running', 'Importazione in corso');

    try {
        const args = {
            startDate: document.getElementById('startDate').value,
            endDate: document.getElementById('endDate').value,
            schoolYear: document.getElementById('schoolYear').value,
            gestoreEndpoint: document.getElementById('gestoreEndpoint').value,
            runId: activeRunId
        };

        args.gestorePdfEndpoint = args.gestoreEndpoint.replace(/importaPagopa\.php(?:\?.*)?$/i, 'importaPagopaPdf.php');

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
            logTrace(res.trace);
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
            log(`- PDF archiviati: ${res.pdfArchive.saved} salvati, ${res.pdfArchive.skipped} gia presenti, ${res.pdfArchive.errors} errori`);
        }

        activitiesCount.textContent = String(res.activities);
        studentsCount.textContent = String(res.students);
        setStatus('ok', 'Import completato');
        setCloseButtonsVisible(true);
        scrollPopupToBottom();

    } catch (e) {
        if (streamedLogCount === 0) {
            logTrace(e.trace);
        }
        log('ERRORE: ' + (e.message || String(e)));
        setStatus('error', 'Errore');
        setCloseButtonsVisible(true);
        scrollPopupToBottom();
    } finally {
        activeRunId = null;
        importBtn.disabled = false;
    }
});
