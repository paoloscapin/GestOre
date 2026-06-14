<?php

require_once '../common/checkSession.php';
require_once '../common/programmiPubbliciLib.php';

ruoloRichiesto('genitore');

if (!programmiPubbliciVisibleForRole('svolti', 'genitore')) {
    redirect('/error/unauthorized.php');
}

$students = programmiPubbliciGenitoreStudents((int)$__genitore_id);
$selectedStudentId = intval($_GET['studente_id'] ?? 0);
if ($selectedStudentId <= 0 && !empty($students)) {
    $selectedStudentId = intval($students[0]['studente_id'] ?? 0);
}
if ($selectedStudentId > 0 && !programmiPubbliciGenitoreCanAccessStudent((int)$__genitore_id, $selectedStudentId)) {
    $selectedStudentId = 0;
}

$context = $selectedStudentId > 0 ? programmiPubbliciStudentContext($selectedStudentId) : null;
$anniSvolti = $context ? programmiPubbliciSvoltiYearsForStudent($selectedStudentId) : [];
$selectedAnnoId = intval($_GET['anno_id'] ?? 0);
if ($selectedAnnoId <= 0) {
    $selectedAnnoId = intval($__anno_scolastico_corrente_id ?? 0);
}
if ($selectedAnnoId > 0 && !in_array($selectedAnnoId, array_map('intval', array_column($anniSvolti, 'id')), true)) {
    $selectedAnnoId = !empty($anniSvolti) ? intval($anniSvolti[0]['id']) : 0;
}
$svolti = $context ? programmiPubbliciRowsForStudent('svolti', $selectedStudentId, $selectedAnnoId) : [];

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
    require_once '../common/header-genitore-mobile.php';
} else {
    require_once '../common/header-genitore.php';
}
?>

<div class="container-fluid">
    <div class="panel panel-orange4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-education"></span>&emsp;Programmi svolti
        </div>
        <div class="panel-body">
            <?php if (empty($students)): ?>
                <div class="alert alert-warning">Non risultano studenti attivi collegati al genitore.</div>
            <?php else: ?>
                <form method="get" class="programmi-pubblici-toolbar">
                    <div>
                        <label for="studente_id">Studente</label>
                        <select id="studente_id" name="studente_id" class="selectpicker" data-live-search="true" data-width="260px" onchange="this.form.submit()">
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo intval($student['studente_id']); ?>" <?php echo intval($student['studente_id']) === $selectedStudentId ? 'selected' : ''; ?>>
                                    <?php echo programmiPubbliciH(($student['studente_cognome'] ?? '') . ' ' . ($student['studente_nome'] ?? '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (!empty($anniSvolti)): ?>
                        <div>
                            <label for="anno_id">Anno scolastico</label>
                            <select id="anno_id" name="anno_id" class="selectpicker" data-width="220px" onchange="this.form.submit()">
                                <?php foreach ($anniSvolti as $anno): ?>
                                    <option value="<?php echo intval($anno['id']); ?>" <?php echo intval($anno['id']) === $selectedAnnoId ? 'selected' : ''; ?>>
                                        <?php echo programmiPubbliciH($anno['anno'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </form>

                <?php if ($context == null): ?>
                    <div class="alert alert-warning">Non risulta una classe corrente associata allo studente selezionato.</div>
                <?php else: ?>
                    <?php echo programmiPubbliciRenderContext($context); ?>
                    <?php echo programmiPubbliciRenderRows($svolti, 'svolti', $selectedStudentId); ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
