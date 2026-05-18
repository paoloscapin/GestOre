<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/noirc_lib.php';

ruoloRichiesto('segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

$date = trim((string)($_POST['data'] ?? ''));
$hour = trim((string)($_POST['ora'] ?? ''));
$studentsJson = (string)($_POST['students'] ?? '[]');
$students = json_decode($studentsJson, true);
if (!is_array($students)) {
    $students = [];
}

$today = (new DateTime('now', new DateTimeZone('Europe/Rome')))->format('Y-m-d');
if ($date !== $today) {
    echo json_encode(['ok' => true, 'results' => [], 'message' => 'Presenza live disponibile solo per oggi'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($hour === '') {
    $hour = (new DateTime('now', new DateTimeZone('Europe/Rome')))->format('H:i');
}

$normalized = [];
foreach ($students as $student) {
    if (!is_array($student)) {
        continue;
    }
    $studentId = intval($student['mastercom_id_studente'] ?? 0);
    $classId = intval($student['mastercom_id_classe_corrente'] ?? 0);
    if ($studentId <= 0 || $classId <= 0) {
        continue;
    }
    $normalized[$studentId] = [
        'mastercom_id_studente' => $studentId,
        'mastercom_id_classe_corrente' => $classId,
        'nome' => trim((string)($student['nome'] ?? '')),
        'cognome' => trim((string)($student['cognome'] ?? '')),
        'classe' => trim((string)($student['classe'] ?? '')),
    ];
}

if (empty($normalized)) {
    echo json_encode(['ok' => true, 'results' => [], 'message' => 'Nessuno studente da verificare'], JSON_UNESCAPED_UNICODE);
    exit;
}

$presence = mastercomNoIrcLoadPresenceMap(array_values($normalized), $date, $hour);
$map = is_array($presence['map'] ?? null) ? $presence['map'] : [];
$results = [];
foreach ($normalized as $studentId => $student) {
    $row = is_array($map[$studentId] ?? null) ? $map[$studentId] : [
        'stato' => 'NON_VERIFICATO',
        'label' => 'Da verificare',
        'detail' => trim((string)($presence['error'] ?? 'Snapshot non disponibile')),
    ];
    $state = strtoupper(trim((string)($row['stato'] ?? 'NON_VERIFICATO')));
    $color = '#777';
    if (in_array($state, ['PRESENTE', 'ENTRATA_RITARDO'], true)) {
        $color = 'green';
    } elseif (in_array($state, ['ASSENTE_MASTERCOM', 'USCITA', 'EVENTO', 'PERMESSO'], true)) {
        $color = 'red';
    }
    $results[$studentId] = [
        'stato' => $state,
        'label' => trim((string)($row['label'] ?? 'Da verificare')),
        'detail' => trim((string)($row['detail'] ?? '')),
        'color' => $color,
    ];
}

echo json_encode([
    'ok' => true,
    'date' => $date,
    'hour' => $hour,
    'results' => $results,
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
