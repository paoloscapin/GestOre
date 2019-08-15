<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

if(isset($_POST['protocollo'])) {
	$protocollo = escapePost('protocollo');
	$tipo_viaggio = $_POST['tipo_viaggio'];
	$data_partenza = $_POST['data_partenza'];
	$data_rientro = $_POST['data_rientro'];
	$data_nomina = $_POST['data_nomina'];
	$docente_incaricato_id = $_POST['docente_incaricato_id'];
	$destinazione = escapePost('destinazione');
	$classe = escapePost('classe');
	$note = escapePost('note');
	$ora_partenza = $_POST['ora_partenza'];
	$ora_rientro = $_POST['ora_rientro'];

	$query = "INSERT INTO viaggio(protocollo, tipo_viaggio, data_nomina, data_partenza, data_rientro, docente_id, destinazione, classe, note, ora_partenza, ora_rientro, anno_scolastico_id) VALUES('$protocollo', '$tipo_viaggio', '$data_nomina', '$data_partenza', '$data_rientro', '$docente_incaricato_id', '$destinazione', '$classe', '$note', '$ora_partenza', '$ora_rientro', '$__anno_scolastico_corrente_id')";
	debug($query);
	dbExec($query);
}
?>