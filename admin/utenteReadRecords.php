<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<tr>
						<th>Username</th>
						<th>Cognome</th>
						<th>Nomee</th>
						<th>ruolo</th>
						<th>email</th>
						<th></th>
					</tr>';

$query = "	SELECT
				utente.id AS local_id,
				utente.*
			FROM utente
			";

$query .= "order by cognome, nome";

foreach(dbGetAll($query) as $row) {
	$data .= '<tr>
		<td>'.$row['username'].'</td>
		<td>'.$row['cognome'].'</td>
		<td>'.$row['nome'].'</td>
		<td>'.$row['ruolo'].'</td>
		<td>'.$row['email'].'</td>
		';
	$data .='
		<td>
			<button onclick="utenteGetDetails('.$row['local_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
			<button onclick="utenteDelete('.$row['local_id'].', \''.$row['cognome'].' '.$row['nome'].'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
		</td>
		</tr>';
}

$data .= '</table></div>';
echo $data;
?>

