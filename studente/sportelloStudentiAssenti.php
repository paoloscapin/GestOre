<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/connect.php';
require_once '../common/send-mail.php';

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
				date_format(sportello.data,'%Y%m%d') = CURDATE()
				AND NOT sportello.cancellato ";

$resultArray = dbGetAll($query);

if ($resultArray == null) {
	echo "nessuno sportello oggi";
	$resultArray = [];
} else {
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
		$sportello_categoria = strtoupper($row['sportello_categoria']);
		$sportello_luogo = $row['sportello_luogo'];

		$query = "SELECT COUNT(*) FROM sportello_studente WHERE sportello_studente.sportello_id = $sportello_id";

		$numero_studenti_iscritti = dbGetValue($query);
		info("dati sportello docente per verifica assenti:  DATA " . $sportello_data . " ORA " . $sportello_ora . " ID " . $sportello_id . " DOCENTE-ID " . $sportello_docente_id);
		echo "** Dati sportello docente per verifica assenti:  DATA " . $sportello_data . " ORA " . $sportello_ora . " ID " . $sportello_id . " DOCENTE-ID " . $sportello_docente_id . " ** ";

		if (($numero_studenti_iscritti > 0) && ($sportello_categoria == "SPORTELLO DIDATTICO"))
		// CI SONO STUDENTI ISCRITTI - CERCO GLI ASSENTI
		{
			$query = "	SELECT
					sportello_studente.sportello_id AS sportello_id,
					sportello_studente.studente_id AS sportello_studente_id,
					sportello_studente.iscritto AS sportello_iscritto,
					sportello_studente.presente AS sportello_presente,
					sportello_studente.argomento AS sportello_argomento,

					studente.cognome AS studente_cognome,
					studente.nome AS studente_nome,
					studente.email AS studente_email
					FROM sportello_studente
					INNER JOIN studente
					ON studente.id = sportello_studente.studente_id
					WHERE
					sportello_id = " . $sportello_id . "
					AND sportello_studente.iscritto = 1
					 AND ((sportello_studente.presente IS NULL) OR ((sportello_studente.presente IS NOT NULL) AND (sportello_studente.presente = 0)))";
		
					 $resultArray = dbGetAll($query);
		
			if ($resultArray == null) {
				$resultArray = [];
			}

			// inverto format data - giorno con mese
			$data_array = explode("-", $sportello_data);
			$sportello_data = $data_array[2] . "-" . $data_array[1] . "-" . $data_array[0];

			$data = "";
			$tabella_html = '';

			foreach ($resultArray as $row) {
				$studente_cognome = $row['studente_cognome'];
				$studente_nome = $row['studente_nome'];
				$studente_email = $row['studente_email'];
				$sportello_argomento = $row['sportello_argomento'];

				info("studenti assenti all'attività: COGNOME " . $studente_cognome . " NOME " . $studente_nome . " DATA " . $data);
				echo "studenti assenti all'attività: COGNOME " . $studente_cognome . " NOME " . $studente_nome . " DATA " . $data;
				// preparo il testo della mail
				$full_mail_body = file_get_contents("template_mail_studente_assente.html");

				$full_mail_body = str_replace("{titolo}", "NOTIFICA ASSENZA ATTIVITA'<br>" . $sportello_categoria, $full_mail_body);
				$full_mail_body = str_replace("{nome}", strtoupper($studente_cognome) . " " . strtoupper($studente_nome), $full_mail_body);
				$full_mail_body = str_replace("{messaggio}", "<b>sei risultato assente alla seguente attività <br><h3 style='background-color:yellow; font-size:20px'><center>" . $sportello_categoria . "</center></h3>", $full_mail_body);
				$full_mail_body = str_replace("{data}", $sportello_data, $full_mail_body);
				$full_mail_body = str_replace("{ora}", $sportello_ora, $full_mail_body);
				$full_mail_body = str_replace("{docente}", strtoupper($sportello_docente_cognome . " " . $sportello_docente_nome), $full_mail_body);
				$full_mail_body = str_replace("{materia}", $sportello_materia, $full_mail_body);
				$full_mail_body = str_replace("{aula}", $sportello_luogo, $full_mail_body);
				$full_mail_body = str_replace("{nome_istituto}", $__settings->local->nomeIstituto, $full_mail_body);
				$full_mail_body = str_replace("{messaggio_finale}", "<h3 style='font-size:14px;color: #000000;text-align:justify'> Se eri presente segnala subito al docente la mancanza. <u>L'assenza ad un'attività prenotata e non giustificata in anticipo con il docente sarà tenuta in considerazione ai fini disciplinari.</u> Ricorda che puoi cancellarti da un'attività fino alla sera precedente.</b></h3>", $full_mail_body);

				$to = $studente_email;
				$toName = $studente_nome . " " . $studente_cognome;

				info("Invio mail allo studente: " . $to . " " . $toName);
				echo " ** Invio mail allo studente: " . $to . " " . $toName . "\n ** ";
				$mailsubject = 'GestOre - Notifica assenza attività ' . $sportello_categoria . ' - materia ' . $sportello_materia;
				sendMail($to, $toName, $mailsubject, $full_mail_body);
				info("inviata mail di notifica assenza agli studenti assenti per l'attività del docente - " . $sportello_docente_cognome . " " . $sportello_docente_nome);
			}
		} else {
			echo " NON CI SONO studenti iscritti all'attività ** ";
			info("NON CI SONO studenti iscritti all'attività");
		}

	}
}



?>