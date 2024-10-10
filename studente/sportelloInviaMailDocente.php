<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// // contiene ID dello sportello a cui lo studente si è appena iscritto    
// // $sportello_id 

// $query = "SELECT
//         sportello.id as sportello_id,
//         sportello.data as sportello_data,
//         sportello.ora as sportello_ora,
//         sportello.numero_ore as sportello_numero_ore,
//         sportello.classe as sportello_classe,

//         docente.cognome AS docente_cognome,
//         docente.nome AS docente_nome,
//         docente.id AS docente_id,
//         docente.email AS docente_email,

//         materia.nome AS materia_nome,
//         materia.id AS materia_id

//     FROM
//         sportello
//     INNER JOIN docente
//     ON sportello.docente_id = docente.id
//     INNER JOIN materia
//     ON sportello.materia_id = materia.id
//     WHERE sportello.id = '$sportello_id';";

// $sportello = dbGetFirst($query);

$mailbody =  "<html><body>Buongiorno ".$docente_nome." ".$docente_cognome.",<br> ricevi questa mail perchè lo sportello di ".$materia." previsto per il giorno " . $data . " alle ore " . $ora . " ha registrato il primo iscritto.<br>";
$mailbody .= "Lo/a studente/ssa iscritto è ".$studente_cognome." ".$studente_nome." della classe ".$studente_classe." ed ha prenotato indicato il seguente argomento: " . $argomento;
$mailbody .= "<br>";
$mailbody .= "mail generata automaticamente da <b>GestOre</b><br></body></html>";
$headers  = 'MIME-Version: 1.0' . "\r\n";
$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
$headers .= "From: " . $sender . "\r\n"."X-Mailer: php";
info("mail inviata al docente");
info("TESTO: ".$mailbody);
$sender = $__settings->local->emailNoReplyFrom;
$mailsubject = 'GestOre - Prenotazione sportello ' . $materia;
mail($docente_email, $mailsubject, $mailbody ,  $headers, additional_params: "-f$sender")
?>