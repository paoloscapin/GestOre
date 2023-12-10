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

function writeLineAttivita($row) {
    global $data;
	global $operatore;
	global $modificabile;
	global $modificabile;
	global $accettataMarker;

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

	$data .= '<tr>
		<td>'.$strikeOn.$row['ore_previste_tipo_attivita_categoria'].$strikeOff.'</td>
		<td>'.$strikeOn.$row['ore_previste_tipo_attivita_nome'].$strikeOff.'</td>
		<td>'.$marker.$strikeOn.$row['ore_fatte_attivita_dettaglio'].$strikeOff;

	if ($row['ore_fatte_attivita_commento_commento'] != null && !empty(trim($row['ore_fatte_attivita_commento_commento'], " "))) {
		$data .='</br><span class="text-danger"><strong>'.$row['ore_fatte_attivita_commento_commento'].'</strong></span>';
	}
	$data .='</td>';

	$ore_con_minuti = oreToDisplay($row['ore_fatte_attivita_ore']);

	// data e ora solo per quelle inserite da docente (dovrebbero essere tutte a questo punto)
	if ($row['ore_previste_tipo_attivita_inserito_da_docente']) {
		$data .= '<td class="text-center">'.$strikeOn.strftime("%d/%m/%Y", strtotime($row['ore_fatte_attivita_data'])).$strikeOff.'</td>';
		$data .= '<td class="text-center">'.writeOre($row['ore_fatte_attivita_ore'], $row['ore_fatte_attivita_ore_originali']).'</td>';
	} else {
		$data .= '<td class="text-center">'.$strikeOn.strftime("%d/%m/%Y", strtotime($row['ore_fatte_attivita_data'])).$strikeOff.'</td>';
		$data .= '<td class="text-center">'.writeOre($row['ore_fatte_attivita_ore'], $row['ore_fatte_attivita_ore_originali']).'</td>';
//		$data .='<td class="text-center">'.'</td><td class="text-center">'.writeOre($row['ore_fatte_attivita_ore'], $row['ore_fatte_attivita_ore_originali']).'</td>';
	}

	$data .='<td class="text-center">';
	// registro per quelle inserite da docente
	if ($row['ore_previste_tipo_attivita_inserito_da_docente']) {
		$data .='<button onclick="oreFatteGetRegistroAttivita('.$row['ore_fatte_attivita_id'].', '.$row['registro_attivita_id'].')" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-list-alt"></button>';
	}
	$data .='</td>';

	$marker = ($row['ore_fatte_attivita_contestata'] == 1)? $contestataMarker : $accettataMarker;
//	$data .= '<td class="col-md-1 text-center">'.$marker.'</td>';

	$data .='<td class="text-center">';

	if ($row['ore_previste_tipo_attivita_inserito_da_docente']) {
		if ($modificabile) {
			$data .='<button onclick="oreFatteGetAttivita('.$row['ore_fatte_attivita_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
				<button onclick="oreFatteDeleteAttivita('.$row['ore_fatte_attivita_id'].')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>';
		} else {
			if ($row['ore_fatte_attivita_contestata'] == 1) {
				$data .='<button onclick="oreFatteRipristrinaAttivita('.$row['ore_fatte_attivita_id'].', \''.str2js($row['ore_fatte_attivita_dettaglio']).'\','.$ore_con_minuti.', \''.str2js($row['ore_fatte_attivita_commento_commento']).'\', \'\')" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-ok"></span> Ripristina</button>';
			} else {
				$data .='<button onclick="oreFatteControllaAttivita('.$row['ore_fatte_attivita_id'].', \''.str2js($row['ore_fatte_attivita_dettaglio']).'\','.$ore_con_minuti.', \'\')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-remove"></span> Contesta</button>';
			}
		}
	} else {
		// non dovrebbe accadere mai, a meno che non sia stato tolto quest'anno il flag inserito da docente per questo tipo di attivita: in quel caso posso solo cancellarlo
		$data .='<button onclick="oreFatteDeleteAttivita('.$row['ore_fatte_attivita_id'].')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>';
	}
	$data .='</td></tr>';

}

function creaQuery($clil, $orientamento) {
	global $docente_id ;
	global $__anno_scolastico_corrente_id;

	return "	SELECT
			ore_fatte_attivita.id AS ore_fatte_attivita_id,
			ore_fatte_attivita.ore AS ore_fatte_attivita_ore,
			ore_fatte_attivita.dettaglio AS ore_fatte_attivita_dettaglio,
			ore_fatte_attivita.data AS ore_fatte_attivita_data,
			ore_fatte_attivita.contestata AS ore_fatte_attivita_contestata,
			ore_fatte_attivita.ultima_modifica AS ore_fatte_attivita_ultima_modifica,
			ore_previste_tipo_attivita.id AS ore_previste_tipo_attivita_id,
			ore_previste_tipo_attivita.categoria AS ore_previste_tipo_attivita_categoria,
			ore_previste_tipo_attivita.inserito_da_docente AS ore_previste_tipo_attivita_inserito_da_docente,
			ore_previste_tipo_attivita.nome AS ore_previste_tipo_attivita_nome,
			ore_previste_tipo_attivita.da_rendicontare AS ore_previste_tipo_attivita_da_rendicontare,
			registro_attivita.id AS registro_attivita_id,
			ore_fatte_attivita_commento.commento AS ore_fatte_attivita_commento_commento,
			ore_fatte_attivita_commento.ore_originali AS ore_fatte_attivita_ore_originali

		FROM ore_fatte_attivita ore_fatte_attivita
		INNER JOIN ore_previste_tipo_attivita ore_previste_tipo_attivita
		ON ore_fatte_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
		LEFT JOIN registro_attivita registro_attivita
		ON registro_attivita.ore_fatte_attivita_id = ore_fatte_attivita.id
		LEFT JOIN ore_fatte_attivita_commento
		on ore_fatte_attivita_commento.ore_fatte_attivita_id = ore_fatte_attivita.id
		WHERE ore_fatte_attivita.anno_scolastico_id = $__anno_scolastico_corrente_id
		AND ore_fatte_attivita.docente_id = $docente_id
		AND ore_previste_tipo_attivita.clil = $clil
		AND ore_previste_tipo_attivita.orientamento = $orientamento
		ORDER BY
			ore_fatte_attivita.data DESC,
			ore_fatte_attivita.ora_inizio";
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
							<th class="col-md-2 text-left">Nome</th>
							<th class="col-md-5 text-left">Dettaglio</th>
							<th class="col-md-1 text-center">Data</th>
							<th class="col-md-1 text-center">Ore</th>
							<th class="col-md-1 text-center">Registro</th>
							<th class="col-md-1 text-center"></th>
						</tr></thead><tbody>';

$query = creaQuery(0,0);
foreach(dbGetAll($query) as $row) {
	writeLineAttivita($row);
}

$query = creaQuery(1,0);
$lista = dbGetAll($query);
if (!empty($lista)) {
	$data .= '<thead><tr><th  colspan="7" class="col-md-12 text-center btn-lightblue4">CLIL</th></tr></thead><tbody>';
	foreach($lista as $row) {
		writeLineAttivita($row);
	}
}

$query = creaQuery(0,1);
$lista = dbGetAll($query);
if (!empty($lista)) {
	$data .= '<thead><tr><th  colspan="7" class="col-md-12 text-center btn-salmon">Orientamento</th></tr></thead><tbody>';
	foreach($lista as $row) {
		writeLineAttivita($row);
	}
}
												
$data .= '</tbody></table></div>';

echo $data;

?>
