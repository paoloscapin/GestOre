<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/connectMBApp.php';

ruoloRichiesto('personale-ata', 'portineria', 'segreteria-ata', 'docente', 'segreteria-docenti', 'segreteria-didattica', 'admin');
header('Content-Type: application/json; charset=utf-8');

global $__conMBApp, $__utente_ruolo;

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

if (!($__conMBApp instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Connessione MBApp non disponibile'], JSON_UNESCAPED_UNICODE);
    exit;
}

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

function normalizeForMatch($s)
{
    $s = strtoupper(trim((string)$s));
    $s = preg_replace('/\s+/', ' ', $s);
    return $s;
}

function containsWord($text, $needle)
{
    $text = normalizeForMatch($text);
    $needle = normalizeForMatch($needle);
    return strpos($text, $needle) !== false;
}

function isImpegno($mot, $det)
{
    return containsWord($mot, 'IMPEGNO') || containsWord($det, 'IMPEGNO');
}

function isSportello($mot, $det)
{
    return containsWord($mot, 'SPORTELLO') || containsWord($det, 'SPORTELLO');
}

function isUdienza($mot, $det)
{
    return containsWord($mot, 'UDIENZA') || containsWord($det, 'UDIENZA');
}

function isNonAssenzaDaEscludere($mot, $det)
{
    $motUp = normalizeForMatch($mot);
    $detUp = normalizeForMatch($det);
    $all   = trim($motUp . ' ' . $detUp);

    if ($motUp === 'IMPEGNO IN ISTITUTO') return true;
    if ($motUp === 'IMPEGNO') return true;
    if ($motUp === 'SPORTELLO') return true;
    if ($motUp === 'UDIENZA') return true;

    if (containsWord($all, 'IMPEGNO IN ISTITUTO')) return true;
    if (containsWord($all, 'SPORTELLO')) return true;
    if (containsWord($all, 'UDIENZA')) return true;

    if ($detUp === 'AULA NON DISPONIBILE') return true;
    if (containsWord($detUp, 'PAUSA PRANZO')) return true;
    if (containsWord($detUp, 'AULA STUDIO')) return true;

    return false;
}

function isPermessoBreve($mot, $det)
{
    return (strpos($mot, 'PERMESSO BREVE') !== false) || (strpos($det, 'PERMESSO BREVE') !== false);
}
function isPermessoGiorno($mot, $det)
{
    return (strpos($mot, 'PERMESSO') !== false || strpos($det, 'PERMESSO') !== false) && !isPermessoBreve($mot, $det);
}
function isUscita($mot, $det)
{
    return (strpos($mot, 'USCITA') !== false) || (strpos($det, 'USCITA') !== false);
}
function isUscitaFuori($mot, $det)
{
    return isUscita($mot, $det) && ((strpos($mot, 'FUORI') !== false) || (strpos($det, 'FUORI') !== false));
}
function isViaggio($mot, $det)
{
    return (strpos($mot, 'VIAGG') !== false || strpos($mot, 'ISTRUZ') !== false || strpos($mot, 'GITA') !== false ||
        strpos($det, 'VIAGG') !== false || strpos($det, 'ISTRUZ') !== false || strpos($det, 'GITA') !== false);
}
function isMalattia($mot, $det)
{
    return (strpos($mot, 'MALATT') !== false || strpos($det, 'MALATT') !== false);
}
function isLutto($mot, $det)
{
    return (strpos($mot, 'LUTTO') !== false || strpos($det, 'LUTTO') !== false);
}

function getOraInRow($a)
{
    $o = trim(h($a['oraInizioReale'] ?? ''));
    if ($o !== '') return normOra($o);
    return normOra(trim(h($a['oraInizio'] ?? '')));
}

function getOraFineRow($a)
{
    $o = trim(h($a['oraFineReale'] ?? ''));
    if ($o !== '') return normOra($o);
    return normOra(trim(h($a['oraFine'] ?? '')));
}

function getDocentiByAssenzaId($idAssenza)
{
    $id = (int)$idAssenza;
    if ($id <= 0) return [];

    $q = "
        SELECT DISTINCT
            CONCAT(u.cognome, ' ', u.nome) AS docente
        FROM utilizza ut
        JOIN utente u ON u.username = ut.username
        WHERE ut.IDassenza = $id
          AND ut.username IS NOT NULL
          AND ut.username <> ''
        ORDER BY docente
    ";

    $rows = mb_dbGetAll($q) ?: [];
    $out = [];
    foreach ($rows as $r) {
        $d = trim((string)($r['docente'] ?? ''));
        if ($d !== '') $out[] = $d;
    }
    return $out;
}

function getClassiByAssenzaId($idAssenza)
{
    $id = (int)$idAssenza;
    if ($id <= 0) return [];

    $q = "
        SELECT DISTINCT classe
        FROM occupa
        WHERE IDassenza = $id
          AND classe IS NOT NULL
          AND classe <> ''
        ORDER BY classe
    ";

    $rows = mb_dbGetAll($q) ?: [];
    $out = [];

    foreach ($rows as $r) {
        $c = trim((string)($r['classe'] ?? ''));
        if ($c !== '') $out[] = strtoupper($c);
    }

    return $out;
}

function classifyAssenzaListItem($a, $visibilityLevel)
{
    $motRaw = (string)($a['motivo'] ?? '');
    $detRaw = trim((string)($a['dettagli'] ?? ''));

    if (isNonAssenzaDaEscludere($motRaw, $detRaw)) {
        return null;
    }

    $mot = normalizeForMatch($motRaw);
    $det = normalizeForMatch($detRaw);

    $type = '';
    $tipoLabel = '';
    $baseTitle = '';

    if (isPermessoBreve($mot, $det)) {
        $type = 'pb';
        $tipoLabel = 'Permesso breve';
        $baseTitle = 'Permesso breve';
    } elseif (isPermessoGiorno($mot, $det)) {
        $type = 'perm';
        $tipoLabel = 'Permesso (giorno)';
        $baseTitle = 'Permesso (giorno)';
    } elseif (isMalattia($mot, $det)) {
        $type = 'mal';
        $tipoLabel = 'Malattia';
        $baseTitle = 'Malattia';
    } elseif (isLutto($mot, $det)) {
        $type = 'lutto';
        $tipoLabel = 'Lutto';
        $baseTitle = 'Lutto';
    } elseif (isViaggio($mot, $det)) {
        $type = 'viag';
        $tipoLabel = 'Viaggio di istruzione';
        $baseTitle = 'Viaggio di istruzione';
    } elseif (isUscitaFuori($mot, $det)) {
        $type = 'uscF';
        $tipoLabel = 'Uscita fuori comune';
        $baseTitle = 'Uscita fuori comune';
    } elseif (isUscita($mot, $det)) {
        $type = 'uscC';
        $tipoLabel = 'Uscita nel comune';
        $baseTitle = 'Uscita nel comune';
    } else {
        $type = 'altro';
        $tipoLabel = trim(h($a['motivo'] ?? 'Assenza'));
        $baseTitle = $tipoLabel !== '' ? $tipoLabel : 'Assenza';
    }

    $title = $baseTitle;
    $badge = $tipoLabel;
    $detail = $baseTitle;

    if ($visibilityLevel === 'FULL') {
        if ($detRaw !== '') {
            $title = $baseTitle . ' · ' . $detRaw;
            $detail = $title;
        }
    } elseif ($visibilityLevel === 'STAFF') {
        if (in_array($type, ['pb', 'perm', 'mal', 'lutto'], true)) {
            $title = 'Assente';
            $badge = 'Assenza';
            $detail = 'Assente';
        }
    }

    return [
        'type'      => $type,
        'title'     => $title,
        'badge'     => $badge,
        'detail'    => $detail,
        'oraInizio' => getOraInRow($a),
        'oraFine'   => getOraFineRow($a),
    ];
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

$dateEsc = mysqli_real_escape_string($__conMBApp, $date);

$q = "
    SELECT a.*
    FROM assenze a
    WHERE DATE(a.dataInizio) <= '$dateEsc'
      AND DATE(COALESCE(NULLIF(a.dataFine,''), a.dataInizio)) >= '$dateEsc'
      AND UPPER(TRIM(COALESCE(a.stato, ''))) = 'CONFERMATO'
    ORDER BY a.dataInizio, a.oraInizio, a.idAssenza
";

$rows = mb_dbGetAll($q) ?: [];
$items = [];

foreach ($rows as $a) {
    $idAss = (int)($a['idAssenza'] ?? 0);
    if ($idAss <= 0) continue;

    $motRaw = (string)($a['motivo'] ?? '');
    $detRaw = (string)($a['dettagli'] ?? '');

    // esclusione forte già qui
    if (isNonAssenzaDaEscludere($motRaw, $detRaw)) {
        continue;
    }

    $ev = classifyAssenzaListItem($a, $visibilityLevel);
    if ($ev === null) continue;

    $docenti = getDocentiByAssenzaId($idAss);
    if (empty($docenti)) continue;
    $classi = getClassiByAssenzaId($idAss);

    foreach ($docenti as $doc) {
        $items[] = [
            'source'    => 'assenze',
            'type'      => $ev['type'],
            'title'     => $ev['title'],
            'detail'    => $ev['detail'],
            'badge'     => $ev['badge'],
            'who'       => strtoupper(trim($doc)),
            'docente'   => strtoupper(trim($doc)),
            'classi'    => $classi,   // 🔴 QUI
            'rooms'     => [],
            'aula'      => '',
            'ora'       => $ev['oraInizio'],
            'oraInizio' => $ev['oraInizio'],
            'oraFine'   => $ev['oraFine'],
        ];
    }
}

echo json_encode([
    'ok' => true,
    'date' => $date,
    'visibilityLevel' => $visibilityLevel,
    'items' => $items
], JSON_UNESCAPED_UNICODE);
