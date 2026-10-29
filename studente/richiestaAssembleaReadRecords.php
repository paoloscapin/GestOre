<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// include Database connection file
require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../studente/richiestaUtil.php';

$openTabMode = getSettingsValue('interfaccia','apriModuloInNuovoTab', false) ? '_blank' : '_self';
$studente_id = $__studente_id;

// nome e cognome insieme
$studente_nome_cognome = $__studente_nome . ' ' . $__studente_cognome;
$studente_classe = $__studente_classe;
$studente_email = $__studente_email; 

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="text-left col-md-1">Classe</th>
						<th class="text-left col-md-1">Data</th>
						<th class="text-left col-md-2">Ora</th>
						<th class="text-left col-md-1">Giorno</th>
						<th class="text-left col-md-2">Richiedente</th>
						<th class="text-left col-md-2">Docente</th>
						<th class="text-center col-md-1">Stato</th>
						<th class="text-center col-md-1">O.d.G.</th>
						<th class="text-center col-md-1">Verbale</th>
					</tr>
					</thead>';

foreach(dbGetAll("SELECT richiesta_assemblea.id AS richiesta_id, richiesta_assemblea.*, docente.* FROM `richiesta_assemblea` INNER JOIN docente ON docente.id = richiesta_assemblea.docente_id WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND classe='$studente_classe' ORDER BY data_assemblea DESC ; ") as $richiesta) {
	$richiesta_id = $richiesta['richiesta_id'];
	$stato = $richiesta['stato'];
	$docente_nome_e_cognome = $richiesta['nome'] . ' ' . $richiesta['cognome'];

	$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
	$data_assemblea = utf8_encode( strftime("%d %B %Y", strtotime($richiesta['data_assemblea'])));
	setlocale(LC_TIME, $oldLocale);
	$ora_assemblea = $richiesta['ora_assemblea'];
	$giornoSettimanaAssemblea = utf8_encode( strftime("%A", strtotime($richiesta['data_assemblea'])));

	$ordineDelGiorno = $richiesta['ordine_del_giorno'];
	$verbale = $richiesta['verbale'];

	// calcola se la data dell'assemblea e' >= a quella di oggi, in questo caso posso inserire il verbale se ancora non e' presente
	$mostraVerbale = False;
	$attivaVerbale = False;

	// lo stesso giorno si puo' sempre modificare il verbale
	if (new DateTime('today') == new DateTime($richiesta['data_assemblea'])) {
		$attivaVerbale = True;
	}

	else if (new DateTime('today') >= new DateTime($richiesta['data_assemblea'])) {
		// dal giorno dopo si puo' compilare solo se e' ancora vuoto
		if ($richiesta['verbale'] == '') {
			if ($studente_id == $richiesta['studente_id']) {
				$attivaVerbale = True;
			}
		} else {
			// altrimenti in visualizzazione
			$mostraVerbale = True;
		}
	}

	$data .= '<tr>
		<td>'.$studente_classe.'</td>
		<td>'.$data_assemblea.'</td>
		<td>'.nomeOra($ora_assemblea).'</td>
		<td>'.$giornoSettimanaAssemblea.'</td>
		<td>'.$studente_nome_cognome.'</td>
		<td>'.$docente_nome_e_cognome.'</td>
		<td class="text-center col-md-1">'.getStatoMarker($stato).'</td>';
	$data .= '<td class="text-center col-md-1"><button onclick="richiestaAssembleaOrdineDelGiorno('.$richiesta_id.', \''.'modificabile'.'\')" class="btn btn-approva btn-xs"><span class="glyphicon glyphicon-list" style="color: #ffffff;"></button></td>';
	if ($attivaVerbale) {
		$data .= '<td class="text-center col-md-1"><button onclick="richiestaAssembleaVerbale('.$richiesta_id.', \''.'modificabile'.'\')" class="btn btn-waiting btn-xs"><span class="glyphicon glyphicon-align-left" style="color: #ffffff;"></button></td>';
	} else if ($mostraVerbale) {
		$data .= '<td class="text-center col-md-1"><button onclick="richiestaAssembleaVerbale('.$richiesta_id.', \''.'readonly'.'\')" class="btn btn-waiting btn-xs"><span class="glyphicon glyphicon-align-left" style="color: #ffffff;"></button></td>';
	} else {
		$data .= '<td></td>';
	}
	$data .= '</tr>';
}
$data .= '</table></div>';

echo $data;
?>
