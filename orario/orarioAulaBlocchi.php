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

/* -------------------- COSTANTI -------------------- */
$ORARI = ["07:50", "08:40", "09:30", "10:30", "11:20", "12:10", "13:00", "13:50", "14:40", "15:30", "16:20", "17:10", "18:00", "18:50", "19:40", "20:30", "21:30", "22:20"];

function getOrarioVisibilityLevel()
{
  global $__utente_ruolo;

  $ruoloUp = strtoupper(trim((string)$__utente_ruolo));

  if (in_array($ruoloUp, ['ADMIN', 'SEGRETERIA-DIDATTICA', 'SEGRETERIA-ATA', 'PORTINERIA'], true)) {
    return 'FULL';
  }

  if (in_array($ruoloUp, ['DOCENTE', 'PERSONALE-ATA'], true)) {
    return 'STAFF';
  }

  if (in_array($ruoloUp, ['STUDENTE', 'GENITORE'], true)) {
    return 'PUBLIC';
  }

  return 'PUBLIC';
}

function applyVisibilityToTeacherAbsEvent($ev, $visibilityLevel)
{
  if (!is_array($ev)) return null;

  $type = strtolower(trim((string)($ev['type'] ?? '')));
  $origin = strtolower(trim((string)($ev['origin'] ?? '')));

  if ($origin !== 'docente') return $ev;

  if ($visibilityLevel === 'FULL') {
    return $ev;
  }

  if ($visibilityLevel === 'STAFF') {
    if (in_array($type, ['pb', 'perm', 'uscc', 'uscf', 'viag'], true)) {
      $ev['title'] = 'Assente';
      $ev['badge'] = 'Assente';
      return $ev;
    }
    return $ev;
  }

  if ($visibilityLevel === 'PUBLIC') {
    if (in_array($type, ['pb', 'perm', 'uscc', 'uscf', 'viag'], true)) {
      return null;
    }
    return $ev;
  }

  return $ev;
}

