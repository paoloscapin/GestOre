<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(! isset($_GET)) {
	return;
} else {
	$corso_di_recupero_id = $_GET['corso_di_recupero_id'];
}

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<tr>
						<th class="col-md-2 text-left">cognome</th>
						<th class="col-md-2 text-left">nome</th>
						<th class="col-md-1 text-left">classe</th>
						<th class="col-md-3 text-left">email</th>
						<th class="col-md-3 text-left">commento</th>
						<th class="col-md-1 text-left"></th>
					</tr>';

$query = "	SELECT studente_per_corso_di_recupero.id AS local_id, studente_per_corso_di_recupero.* FROM studente_per_corso_di_recupero WHERE studente_per_corso_di_recupero.corso_di_recupero_id = $corso_di_recupero_id ORDER BY studente_per_corso_di_recupero.cognome DESC, studente_per_corso_di_recupero.nome DESC ;";

foreach(dbGetAll($query) as $row) {
	$data .= '<tr>
		<td>'.$row['cognome'].'</td>
		<td>'.$row['nome'].'</td>
		<td>'.$row['classe'].'</td>
		<td>'.$row['email'].'</td>
		<td>'.$row['commento'].'</td>
		';
	$data .='
		<td class="text-center">
			<button onclick="corsoDiRecuperoStudentiGetDetails('.$row['local_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
			<button onclick="corsoDiRecuperoStudentiDelete('.$row['local_id'].', \''.$row['cognome'].', \''.$row['nome'].'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
		</td>
		</tr>';
}

$data .= '</table></div>';
echo $data;
?>