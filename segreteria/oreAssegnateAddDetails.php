<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST)) {
	$ore_previste_attivita_id = $_POST['ore_previste_attivita_id'];
	$dettaglio = escapePost('dettaglio');
	$ore = $_POST['ore'];
	$ore_previste_tipo_attivita_id = $_POST['ore_previste_tipo_attivita_id'];
	$docente_id = $_POST['docente_id'];

	// per prima cosa inserisce l'attivita
	$query = "INSERT INTO ore_previste_attivita(dettaglio, ore, docente_id, anno_scolastico_id, ore_previste_tipo_attivita_id) VALUES('$dettaglio', '$ore', $docente_id, $__anno_scolastico_corrente_id, $ore_previste_tipo_attivita_id)";
	dbExec($query);
	info("assegnate ore=$ore docente_id=$docente_id attivita_id=$ore_previste_attivita_id dettaglio=$dettaglio");

	// aggiunge anche le ore alle ore previste nel posto giusto (se ci riesce...)
	// cerca il tipo di attivita
	$query = "SELECT * FROM `ore_previste_tipo_attivita` WHERE id = $ore_previste_tipo_attivita_id";
	$item = dbGetFirst($query);
	$categoria = $item['categoria'];
	$da_rendicontare = $item['da_rendicontare'];
	// se non sono da rendicontare, le aggiunge direttamente anche nella tabella delle ore fatte
	if (!$da_rendicontare) {
		// TODO: inserire
	}
}
?>