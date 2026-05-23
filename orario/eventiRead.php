<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/connectMBApp.php';

ruoloRichiesto('personale-ata', 'portineria', 'segreteria-ata', 'docente', 'studente', 'genitore', 'segreteria-docenti', 'segreteria-didattica');
header('Content-Type: application/json; charset=utf-8');

global $__conMBApp;

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

/* ===================== helpers base ===================== */
function h($s) { return (string)$s; }

function isIsoDate($d) {
    return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d);
}

function up($s) {
    return strtoupper(trim((string)$s));
}

/**
 * Se $o è:
 * - "13:50" o "13:50:00" => "13:50"
 * - "31333A3530" (HEX di "13:50") => "13:50"
 * - binario con caratteri strani => prova a ripulire
 */
function normOra($o) {
    if ($o === null) return '';
    $o = (string)$o;

    // caso HEX puro (es. 31333A3530)
    $t = trim($o);
    if ($t !== '' && preg_match('/^[0-9A-Fa-f]+$/', $t) && (strlen($t) % 2 === 0) && strlen($t) >= 8) {
        $decoded = @hex2bin($t);
        if ($decoded !== false) {
            $t = trim((string)$decoded);
        }
    }

    // ripulisce eventuali byte null
    $t = str_replace("\0", '', $t);
    $t = trim($t);
    if ($t === '') return '';

    // se c'è già HH:MM:SS o HH:MM
    if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $t)) {
        return substr($t, 0, 5);
    }

    // prova a estrarre HH:MM da dentro stringa più lunga
    if (preg_match('/(\d{1,2}:\d{2})/', $t, $m)) {
        $hhmm = $m[1];
        // normalizza 8:40 -> 08:40
        if (preg_match('/^(\d):/', $hhmm)) $hhmm = '0' . $hhmm;
        return $hhmm;
    }

    return '';
}

function splitCsvUnique($csv) {
    $csv = trim((string)$csv);
    if ($csv === '') return [];
    $parts = preg_split('/\s*,\s*/', $csv, -1, PREG_SPLIT_NO_EMPTY);
    $out = [];
    $seen = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        if (isset($seen[$p])) continue;
        $seen[$p] = true;
        $out[] = $p;
    }
    return $out;
}

function uniqCaseInsensitive($arr) {
    $out = [];
    $seen = [];
    foreach ($arr as $x) {
        $k = strtoupper(trim((string)$x));
        if ($k === '' || isset($seen[$k])) continue;
        $seen[$k] = true;
        $out[] = trim((string)$x);
    }
    return $out;
}

function mergeUniqueStrings($left, $right) {
    return uniqCaseInsensitive(array_merge((array)$left, (array)$right));
}

function splitDocentiVisual($csv) {
    $csv = trim((string)$csv);
    if ($csv === '') return [];

    $parts = preg_split('/\s*,\s*/', $csv, -1, PREG_SPLIT_NO_EMPTY);

    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;

        // username tipo nome.cognome -> Cognome Nome
        if (preg_match('/^[a-z]+\.[a-z]+$/i', $p)) {
            list($nome, $cognome) = explode('.', $p, 2);
            $nome = ucfirst(strtolower($nome));
            $cognome = ucfirst(strtolower($cognome));
            $p = $cognome . ' ' . $nome;
        }

        $out[] = $p;
    }

    return array_values(uniqCaseInsensitive($out));
}

function isUdienzaText($s) {
    $u = up($s);
    if ($u === 'UD') return true;
    if (strpos($u, 'UD ') === 0) return true;
    if (strpos($u, 'UD-') === 0) return true;
    if (strpos($u, 'UD.') === 0) return true;
    return (strpos($u, 'UDIENZ') !== false || strpos($u, 'UDIENZE') !== false);
}

