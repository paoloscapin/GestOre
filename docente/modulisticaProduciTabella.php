
<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function produciTabella($listaEtichette, $listaValori, $listaTipi, $listaValoriSelezionabili) {
	global $__settings;
	global $__application_base_path;

	// se la lista dei campi e' vuota non deve produrre nessuna tabella
	if(count($listaEtichette) <= 0) {
		return '';
	}

	$chekboxChecked = '<div class="tick"><b><input type="checkbox" value="" style="vertical-align: bottom;" checked></b> ';
	$chekboxUnchecked = '<div class="tick"><b><input type="checkbox" value="" style="vertical-align: bottom;"></b> ';
	$radioChecked = '<div class="tick"><b><input type="checkbox" value="" style="vertical-align: bottom;" checked></b> ';
	$radioUnchecked = '<div class="tick"><b><input type="checkbox" value="" style="vertical-align: bottom;"></b> ';

	$tableBlock = '';
	$tableBlock .= '<table id="campi"><tr><th>nome</th><th>valore</th><tr>';
	for ($i = 0; $i < count($listaEtichette); $i++) {
		$campo = $listaEtichette[$i];
		if ($listaTipi[$i] == 1 || $listaTipi[$i] == 2 || $listaTipi[$i] == 6) {
			// per tipo 1 (text) e 2 (combo) e 6 (data) mette solo il valore
			$valore = $listaValori[$i];
		} else  if ($listaTipi[$i] == 5) {
			// per tipo 5 (textarea) inserisce il "pre"
			$valore = '<span  style="white-space: pre-wrap;">' . $listaValori[$i] . '</span>';
			// debug('ì='.$i.' valore='.$valore);
		} else  if ($listaTipi[$i] == 3 || $listaTipi[$i] == 4) {
			// per 3 e 4 la stringa rappresenta le posizioni in cui i checkbox o radio sono settati e i testi vanno presi da lista valori del db
			// la trasforma in una lista di stringhe esplodendo i :: come separatori
			$listaBoxChecked = array_map('intval', explode('::', $listaValori[$i]));

			$risultato = '';
			// prende tutte le diciture dei box
			$localiValoriSelezionabili = explode('::', $listaValoriSelezionabili[$i]);
			for ($j = 0; $j < count($localiValoriSelezionabili); $j++) {
				$valoreSelezionabile = $localiValoriSelezionabili[$j];
	
				// controlla se questo deve essere marcato
				if (in_array($j, $listaBoxChecked)) {
					$risultato = $risultato . $chekboxChecked . $valoreSelezionabile . '</div><br/>';
				} else {
					$risultato = $risultato . $chekboxUnchecked . $valoreSelezionabile . '</div><br/>';
				}
			}

			$valore = $risultato;
		} else  if ($listaTipi[$i] == 7) {
			// per tipo 7 (upload): inserisce il link per scaricare il documento
			$filePath = $listaValori[$i];

			$connection = 'http';
			if ($__settings->system->https) {
				$connection = 'https';
			}
			// $url = "$connection://$_SERVER[HTTP_HOST]".$__application_base_path . '/segreteria/modulisticaDownload.php?documento=' . $filePath;
			$url = "$connection://$_SERVER[HTTP_HOST]".$__application_base_path . '/uploads/' . $filePath;
			$valore = '<span  style="white-space: pre-wrap;">' . '<a target="_blank" href=\''.$url.'\'>' . basename($filePath) .'</a>' . '</span>';
		} else {
			$valore = '';
		}
		$tableBlock .= '<tr><td class="col1">'.$campo.'</td><td class="col2">'.$valore.'</td></tr>';
	}
	$tableBlock .= '</table>';
	return $tableBlock;
}

function getEmailHead() {
	$head='<head><style>
		#campi { font-family: Arial, Helvetica, sans-serif; border-collapse: collapse; width: 100%; }
		#campi td, #campi th { border: 1px solid #ddd; padding: 6px; }
		#campi tr:nth-child(even) { background-color: #f2f2f2; }
		#campi tr:hover { background-color: #ddd; }
		#campi th { padding-top: 6px; padding-bottom: 6px; text-align: left; background-color: #04AA6D; color: white; }
		.col1 { width: 25%; }
		.col2 { width: 75%; }
		.tick { margin-left: 0.65cm; text-indent: -0.65cm; }
		.btn-ar { color: #fff; padding: 10px 15px; margin: 20px 15px 10px 15px;
			background-image: radial-gradient(93% 87% at 87% 89%, rgba(0, 0, 0, 0.23) 0%, transparent 86.18%), radial-gradient(66% 66% at 26% 20%, rgba(255, 255, 255, 0.55) 0%, rgba(255, 255, 255, 0) 69.79%, rgba(255, 255, 255, 0) 100%);
			box-shadow: inset -3px -3px 9px rgba(255, 255, 255, 0.25), inset 0px 3px 9px rgba(255, 255, 255, 0.3), inset 0px 1px 1px rgba(255, 255, 255, 0.6), inset 0px -8px 36px rgba(0, 0, 0, 0.3), inset 0px 1px 5px rgba(255, 255, 255, 0.6), 2px 19px 31px rgba(0, 0, 0, 0.2);
			border-radius: 12px; font-weight: bold; font-size: 14px; border: 0; user-select: none; -webkit-user-select: none; touch-action: manipulation; cursor: pointer; }
		.btn-approva { background-color: #1CAF43; }
		.btn-respingi { background-color: #F1003C; }
		.btn-waiting { background-color: #f2e829; }
		.btn-chiudi { background-color: #0ae4f0; }
		.btn-pendente { background-color: #777777; }
		.btn-label { padding: 5px 12px; border-radius: 8px; margin: 10px 5px; }
		</style></head>';

	return $head;
}
?>