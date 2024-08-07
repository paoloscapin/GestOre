<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function orePrevisteAggiornaDocente($docenteId) {
	global $__anno_scolastico_corrente_id;
	global $__settings;

	// per prima cosa azzera i contatori
	$ore_40_sostituzioni_di_ufficio = 0;
	$ore_40_con_studenti = 0;
	$ore_40_aggiornamento = 0;
	$ore_70_funzionali = 0;
	$ore_70_con_studenti = 0;
	$ore_80_aggiornamento = 0;
	$totale_aggiornamento = 0;
	$totale_visite = 0;

	// messaggio da ritornare se ci sono problemi
	$message = '';

	// per prima cosa controlla quante sono le ore 40 con gli studenti dovute per questo docente
	$ore_con_studenti_40_dovute = dbGetValue("SELECT ore_40_con_studenti FROM ore_dovute WHERE docente_id = $docenteId AND anno_scolastico_id = $__anno_scolastico_corrente_id;");

	// legge le attivita previste
	$query = "
		SELECT
		ore_previste_attivita.ore as ore,
		ore_previste_tipo_attivita.categoria as categoria,
		ore_previste_tipo_attivita.nome as nome
		FROM ore_previste_attivita
		INNER JOIN ore_previste_tipo_attivita
		on ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
		WHERE ore_previste_attivita.docente_id = $docenteId AND ore_previste_attivita.anno_scolastico_id = $__anno_scolastico_corrente_id;";

	foreach(dbGetAll($query) as $attivita) {
		switch ($attivita['categoria'])  {
			case "funzionali":
				$ore_70_funzionali = $ore_70_funzionali + $attivita['ore'];
				break;

			case "sostituzioni":
				$ore_40_sostituzioni_di_ufficio = $ore_40_sostituzioni_di_ufficio + $attivita['ore'];
				break;

			case "aggiornamento":
				$totale_aggiornamento = $totale_aggiornamento + $attivita['ore'];
				break;

			case "con studenti":
				// TODO: mettere a punto una strategia generale di massimi
				// controlla che ci siano al massimo 16 ore per le uscite
				if ($attivita['nome'] === 'visite e viaggi') {
					$totale_visite = $totale_visite + $attivita['ore'];
				}
				$ore_40_con_studenti = $ore_40_con_studenti + $attivita['ore'];
				break;

			case "CLIL":
				// il clil viene poi conteggiato a parte e quindi non viene considerato qui
				break;
	
			default:
				warning('attivita sconosciuta: '.$attivita['categoria']);
				break;
		}
	}

	// aggiunge tutte le ore dei corsi di recupero segnate come recuperate
	$ore_corsi_di_recupero = dbGetValue("SELECT COALESCE(SUM(corso_di_recupero.ore_recuperate), 0) FROM corso_di_recupero WHERE docente_id = $docenteId AND anno_scolastico_id = $__anno_scolastico_corrente_id;");
	$ore_40_con_studenti = $ore_40_con_studenti + $ore_corsi_di_recupero;

	// se richieste più di un certo numero di ore di viaggi, le eccedenti vengono tolte (non si dovevano aggiungere ma cosi' e' piu' semplice)
	$max_ore_viaggi = getSettingsValue('viaggi','max_ore', 16);

	if ($max_ore_viaggi > 0) {
		if ($totale_visite > $max_ore_viaggi) {
			$eccedenti_viaggi = $totale_visite - $max_ore_viaggi;
			$ore_40_con_studenti = $ore_40_con_studenti - $eccedenti_viaggi;
			$message = "Sono consentite al massimo $max_ore_viaggi per visite e viaggi: le $eccedenti_viaggi ore eccedenti non saranno considerate. Per queste ore eccedenti è possibile chiedere la diaria.";
		}
	}

	// controllo quante ore di aggiornamento dovrebbe fare
	$ore_aggiornamento_dovute = dbGetValue("SELECT ore_40_aggiornamento FROM ore_dovute WHERE docente_id = $docenteId AND anno_scolastico_id = $__anno_scolastico_corrente_id;");

	// le eccedenti di 10 delle 40 aggiornamento le mette nelle 80 aggiornamento (fino a 10)
	$ore_40_aggiornamento = min($totale_aggiornamento, $ore_aggiornamento_dovute);
	if ($totale_aggiornamento > 10) {
		$ore_80_aggiornamento = min(($totale_aggiornamento - $ore_aggiornamento_dovute), $ore_aggiornamento_dovute);
	}

	// tutte le ore con studenti superiori alle 40 dovute le mette nelle 70
	if ($ore_con_studenti_40_dovute < $ore_40_con_studenti) {
		$ore_70_con_studenti = $ore_40_con_studenti - $ore_con_studenti_40_dovute;
		$ore_40_con_studenti = $ore_con_studenti_40_dovute;
	}

	$ore_40_totali = 0 + round($ore_40_aggiornamento + ($ore_40_sostituzioni_di_ufficio * 50 / 60) + ($ore_40_con_studenti * 50 / 60));
	$ore_70_totali = 0 + round($ore_70_funzionali + $ore_70_con_studenti);
	// aggiorna i valori della tabella ore_previste

	$query = "	UPDATE ore_previste SET
						ore_40_sostituzioni_di_ufficio = '$ore_40_sostituzioni_di_ufficio',
						ore_40_aggiornamento = '$ore_40_aggiornamento',
						ore_40_con_studenti = '$ore_40_con_studenti',
						ore_70_funzionali = '$ore_70_funzionali',
						ore_70_con_studenti = '$ore_70_con_studenti',
						ore_40_totale = '$ore_40_totali',
						ore_70_totale = '$ore_70_totali',
						ore_80_aggiornamento_facoltativo = '$ore_80_aggiornamento'
				WHERE
					docente_id = $docenteId
				AND
					anno_scolastico_id = $__anno_scolastico_corrente_id;";
	dbExec($query);
	debug("aggiornate le ore previste per il docente id=$docenteId");
	echo $message;
}

