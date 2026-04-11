<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('dirigente','segreteria-ata');

header('Content-Type: application/json; charset=utf-8');

global $__con;

if (!isset($_POST['id'])) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Missing id'], JSON_UNESCAPED_UNICODE);
  exit;
}

$id = intval($_POST['id']);
$note_segr = isset($_POST['note_segreteria']) ? trim((string)$_POST['note_segreteria']) : '';
$stato = isset($_POST['stato']) ? strtoupper(trim((string)$_POST['stato'])) : null;

if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'ID non valido'], JSON_UNESCAPED_UNICODE);
  exit;
}

$chk = dbGetFirst("SELECT id FROM permesso_ata_richiesta WHERE id = $id LIMIT 1");
if (!$chk) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'Richiesta non trovata'], JSON_UNESCAPED_UNICODE);
  exit;
}

$note_esc = mysqli_real_escape_string($__con, $note_segr);
$gestito_da = (isset($__utente_id) && intval($__utente_id) > 0) ? intval($__utente_id) : "NULL";

/**
 * 🔹 CASO 1: SOLO NOTE (FERIE)
 */
if ($stato === null) {

  $query = "
    UPDATE permesso_ata_richiesta
    SET
      note_segreteria = '$note_esc',
      updated_at = NOW()
    WHERE id = $id
    LIMIT 1
  ";

} else {

  /**
   * 🔹 CASO 2: STATO + NOTE (MODAL STANDARD)
   */
  $allowed = ['INVIATO','APPROVATO','RESPINTO','ANNULLATO'];
  if (!in_array($stato, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Stato non valido'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $stato_esc = mysqli_real_escape_string($__con, $stato);

  $query = "
    UPDATE permesso_ata_richiesta
    SET
      stato = '$stato_esc',
      note_segreteria = '$note_esc',
      gestito_da_utente_id = $gestito_da,
      gestito_il = NOW(),
      updated_at = NOW()
    WHERE id = $id
    LIMIT 1
  ";
}

$ok = dbExec($query);

if ($ok === false) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => mysqli_error($__con)], JSON_UNESCAPED_UNICODE);
  exit;
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);