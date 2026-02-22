<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/connectMBApp.php';

ruoloRichiesto('personale-ata', 'segreteria-ata', 'docente');
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
  $o = trim((string)$o);
  if ($o === '') return '';
  return substr($o, 0, 5);
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

function splitUsernamesCsv($csv)
{
  return splitCsvUnique($csv);
}

function getDocentiNomiByUsernames($usernames)
{
  global $__conMBApp;
  static $cache = [];

  $need = [];
  foreach ($usernames as $u) if (!isset($cache[$u])) $need[] = $u;

  if (!empty($need)) {
    $in = implode(",", array_map(function ($u) {
      global $__conMBApp;
      return "'" . mysqli_real_escape_string($__conMBApp, $u) . "'";
    }, $need));

    $q = "SELECT username, cognome, nome FROM utente WHERE username IN ($in)";
    $rows = mb_dbGetAll($q) ?: [];

    foreach ($rows as $r) {
      $un = (string)$r['username'];
      $cache[$un] = trim($r['cognome'] . ' ' . $r['nome']);
    }
    foreach ($need as $u) if (!isset($cache[$u])) $cache[$u] = $u;
  }

  $names = [];
  foreach ($usernames as $u) $names[] = $cache[$u] ?? $u;
  return $names;
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

/* =========================
   ASSENZE: riconosci SOLO queste
   ========================= */
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

function getUsernamesByAssenzaId($idAssenza)
{
  global $__conMBApp;
  $id = (int)$idAssenza;
  if ($id <= 0) return [];
  $q = "SELECT DISTINCT username
        FROM utilizza
        WHERE IDassenza = $id AND username IS NOT NULL AND username <> ''";
  $rows = mb_dbGetAll($q) ?: [];
  $out = [];
  foreach ($rows as $r) {
    $u = trim((string)($r['username'] ?? ''));
    if ($u !== '') $out[] = $u;
  }
  return $out;
}

/* classi collegate all’assenza (per vista DOCENTE) */
function getClassiByAssenzaId($idAssenza)
{
  global $__conMBApp;
  $id = (int)$idAssenza;
  if ($id <= 0) return [];
  $q = "SELECT DISTINCT classe FROM occupa WHERE IDassenza = $id AND classe <> '' ORDER BY classe";
  $rows = mb_dbGetAll($q) ?: [];
  $out = [];
  foreach ($rows as $r) {
    $c = trim((string)($r['classe'] ?? ''));
    if ($c !== '') $out[] = $c;
  }
  return $out;
}

/* docenti collegati all’assenza (username -> nomi) */
function getDocentiNomiByAssenzaId($idAssenza)
{
  global $__conMBApp;
  $id = (int)$idAssenza;
  if ($id <= 0) return [];

  $q = "SELECT DISTINCT username
        FROM utilizza
        WHERE IDassenza = $id
          AND username IS NOT NULL
          AND username <> ''
        ORDER BY username";

  $rows = mb_dbGetAll($q) ?: [];
  $usernames = [];
  foreach ($rows as $r) {
    $u = trim((string)($r['username'] ?? ''));
    if ($u !== '') $usernames[] = $u;
  }

  if (empty($usernames)) return [];
  return getDocentiNomiByUsernames($usernames);
}

/* ✅ Classifica assenza: SOLO pb/perm/uscita/viaggio, altrimenti NULL */
function classifyAssenza($a, $scope)
{
  $mot = strtoupper(trim(h($a['motivo'] ?? '')));
  $detRaw = trim(h($a['dettagli'] ?? ''));
  $det = strtoupper(trim($detRaw));

  $idAss = (int)($a['idAssenza'] ?? 0);

  $classi = [];
  if ($scope === 'DOCENTE' || $scope === 'CLASSE') {
    $classi = getClassiByAssenzaId($idAss);
  }

  $docNomiArr = [];
  if ($scope === 'DOCENTE' || $scope === 'CLASSE') {
    $docNomiArr = getDocentiNomiByAssenzaId($idAss);
  }
  $docNomi = !empty($docNomiArr) ? implode(", ", $docNomiArr) : "";

  $meta = ($detRaw !== '') ? $detRaw : '';

  if (isViaggio($mot, $det)) {
    $title = 'Viaggio di istruzione' . ($meta !== '' ? ' · ' . $meta : '');
    return [
      'type'  => 'viag',
      'class' => 'ev ev-viag',
      'title' => $title,
      'who'   => $docNomi,
      'classi' => $classi,
      'badge' => 'Viaggio di istruzione'
    ];
  }

  if (isUscita($mot, $det)) {
    $fuori = isUscitaFuori($mot, $det);
    $baseTitle = $fuori ? 'Uscita fuori comune' : 'Uscita nel comune';
    $type = $fuori ? 'uscF' : 'uscC';
    $title = $baseTitle . ($meta !== '' ? ' · ' . $meta : '');

    return [
      'type'  => $type,
      'class' => 'ev ev-' . $type,
      'title' => $title,
      'who'   => $docNomi,
      'classi' => $classi,
      'badge' => $baseTitle
    ];
  }

  if (isPermessoBreve($mot, $det)) {
    return [
      'type' => 'pb',
      'class' => 'ev ev-pb',
      'title' => 'Permesso breve',
      'who' => $docNomi,
      'classi' => $classi,
      'badge' => 'Permesso breve'
    ];
  }

  if (isPermessoGiorno($mot, $det)) {
    return [
      'type' => 'perm',
      'class' => 'ev ev-perm',
      'title' => 'Permesso (giorno)',
      'who' => $docNomi,          // ✅
      'classi' => $classi,
      'badge' => 'Permesso (giorno)'
    ];
  }

  return null;
}

/* espansione assenza su slot */
function espandiAssenzaInSlot($a, $ORARI)
{
  $dataFrom = substr(h($a['dataInizio'] ?? ''), 0, 10);
  $dataTo   = substr(h($a['dataFine'] ?? ''), 0, 10);
  if ($dataFrom === '' || $dataTo === '') return [];

  $mot = strtoupper(trim(h($a['motivo'] ?? '')));
  $det = strtoupper(trim(h($a['dettagli'] ?? '')));

  $isV = isViaggio($mot, $det);
  $isP = isPermessoGiorno($mot, $det);

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
    foreach ($ORARI as $o) {
      if ($o >= $oraDa && $o < $oraA) $slots[] = $o;
    }
    if (empty($slots) && in_array($oraDa, $ORARI, true)) $slots[] = $oraDa;

    $out[$ymd] = $slots;
  }
  return $out;
}

