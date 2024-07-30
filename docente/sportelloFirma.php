<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('docente','segreteria-docenti','dirigente');

$tableName = "sportello";

if(isset($_POST)) {
	$id = $_POST['id'];
	if ($id > 0) {
		$query = "UPDATE sportello SET firmato = 1 WHERE id = '$id'";
		dbExec($query);
		info("firmato sportello id=$id");
	}
}
?>