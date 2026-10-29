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
require_once '../common/connect.php';
require_once '../studente/richiestaUtil.php';
ruoloRichiesto('segreteria-didattica');

if(! isset($_POST)) {
	return;
}
$richiesta_id = $_POST['richiesta_id'];
$stato = $_POST['stato'];

// recupera i dati della richiesta
$richiesta = dbGetFirst("SELECT * FROM richiesta_assemblea WHERE id=$richiesta_id;");
$classe = $richiesta['classe'];
$data_assemblea = $richiesta['data_assemblea'];
$ora_assemblea = $richiesta['ora_assemblea'];
$coordinatore_id = $richiesta['coordinatore_id'];
$docente_id = $richiesta['docente_id'];
$studente_id = $richiesta['studente_id'];

// docente coordinatore
$docente_coordinatore = dbGetFirst("SELECT * FROM docente WHERE id = $coordinatore_id");
$docente_coordinatore_nome_e_cognome = $docente_coordinatore['nome'] . ' ' . $docente_coordinatore['cognome'];
$docente_coordinatore_email = $docente_coordinatore['email'];

// docente ora
$docente_ora = dbGetFirst("SELECT * FROM docente WHERE id = $docente_id");
$docente_ora_nome_e_cognome = $docente_ora['nome'] . ' ' . $docente_ora['cognome'];
$docente_ora_email = $docente_ora['email'];

// studente richiedente
$studente = dbGetFirst("SELECT * FROM studente WHERE id = $studente_id");
$studente_nome_cognome = $studente['nome'] . ' ' . $studente['cognome'];
$studente_email = $studente['email'];

$oggetto = "$classe: $stato richiesta Assemblea per il giorno  ".giornoEData($data_assemblea)." durante la " . nomeOra($ora_assemblea);

$testo = "L'assemblea del giorno ".giornoEData($data_assemblea)." durante la " . nomeOra($ora_assemblea) . " è stata " . $stato;

// -------------------------------------------------------------------------------------------------------------------------------------------------------
// 1: invio della email allo studente e in copia al docente dell'ora
$mail = new PHPMailer(true);
$mail->CharSet = 'UTF-8';
$mail->setFrom(getSettingsValue('local', 'emailSegreteriaDidattica', ''), 'Segreteria Didattica');
$mail->addAddress($studente_email, $studente_nome_cognome);
$mail->addCC($docente_ora_email, $docente_ora_nome_e_cognome);

// subject
$mail->Subject = $titolo;

$mail->isHTML(TRUE);
$mail->Body = '<html>'.getEmailHead().'<body>';
$mail->Body .= "<p>L'assemblea del giorno ".giornoEData($data_assemblea)." durante la " . nomeOra($ora_assemblea);
$mail->Body .= " &egrave; stata ".$stato.".</p>";
$mail->Body .= "</body></html>";
$mail->AltBody = $testo;

// send the message
if(!$mail->send()){
    warning('Message could not be sent. ' . 'Mailer Error: ' . $mail->ErrorInfo);
    echo 'errore: messaggio non inviato.';
    echo 'Mailer Error: ' . $mail->ErrorInfo;
    return;
} else {
	info('email inviata a studente e docente '.$docente_ora_nome_e_cognome.', oggetto: '.$titolo);
}
?>
