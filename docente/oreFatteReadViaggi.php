<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/__MinutiFunction.php';

$modificabile = $__config->getOre_fatte_aperto();

$docente_id = $__docente_id;
if(isset($_POST['docente_id']) && isset($_POST['docente_id']) != "") {
	$docente_id = $_POST['docente_id'];
	$modificabile = false;
}

// valori da restituire come totali
$viaggiOre = 0;
$data = '';

// Design initial table header
$data .= '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
						<thead><tr>
							<th class="col-md-10 text-left">Destinazione</th>
							<th class="col-md-1 text-center">Data</th>
							<th class="col-md-1 text-center">Ore</th>
						</tr></thead><tbody>';

$query = "	SELECT
					viaggio_ore_recuperate.id AS viaggio_ore_recuperate_id,
					viaggio_ore_recuperate.ore AS viaggio_ore_recuperate_ore,
					viaggio.destinazione AS viaggio_destinazione,
					viaggio.data_partenza AS viaggio_data_partenza

				FROM viaggio_ore_recuperate viaggio_ore_recuperate
				INNER JOIN viaggio viaggio
				ON viaggio_ore_recuperate.viaggio_id = viaggio.id
				WHERE viaggio.anno_scolastico_id = $__anno_scolastico_corrente_id
				AND viaggio.docente_id = $docente_id
				ORDER BY
					viaggio.data_partenza DESC
				"
				;

foreach(dbGetAll($query) as $viaggio) {
	$ore_con_minuti = oreToDisplay($viaggio['viaggio_ore_recuperate_ore']);
	$data .= '<tr>
		<td>'.$viaggio['viaggio_destinazione'].'</td>
		<td class="text-center">'.strftime("%d/%m/%Y", strtotime($viaggio['viaggio_data_partenza'])).'</td>
		<td class="text-center">'.$ore_con_minuti.'</td>
		</tr>';

	// aggiorna il totale da restituire
	$viaggiOre += $viaggio['viaggio_ore_recuperate_ore'];
}

$data .= '</tbody></table></div>';

$response = compact('data', 'viaggiOre');
echo json_encode($response);
?>
