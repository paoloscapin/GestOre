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
$soloNuovi = $_GET["soloNuovi"];
$docente_filtro_id = $_GET["docente_filtro_id"];
$materia_filtro_id = $_GET["materia_filtro_id"];

$direzioneOrdinamento="ASC";

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="text-center col-md-1">Data</th>
						<th class="text-center col-md-1">Ora</th>
						<th class="text-center col-md-2">Materia</th>
						<th class="text-center col-md-2">Docente</th>
						<th class="text-center col-md-2">Argomento</th>
						<th class="text-center col-md-1">Luogo</th>
						<th class="text-center col-md-1">Classe</th>
						<th class="text-center col-md-1">Studenti</th>
						<th class="text-center col-md-1">Iscrizione</th>
					</tr>
					</thead>';

$query = "	SELECT
				sportello.id AS sportello_id,
				sportello.data AS sportello_data,
				sportello.ora AS sportello_ora,
				sportello.numero_ore AS sportello_numero_ore,
				sportello.argomento AS sportello_argomento,
				sportello.luogo AS sportello_luogo,
				sportello.classe AS sportello_classe,
				sportello.firmato AS sportello_firmato,
				sportello.cancellato AS sportello_cancellato,
				sportello.categoria AS sportello_categoria,
				sportello.online AS sportello_online,
				sportello.max_iscrizioni AS sportello_max_iscrizioni,
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
			AND NOT sportello.cancellato
			";

if( $materia_filtro_id > 0) {
	$query .= "AND sportello.materia_id = $materia_filtro_id ";
}
if( $docente_filtro_id > 0) {
	$query .= "AND sportello.docente_id = $docente_filtro_id ";
}
if( ! $ancheCancellati) {
	$query .= "AND NOT sportello.cancellato ";
}
if( $soloNuovi) {
	$query .= "AND sportello.data >= CURDATE() ";
}
$query .= "ORDER BY sportello.data $direzioneOrdinamento, docente_cognome ASC,docente_nome ASC";

