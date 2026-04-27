<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

$programma_id = $_GET["programma_id"];

function canEditProgrammaMateriaModulo(int $programmaId): bool
{
	global $__docente_id;
	$is_docente_effettivo = impersonaRuolo('docente') && intval($__docente_id ?? 0) > 0;

	if ($is_docente_effettivo) {
		if (!getSettingsValue('programmiMaterie', 'visibile_docenti', false)) {
			return false;
		}

		if (getSettingsValue('programmiMaterie', 'docente_puo_modificare', false)) {
			return true;
		}

		if (!getSettingsValue('programmiMaterie', 'coordinatore_dipartimento_puo_modificare', false)) {
			return false;
		}

		global $__anno_scolastico_corrente_id;

		$coord = dbGetFirst("SELECT id_dipartimento FROM coordinatori_dipartimento WHERE id_anno_scolastico=" . intval($__anno_scolastico_corrente_id) . " AND id_docente=" . intval($__docente_id));
		if ($coord == null) {
			return false;
		}

		$program = dbGetFirst("SELECT materia.id_dipartimento
			FROM programma_materie
			INNER JOIN materia ON materia.id = programma_materie.id_materia
			WHERE programma_materie.id=" . intval($programmaId));

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

$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="text-center col-md-1">Ordine</th>
						<th class="text-center col-md-4">Titolo</th>
						<th class="text-center col-md-2">Autore</th>
						<th class="text-center col-md-2">Ultimo aggiornamento</th>
						<th class="text-center col-md-2">Azioni</th>
					</tr>
					</thead>';

$query = "SELECT
				programma_moduli.id AS modulo_id,
				programma_moduli.id_programma AS programma_id,
				programma_moduli.id_utente AS modulo_utente,
				programma_moduli.ordine AS modulo_ordine,
				programma_moduli.nome AS modulo_nome,
				programma_moduli.updated AS modulo_updated
			FROM programma_moduli
			WHERE programma_moduli.id_programma=$programma_id
			ORDER BY programma_moduli.ordine ASC";

$can_edit_moduli = canEditProgrammaMateriaModulo((int)$programma_id);
$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}

$nmoduli = 0;
foreach ($resultArray as $row) {
	$nmoduli++;
	$idmodulo = $row['modulo_id'];
	$id_programma = $row['programma_id'];
	$ordine = $row['modulo_ordine'];
	$titolo = $row['modulo_nome'];
	$updated = $row['modulo_updated'];
	$id_autore = $row['modulo_utente'];
	$result = dbGetFirst("SELECT utente.cognome, utente.nome FROM utente WHERE utente.id = " . $id_autore);
	$autore = $result['cognome'] . " " . $result['nome'];

	$data .= '<tr>
	<td align="center">' . $ordine . '</td>
	<td align="center">' . $titolo . '</td>
	<td align="center">' . $autore . '</td>
	<td align="center">' . $updated . '</td>
	<td class="text-center">';

	if (impersonaRuolo('docente')) {
		if (getSettingsValue('programmiMaterie', 'visibile_docenti', false)) {
			if ($can_edit_moduli) {
				$data .= '
					<button onclick="moduloGetDetails(' . $idmodulo . ')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica la materia"><span class="glyphicon glyphicon-pencil"></span></button>';
			} else {
				$data .= '
					<button onclick="moduloGetDetails(' . $idmodulo . ')" class="btn btn-info btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Vedi il dettaglio del modulo"><span class="glyphicon glyphicon-search"></span></button>';
			}
		}
	} else if (haRuolo('dirigente') || haRuolo('segreteria-didattica')) {
		$data .= '
			<button onclick="moduloGetDetails(' . $idmodulo . ')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica il modulo"><span class="glyphicon glyphicon-pencil"></span></button>
			<button onclick="moduloDelete(' . $idmodulo . ',\'' . $id_programma . '\',\'' . $titolo . '\')" class="btn btn-danger btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Cancella il modulo"><span class="glyphicon glyphicon-trash"></span></button>
		';
	}

	$data .= '
	</td>
	</tr>';
}

$data .= '</table></div>';
$data .= '<input type="hidden" id="hidden_nmoduli" value=' . $nmoduli . '>';

echo $data;
?>
