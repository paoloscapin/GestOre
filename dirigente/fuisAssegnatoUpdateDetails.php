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

	$fuis_assegnato_id = $_POST['fuis_assegnato_id'];
	$importo = $_POST['importo'];
	$fuis_assegnato_tipo_id = $_POST['fuis_assegnato_tipo_id'];
	$docente_id = $_POST['docente_id'];

	$query = '';
	if ($fuis_assegnato_id > 0) {
	    $query = "UPDATE fuis_assegnato SET importo = '$importo', docente_id = '$docente_id' WHERE id = '$fuis_assegnato_id'";
	} else {
	    $query = "INSERT INTO fuis_assegnato (importo, docente_id, fuis_assegnato_tipo_id, anno_scolastico_id) VALUES('$importo', '$docente_id', '$fuis_assegnato_tipo_id', '$__anno_scolastico_corrente_id')";
	}
	dbExec($query);
}
?>