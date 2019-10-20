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

$ancheChiusi = $_GET["ancheChiusi"];

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th>Data</th>
						<th>Docente</th>
						<th>Destinazione</th>
						<th>Stato</th>
						<th class="text-center">Modifica</th>
						<th class="text-center">Stampa</th>
						<th class="text-center">Email</th>
						<th class="text-center">Chiudi</th>
						<th class="text-center">Rimborsa</th>
					</tr>
					</thead>';
					
$query = "	SELECT
				viaggio.id AS viaggio_id,
				viaggio.data_partenza AS viaggio_data_partenza,
				viaggio.data_rientro AS viaggio_data_rientro,
				viaggio.destinazione AS viaggio_destinazione,
				viaggio.stato AS viaggio_stato,
				viaggio.rimborsato AS viaggio_rimborsato,
				docente.cognome AS docente_cognome,
				docente.nome AS docente_nome
			FROM viaggio viaggio
			INNER JOIN docente docente
			ON viaggio.docente_id = docente.id
			WHERE viaggio.anno_scolastico_id = $__anno_scolastico_corrente_id
			";

if( ! $ancheChiusi) {
	$query .= "AND NOT viaggio.stato = 'chiuso' ";
}
$query .= "order by viaggio_data_partenza DESC, docente_cognome ASC,docente_nome ASC";

$resultArray = dbGetAll($query);

foreach($resultArray as $row) {
	$statoMarker = '';
	switch ($row['viaggio_stato']) {
		case "assegnato":
			$statoMarker = '<span class="label label-info">assegnato</span>';
			break;
		case "accettato":
			$statoMarker = '<span class="label label-success">accettato</span>';
			break;
		case "effettuato":
			$statoMarker = '<span class="label label-warning">effettuato</span>';
			break;
		case "evaso":
			$statoMarker = '<span class="label label-primary">evaso</span>';
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
	$dataPartenza = utf8_encode( strftime("%d %B %Y", strtotime($row['viaggio_data_partenza'])));
	setlocale(LC_TIME, $oldLocale);
	$data .= '<tr>
		<td>'.$dataPartenza.'</td>
		<td>'.$row['docente_nome'].' '.$row['docente_cognome'].'</td>
		<td>'.$row['viaggio_destinazione'].'</td>
		<td>'.$statoMarker.'</td>
		';
	$data .='<td class="text-center">
		<button onclick="viaggioGetDetails('.$row['viaggio_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
		<button onclick="viaggioDelete('.$row['viaggio_id'].', \''.$row['viaggio_data_partenza'].'\', \''.$row['viaggio_destinazione'].'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
		</td>
		<td class="text-center">
		<button onclick="viaggioStampaNomina('.$row['viaggio_id'].')" class="btn btn-teal4 btn-xs"><span class="glyphicon glyphicon-save-file"></button>
		</td>';
	// se assegnato, posso inviare la email
	if ($row['viaggio_stato'] == "assegnato") {
		$data .='
			<td class="text-center">
			<button onclick="viaggioNominaEmail('.$row['viaggio_id'].')" class="btn btn-lightblue4 btn-xs"><span class="glyphicon glyphicon-envelope"></button>
			</td>';
	} else {
		$data .='<td></td>';
	}
	// se effettuato, allora lo posso chiudere
	if ($row['viaggio_stato'] == "effettuato") {
		$data .='
			<td class="text-center">
			<button onclick="viaggioChiusura('.$row['viaggio_id'].')" class="btn btn-deeporange4 btn-xs"><span class="glyphicon glyphicon-collapse-down"></button>
			</td>';
	} else {
		$data .='<td></td>';
	}
	// se effettuato o chiuso e non ancora rimborsato, allora lo posso rimborsare
	// controllo di non averlo ancora rimborsato
	$viaggioId = $row['viaggio_id'];
	$numSpese = dbGetValue("SELECT COUNT(id) FROM `spesa_viaggio` WHERE viaggio_id=$viaggioId;");
	if (($row['viaggio_stato'] == "effettuato" || $row['viaggio_stato'] == "chiuso") && ! $row['viaggio_rimborsato'] && $numSpese > 0) {
		$data .='
			<td class="text-center">
			<button onclick="viaggioRimborso('.$row['viaggio_id'].')" class="btn btn-lima4 btn-xs"><span class="glyphicon glyphicon-euro"></button>
			</td>';
	} else {
		$data .='<td></td>';
	}
	$data .='</tr>';
}

$data .= '</table></div>';

echo $data;
?>

