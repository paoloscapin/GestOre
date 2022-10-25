<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/__Minuti.php';

$modificabile = $__config->getOre_fatte_aperto();

$docente_id = $__docente_id;
if(isset($_POST['docente_id']) && isset($_POST['docente_id']) != "") {
	$docente_id = $_POST['docente_id'];
	$modificabile = false;
}

$data = '';

// Design initial table header
$data .= '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
						<thead><tr>
							<th class="col-md-9 text-left">Gruppo</th>
							<th class="col-md-1 text-center">Data</th>
							<th class="col-md-1 text-center">Ore</th>
							<th class="col-md-1 text-center">Clil</th>
						</tr></thead><tbody>';

$query = "SELECT
            gruppo.nome AS gruppo_nome,
            gruppo.clil AS gruppo_clil,
            gruppo_incontro.data AS gruppo_incontro_data,
            gruppo_incontro_partecipazione.ore AS gruppo_incontro_partecipazione_ore

            FROM gruppo_incontro_partecipazione
            INNER JOIN docente ON gruppo_incontro_partecipazione.docente_id = docente.id
            INNER JOIN gruppo_incontro ON gruppo_incontro_partecipazione.gruppo_incontro_id = gruppo_incontro.id
            INNER JOIN gruppo ON gruppo_incontro.gruppo_id = gruppo.id
            WHERE gruppo_incontro_partecipazione.docente_id = $__docente_id
            AND gruppo_incontro_partecipazione.ha_partecipato = true
            AND gruppo.anno_scolastico_id = $__anno_scolastico_corrente_id
            AND gruppo_incontro.effettuato = true
            AND gruppo.dipartimento = false
            ORDER BY gruppo_incontro.data DESC;
        ";

foreach(dbGetAll($query) as $row) {
    $ore_con_minuti = oreToDisplay($row['gruppo_incontro_partecipazione_ore']);
	$clilMarker = '';
	if ($row['gruppo_clil']) {
		$clilMarker = '<span class="label label-danger">clil</span>';
	}
    $data .= '<tr>
        <td>'.$row['gruppo_nome'].'</td>
        <td class="text-center">'.strftime("%d/%m/%Y", strtotime($row['gruppo_incontro_data'])).'</td>
        <td class="text-center">'.$ore_con_minuti.'</td>
		<td>'.$clilMarker.'</td>
        </tr>
        ';

}

$data .= '</tbody></table></div>';

echo $data;
	

?>
