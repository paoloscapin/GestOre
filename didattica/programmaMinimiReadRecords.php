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

$anno_filtro_id = $_GET["anno_id"];
$materia_filtro_id = $_GET["materia_id"];
$indirizzo_filtro_id = $_GET["indirizzo_id"];
$is_docente_effettivo = impersonaRuolo('docente');

function canEditProgrammaMinimiRecord(int $programmaId): bool
{
	global $__docente_id;
	$is_docente_effettivo = impersonaRuolo('docente') && intval($__docente_id ?? 0) > 0;

	if ($is_docente_effettivo) {
		if (!getSettingsValue('programmiMinimi', 'visibile_docenti', false)) {
			return false;
		}

		if (getSettingsValue('programmiMinimi', 'docente_puo_modificare', false)) {
			return true;
		}

		if (!getSettingsValue('programmiMinimi', 'coordinatore_dipartimento_puo_modificare', false)) {
			return false;
		}

		global $__anno_scolastico_corrente_id;

		$coord = dbGetFirst("SELECT id_dipartimento FROM coordinatori_dipartimento WHERE id_anno_scolastico=" . intval($__anno_scolastico_corrente_id) . " AND id_docente=" . intval($__docente_id));
		if ($coord == null) {
			return false;
		}

		$program = dbGetFirst("SELECT materia.id_dipartimento
			FROM programma_minimi
			INNER JOIN materia ON materia.id = programma_minimi.id_materia
			WHERE programma_minimi.id=" . intval($programmaId));

		if ($program == null) {
			return false;
		}

		return intval($program['id_dipartimento']) === intval($coord['id_dipartimento']);
	}

	if (haRuolo('dirigente') || haRuolo('segreteria-didattica')) {
		return true;
	}

	return false;
}

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
				programma_minimi.id AS programma_id,
				programma_minimi.anno AS anno_id,
				programma_minimi.id_indirizzo AS indirizzo_id,
				programma_minimi.id_materia AS materia_id,
				programma_minimi.updated AS ultimo_agg,
                indirizzo.id,
                indirizzo.nome AS indirizzo_nome,
                materia.id,
                materia.nome AS materia_nome,
				utente.id,
				utente.nome AS utente_nome,
				utente.cognome AS utente_cognome
			FROM programma_minimi
			INNER JOIN indirizzo indirizzo
			ON programma_minimi.id_indirizzo = indirizzo.id
			INNER JOIN materia materia
			ON programma_minimi.id_materia = materia.id
			INNER JOIN utente utente
			ON programma_minimi.id_utente = utente.id
			WHERE true ";

if ($anno_filtro_id > 0) {
	$query .= "AND programma_minimi.anno = '$anno_filtro_id' ";
}
if ($materia_filtro_id > 0) {
	$query .= "AND programma_minimi.id_materia = $materia_filtro_id ";
}
if ($indirizzo_filtro_id > 0) {
	$query .= "AND programma_minimi.id_indirizzo = $indirizzo_filtro_id ";
}

$query .= "ORDER BY programma_minimi.anno ASC, indirizzo.nome ASC, materia.nome ASC";

$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}

foreach ($resultArray as $row) { {

		$programma_id = $row['programma_id'];
		$can_edit_programma = canEditProgrammaMinimiRecord((int)$programma_id);
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

		if ($is_docente_effettivo) {
			if (getSettingsValue('programmiMinimi', 'visibile_docenti', false)) {
				if ($can_edit_programma) {
					$data .= '
						<button onclick="programmaGetDetails(' . $programma_id . ')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica il dettaglio della materia"><span class="glyphicon glyphicon-pencil"></button>
						<button onclick="programmaPrint(' . $programma_id . ')" class="btn btn-primary btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Genera PDF con il programma della materia"><span class="glyphicon glyphicon-print"></button>
						';
				} else {
					$data .= '
						<button onclick="programmaGetDetails(' . $programma_id . ')" class="btn btn-info btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Visualizza il dettaglio della materia"><span class="glyphicon glyphicon-search"></button>
						<button onclick="programmaPrint(' . $programma_id . ')" class="btn btn-primary btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Genera PDF con il programma della materia"><span class="glyphicon glyphicon-print"></button>
						';
				}
			}
		} else if ((haRuolo('dirigente')) || (haRuolo('segreteria-didattica'))) {
			$data .= '
			<button onclick="programmaGetDetails(' . $programma_id . ')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica la materia"><span class="glyphicon glyphicon-pencil"></button>
			<button onclick="programmaDelete(' . $programma_id . ', \'' . $materia . '\')" class="btn btn-danger btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Cancella la materia"><span class="glyphicon glyphicon-trash"></button>
			<button onclick="programmaPrint(' . $programma_id . ')" class="btn btn-primary btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Genera PDF con il programma della materia"><span class="glyphicon glyphicon-print"></button>
		';
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
