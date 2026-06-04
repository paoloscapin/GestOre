<?php

/**
 *  This file is part of GestOre
 *  @author     OpenAI Codex
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/mastercom/tag_print_lib.php';
require_once '../common/mastercom/tag_report_lib.php';

ruoloRichiesto('docente', 'segreteria-didattica', 'admin');
$docenteDaParametro = applicaDocenteDaParametroSeAutorizzato();

$docenteId = intval($__docente_id ?? 0);
$impersonaDocenteAttiva = isset($session)
    && intval($session->get('impersona_attiva') ?? 0) === 1
    && (string)($session->get('impersona_ruolo') ?? '') === 'docente'
    && $docenteId > 0;
$docenteScopeActive = $docenteId > 0 && ($docenteDaParametro !== null || ($__utente_ruolo ?? '') === 'docente' || $impersonaDocenteAttiva);
$adminMode = (haRuolo('admin') || haRuolo('segreteria-didattica')) && !$docenteScopeActive;
$range = mastercomTagPrintSchoolYearRange();
$today = mastercomTagPrintToday();
$defaultEnd = min($range['end'], $today);
$tagOptions = mastercomTagPrintTags();
$classRows = mastercomTagPrintClassRowsForUser($docenteId, $adminMode);
$classMap = mastercomTagPrintClassMap($classRows);
$errorMessage = '';

$startDate = trim((string)($_POST['data_inizio'] ?? $range['start']));
$endDate = trim((string)($_POST['data_fine'] ?? $defaultEnd));
$selectedTags = array_values(array_unique(array_map('intval', (array)($_POST['stampa_tag'] ?? []))));
$selectedClasses = array_values(array_unique(array_map('intval', (array)($_POST['mat_classi'] ?? []))));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!mastercomTagPrintValidDate($startDate) || !mastercomTagPrintValidDate($endDate)) {
        $errorMessage = 'Date non valide.';
    } elseif ($endDate < $startDate) {
        $errorMessage = 'La data finale deve essere successiva alla data iniziale.';
    } else {
        $selectedTags = array_values(array_filter($selectedTags, function ($tagId) use ($tagOptions) {
            return isset($tagOptions[$tagId]);
        }));
        $selectedClasses = array_values(array_filter($selectedClasses, function ($classId) use ($classMap) {
            return isset($classMap[$classId]);
        }));

        if (empty($selectedTags)) {
            $errorMessage = 'Selezionare almeno un tag.';
        } elseif (empty($selectedClasses)) {
            $errorMessage = 'Selezionare almeno una classe.';
        } else {
            $exportResult = mastercomTagPrintExport($startDate, $endDate, $selectedTags, $selectedClasses);
            if (!empty($exportResult['ok'])) {
                $filename = preg_replace('/[^A-Za-z0-9_.-]/', '_', (string)$exportResult['filename']);
                $contentType = trim((string)($exportResult['content_type'] ?? 'application/vnd.ms-excel'));
                if ($contentType === '' || stripos($contentType, 'text/html') !== false) {
                    $contentType = 'application/vnd.ms-excel; charset=Windows-1252';
                }

                try {
                    $classLabels = [];
                    foreach ($selectedClasses as $classId) {
                        $classRow = $classMap[$classId] ?? [];
                        $classLabels[] = trim((string)($classRow['gestore_classe'] ?? $classRow['nome'] ?? $classId));
                    }
                    $tagLabels = [];
                    foreach ($selectedTags as $tagId) {
                        $tagLabels[] = $tagOptions[$tagId] ?? (string)$tagId;
                    }

                    $importResult = mastercomTagReportImportFromBinary((string)$exportResult['body'], $filename, [
                        'data_inizio' => $startDate,
                        'data_fine' => $endDate,
                        'classi_label' => implode(', ', array_filter($classLabels)),
                        'docente_label' => 'TUTTI',
                        'tag_label' => implode(' - ', $tagLabels),
                    ]);

                    info('MasterCom stampa TAG importata in GestOre stampa_id=' . intval($importResult['stampa_id']) . ' righe=' . intval($importResult['rows']));
                    header('Location: stampaTagDettaglio.php?id=' . intval($importResult['stampa_id']));
                    exit;
                } catch (Throwable $e) {
                    warning('MasterCom stampa TAG import GestOre fallito | errore=' . $e->getMessage());
                    $errorMessage = 'MasterCom ha generato il file, ma GestOre non e riuscito a importarlo: ' . $e->getMessage();
                }
            }

            if ($errorMessage === '') {
                $errorMessage = $exportResult['message'] ?? 'Esportazione tag MasterCom fallita.';
                if (!empty($exportResult['preview'])) {
                    $errorMessage .= ' ' . $exportResult['preview'];
                }
            }
        }
    }
} else {
    $selectedTags = array_keys($tagOptions);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Stampa TAG</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
</head>
<body>
<?php
if ((($__utente_ruolo ?? '') === 'docente' || $impersonaDocenteAttiva) && !$adminMode) {
    require_once '../common/header-docente.php';
} elseif (haRuolo('admin')) {
    require_once '../common/header-admin.php';
} else {
    require_once '../common/header-didattica.php';
}
?>
<div class="container-fluid">
    <div class="panel panel-lightblue4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-tags"></span>&emsp;Stampa TAG
        </div>
        <div class="panel-body">
            <?php if ($errorMessage !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if (empty($classRows)): ?>
                <div class="alert alert-warning">
                    Nessuna classe disponibile per la stampa TAG.
                    <?php if (!$adminMode): ?>
                        Verificare la sincronizzazione della tabella docente_insegna e il collegamento con le classi MasterCom.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <form method="post" action="stampaTag.php" class="form-horizontal">
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="data_inizio">Data di partenza</label>
                        <div class="col-sm-3">
                            <input type="date" class="form-control" id="data_inizio" name="data_inizio" value="<?php echo htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="data_fine">Data di fine</label>
                        <div class="col-sm-3">
                            <input type="date" class="form-control" id="data_fine" name="data_fine" value="<?php echo htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-2 control-label">Tag</label>
                        <div class="col-sm-10">
                            <?php foreach ($tagOptions as $tagId => $tagLabel): ?>
                                <label class="checkbox-inline" style="margin-bottom: 8px;">
                                    <input type="checkbox" name="stampa_tag[]" value="<?php echo intval($tagId); ?>" <?php echo in_array($tagId, $selectedTags, true) ? 'checked' : ''; ?>>
                                    <?php echo htmlspecialchars($tagLabel, ENT_QUOTES, 'UTF-8'); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-2 control-label">Classi</label>
                        <div class="col-sm-10">
                            <div class="row">
                                <?php foreach ($classRows as $classRow): ?>
                                    <?php
                                    $classId = intval($classRow['mastercom_id_classe'] ?? 0);
                                    $label = trim((string)($classRow['gestore_classe'] ?? ''));
                                    $masterLabel = trim((string)($classRow['nome'] ?? ''));
                                    if ($label === '') {
                                        $label = $masterLabel;
                                    } elseif ($masterLabel !== '' && $masterLabel !== $label) {
                                        $label .= ' - ' . $masterLabel;
                                    }
                                    ?>
                                    <div class="col-sm-4 col-md-3">
                                        <label class="checkbox-inline" style="margin-bottom: 8px;">
                                            <input type="checkbox" name="mat_classi[]" value="<?php echo $classId; ?>" <?php echo in_array($classId, $selectedClasses, true) ? 'checked' : ''; ?>>
                                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="col-sm-offset-2 col-sm-10">
                            <button type="submit" class="btn btn-primary">
                                <span class="glyphicon glyphicon-import"></span>&ensp;Genera stampa TAG
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
