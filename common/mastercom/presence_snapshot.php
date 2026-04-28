<?php

/**
 *  This file is part of GestOre
 *  @author     OpenAI Codex
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/../checkSession.php';
require_once __DIR__ . '/../__MasterCom.php';

ruoloRichiesto('segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

$classId = intval($_GET['class_id'] ?? $_POST['class_id'] ?? 0);
if ($classId <= 0) {
    echo json_encode([
        'ok' => false,
        'message' => 'Parametro class_id mancante o non valido',
    ]);
    exit;
}

function classifyAppealState(array $appealRow): array
{
    $assenze = $appealRow['assenze'] ?? [];
    $entrate = $appealRow['entrate'] ?? [];
    $uscite = $appealRow['uscite'] ?? [];
    $permessi = $appealRow['permessi'] ?? [];
    $eventi = $appealRow['eventi'] ?? [];
    $haLezione = intval($appealRow['ha_lezione'] ?? 0) === 1;

    $status = 'nessuna_lezione';
    $presentAtSchool = false;
    $presentInClass = false;

    if (!empty($eventi)) {
        $status = 'evento';
        $presentAtSchool = true;
    } elseif (!empty($permessi)) {
        $status = 'permesso';
    } elseif (!empty($uscite)) {
        $status = 'uscita';
    } elseif (!empty($assenze)) {
        $status = 'assente';
    } elseif (!empty($entrate)) {
        $status = 'presente_entrato_in_ritardo';
        $presentAtSchool = true;
        $presentInClass = true;
    } elseif ($haLezione) {
        $status = 'presente_in_classe';
        $presentAtSchool = true;
        $presentInClass = true;
    }

    return [
        'status' => $status,
        'present_at_school' => $presentAtSchool,
        'present_in_class' => $presentInClass,
        'ha_lezione' => $haLezione,
    ];
}

$authResult = mastercomAuthenticateService([
    'profile' => 'MasterComDocenteAuth',
    'method' => 'POST',
    'timeout' => 60,
]);

if (!$authResult['ok']) {
    echo json_encode([
        'ok' => false,
        'message' => 'Autenticazione MasterCom docente fallita',
        'error' => $authResult['error'] ?? 'AUTH_FAILED',
        'http_code' => $authResult['http_code'] ?? 0,
    ]);
    exit;
}

$studentsResult = mastercomLoadStudentsList($authResult, $classId, [
    'method' => 'POST',
    'timeout' => 120,
]);
$appealResult = mastercomLoadAppealData($authResult, $classId, [
    'method' => 'POST',
    'timeout' => 120,
]);

if (!$studentsResult['ok'] || !$appealResult['ok']) {
    echo json_encode([
        'ok' => false,
        'message' => 'Caricamento dati presenza MasterCom fallito',
        'students_error' => $studentsResult['error'] ?? '',
        'appeal_error' => $appealResult['error'] ?? '',
    ]);
    exit;
}

$students = $studentsResult['response']['result'] ?? [];
$appeal = $appealResult['response']['result'] ?? [];

$records = [];
$summary = [
    'presenti_in_classe' => 0,
    'presenti_a_scuola_ma_fuori_classe' => 0,
    'assenti' => 0,
    'usciti_o_permesso' => 0,
    'senza_lezione' => 0,
];

foreach ($students as $student) {
    $studentId = intval($student['id_studente'] ?? 0);
    $appealRow = $appeal[(string)$studentId] ?? [];
    $state = classifyAppealState(is_array($appealRow) ? $appealRow : []);

    if ($state['status'] === 'presente_in_classe' || $state['status'] === 'presente_entrato_in_ritardo') {
        $summary['presenti_in_classe']++;
    } elseif ($state['status'] === 'evento') {
        $summary['presenti_a_scuola_ma_fuori_classe']++;
    } elseif ($state['status'] === 'assente') {
        $summary['assenti']++;
    } elseif ($state['status'] === 'uscita' || $state['status'] === 'permesso') {
        $summary['usciti_o_permesso']++;
    } else {
        $summary['senza_lezione']++;
    }

    $records[] = [
        'id_studente' => $studentId,
        'cognome' => $student['cognome'] ?? '',
        'nome' => $student['nome'] ?? '',
        'registro' => $student['registro'] ?? null,
        'status' => $state['status'],
        'present_at_school' => $state['present_at_school'],
        'present_in_class' => $state['present_in_class'],
        'ha_lezione' => $state['ha_lezione'],
        'appeal' => $appealRow,
    ];
}

echo json_encode([
    'ok' => true,
    'class_id' => $classId,
    'summary' => $summary,
    'records' => $records,
    'debug' => $appealResult['response']['debug_code'] ?? null,
    'note' => 'Questo snapshot usa get_appeal_data, quindi rappresenta la situazione corrente e non uno storico arbitrario.',
], JSON_UNESCAPED_UNICODE);

