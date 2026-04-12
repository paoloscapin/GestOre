<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('dirigente', 'segreteria-ata');

function esc_sql_like(string $s): string
{
  global $__con;
  if (isset($__con) && $__con instanceof mysqli) {
    return mysqli_real_escape_string($__con, $s);
  }
  return addslashes($s);
}

function fmtDateTimeIT($dt): string
{
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
        <th class="text-center">ID</th>
        <th>Dipendente</th>
        <th>Matricola</th>
        <th>Profilo</th>
        <th>Ufficio</th>
        <th>Tipo</th>
        <th class="text-center">Stato</th>
        <th class="text-center">Inviato</th>
        <th class="text-center">Azioni</th>
      </tr>
    </thead>
    <tbody>';

foreach ($rows as $r) {
  $id      = intval($r['id']);
  $dip     = htmlspecialchars(trim(($r['cognome'] ?? '') . ' ' . ($r['nome'] ?? '')));
  $mat     = htmlspecialchars($r['matricola'] ?? '');
  $profilo = htmlspecialchars($r['profilo_nome'] ?? '');
  $ufficio = htmlspecialchars($r['ufficio_nome'] ?? '');
  $tipo    = htmlspecialchars(($r['tipo_codice'] ?? '') . ' - ' . ($r['tipo_descrizione'] ?? ''));
  $st      = strtoupper(trim((string)($r['stato'] ?? '')));
  $created = htmlspecialchars(fmtDateTimeIT($r['created_at'] ?? ''));

  $badge = '<span class="label label-default">-</span>';

  if ($st === 'INVIATO') {
    $badge = '<span class="label label-info">
    <span class="glyphicon glyphicon-send"></span> INVIATO
  </span>';
  } elseif ($st === 'APPROVATO') {
    $badge = '<span class="label label-success">
    <span class="glyphicon glyphicon-ok"></span> APPROVATO
  </span>';
  } elseif ($st === 'RESPINTO') {
    $badge = '<span class="label label-danger">
    <span class="glyphicon glyphicon-remove"></span> RESPINTO
  </span>';
  } elseif ($st === 'ANNULLATO') {
    $badge = '<span class="label label-warning">
    <span class="glyphicon glyphicon-ban-circle"></span> ANNULLATO
  </span>';
  } elseif ($st === 'BOZZA') {
    $badge = '<span class="label label-primary">
    <span class="glyphicon glyphicon-edit"></span> BOZZA
  </span>';
  } elseif ($st === 'PARZIALE') {
    $badge = '<span class="label label-warning" style="background:#f39c12;">
    <span class="glyphicon glyphicon-adjust"></span> PARZIALE
  </span>';
  }

  $data .= '
  <tr>
    <td class="text-center"><strong>' . $id . '</strong></td>

  
<td>
  <div style="font-weight:600; line-height:1.2;">' . $dip . '</div>
  <div style="font-size:11px; color:#777;">' . $mat . '</div>
</td>

    <td>' . $mat . '</td>
    <td>' . $profilo . '</td>
    <td>' . $ufficio . '</td>
    <td title="' . $tipo . '">' . $tipo . '</td>
    <td class="text-center">' . $badge . '</td>
    <td class="text-center">' . $created . '</td>
    <td class="text-center">
      <button class="btn btn-primary btn-xs" type="button" onclick="permessoOpen(' . $id . ')" title="Apri dettaglio">
        <span class="glyphicon glyphicon-eye-open"></span>
      </button>
      <a href="permessoPdf.php?id=' . $id . '" 
   class="btn btn-default btn-xs" 
   target="_blank" 
   title="Genera PDF">
  <span class="glyphicon glyphicon-print"></span>
</a>
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
