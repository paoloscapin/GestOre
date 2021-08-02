<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('admin');

// controlla che entry richiesta esista nella tabella specificata, altrimenti la crea
function checkTableExists($table, $docente_id, $anno_id) {
	$verifyQuery = "SELECT * FROM $table WHERE anno_scolastico_id = $anno_id AND docente_id = $docente_id;";
	$result = dbGetFirst($verifyQuery);

	// se non ci sono risultati, inserisce la nuova riga in tabella
	if ($result === null) {
        $createQuery = "INSERT INTO $table (docente_id, anno_scolastico_id) VALUES ($docente_id, $anno_id);";
        info("Query: " . $createQuery);
		dbExec($createQuery);
		debug("inserito in tabella $table nuovo docente id=$docente_id per anno=$anno_id");
	}
}

// controlla che entry richiesta esista nella tabella specificata, altrimenti la crea e copia i valori dall'anno precedente
function duplicaPerNuovoAnno($table, $docente_id, $anno_id, $anno_precedente_id, $lista_campi) {
	$verifyQuery = "SELECT * FROM $table WHERE anno_scolastico_id = $anno_id AND docente_id = $docente_id;";
	$result = dbGetFirst($verifyQuery);

	// se non ci sono risultati, inserisce la nuova riga in tabella
	if ($result === null) {
        $duplicateQuery = "INSERT INTO $table ($lista_campi,docente_id,anno_scolastico_id) SELECT $lista_campi,$docente_id,$anno_id FROM $table WHERE docente_id = $docente_id AND anno_scolastico_id = $anno_precedente_id";
		dbExec($duplicateQuery);
		debug("inserito in tabella $table nuova entry id=$docente_id per anno=$anno_id");
	}
}

if(isset($_POST)) {
	$anno_scolastico_corrente_id = $_POST['anno_scolastico_corrente_id'];
	$anno_scolastico_corrente_anno = $_POST['anno_scolastico_corrente_anno'];
	$anno_scolastico_nuovo_id = $_POST['anno_scolastico_nuovo_id'];
	$anno_scolastico_nuovo_anno = $_POST['anno_scolastico_nuovo_anno'];

    // aggiorna l'anno scolastico
    $query = "UPDATE anno_scolastico_corrente SET anno = '$anno_scolastico_nuovo_anno', anno_scolastico_id = $anno_scolastico_nuovo_id, anno_scorso_id = $anno_scolastico_corrente_id";
    dbExec($query);

    // ruota i log (il messaggio viene ripetuto sul nuovo log file)
    info("aggiornato anno scolastico: nuovo=$anno_scolastico_nuovo_anno (id=$anno_scolastico_nuovo_id) precedente=$anno_scolastico_corrente_anno (id=$anno_scolastico_corrente_id)");
    rotateLog();
    info("aggiornato anno scolastico: nuovo=$anno_scolastico_nuovo_anno (id=$anno_scolastico_nuovo_id) precedente=$anno_scolastico_corrente_anno (id=$anno_scolastico_corrente_id)");

    // per tutti i docenti attivi aggiorna le tabelle copiandole dall'anno precedente
    $query = "SELECT * FROM docente WHERE docente.attivo = true ORDER BY docente.cognome, docente.nome ASC";
    foreach(dbGetAll($query) as $docente) {
        $docente_id = $docente['id'];
        $docenteCognome = $docente['cognome'];
        $docenteNome = $docente['nome'];

        // ore previste ed ore fatte devono essere solo controllate
        checkTableExists('ore_previste', $docente_id, $anno_scolastico_nuovo_id);
        checkTableExists('ore_fatte', $docente_id, $anno_scolastico_nuovo_id);

        // profilo docente ed ore dovute vanno copiate dall'anno in corso
        $lista_campi_profilo_docente = 'classe_di_concorso,tipo_di_contratto,giorni_di_servizio,ore_di_cattedra,ore_eccedenti,note,ore_dovute_70_con_studenti,ore_dovute_70_funzionali,ore_dovute_40,ore_dovute_totale,ore_dovute_supplenze,ore_dovute_aggiornamento,ore_dovute_totale_con_studenti,ore_dovute_totale_funzionali';
        duplicaPerNuovoAnno('profilo_docente', $docente_id, $anno_scolastico_nuovo_id, $anno_scolastico_corrente_id, $lista_campi_profilo_docente);
        $lista_campi_ore_dovute = 'ore_80_collegi_docenti,ore_80_udienze_generali,ore_80_dipartimenti,ore_80_aggiornamento_facoltativo,ore_80_consigli_di_classe,ore_40_sostituzioni_di_ufficio,ore_40_con_studenti,ore_40_aggiornamento,ore_70_funzionali,ore_70_con_studenti,ore_80_totale,ore_40_totale,ore_70_totale,note';
        duplicaPerNuovoAnno('ore_dovute', $docente_id, $anno_scolastico_nuovo_id, $anno_scolastico_corrente_id, $lista_campi_ore_dovute);

        info("aggiornate le tabelle del docente $docenteCognome $docenteNome id= $docente_id");
    }

    // aggiusta l'anno scolastico anche nella sessione
    $session->set ( 'anno_scolastico_corrente_id', $anno_scolastico_nuovo_id);
    $session->set ( 'anno_scolastico_corrente_anno', $anno_scolastico_nuovo_anno);
    $session->set ( 'anno_scolastico_scorso_id', $anno_scolastico_corrente_id);

    $__anno_scolastico_corrente_id = $session->get ( 'anno_scolastico_corrente_id' );
    $__anno_scolastico_corrente_anno = $session->get ( 'anno_scolastico_corrente_anno' );
    $__anno_scolastico_scorso_id = $session->get ( 'anno_scolastico_scorso_id' );
}
?>
