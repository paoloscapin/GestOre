<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/__Minuti.php';
require_once '../common/importi_load.php';

function writeGiorni($attuali, $originali) {
	// se non ci sono gli originali, scrive solo gli attuali
	if ($originali == null || $originali == 0) {
		return $attuali;
	}
	// altrimenti gli originali cancellati e gli attuali in rosso
	return '<s style="text-decoration-style: double;"> '.$originali.' </s>&ensp;<span class="text-danger"><strong> '.$attuali.' </strong></span>';
}

function writeOre($attuali, $originali) {
	// se non ci sono gli originali, scrive solo gli attuali
	if ($originali == null || $originali == 0) {
		return oreToDisplay($attuali);
	}
	// altrimenti gli originali cancellati e gli attuali in rosso
	$ore_con_minuti = oreToDisplay($attuali);
	$ore_con_minuti_originali = oreToDisplay($originali);
	return '<s style="text-decoration-style: double;"> '.$ore_con_minuti_originali.' </s>&ensp;<span class="text-danger"><strong> '.$ore_con_minuti.' </strong></span>';
}

// default opera sul docente connesso e agisce come docente
$docente_id = $__docente_id;
$operatore = 'docente';

$modificabile = $__config->getOre_previsioni_aperto();

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

$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
						<tr>
							<th class="col-md-1 text-left">Data</th>
							<th class="col-md-5 text-left">Descrizione</th>
							<th class="col-md-1 text-center">Senza Pernottamento</th>
							<th class="col-md-1 text-center">Con Pernottamento</th>
							<th class="col-md-1 text-center">Importo</th>
							<th class="col-md-1 text-center">Ore</th>
							<th class="col-md-1 text-center"></th>
						</tr>';

foreach(dbGetAll("SELECT viaggio_diaria_fatta.id as local_viaggio_diaria_fatta_id, viaggio_diaria_fatta.*, viaggio_diaria_fatta_commento.* FROM viaggio_diaria_fatta LEFT JOIN viaggio_diaria_fatta_commento on viaggio_diaria_fatta_commento.viaggio_diaria_fatta_id = viaggio_diaria_fatta.id WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docente_id;") as $row) {
	// controlla se aggiornata dall'ultima modifica (solo per il dirigente)
	$marker = '';
	if ($operatore == 'dirigente') {
		if ($row['ultima_modifica'] > $ultimo_controllo) {
			$marker = '&ensp;<span class="label label-danger glyphicon glyphicon-star" style="color:yellow"> '. '' .'</span>';
		}
	}

	$data .= '<tr>
		<td>'.strftime("%d/%m/%Y", strtotime($row['data_partenza'])).'</td>
		<td>'.$row['descrizione'].$marker;
	if ($row['commento'] != null && !empty(trim($row['commento'], " "))) {
		$data .='</br><span class="text-danger"><strong>'.$row['commento'].'</strong></span>';
	}
	$importo = $row['giorni_senza_pernottamento'] * $__importo_diaria_senza_pernottamento + $row['giorni_con_pernottamento'] * $__importo_diaria_con_pernottamento;
	$data .= '</td>
		<td class="text-center">'.writeGiorni($row['giorni_senza_pernottamento'], $row['giorni_senza_pernottamento_originali']).'</td>
		<td class="text-center">'.writeGiorni($row['giorni_con_pernottamento'], $row['giorni_con_pernottamento_originali']).'</td>
		<td class="text-right">'.$importo.' €&ensp;</td>';

	$data .= '<td class="text-center">'.writeOre($row['ore'], $row['ore_originali']).'</td>';

	$data .='<td class="text-center">';
	// si possono modificare solo le righe previste da docente: se dirigente lo script non cancella ma propone di mettere le ore a zero
	if ($modificabile) {
		$data .='
		<button onclick="diariaFattaGetDetails('.$row['local_viaggio_diaria_fatta_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
		<button onclick="diariaFattaDelete('.$row['local_viaggio_diaria_fatta_id'].')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
	';
	}
	$data .='</td></tr>';
}

$data .= '</table></div>';

echo $data;
?>
