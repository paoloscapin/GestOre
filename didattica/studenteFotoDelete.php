<?php

/**
 *  This file is part of GestOre
 *  @author     OpenAI Codex
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/mastercom/admin_lib.php';
require_once '../common/student_photo.php';

header('Content-Type: application/json; charset=UTF-8');

ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

function studenteFotoDeleteResponse(array $payload): void
{
    echo json_encode($payload);
    exit;
}

$studentId = intval($_POST['studente_id'] ?? 0);
if ($studentId <= 0) {
    studenteFotoDeleteResponse(['ok' => false, 'message' => 'Studente non valido']);
}

$student = dbGetFirst("SELECT * FROM studente WHERE id = " . intval($studentId) . " LIMIT 1");
if (!$student) {
    studenteFotoDeleteResponse(['ok' => false, 'message' => 'Studente non trovato']);
}

$localDeleted = true;
$photoPath = gestoreStudentPhotoPath($studentId);
if (is_file($photoPath)) {
    $localDeleted = unlink($photoPath);
}

$mastercomDelete = ['ok' => false, 'message' => 'Studente non collegato a MasterCom'];
$mirror = null;
if (mastercomAdminTableExists('mastercom_studenti')) {
    $where = ["id_studente_gestore = " . intval($studentId)];
    if (mastercomAdminTableColumnExists('mastercom_studenti', 'codice_fiscale') && trim((string)($student['codice_fiscale'] ?? '')) !== '') {
        $where[] = "LOWER(codice_fiscale) = LOWER(" . dbQ(trim((string)$student['codice_fiscale'])) . ")";
    }
    if (mastercomAdminTableColumnExists('mastercom_studenti', 'mastercom_id_studente') && ctype_digit(trim((string)($student['username'] ?? '')))) {
        $where[] = "mastercom_id_studente = " . dbI(intval($student['username']));
    }
    $mirror = dbGetFirst("
        SELECT *
        FROM mastercom_studenti
        WHERE (" . implode(' OR ', $where) . ")
        ORDER BY last_seen_at DESC, last_sync_at DESC
        LIMIT 1
    ");
}

if ($mirror) {
    $mastercomStudentId = intval($mirror['mastercom_id_studente'] ?? 0);
    $mastercomClassId = intval($mirror['mastercom_id_classe_corrente'] ?? 0);
    $className = trim((string)(dbGetValue("
        SELECT c.classe
        FROM studente_frequenta sf
        INNER JOIN classi c ON c.id = sf.id_classe
        WHERE sf.id_studente = " . intval($studentId) . "
        ORDER BY sf.id_anno_scolastico DESC
        LIMIT 1
    ") ?? ''));
    if ($className === '') {
        $className = trim((string)(($mirror['classe_numero'] ?? '') . ($mirror['sezione'] ?? '')));
    }

    if ($mastercomStudentId > 0 && $mastercomClassId > 0) {
        $authResult = mastercomAuthenticateService([
            'profile' => 'MasterComAuth',
            'method' => 'POST',
            'timeout' => 60,
        ]);
        if (!empty($authResult['ok'])) {
            $deleteResult = mastercomDeleteStudentPhoto($authResult, $mastercomStudentId, $mastercomClassId, $className, [
                'timeout' => 120,
            ]);
            $mastercomDelete = [
                'ok' => !empty($deleteResult['ok']),
                'message' => !empty($deleteResult['ok'])
                    ? 'Foto eliminata da MasterCom'
                    : ('Eliminazione MasterCom fallita: ' . ($deleteResult['error'] ?? 'errore sconosciuto')),
                'http_code' => intval($deleteResult['http_code'] ?? 0),
            ];
            if (!empty($deleteResult['ok']) && mastercomAdminTableColumnExists('mastercom_studenti', 'foto')) {
                dbExec("UPDATE mastercom_studenti SET foto = NULL WHERE id = " . intval($mirror['id']));
            }
        } else {
            $mastercomDelete = ['ok' => false, 'message' => 'Autenticazione MasterCom fallita'];
        }
    } else {
        $mastercomDelete = ['ok' => false, 'message' => 'ID studente/classe MasterCom mancante'];
    }
}

studenteFotoDeleteResponse([
    'ok' => $localDeleted,
    'message' => $localDeleted
        ? (!empty($mastercomDelete['ok']) ? 'Foto eliminata da GestOre e MasterCom' : 'Foto eliminata da GestOre. ' . ($mastercomDelete['message'] ?? ''))
        : 'Eliminazione foto locale fallita',
    'mastercom_delete' => $mastercomDelete,
]);
