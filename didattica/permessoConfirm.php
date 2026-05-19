<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/permessi_uscita_lib.php';
ruoloRichiesto('segreteria-didattica', 'dirigente', 'personale-ata');

if (!empty($_POST)) {
    $id = intval($_POST['id']);

    if ($id > 0) {
        $old = dbGetFirst("SELECT stato FROM permessi_uscita WHERE id = " . dbI($id) . " LIMIT 1");
        $query = "UPDATE permessi_uscita SET stato = '2' WHERE id = '$id'";
        dbExec($query);
        permessiUscitaMarkConfirmedForSync($id);
        if (!$old || intval($old['stato'] ?? 0) !== 2) {
            permessiUscitaSendParentMail($id, 'stato');
        }
        info("aggiornato permesso id=$id");
        echo "ok";
    } else {
        error("Parametri non validi (id=$id)");
        echo "errore";
    }
}

