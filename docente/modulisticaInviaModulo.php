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

$pagina = '';

require_once '../common/checkSession.php';
ruoloRichiesto('docente','dirigente','segreteria-docenti','segreteria-didattica');
require_once '../common/connect.php';
require_once '../common/dompdf/autoload.inc.php';

use Dompdf\Dompdf;
if(! isset($_POST)) {
	return;
} else {
	$template_id = $_POST['template_id'];
	$richiesta_id = $_POST['richiesta_id'];
	$documento = $_POST['documento'];
    $docente_id = $_POST['docente_id'];
    $email_to = $_POST['email_to'];
    $approva_id = $_POST['approva_id'];
	$listaEtichette = json_decode($_POST['listaEtichette']);
	$listaValori = json_decode($_POST['listaValori']);

    $docente_nome = $__docente_nome;
    $docente_cognome = $__docente_cognome;
    $docente_email = $__docente_email;
    $nomeCognomeDocente = $docente_nome . ' ' . $docente_cognome;
    $anno = date("Y");;

    // recupera i dati del template e della richiesta
    $template = dbGetFirst("SELECT * FROM modulistica_template WHERE id = $template_id;");

    // se deve essere approvata allora legge i dati della richiesta (serve solo lo uuid)
    if ($template['approva']) {
        $richiesta = dbGetFirst("SELECT * FROM modulistica_richiesta WHERE id = $richiesta_id;");
    }
}

$pagina .= '<html><head>
<link rel="icon" href="'.$__application_base_path.'/ore-32.png" />
<link rel="stylesheet" href="'.$__application_base_path.'/css/releaseversion.css">';


// ricava il titolo in modo generale
$titolo = $template['nome'] .' - ' . $nomeCognomeDocente . ' - ' . $anno;
debug('titolo=' . $titolo);
$version = phpversion();
debug('version=' . $version);

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

// ------------------------------------------------------------------
// invio della email al docente richiedente
$mail = new PHPMailer(true);

$sender = getSettingsValue('local', 'emailNoReplyFrom', '');
$mail->setFrom($sender, 'no replay');
$mail->addAddress($docente_email, $nomeCognomeDocente);

// subject
$mail->Subject = $titolo;
$mail->isHTML(TRUE);
$mail->Body = "<html><body><p>Gentile ".$nomeCognomeDocente.", allegato troverai il modulo ".$titolo." che hai inviato</p>";
$mail->Body .= "<b>&Egrave; tua responsabilit&agrave; controllare che i dati riportati siano corretti</b></p></body></html>";
$mail->Body .= "<p>Se dovessi trovare delle incongruenze con quanto da te compilato avvisa subito la segreteria inviando una email all'indirizzo</p>";
$mail->Body .= "</body></html>";
$mail->AltBody = "Gentile ".$nomeCognomeDocente.", allegato troverai il modulo ".$titolo." che hai inviato. E tua responsabilita controllare che i dati riportati siano corretti. Se dovessi trovare delle incongruenze con quanto da te compilato avvisa subito la segreteria inviando una email";

// allega il pdf
$encoding = 'base64';
$type = 'application/pdf';
$mail->AddStringAttachment($outputPdf,$pdfFileName,$encoding,$type);

// send the message
if(!$mail->send()){
    warning('Message could not be sent. ' . 'Mailer Error: ' . $mail->ErrorInfo);
    echo 'errore: messaggio non inviato.';
    echo 'Mailer Error: ' . $mail->ErrorInfo;
    return;
}

// ------------------------------------------------------------------
// invio della email al email_to con eventuale link di approvazione
$mail = new PHPMailer(true);

$mail->setFrom($docente_email, $nomeCognomeDocente);

// tutti i destinatari separati da virgole
foreach(explode(',',$email_to) as $address) {
    $mail->addAddress($address, $address);
}

// subject
$mail->Subject = $titolo;
$mail->isHTML(TRUE);