function isUscita($mot, $det) {
    return (strpos($mot, 'USCITA') !== false) || (strpos($det, 'USCITA') !== false);
}
function isUscitaFuori($mot, $det) {
    return isUscita($mot, $det) && ((strpos($mot, 'FUORI') !== false) || (strpos($det, 'FUORI') !== false));
}
function isViaggio($mot, $det) {
    return (strpos($mot, 'VIAGG') !== false || strpos($mot, 'ISTRUZ') !== false || strpos($mot, 'GITA') !== false ||
            strpos($det, 'VIAGG') !== false || strpos($det, 'ISTRUZ') !== false || strpos($det, 'GITA') !== false);
}
function isSportello($mot, $det) {
    return (strpos($mot, 'SPORTELLO') !== false) || (strpos($det, 'SPORTELLO') !== false);
}
function isImpegno($mot, $det) {
    return (strpos($mot, 'IMPEGNO') !== false) || (strpos($det, 'IMPEGNO') !== false);
}
function isExcludedSpecial($text) {
    $t = up($text);
    if ($t === 'AULA NON DISPONIBILE') return true;
    if (strpos($t, 'PRANZO') !== false) return true;
    if (strpos($t, 'AULA S') !== false) return true;
    return false;
}

function getDocentiNomiByAssenzaId($idAssenza) {
    $id = (int)$idAssenza;
    if ($id <= 0) return [];

    $q = "
        SELECT DISTINCT CONCAT(u.cognome,' ',u.nome) AS nom
        FROM utilizza ut
        JOIN utente u ON u.username = ut.username
        WHERE ut.IDassenza = $id
          AND ut.username IS NOT NULL AND ut.username <> ''

        UNION

        SELECT DISTINCT CONCAT(u.cognome,' ',u.nome) AS nom
        FROM oralezione o
        JOIN utilizza ut ON ut.idCalendario = o.idCalendario
        JOIN utente u ON u.username = ut.username
        WHERE o.idAssenza = $id
          AND ut.username IS NOT NULL AND ut.username <> ''

        ORDER BY nom
    ";

    $rows = mb_dbGetAll($q) ?: [];
    $out = [];

    foreach ($rows as $r) {
        $n = trim((string)($r['nom'] ?? ''));
        if ($n !== '') $out[] = $n;
    }

    return $out;
}

function getClassiByAssenzaId($idAssenza) {
    $id = (int)$idAssenza;
    if ($id <= 0) return [];
    $q = "SELECT DISTINCT classe
          FROM occupa
          WHERE IDassenza = $id AND classe <> ''
          ORDER BY classe";
    $rows = mb_dbGetAll($q) ?: [];
    $out = [];
    foreach ($rows as $r) {
        $c = trim((string)($r['classe'] ?? ''));
        if ($c !== '') $out[] = $c;
    }
    return $out;
}

function upName($s) {
    $s = trim((string)$s);
    if ($s === '') return '';
    return mb_strtoupper($s, 'UTF-8');
}
function upNamesLines($s) {
    $s = trim((string)$s);
    if ($s === '') return '';
    $lines = preg_split("/\r\n|\n|\r/", $s);
    $lines = array_map('upName', $lines);
    return trim(implode("\n", array_filter($lines, fn($x) => $x !== '')));
}

function getDocentiNomiFromUsernamesCsv($csv) {
    global $__conMBApp;

    $usernames = splitCsvUnique($csv);
    if (empty($usernames)) return [];

    $safe = [];
    foreach ($usernames as $u) {
        $u = trim((string)$u);
        if ($u === '') continue;
        $safe[] = "'" . mysqli_real_escape_string($__conMBApp, $u) . "'";
    }

    if (empty($safe)) return [];

    $q = "
        SELECT DISTINCT CONCAT(cognome, ' ', nome) AS nom
        FROM utente
        WHERE username IN (" . implode(',', $safe) . ")
        ORDER BY nom
    ";

    $rows = mb_dbGetAll($q) ?: [];
    $out = [];

    foreach ($rows as $r) {
        $n = trim((string)($r['nom'] ?? ''));
        if ($n !== '') $out[] = $n;
    }

    return $out;
}

function getOraInRow($a) {
    return normOra($a['oraInizioReale_txt'] ?? '') ?: normOra($a['oraInizio_txt'] ?? '') ?: normOra($a['oraInizioReale'] ?? '') ?: normOra($a['oraInizio'] ?? '');
}
function getOraFineRow($a) {
    return normOra($a['oraFineReale_txt'] ?? '') ?: normOra($a['oraFine_txt'] ?? '') ?: normOra($a['oraFineReale'] ?? '') ?: normOra($a['oraFine'] ?? '');
}