/* rooms / classi */
function splitRooms($s)
{
  return splitCsvUnique($s);
}
function splitClassi($s)
{
  return splitCsvUnique($s);
}

/* costruzione evento oralezione con rooms + classi */
function classeEventoOralezione($row)
{
  $att   = trim(h($row['attivitaProgetto'] ?? ''));
  $sig   = trim(h($row['siglaMateria'] ?? ''));
  $nome  = trim(h($row['nomeMateria'] ?? ''));
  $doc   = trim(h($row['docenti_nomi'] ?? ''));
  $auleS = trim(h($row['aule'] ?? ''));
  $classiS = trim(h($row['classi'] ?? ''));

  $rooms  = splitRooms($auleS);
  $classi = splitClassi($classiS);

  $SIG_UP  = strtoupper($sig);
  $NOME_UP = strtoupper($nome);

    // =======================
  // EVENTI SPECIALI AULA (non curricolare)
  // =======================

  // 1) PRANZO / PAUSA PRANZO STUDENTI
  $isPranzo =
    (strpos($SIG_UP, 'PRANZO') !== false) ||
    (strpos($NOME_UP, 'PAUSA PRANZO') !== false) ||
    (stripos($att, 'PAUSA PRANZO') !== false);

  if ($isPranzo) {
    return [
      'type'  => 'pranzo',
      'class' => 'ev ev-pranzo',
      'title' => 'Pausa pranzo studenti',
      'who'   => '',                 // niente docenti
      'classi' => $classi,
      'badge' => 'Aula pausa pranzo',
      'rooms' => $rooms
    ];
  }

  // 2) AULA STUDIO STUDENTI
  $isAulaStudio =
    (strpos($SIG_UP, 'AULA S') !== false) ||
    (strpos($NOME_UP, 'AULA STUDIO') !== false) ||
    (stripos($att, 'AULA STUDIO') !== false);

  if ($isAulaStudio) {
    return [
      'type'  => 'studio',
      'class' => 'ev ev-studio',
      'title' => 'Aula studio studenti',
      'who'   => '',                 // niente docenti
      'classi' => $classi,
      'badge' => 'Aula studio',
      'rooms' => $rooms
    ];
  }

  if ($att !== '') {
    return [
      'type'  => 'imp',
      'class' => 'ev ev-imp',
      'title' => $att,
      'who'   => $doc,
      'classi' => $classi,
      'badge' => 'Impegno in istituto',
      'rooms' => $rooms
    ];
  }

  $isUdienza = (strpos($SIG_UP, 'UDIENZA') !== false) || (strpos($NOME_UP, 'UDIENZA') !== false);
  if ($isUdienza) {
    return [
      'type'  => 'udi',
      'class' => 'ev ev-udi',
      'title' => ($sig ? ($sig . ($nome ? " · $nome" : "")) : "UDIENZA"),
      'who'   => $doc,
      'classi' => $classi,
      'badge' => 'Udienza',
      'rooms' => $rooms
    ];
  }

  $title = $sig ? ($sig . ($nome ? " · $nome" : "")) : "LEZIONE";
  return [
    'type'  => 'curr',
    'class' => 'ev ev-curr',
    'title' => $title,
    'who'   => $doc,
    'classi' => $classi,
    'badge' => 'Lezione curricolare',
    'rooms' => $rooms
  ];
}

