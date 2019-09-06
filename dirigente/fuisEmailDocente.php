<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

$docente_id = $_POST["docente_id"];

$query = "SELECT * FROM docente WHERE docente.id = '$docente_id'";
$docente = dbGetFirst($query);

$to = $docente['email'];
$subject = 'Aggiornamento Richiesta FUIS';
$sender = "noreply-gestionale@martinomartini.eu";

$headers = "From: $sender\n";
$headers .= "MIME-Version: 1.0\n";
$headers .= "Content-Type: text/html; charset=\"UTF-8\"\n";
$headers .= "Content-Transfer-Encoding: 8bit\n";
$headers .= "X-Mailer: PHP " . phpversion();

// Corpi del messaggio nei due formati testo e HTML
$text_msg = "La sua richiesta FUIS è stata aggiornata";

$connection = 'http';
if ($__settings->system->https) {
    $connection = 'https';
}
$url = "$connection://$_SERVER[HTTP_HOST]".$__application_base_path . '/docente/attivita.php';
$html_msg = '
<html><body>
Gentile '.$docente['nome'].' '.$docente['cognome'].'

<p>la tua richiesta FUIS &egrave; stata rivista e sono state apportate alcune modifiche.</p>

<p>Le modifiche apportate possono essere riviste all&rsquo;indirizzo
<strong><a href=\''.$url.'\'>attivit&agrave;</a></strong></p>
<p>In caso di dubbi puoi rivolgerti al DS</p>
<p>Segreteria Marconi</p>
</body></html>
';

// Imposta il Return-Path (funziona solo su hosting Windows)
ini_set("sendmail_from", $sender);

// Invia il messaggio, il quinto parametro "-f$sender" imposta il Return-Path su hosting Linux
if (mail($to, $subject, $html_msg, $headers, "-f$sender")) {
    echo "email inviata correttamente a ".$docente['email'];
} else {
    echo "errore nell'invio della email";
}

?>