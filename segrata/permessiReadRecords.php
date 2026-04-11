<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('dirigente','segreteria-ata');

function esc_sql_like(string $s): string {
  global $__con;
  if (isset($__con) && $__con instanceof mysqli) {
    return mysqli_real_escape_string($__con, $s);
  }
  return addslashes($s);
}

function fmtDateTimeIT($dt): string {
  if (!$dt) return '';
  $ts = strtotime($dt);
  if (!$ts) return (string)$dt;
  return date('d/m/Y H:i', $ts);
}

$stato      = isset($_GET['stato']) ? trim((string)$_GET['stato']) : '';
$tipo_id    = isset($_GET['tipo_id']) ? intval($_GET['tipo_id']) : 0;
$profilo_id = isset($_GET['profilo_id']) ? intval($_GET['profilo_id']) : 0;
$ufficio_id = isset($_GET['ufficio_id']) ? intval($_GET['ufficio_id']) : 0;
$search     = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

$where = " WHERE 1=1 ";

if ($stato !== '') {
  $stato_esc = esc_sql_like($stato);
  $where .= " AND r.stato = '$stato_esc' ";
}

if ($tipo_id > 0) {
  $where .= " AND r.permesso_ata_tipo_id = $tipo_id ";
}

if ($profilo_id > 0) {
  $where .= " AND pr.id = $profilo_id ";
}

if ($ufficio_id > 0) {
  $where .= " AND u.id = $ufficio_id ";
}

if ($search !== '') {
  $s = esc_sql_like($search);
  $where .= " AND (
      p.cognome   LIKE '%$s%' OR
      p.nome      LIKE '%$s%' OR
      p.matricola LIKE '%$s%' OR
      p.email     LIKE '%$s%' OR
      p.username  LIKE '%$s%' OR
      pr.nome     LIKE '%$s%' OR
      u.nome      LIKE '%$s%'
  ) ";
}

$query = "
SELECT
  r.id,
  r.stato,
  r.created_at,
  r.updated_at,
  t.codice AS tipo_codice,
  t.descrizione AS tipo_descrizione,
  p.username,
  p.cognome,
  p.nome,
  p.email,
  p.matricola,
  p.tipo_contratto,
  pr.nome AS profilo_nome,
  u.nome  AS ufficio_nome
FROM permesso_ata_richiesta r
JOIN permesso_ata_tipo t ON t.id = r.permesso_ata_tipo_id
JOIN personale_ata p ON p.id = r.personale_ata_id
LEFT JOIN personale_ata_profili pr
       ON pr.id = p.id_profilo
LEFT JOIN personale_ata_assegnazioni pa
       ON pa.username = p.username
      AND pa.attiva = 1
LEFT JOIN personale_ata_uffici u
       ON u.id = pa.id_ufficio
$where
ORDER BY r.created_at DESC
LIMIT 500
";

$rows = dbGetAll($query);
if (!is_array($rows)) {
  $rows = [];
}
$count = count($rows);

$data = '
<div class="table-responsive">
  <table class="table table-bordered table-hover table-striped table-green permessi-table">
    <thead>
      <tr>
        <th class="text-center" style="width:70px;">ID</th>
        <th style="width:220px;">Dipendente</th>
        <th class="hidden-xs" style="width:120px;">Matricola</th>
        <th class="hidden-sm hidden-xs" style="width:160px;">Profilo</th>
        <th class="hidden-sm hidden-xs" style="width:160px;">Ufficio</th>
        <th style="width:220px;">Tipo</th>
        <th class="text-center" style="width:120px;">Stato</th>
        <th class="text-center hidden-xs" style="width:150px;">Inviato</th>
        <th class="text-center" style="width:90px;">Azioni</th>
      </tr>
    </thead>
    <tbody>';

foreach ($rows as $r) {
  $id      = intval($r['id']);
  $dip     = htmlspecialchars(trim(($r['cognome'] ?? '').' '.($r['nome'] ?? '')));
  $mat     = htmlspecialchars($r['matricola'] ?? '');
  $profilo = htmlspecialchars($r['profilo_nome'] ?? '');
  $ufficio = htmlspecialchars($r['ufficio_nome'] ?? '');
  $tipo    = htmlspecialchars(($r['tipo_codice'] ?? '').' - '.($r['tipo_descrizione'] ?? ''));
  $st      = strtoupper(trim((string)($r['stato'] ?? '')));
  $created = htmlspecialchars(fmtDateTimeIT($r['created_at'] ?? ''));

  $badge = '<span class="label label-default">-</span>';
  if ($st === 'INVIATO')   $badge = '<span class="label label-info">INVIATO</span>';
  if ($st === 'APPROVATO') $badge = '<span class="label label-success">APPROVATO</span>';
  if ($st === 'RESPINTO')  $badge = '<span class="label label-danger">RESPINTO</span>';
  if ($st === 'ANNULLATO') $badge = '<span class="label label-warning">ANNULLATO</span>';
  if ($st === 'BOZZA')     $badge = '<span class="label label-primary">BOZZA</span>';

  $data .= '
    <tr>
      <td class="text-center"><strong>'.$id.'</strong></td>
      <td>
        <div><strong>'.$dip.'</strong></div>
        <div class="visible-xs text-muted small">'.$profilo.' · '.$ufficio.'</div>
      </td>
      <td class="hidden-xs">'.$mat.'</td>
      <td class="hidden-sm hidden-xs">'.$profilo.'</td>
      <td class="hidden-sm hidden-xs">'.$ufficio.'</td>
      <td>'.$tipo.'</td>
      <td class="text-center">'.$badge.'</td>
      <td class="text-center hidden-xs">'.$created.'</td>
      <td class="text-center">
        <button class="btn btn-primary btn-xs" type="button" onclick="permessoOpen('.$id.')" title="Apri dettaglio">
          <span class="glyphicon glyphicon-eye-open"></span>
        </button>
      </td>
    </tr>';
}

if ($count === 0) {
  $data .= '
    <tr>
      <td colspan="9" class="text-center text-muted" style="padding:25px 10px;">
        Nessuna richiesta trovata
      </td>
    </tr>';
}

$data .= '
    </tbody>
  </table>
</div>';

echo $data;