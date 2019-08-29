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

	$adesione_id = $_POST['adesione_id'];
	$bonus_id = $_POST['bonus_id'];

	// se adesione id non viene passato significa che devo inserire una nuova adesione con quel bonus id
	if ($adesione_id < 0) {
	    $query = "INSERT INTO `bonus_docente`(`approvato`, `docente_id`, `anno_scolastico_id`, `bonus_id`) VALUES (null, $__docente_id, $__anno_scolastico_corrente_id, $bonus_id);";
	    dbExec($query);

	    //  devo potere tornare l'id che abbiamo generato
	    echo dblastId();
	} else {
	    // altrimenti devo cancellarla
	    $query = "DELETE FROM `bonus_docente` WHERE id = $adesione_id;";
	    dbExec($query);
	}
}
?>