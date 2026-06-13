<?php

require_once '../common/checkSession.php';
require_once '../common/programmiPubbliciLib.php';

ruoloRichiesto('studente');

if (!programmiPubbliciVisibleForRole('svolti', 'studente')) {
    redirect('/error/unauthorized.php');
}

$context = programmiPubbliciStudentContext((int)$__studente_id);
$svolti = $context ? programmiPubbliciRowsForStudent('svolti', (int)$__studente_id) : [];

function programmiPubbliciSvoltiIsMobile(): bool
{
    return preg_match("/Android|iPhone|iPad|iPod|Opera Mini|IEMobile|Mobile|BlackBerry|webOS/i", $_SERVER['HTTP_USER_AGENT'] ?? '') === 1;
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    ?>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/programmi-pubblici.css?v=<?php echo @filemtime(__DIR__ . '/../css/programmi-pubblici.css') ?: time(); ?>">
    <title>Programmi svolti</title>
</head>
<body class="programmi-pubblici-page">
<?php
if (programmiPubbliciSvoltiIsMobile()) {
    require_once '../common/header-studente-mobile.php';
} else {
    require_once '../common/header-studente.php';
}
?>

<div class="container-fluid">
    <div class="panel panel-lightblue4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-education"></span>&emsp;Programmi svolti
        </div>
        <div class="panel-body">
            <?php if ($context == null): ?>
                <div class="alert alert-warning">Non risulta una classe corrente associata allo studente.</div>
            <?php else: ?>
                <?php echo programmiPubbliciRenderContext($context); ?>
                <?php echo programmiPubbliciRenderRows($svolti, 'svolti', (int)$__studente_id); ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
