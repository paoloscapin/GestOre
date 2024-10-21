<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST)) {
    $table = $_POST['table'];
	$id = $_POST['id'];
	$nome = $_POST['nome'];
	$valore = escapePost('valore');

    if ($id > 0) {
        dbExec("UPDATE $table SET $nome = '$valore' WHERE id = '$id'");
        info("aggiornato $table id=$id nome=$nome valore=$valore");
    }
}
?>
