<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

if(isset($_POST)) {
    require_once '../common/checkSession.php';

    $viaggio_id = $_POST['viaggio_id'];
    $importo_diaria = $_POST['importo_diaria'];
    $numero_ore = $_POST['numero_ore'];
    $docente_id = $_POST['docente_id'];
    $docente_cognome_e_nome = $_POST['docente_cognome_e_nome'];
    $data = date('Y-m-d');

    if ($importo_diaria > 0) {
        $query = "INSERT INTO fuis_viaggio_diaria(importo, liquidato, data_richiesta_liquidazione, viaggio_id) VALUES('$importo_diaria', true, '$data', '$viaggio_id')";
        dbExec($query);
        $id = dblastId();
        info("aggiunto fuis_viaggio_diaria id=$id viaggio_id=$viaggio_id importo_diaria=$importo_diaria docente_id=$docente_id docente_cognome_e_nome=$docente_cognome_e_nome");
    }
    
    if ($numero_ore > 0) {
        $query = "INSERT INTO viaggio_ore_recuperate(ore, viaggio_id) VALUES('$numero_ore', '$viaggio_id')";
        dbExec($query);
        $id = dblastId();
        info("aggiunto viaggio_ore_recuperate id=$id viaggio_id=$viaggio_id numero_ore=$numero_ore docente_id=$docente_id docente_cognome_e_nome=$docente_cognome_e_nome");
        // bisogna aggiornare le ore con quelle concesse
        require_once '../docente/oreDovuteAggiornaDocente.php';
        oreFatteAggiornaDocente($docente_id);
    }

    // chiude il viaggio
    $query = "UPDATE viaggio SET stato = 'chiuso' WHERE id = '$viaggio_id'";
    dbExec($query);
    info("chiuso viaggio id=$id viaggio_id=$viaggio_id docente_id=$docente_id docente_cognome_e_nome=$docente_cognome_e_nome");
}
?>