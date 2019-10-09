<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST['gruppo_id'])) {

    $gruppo_id = $_POST["gruppo_id"];

    $data = array();

    $query = "SELECT docente_id FROM gruppo_partecipante WHERE gruppo_id = $gruppo_id;";
    foreach(dbGetAll($query) as $row) {
        $data[] = $row['docente_id'];
        debug('id='.$row['docente_id']);
    }
    echo json_encode($data);
}
?>
