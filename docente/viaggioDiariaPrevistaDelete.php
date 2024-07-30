<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST['id']) && isset($_POST['id']) != "") {

	$docente_id = $_POST['docente_id'];
	$id = $_POST['id'];

	dbExec("DELETE FROM viaggio_diaria_prevista_commento WHERE viaggio_diaria_prevista_id = '$id'");
	dbExec("DELETE FROM viaggio_diaria_prevista WHERE id = '$id'");
	info("cancellata viaggio_diaria_prevista id=$id");
}
?>