<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green-3">
					<tr>
						<th>Nome</th>
						<th>Commento</th>
						<th>Responsabile</th>
						<th>max ore</th>
					</tr>';

$query = "	SELECT
				gruppo.id AS gruppo_id,
				gruppo.nome AS gruppo_nome,
				gruppo.commento AS gruppo_commento,
				gruppo.max_ore AS gruppo_max_ore,
				docente.nome AS docente_nome,
				docente.cognome AS docente_cognome
			FROM gruppo
            INNER JOIN docente
            ON gruppo.responsabile_docente_id = docente.id
			";

$query .= "order by gruppo.nome";

foreach(dbGetAll($query) as $row) {
	$data .= '<tr>
		<td>'.$row['gruppo_nome'].'</td>
		<td>'.$row['gruppo_commento'].'</td>
		<td>'.$row['docente_cognome'].' '.$row['docente_nome'].'</td>
		<td>'.$row['gruppo_max_ore'].'</td>
		';
	$data .='
		<td>
		<button onclick="gruppoGestioneGetDetails('.$row['gruppo_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
		<button onclick="gruppoGestioneDelete('.$row['gruppo_id'].', \''.$row['gruppo_nome'].'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
		</td>
		</tr>';
}

$data .= '</table></div>';
echo $data;
?>

