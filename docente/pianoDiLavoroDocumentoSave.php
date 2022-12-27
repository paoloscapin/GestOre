<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('docente','segreteria-didattica','dirigente');

if(isset($_POST)) {
	$id = $_POST['id'];
    $piano_di_lavoro_id = $_POST['piano_di_lavoro_id'];
    $titolo = escapePost('titolo');
    $testo = escapePost('testo');

    if ($id > 0) {
        $query = "UPDATE piano_di_lavoro_contenuto SET titolo = '$titolo', testo = '$testo' WHERE id = '$id'";
        dbExec($query);
        info("aggiornato piano_di_lavoro_contenuto id=$id titolo=$titolo");
    } else {
        // cerca la posizione piu' alta
        $lastPos = dbGetValue("SELECT COALESCE(MAX(piano_di_lavoro_contenuto.posizione),0) FROM `piano_di_lavoro_contenuto` WHERE piano_di_lavoro_id=$piano_di_lavoro_id;");
        $posizione = $lastPos + 1;
        $query = "INSERT INTO piano_di_lavoro_contenuto(titolo, testo, posizione, piano_di_lavoro_id) VALUES('$titolo', '$testo', $posizione, $piano_di_lavoro_id)";
        dbExec($query);
        $id = dblastId();
        info("aggiunto piano_di_lavoro_contenuto id=$id titolo=$titolo posizione=$posizione");    
    }
}
?>