<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

iscrizioniPrimeEnsureSchema();

$stats = dbGetFirst("
    SELECT
        COUNT(*) AS totale,
        SUM(stato = 'importata') AS importate,
        SUM(stato = 'bozza') AS bozze,
        SUM(stato = 'inviata') AS inviate,
        SUM(stato = 'verificata') AS verificate,
        SUM(email_genitore_1 IS NOT NULL OR email_genitore_2 IS NOT NULL) AS con_email
    FROM iscrizioni_prime_pratiche
    WHERE tipo_iscrizione = 'prime'
");
$draftSubject = iscrizioniPrimeDraftSubject('prime');

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <title>Iscrizioni prime</title>
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    ?>
    <style>
        .iscrizioni-mail-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(15, 23, 42, 0.62);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
        }
        .iscrizioni-mail-card {
            width: min(620px, 100%);
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 18px 60px rgba(15, 23, 42, 0.35);
            padding: 28px;
            text-align: center;
            border-top: 8px solid #0ea5e9;
        }
        .iscrizioni-mail-icon {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            background: #0ea5e9;
            font-size: 30px;
            margin-bottom: 12px;
        }
        .iscrizioni-mail-title {
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 6px;
        }
        .iscrizioni-mail-text {
            color: #475569;
            font-size: 16px;
            line-height: 1.45;
            margin-bottom: 18px;
        }
        .iscrizioni-mail-percent {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 8px;
        }
        .iscrizioni-mail-progress {
            height: 18px;
            background: #e2e8f0;
            border-radius: 999px;
            overflow: hidden;
            margin-bottom: 16px;
        }
        .iscrizioni-mail-progress-bar {
            height: 100%;
            width: 0;
            background: linear-gradient(90deg, #0ea5e9, #22c55e);
            transition: width .35s ease;
        }
        .iscrizioni-mail-progress-bar.is-running {
            width: 75%;
            background-size: 42px 42px;
            background-image: linear-gradient(45deg, rgba(255,255,255,.25) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.25) 50%, rgba(255,255,255,.25) 75%, transparent 75%, transparent);
            animation: iscrizioniMailStripe 1s linear infinite;
        }
        @keyframes iscrizioniMailStripe {
            from { background-position: 42px 0; }
            to { background-position: 0 0; }
        }
        .mail-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 12px;
            margin-bottom: 3px;
        }
        .mail-badge-real { background: #dcfce7; color: #166534; }
        .mail-badge-test { background: #dbeafe; color: #1d4ed8; }
        .mail-badge-none { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
<?php require_once '../common/header-didattica.php'; ?>

<div id="iscrizioni_mail_overlay" class="iscrizioni-mail-overlay" role="status" aria-live="polite">
    <div class="iscrizioni-mail-card">
        <div id="iscrizioni_mail_icon" class="iscrizioni-mail-icon">
            <span class="glyphicon glyphicon-send"></span>
        </div>
        <div id="iscrizioni_mail_title" class="iscrizioni-mail-title">Invio mail in corso</div>
        <div id="iscrizioni_mail_text" class="iscrizioni-mail-text">GestOre sta preparando e inviando il lotto. Tieni aperta questa pagina.</div>
        <div id="iscrizioni_mail_percent" class="iscrizioni-mail-percent">0%</div>
        <div class="iscrizioni-mail-progress">
            <div id="iscrizioni_mail_progress_bar" class="iscrizioni-mail-progress-bar is-running"></div>
        </div>
        <div id="iscrizioni_mail_details" class="text-muted"></div>
        <button type="button" id="iscrizioni_mail_close" class="btn btn-primary" style="display:none;margin-top:16px;" onclick="iscrizioniPrimeHideMailOverlay()">Chiudi</button>
    </div>
</div>

<div class="container-fluid">
    <div class="panel panel-lightblue4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-folder-open"></span>&ensp;Iscrizioni future classi prime
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-2"><strong>Pratiche:</strong> <span id="stat_totale"><?php echo intval($stats['totale'] ?? 0); ?></span></div>
                <div class="col-md-2"><strong>Bozze:</strong> <span id="stat_bozze"><?php echo intval($stats['bozze'] ?? 0); ?></span></div>
                <div class="col-md-2"><strong>Domande inviate:</strong> <span id="stat_domande_inviate"><?php echo intval($stats['inviate'] ?? 0); ?></span></div>
                <div class="col-md-2"><strong>Con email:</strong> <span id="stat_con_email"><?php echo intval($stats['con_email'] ?? 0); ?></span></div>
                <div class="col-md-2"><strong>Mail reali:</strong> <span id="stat_mail_reali">0</span></div>
                <div class="col-md-2"><strong>Mail test:</strong> <span id="stat_mail_test">0</span></div>
            </div>
            <div class="row" style="margin-top:14px;">
                <div class="col-md-12">
                    <a class="btn btn-success" href="iscrizioniPrimeLinkExport.php" onclick="return confirm('Generare un nuovo token per tutte le pratiche non chiuse e scaricare il CSV dei link? I link esportati in precedenza verranno sostituiti.');">
                        <span class="glyphicon glyphicon-envelope"></span> Esporta link famiglie
                    </a>
                    <button type="button" class="btn btn-default" onclick="iscrizioniPrimeLoadTable()">
                        <span class="glyphicon glyphicon-refresh"></span> Aggiorna elenco
                    </button>
                    <a class="btn btn-primary" href="iscrizioniPrimeDomande.php">
                        <span class="glyphicon glyphicon-inbox"></span> Domande inviate
                    </a>
                    <button type="button" class="btn btn-info" onclick="iscrizioniPrimeSendMail(1)">
                        <span class="glyphicon glyphicon-eye-open"></span> Simula invio mail
                    </button>
                    <button type="button" class="btn btn-info" onclick="iscrizioniPrimeSendTestMail()">
                        <span class="glyphicon glyphicon-envelope"></span> Invia test mail
                    </button>
                    <button type="button" class="btn btn-warning" onclick="iscrizioniPrimeSendMail(0)">
                        <span class="glyphicon glyphicon-send"></span> Invia prossimo lotto
                    </button>
                </div>
            </div>
            <div id="iscrizioni_prime_result" class="alert" style="display:none;margin-top:12px;"></div>
            <hr>
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Bozza Gmail per invio mail</strong></div>
                <div class="panel-body">
                    <form id="iscrizioni_prime_draft_subject_form">
                        <input type="hidden" name="tipo_iscrizione" value="prime">
                        <div class="form-group">
                            <label>Oggetto della mail BOZZA in Gmail</label>
                            <input type="text" name="draft_subject" class="form-control" value="<?php echo htmlspecialchars($draftSubject, ENT_QUOTES, 'UTF-8'); ?>" required>
                            <span class="help-block">
                                GestOre cerca questa bozza nelle caselle iscrizioni1, iscrizioni2, ecc. Nella bozza usa
                                <strong>{{LINK_PERSONALE}}</strong>, <strong>{{NOME_STUDENTE}}</strong> e
                                <strong>{blocco traduzioni}</strong>. Gli allegati presenti nella bozza vengono copiati nella mail inviata.
                            </span>
                        </div>
                        <button type="submit" class="btn btn-default">
                            <span class="glyphicon glyphicon-floppy-disk"></span> Salva oggetto bozza
                        </button>
                    </form>
                </div>
            </div>
            <form id="iscrizioni_prime_import_form" class="form-horizontal" enctype="multipart/form-data">
                <input type="hidden" name="tipo_iscrizione" value="prime">
                <div class="form-group">
                    <label class="col-sm-3 control-label">CSV iscrizioni PRIME</label>
                    <div class="col-sm-9">
                        <input type="file" name="prime_csv" accept=".csv,text/csv" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">CSV SAA DSA PRIME</label>
                    <div class="col-sm-9">
                        <input type="file" name="dsa_csv" accept=".csv,text/csv" class="form-control">
                        <span class="help-block">Opzionale: puoi caricarlo anche in un secondo momento per aggiornare DSA/104.</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">CSV esame licenza media</label>
                    <div class="col-sm-9">
                        <input type="file" name="licenza_media_csv" accept=".csv,text/csv" class="form-control">
                        <span class="help-block">Opzionale: importa scuola media di provenienza, anno esame, esito e voto.</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">CSV anagrafica responsabili</label>
                    <div class="col-sm-9">
                        <input type="file" name="anagrafica_csv" accept=".csv,text/csv" class="form-control">
                        <span class="help-block">Opzionale: aggiorna email, telefoni e responsabili. Gli studenti non presenti nelle pratiche prime vengono ignorati.</span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-3 col-sm-9">
                        <button type="submit" class="btn btn-primary">
                            <span class="glyphicon glyphicon-upload"></span> Importa pratiche
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="panel panel-lima4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-list"></span>&ensp;Pratiche importate
        </div>
        <div class="panel-body">
            <div class="table-responsive">
                <table class="table table-striped table-condensed" id="iscrizioni_prime_table">
                    <thead>
                        <tr>
                            <th>Studente</th>
                            <th>Codice fiscale</th>
                            <th>Corso</th>
                            <th>Stato</th>
                            <th>Email responsabili</th>
                            <th>Mail avviso</th>
                            <th>Token</th>
                            <th>Test</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="8" class="text-muted">Caricamento...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
let iscrizioniPrimeMailProgressTimer = null;
let iscrizioniPrimeMailProgressValue = 0;

function iscrizioniPrimeEscape(value) {
    return String(value || '').replace(/[&<>"']/g, function (char) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
    });
}

function iscrizioniPrimeSetText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value || 0;
}

function iscrizioniPrimeNumber(value) {
    return value === undefined || value === null || value === '' ? 0 : value;
}

function iscrizioniPrimeUpdateStats(stats, mailStats) {
    stats = stats || {};
    mailStats = mailStats || {};
    iscrizioniPrimeSetText('stat_totale', stats.totale || 0);
    iscrizioniPrimeSetText('stat_bozze', stats.bozze || 0);
    iscrizioniPrimeSetText('stat_domande_inviate', stats.domande_inviate || 0);
    iscrizioniPrimeSetText('stat_con_email', stats.con_email || 0);
    iscrizioniPrimeSetText('stat_mail_reali', mailStats.mail_reali || 0);
    iscrizioniPrimeSetText('stat_mail_test', mailStats.mail_test || 0);
}

function iscrizioniPrimeMailStatus(row) {
    const real = Number(row.mail_reali || 0);
    const test = Number(row.mail_test || 0);
    let html = '';
    if (real > 0) {
        html += '<span class="mail-badge mail-badge-real">Reale inviata</span>';
        if (row.last_real_sent_at) html += '<br><small>' + iscrizioniPrimeEscape(row.last_real_sent_at) + '</small>';
    } else if (test > 0) {
        html += '<span class="mail-badge mail-badge-test">Test inviato</span>';
        if (row.last_test_sent_at) html += '<br><small>' + iscrizioniPrimeEscape(row.last_test_sent_at) + '</small>';
    } else {
        html += '<span class="mail-badge mail-badge-none">Da inviare</span>';
    }
    if (real > 1 || test > 1) {
        html += '<br><small>reali ' + real + ' / test ' + test + '</small>';
    }
    return html;
}

function iscrizioniPrimeShowMailOverlay(title, text) {
    if (iscrizioniPrimeMailProgressTimer) {
        clearInterval(iscrizioniPrimeMailProgressTimer);
    }
    iscrizioniPrimeMailProgressValue = 3;
    document.getElementById('iscrizioni_mail_overlay').style.display = 'flex';
    document.getElementById('iscrizioni_mail_title').textContent = title;
    document.getElementById('iscrizioni_mail_text').textContent = text;
    document.getElementById('iscrizioni_mail_percent').textContent = iscrizioniPrimeMailProgressValue + '%';
    document.getElementById('iscrizioni_mail_details').textContent = '';
    document.getElementById('iscrizioni_mail_close').style.display = 'none';
    const bar = document.getElementById('iscrizioni_mail_progress_bar');
    bar.className = 'iscrizioni-mail-progress-bar';
    bar.style.width = iscrizioniPrimeMailProgressValue + '%';
    document.getElementById('iscrizioni_mail_icon').style.background = '#0ea5e9';
    document.getElementById('iscrizioni_mail_icon').innerHTML = '<span class="glyphicon glyphicon-send"></span>';

    iscrizioniPrimeMailProgressTimer = setInterval(function () {
        if (iscrizioniPrimeMailProgressValue < 70) {
            iscrizioniPrimeMailProgressValue += 4;
        } else if (iscrizioniPrimeMailProgressValue < 90) {
            iscrizioniPrimeMailProgressValue += 1;
        }
        document.getElementById('iscrizioni_mail_percent').textContent = iscrizioniPrimeMailProgressValue + '%';
        bar.style.width = iscrizioniPrimeMailProgressValue + '%';
    }, 900);
}

function iscrizioniPrimeCompleteMailOverlay(ok, title, text, details) {
    if (iscrizioniPrimeMailProgressTimer) {
        clearInterval(iscrizioniPrimeMailProgressTimer);
        iscrizioniPrimeMailProgressTimer = null;
    }
    document.getElementById('iscrizioni_mail_title').textContent = title;
    document.getElementById('iscrizioni_mail_text').textContent = text;
    document.getElementById('iscrizioni_mail_percent').textContent = ok ? '100%' : 'Errore';
    document.getElementById('iscrizioni_mail_details').innerHTML = details || '';
    document.getElementById('iscrizioni_mail_close').style.display = 'inline-block';
    const bar = document.getElementById('iscrizioni_mail_progress_bar');
    bar.className = 'iscrizioni-mail-progress-bar';
    bar.style.width = ok ? '100%' : '100%';
    bar.style.background = ok ? 'linear-gradient(90deg, #22c55e, #16a34a)' : '#dc2626';
    document.getElementById('iscrizioni_mail_icon').style.background = ok ? '#16a34a' : '#dc2626';
    document.getElementById('iscrizioni_mail_icon').innerHTML = ok ? '<span class="glyphicon glyphicon-ok"></span>' : '<span class="glyphicon glyphicon-alert"></span>';
}

function iscrizioniPrimeHideMailOverlay() {
    document.getElementById('iscrizioni_mail_overlay').style.display = 'none';
}

function iscrizioniPrimeLoadTable() {
    const tbody = document.querySelector('#iscrizioni_prime_table tbody');
    tbody.innerHTML = '<tr><td colspan="8" class="text-muted">Caricamento...</td></tr>';

    fetch('iscrizioniPrimeRead.php?tipo_iscrizione=prime', {credentials: 'same-origin'})
        .then(response => response.json())
        .then(data => {
            if (!data.ok) {
                throw new Error(data.message || 'Errore lettura pratiche');
            }
            iscrizioniPrimeUpdateStats(data.stats, data.mail_stats);

            if (!data.rows.length) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-muted">Nessuna pratica importata.</td></tr>';
                return;
            }

            tbody.innerHTML = data.rows.map(row => {
                const emails = [row.email_genitore_1, row.email_genitore_2].filter(Boolean).join('<br>');
                const token = row.token_last4 ? ('...' + row.token_last4) : '<span class="text-danger">da esportare</span>';

                return '<tr>' +
                    '<td><strong>' + iscrizioniPrimeEscape(row.cognome) + '</strong> ' + iscrizioniPrimeEscape(row.nome) + '</td>' +
                    '<td>' + iscrizioniPrimeEscape(row.codice_fiscale) + '</td>' +
                    '<td>' + iscrizioniPrimeEscape(row.corso_studi) + '</td>' +
                    '<td>' + iscrizioniPrimeEscape(row.stato) + '</td>' +
                    '<td>' + (emails || '<span class="text-danger">mancante</span>') + '</td>' +
                    '<td>' + iscrizioniPrimeMailStatus(row) + '</td>' +
                    '<td>' + token + '</td>' +
                    '<td><button type="button" class="btn btn-xs btn-info" onclick="iscrizioniPrimeOpenTestLink(' + Number(row.id) + ')"><span class="glyphicon glyphicon-new-window"></span> Apri</button></td>' +
                    '</tr>';
            }).join('');
        })
        .catch(error => {
            tbody.innerHTML = '<tr><td colspan="8" class="text-danger">' + iscrizioniPrimeEscape(error.message) + '</td></tr>';
        });
}

function iscrizioniPrimeOpenTestLink(id) {
    const formData = new FormData();
    formData.append('id', id);

    fetch('iscrizioniPrimeTestLink.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (!data.ok) {
            throw new Error(data.message || 'Errore generazione link');
        }
        window.open(data.link, '_blank', 'noopener');
        iscrizioniPrimeLoadTable();
    })
    .catch(error => alert(error.message));
}