/* -------------------- HELPERS -------------------- */
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
  if ($o === null) return '';
  $t = (string)$o;
  $t = trim($t);

  // caso HEX puro (es. 31333A3530 = "13:50")
  if ($t !== '' && preg_match('/^[0-9A-Fa-f]+$/', $t) && (strlen($t) % 2 === 0) && strlen($t) >= 8) {
    $decoded = @hex2bin($t);
    if ($decoded !== false) $t = trim((string)$decoded);
  }

  // ripulisce byte null / sporcizia
  $t = str_replace("\0", '', $t);
  $t = trim($t);
  if ($t === '') return '';

  // HH:MM o HH:MM:SS
  if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $t)) {
    $hhmm = substr($t, 0, 5);
    if (preg_match('/^(\d):/', $hhmm)) $hhmm = '0' . $hhmm; // 8:40 -> 08:40
    return $hhmm;
  }

  // estrae HH:MM da stringhe più lunghe
  if (preg_match('/(\d{1,2}:\d{2})/', $t, $m)) {
    $hhmm = $m[1];
    if (preg_match('/^(\d):/', $hhmm)) $hhmm = '0' . $hhmm;
    return $hhmm;
  }

  return '';
}
function mondayOf($iso)
{
  $dt = new DateTime($iso);
  $n = (int)$dt->format('N');
  $dt->modify('-' . ($n - 1) . ' days');
  return $dt->format('Y-m-d');
}
function addDaysIso($iso, $days)
{
  $dt = new DateTime($iso);
  $dt->modify(($days >= 0 ? '+' : '') . $days . ' days');
  return $dt->format('Y-m-d');
}
function splitCsvUnique($csv)
{
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

function getDocentiNomiFromCsv($csv)
{
  global $__conMBApp;

  $usernames = splitCsvUnique($csv);
  if (empty($usernames)) return [];

  $in = implode(",", array_map(function ($u) {
    global $__conMBApp;
    return "'" . mysqli_real_escape_string($__conMBApp, $u) . "'";
  }, $usernames));

  $q = "
    SELECT username, CONCAT(cognome,' ',nome) AS nome
    FROM utente
    WHERE username IN ($in)
  ";

  $rows = mb_dbGetAll($q) ?: [];

  $map = [];
  foreach ($rows as $r) {
    $map[$r['username']] = $r['nome'];
  }

  $out = [];
  foreach ($usernames as $u) {
    $out[] = $map[$u] ?? $u;
  }

  return $out;
}

function getDocentiNomiByAssenzaId($idAssenza)
{
  global $__conMBApp;
  static $cache = [];

  $id = (int)$idAssenza;
  if ($id <= 0) return [];

  if (isset($cache[$id])) return $cache[$id];

  $q = "
    SELECT DISTINCT u.cognome, u.nome
    FROM utilizza ut
    JOIN utente u ON u.username = ut.username
    WHERE ut.IDassenza = $id
      AND ut.username IS NOT NULL
      AND ut.username <> ''
    ORDER BY u.cognome, u.nome
  ";

  $rows = mb_dbGetAll($q) ?: [];
  $out = [];
  foreach ($rows as $r) {
    $nome = trim(($r['cognome'] ?? '') . ' ' . ($r['nome'] ?? ''));
    if ($nome !== '') $out[] = $nome;
  }

  $cache[$id] = $out;
  return $out;
}

function getUsernamesByAssenzaId($idAssenza)
{
  global $__conMBApp;
  $id = (int)$idAssenza;
  if ($id <= 0) return [];
  $q = "
    SELECT DISTINCT username
    FROM utilizza
    WHERE IDassenza = $id
      AND username IS NOT NULL
      AND username <> ''
  ";
  $rows = mb_dbGetAll($q) ?: [];
  $out = [];
  foreach ($rows as $r) {
    $u = trim((string)($r['username'] ?? ''));
    if ($u !== '') $out[] = $u;
  }
  return $out;
}

/* riconoscimento */
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

function pickOraInizio($a)
{
  $o = trim(h($a['oraInizioReale'] ?? ''));
  if ($o !== '') return normOra($o);
  return normOra(trim(h($a['oraInizio'] ?? '')));
}
function pickOraFine($a)
{
  $o = trim(h($a['oraFineReale'] ?? ''));
  if ($o !== '') return normOra($o);
  return normOra(trim(h($a['oraFine'] ?? '')));
}

function espandiAssenzaInSlot($a, $ORARI)
{
  $dataFrom = substr(h($a['dataInizio'] ?? ''), 0, 10);
  $dataTo   = substr(h($a['dataFine'] ?? ''), 0, 10);
  if ($dataFrom === '' || $dataTo === '') return [];

  $mot = strtoupper(trim(h($a['motivo'] ?? '')));
  $det = strtoupper(trim(h($a['dettagli'] ?? '')));

  $isV = isViaggio($mot, $det);
  $isP = isPermessoGiorno($mot, $det);

  // viaggi/permesso giorno = tutto giorno
  $forceAllDay = $isV || $isP;

  $oraDa = pickOraInizio($a);
  $oraA  = pickOraFine($a);
  $hasTime = (!$forceAllDay && $oraDa !== '' && $oraA !== '');

  $d1 = new DateTime($dataFrom);
  $d2 = new DateTime($dataTo);
  if ($d2 < $d1) {
    $tmp = $d1;
    $d1 = $d2;
    $d2 = $tmp;
  }

  $out = [];
  for ($d = clone $d1; $d <= $d2; $d->modify('+1 day')) {
    $ymd = $d->format('Y-m-d');
    if (!$hasTime) {
      $out[$ymd] = $ORARI;
      continue;
    }

    $slots = [];
    foreach ($ORARI as $o) if ($o >= $oraDa && $o < $oraA) $slots[] = $o;
    if (empty($slots) && in_array($oraDa, $ORARI, true)) $slots[] = $oraDa;
    $out[$ymd] = $slots;
  }
  return $out;
}

function pushEv(&$grid, $ymd, $ora, $ev)
{
  $ora = normOra($ora);
  if ($ora === '') return;
  $k = $ymd . '|' . $ora;
  if (!isset($grid[$k])) $grid[$k] = [];
  $grid[$k][] = $ev;
}

function addBlocked(&$blockedMap, $slotKey, $classi)
{
  if (!isset($blockedMap[$slotKey])) $blockedMap[$slotKey] = [];
  $have = array_flip($blockedMap[$slotKey]);
  foreach ($classi as $c) {
    $c = trim((string)$c);
    if ($c === '') continue;
    if (!isset($have[$c])) {
      $blockedMap[$slotKey][] = $c;
      $have[$c] = true;
    }
  }
}

/* evento assenza (usc/viag/perm/pb) */
function makeEvUscViag($a)
{
  $mot = strtoupper(trim(h($a['motivo'] ?? '')));
  $detRaw = trim(h($a['dettagli'] ?? ''));
  $det = strtoupper(trim($detRaw));

  $meta = ($detRaw !== '') ? $detRaw : '';

  $idAss = (int)($a['idAssenza'] ?? 0);
  $docArr = ($idAss > 0) ? getDocentiNomiByAssenzaId($idAss) : [];
  $docNomi = !empty($docArr) ? implode("\n", $docArr) : "";

  if (isViaggio($mot, $det)) {
    return [
      'type'  => 'viag',
      'class' => 'ev ev-viag',
      'title' => 'Viaggio di istruzione' . ($meta !== '' ? ' · ' . $meta : ''),
      'who'   => $docNomi,
      'classi' => [],
      'badge' => 'Viaggio di istruzione'
    ];
  }

  if (isUscita($mot, $det)) {
    $fuori = isUscitaFuori($mot, $det);
    $base = $fuori ? 'Uscita fuori comune' : 'Uscita nel comune';
    $type = $fuori ? 'uscF' : 'uscC';

    return [
      'type'  => $type,
      'class' => 'ev ev-' . $type,
      'title' => $base . ($meta !== '' ? ' · ' . $meta : ''),
      'who'   => $docNomi,
      'classi' => [],
      'badge' => $base
    ];
  }

  if (isPermessoBreve($mot, $det)) {
    return [
      'type'  => 'pb',
      'class' => 'ev ev-pb',
      'title' => 'Permesso breve' . ($meta !== '' ? ' · ' . $meta : ''),
      'who'   => $docNomi,
      'classi' => [],
      'badge' => 'Permesso breve'
    ];
  }

  if (isPermessoGiorno($mot, $det)) {
    return [
      'type'  => 'perm',
      'class' => 'ev ev-perm',
      'title' => 'Permesso (giorno)' . ($meta !== '' ? ' · ' . $meta : ''),
      'who'   => $docNomi,
      'classi' => [],
      'badge' => 'Permesso (giorno)'
    ];
  }

  return null;
}

/* -------------------- INPUT -------------------- */
$scope  = isset($_GET['scope']) ? strtoupper(trim((string)$_GET['scope'])) : 'AULA';
$period = isset($_GET['period']) ? strtoupper(trim((string)$_GET['period'])) : 'SETTIMANA';
$date   = isset($_GET['date']) ? trim((string)$_GET['date']) : date('Y-m-d');
$target = isset($_GET['target']) ? trim((string)$_GET['target']) : '';

if ($scope !== 'AULA') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Scope non valido'], JSON_UNESCAPED_UNICODE);
  exit;
}
if (!isIsoDate($date)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Data non valida'], JSON_UNESCAPED_UNICODE);
  exit;
}
if ($target === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Target mancante'], JSON_UNESCAPED_UNICODE);
  exit;
}

