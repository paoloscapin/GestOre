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
    require_once 'fuisDocentiCalcolaDocente.php';
    
    $docente_id = $_POST['docente_id'];
    calcolaFuisDocente($docente_id);
}
?>
