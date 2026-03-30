<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('studente', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

function jsonOut($ok, $extra = [])
{
	echo json_encode(array_merge(['ok' => (bool)$ok], $extra), JSON_UNESCAPED_UNICODE);
	exit;
}

try {

	// =========================
	// 1) VALIDAZIONE INPUT
	// =========================
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		jsonOut(false, ['error' => 'Metodo non consentito']);
	}

	$sportello_id = (int)($_POST['id'] ?? 0);
	if ($sportello_id <= 0) {
		jsonOut(false, ['error' => 'ID sportello mancante']);
	}

	// dati "di contorno" (solo per mail/log)
	$materia   = (string)($_POST['materia'] ?? '');
	$argomento = (string)($_POST['argomento'] ?? '');
	$categoria = (string)($_POST['categoria'] ?? '');
	$data      = (string)($_POST['data'] ?? '');
	$ora       = (string)($_POST['ora'] ?? '');
	$numero_ore = (int)($_POST['numero_ore'] ?? 1);
	$luogo     = (string)($_POST['luogo'] ?? '');
	$docente_id = (int)($_POST['docente_id'] ?? 0);

	// sicurezza: uso sempre lo studente in sessione
	$studente_id = (int)$__studente_id;


	// =========================
	// 2) CANCELLA ISCRIZIONE
	// =========================
	dbExec("
		DELETE FROM sportello_studente 
		WHERE sportello_id = " . dbI($sportello_id) . "
		AND studente_id = " . dbI($studente_id)
	);

	info("cancellata iscrizione studente_id=$studente_id sportello_id=$sportello_id materia=$materia");


	// =========================
	// 3) RECUPERO DATI PER MAIL
	// =========================
	$studente = dbGetFirst("SELECT nome, cognome, email FROM studente WHERE id = " . dbI($studente_id));
	$studente_nome = $studente['nome'] ?? '';
	$studente_cognome = $studente['cognome'] ?? '';
	$studente_email = $studente['email'] ?? '';

	$docente = dbGetFirst("SELECT nome, cognome, email FROM docente WHERE id = " . dbI($docente_id));
	$docente_nome = $docente['nome'] ?? '';
	$docente_cognome = $docente['cognome'] ?? '';
	$docente_email = $docente['email'] ?? '';

	// genitori
	$genitori = dbGetAll("
		SELECT g.cognome, g.nome, g.email
		FROM genitori g
		INNER JOIN genitori_studenti gs ON gs.id_genitore = g.id
		WHERE g.attivo = 1
		AND gs.id_studente = " . dbI($studente_id)
	);

	$email_genitori = "";
	$nominativo_genitori = "";

	foreach ($genitori as $genitore) {
		if (!empty($genitore['email'])) {
			if ($email_genitori !== "") {
				$email_genitori .= ", ";
				$nominativo_genitori .= ", ";
			}
			$email_genitori .= $genitore['email'];
			$nominativo_genitori .= $genitore['cognome'] . " " . $genitore['nome'];
		}
	}


	// =========================
	// 4) FORMAT DATA (per mail)
	// =========================
	if (!empty($data)) {
		$data_array = explode("-", $data);
		if (count($data_array) === 3) {
			$data = $data_array[2] . "-" . $data_array[1] . "-" . $data_array[0];
		}
	}


	// =========================
	// 5) MAIL STUDENTE
	// =========================
	require 'sportelloMailCancellazioneStudente.php';


	// =========================
	// 6) CONTROLLO SE ULTIMO ISCRITTO
	// =========================
	$iscritti = (int)dbGetValue("
		SELECT COUNT(*) 
		FROM sportello_studente 
		WHERE sportello_id = " . dbI($sportello_id) . "
		AND iscritto = 1
	");

	if ($iscritti === 0) {

		// reset argomento se necessario
		if (getSettingsValue("sportelli", "unSoloArgomento", true)) {
			debug("ultimo iscritto cancellato -> reset argomento sportello id=$sportello_id");

			dbExec("
				UPDATE sportello 
				SET argomento = '' 
				WHERE id = " . dbI($sportello_id)
			);
		}

		// mail docente (annullamento)
		require 'sportelloInviaMailCancellazioneDocente.php';
	}


	// =========================
	// 7) RISPOSTA JSON
	// =========================
	jsonOut(true);

} catch (Throwable $e) {

	warning("sportelloCancellaIscrizione.php ERROR: " . $e->getMessage());

	jsonOut(false, [
		'error' => $e->getMessage()
	]);
}