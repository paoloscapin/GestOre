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

    // attenzione: può arrivare "true"/"false"
    $approvato_raw = isset($_POST['approvato']) ? $_POST['approvato'] : 0;
    $approvato = ($approvato_raw === true || $approvato_raw === 'true' || $approvato_raw === 1 || $approvato_raw === '1') ? 1 : 0;

    // anno selezionato (se passato), altrimenti anno corrente
    $anno_scolastico_id = isset($_POST['anno_scolastico_id'])
        ? intval($_POST['anno_scolastico_id'])
        : $__anno_scolastico_corrente_id;

    $query = "UPDATE bonus_docente
              SET approvato = $approvato, ultimo_controllo = now()
              WHERE id = $bonus_docente_id
              AND anno_scolastico_id = $anno_scolastico_id";
    dbExec($query);
}
?>
