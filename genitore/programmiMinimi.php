<?php

require_once '../common/checkSession.php';
require_once '../common/programmiPubbliciLib.php';

ruoloRichiesto('genitore');

if (!programmiPubbliciVisibleForRole('minimi', 'genitore')) {
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
$minimi = $context ? programmiPubbliciRowsForStudent('minimi', $selectedStudentId) : [];

function programmiPubbliciMinimiIsMobile(): bool
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
    <title>Programmi obiettivi minimi</title>
</head>
<body class="programmi-pubblici-page">
<?php
if (programmiPubbliciMinimiIsMobile()) {
    require_once '../common/header-genitore-mobile.php';
} else {
    require_once '../common/header-genitore.php';
}
?>

<div class="container-fluid">
    <div class="panel panel-orange4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-check"></span>&emsp;Programmi obiettivi minimi
        </div>
        <div class="panel-body">
            <?php if (empty($students)): ?>
                <div class="alert alert-warning">Non risultano studenti attivi collegati al genitore.</div>
            <?php else: ?>
                <form method="get" class="programmi-pubblici-toolbar">
                    <label for="studente_id">Studente</label>
                    <select id="studente_id" name="studente_id" class="selectpicker" data-live-search="true" data-width="260px" onchange="this.form.submit()">
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo intval($student['studente_id']); ?>" <?php echo intval($student['studente_id']) === $selectedStudentId ? 'selected' : ''; ?>>
                                <?php echo programmiPubbliciH(($student['studente_cognome'] ?? '') . ' ' . ($student['studente_nome'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <?php if ($context == null): ?>
                    <div class="alert alert-warning">Non risulta una classe corrente associata allo studente selezionato.</div>
                <?php else: ?>
                    <div class="programmi-pubblici-context">
                        <span><span>Studente</span><strong><?php echo programmiPubbliciH(($context['studente_cognome'] ?? '') . ' ' . ($context['studente_nome'] ?? '')); ?></strong></span>
                        <span><span>Classe</span><strong><?php echo programmiPubbliciH($context['classe'] ?? ''); ?></strong></span>
                        <span><span>Anno</span><strong><?php echo intval($context['anno_programmi'] ?? 0); ?></strong></span>
                        <?php if (intval($context['anno_programmi'] ?? 0) >= 3): ?>
                            <span><span>Indirizzo</span><strong><?php echo programmiPubbliciH($context['indirizzi_label'] ?? ''); ?></strong></span>
                        <?php endif; ?>
                    </div>

                    <?php echo programmiPubbliciRenderRows($minimi, 'minimi', $selectedStudentId); ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
