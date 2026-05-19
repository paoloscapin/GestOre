<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../api/googleDriveLib.php';

ruoloRichiesto('admin', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

$confirm = isset($_GET['confirm']) && $_GET['confirm'] == '1';
$anno = intval($_GET['anno_scolastico_id'] ?? $__anno_scolastico_corrente_id);

$bonusRootFolderId = googleDriveGetBonusFolderId();

$annoLabel = dbGetValue("SELECT anno FROM anno_scolastico WHERE id = $anno LIMIT 1");
$annoFolderName = 'AS ' . trim((string)$annoLabel);

$folderId = googleDriveGetOrCreateFolderInParent($annoFolderName, $bonusRootFolderId);

$driveFiles = googleDriveListFilesInFolder($folderId);

$dbRows = dbGetAll("
    SELECT id, drive_file_id, original_name
    FROM bonus_docente_allegato
    WHERE anno_scolastico_id = $anno
      AND storage_type = 'DRIVE'
      AND drive_file_id IS NOT NULL
      AND drive_file_id <> ''
");

$dbIds = [];
foreach ($dbRows as $r) {
    $dbIds[(string)$r['drive_file_id']] = intval($r['id']);
}

$byName = [];
foreach ($driveFiles as $f) {
    $name = (string)($f['name'] ?? '');
    if ($name === '') continue;
    $byName[$name][] = $f;
}

$deleted = [];
$kept = [];
$duplicates = [];

foreach ($byName as $name => $items) {
    if (count($items) <= 1) {
        continue;
    }

    usort($items, function ($a, $b) use ($dbIds) {
        $aInDb = isset($dbIds[(string)$a['id']]) ? 1 : 0;
        $bInDb = isset($dbIds[(string)$b['id']]) ? 1 : 0;

        if ($aInDb !== $bInDb) {
            return $bInDb <=> $aInDb;
        }

        return strcmp((string)($b['modifiedTime'] ?? ''), (string)($a['modifiedTime'] ?? ''));
    });

    $keep = array_shift($items);
    $kept[] = [
        'name' => $name,
        'keep_id' => $keep['id'],
        'keep_in_db' => isset($dbIds[(string)$keep['id']]),
    ];

    foreach ($items as $dup) {
        $duplicates[] = [
            'name' => $name,
            'drive_id' => $dup['id'],
            'in_db' => isset($dbIds[(string)$dup['id']]),
        ];

        if ($confirm) {
            googleDriveDeleteFile((string)$dup['id']);
            $deleted[] = [
                'name' => $name,
                'drive_id' => $dup['id'],
            ];

            if (isset($dbIds[(string)$dup['id']])) {
                $allegatoId = intval($dbIds[(string)$dup['id']]);
                dbExec("DELETE FROM bonus_docente_allegato WHERE id = $allegatoId LIMIT 1");
            }
        }
    }
}

echo json_encode([
    'ok' => true,
    'confirm' => $confirm,
    'folder_id' => $folderId,
    'drive_files' => count($driveFiles),
    'duplicate_candidates' => count($duplicates),
    'deleted' => $deleted,
    'kept' => $kept,
    'duplicates' => $duplicates,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);