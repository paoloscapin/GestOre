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

$anchePubblicati = $_GET["anchePubblicati"];
$soloNotificati = $_GET["soloNotificati"];
$anno_filtro_id = $_GET["anno_filtro_id"];
$materia_filtro_id = $_GET["materia_filtro_id"];
$docente_filtro_id = $_GET["docente_filtro_id"];
$stato_filtro_id = $_GET["stato_filtro_id"];

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="text-center col-md-2">Studente</th>
						<th class="text-center col-md-1">Anno Scolastico</th>
						<th class="text-center col-md-2">Materia</th>
						<th class="text-center col-md-1">Classe</th>
						<th class="text-center col-md-2">Docente</th>
						<th class="text-center col-md-1">Stato</th>
						<th class="text-center col-md-1">Moduli</th>
						<th class="text-center col-md-2"></th>
					</tr>
					</thead>';
					
$query = "	SELECT
				piano_di_lavoro.id AS piano_di_lavoro_id, piano_di_lavoro.*, materia.nome AS materia_nome, docente.cognome AS docente_cognome, docente.nome AS docente_nome, indirizzo.nome_breve AS indirizzo_nome_breve , anno_scolastico.anno AS anno, studente.cognome AS studente_cognome, studente.nome AS studente_nome, studente.classe AS studente_classe FROM piano_di_lavoro piano_di_lavoro
			INNER JOIN docente docente
			ON piano_di_lavoro.docente_id = docente.id
			INNER JOIN materia materia
			ON piano_di_lavoro.materia_id = materia.id
			INNER JOIN indirizzo indirizzo
			ON piano_di_lavoro.indirizzo_id = indirizzo.id
			INNER JOIN anno_scolastico anno_scolastico
			ON piano_di_lavoro.anno_scolastico_id = anno_scolastico.id
			LEFT JOIN studente studente
			ON piano_di_lavoro.studente_id = studente.id
			WHERE 1 = 1
			";

if( $materia_filtro_id > 0) {
	$query .= "AND piano_di_lavoro.materia_id = $materia_filtro_id ";
}

if( $anno_filtro_id > 0) {
	$query .= "AND piano_di_lavoro.anno_scolastico_id = $anno_filtro_id ";
}

if( $docente_filtro_id > 0) {
	$query .= "AND piano_di_lavoro.docente_id = $docente_filtro_id ";
}

if( $stato_filtro_id != '0') {
	$query .= "AND piano_di_lavoro.stato = '$stato_filtro_id' ";
}

if( $soloNotificati) {
	$query .= " AND piano_di_lavoro.stato = 'notificato' ";
}

$query .= " AND piano_di_lavoro.carenza ";

$query .= " ORDER BY docente.cognome ASC, docente.nome ASC, piano_di_lavoro.creazione ASC";

foreach(dbGetAll($query) as $row) {

	$piano_di_lavoro_id = $row['piano_di_lavoro_id'];

	$statoMarker = '';
	if ($row['stato'] == 'draft') {
		$statoMarker .= '<span class="label label-warning">draft</span>';
	} elseif ($row['stato'] == 'annullato') {
		$statoMarker .= '<span class="label label-danger">annullato</span>';
	} elseif ($row['stato'] == 'finale') {
		$statoMarker .= '<span class="label label-success">finale</span>';
	} elseif ($row['stato'] == 'pubblicato') {
		$statoMarker .= '<span class="label label-info">pubblicato</span>';
	} elseif ($row['stato'] == 'notificato') {
		$statoMarker .= '<span class="label label-success">notificato</span>';
	}

	$templateMarker = '';
	if ($row['template'] == true) {
		$templateMarker .= '<span class="label label-success">'.getLabel('Template').'</span>';
	}

	$clilMarker = '';
	if ($row['clil'] == true) {
		$clilMarker .= '<span class="label label-info">Clil</span>';
	}

	$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
	$dataCreazione = utf8_encode( strftime("%d %B %Y", strtotime($row['creazione'])));
	$dataUltimaModifica = utf8_encode( strftime("%d %B %Y", strtotime($row['ultima_modifica'])));
	setlocale(LC_TIME, $oldLocale);

	$classe = $row['classe'].$row['indirizzo_nome_breve'].$row['sezione'];

	$docenteCognomeNome = $row['docente_cognome'] . ' ' . $row['docente_nome'];

	$studente = $row['studente_cognome'] . ' ' . $row['studente_nome'] . ' (' . $row['studente_classe'] . ')';

	$data .= '<tr>
		<td>'.$studente.'</td>
		<td>'.$row['anno'].'</td>
		<td>'.$row['materia_nome'].'</td>
		<td>'.$classe.'</td>
		<td>'.$docenteCognomeNome.'</td>
		<td class="text-center">'.$templateMarker.'&nbsp;'.$clilMarker.'&nbsp;'.$statoMarker;

		// controlla se il dirigente ha abilitato l'invio della email
		if ($__config->getEmail_carenze_aperto()) {
			// se e' gia' stata inviata, non appare il bottone della email
			if ($row['stato'] != 'notificato') {
				$data .= '<button onclick="carenzaEmailPdf('.$row['piano_di_lavoro_id'].')" class="btn btn-deeporange4 btn-xs"><span class="glyphicon glyphicon-envelope"></span>&nbsp;email</button>';
			}
		}
		$data .='</td>';

	$data .='<td class="text-center">';
	// se e' gia' stata inviata, non appare il bottone Moduli
	if ($row['stato'] != 'notificato') {
		$data .='<button onclick="carenzaOpenDocument('.$row['piano_di_lavoro_id'].')" class="btn btn-teal4 btn-xs"><span class="glyphicon glyphicon-file">&nbsp;Moduli</span></button>';
	}
	$data .='</td>';
	$data .='<td class="text-center">
		<button onclick="carenzaPreview('.$row['piano_di_lavoro_id'].')" class="btn btn-info btn-xs"><span class="glyphicon glyphicon-blackboard"></span>&nbsp;Preview</button>
		<button onclick="carenzaDuplicate('.$row['piano_di_lavoro_id'].')" class="btn btn-yellow4 btn-xs"><span class="glyphicon glyphicon-copy">&nbsp;Duplica</span></button>
		<button onclick="carenzaSavePdf('.$row['piano_di_lavoro_id'].')" class="btn btn-orange4 btn-xs" style="display: inline-flex;align-items: center;"><i class="icon-play"></i>&nbsp;Pdf</button>
		<button onclick="carenzaGetDetails('.$row['piano_di_lavoro_id'].')" class="btn btn-warning btn-xs" '.(($row['stato'] == 'notificato') ? 'disabled = "disabled"' : '' ).'><span class="glyphicon glyphicon-pencil"></span></button>
		<button onclick="carenzaDelete('.$row['piano_di_lavoro_id'].', \''.$row['materia_nome'].'\', \''.$studente.'\')" class="btn btn-danger btn-xs"  '.(($row['stato'] == 'notificato') ? 'disabled = "disabled"' : '' ).'><span class="glyphicon glyphicon-trash"></button>
		</td>
		</tr>';
}

$data .= '</table></div>';
echo $data;
?>