function iscrizioniPrimeSendMail(dryRun) {
    const result = document.getElementById('iscrizioni_prime_result');

    if (!dryRun && !confirm('Inviare ora il prossimo lotto di mail alle famiglie usando gli account configurati?')) {
        return;
    }

    const formData = new FormData();
    formData.append('dry_run', dryRun ? '1' : '0');
    formData.append('tipo_iscrizione', 'prime');

    result.className = 'alert alert-info';
    result.style.display = 'block';
    result.textContent = dryRun ? 'Simulazione invio in corso...' : 'Invio mail in corso...';
    iscrizioniPrimeShowMailOverlay(
        dryRun ? 'Simulazione invio mail' : 'Invio lotto mail',
        dryRun ? 'GestOre sta calcolando quali mail verrebbero inviate.' : 'GestOre sta inviando il prossimo lotto. Tieni aperta questa pagina fino alla conferma finale.'
    );

    fetch('iscrizioniPrimeMailSend.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        result.className = data.ok ? 'alert alert-success' : 'alert alert-warning';
        result.innerHTML =
            iscrizioniPrimeEscape(data.message || '') +
            '<br>Mail ' + (dryRun ? 'simulabili' : 'inviate') + ': ' + iscrizioniPrimeEscape(iscrizioniPrimeNumber(data.sent)) +
            ' - saltate: ' + iscrizioniPrimeEscape(iscrizioniPrimeNumber(data.skipped)) +
            (data.errors && data.errors.length ? '<br>Errori: ' + data.errors.map(iscrizioniPrimeEscape).join(', ') : '');
        iscrizioniPrimeCompleteMailOverlay(
            !!data.ok,
            data.ok ? (dryRun ? 'Simulazione completata' : 'Lotto completato') : 'Lotto completato con avvisi',
            data.message || '',
            'Mail ' + (dryRun ? 'simulabili' : 'inviate') + ': <strong>' + iscrizioniPrimeEscape(iscrizioniPrimeNumber(data.sent)) + '</strong>' +
            ' &middot; Saltate: <strong>' + iscrizioniPrimeEscape(iscrizioniPrimeNumber(data.skipped)) + '</strong>' +
            (data.errors && data.errors.length ? '<br>Errori: ' + data.errors.map(iscrizioniPrimeEscape).join(', ') : '')
        );
        iscrizioniPrimeLoadTable();
    })
    .catch(error => {
        result.className = 'alert alert-danger';
        result.textContent = error.message;
        iscrizioniPrimeCompleteMailOverlay(false, 'Invio non riuscito', error.message, '');
    });
}

