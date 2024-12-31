<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST['id']) && isset($_POST['id']) != "") {
	// get docente ID
	$docente_id = $_POST['id'];

	// Get Docente Details
	$query = "SELECT docente.id AS local_docente_id, docente.*, profilo_docente.* FROM docente LEFT OUTER JOIN profilo_docente ON docente.id = profilo_docente.docente_id AND profilo_docente.anno_scolastico_id = $__anno_scolastico_corrente_id  WHERE docente.id = $docente_id";

	$docente = dbGetFirst($query);
	echo json_encode($docente);
}
?>