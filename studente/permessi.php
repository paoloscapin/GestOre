<?php

require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';

ruoloRichiesto('studente');

if (!getSettingsValue('config', 'permessi', false) || !getSettingsValue('permessi', 'visibile_studenti', false)) {
    redirect('/error/unauthorized.php');
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Permessi di uscita</title>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
</head>
<body>
<?php require_once '../common/header-studente.php'; ?>

<div class="container-fluid">
    <div class="panel panel-lightblue4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-log-out"></span>&ensp;Permessi di uscita
        </div>
        <div class="panel-body">
            <div class="alert alert-info">
                I permessi sono visibili in sola lettura. Le richieste e le modifiche restano riservate ai genitori.
            </div>
            <div class="records_content"></div>
        </div>
    </div>
</div>

<script>
function permessiReadRecords() {
    $.post('permessiReadRecords.php', {}, function (data) {
        $('.records_content').html(data);
        $('[data-toggle="tooltip"]').tooltip({ trigger: 'hover', container: 'body' });
    });
}

$(document).ready(function () {
    permessiReadRecords();
});
</script>
</body>
</html>
