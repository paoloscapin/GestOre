<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// include Database connection file
require_once '../common/checkSession.php';
require_once '../common/connect.php';

$soloAttivi = $_GET["soloAttivi"];

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<tr>
						<th>Cognome</th>
						<th>Nome</th>
						<th>Email</th>
						<th>Username</th>
						<th>Matricola</th>
						<th>Attivo</th>
						<th>Profilo</th>
						<th>Modifica</th>
					</tr>';

$query = "	SELECT
				docente.id AS local_docente_id,
				docente.*
			FROM docente
			";

if( $soloAttivi) {
	$query .= "WHERE docente.attivo = true ";
}
$query .= "order by cognome,nome";

if (!$result = mysqli_query($con, $query)) {
	exit(mysqli_error($con));
}

// if query results contains rows then fetch those rows
if(mysqli_num_rows($result) > 0) {
	while($row = mysqli_fetch_assoc($result)) {
//			console_log_data("docente=", $row);
		$data .= '<tr>
		<td>'.$row['cognome'].'</td>
		<td>'.$row['nome'].'</td>
		<td>'.$row['email'].'</td>
		<td>'.$row['username'].'</td>
		<td>'.$row['matricola'].'</td>
		';

		$data .= '<td class="text-center"><input type="checkbox" disabled data-toggle="toggle" data-onstyle="primary" id="attivo" ';
	if ($row['attivo']) {
		$data .= 'checked ';
	}
	$data .= '></td>';

$data .= '<td class="text-center">';

if ($row['attivo'] == 1) {
	$data .='
			<button onclick="profiloGetDetails(\''.$row['local_docente_id'].'\')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-cog"></button>
			</td>';
} else {
	$data .='
			<button onclick="profiloGetDetails(\''.$row['local_docente_id'].'\')" class="btn btn-info btn-xs disabed" disabled="disabled" ><span class="glyphicon glyphicon-cog"></button>
			</td>';
}
$data .='
		<td>
		<button onclick="docenteGetDetails('.$row['local_docente_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
		<button onclick="docenteDelete('.$row['local_docente_id'].', \''.$row['cognome'].'\', \''.$row['nome'].'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
		</td>
		</tr>';
	}
}
else {
	// records now found
	$data .= '<tr><td colspan="6">Records not found!</td></tr>';
}

$data .= '</table></div>';

echo $data;
?>

