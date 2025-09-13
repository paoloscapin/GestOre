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
    $id = $_POST['id'];
    $data = escapePost('data');
    $ora_uscita = escapePost('ora_uscita');
    $motivo = escapePost('motivo');
    $ora_rientro = escapePost('ora_rientro');
    $rientro = escapePost('rientro');
    $id_studente = escapePost('id_studente');
    $stato = escapePost('stato');
    $note_segreteria = escapePost('note_segreteria');

    if ($id > 0) {
        // 🔄 aggiorno un permesso esistente
        $query = "
            UPDATE permessi_uscita 
            SET 
                data = '$data',
                ora_uscita = '$ora_uscita',
                ora_rientro = '$ora_rientro',
                motivo = '$motivo',
                rientro = '$rientro',
                stato = '$stato',
                note_segreteria = '$note_segreteria'
            WHERE id = '$id'";
        dbExec($query);
        info("aggiornato permesso id=$id");
    } else {
        // ➕ inserisco un nuovo permesso
        // lato genitore: stato = 0 (Richiesto)
        $query = "
            INSERT INTO permessi_uscita 
                (id_genitore, id_studente, data, ora_uscita, ora_rientro, rientro, motivo, stato, note_segreteria) 
            VALUES 
                ('$__genitore_id', '$id_studente', '$data', '$ora_uscita', '$ora_rientro', '$rientro', '$motivo', '0', '')";
        dbExec($query);
        $id = dbLastId();
        info("inserito nuovo permesso id=$id");
    }
}
