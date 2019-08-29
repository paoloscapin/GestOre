<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

if(isset($_POST)) {
    require_once '../common/checkSession.php';
    require_once '../common/header-common.php';
    require_once '../common/connect.php';
    
    // get values
    $docente_id = $_POST['docente_id'];
    
    // Update details
    $query = "UPDATE bonus_docente SET ultimo_controllo = now() WHERE docente_id = $docente_id AND anno_scolastico_id = $__anno_scolastico_corrente_id;";
    dbExec($query);
}
?>