/* -------------------- INPUT -------------------- */
$scope  = isset($_GET['scope']) ? strtoupper(trim((string)$_GET['scope'])) : 'DOCENTE';
$period = isset($_GET['period']) ? strtoupper(trim((string)$_GET['period'])) : 'SETTIMANA';
$date   = isset($_GET['date']) ? trim((string)$_GET['date']) : date('Y-m-d');
$target = isset($_GET['target']) ? trim((string)$_GET['target']) : '';

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

if (!in_array($scope, ['DOCENTE', 'CLASSE', 'AULA'], true)) $scope = 'DOCENTE';
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

/* -------------------- GRID -------------------- */
$grid = [];

function pushEvUnique(&$grid, $ymd, $ora, $ev)
{
  $ora = normOra($ora);
  if ($ora === '') return;

  $k = $ymd . '|' . $ora;
  if (!isset($grid[$k])) $grid[$k] = [];

  $sig = ($ev['type'] ?? '') . '|' .
    ($ev['title'] ?? '') . '|' .
    ($ev['who'] ?? '');

  foreach ($grid[$k] as $existing) {
    $sig2 = ($existing['type'] ?? '') . '|' .
      ($existing['title'] ?? '') . '|' .
      ($existing['who'] ?? '');
    if ($sig === $sig2) return; // già presente
  }

  $grid[$k][] = $ev;
}

function pushEv(&$grid, $ymd, $ora, $ev)
{
  $ora = normOra($ora);
  if ($ora === '') return;
  $k = $ymd . '|' . $ora;
  if (!isset($grid[$k])) $grid[$k] = [];
  $grid[$k][] = $ev;
}

