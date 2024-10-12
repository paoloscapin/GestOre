<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */


 
require_once '../common/checkSession.php';
ruoloRichiesto('studente','segreteria-didattica','dirigente');


$full_mail_body = file_get_contents("template_mail_cancella_docente.html");

//
$full_mail_body = str_replace("{titolo}","ANNULLAMENTO SPORTELLO",$full_mail_body);
$full_mail_body = str_replace("{nome}",strtoupper($docente_cognome) . " " . strtoupper($docente_nome),$full_mail_body);
$full_mail_body = str_replace("{messaggio}","hai ricevuto questa mail perchè l'ultimo studente iscritto allo sportello si è iscritto. Quindi in mancanza di altre iscrizioni lo sportello è annullato",$full_mail_body);
$full_mail_body = str_replace("{data}",$data,$full_mail_body);
$full_mail_body = str_replace("{ora}",$ora,$full_mail_body);
$full_mail_body = str_replace("{docente}",strtoupper($docente_cognome . " " . $docente_nome),$full_mail_body);
$full_mail_body = str_replace("{materia}",$materia,$full_mail_body);
$full_mail_body = str_replace("{aula}",$luogo,$full_mail_body);
$full_mail_body = str_replace("{messaggio_finale}","Se dovesse iscriversi nuovamente uno studente sarai avvisato via mail.",$full_mail_body);

$sender = $__settings->local->emailNoReplyFrom;
$headers  = 'MIME-Version: 1.0' . "\r\n";
$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
$headers .= "From: " . $sender . "\r\n"."X-Mailer: php";
$mailsubject = 'GestOre - Annullamento sportello ' . $materia;
mail($docente_email, $mailsubject, $full_mail_body ,  $headers, additional_params: "-f$sender");
info("inviata mail di cancellazione ultimo studente dallo sportello - email: " . $docente_email);
?>
