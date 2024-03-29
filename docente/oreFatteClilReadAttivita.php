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

function writeOre($attuali, $originali) {
	// se non ci sono gli originali, scrive solo gli attuali
	if ($originali == null || $originali == 0) {
		return oreToDisplay($attuali);
	}
	// altrimenti gli originali cancellati e gli attuali in rosso
	return '<s style="text-decoration-style: double;"> '.oreToDisplay($originali).' </s>&ensp;<span class="text-danger"><strong> '.oreToDisplay($attuali).' </strong></span>';
}

// default opera sul docente connesso e agisce come docente
$docente_id = $__docente_id;
$operatore = 'docente';

$modificabile = $__config->getOre_fatte_aperto();

if(isset($_POST['operatore']) && $_POST['operatore'] == 'dirigente') {
	// se vuoi fare il dirigente, devi essere dirigente
	ruoloRichiesto('dirigente');
	// agisci quindi come dirigente
	$operatore = 'dirigente';
	// il dirigente può sempre fare modifiche
	$modificabile = true;
	// devi leggere il timestamp dell'ultimo controllo effettuato
	$ultimo_controllo = $_POST['ultimo_controllo'];
}
debug('modificabile='.$modificabile);

$contestataMarker = '<span class=\'label label-danger\'>contestata</span>';
$accettataMarker = '';

$data = '';

// Design initial table header
$data .= '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
						<thead><tr>
							<th class="col-md-1 text-left">Tipo</th>
							<th class="col-md-7 text-left">Dettaglio</th>
							<th class="col-md-1 text-center">Data</th>
							<th class="col-md-1 text-center">Ore</th>
							<th class="col-md-1 text-center">Registro</th>
							<th class="col-md-1 text-center"></th>
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
                    ore_fatte_attivita_clil_commento.commento AS ore_fatte_attivita_commento_commento,
                    ore_fatte_attivita_clil_commento.ore_originali AS ore_fatte_attivita_ore_originali

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

foreach(dbGetAll($query) as $row) {
	$strikeOn = '';
	$strikeOff = '';
	if ($row['ore_fatte_attivita_contestata'] == 1) {
		$strikeOn = '<strike>';
		$strikeOff = '</strike>';
	}
	    
	// controlla se aggiornata dall'ultima modifica (solo per il dirigente)
	$marker = '';
	if ($operatore == 'dirigente') {
		if ($row['ore_fatte_attivita_ultima_modifica'] > $ultimo_controllo) {
			$marker = '&nbsp;<span class="label label-danger glyphicon glyphicon-star" style="color:yellow"> '. '' .'</span>&ensp;';
		}
	}

	$categoria = ($row['ore_fatte_attivita_con_studenti'])? 'con studenti' : 'funzionali';
	$data .= '<tr>
		<td>'.$strikeOn.$categoria.$strikeOff.'</td>
		<td>'.$marker.$strikeOn.$row['ore_fatte_attivita_dettaglio'].$strikeOff;

	if ($row['ore_fatte_attivita_commento_commento'] != null && !empty(trim($row['ore_fatte_attivita_commento_commento'], " "))) {
		$data .='</br><span class="text-danger"><strong>'.$row['ore_fatte_attivita_commento_commento'].'</strong></span>';
	}
	$data .='</td>';

	$ore_con_minuti = oreToDisplay($row['ore_fatte_attivita_ore']);

	$data .= '<td class="text-center">'.$strikeOn.strftime("%d/%m/%Y", strtotime($row['ore_fatte_attivita_data'])).$strikeOff.'</td>';
	$data .= '<td class="text-center">'.writeOre($row['ore_fatte_attivita_ore'], $row['ore_fatte_attivita_ore_originali']).'</td>';

	$data .='<td class="text-center"><button onclick="oreFatteClilGetRegistroAttivita('.$row['ore_fatte_attivita_id'].', '.$row['registro_attivita_id'].')" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-list-alt"></button></td>';

	$marker = ($row['ore_fatte_attivita_contestata'] == 1)? $contestataMarker : $accettataMarker;
	// $data .= '<td class="col-md-1 text-center">'.$marker.'</td>';

	$data .='<td class="text-center">';
	if ($modificabile) {
		$data .='
			<button onclick="oreFatteClilGetAttivita('.$row['ore_fatte_attivita_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
			<button onclick="oreFatteClilDeleteAttivita('.$row['ore_fatte_attivita_id'].')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
		';
	} else {
		if ($row['ore_fatte_attivita_contestata'] == 1) {
			$data .='<button onclick="oreFatteRipristrinaAttivita('.$row['ore_fatte_attivita_id'].', \''.str2js($row['ore_fatte_attivita_dettaglio']).'\','.$ore_con_minuti.', \''.str2js($row['ore_fatte_attivita_commento_commento']).'\', \'clil\')" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-ok"></span> Ripristina</button>';
		} else {
			$data .='<button onclick="oreFatteControllaAttivita('.$row['ore_fatte_attivita_id'].', \''.str2js($row['ore_fatte_attivita_dettaglio']).'\','.$ore_con_minuti.', \'clil\')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-remove"></span> Contesta</button>';
		}
	}
	$data .='</td></tr>';
}

$data .= '</tbody></table></div>';

echo $data;

?>
