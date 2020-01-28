<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

info("inizio");

$docente_id = $_POST["docente_id"];
$oggetto_modifica = $_POST["oggetto_modifica"];

$docente = dbGetFirst("SELECT * FROM docente WHERE docente.id = '$docente_id'");

$to = $docente['email'];
$subject = "Aggiornamento Richiesta $oggetto_modifica";
$sender = $__settings->local->emailNoReplyFrom;

$headers = "From: $sender\n";
$headers .= "MIME-Version: 1.0\n";
$headers .= "Content-Type: text/html; charset=\"UTF-8\"\n";
$headers .= "Content-Transfer-Encoding: 8bit\n";
$headers .= "X-Mailer: PHP " . phpversion();

// Corpi del messaggio nei due formati testo e HTML
$text_msg = "La tua richiesta di $oggetto_modifica è stata rivista";

$connection = 'http';
if ($__settings->system->https) {
    $connection = 'https';
}
$url = "$connection://$_SERVER[HTTP_HOST]".$__application_base_path . '/index.php';
$html_msg = '
<html><body>
Gentile '.$docente['nome'].' '.$docente['cognome'].'
 
<p>la tua richiesta di '.$oggetto_modifica.' &egrave; stata rivista.</p>

<p>La versione approvata &egrave; visionabile all&rsquo;indirizzo
<strong><a href=\''.$url.'\'>attivit&agrave;</a></strong></p>
<p>In caso di dubbi puoi rivolgerti al DS</p>
<p>' . $__settings->name . ' ' . $__settings->local->nomeIstituto . '</p>
</body></html>
';

// Imposta il Return-Path (funziona solo su hosting Windows)
ini_set("sendmail_from", $sender);

// Invia il messaggio, il quinto parametro "-f$sender" imposta il Return-Path su hosting Linux
if (mail($to, $subject, $html_msg, $headers, "-f$sender")) {
    info("email $oggetto_modifica inviata correttamente a ".$docente['email']);
    echo "email $oggetto_modifica inviata correttamente a ".$docente['email'];
} else {
    info("errore nell'invio della email $oggetto_modifica a ".$docente['email']);
    echo "errore nell'invio della email $oggetto_modifica a ".$docente['email'];
}

?>