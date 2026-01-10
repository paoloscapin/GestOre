<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
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