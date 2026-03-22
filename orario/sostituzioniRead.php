<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/connectMBApp.php';

ruoloRichiesto('personale-ata', 'portineria', 'segreteria-ata', 'docente', 'segreteria-docenti', 'segreteria-didattica', 'admin');
header('Content-Type: application/json; charset=utf-8');

global $__utente_ruolo;

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

$date = isset($_GET['date']) ? trim((string)$_GET['date']) : date('Y-m-d');

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
    'items' => $items
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);