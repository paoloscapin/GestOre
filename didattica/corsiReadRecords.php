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

$docente_id = $_GET["docente_id"];
$materia_id = $_GET["materia_id"];
$anni_filtro_id = $_GET["anni_id"];
$futuri = $_GET["futuri"];
$carenze_toggle = isset($_GET['carenze']) ? $_GET['carenze'] : 0;


// Design initial table header
$data = '<style>
  .col-md-2-custom {
    width: 20%;
  }
  .col-md-1-custom {
    width: 10%;
  }
  .col-md-1-2-custom {
    width: 12%;
  }
  .col-md-1-5-custom {
    width: 15%;
  }
  .col-md-0-5-custom {
    width: 5%;
  }

</style>
<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="text-center col-md-2">Materia</th>
						<th class="text-center col-md-2">Docente</th>
						<th class="text-center col-md-3">Titolo</th>
						<th class="text-center col-md-1">Data inizio</th>
						<th class="text-center col-md-1">Data fine</th>
						<th class="text-center col-md-1">Studenti iscritti</th>
						<th class="text-center col-md-1">Stato</th>
						<th class="text-center col-md-2">Azioni</th>
					</tr>
					</thead>';

$query = "
SELECT c.id AS corso_id,
       c.id_materia AS materia_id,
       c.id_docente AS doc_id,
       c.titolo AS titolo,
       c.id_anno_scolastico AS anno_id,
	   c.carenza AS carenza,
       m.nome AS materia_nome,
       MIN(cd.data) AS data_inizio,
       MAX(cd.data) AS data_fine,
       CASE
           WHEN MIN(cd.data) IS NULL THEN 3
           WHEN MIN(cd.data) > CURDATE() THEN 0
           WHEN MAX(cd.data) < CURDATE() THEN 2
           ELSE 1
       END AS stato
FROM corso c
INNER JOIN docente d 
       ON d.id = c.id_docente
INNER JOIN materia m 
       ON m.id = c.id_materia
LEFT JOIN corso_date cd 
       ON cd.id_corso = c.id
WHERE c.id_anno_scolastico = '$anni_filtro_id' AND c.carenza = '$carenze_toggle'
";

// filtro opzionale materia
if ($materia_id > 0) {
	$query .= " AND c.id_materia = $materia_id";
}

$query .= "
GROUP BY c.id, c.id_materia, c.id_docente, c.id_anno_scolastico, m.nome
HAVING ($futuri = 0 OR MAX(cd.data) >= CURDATE())
ORDER BY m.nome ASC
";


$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}

foreach ($resultArray as $row) {
	$idcorso = $row['corso_id'];

	$query2 = "SELECT nome from materia WHERE id = " . $row['materia_id'];
	$materia = dbGetValue($query2);

	$query3 = "SELECT nome,cognome from docente WHERE id = " . $row['doc_id'];
	$docente = dbGetFirst($query3);
	$nome_docente = $docente['cognome'] . ' ' . $docente['nome'];

	$titolo = $row['titolo'];

	$query2 = "SELECT data FROM corso_date WHERE id_corso = $idcorso ORDER BY data ASC";
	$data_inizio = dbGetValue($query2);
	$query2 = "SELECT data FROM corso_date WHERE id_corso = $idcorso ORDER BY data DESC";
	$data_fine = dbGetValue($query2);

	$query2 = "SELECT COUNT(id) FROM corso_iscritti WHERE id_corso = $idcorso";
	$studenti_iscritti = dbGetValue($query2);

	$stato = $row['stato'];

	$statoMarker = '';
	if ($stato == 0) {
		$statoMarker .= '<span class="label label-default">Non ancora iniziato</span>';
	} else {
		if ($stato == 1) {
			$statoMarker .= '<span class="label label-warning">In svolgimento</span>';
		} else {
			if ($stato == 2) {
				$statoMarker .= '<span class="label label-success">Terminato</span>';
			} else {
				if ($stato == 3) {
					$statoMarker .= '<span class="label label-danger">Nessuna data</span>';
				}
			}
		}
	}

	$data .= '<td align="center">' . $materia . '</td>
		<td align="center">' . $nome_docente . '</td>
		<td align="center">' . $titolo . '</td>
		<td align="center">' . $data_inizio . '</td>
		<td align="center">' . $data_fine . '</td>
		<td align="center">' . $studenti_iscritti . '</td>
		<td align="center">' . $statoMarker . '</td>
		';
	$data .= '
		<td class="text-center">';

	if ((haRuolo('dirigente')) || (haRuolo('segreteria-didattica'))) {
		$data .= '
			<button onclick="corsiGetDetails(\'' . $idcorso . '\')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica il corso"><span class="glyphicon glyphicon-pencil"></button>
			<button onclick="corsiDelete(\'' . $idcorso . '\',\'' . $materia . '\',\'' . $nome_docente . '\',\'' . $studenti_iscritti . '\',\'' . $stato . '\')" class="btn btn-danger btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Cancella il corso"><span class="glyphicon glyphicon-trash"></button>';
	}


	$data .= '</td></tr>';
}

echo $data;
