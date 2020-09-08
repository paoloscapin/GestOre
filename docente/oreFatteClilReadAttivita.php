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
	$ultimo_controllo = $_POST['ultimo_controllo'];
	$modificabile = false;
}

$contestataMarker = '<span class=\'label label-danger\'>contestata</span>';
$accettataMarker = '';

$data = '';

// Design initial table header
$data .= '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
						<thead><tr>
							<th class="col-md-1 text-left">Tipo</th>
							<th class="col-md-6 text-left">Dettaglio</th>
							<th class="col-md-1 text-center">Data</th>
							<th class="col-md-1 text-center">Ore</th>
							<th class="col-md-1 text-center">Registro</th>
							<th></th>
							<th></th>
						</tr></thead><tbody>';

$query = "	SELECT
					ore_fatte_attivita_clil.id AS ore_fatte_attivita_id,
					ore_fatte_attivita_clil.ore AS ore_fatte_attivita_ore,
					ore_fatte_attivita_clil.dettaglio AS ore_fatte_attivita_dettaglio,
					ore_fatte_attivita_clil.data AS ore_fatte_attivita_data,
					ore_fatte_attivita_clil.contestata AS ore_fatte_attivita_contestata,
					ore_fatte_attivita_clil.con_studenti AS ore_fatte_attivita_con_studenti,
					ore_fatte_attivita_clil.ultima_modifica AS ore_fatte_attivita_ultima_modifica,
					registro_attivita_clil.id AS registro_attivita_id,
                    ore_fatte_attivita_clil_commento.commento AS ore_fatte_attivita_commento_commento

				FROM ore_fatte_attivita_clil ore_fatte_attivita_clil
				LEFT JOIN registro_attivita_clil registro_attivita_clil
				ON registro_attivita_clil.ore_fatte_attivita_clil_id = ore_fatte_attivita_clil.id
                LEFT JOIN ore_fatte_attivita_clil_commento
                on ore_fatte_attivita_clil_commento.ore_fatte_attivita_clil_id = ore_fatte_attivita_clil.id
				WHERE ore_fatte_attivita_clil.anno_scolastico_id = $__anno_scolastico_corrente_id
				AND ore_fatte_attivita_clil.docente_id = $docente_id
				ORDER BY
					ore_fatte_attivita_clil.data DESC,
					ore_fatte_attivita_clil.ora_inizio
				"
				;
if (!$result = mysqli_query($con, $query)) {
	exit(mysqli_error($con));
}

// if query results contains rows then fetch those rows
if(mysqli_num_rows($result) > 0) {
	while($row = mysqli_fetch_assoc($result)) {
	    $strikeOn = '';
	    $strikeOff = '';
	    if ($row['ore_fatte_attivita_contestata'] == 1) {
	        $strikeOn = '<strike>';
	        $strikeOff = '</strike>';
	    }
	    
	    // controlla se aggiornata dall'ultima modifica
	    $marker = '';
		if ((! $modificabile) && isset($ultimo_controllo)) {
	        if ($row['ore_fatte_attivita_ultima_modifica'] > $ultimo_controllo) {
	            $marker = '&ensp;<span class="label label-danger glyphicon glyphicon-star" style="color:yellow"> '. '' .'</span>';
	        }
	    }
	    
	    $categoria = ($row['ore_fatte_attivita_con_studenti'])? 'con studenti' : 'funzionali';
		$data .= '<tr>
			<td>'.$strikeOn.$categoria.$strikeOff.$marker.'</td>
			<td>'.$strikeOn.$row['ore_fatte_attivita_dettaglio'].$strikeOff;
		if ($row['ore_fatte_attivita_contestata'] == 1) {
		    $data .='</br><span class="text-danger"><strong>'.$row['ore_fatte_attivita_commento_commento'].'</strong></span>';
		}
		$data .='</td>';
		
		$ore_con_minuti = oreToDisplay($row['ore_fatte_attivita_ore']);
		$data .='
		<td class="text-center">'.$strikeOn.strftime("%d/%m/%Y", strtotime($row['ore_fatte_attivita_data'])).$strikeOff.'</td>
		<td class="text-center">'.$strikeOn.$ore_con_minuti.$strikeOff.'</td>
		';

		$data .='
			<td class="text-center">
			';
		$data .='
				<button onclick="oreFatteClilGetRegistroAttivita('.$row['ore_fatte_attivita_id'].', '.$row['registro_attivita_id'].')" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-list-alt"></button>
			';
		$data .='
			</td>';
		$marker = ($row['ore_fatte_attivita_contestata'] == 1)? $contestataMarker : $accettataMarker;
		$data .= '<td class="col-md-1 text-center">'.$marker.'</td>';
		
		$data .='
			<td class="text-center">
			';
		if ($modificabile) {
			$data .='
				<button onclick="oreFatteClilGetAttivita('.$row['ore_fatte_attivita_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
				<button onclick="oreFatteClilDeleteAttivita('.$row['ore_fatte_attivita_id'].')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
			';
		} else {
		    if ($row['ore_fatte_attivita_contestata'] == 1) {
		        $data .='
    				    <button onclick="oreFatteRipristrinaAttivita('.$row['ore_fatte_attivita_id'].', \''.str2js($row['ore_fatte_attivita_dettaglio']).'\','.$ore_con_minuti.', \''.str2js($row['ore_fatte_attivita_commento_commento']).'\', \'clil\')" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-ok"></span> Ripristina</button>
				    ';
		    } else {
		        $data .='
    				<button onclick="oreFatteControllaAttivita('.$row['ore_fatte_attivita_id'].', \''.str2js($row['ore_fatte_attivita_dettaglio']).'\','.$ore_con_minuti.', \'clil\')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-remove"></span> Contesta</button>
				';
		    }
		}
		$data .='
			</td>
			</tr>';
	}
} else {
		// records now found
		$data .= '<tr><td colspan="7">Nessuna attività inserita</td></tr>';
}
$data .= '</tbody>';

$data .= '</table>
';
$data .= '</div>';

echo $data;

?>
