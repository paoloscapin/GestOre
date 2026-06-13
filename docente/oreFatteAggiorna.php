<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/importi_load.php';

function calcolaOreFuisCompensate($oreFunzionali, $oreConStudenti) {
	$funzionali = max($oreFunzionali, 0);
	$conStudenti = max($oreConStudenti, 0);
	$debitoFunzionali = max(-$oreFunzionali, 0);
	$debitoConStudenti = max(-$oreConStudenti, 0);

	if ($funzionali > 0 && $debitoConStudenti > 0) {
		$compensazione = min($funzionali, $debitoConStudenti);
		$funzionali -= $compensazione;
		$debitoConStudenti -= $compensazione;
	}

	if ($conStudenti > 0 && $debitoFunzionali > 0) {
		$compensazione = min($conStudenti, $debitoFunzionali);
		$conStudenti -= $compensazione;
		$debitoFunzionali -= $compensazione;
	}

	return [
		'funzionali' => $funzionali,
		'con_studenti' => $conStudenti,
	];
}

function aggiungiMessaggioOre(&$messaggio, $testo) {
	if (empty($testo)) {
		return;
	}
	if (!empty($messaggio)) {
		$messaggio = $messaggio . "</br>";
	}
	$messaggio = $messaggio . $testo;
}

function formattaOre($ore, $descrizione = '') {
	$testo = $ore . ' ' . ($ore == 1 ? 'ora' : 'ore');
	if (!empty($descrizione)) {
		if ($ore == 1) {
			$descrizione = str_replace('funzionali mancanti', 'funzionale mancante', $descrizione);
			$descrizione = str_replace('funzionali', 'funzionale', $descrizione);
		}
		$testo = $testo . ' ' . $descrizione;
	}
	return $testo;
}

function formattaMessaggioCompensazione($testo) {
	return '<div style="font-weight:bold; text-align:center; background-color:#D9EDF7; color:#245269; padding:6px; margin:0;">' . $testo . '</div>';
}

function formattaMessaggioProspetto($testo, $livello = 'ok') {
	$background = ($livello == 'warning') ? '#FFC6B4' : '#BAEED0';
	return '<div style="font-weight:bold; text-align:center; background-color:' . $background . '; color:#000; padding:6px; margin:0;">' . $testo . '</div>';
}

