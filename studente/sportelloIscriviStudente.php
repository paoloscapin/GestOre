<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
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
	// 1) CHECK NEXT (per JS)
	// =========================
	$action = (string)($_POST['action'] ?? '');
	if ($action === 'check_next') {

		$id = (int)($_POST['id'] ?? 0);
		if ($id <= 0) jsonOut(false, ['error' => 'id mancante']);

		// prendo dati sportello corrente
		$s = dbGetFirst("SELECT id, data, ora, materia_id, categoria, classe_id, docente_id, classe, numero_ore, max_iscrizioni, cancellato, attivo
                         FROM sportello WHERE id = $id LIMIT 1");
		if (!$s) jsonOut(false, ['error' => 'sportello non trovato']);

		// se non attivo/cancellato -> nessun next
		if ((int)$s['cancellato'] === 1 || (int)$s['attivo'] !== 1) {
			jsonOut(true, ['next_id' => 0]);
		}

		$docente_id = (int)($s['docente_id'] ?? 0);
		$data = addslashes((string)$s['data']);
		$ora  = addslashes((string)$s['ora']);

		// calcolo ora successiva guardando la "tabella orari" = tutte le ore disponibili quel giorno
		$ore = dbGetAll("SELECT DISTINCT ora FROM sportello WHERE data='$data' ORDER BY ora");
		$lista = [];
		foreach ($ore as $r) $lista[] = (string)$r['ora'];

		$idx = array_search((string)$s['ora'], $lista, true);
		if ($idx === false || !isset($lista[$idx + 1])) {
			jsonOut(true, ['next_id' => 0]);
		}
		$oraNext = $lista[$idx + 1];

		// cerco sportello "uguale" allo slot successivo (stessa data, stessa materia, stessa categoria, stessa classe)
		$materia_id = (int)$s['materia_id'];
		$classe_id  = (int)$s['classe_id'];
		$classe_txt = addslashes((string)$s['classe']);
		$categoria  = addslashes((string)$s['categoria']);

		$qNext = "
            SELECT id, max_iscrizioni
            FROM sportello
            WHERE data = '$data'
              AND ora = '" . addslashes($oraNext) . "'
              AND materia_id = $materia_id
			  AND docente_id = $docente_id
              AND categoria = '$categoria'
              AND (
                    (classe_id > 0 AND classe_id = $classe_id)
                    OR
                    (classe_id = 0 AND classe = '$classe_txt')
                  )
              AND cancellato = 0
              AND attivo = 1
            LIMIT 1
        ";
		$n = dbGetFirst($qNext);
		if (!$n) jsonOut(true, ['next_id' => 0]);

		$next_id = (int)$n['id'];

		// posti residui next
		$cnt = (int)dbGetValue("SELECT COUNT(*) FROM sportello_studente WHERE sportello_id=$next_id AND iscritto=1");
		$max = (int)$n['max_iscrizioni'];
		$posti = $max - $cnt;

		jsonOut(true, [
			'next_id' => $next_id,
			'next_ora' => $oraNext,
			'next_posti' => $posti
		]);
	}

	if ($action === 'check_adjacent') {

		$id = (int)($_POST['id'] ?? 0);
		if ($id <= 0) jsonOut(false, ['error' => 'id mancante']);

		$s = dbGetFirst("SELECT id, data, ora, materia_id, categoria, classe_id, docente_id, classe, max_iscrizioni, cancellato, attivo
                     FROM sportello WHERE id = $id LIMIT 1");
		if (!$s) jsonOut(false, ['error' => 'sportello non trovato']);

		if ((int)$s['cancellato'] === 1 || (int)$s['attivo'] !== 1) {
			jsonOut(true, ['prev_id' => 0, 'next_id' => 0]);
		}

		$data = addslashes((string)$s['data']);
		$docente_id = (int)($s['docente_id'] ?? 0);

		// lista ore del giorno
		$ore = dbGetAll("SELECT DISTINCT ora FROM sportello WHERE data='$data' ORDER BY ora");
		$lista = [];
		foreach ($ore as $r) $lista[] = (string)$r['ora'];

		$idx = array_search((string)$s['ora'], $lista, true);
		if ($idx === false) jsonOut(true, ['prev_id' => 0, 'next_id' => 0]);

		$oraPrev = ($idx > 0) ? $lista[$idx - 1] : '';
		$oraNext = (isset($lista[$idx + 1])) ? $lista[$idx + 1] : '';

		$materia_id = (int)$s['materia_id'];
		$classe_id  = (int)$s['classe_id'];
		$classe_txt = addslashes((string)$s['classe']);
		$categoria  = addslashes((string)$s['categoria']);

		// helper inline per cercare slot adiacente
		$findSlot = function ($oraSlot) use ($data, $materia_id, $categoria, $docente_id, $classe_id, $classe_txt) {
			if (!$oraSlot) return null;
			$oraSlotEsc = addslashes($oraSlot);
			$q = "
        SELECT id, max_iscrizioni
        FROM sportello
        WHERE data = '$data'
          AND ora = '$oraSlotEsc'
          AND materia_id = $materia_id
          AND docente_id = $docente_id
          AND categoria = '$categoria'
          AND (
                (classe_id > 0 AND classe_id = $classe_id)
                OR
                (classe_id = 0 AND classe = '$classe_txt')
              )
          AND cancellato = 0
          AND attivo = 1
        LIMIT 1
    ";
			return dbGetFirst($q);
		};

		$prev = $findSlot($oraPrev);
		$next = $findSlot($oraNext);

		$resp = [
			'prev_id' => 0,
			'prev_ora' => '',
			'prev_posti' => 0,
			'next_id' => 0,
			'next_ora' => '',
			'next_posti' => 0,
		];

		if ($prev) {
			$pid = (int)$prev['id'];
			$cnt = (int)dbGetValue("SELECT COUNT(*) FROM sportello_studente WHERE sportello_id=$pid AND iscritto=1");
			$max = (int)$prev['max_iscrizioni'];
			$resp['prev_id'] = $pid;
			$resp['prev_ora'] = $oraPrev;
			$resp['prev_posti'] = $max - $cnt;
		}

		if ($next) {
			$nid = (int)$next['id'];
			$cnt = (int)dbGetValue("SELECT COUNT(*) FROM sportello_studente WHERE sportello_id=$nid AND iscritto=1");
			$max = (int)$next['max_iscrizioni'];
			$resp['next_id'] = $nid;
			$resp['next_ora'] = $oraNext;
			$resp['next_posti'] = $max - $cnt;
		}

		jsonOut(true, $resp);
	}

	// =========================
	// 2) ISCRIZIONE (singola o multipla)
	// =========================
	// ids multipli (JSON string) oppure singolo id
	$ids = [];
	if (!empty($_POST['ids'])) {
		$tmp = json_decode((string)$_POST['ids'], true);
		if (is_array($tmp)) $ids = array_map('intval', $tmp);
	} else {
		$ids[] = (int)($_POST['id'] ?? 0);
	}
	$ids = array_values(array_filter($ids, fn($x) => $x > 0));
	if (!$ids) jsonOut(false, ['error' => 'id/ids mancanti']);

	$materia   = escapePost('materia');      // usato nei log/mail (se serve)
	$argomento = escapePost('argomento');
	$categoria = (string)($_POST['categoria'] ?? '');

	// per multi, questi possono NON arrivare (nel tuo JS li mandi solo nel ramo singolo),
	// quindi li recuperiamo dal DB per ogni sportello.
	$docente_id_post = (int)($_POST['docente_id'] ?? 0);

	$iscritti = [];

	foreach ($ids as $sportello_id) {

		// recupero sportello dal DB (fonte di verità)
		$s = dbGetFirst("SELECT * FROM sportello WHERE id = $sportello_id LIMIT 1");
		if (!$s) {
			// non blocco tutto, ma segnalo
			$iscritti[] = ['id' => $sportello_id, 'ok' => false, 'error' => 'sportello non trovato'];
			continue;
		}

		$data       = (string)($s['data'] ?? '');
		$ora        = (string)($s['ora'] ?? '');
		$numero_ore = (int)($s['numero_ore'] ?? 1);
		$luogo      = (string)($s['luogo'] ?? '');
		$docente_id = (int)($s['docente_id'] ?? $docente_id_post);
		$categoriaS = (string)($s['categoria'] ?? $categoria);

		// evita doppia iscrizione
		$already = (int)dbGetValue("SELECT COUNT(*) FROM sportello_studente WHERE sportello_id=$sportello_id AND studente_id=$__studente_id AND iscritto=1");
		if ($already > 0) {
			$iscritti[] = ['id' => $sportello_id, 'ok' => true, 'skip' => true];
			continue;
		}

		dbExec("INSERT INTO sportello_studente(iscritto, argomento, sportello_id, studente_id)
                VALUES(1, '$argomento', $sportello_id, $__studente_id)");
		$last_id = (int)dblastId();

		info("iscritto $__studente_cognome $__studente_nome allo sportello id=$sportello_id materia=$materia argomento=$argomento");

		// aggiorna argomento sportello se unSoloArgomento
		if (getSettingsValue('sportelli', 'unSoloArgomento', true)) {
			dbExec("UPDATE sportello SET argomento = '$argomento' WHERE id = $sportello_id");
			info("aggiornato sportello argomento (unSoloArgomento) id=$sportello_id");
		}

		// Dati per mail: ricavo studente/docente/genitori come facevi tu (una volta sola va bene, ma qui lo lascio semplice)
		$studente = dbGetFirst("SELECT * from studente WHERE id = $__studente_id");
		$studente_nome    = $studente['nome'] ?? '';
		$studente_cognome = $studente['cognome'] ?? '';
		$studente_email   = $studente['email'] ?? '';

		$docente = dbGetFirst("SELECT * from docente WHERE id = $docente_id");
		$docente_nome    = $docente['nome'] ?? '';
		$docente_cognome = $docente['cognome'] ?? '';
		$docente_email   = $docente['email'] ?? '';

		$genitori = dbGetAll("SELECT cognome,nome,email from genitori g
                              INNER JOIN genitori_studenti gs ON gs.id_studente = " . (int)$__studente_id . "
                              WHERE g.attivo=1 AND gs.id_genitore = g.id");
		$email_genitori = "";
		$nominativo_genitori = "";
		foreach ($genitori as $genitore) {
			if ($email_genitori != "") {
				$email_genitori .= ", ";
				$nominativo_genitori .= ", ";
			}
			$email_genitori .= ($genitore['email'] ?? '');
			$nominativo_genitori .= ($genitore['cognome'] ?? '') . " " . ($genitore['nome'] ?? '');
		}

		// calcoli datetime (come prima)
		$date_time = $data . " " . $ora . ":00";
		$dateT = date_create($date_time, timezone_open("Europe/Rome"));
		$isDST = $dateT ? $dateT->format("I") : 0;
		if ($dateT) {
			if ($isDST) $dateT->sub(new DateInterval('PT2H'));
			else        $dateT->sub(new DateInterval('PT1H'));
		}
		$datetime_sportello = $dateT ? date_format($dateT, "Ymd-His") : '';
		$datetime_sportello = str_replace("-", "T", $datetime_sportello);

		$durata_minuti = $numero_ore * 50;
		$dateT_fine = $dateT;
		if ($dateT_fine) $dateT_fine->modify(' + ' . $durata_minuti . ' minutes');
		$datetime_fine_sportello = $dateT_fine ? date_format($dateT_fine, "Ymd-His") : '';
		$datetime_fine_sportello = str_replace("-", "T", $datetime_fine_sportello);

		// inverto data per mail (come prima)
		$data_mail = $data;
		$data_array = explode("-", $data);
		if (count($data_array) === 3) $data_mail = $data_array[2] . "-" . $data_array[1] . "-" . $data_array[0];

		// Mail studente (silenziosa)
		require('sportelloMailIscrizioneStudente.php');

		// primo iscritto? (mail docente)
		$numero_studenti_iscritti = (int)dbGetValue("SELECT COUNT(*) FROM sportello_studente WHERE sportello_id = $sportello_id AND iscritto=1");
		if ($numero_studenti_iscritti === 1) {
			require('sportelloInviaMailIscrizioneDocente.php');
		}

		$iscritti[] = ['id' => $sportello_id, 'ok' => true, 'sportello_studente_id' => $last_id];
	}

	jsonOut(true, ['results' => $iscritti]);
} catch (Throwable $e) {
	warning("sportelloIscriviStudente.php ERROR: " . $e->getMessage());
	jsonOut(false, ['error' => $e->getMessage()]);
}