function buildAssIndexByOraAtt($assRows) {
    $idx = [];
    foreach ($assRows as $a) {
        $detRaw = trim((string)($a['dettagli'] ?? ''));
        if ($detRaw === '') continue;
        if (isExcludedSpecial($detRaw)) continue;
        if (isUdienzaText($detRaw) || isUdienzaText($a['motivo'] ?? '')) continue;

        $oraIn = getOraInRow($a);
        if ($oraIn === '') continue;

        $key = $oraIn . '|' . up($detRaw);

        $oraFine = getOraFineRow($a);
        $doc = trim((string)($a['docenti'] ?? ''));

        if (!isset($idx[$key])) {
            $idx[$key] = ['oraFine' => $oraFine, 'docenti' => $doc];
        } else {
            if (($idx[$key]['oraFine'] ?? '') === '' && $oraFine !== '') $idx[$key]['oraFine'] = $oraFine;
            if (($idx[$key]['docenti'] ?? '') === '' && $doc !== '') $idx[$key]['docenti'] = $doc;
        }
    }
    return $idx;
}

/* ===================== input ===================== */
$date = isset($_GET['date']) ? trim((string)$_GET['date']) : date('Y-m-d');
if (!isIsoDate($date)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Data non valida'], JSON_UNESCAPED_UNICODE);
    exit;
}
$dateEsc = mysqli_real_escape_string($__conMBApp, $date);

$items = [];
$itemsByAssenzaImp = []; // idAssenza => index in $items (solo per type='imp' da ASSENZE)
$itemsByOralezioneAssenza = []; // idAssenza => index in $items (eventi letti da oralezione)

/* ============================================================
   PRECARICO ASSENZE DEL GIORNO (CON CONVERT PER ORARI BINARI)
   ============================================================ */
$qAss = "
  SELECT
    a.*,
    CONVERT(a.oraInizioReale USING utf8) AS oraInizioReale_txt,
    CONVERT(a.oraFineReale   USING utf8) AS oraFineReale_txt,
    CONVERT(a.oraInizio      USING utf8) AS oraInizio_txt,
    CONVERT(a.oraFine        USING utf8) AS oraFine_txt
  FROM assenze a
  WHERE DATE(a.dataInizio) <= '$dateEsc'
    AND DATE(COALESCE(a.dataFine, a.dataInizio)) >= '$dateEsc'
    AND UPPER(TRIM(COALESCE(a.stato, ''))) = 'CONFERMATO'
";
$assRows = mb_dbGetAll($qAss) ?: [];

$assById = [];
foreach ($assRows as $a) {
    $id = (int)($a['idAssenza'] ?? 0);
    if ($id > 0) $assById[$id] = $a;
}

$assIdxOraAtt = buildAssIndexByOraAtt($assRows);

/* ============================================================
   1) USCITE / VIAGGI (da assenze)
   ============================================================ */
foreach ($assRows as $a) {
    $mot = up($a['motivo'] ?? '');
    $detRaw = trim(h($a['dettagli'] ?? ''));
    $det = up($detRaw);

    if (!isUscita($mot, $det) && !isViaggio($mot, $det)) continue;

    $idAss = (int)($a['idAssenza'] ?? 0);
    if ($idAss <= 0) continue;

    $type = isViaggio($mot, $det) ? 'viag' : (isUscitaFuori($mot, $det) ? 'uscF' : 'uscC');
    $baseTitle = ($type === 'viag') ? 'Viaggio di istruzione' : (($type === 'uscF') ? 'Uscita fuori comune' : 'Uscita nel comune');
    $title = $baseTitle . ($detRaw !== '' ? ' · ' . $detRaw : '');

    $whoArr = getDocentiNomiByAssenzaId($idAss);

    $docField = trim(h($a['docenti'] ?? ''));
    $whoFromAssenze = getDocentiNomiFromUsernamesCsv($docField);

    $whoArr = mergeUniqueStrings($whoArr, $whoFromAssenze);

    $classi = getClassiByAssenzaId($idAss);

    $oraIn  = getOraInRow($a);
    $oraOut = getOraFineRow($a);

    $items[] = [
        'source'  => 'assenze',
        'type'    => $type,
        'title'   => $title,
        'badge'   => $baseTitle,
        'who'     => upNamesLines(implode("\n", $whoArr)),
        'classi'  => $classi,
        'rooms'   => [],
        'aula'    => '',
        'ora'     => $oraIn,
        'oraFine' => $oraOut,
    ];
}

