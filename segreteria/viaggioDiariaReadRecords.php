<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

$data = '';

// Design initial table header
$data .= '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
						<thead>
                        <tr>
							<th>Docente</th>
							<th>Destinazione</th>
							<th class="text-center">Data</th>
							<th class="text-center">Importo</th>
							<th class="text-center">Pagato il</th>
						</tr>
                        </thead>
                        <tbody>';

$query = "	SELECT
					fuis_viaggio_diaria.id AS fuis_viaggio_diaria_id,
					fuis_viaggio_diaria.importo AS fuis_viaggio_diaria_importo,
					fuis_viaggio_diaria.liquidato AS fuis_viaggio_diaria_liquidato,
					fuis_viaggio_diaria.data_richiesta_liquidazione AS fuis_viaggio_diaria_data_richiesta_liquidazione,
                    viaggio.id AS viaggio_id,
                    viaggio.destinazione AS viaggio_destinazione,
                    viaggio.data_partenza AS viaggio_data_partenza,
                    viaggio.stato AS viaggio_stato,
					docente.id AS docente_id,
					docente.nome AS docente_nome,
					docente.cognome AS docente_cognome
					
				FROM fuis_viaggio_diaria fuis_viaggio_diaria
				INNER JOIN viaggio viaggio
				ON fuis_viaggio_diaria.viaggio_id = viaggio.id
				INNER JOIN docente docente
				ON viaggio.docente_id = docente.id
				WHERE viaggio.anno_scolastico_id = $__anno_scolastico_corrente_id
				ORDER BY
					fuis_viaggio_diaria.data_richiesta_liquidazione ASC,
					viaggio.data_partenza ASC,
					docente.cognome ASC,
					docente.nome ASC
				"
				;
				debug($query);
				$resultArray = dbGetAll($query);
				foreach($resultArray as $diaria) {
				    $id = $diaria['fuis_viaggio_diaria_id'];
				    $docenteCognomeNome = $diaria['docente_cognome'].' '.$diaria['docente_nome'];
				    $destinazione = $diaria['viaggio_destinazione'];
				    $dataPartenza = strftime("%d/%m/%Y", strtotime($diaria['viaggio_data_partenza']));
				    $importo = $diaria['fuis_viaggio_diaria_importo'];
				    $data .= '<tr>
                    			<td>'.$docenteCognomeNome.'</td>
                    			<td>'.$destinazione.'</td>
                    			<td class="text-center">'.$dataPartenza.'</td>
                    			<td class="text-right">'.number_format($importo,2).'</td>
                                ';
				    $dataLiquidazione = $diaria['fuis_viaggio_diaria_data_richiesta_liquidazione'];
				    $data .='
                    			<td class="text-center">
                    			';
    		        $data .= strftime("%d/%m/%Y", strtotime($dataLiquidazione));
				    $data .='
                    			</td>';
				    $data .= '
                    		</tr>';
				}
				        
				$data .= '</tbody>';
				
				$data .= '</table>
';
				$data .= '</div>';
				
				echo $data;
				
?>
