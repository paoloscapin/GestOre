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
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- responsive -->
    <title>GestOre Error</title>
    <?php require_once '../common/style.php'; ?>
</head>

<body>

    <?php require_once '../common/header-error-mobile.php'; ?>

    <div class="container" style="margin-top:80px;"> <!-- margine navbar -->
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header text-center text-danger fw-bold">
                        Errore durante l'esecuzione dell'applicazione
                    </div>
                    <div class="card-body text-center">
                        <?php
                        if (isset($_GET['message'])) {
                            $message = urldecode((string)$_GET['message']);
                            $isGoogleWrongAccount = stripos($message, 'mail utilizzata non') !== false
                                && stripos($message, 'anagrafica') !== false;
                            echo '<h4>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</h4>';
                            if ($isGoogleWrongAccount) {
                                echo '<div class="alert alert-info" style="margin-top:18px;text-align:left;">';
                                echo '<strong>Probabile account Google sbagliato.</strong><br>';
                                echo 'Se il telefono ha usato una Gmail personale o diversa da quella censita in GestOre, scegli l account Google corretto e riprova.';
                                echo '</div>';
                                echo '<p style="margin-top:16px;">';
                                echo '<a class="btn btn-primary btn-lg w-100" href="../common/googleAccountSwitch.php">';
                                echo 'Cambia account Google';
                                echo '</a>';
                                echo '</p>';
                                echo '<p>';
                                echo '<a class="btn btn-outline-secondary btn-lg w-100" href="../common/logout.php">';
                                echo 'Esci da GestOre';
                                echo '</a>';
                                echo '</p>';
                            }
                            if ($loginMastercomAssistenzaValida) {
                                echo '<p style="margin-top:18px;">';
                                echo '<a class="btn btn-warning btn-lg w-100" href="login_mastercom_assistenza.php?token=' . urlencode((string)$loginMastercomAssistenza['token']) . '">';
                                echo 'Richiedi assistenza su questo problema';
                                echo '</a>';
                                echo '</p>';
                            }
                        } else {
                            echo '<h4>Si è verificato un errore sconosciuto.</h4>';
                        }
                        echo '<h4><code>Per eventuali segnalazioni, scrivi a <a href="mailto:gestore@buonarroti.tn.it">gestore@buonarroti.tn.it</a></code></h4>';
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Bootstrap, jquery etc (css + js) -->
    <?php
    require_once '../common/style.php';
    ?>

    <!-- Custom JS file -->
</body>

</html>