/* ============================================================
   3) SPORTELLI / IMPEGNI (da assenze)
   ============================================================ */
foreach ($assRows as $a) {
    $mot = up($a['motivo'] ?? '');
    $detRaw = trim(h($a['dettagli'] ?? ''));
    $det = up($detRaw);

    if (isUscita($mot, $det) || isViaggio($mot, $det)) continue;
    if (!isImpegno($mot, $det) && !isSportello($mot, $det)) continue;
    if (isUdienzaText($mot) || isUdienzaText($detRaw)) continue;
    if (isExcludedSpecial($detRaw)) continue;

    $idAss = (int)($a['idAssenza'] ?? 0);
    if ($idAss <= 0) continue;

    $oraIn  = getOraInRow($a);
    $oraOut = getOraFineRow($a);

    $detail = ($detRaw !== '') ? $detRaw : trim(h($a['motivo'] ?? 'Impegno in istituto'));

    $docField = trim(h($a['docenti'] ?? ''));
    $whoArr = splitDocentiVisual($docField);
    $whoText = implode("\n", $whoArr);

    $classi = getClassiByAssenzaId($idAss);

    $badge = isSportello($mot, $det) ? 'Sportello didattico' : 'Impegno in istituto';

    $idx = count($items);
    $items[] = [
        'source'    => 'assenze',
        'type'      => 'imp',
        'title'     => $detail,
        'badge'     => $badge,
        'who'       => upNamesLines($whoText),
        'classi'    => $classi,
        'rooms'     => [],
        'aula'      => '',
        'ora'       => $oraIn,
        'oraFine'   => $oraOut,
        'idAssenza' => $idAss,
    ];

    $itemsByAssenzaImp[$idAss] = $idx;
}

/* ============================================================
   2) EVENTI DA ORALEZIONE (attivitaProgetto)
   ============================================================ */
$qImp = "
  SELECT
    o.idCalendario,
    o.idAssenza,
    o.dataGiorno,
    o.ora,
    o.nroAula,
    o.attivitaProgetto,
    GROUP_CONCAT(DISTINCT CONCAT(u.cognome,' ',u.nome) SEPARATOR ', ') AS docenti_nomi,
    GROUP_CONCAT(DISTINCT oc.classe ORDER BY oc.classe SEPARATOR ', ') AS classi
  FROM oralezione o
  LEFT JOIN utilizza ut ON ut.idCalendario = o.idCalendario
  LEFT JOIN utente u ON u.username = ut.username
  LEFT JOIN occupa oc ON oc.idCalendario = o.idCalendario
  LEFT JOIN assenze a ON a.idAssenza = o.idAssenza
  WHERE o.dataGiorno = '$dateEsc'
    AND (o.stato IS NULL OR o.stato <> 'CANCELLATO')
    AND o.attivitaProgetto IS NOT NULL AND o.attivitaProgetto <> ''
    AND (
      o.idAssenza IS NULL
      OR UPPER(TRIM(COALESCE(a.stato, ''))) = 'CONFERMATO'
    )
  GROUP BY o.idCalendario, o.idAssenza, o.dataGiorno, o.ora, o.nroAula, o.attivitaProgetto
";

