<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// contiene ID dello sportello a cui lo studente si è appena iscritto    
//$sportello_id 

$query = "SELECT
        sportello.id as sportello_id,
        sportello.data as sportello_data,
        sportello.ora as sportello_ora,
        sportello.numero_ore as sportello_numero_ore,
        sportello.classe as sportello_classe,

        docente.cognome AS docente_cognome,
        docente.nome AS docente_nome,
        docente.id AS docente_id,
        docente.email AS docente_email,

        materia.nome AS materia_nome,
        materia.id AS materia_id

    FROM
        sportello
    INNER JOIN docente
    ON sportello.docente_id = docente.id
    INNER JOIN materia
    ON sportello.materia_id = materia.id
    WHERE sportello.id = '$sportello_id';";

$sportello = dbGetFirst($query);

$mailbody = "Lo/a studente/ssa ".$__studente_cognome." ".$__studente_nome." ha prenotato lo sportello di ".$sportello['materia_nome']." previsto per il giorno " . $sportello['sportello_data'] . " alle ore " . $sportello['sportello_ora'] . " indicando il seguente argomento: " . $argomento;

$sender = $__settings->local->emailNoReplyFrom;

mail($sportello['docente_email'], 'GestOre - Prenotazione sportello', $mailbody ,  additional_params: "-f$sender")
?>