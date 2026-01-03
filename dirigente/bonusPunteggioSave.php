<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

if (isset($_POST)) {
    require_once '../common/connect.php';

    $bonus_docente_id = isset($_POST['bonus_docente_id']) ? intval($_POST['bonus_docente_id']) : 0;
    $punteggio = isset($_POST['punteggio']) ? intval($_POST['punteggio']) : 0;

    // anno selezionato (se passato), altrimenti anno corrente
    $anno_scolastico_id = isset($_POST['anno_scolastico_id'])
        ? intval($_POST['anno_scolastico_id'])
        : $__anno_scolastico_corrente_id;

    // Update details (vincolato all'anno)
    $query = "UPDATE bonus_docente
              SET approvato = $punteggio, ultimo_controllo = now()
              WHERE id = $bonus_docente_id
              AND anno_scolastico_id = $anno_scolastico_id";
    dbExec($query);

    info("assegnato punteggio $punteggio bonus_docente_id=$bonus_docente_id anno_scolastico_id=$anno_scolastico_id");
}
?>
