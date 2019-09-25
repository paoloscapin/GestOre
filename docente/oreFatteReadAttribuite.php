<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

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
							<th class="col-md-1 text-left">Tipo</th>
							<th class="col-md-2 text-left">Nome</th>
							<th class="col-md-5 text-left">Dettaglio</th>
							<th class="col-md-1 text-center">Ore</th>
							<th class="col-md-1 text-center">Rendiconto</th>
							<th class="col-md-2 text-center"></th>
						</tr></thead><tbody>';

$query = "	SELECT
					ore_previste_attivita.id AS ore_previste_attivita_id,
					ore_previste_attivita.ore AS ore_previste_attivita_ore,
					ore_previste_attivita.dettaglio AS ore_previste_attivita_dettaglio,
					ore_previste_tipo_attivita.id AS ore_previste_tipo_attivita_id,
					ore_previste_tipo_attivita.categoria AS ore_previste_tipo_attivita_categoria,
					ore_previste_tipo_attivita.da_rendicontare AS ore_previste_tipo_attivita_da_rendicontare,
					ore_previste_tipo_attivita.nome AS ore_previste_tipo_attivita_nome
					
				FROM ore_previste_attivita ore_previste_attivita
				INNER JOIN ore_previste_tipo_attivita ore_previste_tipo_attivita
				ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
				WHERE ore_previste_attivita.anno_scolastico_id = $__anno_scolastico_corrente_id
				AND ore_previste_attivita.docente_id = $docente_id
                AND ore_previste_tipo_attivita.inserito_da_docente = false
                AND ore_previste_tipo_attivita.previsto_da_docente = false
				ORDER BY
					ore_previste_tipo_attivita.categoria, ore_previste_tipo_attivita.nome ASC
				"
				;
				
if (!$result = mysqli_query($con, $query)) {
    exit(mysqli_error($con));
}

// if query results contains rows then fetch those rows
if(mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) {
        //			console_log_data("docente=", $row);
        $data .= '<tr>
                <td>'.$row['ore_previste_tipo_attivita_categoria'].'</td>
                <td>'.$row['ore_previste_tipo_attivita_nome'].'</td>
                <td>'.$row['ore_previste_attivita_dettaglio'].'</td>
                <td class="text-center">'.$row['ore_previste_attivita_ore'].'</td>
                ';
        
        $data .='
                    <td class="text-center">
                    ';
        if ($row['ore_previste_tipo_attivita_da_rendicontare']) {
            if ($modificabile) {
                $data .='
				        <button onclick="oreFatteGetRegistroAttivita('.$row['ore_previste_attivita_id'].', '.$docente_id.')" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-list-alt"></button>
        			';
            }
        }
        $data .='
                </td>
                <td></td>
                </tr>';
    }
} else {
    // records now found
    $data .= '<tr><td colspan="6">Nessuna attività attribuita</td></tr>';
}

$data .= '</tbody></table></div>';

echo $data;
	

?>
