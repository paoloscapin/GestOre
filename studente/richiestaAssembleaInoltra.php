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
ruoloRichiesto('studente','segreteria-didattica');

if(! isset($_POST)) {
	return;
}
$richiesta_id = $_POST['richiesta_id'];
$uuid = escapePost('uuid');
$data_assemblea = $_POST['data_assemblea'];
$ora_assemblea = $_POST['ora_assemblea'];
$classe = $_POST['classe'];
$stato = $_POST['stato'];
$docente_id = $_POST['docente_ora_id'];
$coordinatore_id = $_POST['docente_coordinatore_id'];
$ordineDelGiorno = escapePost('ordine_del_giorno');

// studente richiedente
$studente_nome_cognome = $__studente_nome . ' ' . $__studente_cognome;

// docente coordinatore
$docente_coordinatore = dbGetFirst("SELECT * FROM docente WHERE id = $coordinatore_id");
$docente_coordinatore_nome_e_cognome = $docente_coordinatore['nome'] . ' ' . $docente_coordinatore['cognome'];
$docente_coordinatore_email = $docente_coordinatore['email'];

// docente ora
$docente_ora = dbGetFirst("SELECT * FROM docente WHERE id = $docente_id");
$docente_ora_nome_e_cognome = $docente_ora['nome'] . ' ' . $docente_ora['cognome'];
$docente_ora_email = $docente_ora['email'];

$oggetto = "$classe: richiesta Assemblea per il giorno  ".giornoEData($data_assemblea)." durante la " . nomeOra($ora_assemblea);

$testo = "La classe $classe chiede di potere svolgere un'assemblea il giorno ".giornoEData($data_assemblea)." durante la " . nomeOra($ora_assemblea) . "
L'assemblea è stata concordata con il docente dell'ora $docente_ora_nome_e_cognome che riceve questa richiesta in copia.
Riportiamo qui di seguito l'ordine del giorno:
$ordineDelGiorno

Il rappresentante di classe $studente_nome_cognome
";

$anno = date("Y");

// ricava il titolo in modo generale
$titolo = 'Richeista Assemblea ' . $classe .' - ' . giornoEData($data_assemblea) .' - ' . nomeOra($ora_assemblea) .' - ' . $docente_ora_nome_e_cognome . ' - ' . $anno;

// -------------------------------------------------------------------------------------------------------------------------------------------------------
// 1: invio della email alla didattica e in copia al docente dell'ora
$mail = new PHPMailer(true);
$mail->CharSet = 'UTF-8';
$mail->setFrom($__studente_email, $studente_nome_cognome);
$mail->addAddress(getRealEmailAddress('@segreteria-didattica'), 'Segreteria Didattica');
$mail->addCC($docente_ora_email, $docente_ora_nome_e_cognome);

// subject
$mail->Subject = $titolo;

$mail->isHTML(TRUE);
$mail->Body = '<html>'.getEmailHead().'<body>';
$mail->Body .= "<p>La classe $classe chiede di potere svolgere un'assemblea il giorno ".giornoEData($data_assemblea)." durante la " . nomeOra($ora_assemblea) . ".</p>";
$mail->Body .= "<p>L'assemblea &egrave; stata concordata con il docente dell'ora ".$docente_ora_nome_e_cognome." che riceve questa richiesta in copia.</p>";
$mail->Body .= "<p>Riportiamo qui di seguito l'ordine del giorno:</p>";
$mail->Body .= $ordineDelGiorno;
$mail->Body .= "<p>Il rappresentante di classe ".$studente_nome_cognome."</p>";

$mail->Body .= produciTabella($listaEtichette, $listaValori, $listaTipi, $listaValoriSelezionabili);
$mail->Body .= "</body></html>";
$mail->AltBody = $testo;

// send the message
if(!$mail->send()){
    warning('Message could not be sent. ' . 'Mailer Error: ' . $mail->ErrorInfo);
    echo 'errore: messaggio non inviato.';
    echo 'Mailer Error: ' . $mail->ErrorInfo;
    return;
} else {
	info('email inviata a didattica e docente '.$docente_ora_nome_e_cognome.', oggetto: '.$titolo);
}
?>
