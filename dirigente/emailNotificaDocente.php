<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once '../common/PHPMailer/PHPMailer.php';
require_once '../common/PHPMailer/Exception.php';

require_once '../common/checkSession.php';

$docente_id = $_POST["docente_id"];
$oggetto_modifica = $_POST["oggetto_modifica"];

$docente = dbGetFirst("SELECT * FROM docente WHERE docente.id = '$docente_id'");
$docenteEmail= $docente['email'];
$docenteNomeCognome = $docente['nome'].' '.$docente['cognome'];

$subject = "Aggiornamento Richiesta $oggetto_modifica";

// invia la email al docente		
$mail = new PHPMailer(true);

$sender = getSettingsValue('local', 'emailNoReplyFrom', '');
$mail->setFrom($sender, 'no reply');
$mail->addAddress($docenteEmail, $docenteNomeCognome);

// subject
$mail->Subject = $subject;

$connection = 'http';
if ($__settings->system->https) {
    $connection = 'https';
}
$url = "$connection://$_SERVER[HTTP_HOST]".$__application_base_path . '/index.php';

// il testo del messaggio in html
$html_msg = '<html><body>Gentile '.$docenteNomeCognome.'<p>la tua richiesta di '.$oggetto_modifica.' &egrave; stata rivista.</p><p>La versione approvata &egrave; visionabile all&rsquo;indirizzo <strong><a href=\''.$url.'\'>attivit&agrave;</a></strong></p>
<p>In caso di dubbi puoi rivolgerti al DS</p><p>' . $__settings->name . ' ' . $__settings->local->nomeIstituto . '</p></body></html>';

$mail->isHTML(TRUE);
$mail->Body = $html_msg;
$mail->AltBody = 'Gentile $docenteNomeCognome, la tua richiesta di '.$oggetto_modifica.' &egrave; stata rivista.';

// send the message
$message = "Invio email revisione $oggetto_modifica a $docenteNomeCognome: ";

if(!$mail->send()){
	$message .= "errore nell'invio del messaggio. Errore: ".$mail->ErrorInfo;
	warning($message);
	echo $message;
} else {
	$message .= "email inviata correttamente.";
	info($message);
	echo $message;
}
?>