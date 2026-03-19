<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('dirigente','segreteria-ata');

// --- helper escape safe (evita fatal se $__conn è null) ---
function esc_sql_like(string $s): string {
  global $__con;
  if (isset($__con) && $__con instanceof mysqli) {
    return mysqli_real_escape_string($__con, $s);
  }
  // fallback: non ideale ma evita 500; meglio di nulla su hosting “strani”
  return addslashes($s);
}

$stato  = isset($_GET['stato'])  ? trim((string)$_GET['stato'])  : '';
$tipo_id = isset($_GET['tipo_id']) ? intval($_GET['tipo_id']) : 0;
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

$where = " WHERE 1=1 ";

if ($stato !== '') {
  $stato_esc = esc_sql_like($stato);
  $where .= " AND r.stato = '$stato_esc' ";
}

if ($tipo_id > 0) {
  $where .= " AND r.permesso_ata_tipo_id = $tipo_id ";
}

if ($search !== '') {
  $s = esc_sql_like($search);
  $where .= " AND (
      p.cognome   LIKE '%$s%' OR
      p.nome      LIKE '%$s%' OR
      p.matricola LIKE '%$s%' OR
      p.email     LIKE '%$s%' OR
      p.username  LIKE '%$s%'
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
  p.cognome, p.nome, p.email, p.matricola, p.tipo_contratto, p.ruolo
FROM permesso_ata_richiesta r
JOIN permesso_ata_tipo t ON t.id = r.permesso_ata_tipo_id
JOIN personale_ata p ON p.id = r.personale_ata_id
$where
ORDER BY r.created_at DESC
LIMIT 500
";

$rows = dbGetAll($query);
$count = is_array($rows) ? count($rows) : 0;

$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
  <tr>
    <th class="text-center">ID</th>
    <th>Dipendente</th>
    <th class="hidden-xs">Matricola</th>
    <th class="hidden-xs">Email</th>
    <th>Tipo</th>
    <th class="text-center">Stato</th>
    <th class="text-center hidden-xs">Inviato</th>
    <th class="text-center">Azioni</th>
  </tr>';

foreach ($rows as $r) {
  $id = intval($r['id']);
  $dip = htmlspecialchars(($r['cognome'] ?? '').' '.($r['nome'] ?? ''));
  $mat = htmlspecialchars($r['matricola'] ?? '');
  $email = htmlspecialchars($r['email'] ?? '');
  $tipo = htmlspecialchars(($r['tipo_codice'] ?? '').' - '.($r['tipo_descrizione'] ?? ''));
  $st = htmlspecialchars($r['stato'] ?? '');
  $created = htmlspecialchars($r['created_at'] ?? '');

  // badge
  $badge = '<span class="label label-default">'.$st.'</span>';
  if ($st === 'INVIATO')   $badge = '<span class="label label-info">INVIATO</span>';
  if ($st === 'APPROVATO') $badge = '<span class="label label-success">APPROVATO</span>';
  if ($st === 'RESPINTO')  $badge = '<span class="label label-danger">RESPINTO</span>';
  if ($st === 'ANNULLATO') $badge = '<span class="label label-warning">ANNULLATO</span>';

  $data .= '<tr>
    <td class="text-center">'.$id.'</td>
    <td>'.$dip.'</td>
    <td class="hidden-xs">'.$mat.'</td>
    <td class="hidden-xs">'.$email.'</td>
    <td>'.$tipo.'</td>
    <td class="text-center">'.$badge.'</td>
    <td class="text-center hidden-xs">'.$created.'</td>
    <td class="text-center">
      <button class="btn btn-primary btn-xs" onclick="permessoOpen('.$id.')">
        <span class="glyphicon glyphicon-eye-open"></span>
      </button>
    </td>
  </tr>';
}

if ($count === 0) {
  $data .= '<tr><td colspan="8" class="text-center">Nessun risultato</td></tr>';
}

$data .= '</table></div>';
echo $data;
