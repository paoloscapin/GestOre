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
						<th>Nome</th>
						<th>Commento</th>
						<th>Responsabile</th>
						<th>max ore</th>
						<th>clil</th>
						<th></th>
					</tr>';

$query = "	SELECT
				gruppo.id AS gruppo_id,
				gruppo.nome AS gruppo_nome,
				gruppo.commento AS gruppo_commento,
				gruppo.max_ore AS gruppo_max_ore,
				gruppo.clil AS gruppo_clil,
				docente.nome AS docente_nome,
				docente.cognome AS docente_cognome
			FROM gruppo
            INNER JOIN docente
            ON gruppo.responsabile_docente_id = docente.id
			WHERE anno_scolastico_id = $__anno_scolastico_corrente_id
			";

$query .= "order by gruppo.nome";

foreach(dbGetAll($query) as $row) {
	$clilMarker = '';
	if ($row['gruppo_clil']) {
		$clilMarker = '<span class="label label-danger">clil</span>';
	}

	$data .= '<tr>
		<td>'.$row['gruppo_nome'].'</td>
		<td>'.$row['gruppo_commento'].'</td>
		<td>'.$row['docente_cognome'].' '.$row['docente_nome'].'</td>
		<td>'.$row['gruppo_max_ore'].'</td>
		<td>'.$clilMarker.'</td>
		';
	$data .='
		<td class="text-center">
		<button onclick="gruppoGestioneGetDetails('.$row['gruppo_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
		<button onclick="gruppoPartecipantiGetDetails('.$row['gruppo_id'].')" class="btn btn-info btn-xs"><span class="glyphicon glyphicon-user"></button>
		<button onclick="gruppoGestioneDelete('.$row['gruppo_id'].', \''.$row['gruppo_nome'].'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
		</td>
		</tr>';
}

$data .= '</table></div>';
echo $data;
?>
