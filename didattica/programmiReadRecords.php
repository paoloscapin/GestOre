<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// include Database connection file
require_once '../common/checkSession.php';
require_once '../common/connect.php';

$anno_filtro_id = $_GET["anno_id"];
$materia_filtro_id = $_GET["materia_id"];
$indirizzo_filtro_id = $_GET["indirizzo_id"];

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="text-center col-md-1">Anno</th>
						<th class="text-center col-md-2">Indirizzo</th>
						<th class="text-center col-md-4">Materia</th>
						<th class="text-center col-md-1">Azioni</th>
						<th class="text-center col-md-2">Ultimo aggiornamento</th>
						<th class="text-center col-md-2">Autore ultimo aggiornamento</th>
					</tr>
					</thead>';

$query = "	SELECT
				programma_materie.id AS programma_id,
				programma_materie.anno AS anno_id,
				programma_materie.id_indirizzo AS indirizzo_id,
				programma_materie.id_materia AS materia_id,
				programma_materie.updated AS ultimo_agg,
                indirizzo.id,
                indirizzo.nome AS indirizzo_nome,
                materia.id,
                materia.nome AS materia_nome,
				utente.id,
				utente.nome AS utente_nome,
				utente.cognome AS utente_cognome
			FROM programma_materie
			INNER JOIN indirizzo indirizzo
			ON programma_materie.id_indirizzo = indirizzo.id
			INNER JOIN materia materia
			ON programma_materie.id_materia = materia.id
			INNER JOIN utente utente
			ON programma_materie.id_utente = utente.id
			WHERE true ";

if ($anno_filtro_id > 0) {
	$query .= "AND programma_materie.anno = '$anno_filtro_id' ";
}
if ($materia_filtro_id > 0) {
	$query .= "AND programma_materie.id_materia = $materia_filtro_id ";
}
if ($indirizzo_filtro_id > 0) {
	$query .= "AND programma_materie.id_indirizzo = $indirizzo_filtro_id ";
}

$query .= "ORDER BY programma_materie.anno ASC, indirizzo.nome ASC, materia.nome ASC";

$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}

foreach ($resultArray as $row) { {

		$programma_id = $row['programma_id'];
		$anno = $row['anno_id'];
		$indirizzo = $row['indirizzo_nome'];
		$materia = $row['materia_nome'];
		$update = $row['ultimo_agg'];
		$autore = $row['utente_cognome'] . " " . $row['utente_nome'];

		$phpdate = strtotime($update);
		$update = date('d-m-Y', $phpdate) . " alle ore " . date('H:i:s', $phpdate);

		$data .= '<tr>
		<td align="center">' . $anno . '</td>
		<td align="center">' . $indirizzo . '</td>
		<td align="center">' . $materia . '</td>
		';
		$data .= '
		<td class="text-center">';

		if ((haRuolo('dirigente')) || (haRuolo('segreteria-didattica'))) {
			$data .= '
  			<button onclick="programmaGetDetails(' . $programma_id . ')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica la materia"><span class="glyphicon glyphicon-pencil"></button>
			<button onclick="programmaDelete(' . $programma_id . ', \'' . $materia . '\')" class="btn btn-danger btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Cancella la materia"><span class="glyphicon glyphicon-trash"></button>
			<button onclick="programmaPrint(' . $programma_id . ')" class="btn btn-primary btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Genera PDF con il programma della materia"><span class="glyphicon glyphicon-print"></button>
		';
		} else
			if (haRuolo('docente')) {
				if (getSettingsValue('programmiMaterie', 'visibile_docenti', false)) {
					$data .= '
						<button onclick="programmaGetDetails(' . $programma_id . ')" class="btn btn-info btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Visualizza il dettaglio della materia"><span class="glyphicon glyphicon-search"></button>
						';
				}
			}
		$data .= '
		</td>
		<td align="center">' . $update . '</td>
		<td align="center">' . $autore . '</td>
		</tr>';
	}
}

$data .= '</table></div>';

echo $data;
?>