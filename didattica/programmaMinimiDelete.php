<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

ruoloRichiesto('segreteria-didattica', 'dirigente');

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
	http_response_code(400);
	echo 'ID non valido';
	exit;
}

dbExec("DELETE FROM programma_minimi WHERE id=" . $id);
info("deleted programma_minimi id=$id");
echo 'ok';
?>
