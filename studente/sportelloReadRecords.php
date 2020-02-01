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

$ancheCancellati = $_GET["ancheCancellati"];
// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="text-center col-md-2">Data</th>
						<th class="text-center col-md-1">Ora</th>
						<th class="text-center col-md-2">Materia</th>
						<th class="text-center col-md-2">Docente</th>
						<th class="text-center col-md-1">Ore</th>
						<th class="text-center col-md-1">Classe</th>
						<th class="text-center col-md-1">Stato</th>
						<th class="text-center col-md-1">Studenti</th>
						<th class="text-center col-md-1">Iscritto</th>
					</tr>
					</thead>';

$query = "	SELECT
				sportello.id AS sportello_id,
				sportello.data AS sportello_data,
				sportello.ora AS sportello_ora,
				sportello.numero_ore AS sportello_numero_ore,
				sportello.luogo AS sportello_luogo,
				sportello.classe AS sportello_classe,
				sportello.firmato AS sportello_firmato,
				sportello.cancellato AS sportello_cancellato,
				materia.nome AS materia_nome,
				docente.cognome AS docente_cognome,
				docente.nome AS docente_nome,
				(	SELECT COUNT(*) FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id) AS numero_studenti,
				(	SELECT sportello_studente.iscritto FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.studente_id = $__studente_id) AS iscritto,
				(	SELECT sportello_studente.presente FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.studente_id = $__studente_id) AS presente
			FROM sportello sportello
			INNER JOIN docente docente ON sportello.docente_id = docente.id
			INNER JOIN materia materia ON sportello.materia_id = materia.id
			WHERE sportello.anno_scolastico_id = $__anno_scolastico_corrente_id
			";

if( ! $ancheCancellati) {
	$query .= "AND NOT viaggio.cancellato ";
}
$query .= "ORDER BY sportello.data DESC, docente_cognome ASC,docente_nome ASC";

$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}
foreach($resultArray as $row) {
	$passato = false;
	// date('d.m.Y',strtotime("-1 days"));
	if (strtotime($row['sportello_data']) < strtotime('now')) {
		$passato = true;
	}

	$cancellatoMarker = '';
	$cancellato = false;
	if ($row['sportello_cancellato']) {
		$statoMarker = '<span class="label label-danger">cancellato</span>';
		$cancellato = true;
	} else if ($row['sportello_firmato']) {
		$statoMarker = '<span class="label label-success">effettuato</span>';
	}  else if (! $passato) {
		$statoMarker = '<span class="label label-info">disponibile</span>';
	}

	$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
	$dataSportello = utf8_encode( strftime("%d %B %Y", strtotime($row['sportello_data'])));
	setlocale(LC_TIME, $oldLocale);

	$data .= '<tr>
		<td>'.$dataSportello.'</td>
		<td>'.$row['sportello_ora'].'</td>
		<td>'.$row['materia_nome'].'</td>
		<td>'.$row['docente_nome'].' '.$row['docente_cognome'].'</td>
		<td>'.$row['sportello_numero_ore'].'</td>
		<td>'.$row['sportello_classe'].'</td>
		<td>'.$statoMarker.'</td>
		<td>'.$row['numero_studenti'].'</td>
		';


	// apri l'ultima colonna
	$data .= '<td class="text-center">';

	// per quelli cancellati non scrive nulla
	if (!$cancellato) {
		// per prima cosa considera quelli passati
		if ($passato) {
			if ($row['presente']) {
				$data .='<span class="label label-success">Presente</span>';
			} else {
				if ($row['iscritto']) {
					debug('iscritto');
					$data .='<span class="label label-danger">Assente</span>';
				}
				// se passato e non ero iscritto non deve segnalare nulla
			}
		} else {
			// per quelli non passati, se sono iscritto lo dice e mi lascia cancellare, altrimenti mi lascia iscrivere
			if ($row['iscritto']) {
				$data .='
					<span class="label label-success">Iscritto</span>
					<button onclick="sportelloCancellaIscrizione('.$row['sportello_id'].', \''.$row['materia_nome'].'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
					';
				} else {
					$data .='
						<span class="label label-info">Disponibile</span>
						<button onclick="sportelloIscriviti('.$row['sportello_id'].', \''.$row['materia_nome'].'\')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
					';
			}

		}
	}

	// chiudi l'ultima colonna e la riga
	$data .= '</td></tr>';
}

$data .= '</table></div>';

echo $data;
?>

