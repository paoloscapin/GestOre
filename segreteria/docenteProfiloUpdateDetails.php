<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('segreteria-docenti','dirigente');

if(isset($_POST)) {
	// get values
	$profilo_id = $_POST['profilo_id'];
	$docente_id = $_POST['docente_id'];
	$docente_cognome_e_nome = escapePost('docente_cognome_e_nome');
	$ore_dovute_id = $_POST['ore_dovute_id'];
	$ore_previste_id = $_POST['ore_previste_id'];
	$tipo_di_contratto = $_POST['tipo_di_contratto'];
	$giorni_di_servizio = $_POST['giorni_di_servizio'];
	$ore_di_cattedra = $_POST['ore_di_cattedra'];
	$ore_eccedenti = $_POST['ore_eccedenti'];
	$ore_80_collegi_docenti = $_POST['ore_80_collegi_docenti'];
	$ore_80_udienze_generali = $_POST['ore_80_udienze_generali'];
	$ore_80_aggiornamento_facoltativo = $_POST['ore_80_aggiornamento_facoltativo'];
	$ore_80_dipartimenti_min = $_POST['ore_80_dipartimenti_min'];
	$ore_80_dipartimenti_max = $_POST['ore_80_dipartimenti_max'];
	$ore_80_consigli_di_classe = $_POST['ore_80_consigli_di_classe'];
	$ore_80_totale = $_POST['ore_80_totale'];
	$ore_40_sostituzioni_di_ufficio = $_POST['ore_40_sostituzioni_di_ufficio'];
	$ore_40_con_studenti = $_POST['ore_40_con_studenti'];
	$ore_40_aggiornamento = $_POST['ore_40_aggiornamento'];
	$ore_40_totale = $_POST['ore_40_totale'];
	$ore_70_funzionali = $_POST['ore_70_funzionali'];
	$ore_70_con_studenti = $_POST['ore_70_con_studenti'];
	$ore_70_totale = $_POST['ore_70_totale'];
	$note = escapePost('note');
	$ore_40_totale = 0 + round($ore_40_aggiornamento + ($ore_40_sostituzioni_di_ufficio * 50 / 60) + ($ore_40_con_studenti * 50 / 60));
	$ore_70_totale = 0 + round($ore_70_funzionali + $ore_70_con_studenti);

	$query = "	UPDATE profilo_docente SET
					classe_di_concorso = '$classe_di_concorso',
					tipo_di_contratto = '$tipo_di_contratto',
					giorni_di_servizio = '$giorni_di_servizio',
					ore_di_cattedra = '$ore_di_cattedra',
					ore_eccedenti = '$ore_eccedenti',
					note = '$note'
				WHERE id = '$profilo_id'";
	dbExec($query);

	$query = "	UPDATE ore_dovute SET
					ore_80_collegi_docenti = '$ore_80_collegi_docenti',
					ore_80_udienze_generali = '$ore_80_udienze_generali',
					ore_80_aggiornamento_facoltativo = '$ore_80_aggiornamento_facoltativo',
					ore_80_dipartimenti = '$ore_80_dipartimenti_max',
					ore_80_consigli_di_classe = '$ore_80_consigli_di_classe',
					ore_80_totale = '$ore_80_totale',
					ore_40_sostituzioni_di_ufficio = '$ore_40_sostituzioni_di_ufficio',
					ore_40_con_studenti = '$ore_40_con_studenti',
					ore_40_aggiornamento = '$ore_40_aggiornamento',
					ore_40_totale = '$ore_40_totale',
					ore_70_funzionali = '$ore_70_funzionali',
					ore_70_con_studenti = '$ore_70_con_studenti',
					ore_70_totale = '$ore_70_totale'
				WHERE id = '$ore_dovute_id'";
	dbExec($query);

	$query = "	UPDATE ore_previste SET
					ore_80_collegi_docenti = '$ore_80_collegi_docenti',
					ore_80_udienze_generali = '$ore_80_udienze_generali',
					ore_80_aggiornamento_facoltativo = '$ore_80_aggiornamento_facoltativo',
					ore_80_dipartimenti = '$ore_80_dipartimenti_max',
					ore_80_consigli_di_classe = '$ore_80_consigli_di_classe',
					ore_80_totale = '$ore_80_totale'
				WHERE id = '$ore_previste_id'";
	dbExec($query);

	// rimuovo eventuali vecchie sostituzioni: TODO: vecchia cosa che non dovrebbe servire nei prossimi anni
	$query = "DELETE FROM ore_previste_attivita WHERE dettaglio = 'Sostituzioni di ufficio' AND docente_id = $docente_id AND anno_scolastico_id = $__anno_scolastico_corrente_id";
	dbExec($query);
	info("aggiornato profilo per il docente $docente_cognome_e_nome");

/* non vengono inserite qui ma assegnate una per volta
	// restano da aggiornare le sostituzioni
	$sostituzioni_tipo_attivita_id = dbGetValue("SELECT id FROM ore_previste_tipo_attivita WHERE nome = 'sostituzioni'");
	$_POST['ore_previste_attivita_id'] = '';
	$_POST['dettaglio'] = 'Sostituzioni di ufficio';
	$_POST['ore'] = $ore_40_sostituzioni_di_ufficio;
	$_POST['ore_previste_tipo_attivita_id'] = $sostituzioni_tipo_attivita_id;
	$_POST['docente_id'] = $docente_id;
	include 'oreAssegnateAddDetails.php';*/
}
?>