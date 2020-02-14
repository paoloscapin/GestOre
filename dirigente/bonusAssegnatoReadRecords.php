<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

$docente_id = $_GET['docente_id'];

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<tr>
						<th>Commento</th>
						<th>Importo</th>
					</tr>';

$query = "	SELECT
				bonus_assegnato.id AS local_id,
				bonus_assegnato.*
			FROM bonus_assegnato
			WHERE bonus_assegnato.anno_scolastico_id = $__anno_scolastico_corrente_id
			AND bonus_assegnato.docente_id = $docente_id";

foreach(dbGetAll($query) as $row) {
	$data .= '<tr>
		<td>'.$row['commento'].'</td>
		<td>'.$row['importo'].'</td>
		';
	$data .='
		<td>
		<button onclick="fuisAssegnatoGetDetails('.$row['local_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
		<button onclick="fuisAssegnatoDelete('.$row['local_id'].')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
		</td>
		</tr>';
}

$data .= '</table></div>';
echo $data;
?>