function oreFatteAggiorna($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile) {
	global $__anno_scolastico_corrente_id;
	global $__docente_id;
	global $__config;
	global $__importi;

	$totale = [];
	$oreConStudenti = 0;
	$oreFunzionali = 0;
	$oreClilConStudenti = 0;
	$oreClilFunzionali = 0;
	$oreOrientamentoConStudenti = 0;
	$oreOrientamentoFunzionali = 0;
	$oreSostituzione = 0;
	$oreCorsoDiRecuperoExtra = 0;
	$oreAggiornamento = 0;
	$diariaGiorniSenzaPernottamento = 0;
	$diariaGiorniConPernottamento = 0;
	$diariaImporto = 0;
	$messaggio = '';
	$messaggioEccesso = '';
	$messaggioEccessoLivello = '';
	$messaggioPreviste = '';
	$messaggioPrevisteDovute = '';
	$messaggioPrevisteDovuteLivello = '';

	$oreAggiornamentoPreviste = 0;
	$oreConStudentiPreviste = 0;
	$oreFunzionaliPreviste = 0;
	$oreClilFunzionaliPreviste = 0;
	$oreClilConStudentiPreviste = 0;
	$oreOrientamentoFunzionaliPreviste = 0;
	$oreOrientamentoConStudentiPreviste = 0;
	$diariaGiorniSenzaPernottamentoPreviste = 0;
	$diariaGiorniConPernottamentoPreviste = 0;
	$diariaImportoPreviste = 0;

	$oreConStudentiDovute = 0;
	$oreFunzionaliDovute = 0;
	$oreAggiornamentoDovute = 0;
	$oreSostituzioniDovute = 0;
	$ore80DovuteCollegiDocenti = 0;
	$ore80DovuteUdienzeGenerali = 0;
	$ore80DovuteDipartimenti = 0;
	$ore80DovuteAggiornamento = 0;
	$ore80DovuteConsigliDiClasse = 0;

	// servono le ore dovute
	require_once '../docente/oreDovuteReadDetails.php';
	$ore_dovute = oreDovuteReadDetails($soloTotale, $docente_id, 'ore_dovute');

	// se non sono state inserite per questo docente, le lascia a zero
	if ($ore_dovute != null) {
		$oreConStudentiDovute = $ore_dovute['ore_40_con_studenti'] + $ore_dovute['ore_70_con_studenti'];
		$oreFunzionaliDovute = $ore_dovute['ore_70_funzionali'];
		$oreAggiornamentoDovute = $ore_dovute['ore_40_aggiornamento'];
		$oreSostituzioniDovute = $ore_dovute['ore_40_sostituzioni_di_ufficio'];
		$ore80DovuteCollegiDocenti = $ore_dovute['ore_80_collegi_docenti'];
		$ore80DovuteUdienzeGenerali = $ore_dovute['ore_80_udienze_generali'];
		$ore80DovuteDipartimenti = $ore_dovute['ore_80_dipartimenti'];
		$ore80DovuteAggiornamento = $ore_dovute['ore_80_aggiornamento_facoltativo'];
		$ore80DovuteConsigliDiClasse = $ore_dovute['ore_80_consigli_di_classe'];
	}

	$totale = $totale + compact('oreConStudentiDovute', 'oreFunzionaliDovute', 'oreAggiornamentoDovute', 'oreSostituzioniDovute', 'ore80DovuteCollegiDocenti', 'ore80DovuteUdienzeGenerali', 'ore80DovuteDipartimenti', 'ore80DovuteAggiornamento', 'ore80DovuteConsigliDiClasse');

	// attivita previste (solo i totali)
	require_once '../docente/previsteReadRecords.php';
	$ore_previste = previsteReadRecords(true, $docente_id, $operatore, $ultimo_controllo, false);
	$oreAggiornamentoPreviste = $ore_previste['attivitaAggiornamento'];
	$oreConStudentiPreviste = $ore_previste['attivitaOreConStudenti'];
	$oreFunzionaliPreviste = $ore_previste['attivitaOreFunzionali'];
	$oreClilFunzionaliPreviste = $ore_previste['attivitaClilOreFunzionali'];
	$oreClilConStudentiPreviste = $ore_previste['attivitaClilOreConStudenti'];
	$oreOrientamentoFunzionaliPreviste = $ore_previste['attivitaOrientamentoOreFunzionali'];
	$oreOrientamentoConStudentiPreviste = $ore_previste['attivitaOrientamentoOreConStudenti'];

	// previste dei corsi di recupero: per le previste controlla le firme sui corsi in itinere
	$controllaFirmeInItinere = true;
	require_once '../docente/corsoDiRecuperoPrevisteReadRecords.php';
	$result = corsoDiRecuperoPrevisteReadRecords(true, $docente_id, $operatore, $ultimo_controllo, false, false);
	$oreConStudentiPreviste += $result['corso_di_recupero_ore_recuperate'];
	$oreConStudentiPreviste += $result['corso_di_recupero_ore_in_itinere'];

	// attribuite, che vanno incluse sia nelle previste che nelle fatte
	require_once '../docente/oreFatteReadAttribuite.php';
	$result = oreFatteReadAttribuite($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile);
	$oreConStudenti += $result['attribuiteOreConStudenti'];
	$oreFunzionali += $result['attribuiteOreFunzionali'];
	$oreClilConStudenti += $result['attribuiteClilOreConStudenti'];
	$oreClilFunzionali += $result['attribuiteClilOreFunzionali'];
	$oreOrientamentoConStudenti += $result['attribuiteOrientamentoOreConStudenti'];
	$oreOrientamentoFunzionali += $result['attribuiteOrientamentoOreFunzionali'];
	$dataAttribuite = $result['dataAttribuite'];
	$totale = $totale + compact('dataAttribuite');

	// le attribuite vengono aggiunte anche alle previste
	$oreConStudentiPreviste += $result['attribuiteOreConStudenti'];
	$oreFunzionaliPreviste += $result['attribuiteOreFunzionali'];
	$oreClilConStudentiPreviste += $result['attribuiteClilOreConStudenti'];
	$oreClilFunzionaliPreviste += $result['attribuiteClilOreFunzionali'];
	$oreOrientamentoConStudentiPreviste += $result['attribuiteOrientamentoOreConStudenti'];
	$oreOrientamentoFunzionaliPreviste += $result['attribuiteOrientamentoOreFunzionali'];

	// diaria nelle fatte e nelle previste (questo serve al viaggi gestione semplificata)
	require_once '../docente/viaggioDiariaPrevistaReadRecords.php';
	$result = viaggioDiariaPrevistaReadRecords(true, $docente_id, $operatore, $ultimo_controllo, $modificabile);
	$oreConStudentiPreviste += $result['diariaOre'];
	$diariaGiorniSenzaPernottamentoPreviste += $result['diariaGiorniSenzaPernottamento'];
	$diariaGiorniConPernottamentoPreviste += $result['diariaGiorniConPernottamento'];
	$diariaImportoPreviste += $result['diariaImporto'];
	$totale = $totale + compact('diariaGiorniSenzaPernottamentoPreviste', 'diariaGiorniConPernottamentoPreviste', 'diariaImportoPreviste');

	require_once '../docente/viaggioDiariaFattaReadRecords.php';
	$result = viaggioDiariaFattaReadRecords($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile);
	$oreConStudenti += $result['diariaOre'];
	$oreConStudentiPreviste += $result['diariaOre'];
	$diariaGiorniSenzaPernottamento += $result['diariaGiorniSenzaPernottamento'];
	$diariaGiorniConPernottamento += $result['diariaGiorniConPernottamento'];
	$diariaImporto += $result['diariaImporto'];
	$dataDiaria = $result['dataDiaria'];
	$totale = $totale + compact('dataDiaria');

	// aggiunge le previste al risultato totale
	$totale = $totale + compact('oreConStudentiPreviste', 'oreFunzionaliPreviste', 'oreClilConStudentiPreviste', 'oreClilFunzionaliPreviste', 'oreOrientamentoConStudentiPreviste', 'oreOrientamentoFunzionaliPreviste', 'oreAggiornamentoPreviste');

	// corsi di recupero fatte: per le fatte controlla le firme sui corsi in itinere
	$controllaFirmeInItinere = true;
	require_once '../docente/corsoDiRecuperoPrevisteReadRecords.php';
	$result = corsoDiRecuperoPrevisteReadRecords($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile, $controllaFirmeInItinere);
	$oreConStudenti += $result['corso_di_recupero_ore_recuperate'];
	$oreConStudenti += $result['corso_di_recupero_ore_in_itinere'];
	$oreCorsoDiRecuperoExtra += $result['corso_di_recupero_ore_pagamento_extra'];
	$dataCdr = $result['dataCdr'];
	$totale = $totale + compact('dataCdr');

	// attivita' fatte
	require_once '../docente/oreFatteReadAttivita.php';
	$result = oreFatteReadAttivita($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile);
	$oreConStudenti += $result['attivitaOreConStudenti'];
	$oreFunzionali += $result['attivitaOreFunzionali'];
	$oreClilConStudenti += $result['attivitaClilOreConStudenti'];
	$oreClilFunzionali += $result['attivitaClilOreFunzionali'];
	$oreOrientamentoConStudenti += $result['attivitaOrientamentoOreConStudenti'];
	$oreOrientamentoFunzionali += $result['attivitaOrientamentoOreFunzionali'];
	$oreAggiornamento += $result['attivitaAggiornamento'];
	$dataAttivita = $result['dataAttivita'];
	$totale = $totale + compact('dataAttivita');

	// attivita' clil fatte (NBTODO: temporaneo)
	require_once '../docente/oreFatteClilReadAttivita.php';
	$result = oreFatteClilReadAttivita($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile);
	$oreClilConStudenti += $result['attivitaClilOreConStudenti'];
	$oreClilFunzionali += $result['attivitaClilOreFunzionali'];
	$dataClilAttivita = $result['dataClilAttivita'];
	$totale = $totale + compact('dataClilAttivita');

	// gruppi solo nelle fatte
	require_once '../docente/oreFatteReadGruppi.php';
	$result = oreFatteReadGruppi($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile);
	$oreFunzionali += $result['gruppiOre'];
	$oreClilFunzionali += $result['gruppiOreClil'];
	$oreOrientamentoFunzionali += $result['gruppiOreOrientamento'];
	$dataGruppi = $result['dataGruppi'];
	$totale = $totale + compact('dataGruppi');

	// sostituzioni
	require_once '../docente/oreFatteReadSostituzioni.php';
	$result = oreFatteReadSostituzioni($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile);
	$oreSostituzione += $result['sostituzioniOre'];
	$dataSostituzioni = $result['dataSostituzioni'];
	$totale = $totale + compact('dataSostituzioni');

	// sportelli nelle fatte
	require_once '../docente/oreFatteReadSportelli.php';
	$result = oreFatteReadSportelli($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile);
	$oreConStudenti += $result['sportelliOre'];
	$oreClilConStudenti += $result['sportelliOreClil'];
	$oreOrientamentoConStudenti += $result['sportelliOreOrientamento'];
	$dataSportelli = $result['dataSportelli'];
	$totale = $totale + compact('dataSportelli');

	// viaggi solo nelle fatte
	require_once '../docente/oreFatteReadViaggi.php';
	$result = oreFatteReadViaggi($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile);
	$oreConStudenti += $result['viaggiOre'];
	$dataViaggi = $result['dataViaggi'];
	$totale = $totale + compact('dataViaggi');

	$totale = $totale + compact('oreConStudenti', 'oreFunzionali', 'oreClilConStudenti', 'oreClilFunzionali', 'oreOrientamentoConStudenti', 'oreOrientamentoFunzionali', 'oreSostituzione', 'oreAggiornamento', 'diariaGiorniSenzaPernottamento', 'diariaGiorniConPernottamento', 'diariaImporto');

	// adesso devo calcolare il fuis: prima le previste
    $bilancioFunzionaliPreviste = $oreFunzionaliPreviste - $oreFunzionaliDovute;
    $bilancioConStudentiPreviste = $oreConStudentiPreviste - $oreConStudentiDovute;

	if (getSettingsValue('fuis', 'compensa_anche_previste', false)) {
		// se si possono compensare in ore quelle mancanti funzionali con quelle previste in piu' con studenti lo aggiorna ora
		if (getSettingsValue('fuis','accetta_con_studenti_per_funzionali', false)) {
			if ($bilancioFunzionaliPreviste < 0 && $bilancioConStudentiPreviste > 0) {
				$daSpostare = -$bilancioFunzionaliPreviste;
				debug('daSpostare='.$daSpostare);
				// se non ce ne sono abbastanza con studenti, sposta tutte quelle che ci sono
				if ($bilancioConStudentiPreviste < $daSpostare) {
					$daSpostare = $bilancioConStudentiPreviste;
					debug('daSpostare(in if)='.$daSpostare);
				}
				$bilancioConStudentiPreviste = $bilancioConStudentiPreviste - $daSpostare;
				$bilancioFunzionaliPreviste = $bilancioFunzionaliPreviste + $daSpostare;
				$messaggioPreviste = $messaggioPreviste . formattaMessaggioCompensazione(formattaOre($daSpostare, "con studenti") . " verranno usate per coprire " . formattaOre($daSpostare, "funzionali mancanti") . ".");
				debug('spostate con studenti in funzionali bilancioFunzionali='.$bilancioFunzionaliPreviste.' bilancioConStudenti='.$bilancioConStudentiPreviste);
			}
		}

		// se si possono compensare in ore quelle mancanti con studenti con quelle previste in piu' funzionali lo aggiorna ora
		if (getSettingsValue('fuis','accetta_funzionali_per_con_studenti', false)) {
			if ($bilancioConStudentiPreviste < 0 && $bilancioFunzionaliPreviste > 0) {
				$daSpostare = -$bilancioConStudentiPreviste;
				// se non ce ne sono abbastanza funzionali, sposta tutte quelle che ci sono
				if ($bilancioFunzionaliPreviste < $daSpostare) {
					$daSpostare = $bilancioFunzionaliPreviste;
				}
				$bilancioFunzionaliPreviste = $bilancioFunzionaliPreviste - $daSpostare;
				$bilancioConStudentiPreviste = $bilancioConStudentiPreviste + $daSpostare;
				$messaggioPreviste = $messaggioPreviste . formattaMessaggioCompensazione(formattaOre($daSpostare, "funzionali") . " verranno usate per coprire " . formattaOre($daSpostare, "con studenti mancanti") . ".");
				debug('spostate funzionali in con studenti bilancioFunzionali='.$bilancioFunzionaliPreviste.' bilancioConStudenti='.$bilancioConStudentiPreviste);
			}
		}
	}

	$fuisFunzionalePreviste = $bilancioFunzionaliPreviste * $__importi['importo_ore_funzionali'];
	$fuisConStudentiPreviste = $bilancioConStudentiPreviste * $__importi['importo_ore_con_studenti'];

	$oreFunzionaliPrevisteMancanti = max(-$bilancioFunzionaliPreviste, 0);
	$oreConStudentiPrevisteMancanti = max(-$bilancioConStudentiPreviste, 0);
	$orePrevisteMancanti = $oreFunzionaliPrevisteMancanti + $oreConStudentiPrevisteMancanti;
	$oreFunzionaliPrevisteFuis = max($bilancioFunzionaliPreviste, 0);
	$oreConStudentiPrevisteFuis = max($bilancioConStudentiPreviste, 0);
	$orePrevisteFuis = $oreFunzionaliPrevisteFuis + $oreConStudentiPrevisteFuis;

	if (!empty($messaggioPreviste)) {
		aggiungiMessaggioOre($messaggioPrevisteDovute, $messaggioPreviste);
	}
	if ($orePrevisteMancanti > 0) {
		$dettagliPrevisteMancanti = [];
		if ($oreFunzionaliPrevisteMancanti > 0) {
			$dettagliPrevisteMancanti[] = formattaOre($oreFunzionaliPrevisteMancanti, "funzionali");
		}
		if ($oreConStudentiPrevisteMancanti > 0) {
			$dettagliPrevisteMancanti[] = formattaOre($oreConStudentiPrevisteMancanti, "con studenti");
		}
		$testoPrevisteDovute = "Le ore previste sono sotto il minimo dovuto di " . formattaOre($orePrevisteMancanti);
		if (!empty($dettagliPrevisteMancanti)) {
			$testoPrevisteDovute = $testoPrevisteDovute . ": " . implode(", ", $dettagliPrevisteMancanti);
		}
		$testoPrevisteDovute = $testoPrevisteDovute . ". Occorre prevedere altre ore per arrivare al minimo dovuto.";
		aggiungiMessaggioOre($messaggioPrevisteDovute, formattaMessaggioProspetto($testoPrevisteDovute, 'warning'));
		$messaggioPrevisteDovuteLivello = 'warning';
	} elseif ($orePrevisteFuis > 0) {
		$dettagliPrevisteFuis = [];
		if ($oreFunzionaliPrevisteFuis > 0) {
			$dettagliPrevisteFuis[] = formattaOre($oreFunzionaliPrevisteFuis, "funzionali");
		}
		if ($oreConStudentiPrevisteFuis > 0) {
			$dettagliPrevisteFuis[] = formattaOre($oreConStudentiPrevisteFuis, "con studenti");
		}
		$testoPrevisteDovute = "Le ore previste coprono il minimo dovuto. Le ore oltre il minimo andranno a FUIS";
		if (!empty($dettagliPrevisteFuis)) {
			$testoPrevisteDovute = $testoPrevisteDovute . ": (" . implode(", ", $dettagliPrevisteFuis) . ")";
		}
		$testoPrevisteDovute = $testoPrevisteDovute . ".";
		aggiungiMessaggioOre($messaggioPrevisteDovute, formattaMessaggioProspetto($testoPrevisteDovute));
		$messaggioPrevisteDovuteLivello = 'ok';
	} elseif (!empty($messaggioPrevisteDovute)) {
		aggiungiMessaggioOre($messaggioPrevisteDovute, formattaMessaggioProspetto("Le ore previste coprono esattamente il minimo dovuto dopo compensazione."));
		$messaggioPrevisteDovuteLivello = 'ok';
	}

	// se non configurato per compensare, i valori negativi devono essere azzerati (se ce ne sono...)
	if (!getSettingsValue('fuis','compensa_in_valore', false)) {
		$fuisFunzionalePreviste = max($fuisFunzionalePreviste, 0);
		$fuisConStudentiPreviste = max($fuisConStudentiPreviste, 0);
	}

    $fuisOrePreviste = $fuisFunzionalePreviste + $fuisConStudentiPreviste;

    // nessuno deve tornare dei soldi:
    $fuisOrePreviste = max($fuisOrePreviste, 0);

	// fuis clil previsto
    $fuisClilFunzionalePreviste = $oreClilFunzionaliPreviste * $__importi['importo_ore_funzionali'];
    $fuisClilConStudentiPreviste = $oreClilConStudentiPreviste * $__importi['importo_ore_con_studenti'];

	// per ora orientamento in modo semplice, somma le ore fatte
    $fuisOrientamentoFunzionalePreviste = $oreOrientamentoFunzionaliPreviste * $__importi['importo_ore_funzionali'];
    $fuisOrientamentoConStudentiPreviste = $oreOrientamentoConStudentiPreviste * $__importi['importo_ore_con_studenti'];

	$totale = $totale + compact('fuisOrePreviste', 'fuisClilFunzionalePreviste', 'fuisClilConStudentiPreviste', 'fuisOrientamentoFunzionalePreviste', 'fuisOrientamentoConStudentiPreviste');

// ==================================================================================================================
// ==================================================================================================================
// ==================================================================================================================
// ==================================================================================================================
	// poi le fatte: prima si compensano eventuali mancanze rispetto alle previsioni
	// usando le ore eccedenti dell'altra voce. Solo dopo si calcola il FUIS,
	// conteggiando le ore oltre le dovute ma senza superare le previsioni approvate.
    $oreFunzionaliCompensate = $oreFunzionali;
    $oreConStudentiCompensate = $oreConStudenti;

    // le sostituzioni sono da considerare come ore con studenti nel controllo complessivo
	$bilancioSostituzioni = $oreSostituzione - $oreSostituzioniDovute;
	// se configuratato per non sottrarre le sostituzioni, ignora questa parte se sono dovute dal docente (mette a 0), mentre la tiene se il docente ne ha fatte oltre le previste
	if (! getSettingsValue('fuis','rimuovi_sostituzioni_non_fatte', true)) {
		if ($bilancioSostituzioni < 0) {
			$bilancioSostituzioni = 0;
		}
	}

    $oreConStudentiCompensate = $oreConStudentiCompensate + $bilancioSostituzioni;
    debug('oreConStudentiCompensate incluse sostituzioni='.$oreConStudentiCompensate);

	// se si possono compensare in ore quelle mancanti funzionali con quelle fatte in piu' con studenti lo aggiorna ora
	if (getSettingsValue('fuis','accetta_con_studenti_per_funzionali', false)) {
        $oreFunzionaliMancanti = max($oreFunzionaliPreviste - $oreFunzionaliCompensate, 0);
        $oreConStudentiEccedenti = max($oreConStudentiCompensate - $oreConStudentiPreviste, 0);
		if ($oreFunzionaliMancanti > 0 && $oreConStudentiEccedenti > 0) {
			$daSpostare = min($oreFunzionaliMancanti, $oreConStudentiEccedenti);
			debug('daSpostare='.$daSpostare);
			$oreConStudentiCompensate = $oreConStudentiCompensate - $daSpostare;
            $oreFunzionaliCompensate = $oreFunzionaliCompensate + $daSpostare;
            $messaggio = $messaggio . formattaMessaggioCompensazione(formattaOre($daSpostare, "con studenti") . " verranno usate per coprire " . formattaOre($daSpostare, "funzionali mancanti") . ".");
            debug('spostate con studenti in funzionali oreFunzionaliCompensate='.$oreFunzionaliCompensate.' oreConStudentiCompensate='.$oreConStudentiCompensate);
		}
	}

	// se si possono compensare in ore quelle mancanti con studenti con quelle fatte in piu' funzionali lo aggiorna ora
	if (getSettingsValue('fuis','accetta_funzionali_per_con_studenti', false)) {
        $oreConStudentiMancanti = max($oreConStudentiPreviste - $oreConStudentiCompensate, 0);
        $oreFunzionaliEccedenti = max($oreFunzionaliCompensate - $oreFunzionaliPreviste, 0);
		if ($oreConStudentiMancanti > 0 && $oreFunzionaliEccedenti > 0) {
			$daSpostare = min($oreConStudentiMancanti, $oreFunzionaliEccedenti);
			$oreFunzionaliCompensate = $oreFunzionaliCompensate - $daSpostare;
            $oreConStudentiCompensate = $oreConStudentiCompensate + $daSpostare;
            $messaggio = $messaggio . formattaMessaggioCompensazione(formattaOre($daSpostare, "funzionali") . " verranno usate per coprire " . formattaOre($daSpostare, "con studenti mancanti") . ".");
            debug('spostate funzionali in con studenti oreFunzionaliCompensate='.$oreFunzionaliCompensate.' oreConStudentiCompensate='.$oreConStudentiCompensate);
		}
    }

    $oreFunzionaliOltrePreviste = max($oreFunzionaliCompensate - $oreFunzionaliPreviste, 0);
    $oreConStudentiOltrePreviste = max($oreConStudentiCompensate - $oreConStudentiPreviste, 0);
	$oreFatteOltrePreviste = $oreFunzionaliOltrePreviste + $oreConStudentiOltrePreviste;

    if ($oreFatteOltrePreviste > 0) {
		$messaggioEccessoLivello = 'danger';
        aggiungiMessaggioOre($messaggioEccesso, formattaOre($oreFatteOltrePreviste) . " oltre le previsioni approvate non saranno incluse nel conteggio FUIS");
        $dettagliEccesso = [];
        if ($oreFunzionaliOltrePreviste > 0) {
            $dettagliEccesso[] = formattaOre($oreFunzionaliOltrePreviste, "funzionali");
        }
        if ($oreConStudentiOltrePreviste > 0) {
            $dettagliEccesso[] = formattaOre($oreConStudentiOltrePreviste, "con studenti");
        }
        if (!empty($dettagliEccesso)) {
            $messaggioEccesso = $messaggioEccesso . ": (" . implode(", ", $dettagliEccesso) . ")";
        }
        $messaggioEccesso = $messaggioEccesso . ".";
    }

	$bilancioFunzionaliFatteDovute = $oreFunzionali - $oreFunzionaliDovute;
	$bilancioConStudentiFatteDovute = ($oreConStudenti + $bilancioSostituzioni) - $oreConStudentiDovute;
	if (getSettingsValue('fuis','accetta_con_studenti_per_funzionali', false)) {
		if ($bilancioFunzionaliFatteDovute < 0 && $bilancioConStudentiFatteDovute > 0) {
			$daSpostare = min(-$bilancioFunzionaliFatteDovute, $bilancioConStudentiFatteDovute);
			$bilancioConStudentiFatteDovute = $bilancioConStudentiFatteDovute - $daSpostare;
			$bilancioFunzionaliFatteDovute = $bilancioFunzionaliFatteDovute + $daSpostare;
		}
	}
	if (getSettingsValue('fuis','accetta_funzionali_per_con_studenti', false)) {
		if ($bilancioConStudentiFatteDovute < 0 && $bilancioFunzionaliFatteDovute > 0) {
			$daSpostare = min(-$bilancioConStudentiFatteDovute, $bilancioFunzionaliFatteDovute);
			$bilancioFunzionaliFatteDovute = $bilancioFunzionaliFatteDovute - $daSpostare;
			$bilancioConStudentiFatteDovute = $bilancioConStudentiFatteDovute + $daSpostare;
		}
	}
	$oreFunzionaliFatteMancantiDovute = max(-$bilancioFunzionaliFatteDovute, 0);
	$oreConStudentiFatteMancantiDovute = max(-$bilancioConStudentiFatteDovute, 0);
	$oreFatteMancantiDovute = $oreFunzionaliFatteMancantiDovute + $oreConStudentiFatteMancantiDovute;
	$oreFunzionaliFatteSottoPreviste = max($oreFunzionaliPreviste - $oreFunzionaliCompensate, 0);
	$oreConStudentiFatteSottoPreviste = max($oreConStudentiPreviste - $oreConStudentiCompensate, 0);
	$oreFatteSottoPreviste = $oreFunzionaliFatteSottoPreviste + $oreConStudentiFatteSottoPreviste;

	if ($oreFatteMancantiDovute > 0) {
		$messaggioEccessoLivello = 'danger';
		$dettagliFatteMancantiDovute = [];
		if ($oreFunzionaliFatteMancantiDovute > 0) {
			$dettagliFatteMancantiDovute[] = formattaOre($oreFunzionaliFatteMancantiDovute, "funzionali");
		}
		if ($oreConStudentiFatteMancantiDovute > 0) {
			$dettagliFatteMancantiDovute[] = formattaOre($oreConStudentiFatteMancantiDovute, "con studenti");
		}
		$testoFatteDovute = "Attenzione: le ore fatte sono sotto il minimo dovuto di " . formattaOre($oreFatteMancantiDovute);
		if (!empty($dettagliFatteMancantiDovute)) {
			$testoFatteDovute = $testoFatteDovute . ": (" . implode(", ", $dettagliFatteMancantiDovute) . ")";
		}
		$testoFatteDovute = $testoFatteDovute . ". Queste ore devono essere obbligatoriamente svolte.";
		aggiungiMessaggioOre($messaggioEccesso, $testoFatteDovute);
	} elseif ($oreFatteSottoPreviste > 0) {
		if ($messaggioEccessoLivello !== 'danger') {
			$messaggioEccessoLivello = 'warning';
		}
		$dettagliFatteSottoPreviste = [];
		if ($oreFunzionaliFatteSottoPreviste > 0) {
			$dettagliFatteSottoPreviste[] = formattaOre($oreFunzionaliFatteSottoPreviste, "funzionali");
		}
		if ($oreConStudentiFatteSottoPreviste > 0) {
			$dettagliFatteSottoPreviste[] = formattaOre($oreConStudentiFatteSottoPreviste, "con studenti");
		}
		$testoFatteSottoPreviste = "Mancano " . formattaOre($oreFatteSottoPreviste) . " rispetto alle previsioni approvate";
		if (!empty($dettagliFatteSottoPreviste)) {
			$testoFatteSottoPreviste = $testoFatteSottoPreviste . " (" . implode(", ", $dettagliFatteSottoPreviste) . ")";
		}
		$testoFatteSottoPreviste = $testoFatteSottoPreviste . ". Le ore fatte coprono comunque il minimo dovuto; l'eventuale pagamento FUIS sara inferiore a quello previsto.";
		aggiungiMessaggioOre($messaggioEccesso, $testoFatteSottoPreviste);
	}

    $oreFunzionaliValideFuis = min($oreFunzionaliCompensate, $oreFunzionaliPreviste);
    $oreConStudentiValideFuis = min($oreConStudentiCompensate, $oreConStudentiPreviste);

	// Le ore pagabili a FUIS sono quelle oltre le dovute, ma il deficit di una voce
	// assorbe l'eventuale surplus dell'altra. Il tetto massimo resta sempre quanto previsto.
	$oreFuisMassimeDaPreviste = calcolaOreFuisCompensate(
		$oreFunzionaliPreviste - $oreFunzionaliDovute,
		$oreConStudentiPreviste - $oreConStudentiDovute
	);
	$oreFuisDaFatte = calcolaOreFuisCompensate(
		$oreFunzionaliValideFuis - $oreFunzionaliDovute,
		$oreConStudentiValideFuis - $oreConStudentiDovute
	);

    $oreFunzionaliFuis = min($oreFuisMassimeDaPreviste['funzionali'], $oreFuisDaFatte['funzionali']);
    $oreConStudentiFuis = min($oreFuisMassimeDaPreviste['con_studenti'], $oreFuisDaFatte['con_studenti']);

	$fuisFunzionale = $oreFunzionaliFuis * $__importi['importo_ore_funzionali'];
	$fuisConStudenti = $oreConStudentiFuis * $__importi['importo_ore_con_studenti'];

    $fuisOre = $fuisFunzionale + $fuisConStudenti;
    // nessuno deve tornare dei soldi:
    $fuisOre = max($fuisOre, 0);

	if ($oreFatteSottoPreviste > 0 || $oreFatteOltrePreviste > 0) {
		aggiungiMessaggioOre($messaggio, "FUIS pagato sulle ore valide: (" . formattaOre($oreFunzionaliFuis, "funzionali") . ", " . formattaOre($oreConStudentiFuis, "con studenti") . ").");
	} elseif ($oreFatteOltrePreviste == 0 && $oreFatteMancantiDovute == 0) {
		$dettagliFuisReale = [];
		if ($oreFunzionaliFuis > 0) {
			$dettagliFuisReale[] = formattaOre($oreFunzionaliFuis, "funzionali");
		}
		if ($oreConStudentiFuis > 0) {
			$dettagliFuisReale[] = formattaOre($oreConStudentiFuis, "con studenti");
		}
		$testoFattePreviste = "Hai fatto le ore previste.";
		if (!empty($dettagliFuisReale)) {
			$testoFattePreviste = $testoFattePreviste . " FUIS pagato: (" . implode(", ", $dettagliFuisReale) . ").";
		}
		aggiungiMessaggioOre($messaggio, $testoFattePreviste);
	}

    $clilFatteFunzionaliBilancio = $oreClilFunzionali;
    $clilFatteConStudentiBilancio = $oreClilConStudenti;

    // possibile controllo se le ore fatte clil eccedono le previsioni
	if (getSettingsValue('fuis','rimuovi_fatte_clil_eccedenti_previsione', false)) {
        if ($oreClilFunzionali > $oreClilFunzionaliPreviste) {
			if ($messaggioEccessoLivello !== 'danger') {
				$messaggioEccessoLivello = 'warning';
			}
            if ( ! empty($messaggioEccesso)) {
                $messaggioEccesso = $messaggioEccesso . "</br>";
            }
            $clilFatteFunzionaliBilancio = $oreClilFunzionaliPreviste;
            $messaggioEccesso = $messaggioEccesso . ($oreClilFunzionali - $oreClilFunzionaliPreviste) . " ore CLIL funzionali non concordate non saranno incluse nel conteggio FUIS: considerate solo ". $clilFatteFunzionaliBilancio .". ";
        }
        if ($oreClilConStudenti > $oreClilConStudentiPreviste) {
			if ($messaggioEccessoLivello !== 'danger') {
				$messaggioEccessoLivello = 'warning';
			}
            if ( ! empty($messaggioEccesso)) {
                $messaggioEccesso = $messaggioEccesso . "</br>";
            }
            $clilFatteConStudentiBilancio = $oreClilConStudentiPreviste;
            $messaggioEccesso = $messaggioEccesso . ($oreClilConStudenti - $oreClilConStudentiPreviste) . " ore CLIL con studenti non concordate non saranno incluse nel conteggio FUIS: considerate solo ". $clilFatteConStudentiBilancio .". ";
        }
    }

    $fuisClilFunzionale = $clilFatteFunzionaliBilancio * $__importi['importo_ore_funzionali'];
    $fuisClilConStudenti = $clilFatteConStudentiBilancio * $__importi['importo_ore_con_studenti'];

	// per ora orientamento in modo semplice, somma le ore fatte
    $fuisOrientamentoFunzionale = $oreOrientamentoFunzionali * $__importi['importo_ore_funzionali'];
    $fuisOrientamentoConStudenti = $oreOrientamentoConStudenti * $__importi['importo_ore_con_studenti'];

	$fuisExtraCorsiDiRecupero = $oreCorsoDiRecuperoExtra * $__importi['importo_ore_corsi_di_recupero'];

	// calcola il totale del fuis assegnato
    $fuisAssegnato = dbGetValue("SELECT COALESCE(SUM(importo), 0) FROM fuis_assegnato WHERE docente_id = $docente_id AND anno_scolastico_id = $__anno_scolastico_corrente_id;");

	$totale = $totale + compact('messaggio', 'messaggioEccesso', 'messaggioEccessoLivello', 'messaggioPrevisteDovute', 'messaggioPrevisteDovuteLivello', 'oreFatteOltrePreviste', 'fuisFunzionale', 'fuisConStudenti', 'fuisOre', 'fuisClilFunzionale', 'fuisClilConStudenti', 'fuisOrientamentoFunzionale', 'fuisOrientamentoConStudenti', 'fuisExtraCorsiDiRecupero', 'fuisAssegnato');

	return $totale;
}

// se viene chiamato con un post, allora ritonna il valore con echo
if(isset($_POST['richiesta']) && $_POST['richiesta'] == "oreFatteAggiorna") {
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
		$modificabile = $__config->getOre_fatte_aperto();
	}
	$totale = oreFatteAggiorna($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile);

	echo json_encode($totale);
}
?>