foreach (mb_dbGetAll($qImp) ?: [] as $r) {
    $att = trim((string)($r['attivitaProgetto'] ?? ''));
    if ($att === '') continue;
    if (isExcludedSpecial($att)) continue;
    if (isUdienzaText($att)) continue;

    $aula  = trim((string)($r['nroAula'] ?? ''));
    $oraIn = normOra($r['ora'] ?? '');
    $idAss = (int)($r['idAssenza'] ?? 0);
    $classi = splitCsvUnique($r['classi'] ?? '');

    $oraFine = '';
    if ($idAss > 0 && isset($assById[$idAss])) {
        $oraInReale = getOraInRow($assById[$idAss]);
        $oraFineReale = getOraFineRow($assById[$idAss]);

        // Gli eventi legati ad assenza hanno in oralezione una o piu righe di slot.
        // Per la card evento va usato l'orario reale dell'assenza, non l'inizio dello slot.
        if ($oraInReale !== '') {
            $oraIn = $oraInReale;
        }
        if ($oraFineReale !== '') {
            $oraFine = $oraFineReale;
        }
    }

    if ($oraFine === '' && $oraIn !== '') {
        $k = $oraIn . '|' . up($att);
        if (isset($assIdxOraAtt[$k])) {
            $oraFine = normOra($assIdxOraAtt[$k]['oraFine'] ?? '');
        }
    }

    if ($idAss > 0 && isset($itemsByAssenzaImp[$idAss])) {
        $i = $itemsByAssenzaImp[$idAss];

        if ($aula !== '' && trim((string)($items[$i]['aula'] ?? '')) === '') {
            $items[$i]['aula']  = $aula;
            $items[$i]['rooms'] = [$aula];
        }

        if (!empty($classi) && empty($items[$i]['classi'])) {
            $items[$i]['classi'] = $classi;
        }

        if (trim((string)($items[$i]['ora'] ?? '')) === '' && $oraIn !== '') {
            $items[$i]['ora'] = $oraIn;
        }
        if (trim((string)($items[$i]['oraFine'] ?? '')) === '' && $oraFine !== '') {
            $items[$i]['oraFine'] = $oraFine;
        }

        continue;
    }

    $who = trim((string)($r['docenti_nomi'] ?? ''));

    if ($who === '' && $idAss > 0 && isset($assById[$idAss])) {
        $docField = trim((string)($assById[$idAss]['docenti'] ?? ''));
        $whoArr = splitDocentiVisual($docField);
        $who = implode("\n", $whoArr);
    }

    if ($who === '' && $oraIn !== '') {
        $k = $oraIn . '|' . up($att);
        if (isset($assIdxOraAtt[$k])) {
            $docField = trim((string)($assIdxOraAtt[$k]['docenti'] ?? ''));
            if ($docField !== '') {
                $whoArr = splitDocentiVisual($docField);
                $who = implode("\n", $whoArr);
            }
        }
    }

    if ($idAss > 0 && isset($itemsByOralezioneAssenza[$idAss])) {
        $i = $itemsByOralezioneAssenza[$idAss];

        if ($aula !== '') {
            $items[$i]['rooms'] = mergeUniqueStrings($items[$i]['rooms'] ?? [], [$aula]);
            if (trim((string)($items[$i]['aula'] ?? '')) === '') {
                $items[$i]['aula'] = $aula;
            }
        }

        if (!empty($classi)) {
            $items[$i]['classi'] = mergeUniqueStrings($items[$i]['classi'] ?? [], $classi);
        }

        if (trim((string)($items[$i]['who'] ?? '')) === '' && trim($who) !== '') {
            $items[$i]['who'] = upNamesLines($who);
        }

        if (trim((string)($items[$i]['ora'] ?? '')) === '' && $oraIn !== '') {
            $items[$i]['ora'] = $oraIn;
        }
        if (trim((string)($items[$i]['oraFine'] ?? '')) === '' && $oraFine !== '') {
            $items[$i]['oraFine'] = $oraFine;
        }

        continue;
    }

    $idx = count($items);
    $items[] = [
        'source'    => 'oralezione',
        'type'      => 'imp',
        'title'     => $att,
        'badge'     => 'Impegno in istituto',
        'who'       => upNamesLines($who),
        'classi'    => $classi,
        'rooms'     => $aula ? [$aula] : [],
        'aula'      => $aula,
        'ora'       => $oraIn,
        'oraFine'   => $oraFine,
        'idAssenza' => $idAss ?: null,
    ];

    if ($idAss > 0) {
        $itemsByOralezioneAssenza[$idAss] = $idx;
    }
}

echo json_encode([
    'ok'    => true,
    'date'  => $date,
    'items' => $items
], JSON_UNESCAPED_UNICODE);
