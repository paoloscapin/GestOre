<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/__Util.php';
require_once '../common/path.php';
require_once '../common/connect.php';
require_once '../common/__Settings.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_name('GESTORESESSID');
    @session_start();
}

$loginMastercomAssistenza = $_SESSION['login_mastercom_assistenza'] ?? null;
$loginMastercomAssistenzaValida = is_array($loginMastercomAssistenza)
    && !empty($loginMastercomAssistenza['token'])
    && intval($loginMastercomAssistenza['expires_at'] ?? 0) >= time();

?>



<!DOCTYPE html>
<html>
<head>
	<title>GestOre Error</title>
</head>

<body >
<?php
	require_once '../common/header-error-min.php';
?>

<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-success">
<div class="panel-heading">Errore durante l'esecuzione dell'applicazione</div>
<div class="panel-body">
    <div class="row">
        <div class="col-md-12">
<?php
if (isset($_GET['message'])) {
    echo '<h4>' . htmlspecialchars(urldecode($_GET['message']), ENT_QUOTES, 'UTF-8') . '</h4>';
    if ($loginMastercomAssistenzaValida) {
        echo '<p style="margin-top:18px;">';
        echo '<a class="btn btn-warning" href="login_mastercom_assistenza.php?token=' . urlencode((string)$loginMastercomAssistenza['token']) . '">';
        echo 'Richiedi assistenza su questo problema';
        echo '</a>';
        echo '</p>';
    }
    echo '<h4><code>Per eventuali segnalazioni, scrivi a <a href="mailto:registroelettronico@buonarroti.tn.it">registroelettronico@buonarroti.tn.it</a></code></h4>';
}
?>
        </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>
</div>

<!-- Bootstrap, jquery etc (css + js) -->
<?php
	require_once '../common/style.php';
?>

<!-- Custom JS file -->
</body>
</html>
