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

$docente_id = 0;
if (impersonaRuolo("docente")) {
	$docente_id = $__docente_id;
} else {
	$docente_id = $_GET["docente_id"];
}
$materia_id = $_GET["materia_id"];
$anni_filtro_id = $_GET["anni_id"];
$futuri = $_GET["futuri"];
$carenze_toggle = isset($_GET['carenze']) ? $_GET['carenze'] : 0;
$in_itinere_toggle = isset($_GET['itinere']) ? $_GET['itinere'] : 0;

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
    .col-azioni   { width: 7%; }   /* più largo */
    .col-inizio   { width: 10%; }   /* un po’ più stretto */
    .col-fine     { width: 10%; }   /* un po’ più stretto */
    .col-materia  { width: 19%; }
    .col-docente  { width: 10%; }
    .col-titolo   { width: 17%; }
    .col-studenti { width: 7%; }
    .col-stato    { width: 7%; }
</style>
<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
        <tr>
            <th class="text-center col-materia">Materia</th>
            <th class="text-center col-docente">Docente</th>
            <th class="text-center col-titolo">Titolo</th>
            <th class="text-center col-inizio">Data inizio</th>
            <th class="text-center col-fine">Data fine</th>
            <th class="text-center col-studenti">Studenti iscritti</th>
            <th class="text-center col-stato">Stato</th>
            <th class="text-center col-azioni">Azioni</th>
        </tr>
					</thead>';

$query = "
SELECT c.id AS corso_id,
       c.id_materia AS materia_id,
       c.id_docente AS doc_id,
       c.titolo AS titolo,
       c.id_anno_scolastico AS anno_id,
       c.carenza AS carenza,
       c.in_itinere AS in_itinere,
       m.nome AS materia_nome,
       MIN(cd.data) AS data_inizio,
       MAX(cd.data) AS data_fine,
       SUM(CASE WHEN cd.firmato = 1 THEN 1 ELSE 0 END) AS lezioni_firmate,
       COUNT(cd.id) AS lezioni_totali,
       CASE
           WHEN COUNT(cd.id) = 0 THEN 3 -- Nessuna data
           WHEN SUM(CASE WHEN cd.firmato = 1 THEN 1 ELSE 0 END) = 0 AND MIN(cd.data) > CURDATE() THEN 0 -- Non ancora iniziato
           WHEN SUM(CASE WHEN cd.firmato = 1 THEN 1 ELSE 0 END) > 0 AND SUM(CASE WHEN cd.firmato = 1 THEN 1 ELSE 0 END) < COUNT(cd.id) THEN 1 -- Iniziato
           WHEN SUM(CASE WHEN cd.firmato = 1 THEN 1 ELSE 0 END) = COUNT(cd.id) THEN 2 -- Terminato
           ELSE 1
       END AS stato
FROM corso c
INNER JOIN docente d 
       ON d.id = c.id_docente
INNER JOIN materia m 
       ON m.id = c.id_materia
LEFT JOIN corso_date cd 
       ON cd.id_corso = c.id
WHERE c.id_anno_scolastico = '$anni_filtro_id' AND c.carenza = '$carenze_toggle' AND c.in_itinere = '$in_itinere_toggle'
";

// filtro opzionale materia
if ($materia_id > 0) {
	$query .= " AND c.id_materia = $materia_id";
}

