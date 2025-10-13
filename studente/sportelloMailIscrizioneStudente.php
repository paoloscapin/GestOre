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
require_once '../common/send-mail.php';

ruoloRichiesto('studente', 'segreteria-didattica', 'dirigente');

$full_mail_body = file_get_contents("template_mail_iscrivi_studente.html");

$linkCalendar = 'https://calendar.google.com/calendar/render?action=TEMPLATE&dates=' . $datetime_sportello . 'Z%2F' . $datetime_fine_sportello . 'Z&details=' . urlencode("Attività " . $categoria . " - materia " . $materia . ' - Aula ' . urlencode($luogo)) . '&location=' . urlencode('Istituto Tecnico Tecnologico Buonarroti, Via Brigata Acqui, 15, 38122 Trento TN, Italia') . '&text=' . urlencode("Attività " . $categoria . " - materia " . $materia . ' - Aula ' . urlencode($luogo));

$full_mail_body = str_replace("{titolo}", "ISCRIZIONE ATTIVITA'<br>" . strtoupper($categoria), $full_mail_body);
$full_mail_body = str_replace("{nome}", strtoupper($studente_cognome) . " " . strtoupper($studente_nome), $full_mail_body);
$full_mail_body = str_replace("{messaggio}", "hai ricevuto questa mail come conferma della tua iscrizione alla seguente attività</p> <h3 style='background-color:yellow; font-size:20px'><b><center>" . strtoupper($categoria) . "</center></b></h3>", $full_mail_body);
$full_mail_body = str_replace("{data}", $data, $full_mail_body);
$full_mail_body = str_replace("{ora}", $ora, $full_mail_body);
$full_mail_body = str_replace("{docente}", strtoupper($docente_cognome . " " . $docente_nome), $full_mail_body);
$full_mail_body = str_replace("{materia}", $materia, $full_mail_body);
$full_mail_body = str_replace("{aula}", $luogo, $full_mail_body);
$full_mail_body = str_replace("{link}", $linkCalendar, $full_mail_body);
$full_mail_body = str_replace("{nome_istituto}", $__settings->local->nomeIstituto, $full_mail_body);

$to = $studente_email;
$toName = $studente_nome . " " . $studente_cognome;
$toCC = $email_genitori;
$toCCName = $nominativo_genitori;
info("Invio mail al docente: " . $to . " " . $toName);
$mailsubject = 'GestOre - Prenotazione attività ' . $categoria . ' - materia ' . $materia;
if ($toCC != "") {
  sendMailCC($to, $toName, $toCC, $toCCName,$mailsubject, $full_mail_body);
  info("mail di iscrizione prenotazione inviata anche al genitore - email: " . $toCC);
} else {
  sendMail($to, $toName, $mailsubject, $full_mail_body);
  info("mail di iscrizione prenotazione inviata allo studente - email: " . $studente_email);
}

?>