if (!in_array($period, ['GIORNO', 'SETTIMANA'], true)) $period = 'SETTIMANA';

if ($period === 'GIORNO') {
  $from = $date;
  $to = $date;
} else {
  $mon  = mondayOf($date);
  $from = $mon;
  $to   = addDaysIso($mon, 4);
}

$fromEsc = mysqli_real_escape_string($__conMBApp, $from);
$toEsc   = mysqli_real_escape_string($__conMBApp, $to);
$nro = mysqli_real_escape_string($__conMBApp, $target);
$VISIBILITY_LEVEL = getOrarioVisibilityLevel();

/* ✅ INIT OUTPUT SUBITO (prima di usarli) */
$gridAssenze = [];
$blockedMap = [];

/* 1) mappa slot -> classi presenti in quell'aula (da oralezione) */
$aulaSlotClasses = []; // key slot => ['4MMA'=>true,...]
$qSlots = "
  SELECT o.dataGiorno, o.ora,
         GROUP_CONCAT(DISTINCT oc.classe ORDER BY oc.classe SEPARATOR ', ') AS classi
  FROM oralezione o
  LEFT JOIN occupa oc ON oc.idCalendario = o.idCalendario
  WHERE o.nroAula = '$nro'
    AND o.dataGiorno BETWEEN '$fromEsc' AND '$toEsc'
    AND (o.stato IS NULL OR o.stato <> 'CANCELLATO')
  GROUP BY o.dataGiorno, o.ora