// filtro opzionale materia
if ($docente_id > 0) {
	$query .= " AND c.id_docente = $docente_id";
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

	$query2 = "
    SELECT MIN(data) AS data_inizio, 
           MAX(data) AS data_fine
    FROM corso_date 
    WHERE id_corso = $idcorso
";
	$dateRow = dbGetFirst($query2);

	$data_inizio = $dateRow['data_inizio']
		? date("d-m-Y \\d\\a\\l\\l\\e \\o\\r\\e H:i", strtotime($dateRow['data_inizio']))
		: null;

	$data_fine = $dateRow['data_fine']
		? date("d-m-Y \\a\\l\\l\\e \\o\\r\\e H:i", strtotime($dateRow['data_fine']))
		: null;

	$query2 = "SELECT COUNT(id) FROM corso_iscritti WHERE id_corso = $idcorso";
	$studenti_iscritti = dbGetValue($query2);

    if ($row['in_itinere'] == 1) 
    {
        $stato = 4;
    }
    else 
    {
        $stato = $row['stato'];
    }

	$statoMarker = '';
	if ($stato == 0) 
    {
		$statoMarker .= '<span class="label label-default">Non ancora iniziato</span>';
	} 
    else 
    {
		if ($stato == 1) 
        {
			$statoMarker .= '<span class="label label-warning">In svolgimento</span>';
		} 
        else 
        {
			if ($stato == 2) 
            {
				$statoMarker .= '<span class="label label-success">Terminato</span>';
			} else 
            {
				if ($stato == 3) 
                {
					$statoMarker .= '<span class="label label-danger">Nessuna data</span>';
				}
			  else 
                {
				if ($stato == 4) 
                    {
					$statoMarker .= '<span class="label label-primary">Recupero in itinere</span>';
				    }
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

	if (impersonaRuolo('docente')) {
		if (getSettingsValue('config', 'corsi', false)) {
			if (getSettingsValue('corsi', 'visibile_docenti', false)) {
				if (getSettingsValue('corsi', 'docente_puo_modificare', false)) {
					$data .= '
                <button onclick="corsiGetDetails(\'' . $idcorso . '\')" 
                        class="btn btn-warning btn-xs" 
                        data-toggle="tooltip" data-trigger="hover" data-placement="top" 
                        title="Modifica il corso">
                    <span class="glyphicon glyphicon-pencil"></span>
                </button>';
				}
				$data .= '
                <button onclick="apriRegistroLezione(\'' . $idcorso . '\')" 
                        class="btn btn-primary btn-xs" 
                        data-toggle="tooltip" data-trigger="hover" data-placement="top" 
                        title="Gestisci le presenze e gli argomenti">
                    <span class="glyphicon glyphicon-user"></span>
                </button>';
				if ($row['carenza'] == 1) {
					$data .= '
                <button onclick="apriEsameModal(\'' . $idcorso . '\')" 
                        class="btn btn-success btn-xs" 
                        data-toggle="tooltip" data-trigger="hover" data-placement="top" 
                        title="Esame carenze">
                    <span class="glyphicon glyphicon-check"></span>
                </button>';
				}
			}
		}
	} else if ((haRuolo('dirigente')) || (haRuolo('segreteria-didattica'))) {
		$data .= '
        <button onclick="corsiGetDetails(\'' . $idcorso . '\')" 
                class="btn btn-warning btn-xs" 
                data-toggle="tooltip" data-trigger="hover" data-placement="top" 
                title="Modifica il corso">
            <span class="glyphicon glyphicon-pencil"></span>
        </button>
        <button onclick="corsiDelete(\'' . $idcorso . '\',\'' . $materia . '\',\'' . $nome_docente . '\',\'' . $studenti_iscritti . '\',\'' . $stato . '\')" 
                class="btn btn-danger btn-xs" 
                data-toggle="tooltip" data-trigger="hover" data-placement="top" 
                title="Cancella il corso">
            <span class="glyphicon glyphicon-trash"></span>
        </button>';
        if (($stato != 4)&&($stato != 3))
        {
            $data .= '
        <button onclick="apriRegistroLezione(\'' . $idcorso . '\')" 
                class="btn btn-primary btn-xs" 
                data-toggle="tooltip" data-trigger="hover" data-placement="top" 
                title="Gestisci le presenze e gli argomenti">
            <span class="glyphicon glyphicon-user"></span>
        </button>';
        }
        $data .= '
        <button onclick="apriEsameModal(\'' . $idcorso . '\')" 
                class="btn btn-success btn-xs" 
                data-toggle="tooltip" data-trigger="hover" data-placement="top" 
                title="Esame carenze">
            <span class="glyphicon glyphicon-check"></span>
        </button>';
	}

	$data .= '</td></tr>';
}

echo $data;
