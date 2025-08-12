<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

if(isset($_POST['id']) && isset($_POST['id']) != "") {
	require_once '../common/checkSession.php';
	require_once '../common/connect.php';

	$id = $_POST['id'];
	$materia = $_POST['materia'];
	$studente = $_POST['studente'];

	// il docente puo' cancellare le carenze (sarebbe bello controllare che siano le sue...)
	if (haRuolo('docente')) {
		
		// cancella i riferimenti ai materiali, metodologie e tic
		dbExec("DELETE FROM piano_di_lavoro_usa_materiale WHERE piano_di_lavoro_id = $id; ");
		dbExec("DELETE FROM piano_di_lavoro_usa_metodologia WHERE piano_di_lavoro_id = $id; ");
		dbExec("DELETE FROM piano_di_lavoro_usa_tic WHERE piano_di_lavoro_id = $id; ");

		// il contenuto (i moduli)
		dbExec("DELETE FROM piano_di_lavoro_contenuto WHERE piano_di_lavoro_id = $id; ");

		// e la carenza stessa
		dbExec("DELETE FROM piano_di_lavoro WHERE id = $id; ");

		info("rimossa carenza studente=$studente materia=$materia id=$id");
	} else {

		// per i docenti cambia lo stato in annullato
		dbExec("UPDATE piano_di_lavoro SET stato = 'annullato' WHERE id = '$id'; ");
        info("aggiornato carenza id=$id stato=annullato");
	}
}
?>