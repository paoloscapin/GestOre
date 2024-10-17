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
$classe_filtro_id = $_GET["classe_filtro_id"];

$direzioneOrdinamento="ASC";

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="text-center col-md-1">Categoria</th>
						<th class="text-center col-md-1">Data</th>
						<th class="text-center col-md-1">Ora</th>
						<th class="text-center col-md-2">Materia</th>
						<th class="text-center col-md-1">Docente</th>
						<th class="text-center col-md-2">Argomento</th>
						<th class="text-center col-md-1">Luogo</th>
						<th class="text-center col-md-1">Classe</th>
						<th class="text-center col-md-1">Posti disponibili</th>
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
				docente.email AS docente_email,
				(	SELECT COUNT(*) FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id) AS numero_studenti,
				(	SELECT sportello_studente.iscritto FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.studente_id = $__studente_id) AS iscritto,
				(	SELECT sportello_studente.presente FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.studente_id = $__studente_id) AS presente,
				(	SELECT sportello_studente.argomento FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.studente_id = $__studente_id) AS argomento,
				(	SELECT studente.cognome FROM studente WHERE id = $__studente_id) AS studente_cognome,
				(	SELECT studente.nome FROM studente WHERE id = $__studente_id) AS studente_nome,
				(	SELECT studente.email FROM studente WHERE id = $__studente_id) AS studente_email,
				(	SELECT studente.classe FROM studente WHERE id = $__studente_id) AS studente_classe
			FROM sportello sportello
			INNER JOIN docente docente ON sportello.docente_id = docente.id
			INNER JOIN materia materia ON sportello.materia_id = materia.id
			INNER JOIN classe classe ON sportello.classe_id = classe.id
			WHERE sportello.anno_scolastico_id = $__anno_scolastico_corrente_id
			";

// rimossa riga da query visto che compare già qui sotto AND NOT sportello.cancellato

