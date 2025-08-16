<?php
/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
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
                        echo '<h4>' . htmlspecialchars($_GET['message']) . '</h4>';
                    } else {
                        echo '<h4>Si è verificato un errore sconosciuto.</h4>';
                    }
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
