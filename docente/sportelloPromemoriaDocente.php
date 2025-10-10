<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// include Database connection file
require_once '../common/connect.php';
require_once '../common/send-mail.php';

$__anno_scolastico = dbGetFirst("SELECT * FROM anno_scolastico_corrente");
$__anno_scolastico_corrente_id = $__anno_scolastico['anno_scolastico_id'];

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
				date_format(sportello.data,'%Y%m%d') = CURDATE() + INTERVAL 1 DAY
				AND NOT sportello.cancellato ";

$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}

foreach ($resultArray as $row) {
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
	$sportello_categoria = $row['sportello_categoria'];
	$sportello_luogo = $row['sportello_luogo'];

	$query = "SELECT COUNT(*) FROM sportello_studente WHERE sportello_studente.sportello_id = $sportello_id";

	$numero_studenti_iscritti = dbGetValue($query);

	$data .= "ID=" . $sportello_id . " - ORA=" . $sportello_ora . " - CANCELLATO=" . $sportello_cancellato . " - ID DOCENTE=" . $sportello_docente_id . " - NR ISCRITTI=" . $numero_studenti_iscritti;


	info("dati promemoria sportello docente da inviare - COGNOME " . $sportello_docente_cognome . " NOME  " . $sportello_docente_nome);

	$toName = $sportello_docente_cognome . " " . $sportello_docente_nome;
	$to = $sportello_docente_email;

	if ($numero_studenti_iscritti > 0)
	// CI SONO STUDENTI ISCRITTI - INVIO IL PROMEMORIA CON ELENCO STUDENTI AL DOCENTE
	{

		$query = "	SELECT
					sportello_studente.sportello_id AS sportello_id,
					sportello_studente.studente_id AS sportello_studente_id,
					sportello_studente.iscritto AS sportello_iscritto,
					sportello_studente.argomento AS sportello_argomento,

					studente.cognome AS studente_cognome,
					studente.nome AS studente_nome,
					c.classe AS studente_classe
					FROM sportello_studente
					INNER JOIN studente
					ON studente.id = sportello_studente.studente_id
					INNER JOIN studente_frequenta sf
					ON sf.id_studente = studente.id
					AND sf.id_anno_scolastico = $__anno_scolastico_corrente_id
					INNER JOIN classi c
					ON c.id = sf.id_classe
					WHERE
					sportello_id = " . $sportello_id . "
					AND sportello_studente.iscritto = 1";

		$resultArray = dbGetAll($query);
		if ($resultArray == null) {
			$resultArray = [];
		}

		$tabella_html = '';

		foreach ($resultArray as $row) {
			$studente_cognome = $row['studente_cognome'];
			$studente_nome = $row['studente_nome'];
			$studente_classe = $row['studente_classe'];
			$sportello_argomento = $row['sportello_argomento'];

			$data_html = '<tr>';

			$row_html = '<td style="overflow-wrap:break-word;word-break:break-word;padding:10px 0px 10px 0px;font-family:arial,helvetica,sans-serif;background-color: rgb(255, 255, 255);"  align="left">
			<p style="font-size: 12px; line-height: 140%; text-align: center;"><span style="font-size: 12px; line-height: 22.4px; font-family: Lato, sans-serif;"><strong>VALORE</strong></span></p></td>';
			$row_html = str_replace("VALORE", $studente_classe, $row_html);
			$data_html .= $row_html;

			$row_html = '<td style="overflow-wrap:break-word;word-break:break-word;padding:10px 0px 10px 0px;font-family:arial,helvetica,sans-serif;background-color: rgb(255, 255, 255);"  align="left">
			<p style="font-size: 12px; line-height: 140%; text-align: center;"><span style="font-size: 12px; line-height: 22.4px; font-family: Lato, sans-serif;"><strong>VALORE</strong></span></p></td>';
			$row_html = str_replace("VALORE", $studente_cognome, $row_html);
			$data_html .= $row_html;

			$row_html = '<td style="overflow-wrap:break-word;word-break:break-word;padding:10px 0px 10px 0px;font-family:arial,helvetica,sans-serif;background-color: rgb(255, 255, 255);"  align="left">
			<p style="font-size: 12px; line-height: 140%; text-align: center;"><span style="font-size: 12px; line-height: 22.4px; font-family: Lato, sans-serif;"><strong>VALORE</strong></span></p></td>';
			$row_html = str_replace("VALORE", $studente_nome, $row_html);
			$data_html .= $row_html;

			$row_html = '<td style="overflow-wrap:break-word;word-break:break-word;padding:10px 0px 10px 0px;font-family:arial,helvetica,sans-serif;background-color: rgb(255, 255, 255);"  align="left">
			<p style="font-size: 12px; line-height: 140%; text-align: center;"><span style="font-size: 12px; line-height: 22.4px; font-family: Lato, sans-serif;"><strong>VALORE</strong></span></p></td>';
			$row_html = str_replace("VALORE", $sportello_argomento, $row_html);
			$data_html .= $row_html;

			$data_html .= '</tr>';
			$tabella_html .= $data_html;
		}

		info("studenti iscritti sportello: " . $data_html);

		// preparo il testo della mail
		$full_mail_body = file_get_contents("template_mail_cancella_docente_studenti.html");

		// inverto format data - giorno con mese
		$data_array = explode("-", $sportello_data);
		$sportello_data = $data_array[2] . "-" . $data_array[1] . "-" . $data_array[0];

		$full_mail_body = str_replace("{titolo}", "PROMEMORIA ATTIVITA'<br>" . strtoupper($sportello_categoria), $full_mail_body);
		$full_mail_body = str_replace("{nome}", strtoupper($sportello_docente_cognome) . " " . strtoupper($sportello_docente_nome), $full_mail_body);
		$full_mail_body = str_replace("{messaggio}", "questo è il promemoria per la seguente attività</p><h3 style='background-color:yellow; font-size:20px'><b><center>" . strtoupper($sportello_categoria) . "</center></b></h3>", $full_mail_body);
		$full_mail_body = str_replace("{data}", $sportello_data, $full_mail_body);
		$full_mail_body = str_replace("{ora}", $sportello_ora, $full_mail_body);
		$full_mail_body = str_replace("{docente}", strtoupper($sportello_docente_cognome . " " . $sportello_docente_nome), $full_mail_body);
		$full_mail_body = str_replace("{materia}", $sportello_materia, $full_mail_body);
		$full_mail_body = str_replace("{aula}", $sportello_luogo, $full_mail_body);
		$full_mail_body = str_replace("{nome_istituto}", $__settings->local->nomeIstituto, $full_mail_body);
		$full_mail_body = str_replace("{codice_html_tabella}", $tabella_html, $full_mail_body);
		$full_mail_body = str_replace("{messaggio_finale}", "", $full_mail_body);

		info("Invio mail al docente per lo sportello id  " . $row['sportello_id'] . ": " . $to . " " . $toName);
		echo "Invio mail al docente per lo sportello id  " . $row['sportello_id'] . ": " . $to . " " . $toName . "<br>";
		$mailsubject = 'GestOre - Promemoria attività ' . $sportello_categoria . ' - materia ' . $sportello_materia;
		sendMail($to, $toName, $mailsubject, $full_mail_body);
		echo "inviata mail di promemoria per lo sportello id  " . $row['sportello_id'] . " del docente  - " . $toName . "<br>";
		info("inviata mail di promemoria per lo sportello id  " . $row['sportello_id'] . " del docente - " . $toName);
	} else
	// NON CI SONO STUDENTI ISCRITTI - SPORTELLO ANNULLATO
	{
		// preparo il testo della mail
		$full_mail_body = file_get_contents("template_mail_cancella_studente.html");

		// inverto format data - giorno con mese
		$data_array = explode("-", $sportello_data);
		$sportello_data = $data_array[2] . "-" . $data_array[1] . "-" . $data_array[0];

		$full_mail_body = str_replace("{titolo}", "ANNULLAMENTO ATTIVITA'<br>" . strtoupper($sportello_categoria), $full_mail_body);
		$full_mail_body = str_replace("{nome}", strtoupper($sportello_docente_cognome) . " " . strtoupper($sportello_docente_nome), $full_mail_body);
		$full_mail_body = str_replace("{messaggio}", "la seguente attività viene ANNULLATA a causa di mancanza di iscritti</p><h3 style='background-color:yellow; font-size:20px'><b><center>" . strtoupper($sportello_categoria) . "</center></b></h3>", $full_mail_body);
		$full_mail_body = str_replace("{data}", $sportello_data, $full_mail_body);
		$full_mail_body = str_replace("{ora}", $sportello_ora, $full_mail_body);
		$full_mail_body = str_replace("{docente}", strtoupper($sportello_docente_cognome . " " . $sportello_docente_nome), $full_mail_body);
		$full_mail_body = str_replace("{materia}", $sportello_materia, $full_mail_body);
		$full_mail_body = str_replace("{aula}", $sportello_luogo, $full_mail_body);
		$full_mail_body = str_replace("{nome_istituto}", $__settings->local->nomeIstituto, $full_mail_body);

		info("Invio mail al docente di annullamento sportello id  " . $row['sportello_id'] . ": " . $to . " " . $toName);
		echo "Invio mail al docente di annullamento sportello id  " . $row['sportello_id'] . ": " . $to . " " . $toName . "<br>";

		$mailsubject = 'GestOre - Annullamento attività ' . $sportello_categoria . ' - materia ' . $sportello_materia;
		sendMail($to, $toName, $mailsubject, $full_mail_body);

		echo "inviata mail di annullamento sportello id " . $row['sportello_id'] . " per mancanza iscritti - " . $toName . "<br>";
		info("inviata mail di annullamento sportello id " . $row['sportello_id'] . " per mancanza iscritti - " . $toName);
	}
}