if( $classe_filtro_id > 0) {
	$query .= "AND sportello.classe_id = $classe_filtro_id ";
}
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
	$sportello_categoria = $row['sportello_categoria'];
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
	} 

	// calcola il numero di posti disponibili per lo sportello
	$max_iscrizioni = $row['sportello_max_iscrizioni'];
	$posti_disponibili = $max_iscrizioni - $row['numero_studenti'];
	$sportello_cancellato = $row['sportello_cancellato'];
	if (!$sportello_cancellato)
	{
	$data .= '<tr>
		<td align="center">'.$sportello_categoria.'</td>
		<td align="center">'.$dataSportello.'</td>
		<td align="center">'.$row['sportello_ora']. ' &nbsp;&nbsp;&nbsp;('.$row['sportello_numero_ore']. ($row['sportello_numero_ore'] > 1? ' ore)' : ' ora)').'</td>
		<td>'.$row['materia_nome'].'</td>
		<td align="center">'.$row['docente_nome'].' '.$row['docente_cognome'].'</td>
		';
		if ($row['sportello_argomento'] == "")
		{
			if ($row['argomento'] == "")
			{
				$data .= '<td></td>';
			}
			else
			{
				$data .= '<td data-toggle="tooltip" data-placement="left" data-html="true" title="Argomento da te indicato.<br><br><b>RICORDA</b><br>Ogni studente iscritto allo sportello<br>indica il proprio argomento.">' . $row['argomento'] . '</td>';				
			}
		}
		else
		{
			$data .= '<td data-toggle="tooltip" data-placement="left" data-html="true" title="Argomento scelto dal docente<br><br><b>RICORDA</b><br>Per questo tipo di sportello l\'argomento<br>è deciso dal docente">' . $row['sportello_argomento'] . '</td>';
		}
		$data .= '
		<td align="center">'.$luogo_or_onine_marker.'</td>
		<td align="center">'.$row['sportello_classe'].'</td>
		<td align="center" data-toggle="tooltip" data-placement="left" data-html="true" title="'.$studenteTip.'">'.$posti_disponibili.'</td>
		';
	}
	else
	{
		$data .= '<tr>
		<td align="center"><s>'.$sportello_categoria.'</td>
		<td align="center"><s>'.$dataSportello.'</td>
		<td align="center"><s>'.$row['sportello_ora']. ' &nbsp;&nbsp;&nbsp;('.$row['sportello_numero_ore']. ($row['sportello_numero_ore'] > 1? ' ore)' : ' ora)').'</td>
		<td><s>'.$row['materia_nome'].'</td>
		<td align="center"><s>'.$row['docente_nome'].' '.$row['docente_cognome'].'</td>
		';
		if ($row['sportello_argomento'] == "")
		{
			if ($row['argomento'] == "")
			{
				$data .= '<td></td>';
			}
			else
			{
				$data .= '<td data-toggle="tooltip" data-placement="left" data-html="true" title="Argomento da te indicato.<br><br><b>RICORDA</b><br>Ogni studente iscritto allo sportello<br>indica il proprio argomento.">' . $row['argomento'] . '</td>';				
			}
		}
		else
		{
			$data .= '<td data-toggle="tooltip" data-placement="left" data-html="true" title="Argomento scelto dal docente<br><br><b>RICORDA</b><br>Per questo tipo di sportello l\'argomento<br>è deciso dal docente">' . $row['sportello_argomento'] . '</td>';
		}
		$data .= '
		<td align="center"><s>'.$luogo_or_onine_marker.'</td>
		<td align="center"><s>'.$row['sportello_classe'].'</td>
		<td align="center" data-toggle="tooltip" data-placement="left" data-html="true" title="<s>'.$studenteTip.'"><s>0</td>
		';
	}
	// apri l'ultima colonna
	$data .= '<td class="text-center">';

	// per prima cosa considera quelli passati
	if ($passato) {
		if ($row['presente']) {
			$data .='<div data-toggle="tooltip" data-placement="left"  title="La tua presenza è stata registrata"><span class="label label-success">Presente</span></div>';
		} else {
			if ($row['iscritto']) {
				debug('iscritto');
				$data .='<div data-toggle="tooltip" data-placement="left"  title="Sei risultato assente ad uno sportello a cui ti eri prenotato"><span class="label label-danger">Assente</span></div>';
			}
			else
			{
				$data .='<div data-toggle="tooltip" data-placement="left"  title="Sportello passato a cui ti sei iscritto"><span class="label label-default">Non iscritto</span></div>';
			}
			// se passato e non ero iscritto non deve segnalare nulla
		}
	} else {

		// prende la data di oggi e quella dello sportello
		$today = new DateTime('today');
		$dataSportello = $row['sportello_data'];

		// controlla quanti giorni prima chiudono le iscrizioni ( 0 = la mezzanotte del giorno precedente allo sportello)
		$daysInAdvance = getSettingsValue('sportelli', 'chiusuraIscrizioniGiorni', '1');

		// 1 days ago = la mezzanotte del giorno prima, 2 days ago = la mezzanotte di due giorni prima. Quindi bisogna aggiungere 1 per il controllo
		$daysAgo = $daysInAdvance + 1;

		// calcola l'ultimo giorno in cui lo sportello può essere prenotato: 1 day ago vuole dire fino alla notte del giorno precedente, quindi è il minimo da considerare
		$lastDay = new DateTime($dataSportello.' '.$daysAgo.' days ago');

		// calcola la data del lunedi della settimana precedente a quella dello sportello e scopre se siamo dopo quel giorno
		$previousMonday = new DateTime($dataSportello.' Monday ago');

		$todayAfterpreviousMonday = ($today >= $previousMonday);

		$dt_oggi= date_format($today,"Y-m-d");	
		$dt_lastday = date_format($lastDay,"Y-m-d");
		$vecchio = 0;
		$todayBeforeLastDay = 1;
		if ($dt_lastday <= $dt_oggi)
		{
			$vecchio = 1;
			$todayBeforeLastDay = 0;
		}
		info("LAST DAY " . $dt_lastday . " OGGI " . $dt_oggi . " BEFORE: " . $todayBeforeLastDay);

		// se non configurato per prenotare al massimo la settimana successiva, considera come se oggi fosse comunque una data dopo il lunedi della settimana precedente
		if (! getSettingsValue('sportelli','prenotaMaxSettimanaSuccessiva', true)) {
			$todayAfterpreviousMonday = true;
		}

		// ora puo' controllare se oggi viene prima dell'ultimo giorno valido per la prenotazione (o lo stesso giorno)
//		$todayBeforeLastDay = ($today <= $lastDay);

		// lo sportello si puo' prenotare se oggi e' >= al primo lunedi' da cui si puo' prenotare e <= all'ultimo giorno di prenotazione
		$prenotabile = ($todayAfterpreviousMonday && $todayBeforeLastDay && (!$sportello_cancellato));

		// e' cancellabile se oggi e' <= all'ultimo giorno di prenotazione
		$cancellabile = $todayBeforeLastDay;

		// debug("today=".$today->format('d-m-Y H:i:s'));
		// debug("dataSportello=".$dataSportello);
		// debug("daysInAdvance=".$daysInAdvance);
		// debug("daysAgo=".$daysAgo);
		// debug("lastDay=".$lastDay->format('d-m-Y H:i:s'));
		// debug("previousMonday=".$previousMonday->format('d-m-Y H:i:s'));
		// debug("todayAfterpreviousMonday=".$todayAfterpreviousMonday);
		// debug("todayBeforeLastDay=".$todayBeforeLastDay);
		// debug("prenotabile=".$prenotabile);
		// debug("cancellabile=".$cancellabile);

		// controlla che non sia stato raggiunto il massimo numero di prenotazioni

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
		if ($sportello_cancellato) {
			$data .='<div data-toggle="tooltip" data-placement="left"  title="Sportello annullato dal docente"><span class="label label-default">cancellato</span></div>';
		}
		else
		if ($row['iscritto']) 
			{
				if ($cancellabile) {
					$data .='
						<div data-toggle="tooltip" data-placement="left"  title="Clicca qui per cancellare la prenotazione"><span class="label label-success">Iscritto</span><button onclick="sportelloCancellaIscrizione('.$row['sportello_id'].', \''.addslashes($row['materia_nome']).'\', \''.addslashes($row['sportello_argomento']).'\',\''.addslashes($row['sportello_data']).'\',\''.addslashes($row['sportello_ora']).'\',\''.addslashes($row['sportello_numero_ore']).'\',\''.addslashes($row['sportello_luogo']).'\',\''.addslashes($row['studente_cognome']).'\',\''.addslashes($row['studente_nome']).'\',\''.addslashes($row['studente_email']).'\',\''.addslashes($row['studente_classe']).'\',\''.addslashes($row['docente_cognome']).'\',\''.addslashes($row['docente_nome']).'\',\''.addslashes($row['docente_email']).'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button></div>
						';
				}
				else
				{
					$data .='
						<div data-toggle="tooltip" data-placement="left"  title="Iscrizione non più cancellabile"><span class="label label-success">Iscritto</span></div>
						';
				}
			} else {
			if ($prenotabile) {
				$data .='
					<div data-toggle="tooltip" data-placement="left"  title="Clicca qui per iscriverti allo sportello"><span class="label label-primary">Disponibile</span>
					<button onclick="sportelloIscriviti('.$row['sportello_id'].', \''.addslashes($row['materia_nome']).'\', \''.addslashes($row['sportello_argomento']).'\',\''.addslashes($row['sportello_data']).'\',\''.addslashes($row['sportello_ora']).'\',\''.addslashes($row['sportello_numero_ore']).'\',\''.addslashes($row['sportello_luogo']).'\',\''.addslashes($row['studente_cognome']).'\',\''.addslashes($row['studente_nome']).'\',\''.addslashes($row['studente_email']).'\',\''.addslashes($row['studente_classe']).'\',\''.addslashes($row['docente_cognome']).'\',\''.addslashes($row['docente_nome']).'\',\''.addslashes($row['docente_email']).'\')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button></div>
					';
			}
			else 
			{
				if ($posti_disponibili == 0) 
				{
				$data .='
					<div data-toggle="tooltip" data-placement="left"  title="Posti disponibili esauriti"><span class="label label-danger">Posti esauriti</span></div>
					';
				}
				else
				{
					$tSportello = new DateTime($dataSportello);
					if ($tSportello<=$today)
					{
						$data .='
						<div data-toggle="tooltip" data-placement="left"  title="Iscrizioni chiuse"><span span class="label label-danger">Iscrizioni chiuse</span></div>
						';
					}
					else
					{
						$data .='
						<div data-toggle="tooltip" data-placement="left"  title="Prenotabile dal lunedì precedente"><span span class="label label-info">Non ancora prenotabile</span></div>
						';
					}
				}	
			}
		}
	}

	// chiudi l'ultima colonna e la riga
	$data .= '</td></tr>';
}
$data .= '</table></div>';

echo $data;
?>