";

foreach (mb_dbGetAll($qSlots) ?: [] as $r) {
  $k = substr((string)$r['dataGiorno'], 0, 10) . '|' . normOra($r['ora'] ?? '');
  if (!isset($aulaSlotClasses[$k])) $aulaSlotClasses[$k] = [];
  foreach (splitCsvUnique($r['classi'] ?? '') as $c) $aulaSlotClasses[$k][$c] = true;
}

/* 2) carico assenze collegate alle classi che passano in quell'aula nel range */
$qA = "
  SELECT DISTINCT a.*
  FROM oralezione o
  JOIN occupa ocA       ON ocA.idCalendario = o.idCalendario
  JOIN occupa ocAbs     ON ocAbs.classe = ocA.classe AND ocAbs.IDassenza IS NOT NULL
  JOIN assenze a        ON a.idAssenza = ocAbs.IDassenza
  WHERE o.nroAula = '$nro'
    AND o.dataGiorno BETWEEN '$fromEsc' AND '$toEsc'
    AND (o.stato IS NULL OR o.stato <> 'CANCELLATO')
    AND DATE(a.dataFine)  >= '$fromEsc'
    AND DATE(a.dataInizio) <= '$toEsc'
";

$aulaSlotTeachers = []; // key slot => ['rossim'=>true,...] username (o nomi)
$qTeachSlots = "
  SELECT o.dataGiorno, o.ora,
         GROUP_CONCAT(DISTINCT ut.username SEPARATOR ',') AS teachers
  FROM oralezione o
  JOIN utilizza ut ON ut.idCalendario = o.idCalendario
  WHERE o.nroAula = '$nro'
    AND o.dataGiorno BETWEEN '$fromEsc' AND '$toEsc'
    AND (o.stato IS NULL OR o.stato <> 'CANCELLATO')
    AND ut.username IS NOT NULL AND ut.username <> ''
  GROUP BY o.dataGiorno, o.ora
";
foreach (mb_dbGetAll($qTeachSlots) ?: [] as $r) {
  $k = substr((string)$r['dataGiorno'], 0, 10) . '|' . normOra($r['ora'] ?? '');
  if (!isset($aulaSlotTeachers[$k])) $aulaSlotTeachers[$k] = [];
  foreach (splitCsvUnique($r['teachers'] ?? '') as $u) $aulaSlotTeachers[$k][$u] = true;
}

$qDocInAula = "
  SELECT DISTINCT ut.username
  FROM oralezione o
  JOIN utilizza ut ON ut.idCalendario = o.idCalendario
  WHERE o.nroAula = '$nro'
    AND o.dataGiorno BETWEEN '$fromEsc' AND '$toEsc'
    AND (o.stato IS NULL OR o.stato <> 'CANCELLATO')
    AND ut.username IS NOT NULL AND ut.username <> ''
";
$docU = [];
foreach (mb_dbGetAll($qDocInAula) ?: [] as $r) {
  $u = trim((string)$r['username']);
  if ($u !== '') $docU[] = $u;
}

/* ===========================
   2B) ASSENZE DOCENTI (perm/pb/usc/viag) legate ai docenti che stanno in quell'aula
   Mostra SOLO se il docente assente è presente nello slot (match su username)
   =========================== */
