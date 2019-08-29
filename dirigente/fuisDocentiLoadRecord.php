<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

if(isset($_POST)) {
    require_once '../common/checkSession.php';
    require_once '../common/connect.php';
    
    $docente_id = $_POST['docente_id'];

    ruoloRichiesto('dirigente');

$query = "SELECT * FROM `fuis_docente` WHERE fuis_docente.anno_scolastico_id = '$__anno_scolastico_corrente_id' AND fuis_docente.docente_id = $docente_id";
$fuis = dbGetFirst($query);
echo json_encode($fuis);
}
?>
