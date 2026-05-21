<?php

require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-notify.php';
require_once '../common/programmiSvoltiCopertineLib.php';

ruoloRichiesto('segreteria-didattica', 'dirigente');

?>
<!DOCTYPE html>
<html>
<head>
    <script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
    <title>Gestione copertine verifiche</title>
    <style>
        #copertine_overlay {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,.78);
            align-items: center;
            justify-content: center;
        }
        #copertine_overlay .box {
            min-width: 340px;
            max-width: 460px;
            background: #fff;
            border: 1px solid #d7e3f0;
            border-radius: 8px;
            box-shadow: 0 12px 34px rgba(15,23,42,.18);
            padding: 20px 24px;
            text-align: center;
        }
        .copertine-actions {
            white-space: nowrap;
        }
    </style>
</head>
<body>
<?php require_once '../common/header-didattica.php'; ?>

<div id="copertine_overlay">
    <div class="box">
        <div style="font-weight:800;color:#1f5e3b;margin-bottom:10px;">Copertine verifiche</div>
        <div id="copertine_overlay_text">Preparazione...</div>
    </div>
</div>

<div class="container-fluid">
    <div class="panel panel-lightblue4">
        <div class="panel-heading">
            <div class="row">
                <div class="col-md-4" style="padding-top:7px;">
                    <span class="glyphicon glyphicon-folder-open"></span>&ensp;Gestione copertine verifiche
                </div>
                <div class="col-md-8 text-right">
                    <button type="button" class="btn btn-success btn-sm" onclick="programmiSvoltiCopertineGenerate()">
                        <span class="glyphicon glyphicon-print"></span> Genera PDF richiesti
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="programmiSvoltiCopertinePrintGenerated()">
                        <span class="glyphicon glyphicon-print"></span> Stampa PDF generati
                    </button>
                    <button type="button" class="btn btn-default btn-sm" onclick="programmiSvoltiCopertineReadRecords()">
                        <span class="glyphicon glyphicon-refresh"></span> Aggiorna elenco
                    </button>
                </div>
            </div>
        </div>
        <div class="panel-body">
            <?php if (!programmiSvoltiCopertineTableExists()) : ?>
                <div class="alert alert-warning">
                    Tabella <code>programmi_svolti_copertine</code> non presente. Esegui la migrazione SQL prima di usare la gestione copertine.
                </div>
            <?php endif; ?>
            <div class="records_content"></div>
        </div>
    </div>
</div>

<script>
function programmiSvoltiCopertineReadRecords() {
    $.get('programmiSvoltiCopertineReadRecords.php', {}, function (data) {
        $('.records_content').html(data);
        $('[data-toggle="tooltip"]').tooltip({ container: 'body' });
    });
}

function programmiSvoltiCopertineOverlay(text) {
    $('#copertine_overlay_text').text(text || 'Operazione in corso...');
    $('#copertine_overlay').css('display', 'flex');
}

function programmiSvoltiCopertineOverlayHide() {
    $('#copertine_overlay').fadeOut(150);
}

function programmiSvoltiCopertinePrintGenerated() {
    if (!confirm('Stampare in blocco tutte le copertine generate non ancora stampate? Lo stato passera a stampato.')) {
        return;
    }
    programmiSvoltiCopertineOverlay('Preparo il PDF unico per la stampa...');
    window.open('programmiSvoltiCopertinePrint.php', '_blank');
    setTimeout(function () {
        programmiSvoltiCopertineOverlayHide();
        programmiSvoltiCopertineReadRecords();
    }, 1800);
}

function programmiSvoltiCopertineGenerate() {
    if (!confirm('Generare e archiviare su Drive tutte le copertine richieste?')) {
        return;
    }
    programmiSvoltiCopertineOverlay('Creo i PDF A3 e li carico su Drive...');
    $.post('programmiSvoltiCopertineGenerate.php', {}, function (response) {
        var result = typeof response === 'string' ? JSON.parse(response) : response;
        programmiSvoltiCopertineOverlayHide();
        if (!result || result.ok === false) {
            alert((result && result.message) ? result.message : 'Errore durante la generazione.');
            programmiSvoltiCopertineReadRecords();
            return;
        }
        alert(result.message || 'Copertine generate.');
        programmiSvoltiCopertineReadRecords();
    }).fail(function (xhr) {
        programmiSvoltiCopertineOverlayHide();
        var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Errore di connessione.';
        alert(message);
        programmiSvoltiCopertineReadRecords();
    });
}

function programmiSvoltiCopertineRegenerate(id) {
    if (!confirm('Rigenerare questa copertina e sostituire il PDF archiviato su Drive? Lo stato tornera a generato.')) {
        return;
    }
    programmiSvoltiCopertineOverlay('Rigenero la copertina e sostituisco il PDF su Drive...');
    $.post('programmiSvoltiCopertineGenerate.php', { id: id }, function (response) {
        var result = typeof response === 'string' ? JSON.parse(response) : response;
        programmiSvoltiCopertineOverlayHide();
        if (!result || result.ok === false) {
            alert((result && result.message) ? result.message : 'Errore durante la rigenerazione.');
            programmiSvoltiCopertineReadRecords();
            return;
        }
        alert(result.message || 'Copertina rigenerata.');
        programmiSvoltiCopertineReadRecords();
    }).fail(function (xhr) {
        programmiSvoltiCopertineOverlayHide();
        var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Errore di connessione.';
        alert(message);
        programmiSvoltiCopertineReadRecords();
    });
}

$(document).ready(function () {
    programmiSvoltiCopertineReadRecords();
});
</script>
</body>
</html>