$head='<head>
	<style>
	#campi {
	  font-family: Arial, Helvetica, sans-serif;
	  border-collapse: collapse;
	  width: 100%;
	}
	#campi td, #campi th {
	  border: 1px solid #ddd;
	  padding: 6px;
	}
	#campi tr:nth-child(even){background-color: #f2f2f2;}
	#campi tr:hover {background-color: #ddd;}
	#campi th {
	  padding-top: 6px;
	  padding-bottom: 6px;
	  text-align: left;
	  background-color: #04AA6D;
	  color: white;
	}
    .col1 { width: 25%; }
    .col2 { width: 75%; }

	.btn-ar {
		color: #fff;
		padding: 15px 25px;
		margin: 20px 25px 10px 25px;
		background-image: radial-gradient(93% 87% at 87% 89%, rgba(0, 0, 0, 0.23) 0%, transparent 86.18%), radial-gradient(66% 66% at 26% 20%, rgba(255, 255, 255, 0.55) 0%, rgba(255, 255, 255, 0) 69.79%, rgba(255, 255, 255, 0) 100%);
		box-shadow: inset -3px -3px 9px rgba(255, 255, 255, 0.25), inset 0px 3px 9px rgba(255, 255, 255, 0.3), inset 0px 1px 1px rgba(255, 255, 255, 0.6), inset 0px -8px 36px rgba(0, 0, 0, 0.3), inset 0px 1px 5px rgba(255, 255, 255, 0.6), 2px 19px 31px rgba(0, 0, 0, 0.2);
		border-radius: 14px;
		font-weight: bold;
		font-size: 16px;
		border: 0;
		user-select: none;
		-webkit-user-select: none;
		touch-action: manipulation;
		cursor: pointer;
	}
	.btn-approva {
		background-color: #1CAF43;
	}
	.btn-respingi {
		background-color: #F1003C;
	}
	</style>
	</head>';

$mail->Body = '<html>'.$head.'<body>';
$mail->Body .= "<p>".$nomeCognomeDocente." ha inviato il modulo ".$template['nome'];
if ($template['approva']) {
    $mail->Body .= " che <b>richiede di essere approvato</b>.";
}
$mail->Body .= "</p>";

if ($template['approva']) {
    $mail->Body .= '<div class="form-group" style="text-align: center"><button class="btn-ar btn-approva" onclick="location.href=\'http://localhost/GestOre/docente/modulisticaRichiestaApprova.php?richiesta_id='.$richiesta_id.'&uuid='.$richiesta['uuid'].'&comando=approva\'">Approva</button>
		<button class="btn-ar btn-respingi" onclick="location.href=\'http://localhost/GestOre/docente/modulisticaRichiestaApprova.php?richiesta_id='.$richiesta_id.'&uuid='.$richiesta['uuid'].'&comando=respingi\'">Respingi</button></div>';
}

$mail->Body .= "<p>I campi del modulo sono riportati qui di seguito e il pdf generato &egrave; allegato a questa email.</p>";

$mail->Body .= '<table id="campi"><tr><th>nome</th><th>valore</th><tr>';

for ($i = 0; $i < count($listaEtichette); $i++) {
    $campo = $listaEtichette[$i];
    $valore = escapeString($listaValori[$i]);
    $mail->Body .= '<tr><td class="col1">'.$campo.'</td><td class="col2">'.$valore.'</td><tr>';
}
$mail->Body .= '</table id="campi"></p>';

$mail->Body .= "</body></html>";

// allega il pdf
$encoding = 'base64';
$type = 'application/pdf';
$mail->AddStringAttachment($outputPdf,$pdfFileName,$encoding,$type);

// send the message
if(!$mail->send()){
    warning('Message could not be sent. ' . 'Mailer Error: ' . $mail->ErrorInfo);
    echo 'errore: messaggio non inviato.';
    echo 'Mailer Error: ' . $mail->ErrorInfo;
}
?>