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
						<th class="text-center col-md-1">O.d.G. / verbale</th>
						<th class="text-center col-md-1">Approva</th>
					</tr>
					</thead>';

foreach(dbGetAll("SELECT richiesta_assemblea.id AS richiesta_id, richiesta_assemblea.*, docente.* FROM `richiesta_assemblea` INNER JOIN docente ON docente.id = richiesta_assemblea.docente_id WHERE anno_scolastico_id = $__anno_scolastico_corrente_id ORDER BY data_assemblea DESC ; ") as $richiesta) {
	$richiesta_id = $richiesta['richiesta_id'];
	$stato = $richiesta['stato'];
	$docente_nome_e_cognome = $richiesta['nome'] . ' ' . $richiesta['cognome'];

	$studente_id = $richiesta['studente_id'];
	$studente = dbGetFirst("SELECT * FROM studente WHERE id=$studente_id");
	$studente_nome_cognome =  $studente['nome'] . ' ' . $studente['cognome'];

	$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
	$data_assemblea = utf8_encode( strftime("%d %B %Y", strtotime($richiesta['data_assemblea'])));
	setlocale(LC_TIME, $oldLocale);
	$ora_assemblea = $richiesta['ora_assemblea'];
	$giornoSettimanaAssemblea = utf8_encode( strftime("%A", strtotime($richiesta['data_assemblea'])));

	if ($stato == 'verbalizzata') {
		$mostraVerbale = True;
	}

	$data .= '<tr>
		<td>'.$richiesta['classe'].'</td>
		<td>'.$data_assemblea.'</td>
		<td>'.nomeOra($ora_assemblea).'</td>
		<td>'.$giornoSettimanaAssemblea.'</td>
		<td>'.$studente_nome_cognome.'</td>
		<td>'.$docente_nome_e_cognome.'</td>
		<td class="text-center col-md-1">'.getStatoMarker($stato).'</td>';
	$data .='<td class="text-center col-md-1"><!--
		<button onclick="richiestaAssembleaGetDetails('.$richiesta_id.')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
		<button onclick="richiestaAssembleaDelete('.$richiesta_id.', \''.$data_assemblea.'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button> -->
		<button onclick="richiestaAssembleaOrdineDelGiorno('.$richiesta_id.', \''.'modificabile'.'\')" class="btn btn-approva btn-xs"><span class="glyphicon glyphicon-list" style="color: #ffffff;"></button>';
	if ($mostraVerbale) {
		$data .='<button onclick="richiestaAssembleaVerbale('.$richiesta_id.', \''.'readonly'.'\')" class="btn btn-waiting btn-xs"><span class="glyphicon glyphicon-align-left" style="color: #ffffff;"></button>';
	}

	$data .='</td>';
	$data .='<td class="text-center col-md-1">';
	if ($stato == 'in attesa') {
		$data .='<button onclick="richiestaAssembleaApprova('.$richiesta_id.')" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-thumbs-up"> approva</button>
				<button onclick="richiestaAssembleaAnnulla('.$richiesta_id.')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-thumbs-down"> annulla</button>';
	}
	$data .='</td>';
	$data .='</tr>';
}
$data .= '</table></div>';

echo $data;
?>
