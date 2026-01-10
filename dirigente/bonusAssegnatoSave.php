<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('dirigente');

$tableName = "bonus_assegnato";

if (isset($_POST)) {

	$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
	$commento = $_POST['commento'];
	$importo = $_POST['importo'];
	$docente_id = isset($_POST['docente_id']) ? intval($_POST['docente_id']) : 0;

	// anno selezionato (se passato), altrimenti anno corrente
	$anno_scolastico_id = isset($_POST['anno_scolastico_id'])
		? intval($_POST['anno_scolastico_id'])
		: $__anno_scolastico_corrente_id;

	if ($id > 0) {
		$query = "UPDATE $tableName
				  SET commento = '$commento', importo = '$importo'
				  WHERE id = '$id'";
		dbExec($query);
		info("aggiornato $tableName id=$id commento=$commento importo=$importo");
	} else {
		$query = "INSERT INTO $tableName(commento, importo, docente_id, anno_scolastico_id)
				  VALUES('$commento', '$importo', $docente_id, $anno_scolastico_id)";
		dbExec($query);
		$id = dblastId();
		info("aggiunto $tableName id=$id commento=$commento importo=$importo docente_id=$docente_id anno_scolastico_id=$anno_scolastico_id");
	}
}
?>
