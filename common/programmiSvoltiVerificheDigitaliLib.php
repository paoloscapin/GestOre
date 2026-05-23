<?php

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/programmiSvoltiCopertineLib.php';
require_once __DIR__ . '/../api/googleDriveLib.php';

function programmiSvoltiVerificheDigitaliEnsureSchema(): void
{
    dbExec("
        CREATE TABLE IF NOT EXISTS programmi_svolti_verifiche_digitali (
            id INT NOT NULL AUTO_INCREMENT,
            id_programma_svolto INT NOT NULL,
            id_anno_scolastico INT NOT NULL,
            id_docente INT NOT NULL,
            drive_folder_id VARCHAR(255) NOT NULL,
            drive_file_id VARCHAR(255) NOT NULL,
            drive_web_view_link TEXT NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(120) NULL,
            file_size BIGINT NULL,
            uploaded_by_user_id INT NULL,
            uploaded_at DATETIME NOT NULL,
            deleted_by_user_id INT NULL,
            deleted_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_psvd_programma (id_programma_svolto),
            KEY idx_psvd_docente (id_docente),
            KEY idx_psvd_anno (id_anno_scolastico),
            KEY idx_psvd_deleted (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function programmiSvoltiVerificheDigitaliCurrentDocenteId(): int
{
    $docenteId = intval($GLOBALS['__docente_id'] ?? 0);
    if ($docenteId > 0) {
        return $docenteId;
    }

    $username = trim((string)($GLOBALS['__username'] ?? ''));
    $email = trim((string)($GLOBALS['__useremail'] ?? ''));
    if ($username === '' && $email === '') {
        return 0;
    }

    $where = [];
    if ($username !== '') {
        $where[] = "username='" . dbEscape($username) . "'";
    }
    if ($email !== '') {
        $where[] = "email='" . dbEscape($email) . "'";
    }

    $row = dbGetFirst("SELECT id FROM docente WHERE attivo=1 AND (" . implode(' OR ', $where) . ") LIMIT 1");
    return $row ? intval($row['id']) : 0;
}

function programmiSvoltiVerificheDigitaliCanManage(array $programma): bool
{
    if (haRuolo('dirigente') || haRuolo('segreteria-didattica')) {
        return true;
    }

    $docenteId = programmiSvoltiVerificheDigitaliCurrentDocenteId();
    return $docenteId > 0 && intval($programma['id_docente'] ?? 0) === $docenteId;
}

function programmiSvoltiVerificheDigitaliDocenteEmail(array $programma): string
{
    $docenteId = intval($programma['id_docente'] ?? 0);
    if ($docenteId <= 0) {
        return '';
    }

    $row = dbGetFirst("SELECT email, username FROM docente WHERE id=" . $docenteId . " LIMIT 1");
    if (!$row) {
        return '';
    }

    $email = strtolower(trim((string)($row['email'] ?? '')));
    if ($email !== '' && substr($email, -strlen('@buonarroti.tn.it')) === '@buonarroti.tn.it') {
        return $email;
    }

    $username = strtolower(trim((string)($row['username'] ?? '')));
    if ($username === '') {
        return '';
    }
    if (strpos($username, '@') !== false) {
        return substr($username, -strlen('@buonarroti.tn.it')) === '@buonarroti.tn.it' ? $username : '';
    }

    return $username . '@buonarroti.tn.it';
}

function programmiSvoltiVerificheDigitaliRootFolderId(): string
{
    $cfg = googleDriveGetConfig();
    $folderId = trim((string)($cfg->verificheDigitaliFolderId ?? ''));
    if ($folderId !== '') {
        return $folderId;
    }

    $folderName = trim((string)($cfg->verificheDigitaliFolderName ?? 'Verifiche Digitali'));
    $folderId = googleDriveFindFolderByName($folderName);
    if ($folderId === '') {
        $folderId = googleDriveCreateFolder($folderName);
    }
    if ($folderId === '') {
        throw new Exception('Impossibile trovare o creare la cartella Drive Verifiche Digitali');
    }
    return $folderId;
}

function programmiSvoltiVerificheDigitaliAnnoFolderName(array $programma): string
{
    return programmiSvoltiCopertineAnnoCartella((string)($programma['anno_scolastico_label'] ?? ''));
}

function programmiSvoltiVerificheDigitaliProgramFolderName(array $programma): string
{
    $name = strtoupper(trim((string)($programma['docente_cognome'] ?? '') . ' ' . (string)($programma['docente_nome'] ?? '')))
        . ' - ' . (string)($programma['classe_label'] ?? '')
        . ' - ' . (string)($programma['materia_nome'] ?? '');

    return programmiSvoltiCopertinaSafeFilePart($name);
}

function programmiSvoltiVerificheDigitaliFileName(array $programma, string $originalName): string
{
    $originalName = programmiSvoltiCopertinaSafeFilePart($originalName);
    return programmiSvoltiVerificheDigitaliProgramFolderName($programma) . ' - ' . $originalName;
}

function programmiSvoltiVerificheDigitaliProgramFolderId(array $programma): string
{
    $rootFolderId = programmiSvoltiVerificheDigitaliRootFolderId();
    $annoFolderId = googleDriveGetOrCreateFolderInParent(programmiSvoltiVerificheDigitaliAnnoFolderName($programma), $rootFolderId);
    return googleDriveGetOrCreateFolderInParent(programmiSvoltiVerificheDigitaliProgramFolderName($programma), $annoFolderId);
}

function programmiSvoltiVerificheDigitaliIsZipName(string $name): bool
{
    return preg_match('/\.zip$/i', trim($name)) === 1;
}

function programmiSvoltiVerificheDigitaliList(int $programmaId): array
{
    programmiSvoltiVerificheDigitaliEnsureSchema();

    $rows = dbGetAll("
        SELECT *
        FROM programmi_svolti_verifiche_digitali
        WHERE id_programma_svolto=" . intval($programmaId) . "
          AND deleted_at IS NULL
        ORDER BY uploaded_at DESC, id DESC
    ");

    return is_array($rows) ? $rows : [];
}

function programmiSvoltiVerificheDigitaliCount(int $programmaId): int
{
    programmiSvoltiVerificheDigitaliEnsureSchema();
    return intval(dbGetValue("
        SELECT COUNT(*)
        FROM programmi_svolti_verifiche_digitali
        WHERE id_programma_svolto=" . intval($programmaId) . "
          AND deleted_at IS NULL
    "));
}

function programmiSvoltiVerificheDigitaliLoadItem(int $id): ?array
{
    programmiSvoltiVerificheDigitaliEnsureSchema();
    $row = dbGetFirst("SELECT * FROM programmi_svolti_verifiche_digitali WHERE id=" . intval($id) . " AND deleted_at IS NULL LIMIT 1");
    return $row ?: null;
}
