<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST['carenza_id']) && isset($_POST['carenza_id']) != "") {
	$carenza_id = $_POST['carenza_id'];

    $query = "SELECT
            carenze.id as carenza_id,
            carenze.id_studente as carenza_id_studente,
            carenze.id_materia as carenza_id_materia,
            carenze.id_classe as carenza_id_classe,
            carenze.stato as carenza_stato
        FROM
            carenze
        WHERE carenze.id = '$carenza_id'";

    $carenza = dbGetFirst($query);

    $struct_json = json_encode($carenza);
   echo json_encode($carenza);
}
?>