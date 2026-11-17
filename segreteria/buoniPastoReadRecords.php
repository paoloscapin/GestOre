<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once 'buoniPastoCreateQuery.php';

$docente_id = $_GET["docente_id"];
$mese_da = $_GET["mese_da"];
$mese_a = $_GET["mese_a"];

$direzioneOrdinamento="ASC";

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<tr>
						<th class="col-md-1 text-left">Tipo</th>
						<th class="col-md-2 text-left">Docente</th>
						<th class="col-md-2 text-left">data</th>
						<th class="col-md-1 text-left">ora</th>
						<th class="col-md-1 text-left">numero ore</th>
						<th class="col-md-5 text-left">dettaglioo</th>
					</tr>';

$query = buoniPastoCreateQuery($mese_da, $mese_a, $docente_id);

foreach(dbGetAll($query) as $row) {

	$data .= '<tr>
		<td>'.$row['tipo'].'</td>
		<td>'.$row['cognomeDocente'].' '.$row['nomeDocente'].'</td>
		<td>'.$row['data_attivita'].'</td>
		<td>'.$row['ora'].'</td>
		<td>'.$row['numero_ore'].'</td>
		<td>'.$row['dettaglio'].'</td>
		</tr>';
}

$data .= '</table></div>';
echo $data;
?>
