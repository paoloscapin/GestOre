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
ruoloRichiesto('docente','dirigente','segreteria-docenti','segreteria-didattica');
require_once '../common/connect.php';
require_once '../common/dompdf/autoload.inc.php';
require_once '../docente/modulisticaProduciTabella.php';

$pagina = '';

use Dompdf\Dompdf;
if(! isset($_POST)) {
	return;
} else {
	$template_id = $_POST['template_id'];
	$richiesta_id = $_POST['richiesta_id'];
	$documento = $_POST['documento'];
    $docente_id = $_POST['docente_id'];
    $approva_id = $_POST['approva_id'];
	$listaValori = json_decode($_POST['listaValori']);

    $docente_nome = $__docente_nome;
    $docente_cognome = $__docente_cognome;
    $docente_email = $__docente_email;
    $nomeCognomeDocente = $docente_nome . ' ' . $docente_cognome;
    $anno = date("Y");

    // recupera i dati del template e della richiesta
    $template = dbGetFirst("SELECT * FROM modulistica_template WHERE id = $template_id;");
    $email_to = $template['email_to'];
    $email_approva = $template['email_approva'];

	$listaEtichette = [];
	$listaTipi = [];
	$listaValoriSelezionabili = [];
	foreach(dbGetAll("SELECT * FROM modulistica_template_campo WHERE modulistica_template_id = $template_id ORDER BY posizione;") as $campo) {
		$listaEtichette[] = $campo['etichetta'];
		$listaValoriSelezionabili[] = $campo['lista_valori'];
		$listaTipi[] = $campo['tipo'];
	}

	// se deve essere approvata allora legge i dati della richiesta (serve solo lo uuid)
    if ($template['approva']) {
        $richiesta = dbGetFirst("SELECT * FROM modulistica_richiesta WHERE id = $richiesta_id;");
    }
}

$pagina .= '<html><head>
<link rel="icon" href="'.$__application_base_path.'/ore-32.png" />
<link rel="stylesheet" href="'.$__application_base_path.'/css/releaseversion.css">';

// ricava il titolo in modo generale
$titolo = 'M-' . $richiesta_id . ' ' . $template['nome'] .' - ' . $nomeCognomeDocente . ' - ' . $anno;

// aggiunge nella pagina il titolo e gli stili
$pagina .= '<title>' . $titolo . '</title>';

