<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/__MinutiFunction.php';
require_once '../common/importi_load.php';

function writeGiorni($attuali, $originali) {
	// se non ci sono gli originali, scrive solo gli attuali
	if ($originali == null || $originali == 0) {
		return $attuali;
	}
	// altrimenti gli originali cancellati e gli attuali in rosso
	return '<s style="text-decoration-style: double;"> '.$originali.' </s>&ensp;<span class="text-danger"><strong> '.$attuali.' </strong></span>';
}

function formatNoZeroDiaria($value) {
    return ($value != 0) ? number_format($value,2) : ' ';
}

function writeOreDiaria($attuali, $originali) {
	// se non ci sono gli originali, scrive solo gli attuali
	if ($originali == null || $originali == 0) {
		return oreToDisplay($attuali);
	}
	// altrimenti gli originali cancellati e gli attuali in rosso
	$ore_con_minuti = oreToDisplay($attuali);
	$ore_con_minuti_originali = oreToDisplay($originali);
	return '<s style="text-decoration-style: double;"> '.$ore_con_minuti_originali.' </s>&ensp;<span class="text-danger"><strong> '.$ore_con_minuti.' </strong></span>';
}

function viaggioDiariaFattaReadRecords($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile) {
	global $__anno_scolastico_corrente_id;
	global $__importo_diaria_senza_pernottamento;
	global $__importo_diaria_con_pernottamento;

	// valori da restituire come totali
	$diariaGiorniSenzaPernottamento = 0;
	$diariaGiorniConPernottamento = 0;
	$diariaImporto = 0;
	$diariaOre = 0;
	$dataDiaria = '';

	// controlla se deve restituire solo il totale o anche la tabella html
	if($soloTotale) {
		$diaria = dbGetFirst("SELECT COALESCE(SUM(viaggio_diaria_fatta.giorni_senza_pernottamento), 0) AS giorni_senza_pernottamento, COALESCE(SUM(viaggio_diaria_fatta.giorni_con_pernottamento), 0) AS giorni_con_pernottamento , COALESCE(SUM(viaggio_diaria_fatta.ore), 0) AS ore FROM viaggio_diaria_fatta WHERE docente_id = $docente_id AND anno_scolastico_id = $__anno_scolastico_corrente_id;");
		$diariaGiorniSenzaPernottamento = $diaria['giorni_senza_pernottamento'];
		$diariaGiorniConPernottamento = $diaria['giorni_con_pernottamento'];
		$diariaOre = $diaria['ore'];
		$diariaImporto = $diariaGiorniSenzaPernottamento * $__importo_diaria_senza_pernottamento + $diariaGiorniConPernottamento * $__importo_diaria_con_pernottamento;

		// aggiunge un eventuale importo di diaria proveniente dalla vecchia gestione nella tabella fuis_viaggio_diaria
		$diaria_importo_extra = dbGetValue ("SELECT COALESCE(SUM(importo) , 0) AS importo FROM fuis_viaggio_diaria INNER JOIN viaggio ON viaggio.id = fuis_viaggio_diaria.viaggio_id WHERE viaggio.docente_id = $docente_id AND viaggio.anno_scolastico_id = $__anno_scolastico_corrente_id;") ;
		$diariaImporto = $diariaImporto + $diaria_importo_extra;

		$result = compact('dataDiaria', 'diariaGiorniSenzaPernottamento', 'diariaGiorniConPernottamento', 'diariaImporto', 'diariaOre');
		return $result;
	}

	$dataDiaria .= '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
							<tr>
								<th class="col-md-1 text-left">Data</th>';
	if ($modificabile) {
		$dataDiaria .=  '<th class="col-md-5 text-left">Descrizione</th>';
	} else {
		$dataDiaria .=  '<th class="col-md-6 text-left">Descrizione</th>';
	}
							
	$dataDiaria .=  '<th class="col-md-1 text-center">Senza Pernottamento</th>
								<th class="col-md-1 text-center">Con Pernottamento</th>
								<th class="col-md-1 text-center">Importo</th>
								<th class="col-md-1 text-center">Ore</th>';
	if ($modificabile) {
		$dataDiaria .=  '<th class="col-md-1 text-center"></th>';
	}

	$dataDiaria .=  '</tr><tbody>';

	foreach(dbGetAll("SELECT viaggio_diaria_fatta.id as local_viaggio_diaria_fatta_id, viaggio_diaria_fatta.*, viaggio_diaria_fatta_commento.* FROM viaggio_diaria_fatta LEFT JOIN viaggio_diaria_fatta_commento on viaggio_diaria_fatta_commento.viaggio_diaria_fatta_id = viaggio_diaria_fatta.id WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docente_id;") as $row) {
		// controlla se aggiornata dall'ultima modifica (solo per il dirigente)
		$marker = '';
		if ($operatore == 'dirigente') {
			if ($row['ultima_modifica'] > $ultimo_controllo) {
				$marker = '&ensp;<span class="label label-danger glyphicon glyphicon-star" style="color:yellow"> '. '' .'</span>';
			}
		}

		$dataDiaria .= '<tr>
			<td>'.strftime("%d/%m/%Y", strtotime($row['data_partenza'])).'</td>
			<td>'.$row['descrizione'].$marker;
		if ($row['commento'] != null && !empty(trim($row['commento'], " "))) {
			$dataDiaria .='</br><span class="text-danger"><strong>'.$row['commento'].'</strong></span>';
		}
		$importo = $row['giorni_senza_pernottamento'] * $__importo_diaria_senza_pernottamento + $row['giorni_con_pernottamento'] * $__importo_diaria_con_pernottamento;
		$dataDiaria .= '</td>
			<td class="text-center">'.writeGiorni($row['giorni_senza_pernottamento'], $row['giorni_senza_pernottamento_originali']).'</td>
			<td class="text-center">'.writeGiorni($row['giorni_con_pernottamento'], $row['giorni_con_pernottamento_originali']).'</td>
			<td class="text-right">'.$importo.' €&ensp;</td>';

		$dataDiaria .= '<td class="text-center">'.writeOreDiaria($row['ore'], $row['ore_originali']).'</td>';

		// si possono modificare solo le righe previste da docente: se dirigente lo script non cancella ma propone di mettere le ore a zero
		if ($modificabile) {
			$dataDiaria .='<td class="text-center">
			<button onclick="diariaFattaGetDetails('.$row['local_viaggio_diaria_fatta_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
			<button onclick="diariaFattaDelete('.$row['local_viaggio_diaria_fatta_id'].')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
		</td>';
		}
		$dataDiaria .='</tr>';

		$diariaGiorniSenzaPernottamento += $row['giorni_senza_pernottamento'];
		$diariaGiorniConPernottamento += $row['giorni_con_pernottamento'];
		$diariaImporto += $importo;
		$diariaOre += $row['ore'];
	}

	// aggiunge non modificabili i viaggi gestiti nel modo vecchio da tabella fuis_viaggio_diaria
	foreach(dbGetAll("SELECT viaggio.*, fuis_viaggio_diaria.* FROM fuis_viaggio_diaria INNER JOIN viaggio ON viaggio.id = fuis_viaggio_diaria.viaggio_id WHERE viaggio.docente_id = $docente_id AND viaggio.anno_scolastico_id = $__anno_scolastico_corrente_id;") as $row) {
		$importo = $row['importo'];
		$dataDiaria .= '<tr><td>'.strftime("%d/%m/%Y", strtotime($row['data_partenza'])).'</td><td>'.$row['destinazione'].' - classe: '.$row['classe'].'</td>';
		$dataDiaria .= '<td></td>';
		$dataDiaria .= '<td></td>';
		$dataDiaria .= '<td class="text-right">'.$importo.' €&ensp;</td>';
		$dataDiaria .= '<td></td><td></td></tr>';
	}

	// aggiunge un eventuale importo di diaria proveniente dalla vecchia gestione nella tabella fuis_viaggio_diaria
	$diaria_importo_extra = dbGetValue ("SELECT COALESCE(SUM(importo) , 0) AS importo FROM fuis_viaggio_diaria INNER JOIN viaggio ON viaggio.id = fuis_viaggio_diaria.viaggio_id WHERE viaggio.docente_id = $docente_id AND viaggio.anno_scolastico_id = $__anno_scolastico_corrente_id;") ;
	$diariaImporto = $diariaImporto + $diaria_importo_extra;

	$dataDiaria .= '</tbody><tfoot>';
	$dataDiaria .='<tr><td colspan="4" class="text-right"><strong>Totale:</strong></td><td class="text-right funzionale"><strong>' . formatNoZeroDiaria($diariaImporto) . '</strong></td></tr>';
	$dataDiaria .='</tfoot></table></div>';

	$result = compact('dataDiaria', 'diariaGiorniSenzaPernottamento', 'diariaGiorniConPernottamento', 'diariaImporto', 'diariaOre');
	return $result;
}
/*
// se viene chiamato con un post, allora ritonna il valore con echo
if(isset($_POST['richiesta']) && $_POST['richiesta'] == "viaggioDiariaFattaReadRecords") {
	if(isset($_POST['docente_id']) && isset($_POST['docente_id']) != "") {
		$docente_id = $_POST['docente_id'];
	} else {
		$docente_id = $__docente_id;
	}
	$soloTotale = json_decode($_POST['soloTotale']);

	if(isset($_POST['operatore']) && $_POST['operatore'] == 'dirigente') {
		// se vuoi fare il dirigente, devi essere dirigente
		ruoloRichiesto('dirigente');
		// agisci quindi come dirigente
		$operatore = 'dirigente';
		// il dirigente può sempre fare modifiche
		$modificabile = true;
		// devi leggere il timestamp dell'ultimo controllo effettuato
		$ultimo_controllo = $_POST['ultimo_controllo'];
	} else {
		$operatore = 'docente';
		$ultimo_controllo = '';
		$modificabile = $__config->getOre_fatte_aperto();
	}

	// le previste considerano come se tutto fosse stato firmato nel caso di corsi in itinere, ma le fatte no
	if(isset($_POST['sorgente_richiesta']) && $_POST['sorgente_richiesta'] == 'fatte') {
		$controllaFirmeInItinere = true;
	} else {
		$controllaFirmeInItinere = false;
	}

	$result = viaggioDiariaFattaReadRecords($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile);
	echo json_encode($result);
}*/
?>