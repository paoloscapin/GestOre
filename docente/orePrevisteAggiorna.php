<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/importi_load.php';

function orePrevisteAggiorna($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile) {
	global $__anno_scolastico_corrente_id;
	global $__docente_id;
	global $__config;
	global $__importi;

	$totale = [];
	$oreCorsoDiRecuperoExtra = 0;
	$oreAggiornamento = 0;
	$diariaGiorniSenzaPernottamento = 0;
	$diariaGiorniConPernottamento = 0;
	$diariaImporto = 0;
	$messaggio = '';
	$messaggioEccesso = '';

	$oreAggiornamentoPreviste = 0;
	$oreConStudentiPreviste = 0;
	$oreFunzionaliPreviste = 0;
	$oreClilFunzionaliPreviste = 0;
	$oreClilConStudentiPreviste = 0;
	$oreOrientamentoFunzionaliPreviste = 0;
	$oreOrientamentoConStudentiPreviste = 0;

	$dataCdr = '';
	$dataPreviste = '';
	$dataAttribuite = '';
	$dataDiaria = '';

	// servono le ore dovute
	require_once '../docente/oreDovuteReadDetails.php';
	$ore_dovute = oreDovuteReadDetails($soloTotale, $docente_id, 'ore_dovute');

	// se non sono state inserite per questo docente, le lascia a zero
	if ($ore_dovute != null) {
		$oreConStudentiDovute = $ore_dovute['ore_40_con_studenti'] + $ore_dovute['ore_70_con_studenti'];
		$oreFunzionaliDovute = $ore_dovute['ore_70_funzionali'];
		$oreAggiornamentoDovute = $ore_dovute['ore_40_aggiornamento'];
		$oreSostituzioniDovute = $ore_dovute['ore_40_sostituzioni_di_ufficio'];
	}

	$totale = $totale + compact('oreConStudentiDovute', 'oreFunzionaliDovute', 'oreAggiornamentoDovute', 'oreSostituzioniDovute');

	// attivita previste (compreso html tabella)
	require_once '../docente/previsteReadRecords.php';
	$ore_previste = previsteReadRecords($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile);
	$oreAggiornamentoPreviste = $ore_previste['attivitaAggiornamento'];
	$oreConStudentiPreviste = $ore_previste['attivitaOreConStudenti'];
	$oreFunzionaliPreviste = $ore_previste['attivitaOreFunzionali'];
	$oreClilFunzionaliPreviste = $ore_previste['attivitaClilOreFunzionali'];
	$oreClilConStudentiPreviste = $ore_previste['attivitaClilOreConStudenti'];
	$oreOrientamentoFunzionaliPreviste = $ore_previste['attivitaOrientamentoOreFunzionali'];
	$oreOrientamentoConStudentiPreviste = $ore_previste['attivitaOrientamentoOreConStudenti'];
	$dataPreviste = $ore_previste['dataAttivita'];
	$totale = $totale + compact('dataPreviste');

	// previste dei corsi di recupero: per le previste controlla le firme sui corsi in itinere
	require_once '../docente/corsoDiRecuperoPrevisteReadRecords.php';
	$result = corsoDiRecuperoPrevisteReadRecords($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile, true);
	$oreConStudentiPreviste += $result['corso_di_recupero_ore_recuperate'];
	$oreConStudentiPreviste += $result['corso_di_recupero_ore_in_itinere'];
	$oreCorsoDiRecuperoExtra += $result['corso_di_recupero_ore_pagamento_extra'];
	$dataCdr = $result['dataCdr'];
	$totale = $totale + compact('dataCdr');

	// attribuite, che vanno incluse sia nelle previste che nelle fatte
	require_once '../docente/oreFatteReadAttribuite.php';
	$result = oreFatteReadAttribuite($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile);
	$oreConStudentiPreviste += $result['attribuiteOreConStudenti'];
	$oreFunzionaliPreviste += $result['attribuiteOreFunzionali'];
	$oreClilConStudentiPreviste += $result['attribuiteClilOreConStudenti'];
	$oreClilFunzionaliPreviste += $result['attribuiteClilOreFunzionali'];
	$oreOrientamentoConStudentiPreviste += $result['attribuiteOrientamentoOreConStudenti'];
	$oreOrientamentoFunzionaliPreviste += $result['attribuiteOrientamentoOreFunzionali'];
	$dataAttribuite = $result['dataAttribuite'];
	$totale = $totale + compact('dataAttribuite');

	// diaria nelle fatte e nelle previste (questo serve al viaggi gestione semplificata)
	require_once '../docente/viaggioDiariaFattaReadRecords.php';
	$result = viaggioDiariaFattaReadRecords($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile);
	$oreConStudentiPreviste += $result['diariaOre'];
	$diariaGiorniSenzaPernottamento += $result['diariaGiorniSenzaPernottamento'];
	$diariaGiorniConPernottamento += $result['diariaGiorniConPernottamento'];
	$diariaImporto += $result['diariaImporto'];
	$dataDiaria = $result['dataDiaria'];
	$totale = $totale + compact('dataDiaria');

	// aggiunge le previste al risultato totale
	$totale = $totale + compact('oreConStudentiPreviste', 'oreFunzionaliPreviste', 'oreClilConStudentiPreviste', 'oreClilFunzionaliPreviste', 'oreOrientamentoConStudentiPreviste', 'oreOrientamentoFunzionaliPreviste', 'oreAggiornamentoPreviste');

	// adesso devo calcolare il fuis
    $bilancioFunzionali = $oreFunzionaliPreviste - $oreFunzionaliDovute;
    $bilancioConStudenti = $oreConStudentiPreviste - $oreConStudentiDovute;

	// se si possono compensare in ore quelle mancanti funzionali con quelle fatte in piu' con studenti lo aggiorna ora
	if (getSettingsValue('fuis','accetta_con_studenti_per_funzionali', false)) {
		if ($bilancioFunzionali < 0 && $bilancioConStudenti > 0) {
			$daSpostare = -$bilancioFunzionali;
			debug('daSpostare='.$daSpostare);
			// se non ce ne sono abbastanza con studenti, sposta tutte quelle che ci sono
			if ($bilancioConStudenti < $daSpostare) {
				$daSpostare = $bilancioConStudenti;
				debug('daSpostare(in if)='.$daSpostare);
			}
			$bilancioConStudenti = $bilancioConStudenti - $daSpostare;
            $bilancioFunzionali = $bilancioFunzionali + $daSpostare;
            $messaggio = $messaggio . $daSpostare ." ore con studenti verranno usate per coprire " . $daSpostare ." ore funzionali mancanti. ";
            debug('spostate con studenti in funzionali bilancioFunzionali='.$bilancioFunzionali.' bilancioConStudenti='.$bilancioConStudenti);
		}
	}

	// se si possono compensare in ore quelle mancanti con studenti con quelle fatte in piu' funzionali lo aggiorna ora
	if (getSettingsValue('fuis','accetta_funzionali_per_con_studenti', false)) {
		if ($bilancioConStudenti < 0 && $bilancioFunzionali > 0) {
			$daSpostare = -$bilancioConStudenti;
			// se non ce ne sono abbastanza funzionali, sposta tutte quelle che ci sono
			if ($bilancioFunzionali < $daSpostare) {
				$daSpostare = $bilancioFunzionali;
			}
			$bilancioFunzionali = $bilancioFunzionali - $daSpostare;
            $bilancioConStudenti = $bilancioConStudenti + $daSpostare;
            $messaggio = $messaggio . $daSpostare ." ore funzionali verranno usate per coprire " . $daSpostare ." ore con studenti mancanti. ";
            debug('spostate funzionali in con studenti bilancioFunzionali='.$bilancioFunzionali.' bilancioConStudenti='.$bilancioConStudenti);
		}
    }

	// NB: non deve accadere che manchino delle ore con studenti: in quel caso il DS assegnerebbe altre attivita' o Disposizioni
	//     In caso siano rimaste in negativo ore con studenti la cosa viene qui ignorata, visto che in ogni caso il fuis non puo' diventare negativo
	$fuisFunzionale = $bilancioFunzionali * $__importi['importo_ore_funzionali'];
	$fuisConStudenti = $bilancioConStudenti * $__importi['importo_ore_con_studenti'];

	// se non configurato per compensare, i valori negativi devono essere azzerati (se ce ne sono...)
	if (!getSettingsValue('fuis','compensa_in_valore', false)) {
		$fuisFunzionale = max($fuisFunzionale, 0);
		$fuisConStudenti = max($fuisConStudenti, 0);
	}

    $fuisOre = $fuisFunzionale + $fuisConStudenti;

    // nessuno deve tornare dei soldi:
    $fuisOre = max($fuisOre, 0);

    $clilFatteFunzionaliBilancio = $oreClilFunzionaliPreviste;
    $clilFatteConStudentiBilancio = $oreClilConStudentiPreviste;

    $fuisClilFunzionale = $clilFatteFunzionaliBilancio * $__importi['importo_ore_funzionali'];
    $fuisClilConStudenti = $clilFatteConStudentiBilancio * $__importi['importo_ore_con_studenti'];

	// per ora orientamento in modo semplice, somma le ore fatte
    $fuisOrientamentoFunzionale = $oreOrientamentoFunzionaliPreviste * $__importi['importo_ore_funzionali'];
    $fuisOrientamentoConStudenti = $oreOrientamentoConStudentiPreviste * $__importi['importo_ore_con_studenti'];

	$fuisExtraCorsiDiRecupero = $oreCorsoDiRecuperoExtra * $__importi['importo_ore_corsi_di_recupero'];

	// calcola il totale del fuis assegnato
    $fuisAssegnato = dbGetValue("SELECT COALESCE(SUM(importo), 0) FROM fuis_assegnato WHERE docente_id = $docente_id AND anno_scolastico_id = $__anno_scolastico_corrente_id;");

	$totale = $totale + compact('messaggio', 'messaggioEccesso', 'fuisFunzionale', 'fuisConStudenti', 'fuisOre', 'fuisClilFunzionale', 'fuisClilConStudenti', 'fuisOrientamentoFunzionale', 'fuisOrientamentoConStudenti', 'fuisExtraCorsiDiRecupero', 'fuisAssegnato');

	return $totale;
}

// se viene chiamato con un post, allora ritonna il valore con echo
if(isset($_POST['richiesta']) && $_POST['richiesta'] == "orePrevisteAggiorna") {
	if(isset($_POST['docente_id']) && isset($_POST['docente_id']) != "") {
		$docente_id = $_POST['docente_id'];
	} else {
		$docente_id = $__docente_id;
	}
	$soloTotale = json_decode($_POST['soloTotale']);

	if(isset($_POST['operatore']) && $_POST['operatore'] == 'dirigente') {
		// se vuoi fare il dirigente, devi essere dirigente
		ruoloRichiesto('dirigente');
		// agisci quindi come dirigente
		$operatore = 'dirigente';
		// il dirigente può sempre fare modifiche
		$modificabile = true;
		// devi leggere il timestamp dell'ultimo controllo effettuato
		$ultimo_controllo = $_POST['ultimo_controllo'];
	} else {
		$operatore = 'docente';
		$ultimo_controllo = '';
		$modificabile = $__config->getOre_previsioni_aperto();
	}
	$totale = orePrevisteAggiorna($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile);

	echo json_encode($totale);
}
?>
