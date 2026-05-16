<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// include Database connection file
require_once '../common/checkSession.php';
require_once '../common/connect.php';

$classe_filtro_raw = $_GET["classi_id"] ?? 0;
$classe_filtro_id = intval($classe_filtro_raw);
$classe_filtro_articolata_id = 0;
if (is_string($classe_filtro_raw) && strlen($classe_filtro_raw) > 1 && strtoupper(substr($classe_filtro_raw, 0, 1)) === 'A') {
	$classe_filtro_articolata_id = intval(substr($classe_filtro_raw, 1));
	$classe_filtro_id = 0;
}
$materia_filtro_id = $_GET["materia_id"];
$docenti_filtro_id = $_GET["docenti_id"];
$da_completare_filtro_id = $_GET["da_completare_id"];
$anni_filtro_id = $_GET["anni_id"];
$sollecito_lista = '';

dbExec("
	CREATE TABLE IF NOT EXISTS programmi_iniziali_classi (
		id INT NOT NULL AUTO_INCREMENT,
		id_programma_iniziale INT NOT NULL,
		id_classe INT NOT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY uniq_programma_classe (id_programma_iniziale, id_classe)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8
");

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="text-center col-md-1">Classe</th>
						<th class="text-center col-md-2">Docente</th>
						<th class="text-center col-md-3">Materia</th>
						<th class="text-center col-md-2">Azioni</th>
						<th class="text-center col-md-2">Ultimo aggiornamento</th>
						<th class="text-center col-md-2">Autore ultimo aggiornamento</th>
					</tr>
					</thead>';

$query = "	SELECT
			    programmi_iniziali.id AS programma_id,
				programmi_iniziali.id_classe AS classe_id,
				programmi_iniziali.id_docente AS docente_id,
				programmi_iniziali.id_materia AS materia_id,
				programmi_iniziali.id_anno_scolastico AS anno_scolastico_id,
				programmi_iniziali.id_utente AS utente_id,
				programmi_iniziali.updated AS ultimo_agg,
                classi.id,
                classi.classe AS classe_nome,
				(
					SELECT GROUP_CONCAT(c2.classe ORDER BY c2.classe SEPARATOR ' / ')
					FROM programmi_iniziali_classi pic2
					INNER JOIN classi c2 ON c2.id = pic2.id_classe
					WHERE pic2.id_programma_iniziale = programmi_iniziali.id
				) AS classi_collegate_nome,
                materia.id,
                materia.nome AS materia_nome,
				docente.id,
				docente.nome AS docente_nome,
				docente.cognome AS docente_cognome,
				utente.id,
				utente.nome AS utente_nome,
				utente.cognome AS utente_cognome
			FROM programmi_iniziali
			INNER JOIN classi classi
			ON programmi_iniziali.id_classe = classi.id
			INNER JOIN materia materia
			ON programmi_iniziali.id_materia = materia.id
			INNER JOIN docente docente
			ON programmi_iniziali.id_docente = docente.id
			INNER JOIN utente utente
			ON programmi_iniziali.id_utente = utente.id";

if ($anni_filtro_id > 0) {
	$query .= " WHERE programmi_iniziali.id_anno_scolastico=" . $anni_filtro_id;
}

if ($classe_filtro_articolata_id > 0) {
	$query .= "
		AND EXISTS (
			SELECT 1
			FROM programmi_iniziali_classi pic_filter
			INNER JOIN classi_articolate_classi cac_filter
				ON cac_filter.id_classe = pic_filter.id_classe
			INNER JOIN classi_articolate ca_filter
				ON ca_filter.id = cac_filter.id_articolata
			WHERE pic_filter.id_programma_iniziale = programmi_iniziali.id
			  AND ca_filter.id = " . intval($classe_filtro_articolata_id) . "
			  AND ca_filter.attiva = 1
			  AND ca_filter.id_anno_scolastico = " . intval($anni_filtro_id) . "
		)
	";
} else if ($classe_filtro_id > 0) {
	$query .= "
		AND (
			programmi_iniziali.id_classe = " . intval($classe_filtro_id) . "
			OR EXISTS (
				SELECT 1
				FROM programmi_iniziali_classi pic_filter
				WHERE pic_filter.id_programma_iniziale = programmi_iniziali.id
				  AND pic_filter.id_classe = " . intval($classe_filtro_id) . "
			)
		)
	";
}
if ($materia_filtro_id > 0) {
	$query .= " AND programmi_iniziali.id_materia=$materia_filtro_id ";
}
if ($docenti_filtro_id > 0) {
	$query .= " AND programmi_iniziali.id_docente=$docenti_filtro_id ";
}

$query .= " ORDER BY classe_nome ASC, materia_nome ASC";

$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}

foreach ($resultArray as $row) {

	$programma_id = $row['programma_id'];

	$query = "SELECT COUNT(*) from programmi_iniziali_moduli WHERE id_programma=" . $programma_id;
	$nmodulisvolti = dbGetValue($query);

	if (($da_completare_filtro_id == 0) || (($da_completare_filtro_id == 1) && ($nmodulisvolti == 0))) {
		if ($da_completare_filtro_id == 1) {
			if ($sollecito_lista == '') {
				$sollecito_lista .= $programma_id;
			} else {
				$sollecito_lista .= ',' . $programma_id;
			}
		}
		$classe = !empty($row['classi_collegate_nome'])
			? $row['classi_collegate_nome']
			: $row['classe_nome'];
		$docente = $row['docente_cognome'] . ' ' . $row['docente_nome'];
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
  			<button onclick="programmiInizialiGetDetails(' . $programma_id . ',\'false\',\'false\')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica il programma"><span class="glyphicon glyphicon-pencil"></button>
			<button onclick="programmiInizialiDelete(' . $programma_id . ', \'' . $materia . '\')" class="btn btn-danger btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Cancella il programma"><span class="glyphicon glyphicon-trash"></button>
			<button onclick="programmiInizialiPrint(' . $programma_id . ')" class="btn btn-primary btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Genera PDF con il programma svolto"><span class="glyphicon glyphicon-print"></button>
			<button onclick="programmiInizialiGetDetails(' . $programma_id . ',\'true\',\'false\')" class="btn btn-info btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Duplica il programma per un altra classe"><span class="glyphicon glyphicon-duplicate"></button>
			<button onclick="programmiInizialiGetDetails(' . $programma_id . ',\'false\',\'true\')" class="btn btn-success btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Condividi il programma con un altro docente"><span class="glyphicon glyphicon-share"></button>';
			if ($da_completare_filtro_id == 1) {
				$data .= '<button onclick="inviaSollecito(' . $programma_id . ')" class="btn btn-dark btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Invia un sollecito al docente"><span class="glyphicon glyphicon-warning-sign"></button>';
			}
		} else
			if (haRuolo('docente')) {
			if (getSettingsValue('programmiIniziali', 'visibile_docenti', false)) {
				$data .= '
			<button onclick="programmiInizialiPrint(' . $programma_id . ')" class="btn btn-primary btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Genera PDF con il programma svolto"><span class="glyphicon glyphicon-print"></button>
					';
				if (getSettingsValue('programmiIniziali', 'docente_puo_modificare', false)) {
					$data .= '
  			<button onclick="programmiInizialiGetDetails(' . $programma_id . ',\'false\',\'false\')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica il programma"><span class="glyphicon glyphicon-pencil"></button>
			<button onclick="programmiInizialiDelete(' . $programma_id . ', \'' . $materia . '\')" class="btn btn-danger btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Cancella il programma"><span class="glyphicon glyphicon-trash"></button>
			<button onclick="programmiInizialiGetDetails(' . $programma_id . ',\'true\',\'false\')" class="btn btn-info btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Duplica il programma per un altra classe"><span class="glyphicon glyphicon-duplicate"></button>
			<button onclick="programmiInizialiGetDetails(' . $programma_id . ',\'false\',\'true\')" class="btn btn-success btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Condividi il programma con un altro docente"><span class="glyphicon glyphicon-share"></button>
						';
				} else {
					$data .= '
  			<button onclick="programmiInizialiGetDetails(' . $programma_id . ',\'false\',\'false\')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Vedi il programma"><span class="glyphicon glyphicon-search"></button>';
				}
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
$data .= '<input type="hidden" id="hidden_sollecito" value="' . htmlspecialchars($sollecito_lista) . '">';

echo $data;
