<?php

require_once '../common/checkSession.php';
require_once '../common/programmiPubbliciLib.php';

ruoloRichiesto('studente');

if (!programmiPubbliciVisibleForRole('svolti', 'studente')) {
    redirect('/error/unauthorized.php');
}

$context = programmiPubbliciStudentContext((int)$__studente_id);
$anniSvolti = $context ? programmiPubbliciSvoltiYearsForStudent((int)$__studente_id) : [];
$selectedAnnoId = intval($_GET['anno_id'] ?? 0);
if ($selectedAnnoId <= 0) {
    $selectedAnnoId = intval($__anno_scolastico_corrente_id ?? 0);
}
if ($selectedAnnoId > 0 && !in_array($selectedAnnoId, array_map('intval', array_column($anniSvolti, 'id')), true)) {
    $selectedAnnoId = !empty($anniSvolti) ? intval($anniSvolti[0]['id']) : 0;
}
$svolti = $context ? programmiPubbliciRowsForStudent('svolti', (int)$__studente_id, $selectedAnnoId) : [];

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
    require_once '../common/_include_bootstrap-select.php';
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
                <?php if (!empty($anniSvolti)): ?>
                    <form method="get" class="programmi-pubblici-toolbar programmi-pubblici-toolbar-compact">
                        <label for="anno_id">Anno scolastico</label>
                        <select id="anno_id" name="anno_id" class="selectpicker" data-width="220px" onchange="this.form.submit()">
                            <?php foreach ($anniSvolti as $anno): ?>
                                <option value="<?php echo intval($anno['id']); ?>" <?php echo intval($anno['id']) === $selectedAnnoId ? 'selected' : ''; ?>>
                                    <?php echo programmiPubbliciH($anno['anno'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                <?php endif; ?>
                <?php echo programmiPubbliciRenderContext($context); ?>
                <?php echo programmiPubbliciRenderRows($svolti, 'svolti', (int)$__studente_id); ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
