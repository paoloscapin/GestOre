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
require_once '../studente/richiestaUtil.php';
ruoloRichiesto('studente','segreteria-didattica');
require_once '../common/dompdf/autoload.inc.php';

use Dompdf\Dompdf;

if(! isset($_GET)) {
	return;
} else {
	$richiesta_assemblea_id = $_GET['id'];
	// controlla se e' richiesta la stampa
	if(isset($_GET['print'])) {
		$print = true;
	} else {
		$print = false;
	}
	// controlla se deve inviare una email
	if(isset($_GET['email'])) {
		$email = true;
	} else {
		$email = false;
	}
}

// load the css file into a string
ob_start();
include '../css/template-nomina.css';
$cssFileContent = ob_get_clean();

// per il pdf trasformiamo Arial in Helvetica
$cssFileContent = str_replace('Arial', 'Helvetica', $cssFileContent);

// contenuto della pagina
$data = '';

$assemblea = dbGetFirst('SELECT * FROM richiesta_assemblea WHERE id = '.$richiesta_assemblea_id.';');
$studente = dbGetFirst('SELECT * FROM studente WHERE id = '.$assemblea['studente_id'].';');
$docente_ora = dbGetFirst('SELECT * FROM docente WHERE id = '.$assemblea['docente_id'].';');
$docente_coordinatore = dbGetFirst('SELECT * FROM docente WHERE id = '.$assemblea['coordinatore_id'].';');

$studenteEmail = $studente['email'];
$studenteNomeCognome = $studente['nome'] . ' ' . $studente['cognome'];
$docente_ora_nome_e_cognome = $docente_ora['nome'] . ' ' . $docente_ora['cognome'];
$docente_ora_email = $docente_ora['email'];
$docente_coordinatore_nome_e_cognome = $docente_coordinatore['nome'] . ' ' . $docente_coordinatore['cognome'];
$docente_coordinatore_email = $docente_coordinatore['email'];


$title = $assemblea['classe'] . ": richiesta Assemblea per il giorno  ".giornoEData($assemblea['data_assemblea'])." " . nomeOra($assemblea['ora_assemblea']);

$data .= '
<h3 style="text-align: center; "><font color="#003163"><b>Assemblea di Classe - '.$assemblea['classe'].'</b></font></h3>
<h4 style="text-align: center; "><font color="#003163">'.giornoEData($assemblea['data_assemblea']).' - '.nomeOra($assemblea['ora_assemblea']).'</font></h4><p><br></p>
<fieldset style="border: 1px solid #003163; border-radius: 4px; padding: 15px; margin-bottom: 25px;"><legend style="padding: 0 10px;">
    <h4 style="margin: 0;"><b><font color="#003163">Ordine del Giorno</font></b></h4>
</legend>'.$assemblea['ordine_del_giorno'].'</fieldset>
<p><br></p>
<fieldset style="border: 1px solid #003163; border-radius: 4px; padding: 15px; margin-bottom: 25px;"><legend style="padding: 0 10px;">
    <h4 style="margin: 0;"><b><font color="#003163">Verbale</font></b></h4>
</legend>'.$assemblea['verbale'].'</fieldset>
<p><br></p><hr style="border: none; border-top: 1px solid #000080; height: 0; margin: 20px 0;"><p>Il rappresentante di classe</p><p>'.$studenteNomeCognome.'</p>';

// adesso viene il momento di produrre la pagina o il pdf
$pagina .= '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<link rel="icon" href="'.$__application_base_path.'/ore-32.png" />';
//$pagina .= '<link rel="stylesheet" href="'.$__application_base_path.'/css/releaseversion.css?v='. $__software_version.'"><link rel="stylesheet" href="'.$__application_base_path.'/css/template-nomina.css?v='. $__software_version.'">';

$pagina .= '<title>' .$title . '</title>';
$pagina .='<meta content="text/html; charset=UTF-8" http-equiv="content-type"><style>';
//$pagina .= $cssFileContent;
$pagina .='</style>';

// lo script non deve entrare nel pdf
if (! $print && ! $email) {
	$pagina .='<script type="text/javascript">
	window.onload = (event) => {
		var printBtn = document.querySelector(".btn_print");
		printBtn.onclick = function(event) {
			event.preventDefault();
			window.location.search += "&print=true";
		}
	};
	</script>';
}

// chiude l'intestazione
$pagina .='</head><body>';

// bottone di print solo se in visualizzazione
if (! $print && ! $email) {
	$pagina .='<div class="text-center noprint" style="text-align: center;padding: 50px;">
		<button class="btn btn-orange4 btn-xs btn_print"><i class="icon-play"></i>&nbsp;Scarica il pdf</button>
		</div>';
}

// il resto deve entrare in entrambi i casi, pagina o pdf: copertina, consuntivo, poi tutto il resto
$pagina .= $data;

// chiude la pagina
$pagina .= '</body></html>';

// produce il nome del file
$pdfFileName = "$title.pdf";

// decide se visualizzarla o inviarla a pdf
if (! $print && ! $email) {
	echo $pagina;
} else {
	$dompdf = new Dompdf();
	$dompdf->loadHtml($pagina);
 
	// configura i parametri
	$dompdf->setPaper('A4', 'portrait');
	
	// Render html in pdf
	$dompdf->render();

	// richiesta di invio di email
	if ($email) {
		// produce il pdf da inviare
		$outputPdf = $dompdf->output();
		
		$mail = new PHPMailer(true);
		$mail->setFrom(getSettingsValue('local', 'emailNoReplyFrom', ''), 'no reply');
		$mail->addReplyTo($studenteEmail, $studenteNomeCognome);

   		$mail->addAddress($docente_coordinatore_email, $docente_coordinatore_nome_e_cognome);
   		$mail->addCC($studenteEmail, $studenteNomeCognome);

		// subject
		$mail->Subject = 'Verbale assemblea di classe ' . $assemblea['classe'] . ' del '.dataFormattata($assemblea['data_assemblea']);

		$mail->isHTML(TRUE);
		$mail->Body = '<html><body><p>Allegato il verbale dell\'assemblea di classe della ' . $assemblea['classe'] . ' del '.dataFormattata($assemblea['data_assemblea']) . '</p></body></html>';
		$mail->AltBody = 'Allegato il verbale dell\'assemblea di classe della ' . $assemblea['classe'];

		// allega il pdf
		$encoding = 'base64';
		$type = 'application/pdf';
		$mail->AddStringAttachment($outputPdf,$pdfFileName,$encoding,$type);

		// send the message
		if(!$mail->send()){
			echo 'Message could not be sent.';
			echo 'Mailer Error: ' . $mail->ErrorInfo;
		} else {
			info('email verbale inviata al docente '.$docente_coordinatore_nome_e_cognome.' subject: '.'Verbale assemblea di classe ' . $assemblea['classe'] . ' del '.dataFormattata($assemblea['data_assemblea']));
			echo "<script>window.close();</script>";
		}
	} else if ($print) {
		// invia il pdf al browser che fa partire il download in automatico
		$dompdf->stream($pdfFileName);
	}
}
?>