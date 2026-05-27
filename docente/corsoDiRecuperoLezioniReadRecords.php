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
						<th class="col-md-1 text-left">data</th>
						<th class="col-md-1 text-left">orario</th>
						<th class="col-md-1 text-left"></th>
						<th class="col-md-8 text-left">argomento</th>
						<th class="col-md-2 text-left"></th>
					</tr>';

$query = "	SELECT lezione_corso_di_recupero.id AS local_id, lezione_corso_di_recupero.* FROM lezione_corso_di_recupero WHERE lezione_corso_di_recupero.corso_di_recupero_id = $corso_di_recupero_id  ORDER BY lezione_corso_di_recupero.data;";

foreach(dbGetAll($query) as $row) {
	$firmato = ($row['firmato']) ? 'X' : '';
	$data .= '<tr>
		<td>'.$row['data'].'</td>
		<td>'.$row['orario'].'</td>
		<td>'.$firmato.'</td>
		<td>'.$row['argomento'].'</td>
		';
	$data .='
		<td class="text-center">
			<button onclick="corsoDiRecuperoLezioniGetDetails('.$row['local_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
			<button onclick="corsoDiRecuperoLezioniDelete('.$row['local_id'].', \''.$row['data'].'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
		</td>
		</tr>';
}

$data .= '</table></div>';
echo $data;
?>