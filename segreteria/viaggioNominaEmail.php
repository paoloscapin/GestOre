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

$viaggio_id = $_POST["viaggio_id"];

$query = "SELECT
				viaggio.id AS viaggio_id,
				viaggio.protocollo AS viaggio_protocollo,
				viaggio.tipo_viaggio AS viaggio_tipo_viaggio,
				viaggio.data_nomina AS viaggio_data_nomina,
				viaggio.data_partenza AS viaggio_data_partenza,
				viaggio.data_rientro AS viaggio_data_rientro,
				viaggio.ora_partenza AS viaggio_ora_partenza,
				viaggio.ora_rientro AS viaggio_ora_rientro,
				viaggio.destinazione AS viaggio_destinazione,
				viaggio.classe AS viaggio_classe,
				viaggio.stato AS viaggio_stato,
				docente.email AS docente_email,
				docente.cognome AS docente_cognome,
				docente.nome AS docente_nome
			FROM viaggio viaggio
			INNER JOIN docente docente
			ON viaggio.docente_id = docente.id
			WHERE viaggio.id = '$viaggio_id'";


$viaggio = dbGetFirst($query);

$tipoViaggio = $viaggio['viaggio_tipo_viaggio'];

$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
$dataNomina = utf8_encode( strftime("%d %B %Y", strtotime($viaggio['viaggio_data_nomina'])));
$dataPartenza = utf8_encode( strftime("%d %B %Y", strtotime($viaggio['viaggio_data_partenza'])));
$dataRientro = utf8_encode( strftime("%d %B %Y", strtotime($viaggio['viaggio_data_rientro'])));
setlocale(LC_TIME, $oldLocale);

$viaggioDestinazione = $viaggio['viaggio_destinazione'];

$docenteNomeCognome = $viaggio['docente_nome'].' '.$viaggio['docente_cognome'];
$docenteEmail = $viaggio['docente_email'];
$subject = 'Incarico '.$viaggio['viaggio_tipo_viaggio'].' a '.$viaggio['viaggio_destinazione'].' del '.$dataPartenza;
$connection = 'http';
if ($__settings->system->https) {
    $connection = 'https';
}
$url = "$connection://$_SERVER[HTTP_HOST]".$__application_base_path . '/index.php';

// invia la email al docente		
$mail = new PHPMailer(true);

$sender = getSettingsValue('local', 'emailNoReplyFrom', '');
$mail->setFrom($sender, 'no reply');
$mail->addAddress($docenteEmail, $docenteNomeCognome);

// subject
$mail->Subject = $subject;

// il testo del messaggio in html
$html_msg = '<html><body>Gentile '.$docenteNomeCognome.'<p>in data '.$dataNomina.' il Dirigente Scolastico le ha conferito l&rsquo;incarico di accompagnatore degli studenti
	durante '.$tipoViaggio.' a <b>'.$viaggio['viaggio_destinazione'].'</b> del giorno <b>'.$dataPartenza.'</b></p>
	<p>La preghiamo di confermare al pi&ugrave; presto la sua disponibilit&agrave; confermando sul sito di <a href=\''.$url.'\'>accettare l&rsquo;incarico</a></p><p>' . $__settings->name . ' ' . $__settings->local->nomeIstituto . '</p></body></html>';

$mail->isHTML(TRUE);
$mail->Body = $html_msg;
$mail->AltBody = "Gentile $docenteNomeCognome, il DS ti ha conferito l'incarico per il viaggio a ".$viaggio['viaggio_destinazione']." del giorno ".$dataPartenza;

// allega il pdf
// $encoding = 'base64';
// $type = 'application/pdf';
// $mail->AddStringAttachment($outputPdf,$pdfFileName,$encoding,$type);

// send the message
$message = "Invio email incarico a $docenteNomeCognome viaggio a $viaggioDestinazione del $dataPartenza: ";

if(!$mail->send()){
	$message .= "errore nell'invio del messaggio. Errore: ".$mail->ErrorInfo;
	warning($message);
	echo $message;
} else {
	$message .= "email inviata correttamente.";
	info($message);
	echo $message;
	// marca che e' stato notificato (non necessario per i viaggi?)
}
?>