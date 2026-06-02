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

function studenteFotoUploadResponse(array $payload): void
{
    echo json_encode($payload);
    exit;
}

$studentId = intval($_POST['studente_id'] ?? 0);
$imageData = trim((string)($_POST['image'] ?? ''));
if ($studentId <= 0 || $imageData === '') {
    studenteFotoUploadResponse(['ok' => false, 'message' => 'Dati foto mancanti']);
}

$student = dbGetFirst("SELECT * FROM studente WHERE id = " . intval($studentId) . " LIMIT 1");
if (!$student) {
    studenteFotoUploadResponse(['ok' => false, 'message' => 'Studente non trovato']);
}

if (!preg_match('/^data:image\/jpe?g;base64,(.+)$/', $imageData, $matches)) {
    studenteFotoUploadResponse(['ok' => false, 'message' => 'Formato immagine non valido']);
}

$binary = base64_decode($matches[1], true);
if ($binary === false || strlen($binary) < 1000) {
    studenteFotoUploadResponse(['ok' => false, 'message' => 'Immagine vuota o non valida']);
}

if (!gestoreEnsureStudentPhotoDir()) {
    studenteFotoUploadResponse(['ok' => false, 'message' => 'Cartella foto studenti non scrivibile']);
}

$photoPath = gestoreStudentPhotoPath($studentId);
if (file_put_contents($photoPath, $binary) === false) {
    studenteFotoUploadResponse(['ok' => false, 'message' => 'Salvataggio foto GestOre fallito']);
}

$localUrl = gestoreStudentPhotoUrl($studentId);
$mastercomUpload = ['ok' => false, 'message' => 'Studente non collegato a MasterCom'];

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
            $uploadResult = mastercomUploadStudentPhoto($authResult, $mastercomStudentId, $mastercomClassId, $className, $photoPath, [
                'timeout' => 120,
            ]);
            $mastercomUpload = [
                'ok' => !empty($uploadResult['ok']),
                'message' => !empty($uploadResult['ok'])
                    ? 'Foto inviata a MasterCom'
                    : ('Upload MasterCom fallito: ' . ($uploadResult['error'] ?? 'errore sconosciuto')),
                'http_code' => intval($uploadResult['http_code'] ?? 0),
            ];
        } else {
            $mastercomUpload = ['ok' => false, 'message' => 'Autenticazione MasterCom fallita'];
        }
    } else {
        $mastercomUpload = ['ok' => false, 'message' => 'ID studente/classe MasterCom mancante'];
    }
}

studenteFotoUploadResponse([
    'ok' => true,
    'message' => !empty($mastercomUpload['ok'])
        ? 'Foto salvata in GestOre e inviata a MasterCom'
        : 'Foto salvata in GestOre. ' . ($mastercomUpload['message'] ?? ''),
    'local_url' => $localUrl,
    'mastercom_upload' => $mastercomUpload,
]);
