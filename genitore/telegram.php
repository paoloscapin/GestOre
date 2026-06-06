<?php

require_once '../common/checkSession.php';

ruoloRichiesto('genitore');

if (!getSettingsValue('profiloGenitore', 'visibile_telegram', false)) {
    http_response_code(403);
    die('Telegram genitore non abilitato.');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Telegram Genitore</title>
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    ?>
    <script src="<?php echo $__application_base_path; ?>/genitore/js/telegram.js?v=<?php echo @filemtime(__DIR__ . '/js/telegram.js') ?: time(); ?>"></script>
</head>
<body>
<?php
function genitoreIsMobileTelegram()
{
    return preg_match("/Android|iPhone|iPad|iPod|Opera Mini|IEMobile|Mobile|BlackBerry|webOS/i", $_SERVER['HTTP_USER_AGENT'] ?? '');
}

if (genitoreIsMobileTelegram()) {
    require_once '../common/header-genitore-mobile.php';
} else {
    require_once '../common/header-genitore.php';
}
?>
<div class="container-fluid">
    <div class="panel panel-orange4">
        <div class="panel-heading"><span class="glyphicon glyphicon-send"></span>&ensp;Telegram Genitore</div>
        <div class="panel-body">
            <div class="alert alert-info">
                Qui puoi collegare il tuo account Telegram a GestOre. Il link personale di attivazione ti verrà inviato alla mail del profilo.
            </div>
            <div class="alert alert-warning">
                Dopo il collegamento puoi abilitare Telegram nelle preferenze notifiche del profilo. Se scrivi a GestOre da questa chat, il messaggio arriva all'assistenza come ticket.
            </div>
            <div id="genitore-telegram-status-box">
                <div class="alert alert-default">Caricamento stato Telegram in corso...</div>
            </div>
            <p style="margin-top:16px;">
                <a href="profilo.php" class="btn btn-default">Torna al profilo</a>
            </p>
        </div>
    </div>
</div>
</body>
</html>
