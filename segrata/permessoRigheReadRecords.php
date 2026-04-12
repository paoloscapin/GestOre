<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('dirigente','segreteria-ata'. 'ras');

if (!isset($_GET['id'])) {
  http_response_code(400);
  echo "ID mancante";
  exit;
}

$id = intval($_GET['id']);

$rows = dbGetAll("
  SELECT
    data_dal,
    data_al,
    ora_dal,
    ora_al,
    dettagli_json
  FROM permesso_ata_richiesta_riga
  WHERE permesso_ata_richiesta_id = $id
  ORDER BY data_dal, ora_dal
");

if (!is_array($rows) || count($rows) === 0) {
  echo '<div class="text-muted">Nessun intervallo presente.</div>';
  exit;
}

function fmtDateIT($d) {
  if (!$d) return '';
  $dt = DateTime::createFromFormat('Y-m-d', $d);
  return $dt ? $dt->format('d/m/Y') : $d;
}

echo '<table class="table table-bordered table-condensed">';
echo '<tr>
  <th>Unità</th>
  <th>Dal</th>
  <th>Al</th>
  <th>Ora da</th>
  <th>Ora a</th>
</tr>';

foreach ($rows as $r) {
  $unita = '';
  if (!empty($r['dettagli_json'])) {
    $dj = json_decode($r['dettagli_json'], true);
    if (is_array($dj) && isset($dj['unita'])) {
      $unita = strtoupper($dj['unita']);
    }
  }
  if ($unita !== 'GIORNI' && $unita !== 'ORE') {
    $unita = (!empty($r['ora_dal']) || !empty($r['ora_al'])) ? 'ORE' : 'GIORNI';
  }

  echo '<tr>';
  echo '<td><strong>'.htmlspecialchars($unita).'</strong></td>';
  echo '<td>'.htmlspecialchars(fmtDateIT($r['data_dal'] ?? '')).'</td>';
  echo '<td>'.htmlspecialchars(fmtDateIT($r['data_al'] ?? '')).'</td>';
  echo '<td>'.htmlspecialchars($r['ora_dal'] ?? '').'</td>';
  echo '<td>'.htmlspecialchars($r['ora_al'] ?? '').'</td>';
  echo '</tr>';
}

echo '</table>';