<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('docente','segreteria-didattica','dirigente');

if(isset($_POST)) {
	$id = $_POST['id'];
    $docente_id = $_POST['docente_id'];
	$materia_id = $_POST['materia_id'];
	$anno_scolastico_id = $_POST['anno_scolastico_id'];
	$indirizzo_id = $_POST['indirizzo_id'];
	$classe = $_POST['classe'];
	$sezione = $_POST['sezione'];
	$template = $_POST['template'];
	$stato = $_POST['stato'];
    $competenze = escapePost('competenze');

    if ($id > 0) {
        $query = "UPDATE piano_di_lavoro SET docente_id = $docente_id, materia_id = $materia_id, anno_scolastico_id = $anno_scolastico_id, indirizzo_id = $indirizzo_id, classe = $classe, sezione = '$sezione', template = $template , stato = '$stato', competenze = '$competenze' WHERE id = '$id'";
        dbExec($query);
        info("aggiornato piano_di_lavoro id=$id");
    } else {
        $query = "INSERT INTO piano_di_lavoro(docente_id, materia_id, anno_scolastico_id, indirizzo_id, classe, sezione, template, stato, competenze) VALUES($docente_id, $materia_id, $anno_scolastico_id, $indirizzo_id, '$classe', '$sezione', $template, '$stato', '$competenze')";
        dbExec($query);
        $id = dblastId();
        info("aggiunto piano_di_lavoro id=$id");    
    }
}
?>