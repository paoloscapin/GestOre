<?php

require_once __DIR__ . '/../common/__Util.php';
require_once __DIR__ . '/../common/connect.php';

function carenzeDownloadTableHasColumn(string $column): bool
{
    static $columns = null;

    if ($columns === null) {
        $columns = [];
        $rows = dbGetAll("SHOW COLUMNS FROM carenze_downloads");
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $name = (string)($row['Field'] ?? '');
                if ($name !== '') {
                    $columns[$name] = true;
                }
            }
        }
    }

    return isset($columns[$column]);
}

function carenzeDownloadStorageType(array $row): string
{
    if (carenzeDownloadTableHasColumn('storage_type')) {
        $storageType = strtoupper(trim((string)($row['storage_type'] ?? 'LOCAL')));
        return $storageType !== '' ? $storageType : 'LOCAL';
    }

    return 'LOCAL';
}

function carenzeDownloadResolveLocalPath(string $filePath): string
{
    $filePath = str_replace('\\', '/', trim($filePath));
    $filePath = ltrim($filePath, '/');

    return __DIR__ . '/' . $filePath;
}

function carenzeDownloadAnnoFolder(string $annoScolastico): string
{
    $annoScolastico = trim($annoScolastico);
    if (preg_match('/^(\d{4})\D+(\d{2,4})$/', $annoScolastico, $m)) {
        $end = strlen($m[2]) === 4 ? substr($m[2], -2) : $m[2];
        return $m[1] . '-' . $end;
    }

    $safe = preg_replace('/[^0-9A-Za-z_-]+/', '-', $annoScolastico);
    $safe = trim((string)$safe, '-_');

    return $safe !== '' ? $safe : 'senza-anno';
}

function carenzeDownloadLocalRelativePath(string $randomFileName, string $annoScolastico): string
{
    return 'carenze_pdf/' . carenzeDownloadAnnoFolder($annoScolastico) . '/' . $randomFileName;
}

function carenzeDownloadEnsureLocalDir(string $annoScolastico): string
{
    $dir = __DIR__ . '/carenze_pdf/' . carenzeDownloadAnnoFolder($annoScolastico);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    return $dir;
}

function carenzeDownloadOriginalFilename(array $row): string
{
    if (carenzeDownloadTableHasColumn('original_filename')) {
        $original = trim((string)($row['original_filename'] ?? ''));
        if ($original !== '') {
            return $original;
        }
    }

    $filePath = trim((string)($row['file_path'] ?? ''));
    $basename = basename(str_replace('\\', '/', $filePath));

    return $basename !== '' ? $basename : 'carenza.pdf';
}

function carenzeDownloadBuildFilename(array $program): string
{
    $titolo = trim((string)($program['titolo'] ?? 'Programma carenza formativa'));
    $studente = trim((string)($program['stud_cognome'] ?? '') . ' ' . (string)($program['stud_nome'] ?? ''));
    $materia = trim((string)($program['materia_nome'] ?? ''));
    $classe = trim((string)($program['classe_nome'] ?? ''));
    $indirizzo = trim((string)($program['ind_nome'] ?? ''));
    $docente = trim((string)($program['doc_cognome'] ?? '') . ' ' . (string)($program['doc_nome'] ?? ''));

    $name = trim($titolo . ' ' . $studente . ' ' . $materia);
    if ($classe !== '') {
        $name .= ' - Classe ' . $classe;
    }
    if ($indirizzo !== '') {
        $name .= ' - Indirizzo ' . $indirizzo;
    }
    if ($docente !== '') {
        $name .= ' - Docente ' . $docente;
    }

    $name = preg_replace('/[^\p{L}\p{N}\-. ()_°]+/u', '_', $name);
    $name = trim((string)$name, " ._-");

    return ($name !== '' ? $name : 'Programma carenza formativa') . '.pdf';
}

function carenzeDownloadSelectFields(): string
{
    $fields = "file_path, last_download, download_token";

    if (carenzeDownloadTableHasColumn('storage_type')) {
        $fields .= ", storage_type";
    }
    if (carenzeDownloadTableHasColumn('drive_file_id')) {
        $fields .= ", drive_file_id";
    }
    if (carenzeDownloadTableHasColumn('original_filename')) {
        $fields .= ", original_filename";
    }

    return $fields;
}

function carenzeDownloadInsertSqlFields(array $values): array
{
    $columns = [];
    $sqlValues = [];

    foreach ($values as $column => $value) {
        if (in_array($column, ['storage_type', 'drive_file_id', 'drive_web_view_link', 'original_filename', 'migrated_at'], true)
            && !carenzeDownloadTableHasColumn($column)) {
            continue;
        }
        $columns[] = $column;
        $sqlValues[] = $value;
    }

    return [$columns, $sqlValues];
}

function carenzeDownloadUpdateAssignments(array $values): string
{
    $assignments = [];

    foreach ($values as $column => $value) {
        if (in_array($column, ['storage_type', 'drive_file_id', 'drive_web_view_link', 'original_filename', 'migrated_at'], true)
            && !carenzeDownloadTableHasColumn($column)) {
            continue;
        }
        $assignments[] = $column . " = " . $value;
    }

    return implode(", ", $assignments);
}

function carenzeDownloadDriveRequiredColumns(): array
{
    return ['storage_type', 'drive_file_id', 'original_filename', 'migrated_at'];
}

function carenzeDownloadDriveMissingColumns(): array
{
    $missing = [];
    foreach (carenzeDownloadDriveRequiredColumns() as $column) {
        if (!carenzeDownloadTableHasColumn($column)) {
            $missing[] = $column;
        }
    }

    return $missing;
}

function carenzeDownloadDriveRequiredSql(): string
{
    return "ALTER TABLE carenze_downloads\n"
        . "ADD COLUMN storage_type VARCHAR(20) NOT NULL DEFAULT 'LOCAL',\n"
        . "ADD COLUMN drive_file_id VARCHAR(255) NULL,\n"
        . "ADD COLUMN original_filename VARCHAR(255) NULL,\n"
        . "ADD COLUMN migrated_at DATETIME NULL;";
}

function carenzeDownloadDriveRootFolderId(): string
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

function carenzeDownloadDriveAnnoFolderName(string $annoScolastico): string
{
    $annoScolastico = trim($annoScolastico);
    return 'AS ' . str_replace('/', '-', ($annoScolastico !== '' ? $annoScolastico : 'senza-anno'));
}

function carenzeDownloadUploadToDrive(string $localPath, string $driveName, string $annoScolastico): array
{
    $missingColumns = carenzeDownloadDriveMissingColumns();
    if (!empty($missingColumns)) {
        throw new Exception('Tabella carenze_downloads non pronta per Drive. Esegui: ' . carenzeDownloadDriveRequiredSql());
    }

    $rootFolderId = carenzeDownloadDriveRootFolderId();
    $folderId = googleDriveGetOrCreateFolderInParent(carenzeDownloadDriveAnnoFolderName($annoScolastico), $rootFolderId);

    return googleDriveUploadFile($localPath, $driveName, $folderId, 'application/pdf');
}

?>
