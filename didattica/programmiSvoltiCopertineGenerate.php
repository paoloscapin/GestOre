<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/vendor/autoload.php';
require_once '../common/programmiSvoltiCopertineLib.php';
require_once '../api/googleDriveLib.php';
require_once 'programmiSvoltiCopertinePdfLib.php';

ruoloRichiesto('segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

function copertineGenerateOut(bool $ok, array $data = []): void
{
    echo json_encode(array_merge(['ok' => $ok], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function copertineDriveRootFolderId(): string
{
    $cfg = googleDriveGetConfig();
    $folderId = trim((string)($cfg->copertineVerificheFolderId ?? ''));
    if ($folderId !== '') {
        return $folderId;
    }

    $folderName = trim((string)($cfg->copertineVerificheFolderName ?? 'Copertine Verifiche'));
    $folderId = googleDriveFindFolderByName($folderName);
    if ($folderId === '') {
        $folderId = googleDriveCreateFolder($folderName);
    }
    if ($folderId === '') {
        throw new Exception('Impossibile trovare o creare la cartella Drive Copertine Verifiche');
    }
    return $folderId;
}

try {
    if (!programmiSvoltiCopertineTableExists()) {
        copertineGenerateOut(false, ['message' => 'Tabella programmi_svolti_copertine non presente.']);
    }

    $rows = dbGetAll("SELECT * FROM programmi_svolti_copertine WHERE stato IN ('RICHIESTA', 'ERRORE') ORDER BY requested_at ASC, id ASC");
    if (!$rows) {
        copertineGenerateOut(true, ['message' => 'Nessuna copertina richiesta da generare.', 'generated' => 0]);
    }

    $rootFolderId = copertineDriveRootFolderId();
    $generated = 0;
    $errors = 0;
    $messages = [];

    foreach ($rows as $row) {
        $coverId = intval($row['id']);
        $programma = programmiSvoltiCopertinaLoadProgramma(intval($row['id_programma_svolto']));
        if (!$programma) {
            dbExec("UPDATE programmi_svolti_copertine SET stato='ERRORE', error_message='Programma svolto non trovato', updated_at=NOW() WHERE id=$coverId");
            $errors++;
            continue;
        }

        try {
            $annoFine = intval($row['fascicolo_anno'] ?? 0);
            if ($annoFine <= 0) {
                $annoFine = programmiSvoltiCopertineAnnoFine((string)($programma['anno_scolastico_label'] ?? ''));
            }
            $codice = trim((string)($row['fascicolo_codice'] ?? ''));
            $numero = intval($row['fascicolo_numero'] ?? 0);
            if ($codice === '' || $numero <= 0) {
                $next = programmiSvoltiCopertinaNextCode($annoFine);
                $codice = $next['codice'];
                $numero = $next['numero'];
            }

            $fileName = programmiSvoltiCopertinaFileName($programma, $codice);
            $annoFolderName = programmiSvoltiCopertineAnnoCartella((string)($programma['anno_scolastico_label'] ?? ''));
            $annoFolderId = googleDriveGetOrCreateFolderInParent($annoFolderName, $rootFolderId);

            $tmp = tempnam(sys_get_temp_dir(), 'copertina_verifiche_');
            if ($tmp === false) {
                throw new Exception('Impossibile creare file temporaneo');
            }
            $pdfPath = $tmp . '.pdf';
            @unlink($tmp);
            copertineBuildPdf($pdfPath, $programma, $codice, $annoFine);
            $upload = googleDriveUploadFile($pdfPath, $fileName, $annoFolderId, 'application/pdf');
            @unlink($pdfPath);

            $driveFileId = dbEscape((string)($upload['id'] ?? ''));
            $driveLink = dbEscape((string)($upload['webViewLink'] ?? ''));
            dbExec("UPDATE programmi_svolti_copertine
                SET stato='GENERATA',
                    fascicolo_codice='" . dbEscape($codice) . "',
                    fascicolo_numero=" . intval($numero) . ",
                    fascicolo_anno=" . intval($annoFine) . ",
                    file_name='" . dbEscape($fileName) . "',
                    drive_file_id='$driveFileId',
                    drive_web_view_link='$driveLink',
                    generated_by_user_id=" . intval($__utente_id ?? 0) . ",
                    generated_at=NOW(),
                    error_message=NULL,
                    updated_at=NOW()
                WHERE id=$coverId");
            $generated++;
        } catch (Throwable $e) {
            $errors++;
            $messages[] = $e->getMessage();
            dbExec("UPDATE programmi_svolti_copertine
                SET stato='ERRORE',
                    error_message='" . dbEscape($e->getMessage()) . "',
                    updated_at=NOW()
                WHERE id=$coverId");
        }
    }

    $message = "Copertine generate: $generated.";
    if ($errors > 0) {
        $message .= " Errori: $errors.";
    }
    copertineGenerateOut($errors === 0, [
        'message' => $message,
        'generated' => $generated,
        'errors' => $errors,
        'details' => $messages,
    ]);
} catch (Throwable $e) {
    copertineGenerateOut(false, ['message' => $e->getMessage()]);
}
