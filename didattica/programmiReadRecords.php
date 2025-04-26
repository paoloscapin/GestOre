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
			";

if ($anno_filtro_id > 0) {
	$query .= "AND programma_materie.anno = '$anno_filtro_id' ";
}
if ($materia_filtro_id > 0) {
	$query .= "AND programma_materie.id_materia = $materia_filtro_id ";
}
if ($indirizzo_filtro_id > 0) {
	$query .= "AND programma_materie.id_indirizzo = $indirizzo_filtro_id ";
}

//$query .= "ORDER BY sportello.data $direzioneOrdinamento, docente_cognome ASC,docente_nome ASC";

$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}
foreach ($resultArray as $row) {
	{
		$programma_id = $row['programma_id'];
		$anno = $row['anno_id'];
		$indirizzo = $row['indirizzo_nome'];
		$materia = $row['materia_nome'];
		$update = $row['ultimo_agg'];
		$autore = $row['utente_cognome'] . " " . $row['utente_nome'];

		$phpdate = strtotime( $update );
		$update = date( 'd-m-Y', $phpdate ) . " alle ore " . date( 'H:i:s', $phpdate );

		$data .= '<tr>
		<td align="center">' . $anno . '</td>
		<td align="center">' . $indirizzo . '</td>
		<td align="center">' . $materia . '</td>
		';
		$data .= '
		<td class="text-center">
		<button onclick="sportelloGetDetails(' . $programma_id . ')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
		<button onclick="sportelloDelete(' . $programma_id . ', \'' . $row['materia_nome'] . '\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
		<button id="selectbutton' . $programma_id . '" onclick="sportelloSelect(' . $programma_id . ')" class="btn btn-info btn-xs"><span id="selecticon' . $programma_id . '" class="glyphicon glyphicon-remove"></button>
		</td>
		<td align="center">' . $update . '</td>
		<td align="center">' . $autore . '</td>
		</tr>';
	}
}

$data .= '</table></div>';

echo $data;
?>