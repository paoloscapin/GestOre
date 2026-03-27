<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/connectMBApp.php';

ruoloRichiesto('personale-ata', 'portineria', 'segreteria-ata', 'docente', 'segreteria-docenti', 'segreteria-didattica', 'admin');
header('Content-Type: application/json; charset=utf-8');

global $__utente_ruolo;
global $__utente_username, $__utente_nome, $__utente_cognome;

set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Exception: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
});
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => "PHP error: $errstr in $errfile:$errline"], JSON_UNESCAPED_UNICODE);
    exit;
});

function h($s)
{
    return (string)$s;
}

function isIsoDate($d)
{
    return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d);
}

function normOra($o)
{
    $o = trim((string)$o);
    if ($o === '') return '';
    return substr($o, 0, 5);
}

function up($s)
{
    return strtoupper(trim((string)$s));
}

function getVisibilityLevel()
{
    global $__utente_ruolo;

    $ruoloUp = strtoupper(trim((string)$__utente_ruolo));

    if (in_array($ruoloUp, ['ADMIN', 'SEGRETERIA-DIDATTICA', 'SEGRETERIA-ATA', 'PORTINERIA'], true)) {
        return 'FULL';
    }

    if (in_array($ruoloUp, ['DOCENTE', 'PERSONALE-ATA'], true)) {
        return 'STAFF';
    }

    return 'PUBLIC';
}

function isDocenteRole()
{
    global $__utente_ruolo;
    return strtoupper(trim((string)$__utente_ruolo)) === 'DOCENTE';
}

function getCurrentDocenteId()
{
    global $__utente_username, $__utente_nome, $__utente_cognome;

    // 1) se in sessione hai già l'id docente, usa quello
    if (!empty($_SESSION['idDocente'])) {
        return (int)$_SESSION['idDocente'];
    }
    if (!empty($_SESSION['id_docente'])) {
        return (int)$_SESSION['id_docente'];
    }

    // 2) fallback: match per nome/cognome
    $nome = trim((string)($__utente_nome ?? ''));
    $cognome = trim((string)($__utente_cognome ?? ''));

    if ($nome !== '' && $cognome !== '') {
        $q = "
            SELECT id
            FROM docente
            WHERE UPPER(TRIM(nome)) = UPPER(" . dbQ($nome) . ")
              AND UPPER(TRIM(cognome)) = UPPER(" . dbQ($cognome) . ")
            LIMIT 1
        ";
        $row = dbGetFirst($q);
        if (is_array($row) && !empty($row['id'])) {
            return (int)$row['id'];
        }
    }

    return 0;
}

$date = isset($_GET['date']) ? trim((string)$_GET['date']) : date('Y-m-d');

$mineOnly = isset($_GET['mineOnly']) ? (int)$_GET['mineOnly'] : 0;
$isDocente = isDocenteRole();
$currentDocenteId = getCurrentDocenteId();

if (!isIsoDate($date)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Data non valida'], JSON_UNESCAPED_UNICODE);
    exit;
}

$visibilityLevel = getVisibilityLevel();
if ($visibilityLevel === 'PUBLIC') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Non autorizzato'], JSON_UNESCAPED_UNICODE);
    exit;
}

$whereExtra = '';

if ($isDocente && $mineOnly === 1 && $currentDocenteId > 0) {
    $whereExtra .= "
        AND (
            s.idDocenteSostituto = " . dbI($currentDocenteId) . "
            OR s.idDocenteSostituito = " . dbI($currentDocenteId) . "
        )
    ";
}

$q = "
    SELECT
        s.idSostituzione,
        s.data,
        s.oraInizio,
        s.oraFine,
        s.materia,
        s.classe,
        s.aula,

        ds.id       AS idDocenteSostituto,
        ds.cognome  AS cognomeSostituto,
        ds.nome     AS nomeSostituto,

        dd.id       AS idDocenteSostituito,
        dd.cognome  AS cognomeSostituito,
        dd.nome     AS nomeSostituito

    FROM sostituzioni s
    LEFT JOIN docente ds ON ds.id = s.idDocenteSostituto
    LEFT JOIN docente dd ON dd.id = s.idDocenteSostituito
    WHERE s.data = " . dbQ($date) . "
    $whereExtra
    ORDER BY s.oraInizio, s.oraFine, dd.cognome, dd.nome, ds.cognome, ds.nome
";

$rows = dbGetAll($q);
if (!is_array($rows)) $rows = [];

$items = [];

foreach ($rows as $r) {
    $docenteSostituito = trim(h($r['cognomeSostituito'] ?? '') . ' ' . h($r['nomeSostituito'] ?? ''));
    $docenteSostituto  = trim(h($r['cognomeSostituto'] ?? '') . ' ' . h($r['nomeSostituto'] ?? ''));

    if ($docenteSostituito === '' && $docenteSostituto === '') {
        continue;
    }

    $items[] = [
        'source'             => 'sostituzioni',
        'type'               => 'sost',
        'title'              => 'Sostituzione',
        'detail'             => trim(h($r['materia'] ?? '')),
        'badge'              => 'Sostituzione',

        'idSostituzione'     => (int)($r['idSostituzione'] ?? 0),

        'docenteSostituito'  => up($docenteSostituito),
        'docenteSostituto'   => up($docenteSostituto),

        'who'                => up($docenteSostituito),
        'docente'            => up($docenteSostituito),

        'classi'             => trim(h($r['classe'] ?? '')) !== '' ? [strtoupper(trim(h($r['classe'] ?? '')))] : [],
        'rooms'              => trim(h($r['aula'] ?? '')) !== '' ? [trim(h($r['aula'] ?? ''))] : [],

        'classe'             => strtoupper(trim(h($r['classe'] ?? ''))),
        'aula'               => trim(h($r['aula'] ?? '')),
        'materia'            => trim(h($r['materia'] ?? '')),

        'ora'                => normOra($r['oraInizio'] ?? ''),
        'oraInizio'          => normOra($r['oraInizio'] ?? ''),
        'oraFine'            => normOra($r['oraFine'] ?? ''),
    ];
}

echo json_encode([
    'ok' => true,
    'date' => $date,
    'visibilityLevel' => $visibilityLevel,
    'mineOnly' => $mineOnly,
    'isDocente' => $isDocente,
    'currentDocenteId' => $currentDocenteId,
    'items' => $items
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);