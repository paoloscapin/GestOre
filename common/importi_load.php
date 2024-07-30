<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

$__importi = dbGetFirst("SELECT * FROM importo WHERE anno_scolastico_id = $__anno_scolastico_corrente_id;");

// se non trova il record di quest'anno, prova a prenderlo dall'anno scorso
if (empty($__importi)) {
    $__importi = dbGetFirst("SELECT * FROM importo WHERE anno_scolastico_id = $__anno_scolastico_scorso_id;");

    // se trova un record dello scorso anno, lo copia sui valori di quest'anno
    if (! empty($__importi)) {
        $__importo_fuis = $__importi['fuis'];
        $__importo_fuis_clil = $__importi['fuis_clil'];
        $__importo_fuis_orientamento = $__importi['fuis_orientamento'];
        $__importo_bonus = $__importi['bonus'];
        $__importo_ore_con_studenti = $__importi['importo_ore_con_studenti'];
        $__importo_ore_funzionali = $__importi['importo_ore_funzionali'];
        $__importo_ore_corsi_di_recupero = $__importi['importo_ore_corsi_di_recupero'];
        $__importo_diaria_con_pernottamento = $__importi['importo_diaria_con_pernottamento'];
        $__importo_diaria_senza_pernottamento = $__importi['importo_diaria_senza_pernottamento'];

        $query = "INSERT INTO importo (fuis, fuis_clil, fuis_orientamento, bonus, importo_ore_con_studenti, importo_ore_funzionali, importo_ore_corsi_di_recupero, importo_diaria_con_pernottamento, importo_diaria_senza_pernottamento, anno_scolastico_id)
        VALUES ('".$__importi['fuis']."','".$__importi['fuis_clil']."','".$__importi['fuis_orientamento']."','".$__importi['bonus']."','".$__importi['importo_ore_con_studenti']."','".$__importi['importo_ore_funzionali']."','".$__importi['importo_ore_corsi_di_recupero']."','".$__importi['importo_diaria_con_pernottamento']."','".$__importi['importo_diaria_senza_pernottamento']."',$__anno_scolastico_corrente_id)";
        dbExec($query);
        $id = dblastId();
        info("inserito importo id=$id preso dall'anno precedente");
    }
}

// se ha trovato qualcosa, lo usa
if (! empty($__importi)) {
    $__importo_id = $__importi['id'];
    $__importo_fuis = $__importi['fuis'];
    $__importo_fuis_clil = $__importi['fuis_clil'];
    $__importo_fuis_orientamento = $__importi['fuis_orientamento'];
    $__importo_bonus = $__importi['bonus'];
    $__importo_ore_con_studenti = $__importi['importo_ore_con_studenti'];
    $__importo_ore_funzionali = $__importi['importo_ore_funzionali'];
    $__importo_ore_corsi_di_recupero = $__importi['importo_ore_corsi_di_recupero'];
    $__importo_diaria_con_pernottamento = $__importi['importo_diaria_con_pernottamento'];
    $__importo_diaria_senza_pernottamento = $__importi['importo_diaria_senza_pernottamento'];
} else {
    $__importo_id = -1;
    $__importo_fuis = 0;
    $__importo_fuis_clil = 0;
    $__importo_fuis_orientamento = 0;
    $__importo_bonus = 0;
}
?>