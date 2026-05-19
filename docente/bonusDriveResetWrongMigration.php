<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../api/googleDriveLib.php';

ruoloRichiesto('admin', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

$confirm = isset($_GET['confirm']) && $_GET['confirm'] == '1';
$anno = intval($_GET['anno_scolastico_id'] ?? $__anno_scolastico_corrente_id);

$rows = dbGetAll("
    SELECT *
    FROM bonus_docente_allegato
    WHERE anno_scolastico_id = $anno
      AND storage_type = 'DRIVE'
      AND drive_file_id IS NOT NULL
      AND drive_file_id <> ''
      AND stored_name IS NOT NULL
      AND stored_name <> ''
");

$baseDir = realpath(__DIR__ . '/bonus_upload');

$out = [
    'confirm' => $confirm,
    'found' => count($rows),
    'reset' => [],
    'skipped' => [],
    'errors' => [],
];

foreach ($rows as $r) {
    $id = intval($r['id']);

    $path = $baseDir . '/'
        . intval($r['anno_scolastico_id']) . '/'
        . intval($r['docente_id']) . '/'
        . intval($r['bonus_docente_id']) . '/'
        . $r['stored_name'];

    if (!$baseDir || !is_file($path)) {
        $out['skipped'][] = [
            'id' => $id,
            'reason' => 'file locale non trovato',
            'path' => $path,
        ];
        continue;
    }

    try {
        if ($confirm) {
            googleDriveDeleteFile((string)$r['drive_file_id']);

            dbExec("
                UPDATE bonus_docente_allegato
                SET
                    storage_type = 'LOCAL',
                    drive_file_id = NULL,
                    drive_web_view_link = NULL,
                    drive_mime_type = NULL
                WHERE id = $id
                LIMIT 1
            ");
        }

        $out['reset'][] = [
            'id' => $id,
            'original_name' => $r['original_name'],
            'local_path' => $path,
            'drive_file_id' => $r['drive_file_id'],
        ];

    } catch (Throwable $e) {
        $out['errors'][] = [
            'id' => $id,
            'error' => $e->getMessage(),
        ];
    }
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);