/* -------------------- 1) ORALEZIONE -------------------- */
if ($scope === 'AULA') {
  $nro = mysqli_real_escape_string($__conMBApp, $target);

  $q = "
    SELECT
      o.idCalendario,
      o.dataGiorno, o.ora, o.siglaMateria, o.attivitaProgetto,
      m.nomeMateria,
      GROUP_CONCAT(DISTINCT CONCAT(u.cognome,' ',u.nome) SEPARATOR ', ') AS docenti_nomi,
      GROUP_CONCAT(DISTINCT o.nroAula ORDER BY CAST(o.nroAula AS UNSIGNED), o.nroAula SEPARATOR ', ') AS aule,
      GROUP_CONCAT(DISTINCT oc.classe ORDER BY oc.classe SEPARATOR ', ') AS classi
    FROM oralezione o
    LEFT JOIN materia m    ON m.siglaMateria = o.siglaMateria
    LEFT JOIN utilizza ut  ON ut.idCalendario = o.idCalendario
    LEFT JOIN utente u     ON u.username = ut.username
    LEFT JOIN occupa oc    ON oc.idCalendario = o.idCalendario
    WHERE o.nroAula = '$nro'
      AND o.dataGiorno BETWEEN '$fromEsc' AND '$toEsc'
      AND (o.stato IS NULL OR o.stato <> 'CANCELLATO')
    GROUP BY o.idCalendario, o.dataGiorno, o.ora, o.siglaMateria, o.attivitaProgetto, m.nomeMateria
  ";

  foreach (mb_dbGetAll($q) ?: [] as $r) {
    pushEv($grid, $r['dataGiorno'], $r['ora'], classeEventoOralezione($r));
  }
} elseif ($scope === 'CLASSE') {
  $cl = mysqli_real_escape_string($__conMBApp, $target);

  $q = "
    SELECT
      o.idCalendario,
      o.dataGiorno, o.ora, o.siglaMateria, o.attivitaProgetto,
      m.nomeMateria,
      GROUP_CONCAT(DISTINCT CONCAT(u.cognome,' ',u.nome) SEPARATOR ', ') AS docenti_nomi,
      GROUP_CONCAT(DISTINCT o.nroAula ORDER BY CAST(o.nroAula AS UNSIGNED), o.nroAula SEPARATOR ', ') AS aule,
      GROUP_CONCAT(DISTINCT oc.classe ORDER BY oc.classe SEPARATOR ', ') AS classi
    FROM oralezione o
    JOIN occupa oc        ON oc.idCalendario = o.idCalendario
    LEFT JOIN materia m   ON m.siglaMateria = o.siglaMateria
    LEFT JOIN utilizza ut ON ut.idCalendario = o.idCalendario
    LEFT JOIN utente u    ON u.username = ut.username
    WHERE oc.classe = '$cl'
      AND o.dataGiorno BETWEEN '$fromEsc' AND '$toEsc'
      AND (o.stato IS NULL OR o.stato <> 'CANCELLATO')
    GROUP BY o.idCalendario, o.dataGiorno, o.ora, o.siglaMateria, o.attivitaProgetto, m.nomeMateria
  ";

  foreach (mb_dbGetAll($q) ?: [] as $r) {
    pushEv($grid, $r['dataGiorno'], $r['ora'], classeEventoOralezione($r));
  }
} else { // DOCENTE
  $u0 = mysqli_real_escape_string($__conMBApp, $target);

  $q = "
  SELECT
    o.idCalendario,
    o.dataGiorno, o.ora, o.siglaMateria, o.attivitaProgetto,
    m.nomeMateria,

    /* ✅ tutti i docenti della lezione */
    GROUP_CONCAT(DISTINCT CONCAT(u.cognome,' ',u.nome) SEPARATOR ', ') AS docenti_nomi,

    GROUP_CONCAT(DISTINCT o.nroAula ORDER BY CAST(o.nroAula AS UNSIGNED), o.nroAula SEPARATOR ', ') AS aule,
    GROUP_CONCAT(DISTINCT oc.classe ORDER BY oc.classe SEPARATOR ', ') AS classi

  FROM oralezione o

  /* filtro: il docente richiesto deve essere associato alla lezione */
  JOIN utilizza ut2
    ON ut2.idCalendario = o.idCalendario
   AND ut2.username = '$u0'

  /* ✅ recupero: tutti i docenti associati (non solo ut2) */
  LEFT JOIN utilizza utAll
    ON utAll.idCalendario = o.idCalendario
   AND utAll.username IS NOT NULL

  LEFT JOIN utente u
    ON u.username = utAll.username

  LEFT JOIN materia m
    ON m.siglaMateria = o.siglaMateria

  LEFT JOIN occupa oc
    ON oc.idCalendario = o.idCalendario

  WHERE o.dataGiorno BETWEEN '$fromEsc' AND '$toEsc'
    AND (o.stato IS NULL OR o.stato <> 'CANCELLATO')

  GROUP BY
    o.idCalendario, o.dataGiorno, o.ora, o.siglaMateria, o.attivitaProgetto, m.nomeMateria
";

  foreach (mb_dbGetAll($q) ?: [] as $r) {
    pushEv($grid, $r['dataGiorno'], $r['ora'], classeEventoOralezione($r));
  }
}

