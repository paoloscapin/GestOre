<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('dirigente','segreteria-ata');

$soloAttivi = isset($_GET["soloAttivi"]) ? intval($_GET["soloAttivi"]) : 1;

$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
<tr>
    <th>Cognome</th>
    <th>Nome</th>
    <th>Email</th>
    <th>Username</th>
    <th class="text-center">Matricola</th>
    <th class="text-center">Codice Fiscale</th>
    <th class="text-center">Ruolo</th>
    <th class="text-center">Attivo</th>
    <th class="text-center">Modifica</th>
</tr>';

$query = "SELECT * FROM personale_ata";
if ($soloAttivi == 1) {
    $query .= " WHERE attivo = 1";
}
$query .= " ORDER BY cognome, nome";

foreach (dbGetAll($query) as $row) {
    $data .= '<tr>
        <td>'.htmlspecialchars($row['cognome']).'</td>
        <td>'.htmlspecialchars($row['nome']).'</td>
        <td>'.htmlspecialchars($row['email']).'</td>
        <td>'.htmlspecialchars($row['username']).'</td>
        <td class="text-center">'.htmlspecialchars($row['matricola']).'</td>
        <td class="text-center">'.htmlspecialchars($row['codice_fiscale']).'</td>
        <td class="text-center">'.htmlspecialchars($row['ruolo']).'</td>';

    $data .= '<td class="text-center"><input type="checkbox" disabled data-toggle="toggle" data-onstyle="primary" ';
    if (intval($row['attivo']) === 1) $data .= 'checked ';
    $data .= '></td>';

    $data .= '<td class="text-center">
        <button onclick="personaleAtaGetDetails('.intval($row['id']).')" class="btn btn-warning btn-xs">
            <span class="glyphicon glyphicon-pencil"></span>
        </button>
        <button onclick="personaleAtaDelete('.intval($row['id']).', '.json_encode($row['cognome']).', '.json_encode($row['nome']).')" class="btn btn-danger btn-xs">
            <span class="glyphicon glyphicon-trash"></span>
        </button>
    </td>';

    $data .= '</tr>';
}

$data .= '</table></div>';
echo $data;
