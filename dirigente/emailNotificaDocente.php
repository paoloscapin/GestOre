<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/send-mail.php';

$docente_id = $_POST["docente_id"];
$oggetto_modifica = $_POST["oggetto_modifica"];

$docente = dbGetFirst("SELECT * FROM docente WHERE docente.id = '$docente_id'");

$connection = 'http';
if ($__settings->system->https) {
    $connection = 'https';
}
$url = "$connection://$_SERVER[HTTP_HOST]".$__application_base_path . '/index.php';

$html_msg = '
<html><body>
Gentile '.$docente['nome'].' '.$docente['cognome'].'
 
<p>la tua richiesta di '.$oggetto_modifica.' &egrave; stata rivista.</p>

<p>La versione approvata &egrave; visionabile all&rsquo;indirizzo del
<strong><a href=\''.$url.'\'>GestOre</a></strong></p>
<p>In caso di dubbi puoi rivolgerti al DS</p>
<p>' . $__settings->name . ' ' . $__settings->local->nomeIstituto . '</p>
</body></html>
';

$to = $docente['email'];
$toName = $docente['nome'] . " " . $docente['cognome'];
info("Invio mail al docente: ".$to." ".$toName);
echo "Inviata mail al docente: ".$to." ".$toName." - ";
$mailsubject = 'GestOre - Aggiornamento attività previste ' . $oggetto_modifica;
sendMail($to,$toName,$mailsubject,$html_msg);
info("inviata mail ".$mailsubject);

?>