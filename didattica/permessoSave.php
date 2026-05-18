<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/permessi_uscita_lib.php';
ruoloRichiesto('segreteria-didattica', 'dirigente');

if (!empty($_POST)) {
    $id = intval($_POST['id'] ?? 0);
    $data = escapePost('data');
    $ora_uscita = escapePost('ora_uscita');
    $motivo = escapePost('motivo');
    $ora_rientro = escapePost('ora_rientro');
    $rientro = intval($_POST['rientro'] ?? 0);
    $id_studente = intval($_POST['id_studente'] ?? 0);
    $stato = intval($_POST['stato'] ?? 1);
    $note_segreteria = escapePost('note_segreteria');

    if ($id > 0) {
        $old = dbGetFirst("SELECT stato FROM permessi_uscita WHERE id = " . dbI($id) . " LIMIT 1");
        $query = "
            UPDATE permessi_uscita
            SET
                data = " . dbQ($data) . ",
                ora_uscita = " . dbQ($ora_uscita) . ",
                ora_rientro = " . dbQ($ora_rientro) . ",
                motivo = " . dbQ($motivo) . ",
                rientro = " . dbI($rientro) . ",
                stato = " . dbI($stato) . ",
                note_segreteria = " . dbQ($note_segreteria) . "
            WHERE id = " . dbI($id) . "
            LIMIT 1";
        dbExec($query);

        if (!$old || intval($old['stato'] ?? 0) !== $stato) {
            if (in_array($stato, [2, 3, 4], true)) {
                permessiUscitaFreezePresence($id);
            }
        }
        if ($stato === 2) {
            permessiUscitaMarkConfirmedForSync($id);
        }
        if (!$old || intval($old['stato'] ?? 0) !== $stato) {
            permessiUscitaSendParentMail($id, 'stato');
        }
        info("aggiornato permesso id=$id");
    } else {
        $query = "
            INSERT INTO permessi_uscita
                (id_genitore, id_studente, data, ora_uscita, ora_rientro, rientro, motivo, stato, note_segreteria)
            VALUES
                (" . dbI($__genitore_id ?? 0) . ", " . dbI($id_studente) . ", " . dbQ($data) . ", " . dbQ($ora_uscita) . ", " . dbQ($ora_rientro) . ", " . dbI($rientro) . ", " . dbQ($motivo) . ", " . dbI($stato) . ", " . dbQ($note_segreteria) . ")";
        dbExec($query);
        $id = dbLastId();
        if ($stato === 2) {
            permessiUscitaMarkConfirmedForSync((int)$id);
        }
        permessiUscitaSendParentMail((int)$id, 'creazione');
        info("inserito nuovo permesso id=$id");
    }
}
