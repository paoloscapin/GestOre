<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

$docente_id = isset($_GET['docente_id']) ? intval($_GET['docente_id']) : 0;

// anno selezionato (se passato), altrimenti anno corrente
$anno_scolastico_id = isset($_GET['anno_scolastico_id'])
    ? intval($_GET['anno_scolastico_id'])
    : $__anno_scolastico_corrente_id;

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<tr>
						<th>Commento</th>
						<th>Importo</th>
						<th></th>
					</tr>';

$query = "	SELECT
				bonus_assegnato.id AS local_id,
				bonus_assegnato.*
			FROM bonus_assegnato
			WHERE bonus_assegnato.anno_scolastico_id = $anno_scolastico_id
			AND bonus_assegnato.docente_id = $docente_id";

$result = dbGetAll($query);

foreach($result as $row) {
	$data .= '<tr>
		<td>'.$row['commento'].'</td>
		<td>'.$row['importo'].'</td>
		';
	$data .='
		<td>
		<button onclick="bonusAssegnatoGetDetails('.$row['local_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
		<button onclick="bonusAssegnatoDelete('.$row['local_id'].')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
		</td>
		</tr>';
}

$data .= '</table></div>';
echo $data;
?>
