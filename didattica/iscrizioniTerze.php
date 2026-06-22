<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

iscrizioniPrimeEnsureSchema();

$stats = dbGetFirst("
    SELECT
        COUNT(*) AS totale,
        SUM(studente_interno = 1) AS interni,
        SUM(studente_interno = 0) AS esterni,
        SUM(stato = 'bozza' AND studente_interno = 0) AS bozze,
        SUM(stato = 'inviata' AND studente_interno = 0) AS inviate,
        SUM((email_genitore_1 IS NOT NULL OR email_genitore_2 IS NOT NULL) AND studente_interno = 0) AS esterni_con_email
    FROM iscrizioni_prime_pratiche
    WHERE tipo_iscrizione = 'terze'
");
$mailTemplate = iscrizioniPrimeMailTemplate('terze');
$mailAttachments = iscrizioniPrimeMailAttachments('terze');

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <title>Iscrizioni terze</title>
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
            <span class="glyphicon glyphicon-folder-open"></span>&ensp;Iscrizioni future classi terze
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-2"><strong>Totale:</strong> <?php echo intval($stats['totale'] ?? 0); ?></div>
                <div class="col-md-2"><strong>Interni:</strong> <?php echo intval($stats['interni'] ?? 0); ?></div>
                <div class="col-md-2"><strong>Esterni:</strong> <?php echo intval($stats['esterni'] ?? 0); ?></div>
                <div class="col-md-2"><strong>Bozze esterni:</strong> <?php echo intval($stats['bozze'] ?? 0); ?></div>
                <div class="col-md-2"><strong>Inviate:</strong> <?php echo intval($stats['inviate'] ?? 0); ?></div>
                <div class="col-md-2"><strong>Esterni con email:</strong> <?php echo intval($stats['esterni_con_email'] ?? 0); ?></div>
            </div>
            <div class="alert alert-info" style="margin-top:14px;">
                Gli studenti gia presenti in GestOre vengono segnati come interni e non ricevono il link. La raccolta dati e documenti viene inviata solo agli studenti esterni.
            </div>
            <div class="row" style="margin-top:14px;">
                <div class="col-md-12">
                    <button type="button" class="btn btn-default" onclick="iscrizioniTerzeLoadTable()">
                        <span class="glyphicon glyphicon-refresh"></span> Aggiorna elenco
                    </button>
                    <a class="btn btn-success" href="iscrizioniPrimeLinkExport.php?tipo_iscrizione=terze" onclick="return confirm('Generare un nuovo token per tutte le pratiche esterne non chiuse e scaricare il CSV dei link? I link esportati in precedenza verranno sostituiti.');">
                        <span class="glyphicon glyphicon-envelope"></span> Esporta link esterni
                    </a>
                    <a class="btn btn-primary" href="iscrizioniPrimeDomande.php?tipo_iscrizione=terze">
                        <span class="glyphicon glyphicon-inbox"></span> Domande inviate
                    </a>
                    <button type="button" class="btn btn-info" onclick="iscrizioniTerzeSendMail(1)">
                        <span class="glyphicon glyphicon-eye-open"></span> Simula invio mail esterni
                    </button>
                    <button type="button" class="btn btn-info" onclick="iscrizioniTerzeSendTestMail()">
                        <span class="glyphicon glyphicon-envelope"></span> Invia test mail esterni
                    </button>
                    <button type="button" class="btn btn-warning" onclick="iscrizioniTerzeSendMail(0)">
                        <span class="glyphicon glyphicon-send"></span> Invia prossimo lotto esterni
                    </button>
                </div>
            </div>
            <hr>
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Testo mail e allegati per esterni</strong></div>
                <div class="panel-body">
                    <form id="iscrizioni_terze_mail_template_form">
                        <input type="hidden" name="tipo_iscrizione" value="terze">
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
                    <form id="iscrizioni_terze_mail_attachment_form" enctype="multipart/form-data">
                        <input type="hidden" name="tipo_iscrizione" value="terze">
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
            <button type="button" class="btn btn-default" onclick="iscrizioniTerzeToggleManuale()">
                <span class="glyphicon glyphicon-plus"></span> Aggiungi pratica esterna manuale
            </button>
            <form id="iscrizioni_terze_manual_form" class="form-horizontal" style="display:none;margin-top:14px;">
                <div class="alert alert-warning">
                    Usa questa funzione per studenti esterni che devono sostenere esami integrativi e non sono ancora nel CSV ufficiale.
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Anno scolastico</label>
                    <div class="col-sm-2"><input type="text" name="anno_scolastico" value="2026/27" class="form-control"></div>
                    <label class="col-sm-2 control-label">Corso richiesto</label>
                    <div class="col-sm-6"><input type="text" name="corso_studi" class="form-control" placeholder="es. INFORMATICA"></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Cognome</label>
                    <div class="col-sm-4"><input type="text" name="cognome" class="form-control" required></div>
                    <label class="col-sm-2 control-label">Nome</label>
                    <div class="col-sm-4"><input type="text" name="nome" class="form-control" required></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Codice fiscale</label>
                    <div class="col-sm-4"><input type="text" name="codice_fiscale" maxlength="16" class="form-control" required></div>
                    <label class="col-sm-2 control-label">Data nascita</label>
                    <div class="col-sm-4"><input type="text" name="data_nascita" class="form-control" placeholder="gg/mm/aaaa"></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Email studente</label>
                    <div class="col-sm-4"><input type="email" name="email_studente" class="form-control"></div>
                    <label class="col-sm-2 control-label">Telefono studente</label>
                    <div class="col-sm-4"><input type="text" name="telefono_studente" class="form-control"></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Resp. 1 cognome</label>
                    <div class="col-sm-3"><input type="text" name="responsabile_1_cognome" class="form-control"></div>
                    <label class="col-sm-2 control-label">Resp. 1 nome</label>
                    <div class="col-sm-2"><input type="text" name="responsabile_1_nome" class="form-control"></div>
                    <label class="col-sm-1 control-label">Email</label>
                    <div class="col-sm-2"><input type="email" name="email_genitore_1" class="form-control"></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Resp. 2 cognome</label>
                    <div class="col-sm-3"><input type="text" name="responsabile_2_cognome" class="form-control"></div>
                    <label class="col-sm-2 control-label">Resp. 2 nome</label>
                    <div class="col-sm-2"><input type="text" name="responsabile_2_nome" class="form-control"></div>
                    <label class="col-sm-1 control-label">Email</label>
                    <div class="col-sm-2"><input type="email" name="email_genitore_2" class="form-control"></div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <button type="submit" class="btn btn-primary">
                            <span class="glyphicon glyphicon-ok"></span> Salva pratica manuale
                        </button>
                    </div>
                </div>
            </form>
            <hr>
            <form id="iscrizioni_terze_import_form" class="form-horizontal" enctype="multipart/form-data">
                <input type="hidden" name="tipo_iscrizione" value="terze">
                <div class="form-group">
                    <label class="col-sm-3 control-label">CSV iscrizioni TERZE</label>
                    <div class="col-sm-9">
                        <input type="file" name="prime_csv" accept=".csv,text/csv" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">CSV SAA DSA TERZE</label>
                    <div class="col-sm-9">
                        <input type="file" name="dsa_csv" accept=".csv,text/csv" class="form-control">
                        <span class="help-block">Opzionale: puoi caricarlo anche in un secondo momento per aggiornare DSA/104.</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">CSV anagrafica responsabili</label>
                    <div class="col-sm-9">
                        <input type="file" name="anagrafica_csv" accept=".csv,text/csv" class="form-control">
                        <span class="help-block">Puoi importarlo anche in un secondo momento: aggiorna email, telefoni e responsabili degli esterni.</span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-3 col-sm-9">
                        <button type="submit" class="btn btn-primary">
                            <span class="glyphicon glyphicon-upload"></span> Importa pratiche terze
                        </button>
                    </div>
                </div>
            </form>
            <div id="iscrizioni_terze_result" class="alert" style="display:none;"></div>
        </div>
    </div>

    <div class="panel panel-lima4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-list"></span>&ensp;Pratiche terze importate
        </div>
        <div class="panel-body">
            <div class="table-responsive">
                <table class="table table-striped table-condensed" id="iscrizioni_terze_table">
                    <thead>
                        <tr>
                            <th>Studente</th>
                            <th>Codice fiscale</th>
                            <th>Corso</th>
                            <th>Tipo</th>
                            <th>Stato</th>
                            <th>Email responsabili</th>
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
function iscrizioniTerzeEscape(value) {
    return String(value || '').replace(/[&<>"']/g, function (char) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
    });
}

function iscrizioniTerzeLoadTable() {
    const tbody = document.querySelector('#iscrizioni_terze_table tbody');
    tbody.innerHTML = '<tr><td colspan="8" class="text-muted">Caricamento...</td></tr>';

    fetch('iscrizioniPrimeRead.php?tipo_iscrizione=terze', {credentials: 'same-origin'})
        .then(response => response.json())
        .then(data => {
            if (!data.ok) throw new Error(data.message || 'Errore lettura pratiche');
            if (!data.rows.length) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-muted">Nessuna pratica importata.</td></tr>';
                return;
            }
            tbody.innerHTML = data.rows.map(row => {
                const emails = [row.email_genitore_1, row.email_genitore_2].filter(Boolean).join('<br>');
                const token = Number(row.studente_interno) ? '-' : (row.token_last4 ? ('...' + row.token_last4) : '<span class="text-danger">da esportare</span>');
                const tipo = Number(row.studente_interno) ? '<span class="label label-default">interno</span>' : '<span class="label label-warning">esterno</span>';
                const testButton = Number(row.studente_interno)
                    ? '<span class="text-muted">non richiesto</span>'
                    : '<button type="button" class="btn btn-xs btn-info" onclick="iscrizioniTerzeOpenTestLink(' + Number(row.id) + ')"><span class="glyphicon glyphicon-new-window"></span> Apri</button>';

                return '<tr>' +
                    '<td><strong>' + iscrizioniTerzeEscape(row.cognome) + '</strong> ' + iscrizioniTerzeEscape(row.nome) + '</td>' +
                    '<td>' + iscrizioniTerzeEscape(row.codice_fiscale) + '</td>' +
                    '<td>' + iscrizioniTerzeEscape(row.corso_studi) + '</td>' +
                    '<td>' + tipo + '</td>' +
                    '<td>' + iscrizioniTerzeEscape(row.stato) + '</td>' +
                    '<td>' + (emails || '<span class="text-danger">mancante</span>') + '</td>' +
                    '<td>' + token + '</td>' +
                    '<td>' + testButton + '</td>' +
                    '</tr>';
            }).join('');
        })
        .catch(error => {
            tbody.innerHTML = '<tr><td colspan="8" class="text-danger">' + iscrizioniTerzeEscape(error.message) + '</td></tr>';
        });
}

