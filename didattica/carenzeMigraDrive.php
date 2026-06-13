<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../api/googleDriveLib.php';
require_once __DIR__ . '/carenzeDownloadLib.php';

ruoloRichiesto('admin', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

function carenzeMigraOut(array $payload): void
{
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function carenzeMigraAnnoStart(string $anno): int
{
    if (preg_match('/(\d{4})/', $anno, $m)) {
        return intval($m[1]);
    }
    return 0;
}

function carenzeMigraDriveRootFolderId(): string
{
    $cfg = googleDriveGetConfig();
    $folderId = trim((string)($cfg->carenzeFolderId ?? ''));
    if ($folderId !== '') {
        return $folderId;
    }

    $folderName = trim((string)($cfg->carenzeFolderName ?? 'Carenze formative'));
    $folderId = googleDriveFindFolderByName($folderName);
    if ($folderId === '') {
        $folderId = googleDriveCreateFolder($folderName);
    }
    if ($folderId === '') {
        throw new Exception('Impossibile trovare o creare la cartella Drive delle carenze');
    }
    return $folderId;
}

function carenzeMigraDriveName(array $row): string
{
    $existing = carenzeDownloadOriginalFilename($row);
    if ($existing !== '' && $existing !== basename((string)($row['file_path'] ?? ''))) {
        return $existing;
    }

    return carenzeDownloadBuildFilename([
        'stud_cognome' => $row['stud_cognome'] ?? '',
        'stud_nome' => $row['stud_nome'] ?? '',
        'materia_nome' => $row['materia_nome'] ?? '',
        'classe_nome' => $row['classe_nome'] ?? '',
        'anno_scolastico' => $row['anno_scolastico'] ?? '',
        'ind_nome' => $row['ind_nome'] ?? '',
        'doc_cognome' => $row['doc_cognome'] ?? '',
        'doc_nome' => $row['doc_nome'] ?? '',
    ]);
}

$requiredColumns = ['storage_type', 'drive_file_id', 'drive_web_view_link', 'original_filename', 'migrated_at'];
$missingColumns = [];
foreach ($requiredColumns as $column) {
    if (!carenzeDownloadTableHasColumn($column)) {
        $missingColumns[] = $column;
    }
}

$confirm = isset($_GET['confirm']) && $_GET['confirm'] === '1';
$deleteLocal = isset($_GET['delete_local']) && $_GET['delete_local'] === '1';
$limit = max(1, min(500, intval($_GET['limit'] ?? 100)));
$annoId = intval($_GET['anno_scolastico_id'] ?? 0);

$currentAnnoLabel = (string)dbGetValue("SELECT anno FROM anno_scolastico_corrente LIMIT 1");
$currentStart = carenzeMigraAnnoStart($currentAnnoLabel);
$maxStart = $currentStart > 0 ? $currentStart - 2 : 0;

$where = [
    "cd.file_path IS NOT NULL",
    "cd.file_path <> ''",
];

if (carenzeDownloadTableHasColumn('storage_type')) {
    $where[] = "(cd.storage_type IS NULL OR cd.storage_type = '' OR UPPER(cd.storage_type) = 'LOCAL')";
}

if ($annoId > 0) {
    $where[] = "c.id_anno_scolastico = " . dbI($annoId);
} elseif ($maxStart > 0) {
    $where[] = "CAST(LEFT(a.anno, 4) AS UNSIGNED) <= " . dbI($maxStart);
} else {
    $where[] = "1 = 0";
}

$sql = "
    SELECT
        cd.*,
        c.id AS carenza_id,
        c.id_anno_scolastico,
        a.anno AS anno_scolastico,
        s.cognome AS stud_cognome,
        s.nome AS stud_nome,
        m.nome AS materia_nome,
        cl.classe AS classe_nome,
        ind.nome AS ind_nome,
        d.cognome AS doc_cognome,
        d.nome AS doc_nome
    FROM carenze_downloads cd
    INNER JOIN carenze c ON c.id = cd.carenza_id
    INNER JOIN anno_scolastico a ON a.id = c.id_anno_scolastico
    INNER JOIN studente s ON s.id = c.id_studente
    INNER JOIN materia m ON m.id = c.id_materia
    INNER JOIN classi cl ON cl.id = c.id_classe
    LEFT JOIN indirizzo ind ON ind.id = cl.id_primo_indirizzo
    LEFT JOIN docente d ON d.id = c.id_docente
    WHERE " . implode("\n      AND ", $where) . "
    ORDER BY a.id ASC, cd.id ASC
    LIMIT $limit
";

$rows = dbGetAll($sql);
if (!is_array($rows)) {
    $rows = [];
}

$result = [
    'ok' => empty($missingColumns) || !$confirm,
    'confirm' => $confirm,
    'delete_local' => $deleteLocal,
    'current_anno' => $currentAnnoLabel,
    'max_migration_start_year' => $maxStart,
    'anno_scolastico_id' => $annoId,
    'limit' => $limit,
    'missing_columns' => $missingColumns,
    'required_sql' => empty($missingColumns) ? '' : "ALTER TABLE carenze_downloads\n"
        . "ADD COLUMN storage_type VARCHAR(20) NOT NULL DEFAULT 'LOCAL',\n"
        . "ADD COLUMN drive_file_id VARCHAR(255) NULL,\n"
        . "ADD COLUMN drive_web_view_link VARCHAR(500) NULL,\n"
        . "ADD COLUMN original_filename VARCHAR(255) NULL,\n"
        . "ADD COLUMN migrated_at DATETIME NULL;",
    'found' => count($rows),
    'migrated' => [],
    'missing_files' => [],
    'errors' => [],
];

if ($confirm && !empty($missingColumns)) {
    carenzeMigraOut($result);
}

$rootFolderId = '';
$rootFolderName = '';
if ($confirm && !empty($rows)) {
    try {
        $rootFolderId = carenzeMigraDriveRootFolderId();
    } catch (Throwable $e) {
        $result['ok'] = false;
        $result['errors'][] = ['error' => $e->getMessage()];
        carenzeMigraOut($result);
    }
}

foreach ($rows as $row) {
    $downloadId = intval($row['id'] ?? 0);
    $localPath = carenzeDownloadResolveLocalPath((string)($row['file_path'] ?? ''));
    $driveName = carenzeMigraDriveName($row);
    $annoFolderName = 'AS ' . str_replace('/', '-', trim((string)($row['anno_scolastico'] ?? '')));

    if (!is_file($localPath)) {
        $result['missing_files'][] = [
            'id' => $downloadId,
            'carenza_id' => intval($row['carenza_id'] ?? 0),
            'path' => $localPath,
        ];
        continue;
    }

    if (!$confirm) {
        $result['migrated'][] = [
            'id' => $downloadId,
            'simulate' => true,
            'path' => $localPath,
            'drive_folder' => $annoFolderName,
            'drive_name' => $driveName,
            'bytes' => filesize($localPath),
        ];
        continue;
    }

    try {
        $folderId = googleDriveGetOrCreateFolderInParent($annoFolderName, $rootFolderId);
        $existingDriveId = googleDriveFindFileByNameInParent($driveName, $folderId);
        if ($existingDriveId !== '') {
            $upload = googleDriveGetFileMetadata($existingDriveId);
        } else {
            $upload = googleDriveUploadFile($localPath, $driveName, $folderId, 'application/pdf');
        }

        $driveId = trim((string)($upload['id'] ?? ''));
        if ($driveId === '') {
            throw new Exception('Upload Drive senza ID file');
        }

        $assignments = carenzeDownloadUpdateAssignments([
            'storage_type' => "'DRIVE'",
            'drive_file_id' => "'" . escapeString($driveId) . "'",
            'drive_web_view_link' => "'" . escapeString((string)($upload['webViewLink'] ?? '')) . "'",
            'original_filename' => "'" . escapeString($driveName) . "'",
            'migrated_at' => "NOW()",
        ]);
        dbExec("UPDATE carenze_downloads SET $assignments WHERE id = $downloadId LIMIT 1");

        $deleted = false;
        if ($deleteLocal) {
            $deleted = @unlink($localPath);
        }

        $result['migrated'][] = [
            'id' => $downloadId,
            'carenza_id' => intval($row['carenza_id'] ?? 0),
            'drive_file_id' => $driveId,
            'drive_name' => $driveName,
            'deleted_local' => $deleted,
        ];
    } catch (Throwable $e) {
        $result['errors'][] = [
            'id' => $downloadId,
            'carenza_id' => intval($row['carenza_id'] ?? 0),
            'error' => $e->getMessage(),
        ];
    }
}

if (!empty($result['errors'])) {
    $result['ok'] = false;
}

carenzeMigraOut($result);

?>