function iscrizioniPrimeSendTestMail() {
    const result = document.getElementById('iscrizioni_prime_result');
    result.className = 'alert alert-info';
    result.style.display = 'block';
    result.textContent = 'Invio mail di test in corso...';
    result.scrollIntoView({behavior: 'smooth', block: 'center'});

    fetch('iscrizioniPrimeMailTest.php', {
        method: 'POST',
        body: (() => { const fd = new FormData(); fd.append('tipo_iscrizione', 'prime'); return fd; })(),
        credentials: 'same-origin'
    })
    .then(response => response.text().then(text => {
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            throw new Error('Risposta non valida dal server. Verifica che la sessione sia attiva e riprova.');
        }
        if (!response.ok || !data.ok) {
            throw new Error(data.message || 'Invio mail di test non riuscito.');
        }
        return data;
    }))
    .then(data => {
        result.className = data.ok ? 'alert alert-success' : 'alert alert-danger';
        result.innerHTML = iscrizioniPrimeEscape(data.message || '') +
            (data.to ? '<br>Inviata a: ' + iscrizioniPrimeEscape(data.to) : '') +
            (data.original_recipient ? '<br>Destinatario reale simulato: ' + iscrizioniPrimeEscape(data.original_recipient) : '') +
            (data.student ? '<br>Studente: ' + iscrizioniPrimeEscape(data.student) : '');
        iscrizioniPrimeLoadTable();
    })
    .catch(error => {
        result.className = 'alert alert-danger';
        result.textContent = error.message;
        result.scrollIntoView({behavior: 'smooth', block: 'center'});
    });
}

