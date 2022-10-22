<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/importi_load.php';
// ruoloRichiesto('dirigente');

function calcolaFuisDocente($localDocenteId) {
	global $__anno_scolastico_corrente_id;
    global $__importi;

    // eventuale messaggio per chiarire la compensazione effettuata
    $messaggio = "";
    $messaggioEccesso = "";

    $fuisFatto = array();
	// calcola il totale del fuis assegnato
    $assegnato = dbGetValue("SELECT COALESCE(SUM(importo), 0) FROM fuis_assegnato WHERE docente_id = $localDocenteId AND anno_scolastico_id = $__anno_scolastico_corrente_id;");

	// calcola il totale diaria viaggi
    $diaria_res = dbGetFirst("SELECT COALESCE(SUM(giorni_con_pernottamento), 0) AS giorni_con_pernottamento, COALESCE(SUM(giorni_senza_pernottamento), 0) AS giorni_senza_pernottamento FROM viaggio_diaria_fatta WHERE docente_id = $localDocenteId AND anno_scolastico_id = $__anno_scolastico_corrente_id;");
    $diaria_nuova = $diaria_res['giorni_senza_pernottamento'] * $__importi['importo_diaria_senza_pernottamento'] + $diaria_res['giorni_con_pernottamento'] * $__importi['importo_diaria_con_pernottamento'];

    // se ci sono degli importi segnati in modo vecchio, li somma in denaro (TODO: TEMP)
    $diaria_vecchia = dbGetValue("SELECT COALESCE(SUM(fuis_viaggio_diaria.importo), 0) FROM fuis_viaggio_diaria fuis_viaggio_diaria INNER JOIN viaggio viaggio ON fuis_viaggio_diaria.viaggio_id = viaggio.id WHERE viaggio.docente_id = $localDocenteId AND viaggio.anno_scolastico_id = $__anno_scolastico_corrente_id");

    $diaria = $diaria_vecchia + $diaria_nuova;
    debug("diaria=".$diaria."  diaria_vecchia=".$diaria_vecchia."  diaria_nuova=".$diaria_nuova);

    // calcola le ore (prima le dovute)
    $dovute = dbGetFirst("SELECT * FROM ore_dovute WHERE docente_id = $localDocenteId AND anno_scolastico_id = $__anno_scolastico_corrente_id;");
    $dovuteFunzionali = $dovute['ore_70_funzionali'];
    $dovuteConStudenti = $dovute['ore_70_con_studenti'] + $dovute['ore_40_con_studenti'];
    $dovuteSostituzioni = $dovute['ore_40_sostituzioni_di_ufficio'];

    // le previste funzionali e con studenti
    $previsteFunzionali = dbGetValue("SELECT COALESCE(SUM(ore_previste_attivita.ore)) FROM ore_previste_attivita INNER JOIN ore_previste_tipo_attivita ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
        WHERE ore_previste_attivita.docente_id = $localDocenteId AND ore_previste_attivita.anno_scolastico_id = $__anno_scolastico_corrente_id AND ore_previste_tipo_attivita.categoria='funzionali'");
    $previsteConStudenti = dbGetValue("SELECT COALESCE(SUM(ore_previste_attivita.ore)) FROM ore_previste_attivita INNER JOIN ore_previste_tipo_attivita ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
        WHERE ore_previste_attivita.docente_id = $localDocenteId AND ore_previste_attivita.anno_scolastico_id = $__anno_scolastico_corrente_id AND ore_previste_tipo_attivita.categoria='con studenti'");

    // le fatte funzionali e con studenti le carica da db
    $query = "SELECT * FROM ore_fatte WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $localDocenteId;";
    $response = dbGetFirst($query);
    $fatteFunzionali = $response['ore_70_funzionali'];
    $fatteConStudenti = $response['ore_70_con_studenti'] + $response['ore_40_con_studenti'];

    // le sostituzioni ora sono in una tabella a parte
    $fatteSostituzioni = dbGetValue("SELECT COALESCE(SUM(ora), 0) FROM sostituzione_docente WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $localDocenteId;");

    $bilancioFunzionali = $fatteFunzionali - $dovuteFunzionali;
    $bilancioConStudenti = $fatteConStudenti - $dovuteConStudenti;
    debug('dovuteFunzionali='.$dovuteFunzionali);
    debug('previsteFunzionali='.$previsteFunzionali);
    debug('fatteFunzionali='.$fatteFunzionali);
    debug('bilancioFunzionali='.$bilancioFunzionali);

    debug('dovuteConStudenti='.$dovuteConStudenti);
    debug('previsteConStudenti='.$previsteConStudenti);
    debug('fatteConStudenti='.$fatteConStudenti);
    debug('bilancioConStudenti='.$bilancioConStudenti);

    debug('dovuteSostituzioni='.$dovuteSostituzioni);
    debug('fatteSostituzioni='.$fatteSostituzioni);

    // ------------------------------------------------------------
    // applica le regole per vedere se deve dei soldi e quanti

    // le sostituzioni sono da considerare come ore con studenti
	$bilancioSostituzioni = $fatteSostituzioni - $dovuteSostituzioni;
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
			// se non ce ne sono abbastanza con studenti, sposta tutte quelle che ci sono
			if ($bilancioConStudenti < $daSpostare) {
				$daSpostare = $bilancioConStudenti;
			}
			$bilancioConStudenti = $bilancioConStudenti - $daSpostare;
            $bilancioFunzionali = $bilancioFunzionali + $daSpostare;
            $messaggio = $messaggio . "Spostate " . $daSpostare ." ore con studenti per coprire " . $daSpostare ." ore funzionali mancanti. ";
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
            $messaggio = $messaggio . "Spostate " . $daSpostare ." ore funzionali per coprire " . $daSpostare ." ore con studenti mancanti. ";
            debug('spostate funzionali in con studenti bilancioFunzionali='.$bilancioFunzionali.' bilancioConStudenti='.$bilancioConStudenti);
		}
    }

    // possibile controllo se le ore fatte eccedono le previsioni
	if (getSettingsValue('fuis','rimuovi_fatte_eccedenti_previsione', false)) {
        $pagabiliFunzionali = max($previsteFunzionali - $dovuteFunzionali,0);
        $pagabiliConStudenti = max($previsteConStudenti - $dovuteConStudenti,0);
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

    $ore = $fuisFunzionale + $fuisConStudenti;

    // nessuno deve tornare dei soldi:
    $ore = max($ore, 0);

    // ------------------------------------------------------------
    // CLIL
    $clilPrevisteFunzionali=dbGetValue("SELECT COALESCE(SUM(ore_previste_attivita.ore),0) FROM ore_previste_attivita INNER JOIN ore_previste_tipo_attivita ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $localDocenteId AND ore_previste_tipo_attivita.categoria = 'CLIL' AND ore_previste_tipo_attivita.nome = 'funzionali';");
    $clilPrevisteConStudenti=dbGetValue("SELECT COALESCE(SUM(ore_previste_attivita.ore),0) FROM ore_previste_attivita INNER JOIN ore_previste_tipo_attivita ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $localDocenteId AND ore_previste_tipo_attivita.categoria = 'CLIL' AND ore_previste_tipo_attivita.nome = 'con studenti';");


    $clilFatteFunzionaliParziali=dbGetValue("SELECT COALESCE(SUM(ore_fatte_attivita_clil.ore),0) FROM ore_fatte_attivita_clil WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $localDocenteId AND con_studenti = 0;");
    // deve aggiungere le ore dei gruppi clil

	// anche i gruppi di lavoro clil entrano nel clil funzionale (ma solo nelle ore fatte, dove il responsabile ha inserito la partecipazione)
	$query = "SELECT COALESCE(SUM(gruppo_incontro_partecipazione.ore), 0) FROM gruppo_incontro_partecipazione INNER JOIN gruppo_incontro ON gruppo_incontro_partecipazione.gruppo_incontro_id = gruppo_incontro.id INNER JOIN gruppo ON gruppo_incontro.gruppo_id = gruppo.id
		WHERE gruppo_incontro_partecipazione.docente_id = $localDocenteId AND gruppo_incontro_partecipazione.ha_partecipato = true AND gruppo.anno_scolastico_id = $__anno_scolastico_corrente_id AND gruppo_incontro.effettuato = true AND gruppo.dipartimento = false AND gruppo.clil = true;";
	$clil_ore_gruppi = dbGetValue($query);
	$clilFatteFunzionali = $clilFatteFunzionaliParziali + $clil_ore_gruppi;
	debug('clilFatteFunzionaliParziali='.$clilFatteFunzionaliParziali);
	debug('clil_ore_gruppi='.$clil_ore_gruppi);
	debug('clilFatteFunzionali='.$clilFatteFunzionali);

    $clilFatteConStudenti=dbGetValue("SELECT COALESCE(SUM(ore_fatte_attivita_clil.ore),0) FROM ore_fatte_attivita_clil WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $localDocenteId AND con_studenti = 1;");
	debug('clilFatteConStudenti='.$clilFatteConStudenti);

    $clilFatteFunzionaliBilancio = $clilFatteFunzionali;
    $clilFatteConStudentiBilancio = $clilFatteConStudenti;
    debug("ore=".$ore." fatto di fuisFunzionale=".$fuisFunzionale." e fuisConStudenti=".$fuisConStudenti);

    // possibile controllo se le ore fatte clil eccedono le previsioni
	if (getSettingsValue('fuis','rimuovi_fatte_clil_eccedenti_previsione', false)) {
        if ($clilFatteFunzionali > $clilPrevisteFunzionali) {
            if ( ! empty($messaggioEccesso)) {
                $messaggioEccesso = $messaggioEccesso . "</br>";
            }
            $clilFatteFunzionaliBilancio = $clilPrevisteFunzionali;
            $messaggioEccesso = $messaggioEccesso . ($clilFatteFunzionali - $clilPrevisteFunzionali) . " ore CLIL funzionali non concordate non saranno incluse nel conteggio FUIS: considerate solo ". $clilFatteFunzionaliBilancio .". ";
        }
        debug('clilPrevisteConStudenti='.$clilPrevisteConStudenti);
        debug('clilFatteConStudenti='.$clilFatteConStudenti);
        if ($clilFatteConStudenti > $clilPrevisteConStudenti) {
            if ( ! empty($messaggioEccesso)) {
                $messaggioEccesso = $messaggioEccesso . "</br>";
            }
            $clilFatteConStudentiBilancio = $clilPrevisteConStudenti;
            $messaggioEccesso = $messaggioEccesso . ($clilFatteConStudenti - $clilPrevisteConStudenti) . " ore CLIL con studenti non concordate non saranno incluse nel conteggio FUIS: considerate solo ". $clilFatteConStudentiBilancio .". ";
        }
    }

    $clilFunzionale = $clilFatteFunzionaliBilancio * $__importi['importo_ore_funzionali'];
    $clilConStudenti = $clilFatteConStudentiBilancio * $__importi['importo_ore_con_studenti'];

    // extra ore dei corsi di recupero
    $oreExtraCorsiDiRecupero = dbGetValue("SELECT  COALESCE(SUM(corso_di_recupero.ore_pagamento_extra), 0) FROM corso_di_recupero WHERE docente_id = $localDocenteId AND anno_scolastico_id = $__anno_scolastico_corrente_id;");

    $extraCorsiDiRecupero = $oreExtraCorsiDiRecupero * $__importi['importo_ore_corsi_di_recupero'];

    $fuisFatto = array("assegnato"=>$assegnato,"ore"=>$ore,"diaria"=>$diaria,"clilFunzionale"=>$clilFunzionale,"clilConStudenti"=>$clilConStudenti,"extraCorsiDiRecupero"=>$extraCorsiDiRecupero,"messaggio"=>$messaggio,"messaggioEccesso"=>$messaggioEccesso);
    return $fuisFatto;
}

// se viene chiamato con un post, allora ritonna il valore con echo
if(isset($_POST['docente_id']) && isset($_POST['docente_id']) != "") {
    $docente_id = $_POST['docente_id'];
    $fuisFatto = calcolaFuisDocente($docente_id);
    echo json_encode($fuisFatto);
}

?>
