<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('personale-ata');

$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
<tr>
  <th class="text-center">ID</th>
  <th>Tipo</th>
  <th class="text-center">Stato</th>
  <th class="text-center">Creato</th>
  <th class="text-center">Aggiornato</th>
  <th class="text-center">Azioni</th>
</tr>';

$query = "
SELECT
  r.id, r.stato, r.created_at, r.updated_at,
  t.codice, t.descrizione
FROM permesso_ata_richiesta r
INNER JOIN permesso_ata_tipo t ON t.id = r.permesso_ata_tipo_id
WHERE r.personale_ata_id = $__ata_id
ORDER BY r.updated_at DESC, r.id DESC
";

foreach (dbGetAll($query) as $row) {
  $id = (int)$row['id'];
  $tipo = htmlspecialchars($row['codice'].' - '.$row['descrizione']);
  $stato = htmlspecialchars($row['stato']);

  $btnEdit = '<button class="btn btn-warning btn-xs" onclick="permessoGetDetails('.$id.')"><span class="glyphicon glyphicon-pencil"></span></button>';
  $btnDel  = ($row['stato'] === 'BOZZA')
    ? '<button class="btn btn-danger btn-xs" onclick="permessoDelete('.$id.')"><span class="glyphicon glyphicon-trash"></span></button>'
    : '<button class="btn btn-danger btn-xs" disabled><span class="glyphicon glyphicon-trash"></span></button>';

  $data .= '<tr>
    <td class="text-center">'.$id.'</td>
    <td>'.$tipo.'</td>
    <td class="text-center">'.$stato.'</td>
    <td class="text-center">'.htmlspecialchars($row['created_at']).'</td>
    <td class="text-center">'.htmlspecialchars($row['updated_at']).'</td>
    <td class="text-center">'.$btnEdit.' '.$btnDel.'</td>
  </tr>';
}

$data .= '</table></div>';
echo $data;
