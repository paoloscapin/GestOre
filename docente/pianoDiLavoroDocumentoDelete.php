<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

 require_once '../common/checkSession.php';
 ruoloRichiesto('docente','segreteria-didattica','dirigente');

 if(isset($_POST['id']) && isset($_POST['id']) != "") {
    $id = $_POST['id'];
    $piano_di_lavoro_id = $_POST['piano_di_lavoro_id'];
    $posizione = $_POST['posizione'];

    // cancella il modulo
    dbExec("DELETE FROM piano_di_lavoro_contenuto WHERE id = '$id'");

    // ora deve controllare tutti i numeri dei moduli
    $pos = 1;
    foreach(dbGetAll("SELECT * FROM piano_di_lavoro_contenuto WHERE piano_di_lavoro_id = $piano_di_lavoro_id ORDER BY posizione; ") as $modulo) {
        if ($pos != $modulo[posizione]) {
            dbExec("UPDATE piano_di_lavoro_contenuto SET posizione = $pos WHERE id = '$modulo[id]'");
        }
        $pos = $pos + 1;
    }

    info("cancellato piano_di_lavoro_contenuto id=$id posizione=$posizione");
}
?>