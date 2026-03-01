<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

function formatNoZeroDiariaViaggio($value) {
    return ($value != 0) ? number_format($value,2) : ' ';
}

function formatNoZeroOreViaggio($value) {
    return ($value != 0) ? number_format($value,0) : ' ';
}

if ( empty($__docente_id) ) {
	return;
}

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="col-md-1 text-left">Data</th>
						<th class="col-md-2 text-left">Tipo</th>
						<th class="col-md-4 text-left">Destinazione</th>
						<th class="col-md-1 text-center">Classe</th>
						<th class="col-md-1 text-center">Stato</th>
						<th class="col-md-1 text-center"></th>
						<th class="col-md-1 text-center">Ore</th>
						<th class="col-md-1 text-center">Diaria</th>
					</tr>
					</thead><tbody>';

// data destinazione, classe, stato, azioni
foreach(dbGetAll("SELECT * from viaggio WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $__docente_id ORDER BY data_partenza DESC;") as $viaggio) {
	$viaggioId = $viaggio['id'];
	$tipoViaggio = $viaggio['tipo_viaggio'];
	$destinazione = $viaggio['destinazione'];
	$classe = $viaggio['classe'];
	$stato = $viaggio['stato'];
	$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
	$dataPartenza = utf8_encode( strftime("%d %B %Y", strtotime($viaggio['data_partenza'])));
	setlocale(LC_TIME, $oldLocale);

	$statoMarker = '';
	switch ($stato) {
		case "assegnato":
			$statoMarker = '<span class="label label-info">assegnato</span>';
			break;
		case "protocollato":
			$statoMarker = '<span class="label label-info">protocollato</span>';
			break;
		case "accettato":
			$statoMarker = '<span class="label label-success">accettato</span>';
			break;
		case "effettuato":
			$statoMarker = '<span class="label label-warning">effettuato</span>';
			break;
		case "protocollato":
			$statoMarker = '<span class="label label-primary">protocollato</span>';
			break;
		case "chiuso":
			$statoMarker = '<span class="label label-danger">chiuso</span>';
			break;
		case "annullato":
			$statoMarker = '<span class="label label-danger">annullato</span>';
			break;
		default:
			$statoMarker = '<span class="label label-danger">sconosciuto</span>';
	}

	$data .= '<tr>
		<td>'.$dataPartenza.'</td>
		<td>'.$tipoViaggio.'</td>
		<td>'.$destinazione.'</td>
		<td>'.$classe.'</td>
		<td class="text-center">'.$statoMarker.'</td>
		';

	// se e' assegnato o protocollato ed e' richiesta l'accettazione compare il bottone di accetta
	if (($stato === 'protocollato' || ($stato === 'assegnato') && getSettingsValue('viaggi', 'accettazione', true))) {
		$data .= '<td><button type="button" class="btn btn-success btn-xs firmaBtnClass" onclick="viaggioAccetta(\''.$viaggioId.'\')"><span class="glyphicon glyphicon-thumbs-up"> Accetta </button></td>';

	// se invece e' stato accettato oppure l'accettazione non e' richiesta, puo' essere inoltrato
	} else if ($stato === 'accettato' || $stato === 'assegnato' || $stato === 'protocollato' ) {
		$data .= '<td><button onclick="viaggioGetDetails('.$viaggioId.')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-log-out"> Inoltra</button></td>';

	// altrimenti vuole dire che il docente non deve fare altre azioni
	} else {
		$data .= '<td>'.'</td>';
	}

	// adesso ricaviamo le ore e l'importo di una eventuale diaria
	$ore = dbGetValue("SELECT COALESCE( (SELECT ore FROM viaggio_ore_recuperate WHERE viaggio_id = $viaggioId), 0);");
	$diaria = dbGetValue("SELECT COALESCE( (SELECT importo FROM fuis_viaggio_diaria WHERE viaggio_id = $viaggioId), 0);");
	$oreString = formatNoZeroOreViaggio($ore);
	$diariaString =  formatNoZeroDiariaViaggio($diaria);
	$data .='<td class="text-center">'.$oreString.'</td><td>'.$diariaString.'</td>';
	$data .='</tr>';
}
echo $data;
?>