/* -------------------- 2) ASSENZE collegate (SOLO DOCENTE/CLASSE) -------------------- */
if ($scope === 'DOCENTE') {

  $u = mysqli_real_escape_string($__conMBApp, $target);

  /* ===============================
     2A) Assenze del docente selezionato (già presenti)
     =============================== */
  $qA = "
    SELECT a.*
    FROM assenze a
    WHERE a.idAssenza IN (
      SELECT DISTINCT ut.IDassenza
      FROM utilizza ut
      WHERE ut.username = '$u'
        AND ut.IDassenza IS NOT NULL
    )
      AND DATE(a.dataFine)  >= '$fromEsc'
      AND DATE(a.dataInizio) <= '$toEsc'
  ";

  foreach (mb_dbGetAll($qA) ?: [] as $a) {
    $ev = classifyAssenza($a, $scope);
    if ($ev === null) continue;
    $slots = espandiAssenzaInSlot($a, $ORARI);
    foreach ($slots as $ymd => $ores) {
      foreach ($ores as $ora) pushEvUnique($grid, $ymd, $ora, $ev);
    }
  }

  /* ===============================
     2B) 🔥 Assenze dei COLLEGHI delle stesse lezioni
     =============================== */

  // 1️⃣ recupero idCalendario dove insegna il docente selezionato
  $qCal = "
    SELECT DISTINCT o.idCalendario
    FROM oralezione o
    JOIN utilizza ut2
      ON ut2.idCalendario = o.idCalendario
     AND ut2.username = '$u'
    WHERE o.dataGiorno BETWEEN '$fromEsc' AND '$toEsc'
      AND (o.stato IS NULL OR o.stato <> 'CANCELLATO')
  ";

  $calIds = [];
  foreach (mb_dbGetAll($qCal) ?: [] as $r) {
    $calIds[] = (int)$r['idCalendario'];
  }

  if (!empty($calIds)) {

    $inCal = implode(",", $calIds);

    // 2️⃣ recupero username colleghi (escludendo il docente target)
    $qColleghi = "
      SELECT DISTINCT ut.username
      FROM utilizza ut
      WHERE ut.idCalendario IN ($inCal)
        AND ut.username <> '$u'
        AND ut.username IS NOT NULL
    ";

    $colleghi = [];
    foreach (mb_dbGetAll($qColleghi) ?: [] as $r) {
      $colleghi[] = mysqli_real_escape_string($__conMBApp, $r['username']);
    }

    if (!empty($colleghi)) {

      $inUser = "'" . implode("','", $colleghi) . "'";

      // 3️⃣ recupero assenze dei colleghi nel periodo
      $qAssCol = "
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

      foreach (mb_dbGetAll($qAssCol) ?: [] as $a) {

        $ev = classifyAssenza($a, $scope);
        if ($ev === null) continue;

        $slots = espandiAssenzaInSlot($a, $ORARI);

        foreach ($slots as $ymd => $ores) {
          foreach ($ores as $ora) {
            pushEvUnique($grid, $ymd, $ora, $ev);
          }
        }
      }
    }
  }
} elseif ($scope === 'CLASSE') {


  $cl = mysqli_real_escape_string($__conMBApp, $target);

  // slotKey => ['username1'=>true, ...]
  $slotTeachers = [];

  $qSlotTeach = "
  SELECT o.dataGiorno, o.ora, ut.username
  FROM oralezione o
  JOIN occupa oc        ON oc.idCalendario = o.idCalendario AND oc.classe = '$cl'
  JOIN utilizza ut      ON ut.idCalendario = o.idCalendario
  WHERE o.dataGiorno BETWEEN '$fromEsc' AND '$toEsc'
    AND (o.stato IS NULL OR o.stato <> 'CANCELLATO')
    AND ut.username IS NOT NULL AND ut.username <> ''
";

  foreach (mb_dbGetAll($qSlotTeach) ?: [] as $r) {
    $k = substr((string)$r['dataGiorno'], 0, 10) . '|' . normOra($r['ora'] ?? '');
    $u = trim((string)$r['username'] ?? '');
    if ($k !== '' && $u !== '') {
      if (!isset($slotTeachers[$k])) $slotTeachers[$k] = [];
      $slotTeachers[$k][$u] = true;
    }
  }
  $qA = "
    SELECT a.*
    FROM assenze a
    WHERE a.idAssenza IN (
      SELECT DISTINCT oc.IDassenza
      FROM occupa oc
      WHERE oc.classe = '$cl'
        AND oc.IDassenza IS NOT NULL
    )
      AND DATE(a.dataFine)  >= '$fromEsc'
      AND DATE(a.dataInizio) <= '$toEsc'
  ";

  foreach (mb_dbGetAll($qA) ?: [] as $a) {
    $ev = classifyAssenza($a, $scope);
    if (!empty($ev['classi']) && !in_array($cl, $ev['classi'], true)) {
      continue;
    }
    if ($ev === null) continue;
    $slots = espandiAssenzaInSlot($a, $ORARI);
    foreach ($slots as $ymd => $ores) {
      foreach ($ores as $ora) pushEvUnique($grid, $ymd, $ora, $ev);
    }
  }

  /* ===============================
     2B) 🔥 Assenze DOCENTI (utilizza) dei docenti che insegnano alla classe
     =============================== */

  // 1) docenti (username) che insegnano alla classe nel range
  $qDocClass = "
    SELECT DISTINCT ut.username
    FROM oralezione o
    JOIN occupa oc ON oc.idCalendario = o.idCalendario AND oc.classe = '$cl'
    JOIN utilizza ut ON ut.idCalendario = o.idCalendario
    WHERE o.dataGiorno BETWEEN '$fromEsc' AND '$toEsc'
      AND (o.stato IS NULL OR o.stato <> 'CANCELLATO')
      AND ut.username IS NOT NULL AND ut.username <> ''
  ";
  $user = [];
  foreach (mb_dbGetAll($qDocClass) ?: [] as $r) {
    $u = trim((string)$r['username']);
    if ($u !== '') $user[] = $u;
  }

  if (!empty($user)) {
    $inUser = "'" . implode("','", array_map(function ($x) use ($__conMBApp) {
      return mysqli_real_escape_string($__conMBApp, $x);
    }, $user)) . "'";

    // 2) assenze di quei docenti nel range (via utilizza.IDassenza)
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

      $ev = classifyAssenza($a, $scope);
      if ($ev === null) continue;

      $idAss = (int)($a['idAssenza'] ?? 0);
      if ($idAss <= 0) continue;

      // docenti coinvolti in questa assenza
      $assUsernames = getUsernamesByAssenzaId($idAss);
      if (empty($assUsernames)) continue;

      $slots = espandiAssenzaInSlot($a, $ORARI);

      foreach ($slots as $ymd => $ores) {
        foreach ($ores as $ora) {

          $slotKey = $ymd . '|' . normOra($ora);

          // 🔥 MOSTRA assenza SOLO se quel docente insegna in questo slot
          if (!isset($slotTeachers[$slotKey])) continue;

          $presentTeachers = $slotTeachers[$slotKey];

          $match = false;
          foreach ($assUsernames as $uAss) {
            if (isset($presentTeachers[$uAss])) {
              $match = true;
              break;
            }
          }

          if ($match) {
            pushEv($grid, $ymd, $ora, $ev);
          }
        }
      }
    }
  }
}

echo json_encode([
  "ok" => true,
  "scope" => $scope,
  "target" => $target,
  "period" => $period,
  "from" => $from,
  "to" => $to,
  "orari" => $ORARI,
  "grid" => $grid
], JSON_UNESCAPED_UNICODE);
