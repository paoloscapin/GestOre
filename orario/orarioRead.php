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

function getOrarioVisibilityLevel()
{
  global $__utente_ruolo;

  $ruoloUp = strtoupper(trim((string)$__utente_ruolo));

  // SEGRETERIE / ADMIN -> FULL
  if (in_array($ruoloUp, ['ADMIN', 'SEGRETERIA-DIDATTICA', 'SEGRETERIA-ATA', 'PORTINERIA'], true)) {
    return 'FULL';
  }

  // DOCENTI + PERSONALE ATA/CS -> STAFF
  if (in_array($ruoloUp, ['DOCENTE', 'PERSONALE-ATA'], true)) {
    return 'STAFF';
  }

  // STUDENTI / GENITORI -> PUBLIC
  if (in_array($ruoloUp, ['STUDENTE', 'GENITORE'], true)) {
    return 'PUBLIC';
  }

  return 'PUBLIC';
}

$VISIBILITY_LEVEL = getOrarioVisibilityLevel();
$DEBUG_RUOLI = $_SESSION['ruoli'] ?? ($_SESSION['ruolo'] ?? null);

function enforceScopeByVisibility($scope, $visibilityLevel)
{
  $scope = strtoupper(trim((string)$scope));

  if ($visibilityLevel === 'PUBLIC') {
    // lato pubblico: niente AULA
    if (!in_array($scope, ['CLASSE', 'DOCENTE'], true)) {
      return 'CLASSE';
    }
  } else {
    if (!in_array($scope, ['DOCENTE', 'CLASSE', 'AULA'], true)) {
      return 'DOCENTE';
    }
  }

  return $scope;
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

function getAuleByAssenzaId($idAssenza)
{
  $id = (int)$idAssenza;
  if ($id <= 0) return [];

  $q = "
    SELECT DISTINCT nroAula
    FROM oralezione
    WHERE idAssenza = $id
      AND nroAula IS NOT NULL
      AND nroAula <> ''
    ORDER BY CAST(nroAula AS UNSIGNED), nroAula
  ";

  $rows = mb_dbGetAll($q) ?: [];

  $out = [];
  foreach ($rows as $r) {
    $aula = trim((string)($r['nroAula'] ?? ''));
    if ($aula !== '') $out[] = $aula;
  }

  return $out;
}

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

function getDocentiUsernamesByAssenzaId($idAssenza)
{
  $id = (int)$idAssenza;
  if ($id <= 0) return [];

  $q = "SELECT DISTINCT username
        FROM utilizza
        WHERE IDassenza = $id
          AND username IS NOT NULL
          AND username <> ''
        ORDER BY username";

  $rows = mb_dbGetAll($q) ?: [];
  $out = [];
  foreach ($rows as $r) {
    $u = trim((string)($r['username'] ?? ''));
    if ($u !== '') $out[] = $u;
  }
  return $out;
}

/* ✅ Classifica assenza: SOLO pb/perm/uscita/viaggio, altrimenti NULL */
function classifyAssenza($a, $scope)
{
  global $VISIBILITY_LEVEL;

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
  $docUsernamesArr = ($scope === 'DOCENTE' || $scope === 'CLASSE') ? getDocentiUsernamesByAssenzaId($idAss) : [];

  $meta = $detRaw !== '' ? $detRaw : '';

  // origine: se c'è almeno una classe associata lo trattiamo come evento di classe,
  // altrimenti come assenza docente/personale
  $origin = !empty($classi) ? 'classe' : 'docente';

  // in FULL mostro anche il dettaglio reale
  $showFullDetail = ($VISIBILITY_LEVEL === 'FULL');

  if (isViaggio($mot, $det)) {
    $title = 'Viaggio di istruzione' . ($meta !== '' ? ' · ' . $meta : '');
    return [
      'type'   => 'viag',
      'origin' => $origin,
      'class'  => 'ev ev-viag',
      'title'  => $title,
      'who'    => $docNomi,
      'who_usernames' => $docUsernamesArr,
      'classi' => $classi,
      'badge'  => 'Viaggio di istruzione'
    ];
  }

  if (isUscita($mot, $det)) {
    $fuori = isUscitaFuori($mot, $det);
    $baseTitle = $fuori ? 'Uscita fuori comune' : 'Uscita nel comune';
    $type = $fuori ? 'uscF' : 'uscC';
    $title = $baseTitle . ($meta !== '' ? ' · ' . $meta : '');

    return [
      'type'   => $type,
      'origin' => $origin,
      'class'  => 'ev ev-' . $type,
      'title'  => $title,
      'who'    => $docNomi,
      'who_usernames' => $docUsernamesArr,
      'classi' => $classi,
      'badge'  => $baseTitle
    ];
  }

  if (isPermessoBreve($mot, $det)) {
    $baseTitle = 'Permesso breve';
    $title = ($showFullDetail && $meta !== '') ? ($baseTitle . ' · ' . $meta) : $baseTitle;

    return [
      'type'   => 'pb',
      'origin' => 'docente',
      'class'  => 'ev ev-pb',
      'title'  => $title,
      'who'    => $docNomi,
      'who_usernames' => $docUsernamesArr,
      'classi' => $classi,
      'badge'  => $baseTitle
    ];
  }

  if (isPermessoGiorno($mot, $det)) {
    $baseTitle = 'Permesso (giorno)';
    $title = ($showFullDetail && $meta !== '') ? ($baseTitle . ' · ' . $meta) : $baseTitle;

    return [
      'type'   => 'perm',
      'origin' => 'docente',
      'class'  => 'ev ev-perm',
      'title'  => $title,
      'who'    => $docNomi,
      'who_usernames' => $docUsernamesArr,
      'classi' => $classi,
      'badge'  => $baseTitle
    ];
  }

  return null;
}

/* espansione assenza su slot */
function espandiAssenzaInSlot($a, $ORARI)
{
  $dataFrom = substr(h($a['dataInizio'] ?? ''), 0, 10);
  $dataToRaw = substr(h($a['dataFine'] ?? ''), 0, 10);
  $dataTo = ($dataToRaw !== '') ? $dataToRaw : $dataFrom;

  if ($dataFrom === '') return [];

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
    for ($i = 0; $i < count($ORARI); $i++) {
      $slotStart = $ORARI[$i];
      $slotEnd = $ORARI[$i + 1] ?? '';

      if ($slotEnd === '') {
        continue;
      }

      // include lo slot se c'è sovrapposizione reale
      // es. 14:00-15:15 sovrappone sia 13:50-14:40 sia 14:40-15:30
      if ($slotStart < $oraA && $slotEnd > $oraDa) {
        $slots[] = $slotStart;
      }
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

function eventIsClassLevelForDocente($ev)
{
  $t = (string)($ev['type'] ?? '');
  return in_array($t, ['imp', 'pranzo', 'studio', 'udi'], true);
}

function intersectsClassi($eventClassi, $slotClassiMap)
{
  if (empty($eventClassi) || empty($slotClassiMap)) return false;
  foreach ($eventClassi as $c) {
    if (isset($slotClassiMap[$c])) return true;
  }
  return false;
}

/* costruzione evento oralezione con rooms + classi */
function classeEventoOralezione($row)
{
  $att   = trim(h($row['attivitaProgetto'] ?? ''));
  $sig   = trim(h($row['siglaMateria'] ?? ''));
  $nome  = trim(h($row['nomeMateria'] ?? ''));
  $doc   = trim(h($row['docenti_nomi'] ?? ''));
  $docUsernames = splitCsvUnique($row['docenti_usernames'] ?? '');
  $auleS = trim(h($row['aule'] ?? ''));
  $classiS = trim(h($row['classi'] ?? ''));

  $rooms  = splitRooms($auleS);
  $classi = splitClassi($classiS);

  $SIG_UP  = strtoupper($sig);
  $NOME_UP = strtoupper($nome);

  $isPranzo =
    (strpos($SIG_UP, 'PRANZO') !== false) ||
    (strpos($NOME_UP, 'PAUSA PRANZO') !== false) ||
    (stripos($att, 'PAUSA PRANZO') !== false);

  if ($isPranzo) {
    return [
      'type'  => 'pranzo',
      'class' => 'ev ev-pranzo',
      'title' => 'Pausa pranzo studenti',
      'who'   => '',
      'classi' => $classi,
      'badge' => 'Aula pausa pranzo',
      'rooms' => $rooms
    ];
  }

  $isAulaStudio =
    (strpos($SIG_UP, 'AULA S') !== false) ||
    (strpos($NOME_UP, 'AULA STUDIO') !== false) ||
    (stripos($att, 'AULA STUDIO') !== false);

  if ($isAulaStudio) {
    return [
      'type'  => 'studio',
      'class' => 'ev ev-studio',
      'title' => 'Aula studio studenti',
      'who'   => '',
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
      'who_usernames' => $docUsernames,
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
      'who_usernames' => $docUsernames,
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
    'who_usernames' => $docUsernames,
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

$scope = enforceScopeByVisibility($scope, $VISIBILITY_LEVEL);
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

  $sig = ($ev['type'] ?? '') . '|' . ($ev['title'] ?? '') . '|' . ($ev['who'] ?? '');

  foreach ($grid[$k] as $existing) {
    $sig2 = ($existing['type'] ?? '') . '|' . ($existing['title'] ?? '') . '|' . ($existing['who'] ?? '');
    if ($sig === $sig2) return;
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

function orarioLocalTableHasColumn($tableName, $columnName)
{
  static $cache = [];
  $key = $tableName . '.' . $columnName;
  if (array_key_exists($key, $cache)) return $cache[$key];

  $row = dbGetFirst("SHOW COLUMNS FROM `" . dbEscape($tableName) . "` LIKE '" . dbEscape($columnName) . "'");
  $cache[$key] = ($row != null);
  return $cache[$key];
}

function orarioUpperName($cognome, $nome)
{
  return strtoupper(trim(h($cognome) . ' ' . h($nome)));
}

function orarioSostituzioneSlots($oraInizio, $oraFine, $ORARI)
{
  $oraDa = normOra($oraInizio);
  $oraA = normOra($oraFine);
  if ($oraDa === '') return [];

  $slots = [];
  foreach ($ORARI as $ora) {
    if ($oraA !== '') {
      if ($ora >= $oraDa && $ora < $oraA) $slots[] = $ora;
    } elseif ($ora === $oraDa) {
      $slots[] = $ora;
    }
  }
  if (empty($slots) && in_array($oraDa, $ORARI, true)) $slots[] = $oraDa;
  return $slots;
}

function getSostituzioniOrario($from, $to, $ORARI)
{
  $hasStato = orarioLocalTableHasColumn('sostituzioni', 'stato');
  $where = [
    "s.data >= " . dbQ($from),
    "s.data <= " . dbQ($to),
  ];
  if ($hasStato) {
    $where[] = "(s.stato IS NULL OR UPPER(TRIM(s.stato)) <> 'ANNULLATA')";
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
      " . ($hasStato ? "s.stato" : "'' AS stato") . ",
      ds.id AS idDocenteSostituto,
      ds.username AS usernameSostituto,
      ds.cognome AS cognomeSostituto,
      ds.nome AS nomeSostituto,
      dd.id AS idDocenteSostituito,
      dd.username AS usernameSostituito,
      dd.cognome AS cognomeSostituito,
      dd.nome AS nomeSostituito
    FROM sostituzioni s
    LEFT JOIN docente ds ON ds.id = s.idDocenteSostituto
    LEFT JOIN docente dd ON dd.id = s.idDocenteSostituito
    WHERE " . implode("\n      AND ", $where) . "
    ORDER BY s.data, s.oraInizio, s.oraFine, s.classe, s.aula
  ";

  $out = [];
  foreach (dbGetAll($q) ?: [] as $r) {
    $data = substr(trim(h($r['data'] ?? '')), 0, 10);
    if ($data === '') continue;

    $sost = [
      'id' => (int)($r['idSostituzione'] ?? 0),
      'data' => $data,
      'oraInizio' => normOra($r['oraInizio'] ?? ''),
      'oraFine' => normOra($r['oraFine'] ?? ''),
      'materia' => trim(h($r['materia'] ?? '')),
      'classe' => strtoupper(trim(h($r['classe'] ?? ''))),
      'aula' => trim(h($r['aula'] ?? '')),
      'stato' => trim(h($r['stato'] ?? '')),
      'sostituto' => orarioUpperName($r['cognomeSostituto'] ?? '', $r['nomeSostituto'] ?? ''),
      'sostituto_username' => trim(h($r['usernameSostituto'] ?? '')),
      'sostituito' => orarioUpperName($r['cognomeSostituito'] ?? '', $r['nomeSostituito'] ?? ''),
      'sostituito_username' => trim(h($r['usernameSostituito'] ?? '')),
    ];

    foreach (orarioSostituzioneSlots($r['oraInizio'] ?? '', $r['oraFine'] ?? '', $ORARI) as $ora) {
      $key = $data . '|' . $ora;
      if (!isset($out[$key])) $out[$key] = [];
      $out[$key][] = $sost;
    }
  }

  return $out;
}

function orarioNamesFromWho($who)
{
  $parts = preg_split('/[\r\n;,|]+/', (string)$who, -1, PREG_SPLIT_NO_EMPTY);
  $out = [];
  foreach ($parts as $p) {
    $v = strtoupper(trim(preg_replace('/\s+/', ' ', $p)));
    if ($v !== '') $out[] = $v;
  }
  return $out;
}

function orarioSostituzioneMatchesEvent($sost, $ev)
{
  $classe = trim((string)($sost['classe'] ?? ''));
  $aula = trim((string)($sost['aula'] ?? ''));
  $sostituito = strtoupper(trim((string)($sost['sostituito'] ?? '')));
  $sostituitoUsername = trim((string)($sost['sostituito_username'] ?? ''));

  $eventClassi = array_map('strtoupper', $ev['classi'] ?? []);
  $eventRooms = array_map('strval', $ev['rooms'] ?? []);
  $eventUsernames = array_map('strval', $ev['who_usernames'] ?? []);
  $eventNames = orarioNamesFromWho($ev['who'] ?? '');

  $classOk = ($classe === '' || in_array($classe, $eventClassi, true));
  $roomOk = ($aula === '' || in_array($aula, $eventRooms, true));
  $teacherOk = true;
  if ($sostituitoUsername !== '') {
    $teacherOk = in_array($sostituitoUsername, $eventUsernames, true);
    if (!$teacherOk && $sostituito !== '') {
      $teacherOk = in_array($sostituito, $eventNames, true);
    }
  } elseif ($sostituito !== '') {
    $teacherOk = in_array($sostituito, $eventNames, true);
  }

  return $classOk && $roomOk && $teacherOk;
}

function orarioBuildSostituzioneEvent($sost)
{
  return [
    'type' => 'sost',
    'origin' => 'sostituzione',
    'class' => 'ev ev-sost',
    'title' => trim((string)($sost['materia'] ?? '')) !== '' ? trim((string)$sost['materia']) : 'Sostituzione',
    'who' => trim((string)($sost['sostituto'] ?? '')),
    'who_usernames' => trim((string)($sost['sostituto_username'] ?? '')) !== '' ? [trim((string)$sost['sostituto_username'])] : [],
    'classi' => trim((string)($sost['classe'] ?? '')) !== '' ? [trim((string)$sost['classe'])] : [],
    'rooms' => trim((string)($sost['aula'] ?? '')) !== '' ? [trim((string)$sost['aula'])] : [],
    'badge' => 'Sostituzione',
    'sostituzione' => $sost,
    'is_sostituzione_extra' => true,
  ];
}

function applySostituzioniToGrid(&$grid, $sostituzioniBySlot, $scope, $target)
{
  $scope = strtoupper(trim((string)$scope));
  $target = trim((string)$target);

  foreach ($sostituzioniBySlot as $key => $sostList) {
    if (!isset($grid[$key])) $grid[$key] = [];

    foreach ($grid[$key] as $idx => $ev) {
      $type = strtolower(trim((string)($ev['type'] ?? '')));
      if (!in_array($type, ['curr', 'udi', 'imp'], true)) continue;

      foreach ($sostList as $sost) {
        if (!orarioSostituzioneMatchesEvent($sost, $ev)) continue;

        $ev['sostituzione'] = $sost;
        $ev['badge_originale'] = $ev['badge'] ?? '';
        $ev['badge'] = 'Sostituzione';
        $ev['who_originale'] = $ev['who'] ?? '';
        $ev['who_usernames_originali'] = $ev['who_usernames'] ?? [];
        $ev['who'] = $sost['sostituto'] ?? '';
        $ev['who_usernames'] = !empty($sost['sostituto_username']) ? [$sost['sostituto_username']] : [];
        $grid[$key][$idx] = $ev;
        break;
      }
    }

    if ($scope === 'DOCENTE') {
      foreach ($sostList as $sost) {
        $subUsername = trim((string)($sost['sostituto_username'] ?? ''));
        if ($subUsername === '' || $subUsername !== $target) continue;

        $alreadyVisible = false;
        foreach ($grid[$key] as $ev) {
          $s = $ev['sostituzione'] ?? null;
          if (is_array($s) && (int)($s['id'] ?? 0) === (int)($sost['id'] ?? 0)) {
            $alreadyVisible = true;
            break;
          }
        }
        if (!$alreadyVisible) {
          $grid[$key][] = orarioBuildSostituzioneEvent($sost);
        }
      }
    }
  }
}

/* -------------------- 1) ORALEZIONE -------------------- */
if ($scope === 'AULA') {
  $nro = mysqli_real_escape_string($__conMBApp, $target);

  $q = "
    SELECT
      o.idCalendario,
      o.dataGiorno, o.ora, o.siglaMateria, o.attivitaProgetto,
      m.nomeMateria,
      GROUP_CONCAT(DISTINCT CONCAT(u.cognome,' ',u.nome) ORDER BY u.cognome, u.nome SEPARATOR ', ') AS docenti_nomi,
      GROUP_CONCAT(DISTINCT u.username ORDER BY u.cognome, u.nome SEPARATOR ', ') AS docenti_usernames,
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
      GROUP_CONCAT(DISTINCT CONCAT(u.cognome,' ',u.nome) ORDER BY u.cognome, u.nome SEPARATOR ', ') AS docenti_nomi,
      GROUP_CONCAT(DISTINCT u.username ORDER BY u.cognome, u.nome SEPARATOR ', ') AS docenti_usernames,
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

  // Mappa slot -> classi del docente in quello slot
  $slotClasses = [];
  $allClassiDocente = [];

  $qSlotClassi = "
    SELECT DISTINCT
      o.dataGiorno,
      o.ora,
      oc.classe
    FROM oralezione o
    JOIN utilizza ut2
      ON ut2.idCalendario = o.idCalendario
     AND ut2.username = '$u0'
    JOIN occupa oc
      ON oc.idCalendario = o.idCalendario
    WHERE o.dataGiorno BETWEEN '$fromEsc' AND '$toEsc'
      AND (o.stato IS NULL OR o.stato <> 'CANCELLATO')
      AND oc.classe IS NOT NULL
      AND oc.classe <> ''
  ";

  foreach (mb_dbGetAll($qSlotClassi) ?: [] as $r) {
    $k = substr((string)$r['dataGiorno'], 0, 10) . '|' . normOra($r['ora'] ?? '');
    $cl = trim((string)($r['classe'] ?? ''));
    if ($k !== '' && $cl !== '') {
      if (!isset($slotClasses[$k])) $slotClasses[$k] = [];
      $slotClasses[$k][$cl] = true;
      $allClassiDocente[$cl] = true;
    }
  }

  // 1) Orario normale del docente
  $q = "
    SELECT
      o.idCalendario,
      o.dataGiorno, o.ora, o.siglaMateria, o.attivitaProgetto,
      m.nomeMateria,
      GROUP_CONCAT(DISTINCT CONCAT(u.cognome,' ',u.nome) ORDER BY u.cognome, u.nome SEPARATOR ', ') AS docenti_nomi,
      GROUP_CONCAT(DISTINCT u.username ORDER BY u.cognome, u.nome SEPARATOR ', ') AS docenti_usernames,
      GROUP_CONCAT(DISTINCT o.nroAula ORDER BY CAST(o.nroAula AS UNSIGNED), o.nroAula SEPARATOR ', ') AS aule,
      GROUP_CONCAT(DISTINCT oc.classe ORDER BY oc.classe SEPARATOR ', ') AS classi
    FROM oralezione o
    JOIN utilizza ut2
      ON ut2.idCalendario = o.idCalendario
     AND ut2.username = '$u0'
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

  // 2) Eventi della classe del docente (es. INVALSI, impegni istituto, aula studio, ecc.)
  if (!empty($allClassiDocente)) {
    $classiEsc = array_map(function ($c) use ($__conMBApp) {
      return "'" . mysqli_real_escape_string($__conMBApp, $c) . "'";
    }, array_keys($allClassiDocente));
    $inClassi = implode(",", $classiEsc);

    $qEventiClasse = "
      SELECT
        o.idCalendario,
        o.dataGiorno, o.ora, o.siglaMateria, o.attivitaProgetto,
        m.nomeMateria,
        GROUP_CONCAT(DISTINCT CONCAT(u.cognome,' ',u.nome) ORDER BY u.cognome, u.nome SEPARATOR ', ') AS docenti_nomi,
        GROUP_CONCAT(DISTINCT u.username ORDER BY u.cognome, u.nome SEPARATOR ', ') AS docenti_usernames,
        GROUP_CONCAT(DISTINCT o.nroAula ORDER BY CAST(o.nroAula AS UNSIGNED), o.nroAula SEPARATOR ', ') AS aule,
        GROUP_CONCAT(DISTINCT oc.classe ORDER BY oc.classe SEPARATOR ', ') AS classi
      FROM oralezione o
      JOIN occupa oc
        ON oc.idCalendario = o.idCalendario
      LEFT JOIN utilizza utAll
        ON utAll.idCalendario = o.idCalendario
       AND utAll.username IS NOT NULL
      LEFT JOIN utente u
        ON u.username = utAll.username
      LEFT JOIN materia m
        ON m.siglaMateria = o.siglaMateria
      WHERE oc.classe IN ($inClassi)
        AND o.dataGiorno BETWEEN '$fromEsc' AND '$toEsc'
        AND (o.stato IS NULL OR o.stato <> 'CANCELLATO')
      GROUP BY
        o.idCalendario, o.dataGiorno, o.ora, o.siglaMateria, o.attivitaProgetto, m.nomeMateria
    ";

    foreach (mb_dbGetAll($qEventiClasse) ?: [] as $r) {
      $ev = classeEventoOralezione($r);

      // in DOCENTE vogliamo solo gli eventi "di classe", non tutte le lezioni della classe
      if (!eventIsClassLevelForDocente($ev)) continue;

      $slotKey = substr((string)$r['dataGiorno'], 0, 10) . '|' . normOra($r['ora'] ?? '');
      if (!isset($slotClasses[$slotKey])) continue;

      if (!intersectsClassi($ev['classi'] ?? [], $slotClasses[$slotKey])) continue;

      pushEvUnique($grid, $r['dataGiorno'], $r['ora'], $ev);
    }
  }
  // 3) Consigli di classe senza docenti agganciati:
  // se in assenze.motivo = "CONSIGLIO DI CLASSE"
  // e in dettagli c'è "CC 1A", lo mostro ai docenti che insegnano in 1A
  $docenteLocale = dbGetFirst("
    SELECT id
    FROM docente
    WHERE username = " . dbQ($target) . "
    LIMIT 1
  ");

  $idDocenteLocale = (int)($docenteLocale['id'] ?? 0);

  if ($idDocenteLocale > 0) {

    $classiDocenteDaGestore = dbGetAll("
      SELECT DISTINCT c.classe AS classe
      FROM docente_insegna di
      JOIN classi c ON c.id = di.id_classe
      WHERE di.id_docente = " . dbI($idDocenteLocale) . "
        AND c.classe IS NOT NULL
        AND c.classe <> ''
    ") ?: [];

    $classiCdc = [];
    foreach ($classiDocenteDaGestore as $r) {
      $cl = strtoupper(trim((string)($r['classe'] ?? '')));
      if ($cl !== '') {
        $classiCdc[$cl] = true;
      }
    }

    if (!empty($classiCdc)) {

      // 3) Consigli di classe: classe letta da dettagli, docenti da docente_insegna
      $qCdc = "
  SELECT a.*
  FROM assenze a
  WHERE UPPER(TRIM(COALESCE(a.motivo, ''))) = 'CONSIGLIO DI CLASSE'
    AND DATE(COALESCE(NULLIF(a.dataFine,''), a.dataInizio)) >= '$fromEsc'
    AND DATE(a.dataInizio) <= '$toEsc'
    AND UPPER(TRIM(COALESCE(a.stato, ''))) = 'CONFERMATO'
";

      foreach (mb_dbGetAll($qCdc) ?: [] as $a) {

        $det = strtoupper(trim((string)($a['dettagli'] ?? '')));

        $classeCdc = '';
        if (preg_match('/\bCC\s+([0-9][A-Z]{1,4})\b/u', $det, $m)) {
          $classeCdc = strtoupper(trim($m[1]));
        } elseif (preg_match('/\b([0-9][A-Z]{1,4})\b/u', $det, $m)) {
          $classeCdc = strtoupper(trim($m[1]));
        }

        if ($classeCdc === '') continue;

        $qTargetInClasse = "
    SELECT COUNT(*) AS n
    FROM docente_insegna di
    JOIN docente d ON d.id = di.id_docente
    JOIN classi c ON c.id = di.id_classe
    WHERE d.username = " . dbQ($target) . "
      AND UPPER(TRIM(c.classe)) = " . dbQ($classeCdc) . "
  ";

        $isDocenteClasse = (int)(dbGetValue($qTargetInClasse) ?? 0);

        if ($isDocenteClasse <= 0) {
          continue;
        }

        $ev = [
          'type'   => 'imp',
          'origin' => 'classe',
          'class'  => 'ev ev-imp',
          'title'  => 'Consiglio di classe · ' . $classeCdc,
          'who'    => '',
          'classi' => [$classeCdc],
          'rooms'  => getAuleByAssenzaId((int)($a['idAssenza'] ?? 0)),
          'badge'  => 'Consiglio di classe'
        ];

        $slots = espandiAssenzaInSlot($a, $ORARI);

        foreach ($slots as $ymd => $ores) {
          foreach ($ores as $ora) {
            pushEvUnique($grid, $ymd, $ora, $ev);
          }
        }
      }
    }
  }
}

/* -------------------- 2) ASSENZE collegate (SOLO DOCENTE/CLASSE) -------------------- */
if ($VISIBILITY_LEVEL !== 'PUBLIC' && $scope === 'DOCENTE') {

  $u = mysqli_real_escape_string($__conMBApp, $target);


  $qA = "
    SELECT a.*
    FROM assenze a
    WHERE a.idAssenza IN (
      SELECT DISTINCT ut.IDassenza
      FROM utilizza ut
      WHERE ut.username = '$u'
        AND ut.IDassenza IS NOT NULL
    )
      AND DATE(COALESCE(NULLIF(a.dataFine,''), a.dataInizio)) >= '$fromEsc'
      AND DATE(a.dataInizio) <= '$toEsc'
      AND UPPER(TRIM(COALESCE(a.stato, ''))) = 'CONFERMATO'
  ";

  foreach (mb_dbGetAll($qA) ?: [] as $a) {
    $ev = classifyAssenza($a, $scope);
    if ($ev === null) continue;
    $slots = espandiAssenzaInSlot($a, $ORARI);
    foreach ($slots as $ymd => $ores) {
      foreach ($ores as $ora) pushEvUnique($grid, $ymd, $ora, $ev);
    }
  }

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
  foreach (mb_dbGetAll($qCal) ?: [] as $r) $calIds[] = (int)$r['idCalendario'];

  if (!empty($calIds)) {
    $inCal = implode(",", $calIds);

    $qColleghi = "
      SELECT DISTINCT ut.username
      FROM utilizza ut
      WHERE ut.idCalendario IN ($inCal)
        AND ut.username <> '$u'
        AND ut.username IS NOT NULL
    ";

    $colleghi = [];
    foreach (mb_dbGetAll($qColleghi) ?: [] as $r) $colleghi[] = mysqli_real_escape_string($__conMBApp, $r['username']);

    if (!empty($colleghi)) {
      $inUser = "'" . implode("','", $colleghi) . "'";

      $qAssCol = "
        SELECT a.*
        FROM assenze a
        WHERE a.idAssenza IN (
          SELECT DISTINCT ut.IDassenza
          FROM utilizza ut
          WHERE ut.username IN ($inUser)
            AND ut.IDassenza IS NOT NULL
        )
          AND DATE(COALESCE(NULLIF(a.dataFine,''), a.dataInizio)) >= '$fromEsc'
          AND DATE(a.dataInizio) <= '$toEsc'
          AND UPPER(TRIM(COALESCE(a.stato, ''))) = 'CONFERMATO'
      ";

      foreach (mb_dbGetAll($qAssCol) ?: [] as $a) {
        $ev = classifyAssenza($a, $scope);
        if ($ev === null) continue;

        $slots = espandiAssenzaInSlot($a, $ORARI);
        foreach ($slots as $ymd => $ores) {
          foreach ($ores as $ora) pushEvUnique($grid, $ymd, $ora, $ev);
        }
      }
    }
  }

  // 3) Assenze / uscite / viaggi della CLASSE del docente
  if (!empty($slotClasses)) {
    $allClassiDocente2 = [];
    foreach ($slotClasses as $slotMap) {
      foreach ($slotMap as $cl => $_true) {
        $allClassiDocente2[$cl] = true;
      }
    }

    if (!empty($allClassiDocente2)) {
      $classiEsc = array_map(function ($c) use ($__conMBApp) {
        return "'" . mysqli_real_escape_string($__conMBApp, $c) . "'";
      }, array_keys($allClassiDocente2));
      $inClassi = implode(",", $classiEsc);

      $qAssClasseDoc = "
        SELECT a.*
        FROM assenze a
        WHERE a.idAssenza IN (
          SELECT DISTINCT oc.IDassenza
          FROM occupa oc
          WHERE oc.classe IN ($inClassi)
            AND oc.IDassenza IS NOT NULL
        )
          AND DATE(COALESCE(NULLIF(a.dataFine,''), a.dataInizio)) >= '$fromEsc'
          AND DATE(a.dataInizio) <= '$toEsc'
          AND UPPER(TRIM(COALESCE(a.stato, ''))) = 'CONFERMATO'
      ";

      foreach (mb_dbGetAll($qAssClasseDoc) ?: [] as $a) {
        $ev = classifyAssenza($a, $scope);
        if ($ev === null) continue;

        $slots = espandiAssenzaInSlot($a, $ORARI);

        foreach ($slots as $ymd => $ores) {
          foreach ($ores as $ora) {
            $slotKey = $ymd . '|' . normOra($ora);
            if (!isset($slotClasses[$slotKey])) continue;

            if (!intersectsClassi($ev['classi'] ?? [], $slotClasses[$slotKey])) continue;

            pushEvUnique($grid, $ymd, $ora, $ev);
          }
        }
      }
    }
  }
} elseif ($VISIBILITY_LEVEL !== 'PUBLIC' && $scope === 'CLASSE') {

  $cl = mysqli_real_escape_string($__conMBApp, $target);

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
      AND DATE(COALESCE(NULLIF(a.dataFine,''), a.dataInizio)) >= '$fromEsc'
      AND DATE(a.dataInizio) <= '$toEsc'
      AND UPPER(TRIM(COALESCE(a.stato, ''))) = 'CONFERMATO'
  ";

  foreach (mb_dbGetAll($qA) ?: [] as $a) {
    $ev = classifyAssenza($a, $scope);
    if ($ev === null) continue;

    if (!empty($ev['classi']) && !in_array($cl, $ev['classi'], true)) continue;

    $slots = espandiAssenzaInSlot($a, $ORARI);
    foreach ($slots as $ymd => $ores) {
      foreach ($ores as $ora) pushEvUnique($grid, $ymd, $ora, $ev);
    }
  }

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

    $qAssDoc = "
      SELECT a.*
      FROM assenze a
      WHERE a.idAssenza IN (
        SELECT DISTINCT ut.IDassenza
        FROM utilizza ut
        WHERE ut.username IN ($inUser)
          AND ut.IDassenza IS NOT NULL
      )
        AND DATE(COALESCE(NULLIF(a.dataFine,''), a.dataInizio)) >= '$fromEsc'
        AND DATE(a.dataInizio) <= '$toEsc'
        AND UPPER(TRIM(COALESCE(a.stato, ''))) = 'CONFERMATO'
    ";

    foreach (mb_dbGetAll($qAssDoc) ?: [] as $a) {
      $ev = classifyAssenza($a, $scope);
      if ($ev === null) continue;

      $idAss = (int)($a['idAssenza'] ?? 0);
      if ($idAss <= 0) continue;

      $assUsernames = getUsernamesByAssenzaId($idAss);
      if (empty($assUsernames)) continue;

      $slots = espandiAssenzaInSlot($a, $ORARI);

      foreach ($slots as $ymd => $ores) {
        foreach ($ores as $ora) {

          $slotKey = $ymd . '|' . normOra($ora);
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

/* =========================================================
   ✅ NORMALIZZAZIONE TOTALE GRID (anti-crash JS)
   Garantisce sempre rooms/classi array e campi stringa.
   ========================================================= */
function normalizeEventArray(&$ev)
{
  if (!is_array($ev)) $ev = [];

  if (!isset($ev['type']))   $ev['type'] = '';
  if (!isset($ev['origin'])) $ev['origin'] = '';
  if (!isset($ev['class']))  $ev['class'] = '';
  if (!isset($ev['title']))  $ev['title'] = '';
  if (!isset($ev['who']))    $ev['who'] = '';
  if (!isset($ev['who_usernames']) || !is_array($ev['who_usernames'])) $ev['who_usernames'] = [];
  if (!isset($ev['badge']))  $ev['badge'] = '';

  $ev['type']   = (string)$ev['type'];
  $ev['origin'] = (string)$ev['origin'];
  $ev['class']  = (string)$ev['class'];
  $ev['title']  = (string)$ev['title'];
  $ev['who']    = (string)$ev['who'];
  $ev['who_usernames'] = array_values(array_filter(array_map('strval', $ev['who_usernames']), function ($x) {
    return $x !== '';
  }));
  $ev['badge']  = (string)$ev['badge'];

  if (!isset($ev['classi']) || !is_array($ev['classi'])) $ev['classi'] = [];
  $ev['classi'] = array_values(array_filter(array_map('strval', $ev['classi']), function ($x) {
    return $x !== '';
  }));

  if (!isset($ev['rooms']) || !is_array($ev['rooms'])) $ev['rooms'] = [];
  $ev['rooms'] = array_values(array_filter(array_map('strval', $ev['rooms']), function ($x) {
    return $x !== '';
  }));
}

function applyVisibilityToEvent($ev, $scope, $visibilityLevel)
{
  if (!is_array($ev)) return null;

  $type   = strtolower(trim((string)($ev['type'] ?? '')));
  $origin = strtolower(trim((string)($ev['origin'] ?? '')));

  // =========================
  // FULL: vede tutto
  // =========================
  if ($visibilityLevel === 'FULL') {
    return $ev;
  }

  // =========================
  // STAFF: vede tutto, ma assenze docente anonimizzate nel motivo
  // =========================
  if ($visibilityLevel === 'STAFF') {
    if (in_array($type, ['pb', 'perm', 'uscc', 'uscf'], true) && $origin === 'docente') {
      $ev['title'] = 'Assente';
      $ev['badge'] = 'Assente';
      return $ev;
    }

    if ($type === 'viag' && $origin === 'docente') {
      $ev['title'] = 'Assente';
      $ev['badge'] = 'Assente';
      return $ev;
    }

    return $ev;
  }

  // =========================
  // PUBLIC: nessuna assenza docente
  // =========================
  if ($visibilityLevel === 'PUBLIC') {
    if (in_array($type, ['pb', 'perm', 'uscc', 'uscf'], true) && $origin === 'docente') {
      return null;
    }

    if ($type === 'viag' && $origin === 'docente') {
      return null;
    }

    return $ev;
  }

  return $ev;
}

function applyVisibilityToGrid(&$grid, $scope, $visibilityLevel)
{
  foreach ($grid as $k => $arr) {
    $out = [];
    foreach ($arr as $ev) {
      $ev2 = applyVisibilityToEvent($ev, $scope, $visibilityLevel);
      if ($ev2 !== null) $out[] = $ev2;
    }
    $grid[$k] = $out;
  }
}

function normalizeGrid(&$grid)
{
  if (!is_array($grid)) {
    $grid = [];
    return;
  }
  foreach ($grid as $k => $arr) {
    if (!is_array($arr)) {
      $grid[$k] = [];
      continue;
    }
    foreach ($arr as $i => $ev) {
      normalizeEventArray($ev);
      $grid[$k][$i] = $ev;
    }
  }
}

$sostituzioniBySlot = getSostituzioniOrario($from, $to, $ORARI);
applySostituzioniToGrid($grid, $sostituzioniBySlot, $scope, $target);

applyVisibilityToGrid($grid, $scope, $VISIBILITY_LEVEL);
normalizeGrid($grid);

echo json_encode([
  "ok" => true,
  "scope" => $scope,
  "target" => $target,
  "period" => $period,
  "from" => $from,
  "to" => $to,
  "visibilityLevel" => $VISIBILITY_LEVEL,
  "debugRuoli" => $DEBUG_RUOLI,
  "orari" => $ORARI,
  "grid" => $grid
], JSON_UNESCAPED_UNICODE);
