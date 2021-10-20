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

// non abbiamo una sessione per cui calcola l'id dell'anno scolastico
$anno_scolastico_corrente_id = dbGetValue("SELECT anno_scolastico_id FROM anno_scolastico_corrente");

// per prima cosa determina quale è la data da controllare
$daysInAdvance = getSettingsValue('sportelli', 'chiusuraIscrizioniGiorni', '1');
$dateToCheck = date('Y-m-d', strtotime(' + ' . $daysInAdvance . ' days'));

// controlla la data dell'ultimo controllo effettuato
$lastCheckDate = dbGetValue("SELECT ultimo_controllo_sportelli FROM config");

// se si tratta della stessa data non deve fare nulla
if ($lastCheckDate == $dateToCheck) {
	debug('controllo sportello già effettuato per la data ' . $dateToCheck);
	return;
}

info('inizio controllo sportello per la data ' . $dateToCheck);
$query = "	SELECT
				sportello.id AS sportello_id,
				sportello.data AS sportello_data,
				sportello.ora AS sportello_ora,
				sportello.numero_ore AS sportello_numero_ore,
				sportello.luogo AS sportello_luogo,
				sportello.classe AS sportello_classe,
				sportello.argomento AS sportello_argomento,
				sportello.firmato AS sportello_firmato,
				sportello.online AS sportello_online,
				sportello.cancellato AS sportello_cancellato,
				materia.nome AS materia_nome,
				docente.cognome AS docente_cognome,
				docente.nome AS docente_nome,
				docente.email AS docente_email,
				(	SELECT COUNT(*) FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id) AS numero_studenti
			FROM sportello sportello
			INNER JOIN docente docente
			ON sportello.docente_id = docente.id
			INNER JOIN materia materia
			ON sportello.materia_id = materia.id
			WHERE sportello.anno_scolastico_id = $anno_scolastico_corrente_id
			AND NOT sportello.cancellato
			AND sportello.data = '$dateToCheck' ;
			";

foreach(dbGetAll($query) as $sportello) {
	$sportello_id = $sportello['sportello_id'];
	
	$nomeCognome = $sportello['docente_nome'] . ' ' . $sportello['docente_cognome'];
	$sportelloMateria = $sportello['materia_nome'];
	$sportelloData = $sportello['sportello_data'];
	$sportelloOra = $sportello['sportello_ora'];
	$sportelloArgomento = $sportello['sportello_argomento'];
	$sportelloNumeroOre = $sportello['sportello_numero_ore'];
	$sportelloNumeroStudenti = $sportello['numero_studenti'];

	$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
	$dateString = utf8_encode( strftime("%A, %d %B %Y", strtotime($sportelloData)));
	setlocale(LC_TIME, $oldLocale);

	debug('sportelloData='.$sportelloData);
	debug('dateString='.$dateString);

	$dicituraSportello = "sportello di " . $sportelloMateria . ' di ' . $dateString . ' alle ' . $sportelloOra . ' (durata ' . $sportelloNumeroOre . ' ore)';

	$text_msg = "Gentile $nomeCognome, allo sportello $dicituraSportello risultano iscritti $sportelloNumeroStudenti studenti";
	$html_msg = "
	<html>
	<head>
	<style>
	#student {
	  font-family: Arial, Helvetica, sans-serif;
	  border-collapse: collapse;
	  width: 100%;
	}
	#student td, #student th {
	  border: 1px solid #ddd;
	  padding: 6px;
	}
	#student tr:nth-child(even){background-color: #f2f2f2;}
	#student tr:hover {background-color: #ddd;}
	#student th {
	  padding-top: 6px;
	  padding-bottom: 6px;
	  text-align: left;
	  background-color: #04AA6D;
	  color: white;
	}
	</style>
	</head>
	<body>
	<p>Sportello: $dateString - $sportelloOra (durata $sportelloNumeroOre ore)<br>
	Docente: $nomeCognome<br>
	Materia: $sportelloMateria</p>
	<hr>
	<p>Argomento: <strong>$sportelloArgomento</strong></p>
	<p>Studenti: ($sportelloNumeroStudenti):</br>
	<table id=\"student\">
	<tr><th>Studente</th><th>Classe</th><th>Argomento</th><tr>
";
	
	foreach(dbGetAll("SELECT * FROM studente INNER JOIN sportello_studente ON sportello_studente.studente_id = studente.id  where sportello_studente.sportello_id = '$sportello_id';") as $studente) {
		$html_msg .=  "<tr><td>" . $studente['cognome'] . " " . $studente['nome'] ."</td><td>" . $studente['classe']."</td><td>".$studente['argomento'].'</td></tr>';
	}

	$html_msg .= '</table></p></body></html>';

	// invia la email al docente
	$to = $sportello['docente_email'];
	if ($sportelloNumeroStudenti > 0) {
		$subject = "Conferma $dicituraSportello";
	} else {
		$subject = "Annullato $dicituraSportello";
		$text_msg = "Gentile $nomeCognome, lo sportello $dicituraSportello viene annullato perché non risultano iscritti";
		$html_msg = "<html><body><p><strong>Annullamento Sportello</strong></p><p>Gentile $nomeCognome, lo sportello $dicituraSportello viene annullato perché non risultano studenti iscritti</p>";
	}
	$sender = $__settings->local->emailNoReplyFrom;
	$headers = "From: $sender\n";
	$headers .= "MIME-Version: 1.0\n";
	$headers .= "Content-Type: text/html; charset=\"UTF-8\"\n";
	$headers .= "Content-Transfer-Encoding: 8bit\n";
	$headers .= "X-Mailer: PHP " . phpversion();

	$connection = 'http';
	if ($__settings->system->https) {
		$connection = 'https';
	}
	$url = "$connection://$_SERVER[HTTP_HOST]".$__application_base_path . '/index.php';
	
	// Imposta il Return-Path (funziona solo su hosting Windows)
	ini_set("sendmail_from", $sender);
	
	// Invia il messaggio, il quinto parametro "-f$sender" imposta il Return-Path su hosting Linux
	if (mail($to, $subject, $html_msg, $headers, "-f$sender")) {
		info("email inviata correttamente a " . $to . " oggetto: " . $subject);
	} else {
		warning("errore nell'invio della email a " . $to . " oggetto: " . $subject);
	}
}

// effettuato il controllo deve solo aggiornare la data di ultimo controllo in config
dbExec("UPDATE config SET ultimo_controllo_sportelli = '$dateToCheck';");

info('terminato controllo sportello per la data ' . $dateToCheck);
?>
