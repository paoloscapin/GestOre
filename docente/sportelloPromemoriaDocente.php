<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// include Database connection file
//require_once '../common/checkSession.php';
require_once '../common/connect.php';


$today = new DateTime("now");

$query = "	SELECT
				sportello.id AS sportello_id,
				sportello.ora AS sportello_ora,
				sportello.data AS sportello_data,
				sportello.cancellato AS sportello_cancellato,
				sportello.docente_id AS sportello_docente_id,
				sportello.materia_id AS sportello_materia_id,
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
				date_format(sportello.data,'%Y%m%d') = CURDATE()
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
	$sportello_luogo = $row['sportello_luogo'];

	$query = "SELECT COUNT(*) FROM sportello_studente WHERE sportello_studente.sportello_id = $sportello_id";

	$numero_studenti_iscritti = dbGetValue($query);

	$data .= "ID=" . $sportello_id . " - ORA=" . $sportello_ora . " - CANCELLATO=" . $sportello_cancellato . " - ID DOCENTE=" . $sportello_docente_id . " - NR ISCRITTI=" . $numero_studenti_iscritti;

	
	info("dati promemoria sportello docente da inviare - COGNOME " . $sportello_docente_cognome . " NOME  " . $sportello_docente_nome);

	
	if ($numero_studenti_iscritti>0)
	// CI SONO STUDENTI ISCRITTI - INVIO IL PROMEMORIA CON ELENCO STUDENTI AL DOCENTE
	{

		$query = "	SELECT
					sportello_studente.sportello_id AS sportello_id,
					sportello_studente.studente_id AS sportello_studente_id,
					sportello_studente.iscritto AS sportello_iscritto,
					sportello_studente.argomento AS sportello_argomento,

					studente.cognome AS studente_cognome,
					studente.nome AS studente_nome,
					studente.classe AS studente_classe
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

		$data = "";
		$tabella_html = '';

		foreach($resultArray as $row) 
		{
			$studente_cognome = $row['studente_cognome'];
			$studente_nome = $row['studente_nome'];
			$studente_classe = $row['studente_classe'];
			$sportello_argomento = $row['sportello_argomento'];
			$data .= "S-COGNOME: " . $studente_cognome . " - ";
			$data .= "S-NOME: " . $studente_nome . " - ";
			$data .= "S-CLASSE: " . $studente_classe . " - ";
			$data .= "S-ARGOMENTO: " . $sportello_argomento . "<br>";

			$data_html = '<tr>';

			$row_html = '<td style="overflow-wrap:break-word;word-break:break-word;padding:10px 0px 10px 0px;font-family:arial,helvetica,sans-serif;background-color: rgb(255, 255, 255);"  align="left">
			<p style="font-size: 12px; line-height: 140%; text-align: center;"><span style="font-size: 12px; line-height: 22.4px; font-family: Lato, sans-serif;"><strong>VALORE</strong></span></p></td>';
			$row_html = str_replace("VALORE",$studente_classe,$row_html);
			$data_html .= $row_html;

			$row_html = '<td style="overflow-wrap:break-word;word-break:break-word;padding:10px 0px 10px 0px;font-family:arial,helvetica,sans-serif;background-color: rgb(255, 255, 255);"  align="left">
			<p style="font-size: 12px; line-height: 140%; text-align: center;"><span style="font-size: 12px; line-height: 22.4px; font-family: Lato, sans-serif;"><strong>VALORE</strong></span></p></td>';
			$row_html = str_replace("VALORE",$studente_cognome,$row_html);
			$data_html .= $row_html;

			$row_html = '<td style="overflow-wrap:break-word;word-break:break-word;padding:10px 0px 10px 0px;font-family:arial,helvetica,sans-serif;background-color: rgb(255, 255, 255);"  align="left">
			<p style="font-size: 12px; line-height: 140%; text-align: center;"><span style="font-size: 12px; line-height: 22.4px; font-family: Lato, sans-serif;"><strong>VALORE</strong></span></p></td>';
			$row_html = str_replace("VALORE",$studente_nome,$row_html);
			$data_html .= $row_html;

			$row_html = '<td style="overflow-wrap:break-word;word-break:break-word;padding:10px 0px 10px 0px;font-family:arial,helvetica,sans-serif;background-color: rgb(255, 255, 255);"  align="left">
			<p style="font-size: 12px; line-height: 140%; text-align: center;"><span style="font-size: 12px; line-height: 22.4px; font-family: Lato, sans-serif;"><strong>VALORE</strong></span></p></td>';
			$row_html = str_replace("VALORE",$sportello_argomento,$row_html);
			$data_html .= $row_html;

			$data_html .= '</tr>';
			$tabella_html .= $data_html;
		}

		info("studenti iscritti sportello: " . $data);
		echo "studenti iscritti sportello: " . $data . " - ";

		// preparo il testo della mail
		$full_mail_body = file_get_contents("template_mail_promemoria_docente_con_elenco_studenti.html");

		// inverto format data - giorno con mese
		$data_array = explode("-", $sportello_data);
		$sportello_data = $data_array[2] . "-" . $data_array[1] . "-" . $data_array[0];

		$full_mail_body = str_replace("{titolo}","PROMEMORIA SPORTELLO",$full_mail_body);
		$full_mail_body = str_replace("{nome}",strtoupper($sportello_docente_cognome) . " " . strtoupper($sportello_docente_nome),$full_mail_body);
		$full_mail_body = str_replace("{messaggio}","questo è il promemoria del seguente sportello",$full_mail_body);
		$full_mail_body = str_replace("{data}",$sportello_data,$full_mail_body);
		$full_mail_body = str_replace("{ora}",$sportello_ora,$full_mail_body);
		$full_mail_body = str_replace("{docente}",strtoupper($sportello_docente_cognome . " " . $sportello_docente_nome),$full_mail_body);
		$full_mail_body = str_replace("{materia}",$sportello_materia,$full_mail_body);
		$full_mail_body = str_replace("{aula}",$sportello_luogo,$full_mail_body);
		$full_mail_body = str_replace("{nome_istituto}",$__settings->local->nomeIstituto,$full_mail_body);
		$full_mail_body = str_replace("{codice_html_tabella}",$tabella_html,$full_mail_body);

		$sender = $__settings->local->emailNoReplyFrom;
		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
		$headers .= "From: " . $sender . "\r\n";
		$headers .= "Bcc: " . $__settings->local->emailSportelli . "\r\n"."X-Mailer: php";
		$mailsubject = 'GestOre - Promemoria sportello ' . $sportello_materia;
		mail($sportello_docente_email, $mailsubject, $full_mail_body ,  $headers, additional_params: "-f$sender");
		echo "inviata mail di promemoria per lo sportello del docente - " . $sportello_docente_cognome . " " . $sportello_docente_nome . " - ";
		info("inviata mail di promemoria per lo sportello del docente - " . $sportello_docente_cognome . " " . $sportello_docente_nome);
	}
	else
	// NON CI SONO STUDENTI ISCRITTI - SPORTELLO ANNULLATO
	{
		// preparo il testo della mail
		$full_mail_body = file_get_contents("template_mail_cancella_studente.html");

		// inverto format data - giorno con mese
		$data_array = explode("-", $sportello_data);
		$sportello_data = $data_array[2] . "-" . $data_array[1] . "-" . $data_array[0];

		$full_mail_body = str_replace("{titolo}","ANNULLAMENTO SPORTELLO",$full_mail_body);
		$full_mail_body = str_replace("{nome}",strtoupper($sportello_docente_cognome) . " " . strtoupper($sportello_docente_nome),$full_mail_body);
		$full_mail_body = str_replace("{messaggio}","il seguente sportello viene ANNULLATO a causa di mancanza di iscritti",$full_mail_body);
		$full_mail_body = str_replace("{data}",$sportello_data,$full_mail_body);
		$full_mail_body = str_replace("{ora}",$sportello_ora,$full_mail_body);
		$full_mail_body = str_replace("{docente}",strtoupper($sportello_docente_cognome . " " . $sportello_docente_nome),$full_mail_body);
		$full_mail_body = str_replace("{materia}",$sportello_materia,$full_mail_body);
		$full_mail_body = str_replace("{aula}",$sportello_luogo,$full_mail_body);

		$sender = $__settings->local->emailNoReplyFrom;
		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
		$headers .= "From: " . $sender . "\r\n";
		$headers .= "Bcc: " . $__settings->local->emailSportelli . "\r\n"."X-Mailer: php";
		$mailsubject = 'GestOre - Annullamento sportello ' . $sportello_materia;
		mail($sportello_docente_email, $mailsubject, $full_mail_body ,  $headers, additional_params: "-f$sender");
		echo "inviata mail di annullamento sportello per mancanza iscritti - " . $sportello_docente_cognome . " " . $sportello_docente_nome . " - ";
		info("inviata mail di annullamento sportello per mancanza iscritti - " . $sportello_docente_cognome . " " . $sportello_docente_nome);

		}
}



?>

