<?php
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/__Log.php';

header('Content-Type: application/json; charset=utf-8');

set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Exception: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => "PHP error: $errstr in $errfile:$errline"
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
});

function miniSostJson(array $arr, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function miniSostNorm($v): string
{
    if ($v === null) return '';
    return trim((string)$v);
}

function h($s)
{
    return (string)$s;
}

function up($s)
{
    return strtoupper(trim((string)$s));
}

function normOra($o)
{
    $o = trim((string)$o);
    if ($o === '') return '';
    return substr($o, 0, 5);
}

function formatOraRange($oraInizio, $oraFine): string
{
    $i = normOra($oraInizio);
    $f = normOra($oraFine);

    if ($i !== '' && $f !== '') return $i . ' - ' . $f;
    if ($i !== '') return $i;
    if ($f !== '') return $f;

    return '';
}

function tableHasColumnCompat($tableName, $columnName)
{
    $tableName = dbEscape($tableName);
    $columnName = dbEscape($columnName);

    $q = "SHOW COLUMNS FROM `$tableName` LIKE '$columnName'";
    $rows = dbGetAll($q);
    return is_array($rows) && count($rows) > 0;
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

function miniSostValidateInitData(string $initData, string $botToken): array
{
    $initData = trim($initData);
    if ($initData === '') {
        return ['ok' => false, 'error' => 'initData mancante'];
    }

    parse_str($initData, $data);

    $hash = $data['hash'] ?? '';
    unset($data['hash']);

    if ($hash === '') {
        return ['ok' => false, 'error' => 'hash mancante'];
    }

    ksort($data);

    $checkArr = [];
    foreach ($data as $k => $v) {
        $checkArr[] = $k . '=' . $v;
    }

    $dataCheckString = implode("\n", $checkArr);

    $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
    $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

    if (!hash_equals($calculatedHash, $hash)) {
        return ['ok' => false, 'error' => 'initData non valida'];
    }

    return ['ok' => true, 'data' => $data];
}

function miniSostLogInfo(string $msg): void
{
    if (function_exists('infoTelegram')) {
        infoTelegram('[MINIAPP_SOST] ' . $msg);
    } elseif (function_exists('info')) {
        info('[MINIAPP_SOST] ' . $msg);
    } else {
        error_log('[MINIAPP_SOST] ' . $msg);
    }
}

$TELEGRAM_BOT_TOKEN = trim((string)($__settings->telegram->bot_token ?? ''));
if ($TELEGRAM_BOT_TOKEN === '') {
    miniSostJson([
        'ok' => false,
        'error' => 'Bot token mancante'
    ], 500);
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
$scope = miniSostNorm($input['scope'] ?? 'today');
if ($scope !== 'today' && $scope !== 'year') {
    $scope = 'today';
}

if (!is_array($input)) {
    miniSostJson([
        'ok' => false,
        'error' => 'JSON non valido'
    ], 400);
}

$initData = miniSostNorm($input['initData'] ?? '');
$check = miniSostValidateInitData($initData, $TELEGRAM_BOT_TOKEN);

if (empty($check['ok'])) {
    miniSostJson([
        'ok' => false,
        'error' => $check['error'] ?? 'initData non valida'
    ], 401);
}

$data = $check['data'] ?? [];
$userJson = $data['user'] ?? '';
$user = json_decode($userJson, true);

if (!is_array($user)) {
    miniSostJson([
        'ok' => false,
        'error' => 'Dati utente Telegram non validi'
    ], 400);
}

$telegramUserId = miniSostNorm($user['id'] ?? '');
if ($telegramUserId === '') {
    miniSostJson([
        'ok' => false,
        'error' => 'Telegram user id mancante'
    ], 400);
}

miniSostLogInfo("telegramUserId=[$telegramUserId]");

/**
 * Trova docente collegato a Telegram
 */
$qDoc = "
    SELECT
        d.id,
        d.cognome,
        d.nome,
        d.email,
        t.telegram_chat_id
    FROM docente_telegram t
    INNER JOIN docente d ON d.id = t.idDocente
    WHERE t.telegram_chat_id = " . dbQ($telegramUserId) . "
      AND t.attivo = 1
      AND t.consenso_notifiche = 1
    LIMIT 1
";

$doc = dbGetFirst($qDoc);

if (!$doc) {
    miniSostJson([
        'ok' => false,
        'error' => 'Nessun docente collegato a questo account Telegram'
    ], 404);
}

$currentDocenteId = (int)($doc['id'] ?? 0);
if ($currentDocenteId <= 0) {
    miniSostJson([
        'ok' => false,
        'error' => 'Docente non valido'
    ], 500);
}

$docenteNome = trim(h($doc['cognome'] ?? '') . ' ' . h($doc['nome'] ?? ''));

$date = date('Y-m-d');
$startYear = getSchoolYearStartDate($date);
$hasStato = tableHasColumnCompat('sostituzioni', 'stato');

$whereParts = [];

/* filtro data */
if ($scope === 'year') {
    $whereParts[] = "s.data >= " . dbQ($startYear);
    $whereParts[] = "s.data <= " . dbQ($date);
} else {
    $whereParts[] = "s.data = " . dbQ($date);
}

/* escludi le sostituzioni annullate */
if ($hasStato) {
    $whereParts[] = "(s.stato IS NULL OR UPPER(TRIM(s.stato)) <> 'ANNULLATA')";
}

/* dove il docente è sostituto oppure sostituito */
$whereParts[] = "(s.idDocenteSostituto = " . dbI($currentDocenteId) . " OR s.idDocenteSostituito = " . dbI($currentDocenteId) . ")";

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

miniSostLogInfo('q=' . preg_replace('/\s+/', ' ', trim($q)));

$rows = dbGetAll($q);
if (!is_array($rows)) $rows = [];

$items = [];

foreach ($rows as $r) {
    $docenteSostituito = trim(h($r['cognomeSostituito'] ?? '') . ' ' . h($r['nomeSostituito'] ?? ''));
    $docenteSostituto  = trim(h($r['cognomeSostituto'] ?? '') . ' ' . h($r['nomeSostituto'] ?? ''));

    if ($docenteSostituito === '' && $docenteSostituto === '') {
        continue;
    }

    $ruoloDocente = ((int)($r['idDocenteSostituito'] ?? 0) === $currentDocenteId) ? 'sostituito' : 'sostituto';

    $items[] = [
        'idSostituzione'     => (int)($r['idSostituzione'] ?? 0),
        'data'               => trim(h($r['data'] ?? '')),
        'data_fmt'           => date('d/m/Y', strtotime((string)($r['data'] ?? ''))),
        'stato'              => trim(h($r['stato'] ?? '')),

        'docente_sostituito' => up($docenteSostituito),
        'docente_sostituto'  => up($docenteSostituto),

        'classe'             => strtoupper(trim(h($r['classe'] ?? ''))),
        'aula'               => trim(h($r['aula'] ?? '')),
        'materia'            => trim(h($r['materia'] ?? '')),

        'ora_inizio'         => normOra($r['oraInizio'] ?? ''),
        'ora_fine'           => normOra($r['oraFine'] ?? ''),
        'ora_range_fmt'      => formatOraRange($r['oraInizio'] ?? '', $r['oraFine'] ?? ''),

        'ruolo_docente'      => $ruoloDocente
    ];
}

miniSostJson([
    'ok' => true,
    'scope' => $scope,
    'date' => $date,
    'startYear' => $startYear,
    'docente' => [
        'id' => $currentDocenteId,
        'nome' => $docenteNome,
        'email' => miniSostNorm($doc['email'] ?? '')
    ],
    'counts' => [
        'totale' => count($items)
    ],
    'sostituzioni' => $items
], 200);