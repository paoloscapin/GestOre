<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/connect.php';

$today = new DateTime("now");

$query = "	SELECT
				sportello.id AS sportello_id,
				sportello.ora AS sportello_ora,
				sportello.data AS sportello_data,
				sportello.cancellato AS sportello_cancellato,
				sportello.docente_id AS sportello_docente_id,
				sportello.materia_id AS sportello_materia_id,
				sportello.categoria AS sportello_categoria,
				sportello.luogo AS sportello_luogo,
				date_format(sportello.data,'%d/%m/%Y %H:%i:%s') AS data,

				docente.cognome AS docente_cognome,
				docente.nome AS docente_nome,
				docente.email AS docente_email,

				materia.nome AS sportello_materia
				FROM sportello
				INNER JOIN docente
				ON docente.id = sportello.docente_id
				INNER JOIN materia
				ON materia.id = sportello.materia_id
				WHERE
				date_format(sportello.data,'%Y%m%d') = ADDDATE(CURDATE(),1)
				AND NOT sportello.cancellato ";
				
$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}

foreach($resultArray as $row) 
{
	$data = "";
	$sportello_id = $row['sportello_id'];
	$sportello_ora = $row['sportello_ora'];
	$sportello_data = $row['sportello_data'];
	$sportello_cancellato = $row['sportello_cancellato'];
	$sportello_docente_id = $row['sportello_docente_id'];
	$sportello_docente_cognome = $row['docente_cognome'];
	$sportello_docente_nome = $row['docente_nome'];
	$sportello_docente_email = $row['docente_email'];
	$sportello_materia = $row['sportello_materia'];
	$sportello_categoria = strtoupper($row['sportello_categoria']);
	$sportello_luogo = $row['sportello_luogo'];

	$query = "SELECT COUNT(*) FROM sportello_studente WHERE sportello_studente.sportello_id = $sportello_id";

	$numero_studenti_iscritti = dbGetValue($query);

	info("dati sportello docente da inviare promemoria agli studenti:  DATA " . $data . " ORA " . $sportello_ora . " ID " . $sportello_id . " DOCENTE-ID " . $sportello_docente_id);

	if ($numero_studenti_iscritti>0)
	// CI SONO STUDENTI ISCRITTI - INVIO IL PROMEMORIA AGLI STUDENTI
	{

		$query = "	SELECT
					sportello_studente.sportello_id AS sportello_id,
					sportello_studente.studente_id AS sportello_studente_id,
					sportello_studente.iscritto AS sportello_iscritto,
					sportello_studente.argomento AS sportello_argomento,

					studente.cognome AS studente_cognome,
					studente.nome AS studente_nome,
					studente.email AS studente_email
					FROM sportello_studente
					INNER JOIN studente
					ON studente.id = sportello_studente.studente_id
					WHERE
					sportello_id = " . $sportello_id . "
					AND sportello_studente.iscritto = 1";
					
		$resultArray = dbGetAll($query);
		if ($resultArray == null) {
			$resultArray = [];
		}

		// inverto format data - giorno con mese
		$data_array = explode("-", $sportello_data);
		$sportello_data = $data_array[2] . "-" . $data_array[1] . "-" . $data_array[0];

		$data = "";
		$tabella_html = '';

		foreach($resultArray as $row) 
		{
			$studente_cognome = $row['studente_cognome'];
			$studente_nome = $row['studente_nome'];
			$studente_email = $row['studente_email'];
			$sportello_argomento = $row['sportello_argomento'];

			info("studenti iscritti sportello: COGNOME " . $studente_cognome . " NOME " . $studente_nome . " DATA ". $data);

			// preparo il testo della mail
			$full_mail_body = file_get_contents("template_mail_promemoria_studente.html");

			$full_mail_body = str_replace("{titolo}","PROMEMORIA ATTIVITA'<br>".$sportello_categoria,$full_mail_body);
			$full_mail_body = str_replace("{nome}",strtoupper($studente_cognome) . " " . strtoupper($studente_nome),$full_mail_body);
			$full_mail_body = str_replace("{messaggio}","questo è il promemoria per l'attività <br><h3 style='background-color:yellow; font-size:20px'><b><center>" . $sportello_categoria ."</center></b></h3>",$full_mail_body);
			$full_mail_body = str_replace("{data}",$sportello_data,$full_mail_body);
			$full_mail_body = str_replace("{ora}",$sportello_ora,$full_mail_body);
			$full_mail_body = str_replace("{docente}",strtoupper($sportello_docente_cognome . " " . $sportello_docente_nome),$full_mail_body);
			$full_mail_body = str_replace("{materia}",$sportello_materia,$full_mail_body);
			$full_mail_body = str_replace("{aula}",$sportello_luogo,$full_mail_body);
			$full_mail_body = str_replace("{nome_istituto}",$__settings->local->nomeIstituto,$full_mail_body);

			$sender = $__settings->local->emailNoReplyFrom;
			$headers  = 'MIME-Version: 1.0' . "\r\n";
			$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
			$headers .= "From: " . $sender . "\r\n";
			$headers .= "Bcc: " . $__settings->local->emailSportelli . "\r\n"."X-Mailer: php";
			$mailsubject = 'GestOre - Promemoria attività ' . $sportello_categoria . ' - materia '. $sportello_materia;
			mail($studente_email, $mailsubject, $full_mail_body ,  $headers, additional_params: "-f$sender");
			echo "inviata mail di promemoria agli studenti per lo sportello del docente - " . $sportello_docente_cognome . " " . $sportello_docente_nome . " - ";
			info("inviata mail di promemoria agli studenti per lo sportello del docente - " . $sportello_docente_cognome . " " . $sportello_docente_nome);
		}
	}
	else
	{
		info("NON CI SONO studenti iscritti sportello");
	}
	
}



?>

