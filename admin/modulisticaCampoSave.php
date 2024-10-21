<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('admin');

if(isset($_POST)) {
	$id = $_POST['id'];
    $modulistica_template_id = $_POST['modulistica_id'];
    $nome = escapePost('nome');
    $etichetta = escapePost('etichetta');
    $tip = escapePost('tip');
    $tipo = escapePost('tipo');
    $lista_valori = escapePost('lista_valori');
    $valore_default = escapePost('valore_default');
    $salva_valore = $_POST['salva_valore'];
    $obbligatorio = $_POST['obbligatorio'];

    if ($id > 0) {
        $query = "UPDATE modulistica_template_campo SET nome = '$nome', etichetta = '$etichetta', tip = '$tip', tipo = '$tipo', `valore_default` = '$valore_default', lista_valori = '$lista_valori', obbligatorio = '$obbligatorio', salva_valore = '$salva_valore' WHERE id = $id";
        dbExec($query);
        info("aggiornato modulistica_template_campo id=$id nome=$nome");
    } else {
        // cerca la posizione piu' alta
        $lastPos = dbGetValue("SELECT COALESCE(MAX(modulistica_template_campo.posizione),0) FROM `modulistica_template_campo` WHERE modulistica_template_id=$modulistica_template_id;");
        $posizione = $lastPos + 1;
        $query = "INSERT INTO modulistica_template_campo(nome, etichetta, tip, tipo, `valore_default`, obbligatorio, salva_valore, lista_valori, posizione, modulistica_template_id) VALUES('$nome', '$etichetta', '$tip', '$tipo', '$valore_default', '$obbligatorio', '$salva_valore', '$lista_valori', $posizione, $modulistica_template_id)";
        dbExec($query);
        $id = dblastId();
        info("aggiunto modulistica_template_campo id=$id nome=$nome posizione=$posizione");    
    }
}
?>