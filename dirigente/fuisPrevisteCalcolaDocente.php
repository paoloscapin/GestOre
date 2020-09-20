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
ruoloRichiesto('dirigente');

function calcolaFuisDocente($localDocenteId) {
	global $__anno_scolastico_corrente_id;
	global $__importi;

    $fuisPrevisto = array();
	// calcola il totale del fuis assegnato
    $assegnato = dbGetValue("SELECT COALESCE(SUM(importo), 0) FROM fuis_assegnato WHERE docente_id = $localDocenteId AND anno_scolastico_id = $__anno_scolastico_corrente_id;");

	// calcola il totale diaria viaggi
    $diaria_res = dbGetFirst("SELECT COALESCE(SUM(giorni_con_pernottamento), 0) AS giorni_con_pernottamento, COALESCE(SUM(giorni_senza_pernottamento), 0) AS giorni_senza_pernottamento FROM viaggio_diaria_prevista WHERE docente_id = $localDocenteId AND anno_scolastico_id = $__anno_scolastico_corrente_id;");
    $diaria = $diaria_res['giorni_senza_pernottamento'] * $__importi['importo_diaria_senza_pernottamento'] + $diaria_res['giorni_con_pernottamento'] * $__importi['importo_diaria_con_pernottamento'];

    // calcola le ore (prima le dovute)
    $dovute = dbGetFirst("SELECT * FROM ore_dovute WHERE docente_id = $localDocenteId AND anno_scolastico_id = $__anno_scolastico_corrente_id;");
    $dovuteFunzionali = $dovute['ore_70_funzionali'];
    $dovuteConStudenti = $dovute['ore_70_con_studenti'] + $dovute['ore_40_con_studenti'];

    // le previste funzionali e con studenti
    $oreFunzionali = dbGetValue("SELECT COALESCE(SUM(ore_previste_attivita.ore)) FROM ore_previste_attivita INNER JOIN ore_previste_tipo_attivita ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
        WHERE ore_previste_attivita.docente_id = $localDocenteId AND ore_previste_attivita.anno_scolastico_id = $__anno_scolastico_corrente_id AND ore_previste_tipo_attivita.categoria='funzionali'");
    $oreConStudenti = dbGetValue("SELECT COALESCE(SUM(ore_previste_attivita.ore)) FROM ore_previste_attivita INNER JOIN ore_previste_tipo_attivita ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
        WHERE ore_previste_attivita.docente_id = $localDocenteId AND ore_previste_attivita.anno_scolastico_id = $__anno_scolastico_corrente_id AND ore_previste_tipo_attivita.categoria='con studenti'");

    $ore_corsi_di_recupero = dbGetValue("SELECT COALESCE(SUM(corso_di_recupero.ore_recuperate), 0) FROM corso_di_recupero WHERE docente_id = $localDocenteId AND anno_scolastico_id = $__anno_scolastico_corrente_id;");

    $bilancioFunzionali = $oreFunzionali - $dovuteFunzionali;
    $bilancioConStudenti = $oreConStudenti - $dovuteConStudenti;
    debug('dovuteFunzionali='.$dovuteFunzionali);
    debug('oreFunzionali='.$oreFunzionali);
    debug('bilancioFunzionali='.$bilancioFunzionali);

    debug('dovuteConStudenti='.$dovuteConStudenti);
    debug('oreConStudenti='.$oreConStudenti);
    debug('bilancioConStudenti='.$bilancioConStudenti);
    // ------------------------------------------------------------
    // applica le regole per vedere se deve dei soldi e quanti

	// se si possono compensare in ore quelle mancanti funzionali con quelle fatte in piu' con studenti lo aggiorna ora
	if (getSettingsValue('fuis','accetta_con_studenti_per_funzionali', false)) {
		if ($bilancioFunzionali < 0) {
			$daSpostare = -$bilancioFunzionali;
			// se non ce ne sono abbastanza con studenti, sposta tutte quelle che ci sono
			if ($bilancioConStudenti < $daSpostare) {
				$daSpostare = $bilancioConStudenti;
			}
			$bilancioConStudenti = $bilancioConStudenti - $daSpostare;
			$bilancioFunzionali = $bilancioFunzionali + $daSpostare;
            debug('spostate bilancioFunzionali='.$bilancioFunzionali);
            debug('spostate bilancioConStudenti='.$bilancioConStudenti);
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

    $ore = $fuisFunzionale + $fuisConStudenti;

    // nessuno deve tornare dei soldi:
    $ore = max($ore, 0);
    // ------------------------------------------------------------

    // CLIL
    $query = "SELECT COALESCE(SUM(ore_previste_attivita.ore),0) FROM ore_previste_attivita INNER JOIN ore_previste_tipo_attivita ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
        WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $localDocenteId AND ore_previste_tipo_attivita.categoria = 'CLIL' AND ore_previste_tipo_attivita.nome = 'funzionali' ;";
    $clilFunzionaleOre=dbGetValue($query);
    $clilFunzionale = $clilFunzionaleOre * $__importi['importo_ore_funzionali'];

    $query = "SELECT COALESCE(SUM(ore_previste_attivita.ore),0) FROM ore_previste_attivita INNER JOIN ore_previste_tipo_attivita ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
        WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $localDocenteId AND ore_previste_tipo_attivita.categoria = 'CLIL' AND ore_previste_tipo_attivita.nome = 'con studenti' ;";
    $clilConStudentiOre=dbGetValue($query);
    $clilConStudenti = $clilConStudentiOre * $__importi['importo_ore_con_studenti'];

    // extra ore dei corsi di recupero
    $oreExtraCorsiDiRecupero = dbGetValue("SELECT  COALESCE(SUM(corso_di_recupero.ore_pagamento_extra), 0) FROM corso_di_recupero WHERE docente_id = $localDocenteId AND anno_scolastico_id = $__anno_scolastico_corrente_id;");

    $extraCorsiDiRecupero = $oreExtraCorsiDiRecupero * $__importi['importo_ore_corsi_di_recupero'];

    $fuisPrevisto = array("assegnato"=>$assegnato,"ore"=>$ore,"diaria"=>$diaria,"clilFunzionale"=>$clilFunzionale,"clilConStudenti"=>$clilConStudenti,"extraCorsiDiRecupero"=>$extraCorsiDiRecupero);
    return $fuisPrevisto;
}

// se viene chiamato con un post, allora ritonna il valore con echo
if(isset($_POST['docente_id']) && isset($_POST['docente_id']) != "") {
    $docente_id = $_POST['docente_id'];
    $fuisPrevisto = calcolaFuisDocente($docente_id);
    echo json_encode($fuisPrevisto);
}

?>
