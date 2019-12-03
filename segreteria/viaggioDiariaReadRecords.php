<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

$data = '';

// Design initial table header
$data .= '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
						<thead>
                        <tr>
							<th>Docente</th>
							<th>Destinazione</th>
							<th class="text-center">Data</th>
							<th class="text-center">Diaria</th>
							<th class="text-center">Pagato il</th>
							<th class="text-center">Ore</th>
							<th class="text-center"></th>
						</tr>
                        </thead>
                        <tbody>';

$query = "	SELECT
				viaggio.id AS viaggio_id,
				viaggio.destinazione AS viaggio_destinazione,
				viaggio.data_partenza AS viaggio_data_partenza,
				viaggio.stato AS viaggio_stato,

				fuis_viaggio_diaria.id AS fuis_viaggio_diaria_id,
				fuis_viaggio_diaria.importo AS fuis_viaggio_diaria_importo,
				fuis_viaggio_diaria.liquidato AS fuis_viaggio_diaria_liquidato,
				fuis_viaggio_diaria.data_richiesta_liquidazione AS fuis_viaggio_diaria_data_richiesta_liquidazione,

				viaggio_ore_recuperate.id AS viaggio_ore_recuperate_id,
				viaggio_ore_recuperate.ore AS viaggio_ore_recuperate_ore,

				docente.id AS docente_id,
				docente.nome AS docente_nome,
				docente.cognome AS docente_cognome
					
			FROM  viaggio viaggio
			LEFT JOIN  fuis_viaggio_diaria fuis_viaggio_diaria
			ON fuis_viaggio_diaria.viaggio_id = viaggio.id
			LEFT JOIN  viaggio_ore_recuperate viaggio_ore_recuperate
			ON viaggio_ore_recuperate.viaggio_id = viaggio.id
			INNER JOIN docente docente
			ON viaggio.docente_id = docente.id
			WHERE viaggio.anno_scolastico_id = $__anno_scolastico_corrente_id
			AND viaggio.stato = 'chiuso'
			ORDER BY
				fuis_viaggio_diaria.data_richiesta_liquidazione DESC,
				viaggio.data_partenza DESC,
				docente.cognome ASC,
				docente.nome ASC;";

$resultArray = dbGetAll($query);
foreach($resultArray as $diaria) {
	$id = $diaria['fuis_viaggio_diaria_id'];
	$docenteCognomeNome = $diaria['docente_cognome'].' '.$diaria['docente_nome'];
	$destinazione = $diaria['viaggio_destinazione'];
	$dataPartenza = strftime("%d/%m/%Y", strtotime($diaria['viaggio_data_partenza']));
	$importo = $diaria['fuis_viaggio_diaria_importo'];
	$viaggio_ore_recuperate_ore = $diaria['viaggio_ore_recuperate_ore'];
	$data .= '<tr>
				<td>'.$docenteCognomeNome.'</td>
				<td>'.$destinazione.'</td>
				<td class="text-center">'.$dataPartenza.'</td>
				<td class="text-right">'.formatImporto($importo).'</td>';
	$dataLiquidazione = $diaria['fuis_viaggio_diaria_data_richiesta_liquidazione'];
	$data .='<td class="text-center">'.formatDataLiquidazione($dataLiquidazione, $importo).'</td>';
	$data .='<td class="text-center">'.$viaggio_ore_recuperate_ore.'</td>';
	$data .='<td class="text-center">
		<button onclick=\'viaggioDiariaGetDetails("'.$diaria['fuis_viaggio_diaria_id'].'", "'.$diaria['fuis_viaggio_diaria_importo'].'", "'.$diaria['viaggio_ore_recuperate_id'].'", "'.$diaria['viaggio_ore_recuperate_ore'].'", '.$diaria['docente_id'].')\' class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
		</td>';
	$data .= '</tr>';
}
$data .= '</tbody>';
$data .= '</table>';
$data .= '</div>';

echo $data;

function formatImporto($importo) {
	if ($importo == 0) {
		return '';
	}
	return number_format($importo,2);
}

function formatDataLiquidazione($dataLiquidazione, $importo) {
	if ($importo == 0) {
		return '';
	}
	return strftime("%d/%m/%Y", strtotime($dataLiquidazione));
}

?>
