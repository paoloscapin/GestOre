<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

if(isset($_POST)) {
    // include Database connection file
    require_once '../common/checkSession.php';
    require_once '../common/connect.php';
    
    // get values
    $viaggio_id = $_POST['viaggio_id'];
    $importo_diaria = $_POST['importo_diaria'];
    $numero_ore = $_POST['numero_ore'];
    $data = date('Y-m-d');
    
    if ($importo_diaria > 0) {
        $query = "INSERT INTO fuis_viaggio_diaria(importo, liquidato, data_richiesta_liquidazione, viaggio_id) VALUES('$importo_diaria', true, '$data', '$viaggio_id')";
        debug($query);
        dbExec($query);
    }
    
    if ($numero_ore > 0) {
        $query = "INSERT INTO viaggio_ore_recuperate(ore, viaggio_id) VALUES('$numero_ore', '$viaggio_id')";
        debug($query);
        dbExec($query);
    }

    // chiude il viaggio
    $query = "UPDATE viaggio SET stato = 'chiuso' WHERE id = '$viaggio_id'";
    debug($query);
    dbExec($query);
}
?>