document.getElementById('iscrizioni_prime_draft_subject_form').addEventListener('submit', function (event) {
    event.preventDefault();
    const result = document.getElementById('iscrizioni_prime_result');
    result.className = 'alert alert-info';
    result.style.display = 'block';
    result.textContent = 'Salvataggio oggetto bozza in corso...';
    fetch('iscrizioniPrimeDraftSubjectSave.php', { method: 'POST', body: new FormData(event.target), credentials: 'same-origin' })
        .then(response => response.json().then(data => ({ok: response.ok, data})))
        .then(resultData => {
            if (!resultData.ok || !resultData.data.ok) throw new Error(resultData.data.message || 'Errore salvataggio oggetto bozza');
            result.className = 'alert alert-success';
            result.textContent = resultData.data.message || 'Oggetto bozza salvato.';
        })
        .catch(error => {
            result.className = 'alert alert-danger';
            result.textContent = error.message;
        });
});

document.getElementById('iscrizioni_prime_import_form').addEventListener('submit', function (event) {
    event.preventDefault();

    const result = document.getElementById('iscrizioni_prime_result');
    result.className = 'alert alert-info';
    result.style.display = 'block';
    result.textContent = 'Importazione in corso...';

    fetch('iscrizioniPrimeImport.php', {
        method: 'POST',
        body: new FormData(event.target),
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (!data.ok) {
            throw new Error(data.message || 'Errore importazione');
        }

        result.className = 'alert alert-success';
        result.innerHTML =
            'Import completato: ' +
            data.inserted + ' nuove, ' +
            data.updated + ' aggiornate. ' +
            'Righe PRIME: ' + data.prime_rows + ', righe DSA: ' + data.dsa_rows + '. ' +
            'Righe licenza media: ' + (data.licenza_media_rows || 0) + '. ' +
            'Contatti aggiornati: ' + data.contacts_updated + ', anagrafiche ignorate: ' + data.contacts_ignored + '. ' +
            'Token nuovi generati: ' + data.generated_tokens + '.';
        iscrizioniPrimeLoadTable();
    })
    .catch(error => {
        result.className = 'alert alert-danger';
        result.textContent = error.message;
    });
});

iscrizioniPrimeLoadTable();
</script>

</body>
</html>
