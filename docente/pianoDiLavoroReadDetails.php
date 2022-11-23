<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST['piano_di_lavoro_id']) && isset($_POST['piano_di_lavoro_id']) != "") {
	$piano_di_lavoro_id = $_POST['piano_di_lavoro_id'];

    $piano_di_lavoro = dbGetFirst("SELECT * FROM piano_di_lavoro WHERE id = $piano_di_lavoro_id;");

	echo json_encode($piano_di_lavoro);
}
?>