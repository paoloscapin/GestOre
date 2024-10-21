<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

 require_once '../common/checkSession.php';
 ruoloRichiesto('admin');

 if(isset($_POST['id']) && isset($_POST['id']) != "") {
    $id = $_POST['id'];
    $modulistica_template_id = $_POST['modulistica_template_id'];
    $posizione = $_POST['posizione'];

    // cancella il campo
    dbExec("DELETE FROM modulistica_template_campo WHERE id = '$id'");

    // ora deve controllare tutti i numeri dei moduli
    $pos = 1;
    foreach(dbGetAll("SELECT * FROM modulistica_template_campo WHERE modulistica_template_id = $modulistica_template_id ORDER BY posizione; ") as $campo) {
        if ($pos != $modulo[posizione]) {
            dbExec("UPDATE modulistica_template_campo SET posizione = $pos WHERE id = '$campo[id]'");
        }
        $pos = $pos + 1;
    }

    info("cancellato modulistica_template_campo id=$id posizione=$posizione");
}
?>