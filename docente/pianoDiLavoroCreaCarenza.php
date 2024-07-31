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

	$original_piano_di_lavoro_id = $_POST['id'];

    // legge il contenuto del record corrente e lo duplica
    $query = "INSERT INTO piano_di_lavoro(docente_id, anno_scolastico_id, carenza, template, materia_id, indirizzo_id, classe, sezione, nome_classe, competenze, note_aggiuntive)
            SELECT '$__docente_id', '$__anno_scolastico_corrente_id', 1, 0, materia_id, indirizzo_id, classe, sezione, nome_classe, competenze, note_aggiuntive
            FROM piano_di_lavoro WHERE id = $original_piano_di_lavoro_id; ";
    dbExec($query);

    $piano_di_lavoro_id = dblastId();

    // aggiorna tutte le metodologie, i materiali e le tic riferite
    dbExec("INSERT INTO piano_di_lavoro_usa_metodologia(piano_di_lavoro_id, piano_di_lavoro_metodologia_id) SELECT '$piano_di_lavoro_id', piano_di_lavoro_metodologia_id FROM piano_di_lavoro_usa_metodologia WHERE piano_di_lavoro_id = $original_piano_di_lavoro_id;");
    dbExec("INSERT INTO piano_di_lavoro_usa_materiale(piano_di_lavoro_id, piano_di_lavoro_materiale_id) SELECT '$piano_di_lavoro_id', piano_di_lavoro_materiale_id FROM piano_di_lavoro_usa_materiale WHERE piano_di_lavoro_id = $original_piano_di_lavoro_id;");
    dbExec("INSERT INTO piano_di_lavoro_usa_tic(piano_di_lavoro_id, piano_di_lavoro_tic_id) SELECT '$piano_di_lavoro_id', piano_di_lavoro_tic_id FROM piano_di_lavoro_usa_tic WHERE piano_di_lavoro_id = $original_piano_di_lavoro_id;");

    // ora deve duplicare tutti i moduli
    dbExec("INSERT INTO piano_di_lavoro_contenuto(titolo, testo, posizione, piano_di_lavoro_id) SELECT titolo, testo, posizione, '$piano_di_lavoro_id' FROM piano_di_lavoro_contenuto WHERE piano_di_lavoro_id = $original_piano_di_lavoro_id;");

    info("creata carenza original_piano_di_lavoro_id=$original_piano_di_lavoro_id piano_di_lavoro_id=$piano_di_lavoro_id");    

    // ritorna l'id della nuova copia del piano di lavoro
    echo json_encode($piano_di_lavoro_id);
}
?>