function iscrizioniTerzeOpenTestLink(id) {
    const formData = new FormData();
    formData.append('id', id);

    fetch('iscrizioniPrimeTestLink.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (!data.ok) throw new Error(data.message || 'Errore generazione link');
        window.open(data.link, '_blank', 'noopener');
        iscrizioniTerzeLoadTable();
    })
    .catch(error => alert(error.message));
}

function iscrizioniTerzeSendMail(dryRun) {
    const result = document.getElementById('iscrizioni_terze_result');
    if (!dryRun && !confirm('Inviare ora il prossimo lotto di mail agli studenti esterni delle terze?')) return;

    const formData = new FormData();
    formData.append('dry_run', dryRun ? '1' : '0');
    formData.append('tipo_iscrizione', 'terze');

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
            iscrizioniTerzeEscape(data.message || '') +
            '<br>Mail ' + (dryRun ? 'simulabili' : 'inviate') + ': ' + iscrizioniTerzeEscape(data.sent) +
            ' - saltate: ' + iscrizioniTerzeEscape(data.skipped || 0) +
            (data.errors && data.errors.length ? '<br>Errori: ' + data.errors.map(iscrizioniTerzeEscape).join(', ') : '');
        iscrizioniTerzeLoadTable();
    })
    .catch(error => {
        result.className = 'alert alert-danger';
        result.textContent = error.message;
    });
}

