
<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function produciTabella($listaEtichette, $listaValori, $listaTipi, $listaValoriSelezionabili) {

    $chekboxChecked = '<div class="tick"><b><input type="checkbox" value="" style="vertical-align: bottom;" checked></b> ';
	$chekboxUnchecked = '<div class="tick"><b><input type="checkbox" value="" style="vertical-align: bottom;"></b> ';
	$radioChecked = '<div class="tick"><b><input type="checkbox" value="" style="vertical-align: bottom;" checked></b> ';
	$radioUnchecked = '<div class="tick"><b><input type="checkbox" value="" style="vertical-align: bottom;"></b> ';

	$tableBlock = '';
	$tableBlock .= '<table id="campi"><tr><th>nome</th><th>valore</th><tr>';
	for ($i = 0; $i < count($listaEtichette); $i++) {
		$campo = $listaEtichette[$i];
		if ($listaTipi[$i] == 1 || $listaTipi[$i] == 2) {
			// per tipo 1 e 2 mette solo il valore
			$valore = $listaValori[$i];
		} else  if ($listaTipi[$i] == 5) {
			// per tipo 5 (textarea) inserisce il "pre")
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
		}
		$tableBlock .= '<tr><td class="col1">'.$campo.'</td><td class="col2">'.$valore.'</td></tr>';
	}
	$tableBlock .= '</table>';
	return $tableBlock;
}
?>