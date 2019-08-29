<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST['id']) && isset($_POST['id']) != "") {
	$corso_di_recupero_id = $_POST['id'];
	$query = "DELETE FROM corso_di_recupero WHERE id = '$corso_di_recupero_id'";
	dbExec($query);
	info("rimosso corso_di_recupero id=$corso_di_recupero_id");
}
?>