function iscrizioniTerzeSendTestMail() {
    const result = document.getElementById('iscrizioni_terze_result');
    const fd = new FormData();
    fd.append('tipo_iscrizione', 'terze');
    result.className = 'alert alert-info';
    result.style.display = 'block';
    result.textContent = 'Invio mail di test in corso...';

    fetch('iscrizioniPrimeMailTest.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        result.className = data.ok ? 'alert alert-success' : 'alert alert-danger';
        result.innerHTML = iscrizioniTerzeEscape(data.message || '') +
            (data.to ? '<br>Inviata a: ' + iscrizioniTerzeEscape(data.to) : '') +
            (data.original_recipient ? '<br>Destinatario reale simulato: ' + iscrizioniTerzeEscape(data.original_recipient) : '') +
            (data.student ? '<br>Studente: ' + iscrizioniTerzeEscape(data.student) : '');
        iscrizioniTerzeLoadTable();
    })
    .catch(error => {
        result.className = 'alert alert-danger';
        result.textContent = error.message;
    });
}

function iscrizioniTerzeToggleManuale() {
    const form = document.getElementById('iscrizioni_terze_manual_form');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

document.getElementById('iscrizioni_terze_manual_form').addEventListener('submit', function (event) {
    event.preventDefault();
    const result = document.getElementById('iscrizioni_terze_result');
    result.className = 'alert alert-info';
    result.style.display = 'block';
    result.textContent = 'Salvataggio pratica manuale in corso...';

    fetch('iscrizioniTerzeManualSave.php', {
        method: 'POST',
        body: new FormData(event.target),
        credentials: 'same-origin'
    })
    .then(response => response.json().then(data => ({ok: response.ok, data})))
    .then(resultData => {
        if (!resultData.ok || !resultData.data.ok) {
            throw new Error(resultData.data.message || 'Errore salvataggio pratica manuale');
        }
        result.className = 'alert alert-success';
        result.textContent = resultData.data.message || 'Pratica salvata.';
        event.target.reset();
        event.target.style.display = 'none';
        iscrizioniTerzeLoadTable();
    })
    .catch(error => {
        result.className = 'alert alert-danger';
        result.textContent = error.message;
    });
});

document.getElementById('iscrizioni_terze_import_form').addEventListener('submit', function (event) {
    event.preventDefault();
    const result = document.getElementById('iscrizioni_terze_result');
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
        if (!data.ok) throw new Error(data.message || 'Errore importazione');
        result.className = 'alert alert-success';
        result.innerHTML =
            'Import completato: ' +
            data.inserted + ' nuove, ' +
            data.updated + ' aggiornate. ' +
            'Interni: ' + data.interni + ', esterni: ' + data.esterni + '. ' +
            'Righe DSA: ' + data.dsa_rows + '. ' +
            'Contatti aggiornati: ' + data.contacts_updated + ', anagrafiche ignorate: ' + data.contacts_ignored + '. ' +
            'Interni non aggiornati: ' + (data.contacts_internal_skipped || 0) + '. ' +
            'Token nuovi generati: ' + data.generated_tokens + '.';
        iscrizioniTerzeLoadTable();
    })
    .catch(error => {
        result.className = 'alert alert-danger';
        result.textContent = error.message;
    });
});

document.getElementById('iscrizioni_terze_mail_template_form').addEventListener('submit', function (event) {
    event.preventDefault();
    fetch('iscrizioniPrimeMailTemplateSave.php', { method: 'POST', body: new FormData(event.target), credentials: 'same-origin' })
        .then(response => response.json().then(data => ({ok: response.ok, data})))
        .then(result => {
            if (!result.ok || !result.data.ok) throw new Error(result.data.message || 'Errore salvataggio testo mail');
            alert(result.data.message || 'Testo mail salvato.');
        })
        .catch(error => alert(error.message));
});

document.getElementById('iscrizioni_terze_mail_attachment_form').addEventListener('submit', function (event) {
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

iscrizioniTerzeLoadTable();
</script>
</body>
</html>
