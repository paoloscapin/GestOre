<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST['programma_id']) && isset($_POST['programma_id']) != "") {
	$programma_id = $_POST['programma_id'];

    $query = "SELECT
            programma_minimi.id as programma_id,
            programma_minimi.anno as programma_anno,
            programma_minimi.id_materia as programma_idmateria,
            programma_minimi.id_indirizzo as programma_idindirizzo,
            programma_minimi.updated as programma_updated

        FROM
            programma_minimi
        WHERE programma_minimi.id = '$programma_id'";

    $programma = dbGetFirst($query);

    $struct_json = json_encode($programma);
   echo json_encode($programma);
}
?>