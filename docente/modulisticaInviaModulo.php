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

debug('Sono qui');

use Dompdf\Dompdf;
if(! isset($_GET)) {
	return;
} else {
	$template_id = $_GET['template_id'];
	$documento = $_GET['documento'];
    $docente_id = $_GET['docente_id'];
    $docente_nome = $__docente_nome;
    $docente_cognome = $__docente_cognome;
    $docente_email = $__docente_email;
    $nomeCognomeDocente = $docente_nome . ' ' . $docente_cognome;
    $anno = date("Y");;

    // recupera i dati del template
    $template = dbGetFirst("SELECT * FROM modulistica_template WHERE id = $template_id;");
}

$pagina .= '<html><head>
<link rel="icon" href="'.$__application_base_path.'/ore-32.png" />
<link rel="stylesheet" href="'.$__application_base_path.'/css/releaseversion.css">';


// ricava il titolo in modo generale
$titolo = $template['nome'] .' - ' . $nomeCognomeDocente . ' - ' . $anno;
debug('titolo' . $titolo);
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
debug('pdfFileName' . $pdfFileName);
// per ora va sempre inviata per email
$email = true;

// richiesta di invio di email
if ($email) {
    // produce il pdf da inviare
    $outputPdf = $dompdf->output();
    
    $mail = new PHPMailer(true);

    $sender = getSettingsValue('local', 'emailNoReplyFrom', '');
    $mail->setFrom($sender, 'no replay');
    $mail->addAddress($docente_email, $nomeCognomeDocente);

    // cc to carenze
    $cc = getSettingsValue('local', 'emailCarenze', '');
    if ($cc != '') {
        $mail->AddCC($cc, 'Carenze Repository');
    }

    // subject
    $mail->Subject = $titolo;
    $mail->isHTML(TRUE);
    $mail->Body = "<html><body><p><strong>".$template['nome']."</strong></p><p>Gentile ".$nomeCognomeDocente.", allegato troverai il programma di ".$anno."</p></body></html>";
    $mail->AltBody = "Gentile $nomeCognomeDocente, allegato troverai il programma di $anno";

    // allega il pdf
    $encoding = 'base64';
    $type = 'application/pdf';
    $mail->AddStringAttachment($outputPdf,$pdfFileName,$encoding,$type);

    // send the message
    if(!$mail->send()){
        warning('Message could not be sent. ' . 'Mailer Error: ' . $mail->ErrorInfo);
        echo 'Message could not be sent.';
        echo 'Mailer Error: ' . $mail->ErrorInfo;
    } else {
        // marca che e' stato notificato
        // dbExec("UPDATE piano_di_lavoro SET stato = 'notificato' WHERE id = $piano_di_lavoro_id;");

        echo "<script>window.close();</script>";
    }
}
?>