$pagina .='
<meta content="text/html; charset=UTF-8" http-equiv="content-type">
<style>
	h1,h2,h3,h4,h5 { color: #0e2c50; font-family: Helvetica, Sans-Serif; }
	.unita_titolo { display:inline-block; vertical-align: middle; }
	.nome { text-transform:uppercase; color: #0e2c50; font-family: Helvetica, Sans-Serif; display: block; font-weight: bold; font-size: .83em; }
	.nomeSemplice { color: #0e2c50; font-family: Helvetica, Sans-Serif; display: block; font-weight: bold; font-size: .83em; }
	body { max-width: 800px; }
	@media print {
		.noprint {
			visibility: hidden;
		}
	}
	 @page {
		@bottom-left {
			content: counter(page) " of " counter(pages);
		}
	}
</style>';

// chiude l'intestazione
$pagina .='</head><body>';

// aggiunge l'intestazione se richiesta
if ($template['intestazione']) {
    $pagina .= '
	<div style="; text-align: center;">
		<span style="overflow: hidden; display: inline-block; margin: 0.00px 0.00px; border: 0.00px solid #000000; transform: rotate(0.00rad) translateZ(0px); -webkit-transform: rotate(0.00rad) translateZ(0px); width: 642.82px;">
			<img alt="" src="data:image/png;base64,'.base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'intestazione.png'")).'" style="width: 642.82px; margin-left: 0.00px; margin-top: 0.00px; transform: rotate(0.00rad) translateZ(0px); -webkit-transform: rotate(0.00rad) translateZ(0px);" title="">
		</span>
		<hr>
	</div>';
}

// aggiunge il corpo del documento nel pdf
$pagina .= $documento;

// chiude la pagina
$pagina .= '</body></html>';

// costruisce il pdf
$dompdf = new Dompdf();
$dompdf->loadHtml($pagina);

// configura i parametri
$dompdf->setPaper('A4', 'portrait');

// Render html in pdf
$dompdf->render();

// produce il nome del file
$pdfFileName = $titolo . ".pdf";
debug('pdfFileName=' . $pdfFileName);

// produce il pdf da inviare
$outputPdf = $dompdf->output();
$encoding = 'base64';
$type = 'application/pdf';

// -------------------------------------------------------------------------------------------------------------------------------------------------------
// ora deve inviare la emai potenzialmente a tre diversi destinatari: il docente richiedente, l'ufficio competente, il dirigente se richiede approvazione
// 1: invio della email al docente richiedente
$mail = new PHPMailer(true);
$mail->setFrom(getSettingsValue('local', 'emailNoReplyFrom', ''), 'no replay');
$mail->addAddress($docente_email, $nomeCognomeDocente);

// subject
$mail->Subject = $titolo;
$mail->isHTML(TRUE);
$mail->Body = '<html>'.getEmailHead().'<body>';
$mail->Body .= "<p>Gentile ".$nomeCognomeDocente.", allegato troverai il modulo ".$template['nome']." che hai inviato</p>";
$mail->Body .= "<b>&Egrave; tua responsabilit&agrave; controllare che i dati riportati siano corretti</b></p></body></html>";
$mail->Body .= "<p>Se dovessi trovare delle incongruenze con quanto da te compilato avvisa subito la segreteria inviando una email all'indirizzo: " . $email_to . "</p>";
$mail->Body .= '<p>I campi del modulo sono riportati qui di seguito e il pdf generato &egrave; allegato a questa email.</p>';
$mail->Body .= produciTabella($listaEtichette, $listaValori, $listaTipi, $listaValoriSelezionabili);
$mail->Body .= "</body></html>";
$mail->AltBody = "Gentile ".$nomeCognomeDocente.", allegato troverai il modulo ".$template['nome']." che hai inviato. E tua responsabilita controllare che i dati riportati siano corretti. Se dovessi trovare delle incongruenze con quanto da te compilato avvisa subito la segreteria inviando una email";

// allega il pdf
$mail->AddStringAttachment($outputPdf,$pdfFileName,$encoding,$type);

// send the message
if(!$mail->send()){
    warning('Message could not be sent. ' . 'Mailer Error: ' . $mail->ErrorInfo);
    echo 'errore: messaggio non inviato.';
    echo 'Mailer Error: ' . $mail->ErrorInfo;
    return;
}

// -------------------------------------------------------------------------------------------------------------------------------------------------------
// 2: se non richiede l'approvazione, inoltra la richiesta direttamente al destinatario
if (! $template['approva']) {
	$mail = new PHPMailer(true);
	$mail->setFrom($docente_email, $nomeCognomeDocente);
	// tutti i destinatari separati da virgole
	foreach(explode(',',$email_to) as $address) {
		$mail->addAddress($address, $address);
	}

	// subject
	$mail->Subject = $titolo;
	$mail->isHTML(TRUE);

	$mail->Body = '<html>'.getEmailHead().'<body>';
	$mail->Body .= "<p>".$nomeCognomeDocente." ha inviato il modulo ".$template['nome'];
	$mail->Body .= "</p>";
	$mail->Body .= '<p>I campi del modulo sono riportati qui di seguito e il pdf generato &egrave; allegato a questa email.</p>';
	$mail->Body .= produciTabella($listaEtichette, $listaValori, $listaTipi, $listaValoriSelezionabili);
	$mail->Body .= "</body></html>";
	$mail->AltBody = $nomeCognomeDocente."ha inviato il modulo ".$template['nome']." qui allegato.";

	// allega il pdf
	$mail->AddStringAttachment($outputPdf,$pdfFileName,$encoding,$type);

	// send the message
	if(!$mail->send()){
		warning('Message could not be sent. ' . 'Mailer Error: ' . $mail->ErrorInfo);
		echo 'errore: messaggio non inviato.';
		echo 'Mailer Error: ' . $mail->ErrorInfo;
		return;
	}
}

// -------------------------------------------------------------------------------------------------------------------------------------------------------
// 3: se richiesta l'approvazione, invio della email a chi la deve approvare (con link di approvazione)
if ($template['approva']) {
	$mail = new PHPMailer(true);
	$mail->setFrom($docente_email, $nomeCognomeDocente);
	// tutti i destinatari che possono approvare separati da virgole
	foreach(explode(',',$email_approva) as $address) {
		$mail->addAddress($address, $address);
	}

	// subject
	$mail->Subject = $titolo;
	$mail->isHTML(TRUE);

	$mail->Body = '<html>'.getEmailHead().'<body>';
	$mail->Body .= "<p>" . $nomeCognomeDocente . " ha inviato il modulo ".$template['nome'] . " che <b>richiede di essere approvato</b>.</p>";

	// inserisce i bottoni per approvare o respingere
	$mail->Body .= '<div class="form-group" style="text-align: center">
		<a class="btn-ar btn-approva" href=\''.$__http_base_link.'/docente/modulisticaRichiestaApprova.php?richiesta_id='.$richiesta_id.'&uuid='.$richiesta['uuid'].'&comando=approva\'">Approva</a>
		<a class="btn-ar btn-respingi" href=\''.$__http_base_link.'/docente/modulisticaRichiestaApprova.php?richiesta_id='.$richiesta_id.'&uuid='.$richiesta['uuid'].'&comando=respingi\'">Respingi</a>
		</div>';
	$mail->Body .= '<p>I campi del modulo sono riportati qui di seguito e il pdf generato &egrave; allegato a questa email.</p>';
	$mail->Body .= produciTabella($listaEtichette, $listaValori, $listaTipi, $listaValoriSelezionabili);
	$mail->Body .= "</body></html>";

	// allega il pdf
	$mail->AddStringAttachment($outputPdf,$pdfFileName,$encoding,$type);

	// send the message
	if(!$mail->send()){
		warning('Message could not be sent. ' . 'Mailer Error: ' . $mail->ErrorInfo);
		echo 'errore: messaggio non inviato.';
		echo 'Mailer Error: ' . $mail->ErrorInfo;
	}
}

function getEmailHead() {
	$head='<head><style>
		#campi { font-family: Arial, Helvetica, sans-serif; border-collapse: collapse; width: 100%; }
		#campi td, #campi th { border: 1px solid #ddd; padding: 6px; }
		#campi tr:nth-child(even) { background-color: #f2f2f2; }
		#campi tr:hover { background-color: #ddd; }
		#campi th { padding-top: 6px; padding-bottom: 6px; text-align: left; background-color: #04AA6D; color: white; }
		.col1 { width: 25%; }
		.col2 { width: 75%; }
		.tick { margin-left: 0.65cm; text-indent: -0.65cm; }
		.btn-ar { color: #fff; padding: 10px 15px; margin: 20px 15px 10px 15px;
			background-image: radial-gradient(93% 87% at 87% 89%, rgba(0, 0, 0, 0.23) 0%, transparent 86.18%), radial-gradient(66% 66% at 26% 20%, rgba(255, 255, 255, 0.55) 0%, rgba(255, 255, 255, 0) 69.79%, rgba(255, 255, 255, 0) 100%);
			box-shadow: inset -3px -3px 9px rgba(255, 255, 255, 0.25), inset 0px 3px 9px rgba(255, 255, 255, 0.3), inset 0px 1px 1px rgba(255, 255, 255, 0.6), inset 0px -8px 36px rgba(0, 0, 0, 0.3), inset 0px 1px 5px rgba(255, 255, 255, 0.6), 2px 19px 31px rgba(0, 0, 0, 0.2);
			border-radius: 12px; font-weight: bold; font-size: 14px; border: 0; user-select: none; -webkit-user-select: none; touch-action: manipulation; cursor: pointer; }
		.btn-approva { background-color: #1CAF43; }
		.btn-respingi { background-color: #F1003C; }
		.btn-waiting { background-color: #f2e829; }
		.btn-chiudi { background-color: #0ae4f0; }
		.btn-pendente { background-color: #777777; }
		.btn-label { padding: 5px 12px; border-radius: 8px; margin: 10px 5px; }
		</style></head>';

	return $head;
}
?>