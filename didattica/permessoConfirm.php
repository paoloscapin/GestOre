<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('segreteria-didattica', 'dirigente');

if (!empty($_POST)) {
    $id = intval($_POST['id']);

    if ($id > 0) {
        $query = "UPDATE permessi_uscita SET stato = '2' WHERE id = '$id'";
        dbExec($query);
        info("aggiornato permesso id=$id");
        echo "ok";
    } else {
        error("Parametri non validi (id=$id)");
        echo "errore";
    }
}

