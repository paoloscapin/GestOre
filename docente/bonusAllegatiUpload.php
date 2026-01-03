<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('segreteria-docenti','dirigente','docente');

header('Content-Type: application/json; charset=utf-8');
function out($ok, $msg){ echo json_encode(['success'=>$ok,'message'=>$msg]); exit; }

if (!isset($_POST['bonus_docente_id'])) out(false, 'Parametro mancante');
$bonus_docente_id = intval($_POST['bonus_docente_id']);
$anno = isset($_POST['anno_scolastico_id']) ? intval($_POST['anno_scolastico_id']) : $__anno_scolastico_corrente_id;

if ($bonus_docente_id <= 0) out(false, 'bonus_docente_id non valido');

// regola: docente può modificare solo anno corrente e solo se aperto
if (!$__config->getBonus_rendiconto_aperto()) out(false, 'Rendiconto chiuso');
if ($anno != intval($__anno_scolastico_corrente_id)) out(false, 'Puoi caricare solo sull’anno corrente');

// verifica ownership bonus_docente
$bd = dbGetFirst("SELECT id, docente_id, anno_scolastico_id FROM bonus_docente WHERE id = $bonus_docente_id");
if (!$bd) out(false, 'Record non trovato');
if (intval($bd['anno_scolastico_id']) !== $anno) out(false, 'Anno non coerente');
if (!haRuolo('dirigente') && intval($bd['docente_id']) !== intval($__docente_id)) out(false, 'Non autorizzato');

// files
if (!isset($_FILES['files'])) out(false, 'Nessun file');

$baseDir = realpath(__DIR__ . '/bonus_upload');
if ($baseDir === false) {
    $try = __DIR__ . '/bonus_upload';
    if (!is_dir($try)) @mkdir($try, 0755, true);
    $baseDir = realpath($try);
}
if ($baseDir === false) out(false, 'Cartella upload non disponibile');

$targetDir = $baseDir . '/' . $anno . '/' . intval($__docente_id) . '/' . $bonus_docente_id;
if (!is_dir($targetDir)) @mkdir($targetDir, 0755, true);
if (!is_dir($targetDir)) out(false, 'Impossibile creare cartella upload');


$allowedMime = ['application/pdf'];
$maxEach = 15 * 1024 * 1024; // 15MB per file

$names = $_FILES['files']['name'];
$tmp = $_FILES['files']['tmp_name'];
$err = $_FILES['files']['error'];
$size = $_FILES['files']['size'];
$type = $_FILES['files']['type'];

$uploaded = 0;

for ($i=0; $i<count($names); $i++) {
  if ($err[$i] !== UPLOAD_ERR_OK) continue;

  $orig = $names[$i];
  $origSafe = preg_replace('/[^\w\-. ()]/u', '_', $orig);

  if ($size[$i] <= 0 || $size[$i] > $maxEach) continue;

  // check MIME (meglio finfo)
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime = finfo_file($finfo, $tmp[$i]);
  finfo_close($finfo);

  if (!in_array($mime, $allowedMime, true)) continue;

  $stored = bin2hex(random_bytes(16)) . '.pdf';
  $dest = $targetDir . '/' . $stored;

  if (!move_uploaded_file($tmp[$i], $dest)) continue;

  $mimeEsc = escapeString($mime);
  $origEsc = escapeString($origSafe);
  $storedEsc = escapeString($stored);
  $fileSize = intval($size[$i]);

  $q = "INSERT INTO bonus_docente_allegato
        (bonus_docente_id, docente_id, anno_scolastico_id, original_name, stored_name, mime_type, file_size)
        VALUES
        ($bonus_docente_id, ".intval($__docente_id).", $anno, '$origEsc', '$storedEsc', '$mimeEsc', $fileSize)";
  dbExec($q);

  $uploaded++;
}

if ($uploaded <= 0) out(false, 'Nessun PDF valido caricato');
out(true, "Caricati $uploaded file");