$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}
foreach($resultArray as $row) {
	$sportello_id = $row['sportello_id'];
	$todayDate = new DateTime ("today");
	$sportelloDate = new DateTime ($row['sportello_data']);
	$passato = ($sportelloDate < $todayDate);

	$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
	$dataSportello = utf8_encode( strftime("%d %B %Y", strtotime($row['sportello_data'])));
	setlocale(LC_TIME, $oldLocale);

	// se ci sono prenotazioni, cerca la lista di studenti che sono prenotati
	$studenteTip = '';
	if ($row['numero_studenti'] > 0) {
		$query2 = "SELECT
				sportello_studente.id AS sportello_studente_id,
				sportello_studente.iscritto AS sportello_studente_iscritto,
				sportello_studente.presente AS sportello_studente_presente,
				sportello_studente.note AS sportello_studente_note,

				studente.cognome AS studente_cognome,
				studente.nome AS studente_nome,
				studente.classe AS studente_classe,
				studente.id AS studente_id

			FROM
				sportello_studente
			INNER JOIN studente
			ON sportello_studente.studente_id = studente.id
			WHERE sportello_studente.sportello_id = '$sportello_id';";

		$studenti = dbGetAll($query2);
		foreach($studenti as $studente) {
			if (getSettingsValue('sportelli','nascondiNomeStudenti', false)) {
				$studenteTip = $studenteTip . '---' . " " . '---' ." " . $studente['studente_classe'] . "</br>";
			} else {
				$studenteTip = $studenteTip . $studente['studente_cognome'] . " " . $studente['studente_nome'] ." " . $studente['studente_classe'] . "</br>";
			}
		}
	}

	// marker per eventuali sportelli online
	$luogo_or_onine_marker = $row['sportello_luogo'];
	if ($row['sportello_online']) {
		$luogo_or_onine_marker = '<span class="label label-danger">online</span>';
	} else {
		debug("online=".$row['sportello_online']);
	}

	$data .= '<tr>
		<td>'.$dataSportello.'</td>
		<td>'.$row['sportello_ora']. ' &nbsp;&nbsp;&nbsp;('.$row['sportello_numero_ore']. ($row['sportello_numero_ore'] > 1? ' ore)' : ' ora)').'</td>
		<td>'.$row['materia_nome'].'</td>
		<td>'.$row['docente_nome'].' '.$row['docente_cognome'].'</td>
		<td>'.$row['sportello_argomento'].'</td>
		<td>'.$luogo_or_onine_marker.'</td>
		<td>'.$row['sportello_classe'].'</td>
		<td data-toggle="tooltip" data-placement="left" data-html="true" title="'.$studenteTip.'">'.$row['numero_studenti'].'</td>
		';

	// apri l'ultima colonna
	$data .= '<td class="text-center">';

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

		// prende la data di oggi e quella dello sportello
		$today = new DateTime('today');
		$dataSportello = $row['sportello_data'];

		// controlla quanti giorni prima chiudono le iscrizioni ( 0 = la mezzanotte del giorno precedente allo sportello)
		$daysInAdvance = getSettingsValue('sportelli', 'chiusuraIscrizioniGiorni', '1');
		$oraChiusura = getSettingsValue('sportelli', 'chiusuraIscrizioniOra', '24');

		// 1 days ago = la mezzanotte del giorno prima, 2 days ago = la mezzanotte di due giorni prima. Quindi bisogna aggiungere 1 per il controllo
		$daysAgo = $daysInAdvance;

		// calcola l'ultimo giorno in cui lo sportello può essere prenotato: 1 day ago vuole dire fino alla notte del giorno precedente, quindi è il minimo da considerare
		$lastDay = new DateTime($dataSportello.' '.$daysAgo.' days ago');

		// calcola la data del lunedi della settimana precedente a quella dello sportello e scopre se siamo dopo quel giorno
		$previousMonday = new DateTime($dataSportello.' Monday ago');
		$todayAfterpreviousMonday = ($today >= $previousMonday);

		// se non configurato per prenotare al massimo la settimana successiva, considera come se oggi fosse comunque una data dopo il lunedi della settimana precedente
		if (! getSettingsValue('sportelli','prenotaMaxSettimanaSuccessiva', true)) {
			$todayAfterpreviousMonday = true;
		}

		// ora puo' controllare se oggi viene prima dell'ultimo giorno valido per la prenotazione (o lo stesso giorno) considerando anche l'ora
		$todayBeforeLastDay = ($today <= $lastDay);

		// se esiste l'ora, considera anche quella
		$todayAndTime = new DateTime();
		$lastDayAndTime = $lastDay->add(DateInterval::createFromDateString($oraChiusura.' hour'));
		$todayAndTimeBeforeLastDayAndTime = ($todayAndTime <= $lastDayAndTime);

		// lo sportello si puo' prenotare se oggi e' >= al primo lunedi' da cui si puo' prenotare e <= all'ultimo giorno di prenotazione (considerando anche l'ora)
		$prenotabile = ($todayAfterpreviousMonday && $todayAndTimeBeforeLastDayAndTime);

		// e' cancellabile se oggi e' <= all'ultimo giorno di prenotazione
		$cancellabile = $todayAndTimeBeforeLastDayAndTime;

		// debug("today=".$today->format('d-m-Y H:i:s'));
		// debug("dataSportello=".$dataSportello);
		// debug("daysInAdvance=".$daysInAdvance);
		// debug("oraChiusura=".$oraChiusura);
		// debug("daysAgo=".$daysAgo);
		// debug("lastDay=".$lastDay->format('d-m-Y H:i:s'));
		// debug("previousMonday=".$previousMonday->format('d-m-Y H:i:s'));
		// debug("todayAfterpreviousMonday=".$todayAfterpreviousMonday);
		// debug("todayBeforeLastDay=".$todayBeforeLastDay);
		// debug("prenotabile=".$prenotabile);
		// debug("cancellabile=".$cancellabile);
		// debug("todayAndTime=".$todayAndTime->format('d-m-Y H:i:s'));
		// debug("lastDayAndTime=".$lastDayAndTime->format('d-m-Y H:i:s'));
		// debug("todayAndTimeBeforeLastDayAndTime=".$todayAndTimeBeforeLastDayAndTime);

		// controlla che non sia stato raggiunto il massimo numero di prenotazioni
		$max_iscrizioni = $row['sportello_max_iscrizioni'];
		if ($max_iscrizioni == null && $row['sportello_categoria'] == 'sportello didattico') {
			$max_iscrizioni = getSettingsValue('sportelli','numero_max_prenotazioni', 10);
		}
		// debug("max_iscrizioni=".$max_iscrizioni);
		// debug("numero_studenti=".$row['numero_studenti']);

		// zero o null significa nessun limite, altrimenti controlla quanti ce ne sono
		if ($max_iscrizioni != null && $max_iscrizioni > 0 && $max_iscrizioni <= $row['numero_studenti']) {
			$prenotabile = false;
		}

		// la didattica puo' inserire la prenotazione sempre e puo' sempre cancellare
		if (haRuolo('segreteria-didattica')) {
			$prenotabile = true;
			$cancellabile = true;
		}
		
		// debug("final prenotabile=".$prenotabile);
		// debug("final cancellabile=".$cancellabile);

		// per quelli non passati, se sono iscritto lo dice e mi lascia cancellare, altrimenti mi lascia iscrivere se non sono scaduti i termini
		if ($row['iscritto']) {
			$data .='
				<span class="label label-success">Iscritto</span>
				';
				if ($cancellabile) {
					$data .='
						<button onclick="sportelloCancellaIscrizione('.$row['sportello_id'].', \''.addslashes($row['materia_nome']).'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
						';
				}
			} else {
			if ($prenotabile) {
				$data .='
					<span class="label label-info">Disponibile</span>
					<button onclick="sportelloIscriviti('.$row['sportello_id'].', \''.addslashes($row['materia_nome']).'\', \''.addslashes($row['sportello_argomento']).'\')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
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
