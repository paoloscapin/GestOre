<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

function numOrBlank($num) {
	if ($num > 0) {
		return $num;
	}
	return '';
}

$soloAttivi = $_GET["soloAttivi"];

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<tr>
						<th>Cognome</th>
						<th>Nome</th>
						<th>Email</th>
						<th>Username</th>
						<th>Matricola</th>
						<th>Contratto</th>
						<th>Giorni</th>
						<th>Cattedra</th>
						<th>Attivo</th>
						<th>Profilo</th>
						<th>Modifica</th>
					</tr>';

$query = "SELECT docente.id AS local_docente_id, docente.*, profilo_docente.* FROM docente LEFT OUTER JOIN profilo_docente ON docente.id = profilo_docente.docente_id WHERE profilo_docente.anno_scolastico_id = $__anno_scolastico_corrente_id ";

if( $soloAttivi) {
	$query .= " AND docente.attivo = true ";
}
$query .= "order by cognome,nome";

foreach(dbGetAll($query) as $row) {
	$data .= '<tr>
		<td>'.$row['cognome'].'</td>
		<td>'.$row['nome'].'</td>
		<td>'.$row['email'].'</td>
		<td>'.$row['username'].'</td>
		<td>'.$row['matricola'].'</td>
		<td>'.$row['tipo_di_contratto'].'</td>
		<td>'.numOrBlank($row['giorni_di_servizio']).'</td>
		<td>'.numOrBlank($row['ore_di_cattedra']).'</td>
		';

	$data .= '<td class="text-center"><input type="checkbox" disabled data-toggle="toggle" data-onstyle="primary" id="attivo" ';
	if ($row['attivo']) {
		$data .= 'checked ';
	}
	$data .= '></td>';

	$data .= '<td class="text-center">';

	if ($row['attivo'] == 1) {
		$data .='
				<button onclick="profiloGetDetails(\''.$row['local_docente_id'].'\')" class="btn btn-deeporange4 btn-xs"><span class="glyphicon glyphicon-cog"></button>
			</td>';
	} else {
		$data .='
				<button onclick="profiloGetDetails(\''.$row['local_docente_id'].'\')" class="btn btn-deeporange4 btn-xs disabed" disabled="disabled" ><span class="glyphicon glyphicon-cog"></button>
			</td>';
	}
	$data .='
		<td>
		<button onclick="docenteGetDetails('.$row['local_docente_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
		<button onclick="docenteDelete('.$row['local_docente_id'].', \''.$row['cognome'].'\', \''.$row['nome'].'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
		</td>
		</tr>';
}

$data .= '</table></div>';
echo $data;
?>

