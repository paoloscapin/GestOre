<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/importi_load.php';

function getFuisAssegnatoImporto($localDocenteId) {
	global $__anno_scolastico_corrente_id;
	$assegnato = dbGetValue("SELECT COALESCE(SUM(importo), 0) FROM fuis_assegnato WHERE docente_id = $localDocenteId AND anno_scolastico_id = $__anno_scolastico_corrente_id;");
	return $assegnato;
}

// se viene chiamato con un post, allora ritonna il valore con echo
if(isset($_POST['docente_id']) && isset($_POST['docente_id']) != "") {
    $docente_id = $_POST['docente_id'];
    $fuisAssegnato = getFuisAssegnatoImporto($docente_id);
    echo json_encode($fuisAssegnato);
}
?>
