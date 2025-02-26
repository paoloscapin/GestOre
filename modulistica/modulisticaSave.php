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
    $produci_pdf = escapePost('produci_pdf');
    $approva = $_POST['approva'];
    $messaggio_approvazione = $_POST['messaggio_approvazione'];
    $email_di_avviso = escapePost('email_di_avviso');
    $email_to = escapePost('email_to');
    $approva = $_POST['approva'];
    $email_approva = escapePost('email_approva');
    $firma_forte = $_POST['firma_forte'];
    $valido = $_POST['valido'];
    $categoria_id = $_POST['categoria_id'];

    if ($id > 0) {
        $query = "UPDATE modulistica_template SET nome = '$nome', intestazione = '$intestazione', produci_pdf = '$produci_pdf', email_to = '$email_to', email_di_avviso = '$email_di_avviso', approva = '$approva', messaggio_approvazione = '$messaggio_approvazione', email_approva = '$email_approva', firma_forte = '$firma_forte', valido = '$valido', modulistica_categoria_id = '$categoria_id' WHERE id = '$id'";
        dbExec($query);
        info("aggiornato modulistica_template id=$id nome=$nome valido=$valido intestazione=$intestazione");
    } else {
        $query = "INSERT INTO modulistica_template(nome, intestazione, produci_pdf, email_to, email_di_avviso, approva, messaggio_approvazione, email_approva, firma_forte, valido, modulistica_categoria_id) VALUES('$nome', '$intestazione', '$produci_pdf', '$email_to', '$email_di_avviso', '$approva', 'messaggio_approvazione', '$email_approva', '$firma_forte', '$valido', '$categoria_id')";
        dbExec($query);
        $id = dblastId();
        info("aggiunto modulistica_template id=$id nome=$nome valido=$valido");    
    }
}
?>