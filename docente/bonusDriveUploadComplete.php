<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('segreteria-docenti', 'dirigente', 'docente');

header('Content-Type: application/json; charset=utf-8');

function outJson($ok, $data = []) {
    echo json_encode(array_merge(['success' => $ok], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (!$__config->getBonus_rendiconto_aperto()) {
        outJson(false, ['message' => 'Rendiconto chiuso']);
    }

    $bonus_docente_id = intval($_POST['bonus_docente_id'] ?? 0);
    $anno = intval($_POST['anno_scolastico_id'] ?? $__anno_scolastico_corrente_id);

    $fileId = trim((string)($_POST['file_id'] ?? ''));
    $name = trim((string)($_POST['name'] ?? ''));
    $mime = trim((string)($_POST['mime'] ?? ''));
    $size = intval($_POST['size'] ?? 0);
    $webViewLink = trim((string)($_POST['web_view_link'] ?? ''));

    if ($bonus_docente_id <= 0 || $fileId === '' || $name === '') {
        outJson(false, ['message' => 'Parametri mancanti']);
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

    $nameEsc = escapeString($name);
    $mimeEsc = escapeString($mime);
    $fileIdEsc = escapeString($fileId);
    $linkEsc = escapeString($webViewLink);

    dbExec("
        INSERT INTO bonus_docente_allegato
        (
            bonus_docente_id,
            docente_id,
            anno_scolastico_id,
            original_name,
            stored_name,
            mime_type,
            file_size,
            storage_type,
            drive_file_id,
            drive_web_view_link,
            drive_mime_type
        )
        VALUES
        (
            $bonus_docente_id,
            " . intval($bd['docente_id']) . ",
            $anno,
            '$nameEsc',
            '',
            '$mimeEsc',
            $size,
            'DRIVE',
            '$fileIdEsc',
            '$linkEsc',
            '$mimeEsc'
        )
    ");

    outJson(true, ['message' => 'Allegato collegato al bonus']);

} catch (Throwable $e) {
    outJson(false, ['message' => $e->getMessage()]);
}