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
	$id = $_POST['id'];
    $nome = escapePost('nome');
    $intestazione = escapePost('intestazione');
    $email_to = escapePost('email_to');
    $approva = $_POST['approva'];
    $email_approva = escapePost('email_approva');
    $firma_forte = $_POST['firma_forte'];
    $valido = $_POST['valido'];

    if ($id > 0) {
        $query = "UPDATE modulistica_template SET nome = '$nome', intestazione = '$intestazione', email_to = '$email_to', approva = '$approva', email_approva = '$email_approva', firma_forte = '$firma_forte', valido = '$valido' WHERE id = '$id'";
        dbExec($query);
        info("aggiornato modulistica_template id=$id nome=$nome valido=$valido");
    } else {
        $query = "INSERT INTO modulistica_template(nome, intestazione, email_to, approva, email_approva, firma_forte, valido) VALUES('$nome', '$intestazione', '$email_to', '$approva', '$email_approva', '$firma_forte', '$valido')";
        dbExec($query);
        $id = dblastId();
        info("aggiunto modulistica_template id=$id nome=$nome valido=$valido");    
    }
}
?>