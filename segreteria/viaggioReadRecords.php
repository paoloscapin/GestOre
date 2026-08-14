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

function formatNoZeroDiariaViaggio($value) {
    return ($value != 0) ? number_format($value,2) : ' ';
}

function formatNoZeroOreViaggio($value) {
    return ($value != 0) ? number_format($value,0) : ' ';
}

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="col-md-1 text-left">Data</th>
						<th class="col-md-1 text-left">Docente</th>
						<th class="col-md-3 text-left">Destinazione</th>
						<th class="col-md-1 text-center">Stato</th>
						<th class="col-md-2 text-center">Incarico</th>
						<th class="col-md-1 text-center">Modifica</th>
						<th class="col-md-1 text-center"></th>
						<th class="col-md-1 text-center">Ore</th>
						<th class="col-md-1 text-center">Diaria</th>
					</tr>
					</thead><tbody>';

$query = "SELECT viaggio.id AS viaggio_id, viaggio.*, docente.cognome, docente.nome, COALESCE(viaggio_ore_recuperate.ore, 0) AS ore, COALESCE(fuis_viaggio_diaria.importo, 0) AS diaria FROM viaggio
		INNER JOIN docente ON viaggio.docente_id = docente.id
		LEFT JOIN viaggio_ore_recuperate ON viaggio_ore_recuperate.viaggio_id = viaggio.id
		LEFT JOIN fuis_viaggio_diaria ON fuis_viaggio_diaria.viaggio_id = viaggio.id
		WHERE viaggio.anno_scolastico_id = $__anno_scolastico_corrente_id ORDER BY data_partenza DESC, cognome ASC, nome ASC; ";

foreach(dbGetAll($query) as $viaggio) {
	$viaggioId = $viaggio['viaggio_id'];
	$stato = $viaggio['stato'];
	$protocollo = $viaggio['protocollo'];
	$statoMarker = '';
	switch ($stato) {
		case "assegnato":
			$statoMarker = '<span class="label label-info">assegnato</span>';
			break;
		case "creato":
			$statoMarker = '<span class="label label-default">creato</span>';
			break;
		case "protocollato":
			$statoMarker = '<span class="label label-primary">protocollato</span>';
			break;
		case "accettato":
			$statoMarker = '<span class="label label-success">accettato</span>';
			break;
		case "effettuato":
			$statoMarker = '<span class="label label-warning">effettuato</span>';
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
	$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
	$dataPartenza = utf8_encode( strftime("%d %B %Y", strtotime($viaggio['data_partenza'])));
	setlocale(LC_TIME, $oldLocale);
	$data .= '<tr>
		<td>'.$dataPartenza.'</td>
		<td>'.$viaggio['nome'].' '.$viaggio['cognome'].'</td>
		<td>'.$viaggio['destinazione'].'</td>
		<td class="text-center">'.$statoMarker.'</td>';
	$data .='<td class="text-center">
		<button onclick="viaggioNominaStampa('.$viaggioId.')" class="btn btn-orange4 btn-xs" style="display: inline-flex;align-items: center;"><i class="icon-pdf"></i>&nbsp;Pdf</button>';
		if (getSettingsValue('viaggi','protocollo', false)) {
			if ($protocollo != '') {
				$data .='<button onclick="viaggioProtocolla('.$viaggioId.')" class="btn btn-orange4 btn-xs style="display: inline-flex;align-items: center;"><i class="icon-protocollo" style="vertical-align: middle;"></i>&nbsp;PITre</button>';
			} else {
				$data .='<button onclick="viaggioNumeroProtocollo('.$viaggioId.')" class="btn btn-orange4 btn-xs style="display: inline-flex;align-items: center;"><i class="icon-protocollo" style="vertical-align: middle;"></i>&nbsp;numero</button>';
			}
		}
		$data .='<button onclick="viaggioNominaEmail('.$viaggioId.')" class="btn btn-lightblue4 btn-xs style="display: inline-flex;align-items: center;"><i class="icon-email" style="vertical-align: middle;"></i>&nbsp;email</button></td>';
		$data .='<td class="text-center">
			<button onclick="viaggioGetDetails('.$viaggioId.')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
			<button onclick="viaggioDelete('.$viaggioId.', \''.$viaggio['data_partenza'].'\', \''.$viaggio['destinazione'].'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button></td>';
	// se effettuato, allora lo posso chiudere
	if ($stato == "effettuato" || $stato == "chiuso") {
		$data .='
			<td class="text-center">
			<button onclick="viaggioChiusura('.$viaggioId.')" class="btn btn-deeporange4 btn-xs"><span class="glyphicon glyphicon-collapse-down"></button>
			</td>';
	} else {
		$data .='<td></td>';
	}

	// adesso ricaviamo le ore e l'importo di una eventuale diaria
	$ore = $viaggio['ore'];
	$oreString = formatNoZeroOreViaggio($ore);
	$diaria = $viaggio['diaria'];
	$diariaString =  formatNoZeroDiariaViaggio($diaria);
	$diariaIcon = '';
	if ($diaria != 0) {
		if ($viaggio['rimborsato']) {
			$diariaIcon = '<span class="glyphicon glyphicon-ok"></span>';
		} else {
			$diariaIcon = '<button onclick="viaggioDiariaLiquida('.$viaggioId.')" class="btn btn-salmon btn-xs" style="display: inline-flex;align-items: center;"><i class="icon-euro"></i>&nbsp;Liquida</button>';
		}
	}

	$data .='<td class="text-center">'.$oreString.'</td><td>'.$diariaString.'&nbsp;&nbsp;'.$diariaIcon .'</td>';
	$data .='</tr>';
}

$data .= '</tbody></table></div>';
echo $data;
?>
