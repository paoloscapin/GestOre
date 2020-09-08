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
						<th>Codice</th>
						<th>Materia</th>
						<th>Aula</th>
						<th>Docente</th>
						<th>Ore</th>
						<th></th>
					</tr>';

$query = "	SELECT
				corso_di_recupero.id AS corso_di_recupero_id,
				corso_di_recupero.codice AS corso_di_recupero_codice,
				corso_di_recupero.aula AS corso_di_recupero_aula,
				corso_di_recupero.numero_ore AS corso_di_recupero_numero_ore,
				materia.nome AS materia_nome,
                docente.cognome AS docente_cognome,
				docente.nome AS docente_nome
			FROM corso_di_recupero
			INNER JOIN materia materia
			ON corso_di_recupero.materia_id = materia.id
			INNER JOIN docente docente
			ON corso_di_recupero.docente_id = docente.id
            WHERE corso_di_recupero.anno_scolastico_id = $__anno_scolastico_corrente_id
			ORDER BY corso_di_recupero.codice ASC
			";

foreach(dbGetAll($query) as $row) {
	$data .= '<tr>
		<td>'.$row['corso_di_recupero_codice'].'</td>
		<td>'.$row['materia_nome'].'</td>
		<td>'.$row['corso_di_recupero_aula'].'</td>
		<td>'.$row['docente_cognome'].' '.$row['docente_nome'].'</td>
        <td>'.$row['corso_di_recupero_numero_ore'].'</td>       
		';
	$data .='
		<td class="text-center">
		<button onclick="corsiDiRecuperoGetDetails('.$row['corso_di_recupero_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
		<button onclick="corsiDiRecuperoDelete('.$row['corso_di_recupero_id'].', \''.$row['corso_di_recupero_codice'].'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
		</td>
		</tr>';
}

$data .= '</table></div>';
echo $data;
?>
