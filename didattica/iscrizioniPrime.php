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
$mailTemplate = iscrizioniPrimeMailTemplate('prime');
$mailAttachments = iscrizioniPrimeMailAttachments('prime');

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <title>Iscrizioni prime</title>
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    ?>
</head>
<body>
<?php require_once '../common/header-didattica.php'; ?>

<div class="container-fluid">
    <div class="panel panel-lightblue4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-folder-open"></span>&ensp;Iscrizioni future classi prime
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-3"><strong>Pratiche:</strong> <?php echo intval($stats['totale'] ?? 0); ?></div>
                <div class="col-md-3"><strong>Bozze:</strong> <?php echo intval($stats['bozze'] ?? 0); ?></div>
                <div class="col-md-3"><strong>Inviate:</strong> <?php echo intval($stats['inviate'] ?? 0); ?></div>
                <div class="col-md-3"><strong>Con email:</strong> <?php echo intval($stats['con_email'] ?? 0); ?></div>
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
            <hr>
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Testo mail e allegati</strong></div>
                <div class="panel-body">
                    <form id="iscrizioni_prime_mail_template_form">
                        <input type="hidden" name="tipo_iscrizione" value="prime">
                        <div class="form-group">
                            <label>Oggetto mail</label>
                            <input type="text" name="subject" class="form-control" value="<?php echo htmlspecialchars((string)($mailTemplate['subject'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Lascia vuoto per usare l'oggetto automatico">
                        </div>
                        <div class="form-group">
                            <label>Testo mail</label>
                            <textarea name="body_html" class="form-control" rows="8" placeholder="Lascia vuoto per usare il testo automatico"><?php echo htmlspecialchars((string)($mailTemplate['body_html'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                            <span class="help-block">Segnaposto disponibili: {link}, {studente}, {nome}, {cognome}, {corso}, {anno}, {istituto}. Puoi usare anche semplice HTML.</span>
                        </div>
                        <button type="submit" class="btn btn-default"><span class="glyphicon glyphicon-floppy-disk"></span> Salva testo mail</button>
                    </form>
                    <hr>
                    <form id="iscrizioni_prime_mail_attachment_form" enctype="multipart/form-data">
                        <input type="hidden" name="tipo_iscrizione" value="prime">
                        <div class="form-inline">
                            <input type="file" name="pdf" accept="application/pdf,.pdf" class="form-control" required>
                            <button type="submit" class="btn btn-default"><span class="glyphicon glyphicon-paperclip"></span> Aggiungi PDF allegato</button>
                        </div>
                    </form>
                    <div style="margin-top:8px;">
                        <?php foreach ($mailAttachments as $attachment) : ?>
                            <span class="label label-info"><?php echo htmlspecialchars((string)$attachment['original_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <hr>
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
            <div id="iscrizioni_prime_result" class="alert" style="display:none;"></div>
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
                            <th>Token</th>
                            <th>Test</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="7" class="text-muted">Caricamento...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function iscrizioniPrimeEscape(value) {
    return String(value || '').replace(/[&<>"']/g, function (char) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
    });
}

function iscrizioniPrimeLoadTable() {
    const tbody = document.querySelector('#iscrizioni_prime_table tbody');
    tbody.innerHTML = '<tr><td colspan="7" class="text-muted">Caricamento...</td></tr>';

    fetch('iscrizioniPrimeRead.php?tipo_iscrizione=prime', {credentials: 'same-origin'})
        .then(response => response.json())
        .then(data => {
            if (!data.ok) {
                throw new Error(data.message || 'Errore lettura pratiche');
            }

            if (!data.rows.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-muted">Nessuna pratica importata.</td></tr>';
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
                    '<td>' + token + '</td>' +
                    '<td><button type="button" class="btn btn-xs btn-info" onclick="iscrizioniPrimeOpenTestLink(' + Number(row.id) + ')"><span class="glyphicon glyphicon-new-window"></span> Apri</button></td>' +
                    '</tr>';
            }).join('');
        })
        .catch(error => {
            tbody.innerHTML = '<tr><td colspan="7" class="text-danger">' + iscrizioniPrimeEscape(error.message) + '</td></tr>';
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
            '<br>Mail ' + (dryRun ? 'simulabili' : 'inviate') + ': ' + iscrizioniPrimeEscape(data.sent) +
            ' - saltate: ' + iscrizioniPrimeEscape(data.skipped || 0) +
            (data.errors && data.errors.length ? '<br>Errori: ' + data.errors.map(iscrizioniPrimeEscape).join(', ') : '');
        iscrizioniPrimeLoadTable();
    })
    .catch(error => {
        result.className = 'alert alert-danger';
        result.textContent = error.message;
    });
}

function iscrizioniPrimeSendTestMail() {
    const result = document.getElementById('iscrizioni_prime_result');
    result.className = 'alert alert-info';
    result.style.display = 'block';
    result.textContent = 'Invio mail di test in corso...';

    fetch('iscrizioniPrimeMailTest.php', {
        method: 'POST',
        body: (() => { const fd = new FormData(); fd.append('tipo_iscrizione', 'prime'); return fd; })(),
        credentials: 'same-origin'
    })
    .then(response => response.json())
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
    });
}

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

document.getElementById('iscrizioni_prime_mail_template_form').addEventListener('submit', function (event) {
    event.preventDefault();
    fetch('iscrizioniPrimeMailTemplateSave.php', { method: 'POST', body: new FormData(event.target), credentials: 'same-origin' })
        .then(response => response.json().then(data => ({ok: response.ok, data})))
        .then(result => {
            if (!result.ok || !result.data.ok) throw new Error(result.data.message || 'Errore salvataggio testo mail');
            alert(result.data.message || 'Testo mail salvato.');
        })
        .catch(error => alert(error.message));
});

document.getElementById('iscrizioni_prime_mail_attachment_form').addEventListener('submit', function (event) {
    event.preventDefault();
    fetch('iscrizioniPrimeMailAttachmentUpload.php', { method: 'POST', body: new FormData(event.target), credentials: 'same-origin' })
        .then(response => response.json().then(data => ({ok: response.ok, data})))
        .then(result => {
            if (!result.ok || !result.data.ok) throw new Error(result.data.message || 'Errore caricamento allegato');
            alert(result.data.message || 'Allegato caricato.');
            window.location.reload();
        })
        .catch(error => alert(error.message));
});

iscrizioniPrimeLoadTable();
</script>

</body>
</html>
