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
   
$classe_filtro_id = $_GET["classi_id"];
$materia_filtro_id = $_GET["materia_id"];
$docenti_filtro_id = $_GET["docenti_id"];

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="text-center col-md-1">Classe</th>
						<th class="text-center col-md-2">Docente</th>
						<th class="text-center col-md-4">Materia</th>
						<th class="text-center col-md-1">Azioni</th>
						<th class="text-center col-md-2">Ultimo aggiornamento</th>
						<th class="text-center col-md-2">Autore ultimo aggiornamento</th>
					</tr>
					</thead>';

$query = "	SELECT
			    programmi_svolti.id AS programma_id,
				programmi_svolti.id_classe AS classe_id,
				programmi_svolti.id_docente AS docente_id,
				programmi_svolti.id_materia AS materia_id,
				programmi_svolti.id_anno_scolastico AS anno_scolastico_id,
				programmi_svolti.id_utente AS utente_id,
				programmi_svolti.updated AS ultimo_agg,
                classi.id,
                classi.classe AS classe_nome,
                materia.id,
                materia.nome AS materia_nome,
				docente.id,
				docente.nome AS docente_nome,
				docente.cognome AS docente_cognome,
				utente.id,
				utente.nome AS utente_nome,
				utente.cognome AS utente_cognome
			FROM programmi_svolti
			INNER JOIN classi classi
			ON programmi_svolti.id_classe = classi.id
			INNER JOIN materia materia
			ON programmi_svolti.id_materia = materia.id
			INNER JOIN docente docente
			ON programmi_svolti.id_docente = docente.id
			INNER JOIN utente utente
			ON programmi_svolti.id_utente = utente.id
			WHERE programmi_svolti.id_anno_scolastico=" . $__anno_scolastico_corrente_id;

if ($classe_filtro_id > 0) {
	$query .= "  AND programmi_svolti.id_classe=$classe_filtro_id ";
}
if ($materia_filtro_id > 0) {
	$query .= " AND programmi_svolti.id_materia=$materia_filtro_id ";
}
if ($docenti_filtro_id > 0) {
	$query .= " AND programmi_svolti.id_docente=$docenti_filtro_id ";
}

$query .= " ORDER BY classe_nome ASC, materia_nome ASC";

$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}

foreach ($resultArray as $row) { {

		$programma_id = $row['programma_id'];
		$classe = $row['classe_nome'];
		$docente = $row['docente_cognome'].' '.$row['docente_nome'];
		$materia = $row['materia_nome'];
		$update = $row['ultimo_agg'];
		$autore = $row['utente_cognome'] . " " . $row['utente_nome'];

		$phpdate = strtotime($update);
		$update = date('d-m-Y', $phpdate) . " alle ore " . date('H:i:s', $phpdate);

		$data .= '<tr>
		<td align="center">' . $classe . '</td>
		<td align="center">' . $docente . '</td>
		<td align="center">' . $materia . '</td>
		';
		$data .= '
		<td class="text-center">';

		if ((haRuolo('dirigente')) || (haRuolo('segreteria-didattica'))) {
			$data .= '
  			<button onclick="programmiSvoltiGetDetails(' . $programma_id . ')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica il programma"><span class="glyphicon glyphicon-pencil"></button>
			<button onclick="programmiSvoltiDelete(' . $programma_id . ', \'' . $materia . '\')" class="btn btn-danger btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Cancella il programma"><span class="glyphicon glyphicon-trash"></button>
			<button onclick="programmiSvoltiPrint(' . $programma_id . ')" class="btn btn-primary btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Genera PDF con il programma svolto"><span class="glyphicon glyphicon-print"></button>
		';
		} else
			if (haRuolo('docente')) {
				if (getSettingsValue('programmiSvolti', 'visibile_docenti', false)) {
					$data .= '
			  			<button onclick="programmiSvoltiGetDetails(' . $programma_id . ')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica il programma"><span class="glyphicon glyphicon-pencil"></button>
						<button onclick="programmiSvoltiDelete(' . $programma_id . ', \'' . $materia . '\')" class="btn btn-danger btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Cancella il programma"><span class="glyphicon glyphicon-trash"></button>
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