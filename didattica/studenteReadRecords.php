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

$ancheCancellati = $_GET["ancheCancellati"];

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="text-center col-md-3">Cognome</th>
						<th class="text-center col-md-3">Nome</th>
						<th class="text-center col-md-3">email</th>
						<th class="text-center col-md-1">Classe</th>
						<th class="text-center col-md-1">Anno</th>
						<th class="text-center col-md-1"></th>
					</tr>
					</thead>';

$query = "SELECT * FROM studente ";

if( ! $ancheCancellati) {
	$query .= " WHERE studente.classe <> '' ";
}
$query .= "ORDER BY studente.classe ASC, studente.cognome ASC, studente.nome ASC";

foreach(dbGetAll($query) as $row) {

	$data .= '<tr>
	<td>'.$row['cognome'].'</td>
	<td>'.$row['nome'].'</td>
	<td>'.$row['email'].'</td>
	<td>'.$row['classe'].'</td>
	<td>'.$row['anno'].'</td>';
	$data .='
		<td class="text-center">
		<button onclick="studenteGetDetails('.$row['id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
		<button onclick="studenteDelete('.$row['id'].', \''.$row['cognome'].'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
		</td>
		</tr>';
}

$data .= '</table></div>';

echo $data;
?>
