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

$programma_id = $_GET["programma_id"];

// Design initial table header
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

$query = "	SELECT
					programma_minimi_moduli.id AS modulo_id,
					programma_minimi_moduli.id_programma AS programma_id,
					programma_minimi_moduli.id_utente AS modulo_utente,
					programma_minimi_moduli.ordine AS modulo_ordine,
					programma_minimi_moduli.nome AS modulo_nome,
					programma_minimi_moduli.updated AS modulo_updated
				FROM programma_minimi_moduli
				WHERE programma_minimi_moduli.id_programma=$programma_id ";

$query .= "ORDER BY programma_minimi_moduli.ordine ASC";

$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}
$nmoduli = 0;
foreach ($resultArray as $row) { {
		$nmoduli++;
		$idmodulo = $row['modulo_id'];
		$id_programma = $row['programma_id'];
		$ordine = $row['modulo_ordine'];
		$titolo = $row['modulo_nome'];
		$updated = $row['modulo_updated'];
		$id_autore = $row['modulo_utente'];
		$query = "SELECT utente.cognome,utente.nome from utente WHERE utente.id = " . $id_autore;
		$result = dbGetFirst($query);
		$autore = $result['cognome'] . " " . $result['nome'];
		//$autore="massimo";
		$data .= '<tr>
		<td align="center">' . $ordine . '</td>
		<td align="center">' . $titolo . '</td>
		<td align="center">' . $autore . '</td>
		<td align="center">' . $updated . '</td>
		';
		$data .= '
		<td class="text-center">';

		if ((haRuolo('dirigente')) || (haRuolo('segreteria-didattica'))) {
			$data .= '
			<button onclick="moduloGetDetails(' . $idmodulo . ')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica il modulo"><span class="glyphicon glyphicon-pencil"></button>
			<button onclick="moduloDelete(' . $idmodulo . ',\'' . $id_programma . '\',\'' . $titolo . '\')" class="btn btn-danger btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Cancella il modulo"><span class="glyphicon glyphicon-trash"></button>
			';
		} else
			if (haRuolo('docente')) {
				if (getSettingsValue('programmiMaterie', 'visibile_docenti', false)) {
					if (getSettingsValue('programmiMaterie', 'docente_puo_modificare', false)) {
						$data .= '
  						<button onclick="moduloGetDetails(' . $idmodulo . ')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica la materia"><span class="glyphicon glyphicon-pencil"></button>';
					} else {
						$data .= '
						<button onclick="moduloGetDetails(' . $idmodulo . ')" class="btn btn-info btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Vedi il dettaglio del modulo"><span class="glyphicon glyphicon-search"></button>';
					}
				}
			}

		$data .= '
		</td>
		</tr>';
	}
}

$data .= '</table></div>';
$data .= '<input type="hidden" id="hidden_nmoduli" value=' . $nmoduli . '>';

echo $data;
?>