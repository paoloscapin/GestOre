<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST)) {
	$lezione_corso_di_recupero_id = $_POST['lezione_corso_di_recupero_id'];

	$query = "UPDATE lezione_corso_di_recupero SET firmato = NOT FIRMATO WHERE id = '$lezione_corso_di_recupero_id'";
	dbExec($query);
	info("firmato lezione_corso_di_recupero id=$id");
}
?>