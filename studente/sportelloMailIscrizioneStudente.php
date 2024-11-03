<!--
/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
-->

<?php

  require_once '../common/checkSession.php';
  ruoloRichiesto('studente','segreteria-didattica','dirigente');

  $full_mail_body = file_get_contents("template_mail_iscrivi_studente.html");


  $linkCalendar = 'https://calendar.google.com/calendar/render?action=TEMPLATE&dates=' . $datetime_sportello . 'Z%2F' . $datetime_fine_sportello . 'Z&details=' . urlencode("Attività " . $categoria . " - materia ". $materia . ' - Aula ' . urlencode($luogo)) . '&location=' . urlencode('Istituto Tecnico Tecnologico Buonarroti, Via Brigata Acqui, 15, 38122 Trento TN, Italia') . '&text=' . urlencode("Attività " . $categoria . " - materia ". $materia . ' - Aula ' . urlencode($luogo));

  $full_mail_body = str_replace("{titolo}","ISCRIZIONE ATTIVITA'<br>".strtoupper($categoria),$full_mail_body);
  $full_mail_body = str_replace("{nome}",strtoupper($studente_cognome) . " " . strtoupper($studente_nome),$full_mail_body);
  $full_mail_body = str_replace("{messaggio}","hai ricevuto questa mail come conferma della tua iscrizione alla seguente attività</p> <h3 style='background-color:yellow; font-size:20px'><b><center>" . strtoupper($categoria) . "</center></b></h3>",$full_mail_body);
  $full_mail_body = str_replace("{data}",$data,$full_mail_body);
  $full_mail_body = str_replace("{ora}",$ora,$full_mail_body);
  $full_mail_body = str_replace("{docente}",strtoupper($docente_cognome . " " . $docente_nome),$full_mail_body);
  $full_mail_body = str_replace("{materia}",$materia,$full_mail_body);
  $full_mail_body = str_replace("{aula}",$luogo,$full_mail_body);
  $full_mail_body = str_replace("{link}",$linkCalendar,$full_mail_body);
  $full_mail_body = str_replace("{nome_istituto}",$__settings->local->nomeIstituto,$full_mail_body);
  
  $sender = $__settings->local->emailNoReplyFrom;
  $headers  = 'MIME-Version: 1.0' . "\r\n";
  $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
  $headers .= "From: " . $sender . "\r\n";
  $headers .= "Bcc: " . $__settings->local->emailSportelli . "\r\n"."X-Mailer: php";
  $mailsubject = 'GestOre - Prenotazione attività ' . $categoria . ' - materia '. $materia;
  mail($studente_email, $mailsubject, $full_mail_body ,  $headers, additional_params: "-f$sender");
  info("inviata mail allo studente per iscrizione allo sportello - email: " . $studente_email);
 ?>
  




