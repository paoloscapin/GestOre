<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../api/googleDriveLib.php';

ruoloRichiesto('admin', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

$confirm = isset($_GET['confirm']) && $_GET['confirm'] == '1';
$deleteLocal = isset($_GET['delete_local']) && $_GET['delete_local'] == '1';
$anno = intval($_GET['anno_scolastico_id'] ?? $__anno_scolastico_corrente_id);

$bonusRootFolderId = googleDriveGetBonusFolderId();

$annoLabel = dbGetValue("SELECT anno FROM anno_scolastico WHERE id = $anno LIMIT 1");
$annoFolderName = 'AS ' . trim((string)$annoLabel);
$folderId = googleDriveGetOrCreateFolderInParent($annoFolderName, $bonusRootFolderId);

$rows = dbGetAll("
    SELECT
        a.*,
        d.cognome,
        d.nome,
        b.codice AS bonus_codice
    FROM bonus_docente_allegato a
    JOIN bonus_docente bd ON bd.id = a.bonus_docente_id
    JOIN docente d ON d.id = bd.docente_id
    JOIN bonus b ON b.id = bd.bonus_id
    WHERE a.anno_scolastico_id = $anno
      AND (a.storage_type IS NULL OR a.storage_type = 'LOCAL')
    ORDER BY a.id
");

$baseDir = realpath(__DIR__ . '/bonus_upload');

$result = [
    'ok' => true,
    'confirm' => $confirm,
    'delete_local' => $deleteLocal,
    'folder_id' => $folderId,
    'found' => count($rows),
    'migrated' => [],
    'missing' => [],
    'errors' => [],
];

foreach ($rows as $r) {
    $id = intval($r['id']);
    $path = $baseDir . '/' . intval($r['anno_scolastico_id']) . '/' . intval($r['docente_id']) . '/' . intval($r['bonus_docente_id']) . '/' . $r['stored_name'];

    if (!$baseDir || !is_file($path)) {
        $result['missing'][] = [
            'id' => $id,
            'path' => $path,
            'original_name' => $r['original_name'],
        ];
        continue;
    }

    $cognomeNome = trim((string)$r['cognome'] . ' ' . (string)$r['nome']);
    $bonusCodice = trim((string)$r['bonus_codice']);
    $originalName = trim((string)$r['original_name']);

    $safeOriginalName = preg_replace('/[^\w\-. ()]/u', '_', $originalName);
    $safeCognomeNome = preg_replace('/[^\w\-. ()]/u', '_', $cognomeNome);
    $safeBonusCodice = preg_replace('/[^\w\-. ()]/u', '_', $bonusCodice);

    $driveName = $safeCognomeNome . ' - ' . $safeBonusCodice . ' - ' . $safeOriginalName;

    try {
        if (!$confirm) {
            $result['migrated'][] = [
                'id' => $id,
                'simulate' => true,
                'path' => $path,
                'drive_name' => $driveName,
            ];
            continue;
        }

        $docenteFolderId = googleDriveGetOrCreateFolderInParent(
            $safeCognomeNome,
            $folderId
        );

        $upload = googleDriveUploadFile(
            $path,
            $driveName,
            $docenteFolderId,
            (string)($r['mime_type'] ?? '')
        );

        $driveId = escapeString((string)($upload['id'] ?? ''));
        $driveLink = escapeString((string)($upload['webViewLink'] ?? ''));

        if ($driveId === '') {
            throw new Exception('Upload Drive senza ID');
        }

        dbExec("
            UPDATE bonus_docente_allegato
            SET
                storage_type = 'DRIVE',
                drive_file_id = '$driveId',
                drive_web_view_link = '$driveLink',
                drive_mime_type = mime_type
            WHERE id = $id
            LIMIT 1
        ");

        $deleted = false;
        if ($deleteLocal) {
            $deleted = @unlink($path);
        }

        $result['migrated'][] = [
            'id' => $id,
            'drive_id' => $upload['id'] ?? '',
            'drive_name' => $driveName,
            'deleted_local' => $deleted,
        ];
    } catch (Throwable $e) {
        $result['errors'][] = [
            'id' => $id,
            'original_name' => $originalName,
            'error' => $e->getMessage(),
        ];
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
