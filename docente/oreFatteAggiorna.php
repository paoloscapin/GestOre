<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/importi_load.php';

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
	$messaggioPreviste = '';

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
	$fuisFunzionalePreviste = $bilancioFunzionaliPreviste * $__importi['importo_ore_funzionali'];
	$fuisConStudentiPreviste = $bilancioConStudentiPreviste * $__importi['importo_ore_con_studenti'];

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
				$messaggioPreviste = $messaggioPreviste . $daSpostare ." ore con studenti verranno usate per coprire " . $daSpostare ." ore funzionali mancanti. ";
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
				$messaggioPreviste = $messaggioPreviste . $daSpostare ." ore funzionali verranno usate per coprire " . $daSpostare ." ore con studenti mancanti. ";
				debug('spostate funzionali in con studenti bilancioFunzionali='.$bilancioFunzionaliPreviste.' bilancioConStudenti='.$bilancioConStudentiPreviste);
			}
		}
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
	// poi le fatte
    $bilancioFunzionali = $oreFunzionali - $oreFunzionaliDovute;
    $bilancioConStudenti = $oreConStudenti - $oreConStudentiDovute;

    // le sostituzioni sono da considerare come ore con studenti
	$bilancioSostituzioni = $oreSostituzione - $oreSostituzioniDovute;
	// se configuratato per non sottrarre le sostituzioni, ignora questa parte se sono dovute dal docente (mette a 0), mentre la tiene se il docente ne ha fatte oltre le previste
	if (! getSettingsValue('fuis','rimuovi_sostituzioni_non_fatte', true)) {
		if ($bilancioSostituzioni < 0) {
			$bilancioSostituzioni = 0;
		}
	}

    // a questo punto aggiorna le ore con studenti includendo le sostituzioni
    $bilancioConStudenti = $bilancioConStudenti + $bilancioSostituzioni;
    debug('bilancioConStudenti incluse sostituzioni='.$bilancioConStudenti);

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

    // possibile controllo se le ore fatte eccedono le previsioni
	if (getSettingsValue('fuis','rimuovi_fatte_eccedenti_previsione', false)) {
        $pagabiliFunzionali = max($oreFunzionaliPreviste - $oreFunzionaliDovute,0);
        $pagabiliConStudenti = max($oreConStudentiPreviste - $oreConStudentiDovute,0);
        if ($bilancioFunzionali > 0 && $bilancioFunzionali > $pagabiliFunzionali) {
            $bilancioDifferenzaFunzionali = $bilancioFunzionali - $pagabiliFunzionali;
            $bilancioFunzionali = $pagabiliFunzionali;
            $messaggioEccesso = $messaggioEccesso . $bilancioDifferenzaFunzionali . " ore funzionali non concordate non saranno incluse nel conteggio FUIS: considerate solo ". $bilancioFunzionali .".";
        }
        if ($bilancioConStudenti > 0 && $bilancioConStudenti > $pagabiliConStudenti) {
            $bilancioDifferenzaConStudenti = $bilancioConStudenti - $pagabiliConStudenti;
            $bilancioConStudenti = $pagabiliConStudenti;
            if ( ! empty($messaggioEccesso)) {
                $messaggioEccesso = $messaggioEccesso . "</br>";
            }
            $messaggioEccesso = $messaggioEccesso . $bilancioDifferenzaConStudenti . " ore con studenti non concordate non saranno incluse nel conteggio FUIS: considerate solo ". $bilancioConStudenti .". ";
        }
        debug('messaggioEccesso=' . $messaggioEccesso);
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

    $clilFatteFunzionaliBilancio = $oreClilFunzionali;
    $clilFatteConStudentiBilancio = $oreClilConStudenti;

    // possibile controllo se le ore fatte clil eccedono le previsioni
	if (getSettingsValue('fuis','rimuovi_fatte_clil_eccedenti_previsione', false)) {
        if ($oreClilFunzionali > $oreClilFunzionaliPreviste) {
            if ( ! empty($messaggioEccesso)) {
                $messaggioEccesso = $messaggioEccesso . "</br>";
            }
            $clilFatteFunzionaliBilancio = $oreClilFunzionaliPreviste;
            $messaggioEccesso = $messaggioEccesso . ($oreClilFunzionali - $oreClilFunzionaliPreviste) . " ore CLIL funzionali non concordate non saranno incluse nel conteggio FUIS: considerate solo ". $clilFatteFunzionaliBilancio .". ";
        }
        if ($oreClilConStudenti > $oreClilConStudentiPreviste) {
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

	$totale = $totale + compact('messaggio', 'messaggioEccesso', 'fuisFunzionale', 'fuisConStudenti', 'fuisOre', 'fuisClilFunzionale', 'fuisClilConStudenti', 'fuisOrientamentoFunzionale', 'fuisOrientamentoConStudenti', 'fuisExtraCorsiDiRecupero', 'fuisAssegnato');

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
