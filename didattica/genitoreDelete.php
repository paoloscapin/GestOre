<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('segreteria-didattica');

if (isset($_POST)) {
    $id = $_POST['id'];

    if ($id > 0) {
        // cancello le associazioni con gli studenti
        $query = "DELETE FROM genitori_studenti WHERE id_genitore = $id";
        dbExec($query);

        $query = "DELETE FROM genitori WHERE id = $id";
        dbExec($query);
        info("cancellato genitore id=$id");
    } else {
        info("richiesta di cancellazione genitore con id=$id ma id non valido");
    }
}
