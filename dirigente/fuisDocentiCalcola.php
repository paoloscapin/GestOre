<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('dirigente');

require_once 'fuisDocentiCalcolaDocente.php';

$query = "	SELECT docente.id AS local_docente_id, docente.* FROM docente WHERE docente.attivo = true  order by cognome,nome";
$resultArray = dbGetAll($query);
foreach($resultArray as $docente) {
    $localDocenteId = $docente['local_docente_id'];
    calcolaFuisDocente($localDocenteId);
}
?>
