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
    global $__utente_nome, $__utente_cognome;

    if (!empty($_SESSION['idDocente'])) {
        return (int)$_SESSION['idDocente'];
    }
    if (!empty($_SESSION['id_docente'])) {
        return (int)$_SESSION['id_docente'];
    }

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

function getSchoolYearStartDate($referenceDate)
{
    $referenceDate = trim((string)$referenceDate);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $referenceDate)) {
        $referenceDate = date('Y-m-d');
    }

    $year = (int)substr($referenceDate, 0, 4);
    $month = (int)substr($referenceDate, 5, 2);

    if ($month >= 9) {
        return sprintf('%04d-09-01', $year);
    }

    return sprintf('%04d-09-01', $year - 1);
}

function tableHasColumnCompat($tableName, $columnName)
{
    $tableName = dbEscape($tableName);
    $columnName = dbEscape($columnName);

    $q = "SHOW COLUMNS FROM `$tableName` LIKE '$columnName'";
    $rows = dbGetAll($q);
    return is_array($rows) && count($rows) > 0;
}

$date = isset($_GET['date']) ? trim((string)$_GET['date']) : date('Y-m-d');

$mode = isset($_GET['mode']) ? trim((string)$_GET['mode']) : '';
$mineOnly = isset($_GET['mineOnly']) ? (int)$_GET['mineOnly'] : 0; // compatibilità vecchia
$isDocente = isDocenteRole();
$currentDocenteId = getCurrentDocenteId();
$hasStato = tableHasColumnCompat('sostituzioni', 'stato');

if ($mode === '') {
    if ($isDocente) {
        $mode = ($mineOnly === 1) ? 'mine_today' : 'all_today';
    } else {
        $mode = 'all_today';
    }
}

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

$whereParts = [];

/* escludi le sostituzioni annullate */
if ($hasStato) {
    $whereParts[] = "(s.stato IS NULL OR UPPER(TRIM(s.stato)) <> 'ANNULLATA')";
}

if ($mode === 'mine_year' && $isDocente && $currentDocenteId > 0) {
    $startYear = getSchoolYearStartDate($date);

    $whereParts[] = "s.data >= " . dbQ($startYear);
    $whereParts[] = "s.data <= " . dbQ($date);

    // SOLO dove il docente è il sostituto
    $whereParts[] = "s.idDocenteSostituto = " . dbI($currentDocenteId);
} else {
    $whereParts[] = "s.data = " . dbQ($date);

    if ($mode === 'mine_today' && $isDocente && $currentDocenteId > 0) {
        // SOLO dove il docente è il sostituto
        $whereParts[] = "s.idDocenteSostituto = " . dbI($currentDocenteId);
    }
}

$whereSql = implode("\n      AND ", $whereParts);

$q = "
    SELECT
        s.idSostituzione,
        s.data,
        s.oraInizio,
        s.oraFine,
        s.materia,
        s.classe,
        s.aula" .
        ($hasStato ? ",
        s.stato" : ",
        '' AS stato") . ",

        ds.id       AS idDocenteSostituto,
        ds.cognome  AS cognomeSostituto,
        ds.nome     AS nomeSostituto,

        dd.id       AS idDocenteSostituito,
        dd.cognome  AS cognomeSostituito,
        dd.nome     AS nomeSostituito

    FROM sostituzioni s
    LEFT JOIN docente ds ON ds.id = s.idDocenteSostituto
    LEFT JOIN docente dd ON dd.id = s.idDocenteSostituito
    WHERE $whereSql
    ORDER BY s.data DESC, s.oraInizio, s.oraFine, dd.cognome, dd.nome, ds.cognome, ds.nome
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
        'data'               => trim(h($r['data'] ?? '')),
        'stato'              => trim(h($r['stato'] ?? '')),

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
    'mode' => $mode,
    'visibilityLevel' => $visibilityLevel,
    'isDocente' => $isDocente,
    'currentDocenteId' => $currentDocenteId,
    'hasStato' => $hasStato,
    'items' => $items
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);