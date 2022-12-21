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
	$piano_di_lavoro_id = $_POST['piano_di_lavoro_id'];
    $piano_di_lavoro_contenuto_posizione = $_POST['piano_di_lavoro_contenuto_posizione'];
    $di_quanto = escapePost('di_quanto');

    // calcola la nuova posizione
    $vecchiaPosizione = $piano_di_lavoro_contenuto_posizione;
    $nuovaPosizione = $vecchiaPosizione + $di_quanto;
    
    // controlla quale e' la posizione piu' alta
    $lastPos = dbGetValue("SELECT COALESCE(MAX(piano_di_lavoro_contenuto.posizione),0) FROM `piano_di_lavoro_contenuto` WHERE piano_di_lavoro_id=$piano_di_lavoro_id;");
    
    // controlla l'ordine delle posizioni
    if ($nuovaPosizione < 1 || $nuovaPosizione > $lastPos) {
        warning("cerco di muovere da posizione $vecchiaPosizione a posizione $nuovaPosizione il contenuto del piano $piano_di_lavoro_id");
        return;
    }

    $vecchiaPosizioneId = dbGetValue("SELECT id FROM `piano_di_lavoro_contenuto` WHERE piano_di_lavoro_id=$piano_di_lavoro_id and posizione=$vecchiaPosizione;");
    $nuovaPosizioneId = dbGetValue("SELECT id FROM `piano_di_lavoro_contenuto` WHERE piano_di_lavoro_id=$piano_di_lavoro_id and posizione=$nuovaPosizione;");
    dbExec("UPDATE piano_di_lavoro_contenuto SET posizione = $nuovaPosizione WHERE id = '$vecchiaPosizioneId'");
    dbExec("UPDATE piano_di_lavoro_contenuto SET posizione = $vecchiaPosizione WHERE id = '$nuovaPosizioneId'");
    info("spostato contenuto da posizione $vecchiaPosizione a $nuovaPosizione piano_di_lavoro_id=$piano_di_lavoro_id");    
}
?>