function oreFatteAggiornaDocente($docenteId) {
	global $__anno_scolastico_corrente_id;
	global $__settings;

	// per prima cosa azzera i contatori
	$ore_40_sostituzioni_di_ufficio = 0;
	$ore_40_con_studenti = 0;
	$ore_40_aggiornamento = 0;
	$ore_70_funzionali = 0;
	$ore_70_con_studenti = 0;
	$ore_80_aggiornamento = 0;
	$totale_aggiornamento = 0;
	$totale_visite = 0;

	// per prima cosa controlla quante sono le ore 40 con gli studenti dovute per questo docente
	$ore_con_studenti_40_dovute = dbGetValue("SELECT ore_40_con_studenti FROM ore_dovute WHERE docente_id = $docenteId AND anno_scolastico_id = $__anno_scolastico_corrente_id;");

	// legge le attivita fatte dalla tabella inserita dal docente
	$query = "
		SELECT
		ore_fatte_attivita.ore as ore,
		ore_previste_tipo_attivita.categoria as categoria
		FROM ore_fatte_attivita
		INNER JOIN ore_previste_tipo_attivita
		on ore_fatte_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
		WHERE ore_fatte_attivita.docente_id = $docenteId AND ore_fatte_attivita.contestata is not true AND ore_fatte_attivita.anno_scolastico_id = $__anno_scolastico_corrente_id;";

	foreach(dbGetAll($query) as $attivita) {
		switch ($attivita['categoria'])  {
			case "funzionali":
				$ore_70_funzionali = $ore_70_funzionali + $attivita['ore'];
				break;

			case "sostituzioni":
				$ore_40_sostituzioni_di_ufficio = $ore_40_sostituzioni_di_ufficio + $attivita['ore'];
				break;

			case "aggiornamento":
				$totale_aggiornamento = $totale_aggiornamento + $attivita['ore'];
				break;

			case "con studenti":
				$ore_40_con_studenti = $ore_40_con_studenti + $attivita['ore'];
				break;

			default:
				warning('attivita sconosciuta: '.$attivita['categoria']);
				break;
		}
	}

	// aggiunge tutte le ore dei corsi di recupero segnate come recuperate
	$ore_corsi_di_recupero = dbGetValue("SELECT COALESCE(SUM(corso_di_recupero.ore_recuperate), 0) FROM corso_di_recupero WHERE docente_id = $docenteId AND anno_scolastico_id = $__anno_scolastico_corrente_id;");
	$ore_40_con_studenti = $ore_40_con_studenti + $ore_corsi_di_recupero;

	// a queste vanno aggiunte le ore attribuite
	$query = "	SELECT
					ore_previste_attivita.id AS ore_previste_attivita_id,
					ore_previste_attivita.ore AS ore_previste_attivita_ore,
					ore_previste_attivita.dettaglio AS ore_previste_attivita_dettaglio,
					ore_previste_tipo_attivita.id AS ore_previste_tipo_attivita_id,
					ore_previste_tipo_attivita.categoria AS ore_previste_tipo_attivita_categoria,
					ore_previste_tipo_attivita.da_rendicontare AS ore_previste_tipo_attivita_da_rendicontare,
					ore_previste_tipo_attivita.nome AS ore_previste_tipo_attivita_nome
					
				FROM ore_previste_attivita ore_previste_attivita
				INNER JOIN ore_previste_tipo_attivita ore_previste_tipo_attivita
				ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
				WHERE ore_previste_attivita.anno_scolastico_id = $__anno_scolastico_corrente_id
				AND ore_previste_attivita.docente_id = $docenteId
                AND ore_previste_tipo_attivita.inserito_da_docente = false
                AND ore_previste_tipo_attivita.previsto_da_docente = false;";

	foreach(dbGetAll($query) as $attivita) {
	    switch ($attivita['ore_previste_tipo_attivita_categoria'])  {
	        case "funzionali":
	            $ore_70_funzionali = $ore_70_funzionali + $attivita['ore_previste_attivita_ore'];
	            break;

	        case "sostituzioni":
	            $ore_40_sostituzioni_di_ufficio = $ore_40_sostituzioni_di_ufficio + $attivita['ore_previste_attivita_ore'];
	            break;

	        case "aggiornamento":
	            $totale_aggiornamento = $totale_aggiornamento + $attivita['ore_previste_attivita_ore'];
	            break;

	        case "con studenti":
	            $ore_40_con_studenti = $ore_40_con_studenti + $attivita['ore_previste_attivita_ore'];
	            break;

	        default:
	            warning('attivita sconosciuta: '.$attivita['ore_previste_tipo_attivita_categoria']);
	            break;
	    }
	}

	// gli sportelli (di vario tipo) se attivati: todo: controllare che la categoria sia di tipo automaticamente inserito
	if(getSettingsValue("config", "sportelli", false)) {
		$totaleOreSportello = 0;
		$query = "	SELECT sportello.id AS sportello_id, sportello.*,
					( SELECT COUNT(id) FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.presente) AS numero_presenti,
					( SELECT COUNT(id) FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.iscritto) AS numero_iscritti
					FROM sportello sportello
					WHERE sportello.anno_scolastico_id = $__anno_scolastico_corrente_id AND sportello.docente_id = $docenteId AND sportello.firmato = true AND sportello.cancellato = false
					ORDER BY sportello.categoria, sportello.data DESC ;" ;
		foreach(dbGetAll($query) as $sportello) {
			debug('calcolo sportello ' .$sportello['sportello_id']);
			$oreSportello = 0;
			if ($sportello['numero_presenti'] > 0) {
				$oreSportello = $sportello['numero_ore'];
			} else if ($sportello['numero_iscritti'] > 0) {
				$oreSportello = 1;
			}
			debug('oreSportello=' .$oreSportello);
			$totaleOreSportello += $oreSportello;
		}
		debug('totaleOreSportello=' .$totaleOreSportello);
		$ore_40_con_studenti = $ore_40_con_studenti + $totaleOreSportello;
	}

	// i gruppi di lavoro
	$query = "SELECT COALESCE(SUM(gruppo_incontro_partecipazione.ore), 0)
			FROM gruppo_incontro_partecipazione
			INNER JOIN docente ON gruppo_incontro_partecipazione.docente_id = docente.id
			INNER JOIN gruppo_incontro ON gruppo_incontro_partecipazione.gruppo_incontro_id = gruppo_incontro.id
			INNER JOIN gruppo ON gruppo_incontro.gruppo_id = gruppo.id
			WHERE gruppo_incontro_partecipazione.docente_id = $docenteId
			AND gruppo_incontro_partecipazione.ha_partecipato = true
			AND gruppo.anno_scolastico_id = $__anno_scolastico_corrente_id
			AND gruppo_incontro.effettuato = true
			AND gruppo.dipartimento = false
			AND gruppo.clil = false;";
	$ore_gruppi = dbGetValue($query);
	$ore_70_funzionali = $ore_70_funzionali + $ore_gruppi;

	// le sostituzioni ora sono in una tabella a parte
	$query = "SELECT COALESCE(SUM(ora), 0) FROM sostituzione_docente WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docenteId;";
	$ore_40_sostituzioni_di_ufficio = dbGetValue($query);

	// infine le ore dei viaggi (che vanno con gli studenti)
	$query = "	SELECT
					viaggio_ore_recuperate.id AS viaggio_ore_recuperate_id,
					viaggio_ore_recuperate.ore AS viaggio_ore_recuperate_ore
					
				FROM viaggio_ore_recuperate viaggio_ore_recuperate
				INNER JOIN viaggio viaggio
				ON viaggio_ore_recuperate.viaggio_id = viaggio.id
				WHERE viaggio.anno_scolastico_id = $__anno_scolastico_corrente_id
				AND viaggio.docente_id = $docenteId;";

	foreach(dbGetAll($query) as $viaggio) {
	    $ore_40_con_studenti = $ore_40_con_studenti + $viaggio['viaggio_ore_recuperate_ore'];
	}

	// se attiva la gestione viaggi semplificata, aggiungo le ore dei viaggi
	if(getSettingsValue("config", "gestioneViaggiSemplificata", false)) {
		$ore_viaggi_semplificata = dbGetValue("SELECT COALESCE(SUM(ore), 0) FROM viaggio_diaria_fatta WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docenteId;");
		debug('ore_viaggi_semplificata=' . $ore_viaggi_semplificata);
		$ore_40_con_studenti = $ore_40_con_studenti + $ore_viaggi_semplificata;
	}

	// controllo quante ore di aggiornamento dovrebbe fare
	$ore_aggiornamento_dovute = dbGetValue("SELECT ore_40_aggiornamento FROM ore_dovute WHERE docente_id = $docenteId AND anno_scolastico_id = $__anno_scolastico_corrente_id;");

	// le eccedenti di 10 delle 40 aggiornamento le mette nelle 80 aggiornamento (fino a 10)
	$ore_40_aggiornamento = min($totale_aggiornamento, $ore_aggiornamento_dovute);
	if ($totale_aggiornamento > $ore_aggiornamento_dovute) {
		$ore_80_aggiornamento = min(($totale_aggiornamento - $ore_aggiornamento_dovute), $ore_aggiornamento_dovute);
	}

	// tutte le ore con studenti superiori alle 40 dovute le mette nelle 70
	if ($ore_con_studenti_40_dovute < $ore_40_con_studenti) {
		$ore_70_con_studenti = $ore_40_con_studenti - $ore_con_studenti_40_dovute;
		$ore_40_con_studenti = $ore_con_studenti_40_dovute;
	}

	$ore_40_totali = 0 + round($ore_40_aggiornamento + ($ore_40_sostituzioni_di_ufficio * 50 / 60) + ($ore_40_con_studenti * 50 / 60));
	$ore_70_totali = 0 + round($ore_70_funzionali + $ore_70_con_studenti);

	// aggiorna i valori della tabella ore_fatte

	// inserito di nuovo dal seguente:
	// 						ore_40_sostituzioni_di_ufficio = '$ore_40_sostituzioni_di_ufficio',
	$query = "	UPDATE ore_fatte SET
						ore_40_sostituzioni_di_ufficio = '$ore_40_sostituzioni_di_ufficio',
						ore_40_aggiornamento = '$ore_40_aggiornamento',
						ore_40_con_studenti = '$ore_40_con_studenti',
						ore_70_funzionali = '$ore_70_funzionali',
						ore_70_con_studenti = '$ore_70_con_studenti',
						ore_40_totale = '$ore_40_totali',
						ore_70_totale = '$ore_70_totali',
						ore_80_aggiornamento_facoltativo = '$ore_80_aggiornamento'
				WHERE
					docente_id = $docenteId
				AND
					anno_scolastico_id = $__anno_scolastico_corrente_id;";
	debug("aggiornate le ore fatte per il docente id=$docenteId");
	dbExec($query);
}
?>
