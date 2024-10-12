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
 
function writeGiorniPrevisti($attuali, $originali) {
	// se non ci sono gli originali, scrive solo gli attuali
	if ($originali == null || $originali == 0) {
		return $attuali;
	}
	// altrimenti gli originali cancellati e gli attuali in rosso
	return '<s style="text-decoration-style: double;"> '.$originali.' </s>&ensp;<span class="text-danger"><strong> '.$attuali.' </strong></span>';
}

function writeOreDiariaPreviste($attuali, $originali) {
	// se non ci sono gli originali, scrive solo gli attuali
	if ($originali == null || $originali == 0) {
		return oreToDisplay($attuali);
	}
	// altrimenti gli originali cancellati e gli attuali in rosso
	$ore_con_minuti = oreToDisplay($attuali);
	$ore_con_minuti_originali = oreToDisplay($originali);
	return '<s style="text-decoration-style: double;"> '.$ore_con_minuti_originali.' </s>&ensp;<span class="text-danger"><strong> '.$ore_con_minuti.' </strong></span>';
}

function viaggioDiariaPrevistaReadRecords($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile) {
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
		$diaria = dbGetFirst("SELECT COALESCE(SUM(viaggio_diaria_prevista.giorni_senza_pernottamento), 0) AS giorni_senza_pernottamento, COALESCE(SUM(viaggio_diaria_prevista.giorni_con_pernottamento), 0) AS giorni_con_pernottamento , COALESCE(SUM(viaggio_diaria_prevista.ore), 0) AS ore FROM viaggio_diaria_prevista WHERE docente_id = $docente_id AND anno_scolastico_id = $__anno_scolastico_corrente_id;");
		$diariaGiorniSenzaPernottamento = $diaria['giorni_senza_pernottamento'];
		$diariaGiorniConPernottamento = $diaria['giorni_con_pernottamento'];
		$diariaOre = $diaria['ore'];
		$diariaImporto = $diariaGiorniSenzaPernottamento * $__importo_diaria_senza_pernottamento + $diariaGiorniConPernottamento * $__importo_diaria_con_pernottamento;

		$result = compact('dataDiaria', 'diariaGiorniSenzaPernottamento', 'diariaGiorniConPernottamento', 'diariaImporto', 'diariaOre');
		return $result;
	}

	$dataDiaria .= '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
							<tr>
								<th class="col-md-6 text-left">Descrizione</th>
								<th class="col-md-1 text-center">Senza Pernottamento</th>
								<th class="col-md-1 text-center">Con Pernottamento</th>
								<th class="col-md-1 text-center">Importo</th>
								<th class="col-md-1 text-center">Ore</th>
								<th class="col-md-1 text-center"></th>
							</tr>';

	foreach(dbGetAll("SELECT viaggio_diaria_prevista.id as local_viaggio_diaria_prevista_id, viaggio_diaria_prevista.*, viaggio_diaria_prevista_commento.* FROM viaggio_diaria_prevista LEFT JOIN viaggio_diaria_prevista_commento on viaggio_diaria_prevista_commento.viaggio_diaria_prevista_id = viaggio_diaria_prevista.id WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docente_id;") as $row) {
		// controlla se aggiornata dall'ultima modifica (solo per il dirigente)
		$marker = '';
		if ($operatore == 'dirigente') {
			if ($row['ultima_modifica'] > $ultimo_controllo) {
				$marker = '&ensp;<span class="label label-danger glyphicon glyphicon-star" style="color:yellow"> '. '' .'</span>';
			}
		}

		$dataDiaria .= '<tr>
			<td>'.$row['descrizione'].$marker;
		if ($row['commento'] != null && !empty(trim($row['commento'], " "))) {
			$dataDiaria .='</br><span class="text-danger"><strong>'.$row['commento'].'</strong></span>';
		}
		$importo = $row['giorni_senza_pernottamento'] * $__importo_diaria_senza_pernottamento + $row['giorni_con_pernottamento'] * $__importo_diaria_con_pernottamento;
		$dataDiaria .= '</td>
			<td class="text-center">'.writeGiorniPrevisti($row['giorni_senza_pernottamento'], $row['giorni_senza_pernottamento_originali']).'</td>
			<td class="text-center">'.writeGiorniPrevisti($row['giorni_con_pernottamento'], $row['giorni_con_pernottamento_originali']).'</td>
			<td class="text-right">'.$importo.' €&ensp;</td>';

		$dataDiaria .= '<td class="text-center">'.writeOreDiariaPreviste($row['ore'], $row['ore_originali']).'</td>';

		$dataDiaria .='<td class="text-center">';
		// si possono modificare solo le righe previste da docente: se dirigente lo script non cancella ma propone di mettere le ore a zero
		if ($modificabile) {
			$dataDiaria .='
			<button onclick="diariaPrevistaGetDetails('.$row['local_viaggio_diaria_prevista_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
			<button onclick="diariaPrevistaDelete('.$row['local_viaggio_diaria_prevista_id'].')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
		';
		}
		$dataDiaria .='</td></tr>';

		$diariaGiorniSenzaPernottamento += $row['giorni_senza_pernottamento'];
		$diariaGiorniConPernottamento += $row['giorni_con_pernottamento'];
		$diariaImporto += $importo;
		$diariaOre += $row['ore'];
	}

	$dataDiaria .= '</table></div>';

	$result = compact('dataDiaria', 'diariaGiorniSenzaPernottamento', 'diariaGiorniConPernottamento', 'diariaImporto', 'diariaOre');
	return $result;
}
?>
