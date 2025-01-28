<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('modulistica');

if(isset($_POST)) {
	$modulistica_template_id = $_POST['template_id'];
    $modulistica_campo_posizione = $_POST['modulistica_campo_posizione'];
    $di_quanto = escapePost('di_quanto');

    // calcola la nuova posizione
    $vecchiaPosizione = $modulistica_campo_posizione;
    $nuovaPosizione = $vecchiaPosizione + $di_quanto;
    
    // controlla quale e' la posizione piu' alta
    $lastPos = dbGetValue("SELECT COALESCE(MAX(modulistica_template_campo.posizione),0) FROM `modulistica_template_campo` WHERE modulistica_template_id=$modulistica_template_id;");
    
    // controlla l'ordine delle posizioni
    if ($nuovaPosizione < 1 || $nuovaPosizione > $lastPos) {
        warning("cerco di muovere da posizione $vecchiaPosizione a posizione $nuovaPosizione il contenuto del piano $modulistica_template_id");
        return;
    }

    $vecchiaPosizioneId = dbGetValue("SELECT id FROM `modulistica_template_campo` WHERE modulistica_template_id=$modulistica_template_id and posizione=$vecchiaPosizione;");
    $nuovaPosizioneId = dbGetValue("SELECT id FROM `modulistica_template_campo` WHERE modulistica_template_id=$modulistica_template_id and posizione=$nuovaPosizione;");
    dbExec("UPDATE modulistica_template_campo SET posizione = $nuovaPosizione WHERE id = '$vecchiaPosizioneId'");
    dbExec("UPDATE modulistica_template_campo SET posizione = $vecchiaPosizione WHERE id = '$nuovaPosizioneId'");
    info("spostato contenuto da posizione $vecchiaPosizione a $nuovaPosizione modulistica_template_id=$modulistica_template_id");    
}
?>