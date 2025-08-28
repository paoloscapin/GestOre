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

$studente_filtro_id = $_GET["studente_filtro_id"] ?? null;
$__studente_id = $studente_filtro_id;
$anni_filtro_id = $_GET["anni_filtro_id"];

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="text-center col-md-2">Materia</th>						
						<th class="text-center col-md-2">Docente</th>
						<th class="text-center col-md-1">Data ricezione</th>
						<th class="text-center col-md-5">Note</th>
						<th class="text-center col-md-1">Programma Carenza</th>
					</tr>
					</thead>';

$query = "	SELECT
					carenze.id AS carenza_id,
					carenze.id_studente AS carenza_id_studente,
					carenze.id_materia AS carenza_id_materia,
					carenze.id_classe AS carenza_id_classe,
					carenze.id_docente AS carenza_id_docente,
					carenze.id_anno_scolastico AS carenza_id_anno_scolastico,
					carenze.stato AS carenza_stato,
					carenze.data_inserimento AS carenza_inserimento,
					carenze.data_validazione AS carenza_validazione,
					carenze.data_invio AS carenza_invio,
					carenze.nota_docente AS nota,
					docente.cognome AS doc_cognome,
					docente.nome AS doc_nome,
					materia.nome AS materia
				FROM carenze
				INNER JOIN docente docente
				ON carenze.id_docente = docente.id
				INNER JOIN studente studente
				ON carenze.id_studente = studente.id
				INNER JOIN materia materia
				ON carenze.id_materia = materia.id
				INNER JOIN classi classi
				ON carenze.id_classe = classi.id";

if ($anni_filtro_id > 0) {
			$query .= " WHERE carenze.id_anno_scolastico=" . $anni_filtro_id . " AND studente.id='$__studente_id' AND (carenze.stato=2 OR carenze.stato=3)";
}
else {
			$query .= " WHERE studente.id='$__studente_id' AND (carenze.stato=2 OR carenze.stato=3)";
}
$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}
foreach ($resultArray as $row) {
	$materia = $row['materia'];
	$anno_carenza = $row['carenza_id_anno_scolastico'];
	// Creazione dell'oggetto DateTime
	$datf = new DateTime($row['carenza_validazione']);
	$idcarenza = $row['carenza_id'];
	// Conversione nel formato desiderato
	$data_ricezione  = $datf->format('d-m-Y H:i:s');
	$note = $row['nota'];
	$data .= '<tr>
		<td align="center">' . $materia . '</td>
		<td align="center">' . $row['doc_cognome'] . ' ' . $row['doc_nome'] . '</td>
		<td align="center">' . $data_ricezione . '</td>
		<td align="center">' . $note . '</td>
		<td align="center">
			<button onclick="carenzaPrint(\'' . $idcarenza . '\',\'' . $anno_carenza . '\')" class="btn btn-primary btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Scarica il PDF del programma della carenza"><span class="glyphicon glyphicon-print"></button>
			<button onclick="carenzaSend(\'' . $idcarenza . '\',\'' . $anno_carenza . '\')" class="btn btn-info btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Invia una copia via
			 mail"><span class="glyphicon glyphicon-envelope"></button> 
		</td>';

	$data .= '</tr>';
}

$data .= '</table></div>';

echo $data;