if (!empty($docU)) {
  $inUser = "'" . implode("','", array_map(function ($x) use ($__conMBApp) {
    return mysqli_real_escape_string($__conMBApp, $x);
  }, $docU)) . "'";

  $qAssDoc = "
    SELECT a.*
    FROM assenze a
    WHERE a.idAssenza IN (
      SELECT DISTINCT ut.IDassenza
      FROM utilizza ut
      WHERE ut.username IN ($inUser)
        AND ut.IDassenza IS NOT NULL
    )
      AND DATE(a.dataFine)  >= '$fromEsc'
      AND DATE(a.dataInizio) <= '$toEsc'
  ";

  foreach (mb_dbGetAll($qAssDoc) ?: [] as $a) {

    $ev = makeEvUscViag($a);
    if ($ev === null) continue;

    $idAss = (int)($a['idAssenza'] ?? 0);
    if ($idAss <= 0) continue;

    $assUsernames = getUsernamesByAssenzaId($idAss);
    if (empty($assUsernames)) continue;

    // marca come assenza docente per applicare la visibilità
    $ev['origin'] = 'docente';

    // applica la privacy:
    // FULL  -> dettaglio reale
    // STAFF -> solo "Assente"
    // PUBLIC -> non mostrare
    $ev = applyVisibilityToTeacherAbsEvent($ev, $VISIBILITY_LEVEL);
    if ($ev === null) continue;

    $slots = espandiAssenzaInSlot($a, $ORARI);

    foreach ($slots as $ymd => $ores) {
      foreach ($ores as $ora) {
        $oraN = normOra($ora);
        if ($oraN === '') continue;

        $slotKey = $ymd . '|' . $oraN;

        // deve esserci una lezione in quell'aula in quello slot con docenti agganciati
        if (!isset($aulaSlotTeachers[$slotKey]) || empty($aulaSlotTeachers[$slotKey])) continue;

        // match tra docenti assenti e docenti presenti in aula nello slot
        $presentTeachers = $aulaSlotTeachers[$slotKey];
        $match = false;
        foreach ($assUsernames as $uAss) {
          if (isset($presentTeachers[$uAss])) {
            $match = true;
            break;
          }
        }
        if (!$match) continue;

        // ev['who'] è già NOMI (non usernames) => ok per UI
        pushEv($gridAssenze, $ymd, $oraN, $ev);
      }
    }
  }
}

/* ===========================
   2A) ASSENZE collegate alle CLASSI in aula (occupa)
   (qui lasciamo la tua logica, ma estendiamo ai permessi)
   =========================== */
foreach (mb_dbGetAll($qA) ?: [] as $a) {
  $mot = strtoupper(trim(h($a['motivo'] ?? '')));
  $det = strtoupper(trim(h($a['dettagli'] ?? '')));

  // ✅ anche permessi
  if (!(isViaggio($mot, $det) || isUscita($mot, $det) || isPermessoGiorno($mot, $det) || isPermessoBreve($mot, $det))) continue;

  $ev = makeEvUscViag($a);
  if ($ev === null) continue;

  // classi coinvolte dall'assenza
  $idAss = (int)($a['idAssenza'] ?? 0);
  if ($idAss <= 0) continue;

  $qC = "SELECT DISTINCT classe FROM occupa WHERE IDassenza = $idAss AND classe<>'' ORDER BY classe";
  $cls = [];
  foreach (mb_dbGetAll($qC) ?: [] as $rr) {
    $c = trim((string)$rr['classe']);
    if ($c !== '') $cls[] = $c;
  }
  if (empty($cls)) continue;

  $slots = espandiAssenzaInSlot($a, $ORARI);

  foreach ($slots as $ymd => $ores) {
    foreach ($ores as $ora) {
      $oraN = normOra($ora);
      if ($oraN === '') continue;
      $slotKey = $ymd . '|' . $oraN;

      // deve esserci almeno una classe in aula in quello slot
      if (!isset($aulaSlotClasses[$slotKey]) || empty($aulaSlotClasses[$slotKey])) continue;

      // intersezione: solo classi che erano davvero in quell'aula in quello slot
      $inter = [];
      foreach ($cls as $c) if (isset($aulaSlotClasses[$slotKey][$c])) $inter[] = $c;
      if (empty($inter)) continue;

      $ev2 = $ev;

      // classi reali dell’assenza (non solo intersezione)
      $ev2['classi'] = $cls;

      // docenti (nomi) da campo assenze.docenti se vuoi mantenerlo:
      // (lasciata la tua logica)
      $docArr = getDocentiNomiFromCsv($a['docenti'] ?? '');
      if (!empty($docArr)) {
        $ev2['who'] = implode("\n", $docArr);
      }

      pushEv($gridAssenze, $ymd, $oraN, $ev2);
      addBlocked($blockedMap, $slotKey, $inter);
    }
  }
}

echo json_encode([
  "ok" => true,
  "scope" => "AULA",
  "target" => $target,
  "period" => $period,
  "from" => $from,
  "to" => $to,
  "gridAssenze" => $gridAssenze,
  "blockedMap" => $blockedMap
], JSON_UNESCAPED_UNICODE);
