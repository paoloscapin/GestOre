<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('docente','segreteria-didattica','dirigente');

function salvaMetodologie($piano_di_lavoro_id, $metodologie_id_list_str) {
    // per prima cosa rimuove tutte quelle che erano presenti
    dbExec("DELETE FROM piano_di_lavoro_usa_metodologia WHERE piano_di_lavoro_id = $piano_di_lavoro_id ;");

    // a questo punto le inserisce una alla volta (convertite in integer)
    foreach($metodologie_id_list_str as $metodologia_id_str) {
        $metodologia_id = (int)$metodologia_id_str;
        dbExec("INSERT INTO piano_di_lavoro_usa_metodologia(piano_di_lavoro_id, piano_di_lavoro_metodologia_id) VALUES($piano_di_lavoro_id, $metodologia_id) ;");
    }
    info("aggiornate metodologie (". json_encode($metodologie_id_list_str) .") per il piano_di_lavoro id=$piano_di_lavoro_id");    
}

function salvaMateriali($piano_di_lavoro_id, $materiali_id_list_str) {
    // per prima cosa rimuove tutti quelli che erano presenti
    dbExec("DELETE FROM piano_di_lavoro_usa_materiale WHERE piano_di_lavoro_id = $piano_di_lavoro_id ;");

    // a questo punto li inserisce una alla volta (convertite in integer)
    foreach($materiali_id_list_str as $materiale_id_str) {
        $materiale_id = (int)$materiale_id_str;
        dbExec("INSERT INTO piano_di_lavoro_usa_materiale(piano_di_lavoro_id, piano_di_lavoro_materiale_id) VALUES($piano_di_lavoro_id, $materiale_id) ;");
    }
    info("aggiornati materiali (". json_encode($materiali_id_list_str) .") per il piano_di_lavoro id=$piano_di_lavoro_id");    
}

function salvaTic($piano_di_lavoro_id, $tic_id_list_str) {
    // per prima cosa rimuove tutte quelle che erano presenti
    dbExec("DELETE FROM piano_di_lavoro_usa_tic WHERE piano_di_lavoro_id = $piano_di_lavoro_id ;");

    // a questo punto le inserisce una alla volta (convertite in integer)
    foreach($tic_id_list_str as $tic_id_str) {
        $tic_id = (int)$tic_id_str;
        dbExec("INSERT INTO piano_di_lavoro_usa_tic(piano_di_lavoro_id, piano_di_lavoro_tic_id) VALUES($piano_di_lavoro_id, $tic_id) ;");
    }
    info("aggiornate tic (". json_encode($tic_id_list_str) .") per il piano_di_lavoro id=$piano_di_lavoro_id");    
}

if(isset($_POST)) {
	$id = $_POST['id'];
    $docente_id = $_POST['docente_id'];
	$materia_id = $_POST['materia_id'];
	$anno_scolastico_id = $_POST['anno_scolastico_id'];
	$indirizzo_id = $_POST['indirizzo_id'];
	$classe = $_POST['classe'];
	$sezione = $_POST['sezione'];
	$template = $_POST['template'];
	$obiettivi_minimi = $_POST['obiettivi_minimi'];
	$clil = $_POST['clil'];
	$stato = $_POST['stato'];
    $competenze = escapePost('competenze');
    $note_aggiuntive = escapePost('note_aggiuntive');
    $metodologie_id_list_str = $_POST['metodologie'];
    $materiali_id_list_str = $_POST['materiali'];
    $tic_id_list_str = $_POST['tic'];
    $carenza = $_POST['carenza'];
    $studente_id = $_POST['studente_id'];

    // calcola il nome classe: per prima cosa serve il nome breve dell'indirizzo
    $nome_indirizzo = dbGetValue("SELECT nome_breve FROM indirizzo WHERE id = $indirizzo_id ;");
    // costruisce il nome della classe
    $nome_classe = $classe . $nome_indirizzo . $sezione;
    // debug("nome classe=$nome_classe");

    // se non e' una carenza non c'e' lo studente
    if ($carenza == 0) {
        $studente_id = null;
    } else {
        $obiettivi_minimi = 0;
    }
    
    if ($id > 0) {
        $query = "UPDATE piano_di_lavoro SET docente_id = $docente_id, materia_id = $materia_id, anno_scolastico_id = $anno_scolastico_id, indirizzo_id = $indirizzo_id, classe = $classe, sezione = '$sezione', nome_classe = '$nome_classe', template = $template, obiettivi_minimi = $obiettivi_minimi, clil = $clil, stato = '$stato', competenze = '$competenze' , note_aggiuntive = '$note_aggiuntive' ".($studente_id != null? ", studente_id = $studente_id" : "")." WHERE id = '$id' ;";
        dbExec($query);
        info("aggiornato piano_di_lavoro id=$id");

        // devo decidere se aggiornare le metodologie: ricupero la lista originale dal database
        $metodologie_original_id_list = dbGetAllValues("SELECT piano_di_lavoro_metodologia_id FROM piano_di_lavoro_usa_metodologia WHERE piano_di_lavoro_id = $id;");

        // se la nuova lista risulta diversa, la salva
        if ($metodologie_id_list_str != $metodologie_original_id_list) {
            salvaMetodologie($id, $metodologie_id_list_str);
        }

        // devo decidere se aggiornare i materiali: ricupero la lista originale dal database
        $materiali_original_id_list = dbGetAllValues("SELECT piano_di_lavoro_materiale_id FROM piano_di_lavoro_usa_materiale WHERE piano_di_lavoro_id = $id;");

        // se la nuova lista risulta diversa, la salva
        if ($materiali_id_list_str != $materiali_original_id_list) {
            salvaMateriali($id, $materiali_id_list_str);
        }

        // devo decidere se aggiornare i tic: ricupero la lista originale dal database
        $tic_original_id_list = dbGetAllValues("SELECT piano_di_lavoro_tic_id FROM piano_di_lavoro_usa_tic WHERE piano_di_lavoro_id = $id;");

        // se la nuova lista risulta diversa, la salva
        if ($tic_id_list_str != $tic_original_id_list) {
            salvaTic($id, $tic_id_list_str);
        }
    } else {
        $query = "INSERT INTO piano_di_lavoro(docente_id, materia_id, anno_scolastico_id, indirizzo_id, classe, sezione, nome_classe, template, obiettivi_minimi, clil, stato, competenze, note_aggiuntive, carenza".($studente_id != null? ", studente_id" : "").") VALUES($docente_id, $materia_id, $anno_scolastico_id, $indirizzo_id, '$classe', '$sezione', '$nome_classe', $template, $obiettivi_minimi, $clil, '$stato', '$competenze', '$note_aggiuntive', $carenza".($studente_id != null? ", $studente_id" : "").")";
        dbExec($query);
        $id = dblastId();
        info("aggiunto piano_di_lavoro id=$id");

        // se inserisco un nuovo piano, salvo comunque le metodologie (se non vuote)
        if (! empty($metodologie_id_list_str)) {
            salvaMetodologie($id, $metodologie_id_list_str);
        }

        // se inserisco un nuovo piano, salvo comunque i materiali (se non vuoti)
        if (! empty($materiali_id_list_str)) {
            salvaMateriali($id, $materiali_id_list_str);
        }

        // se inserisco un nuovo piano, salvo comunque i tic (se non vuoti)
        if (! empty($tic_id_list_str)) {
            salvaTic($id, $tic_id_list_str);
        }
    }
}
?>