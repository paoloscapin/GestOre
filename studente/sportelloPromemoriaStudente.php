<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/connect.php';
require_once '../common/send-mail.php';

// ✅ UI comune (mailWrap, badge, kvRow, studentiTableHtml)
require_once '../common/mail-ui.php';

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

	info("dati sportello docente da inviare promemoria agli studenti:  DATA " . $data . " ORA " . $sportello_ora . " ID " . $sportello_id . " DOCENTE-ID " . $sportello_docente_id);
	echo "dati sportello docente da inviare promemoria agli studenti:  DATA " . $data . " ORA " . $sportello_ora . " ID " . $sportello_id . " DOCENTE-ID " . $sportello_docente_id . "<br>";

	if ($numero_studenti_iscritti > 0) {

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

		foreach ($resultArray as $row) {
			$studente_cognome = $row['studente_cognome'];
			$studente_nome = $row['studente_nome'];
			$studente_email = $row['studente_email'];
			$sportello_argomento = $row['sportello_argomento'];

			// ✅ genitori dello studente corrente (id corretto: sportello_studente_id)
			$genitori = dbGetAll("SELECT cognome,nome,email from genitori g
                          INNER JOIN genitori_studenti gs ON gs.id_studente = " . $row['sportello_studente_id'] . "
                          WHERE g.attivo=1 AND gs.id_genitore = g.id");
			$email_genitori = "";
			$nominativo_genitori = "";

			foreach ($genitori as $genitore) {
				if ($genitore['email'] != "") {
					if ($email_genitori != "") {
						$email_genitori = $email_genitori . ", ";
						$nominativo_genitori = $nominativo_genitori . ", ";
					}
					$email_genitori = $email_genitori . $genitore['email'];
					$nominativo_genitori = $nominativo_genitori . $genitore['cognome'] . " " . $genitore['nome'];
				}
			}

			info("studenti iscritti sportello: COGNOME " . $studente_cognome . " NOME " . $studente_nome . " DATA " . $data);

			// ✅ NUOVA GRAFICA (mail-ui.php) — sostituisce template_mail_promemoria_studente.html
			$title = 'PROMEMORIA ATTIVITÀ<br>' . $sportello_categoria;
			$intro = "Promemoria automatico per l’attività prenotata per <b>domani</b>.";
			$toName = $studente_nome . " " . $studente_cognome;

			$content = '
				<div style="margin:0 0 12px 0;">
					' . badge('PROMEMORIA – attività prenotata', '#dbeafe', '#1e3a8a') . '
				</div>

				<div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:12px 12px;margin:0 0 14px 0;">
					<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
						' . kvRow('Attività', $sportello_categoria) . '
						' . kvRow('Materia', $sportello_materia) . '
						' . kvRow('Data', $sportello_data) . '
						' . kvRow('Ora', $sportello_ora) . '
						' . kvRow('Docente', strtoupper($sportello_docente_cognome . " " . $sportello_docente_nome)) . '
						' . kvRow('Aula', ($sportello_luogo !== '' ? $sportello_luogo : '—')) . '
						' . kvRow('Argomento (se inserito)', ($sportello_argomento !== '' ? $sportello_argomento : '—')) . '
						' . kvRow('ID Sportello', (string)$sportello_id) . '
					</table>
				</div>

				<div style="font-size:13.5px;line-height:1.55;color:#374151;">
					Ti ricordiamo l’attività prenotata per domani. Presentati puntuale e controlla i dettagli qui sopra.
				</div>
			';

			$footer = htmlspecialchars((string)$__settings->local->nomeIstituto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

			$full_mail_body = mailWrap($title, $toName, $intro, $content, $footer,'studente');

			$to = $studente_email;
			$toCC = $email_genitori;
			$toCCName = $nominativo_genitori;

			info("Invio mail allo studente: " . $to . " " . $toName);
			echo "Invio mail promemoria allo studente: " . $to . " " . $toName . "<br>";

			$mailsubject = 'GestOre - Promemoria attività ' . $sportello_categoria . ' - materia ' . $sportello_materia;

			if ($toCC != "") {
				try {
					sendMailCC($to, $toName, $toCC, $toCCName, $mailsubject, $full_mail_body);
					info("mail di promemoria inviata allo studente - email: " . $studente_email);
					echo "mail di promemoria inviata allo studente - email: " . $studente_email . "<br>";
					info("mail di promemoria inviata anche al genitore - email: " . $toCC);
					echo "mail di promemoria inviata anche al genitore - email: " . $toCC . "<br>";
				} catch (Exception $e) {
					error("Errore invio mail al genitore: " . $e->getMessage() . " - email: " . $toCC);
				}
			} else {
				try {
					sendMail($to, $toName, $mailsubject, $full_mail_body);
					info("mail di promemoria inviata allo studente - email: " . $studente_email);
					echo "mail di promemoria inviata allo studente - email: " . $studente_email . "<br>";
				} catch (Exception $e) {
					error("Errore invio mail allo studente: " . $e->getMessage() . " - email: " . $studente_email);
				}
			}

			info("inviata mail di promemoria agli studenti per lo sportello del docente - " . $sportello_docente_cognome . " " . $sportello_docente_nome);
			echo "inviata mail di promemoria agli studenti per lo sportello del docente - " . $sportello_docente_cognome . " " . $sportello_docente_nome . "<br>";
		}
	} else {
		info("NON CI SONO studenti iscritti sportello");
		echo "NON CI SONO studenti iscritti sportello<br>";
	}
}
