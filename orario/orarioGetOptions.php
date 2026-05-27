<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/connectMBApp.php';

ruoloRichiesto('admin', 'personale-ata', 'portineria', 'segreteria-ata', 'docente', 'dirigente', 'studente', 'genitore', 'segreteria-docenti', 'segreteria-didattica');
header('Content-Type: application/json; charset=utf-8');

// =====================
// COSTANTI
// =====================
// Dirigente da ESCLUDERE sempre dalla lista docenti (match su cognome/nome)
const DS_COGNOME = 'ROSSI';
const DS_NOME    = 'TIZIANA';

// Connessione MBApp
global $__conMBApp;
if (!($__conMBApp instanceof mysqli)) {
  echo json_encode(["ok"=>false,"error"=>"Connessione MBApp non disponibile"], JSON_UNESCAPED_UNICODE);
  exit;
}

$scope = isset($_GET['scope']) ? strtoupper(trim((string)$_GET['scope'])) : 'AULA';

try {
  $items = [];

  if ($scope === 'AULA') {
    $q = "SELECT nroAula AS id, CONCAT(nroAula,' - ',IFNULL(descrizione,'')) AS label
          FROM aula
          WHERE prenotabile='SI'
          ORDER BY CAST(nroAula AS UNSIGNED), nroAula";
    $items = mb_dbGetAll($q) ?: [];
  }
  elseif ($scope === 'CLASSE') {
    $q = "SELECT DISTINCT classe AS id, classe AS label
          FROM occupa
          WHERE classe IS NOT NULL AND classe <> ''
          ORDER BY classe";
    $items = mb_dbGetAll($q) ?: [];
  }
  elseif ($scope === 'DOCENTE') {

    // ✅ Docenti + Admin (tutti gli admin sono anche docenti) - ESCLUDO DS
    // Nota: assumo che in tabella utente esista un campo "ruolo" con valore 'admin'
    // Se nel tuo DB il campo si chiama diverso (es. permessi/Permissions/ruoli), dimmelo e lo adatto.
    $dsC = mysqli_real_escape_string($__conMBApp, DS_COGNOME);
    $dsN = mysqli_real_escape_string($__conMBApp, DS_NOME);

    $q = "
      SELECT
        u.username AS id,
        CONCAT(u.cognome,' ',u.nome) AS label
      FROM utente u
      WHERE u.username IS NOT NULL AND u.username <> ''
        AND u.nome IS NOT NULL AND u.nome <> ''
        AND u.cognome IS NOT NULL AND u.cognome <> ''
        AND NOT (UPPER(u.cognome) = UPPER('$dsC') AND UPPER(u.nome) = UPPER('$dsN'))
        AND (
          u.tipo = 'Docente'
          OR UPPER(COALESCE(u.tipo,'')) = 'ADMIN'
        )
      GROUP BY u.username, u.cognome, u.nome
      ORDER BY u.cognome, u.nome
    ";

    $items = mb_dbGetAll($q) ?: [];
  }
  else {
    echo json_encode(["ok"=>false,"error"=>"Scope non valido"], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if (!is_array($items)) $items = [];

  echo json_encode(["ok"=>true,"items"=>$items], JSON_UNESCAPED_UNICODE);
  exit;

} catch (Throwable $e) {
  echo json_encode(["ok"=>false,"error"=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
}
