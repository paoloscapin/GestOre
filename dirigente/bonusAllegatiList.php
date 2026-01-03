<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('dirigente');

$bonus_docente_id = isset($_GET['bonus_docente_id']) ? intval($_GET['bonus_docente_id']) : 0;
if ($bonus_docente_id <= 0) { echo ''; exit; }
// prendo anno/docente per costruire path e autorizzazione (dirigente ok)
$rowBd = dbGetFirst("SELECT docente_id, anno_scolastico_id FROM bonus_docente WHERE id = $bonus_docente_id");
if (!$rowBd) { echo ''; exit; }

$allegati = dbGetAll("
  SELECT id, original_name, file_size, uploaded_at
  FROM bonus_docente_allegato
  WHERE bonus_docente_id = $bonus_docente_id
  ORDER BY uploaded_at DESC, id DESC
");
echo '<ul class="list-group" style="margin:0;">';

if (empty($allegati)) {
  echo '<li class="list-group-item text-muted">Nessun allegato</li>';
  echo '</ul>';
  exit;
}

foreach ($allegati as $a) {
  $id = intval($a['id']);
  $name = htmlspecialchars($a['original_name']);
  $sizeKb = $a['file_size'] ? round(intval($a['file_size'])/1024) : 0;
  $dt = htmlspecialchars($a['uploaded_at']);

  $viewUrl = $__application_base_path . '/docente/bonusAllegatoDownload.php?id=' . $id;
  $downloadUrl = $viewUrl . '&download=1';

  echo '<li class="list-group-item">';
  echo '  <a href="'.$viewUrl.'" target="_blank">'.$name.'</a>';
  echo '  <span class="text-muted"> ('.$sizeKb.' KB, '.$dt.')</span>';
  echo '  <span class="pull-right">';
  echo '    <a class="btn btn-xs btn-default" href="'.$downloadUrl.'" title="Scarica">';
  echo '      <span class="glyphicon glyphicon-download"></span>';
  echo '    </a>';
  echo '  </span>';
  echo '</li>';
}

echo '</ul>';
