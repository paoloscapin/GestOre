<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../api/googleDriveLib.php';

ruoloRichiesto('segreteria-docenti', 'dirigente', 'docente');

header('Content-Type: application/json; charset=utf-8');

function outJson($ok, $data = [])
{
    echo json_encode(array_merge(['success' => $ok], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (!$__config->getBonus_rendiconto_aperto()) {
        outJson(false, ['message' => 'Rendiconto chiuso']);
    }

    $bonus_docente_id = intval($_POST['bonus_docente_id'] ?? 0);
    $anno = intval($_POST['anno_scolastico_id'] ?? $__anno_scolastico_corrente_id);
    $name = trim((string)($_POST['name'] ?? ''));
    $mime = trim((string)($_POST['mime'] ?? ''));
    $size = intval($_POST['size'] ?? 0);

    if ($bonus_docente_id <= 0 || $name === '' || $mime === '' || $size <= 0) {
        outJson(false, ['message' => 'Parametri mancanti']);
    }

    if ($anno !== intval($__anno_scolastico_corrente_id)) {
        outJson(false, ['message' => 'Puoi caricare solo sull’anno corrente']);
    }

    $allowed = (
        $mime === 'application/pdf'
        || strpos($mime, 'image/') === 0
        || strpos($mime, 'video/') === 0
    );

    if (!$allowed) {
        outJson(false, ['message' => 'Sono ammessi solo PDF, immagini e video']);
    }

    $bd = dbGetFirst("SELECT id, docente_id, anno_scolastico_id FROM bonus_docente WHERE id = $bonus_docente_id");
    if (!$bd) {
        outJson(false, ['message' => 'Record bonus non trovato']);
    }

    if (intval($bd['anno_scolastico_id']) !== $anno) {
        outJson(false, ['message' => 'Anno non coerente']);
    }

    if (!haRuolo('dirigente') && intval($bd['docente_id']) !== intval($__docente_id)) {
        outJson(false, ['message' => 'Non autorizzato']);
    }

    $info = dbGetFirst("
    SELECT
        d.cognome,
        d.nome,
        b.codice AS bonus_codice
    FROM bonus_docente bd
    JOIN docente d ON d.id = bd.docente_id
    JOIN bonus b ON b.id = bd.bonus_id
    WHERE bd.id = $bonus_docente_id
    LIMIT 1
");

    if (!$info) {
        outJson(false, ['message' => 'Dati docente/bonus non trovati']);
    }

    $cognomeNome = trim((string)$info['cognome'] . ' ' . (string)$info['nome']);
    $bonusCodice = trim((string)$info['bonus_codice']);

    $safeOriginalName = preg_replace('/[^\w\-. ()]/u', '_', $name);
    $safeCognomeNome = preg_replace('/[^\w\-. ()]/u', '_', $cognomeNome);
    $safeBonusCodice = preg_replace('/[^\w\-. ()]/u', '_', $bonusCodice);

    $driveName = $safeCognomeNome . ' - ' . $safeBonusCodice . ' - ' . $safeOriginalName;

    $bonusRootFolderId = googleDriveGetBonusFolderId();

    $annoLabel = dbGetValue("SELECT anno FROM anno_scolastico WHERE id = $anno LIMIT 1");
    $annoLabel = trim((string)$annoLabel);
    if ($annoLabel === '') {
        $annoLabel = (string)$anno;
    }

    $annoFolderName = 'AS ' . $annoLabel;
    $folderId = googleDriveGetOrCreateFolderInParent($annoFolderName, $bonusRootFolderId);
    $docenteFolderId = googleDriveGetOrCreateFolderInParent($safeCognomeNome, $folderId);
    $existingId = googleDriveFindFileByNameInParent($driveName, $folderId);

    if ($existingId !== '') {
        outJson(false, ['message' => 'Esiste già un allegato con lo stesso nome su Drive']);
    }
    $session = googleDriveStartResumableUpload($driveName, $mime, $size, $docenteFolderId);

    outJson(true, [
        'uploadUrl' => $session['uploadUrl'],
        'driveName' => $driveName,
    ]);
} catch (Throwable $e) {
    outJson(false, ['message' => $e->getMessage()]);
}
