<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST)) {
	$docente_id = $_POST['docente_id'];
	$template_id = $_POST['template_id'];
	$uuid = $_POST['uuid'];

	$listaCampi = json_decode($_POST['listaCampi']);
	$listaCampiId = json_decode($_POST['listaCampiId']);
	$listaValori = json_decode($_POST['listaValori']);

	dbExec("INSERT INTO modulistica_richiesta (uuid, modulistica_template_id, docente_id, anno_scolastico_id) VALUES('$uuid', $template_id, $docente_id, $__anno_scolastico_corrente_id);");
	$richiestaId = dblastId();

	for ($i = 0; $i < count($listaCampiId); $i++) {
		$campoId = $listaCampiId[$i];
		$campo = $listaCampi[$i];
		$valore = escapeString($listaValori[$i]);

		dbExec("INSERT INTO modulistica_richiesta_campo (valore, modulistica_template_campo_id, modulistica_richiesta_id) VALUES('$valore', $campoId, $richiestaId);");
	}

	info("aggiunto modulistica_richiesta id=$richiestaId template_id=$template_id docente_id=$docente_id");

	echo $richiestaId;
}
?>