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
require_once '../common/importi_load.php';
ruoloRichiesto('segreteria-docenti');
require_once '../common/dompdf/autoload.inc.php';

use Dompdf\Dompdf;

if(! isset($_GET)) {
	return;
} else {
	$viaggio_id = $_GET['viaggio_id'];
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

function printableDate($data) {
	if ($data != null) {
		return strftime("%d/%m/%Y", strtotime($data));
	}
	return null;
}

// load the css file into a string
ob_start();
include '../css/template-nomina.css';
$cssFileContent = ob_get_clean();

// per il pdf trasformiamo Arial in Helvetica
$cssFileContent = str_replace('Arial', 'Helvetica', $cssFileContent);

// legge i dati del viaggio e del docente
$query = "SELECT viaggio.id AS viaggio_id, viaggio.*, docente.email AS docente_email, docente.cognome AS docente_cognome, docente.nome AS docente_nome FROM viaggio INNER JOIN docente ON viaggio.docente_id = docente.id WHERE viaggio.id = '$viaggio_id'";
$viaggio = dbGetFirst($query);

$tipoViaggio = "";
$tipoViaggioNoArticolo = "";
if ($viaggio['tipo_viaggio'] === 'Uscita Formativa') {
	$tipoViaggio = "l'uscita formativa";
	$tipoViaggioNoArticolo = "uscita formativa";
} else if ($viaggio['tipo_viaggio'] === 'Visita Guidata') {
	$tipoViaggio = "la visita guidata";
	$tipoViaggioNoArticolo = "visita guidata";
} else if ($viaggio['tipo_viaggio'] === 'Viaggio di Istruzione') {
	$tipoViaggio = "il viaggio d'istruzione";
	$tipoViaggioNoArticolo = "viaggio d'istruzione";
}

$note = $viaggio['note'];
$protocollo = $viaggio['protocollo'];
$destinazione = $viaggio['destinazione'];
$classe = $viaggio['classe'];

$oraPartenza = date('G:i', strtotime($viaggio['ora_partenza']));
$oraRientro = date('G:i', strtotime($viaggio['ora_rientro']));

$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
$dataNomina = utf8_encode( strftime("%d %B %Y", strtotime($viaggio['data_nomina'])));
$dataPartenza = utf8_encode( strftime("%d %B %Y", strtotime($viaggio['data_partenza'])));
$dataRientro = utf8_encode( strftime("%d %B %Y", strtotime($viaggio['data_rientro'])));
setlocale(LC_TIME, $oldLocale);
$luogoIstituto = $__settings->local->luogoIstituto;

$docenteNomeCognome = $viaggio['docente_nome'] . ' ' . $viaggio['docente_cognome'];
$docenteEmail = $viaggio['docente_email'];

$title = 'nomina prot '.str_replace('/','-',$viaggio['protocollo']).' '.$viaggio['docente_cognome'].' '.$viaggio['docente_nome'].' - '.$viaggio['destinazione'];

// contenuto della pagina
$data = '';

$data .= '
<body class="c13">
	<div>
		<p class="c7">
			<span style="overflow: hidden; display: inline-block; margin: 0.00px 0.00px; border: 0.00px solid #000000; transform: rotate(0.00rad) translateZ(0px); -webkit-transform: rotate(0.00rad) translateZ(0px); width: 642.82px;">
				<img alt="" src="data:image/png;base64,'.base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'intestazione.png'")).'" style="width: 642.82px; margin-left: 0.00px; margin-top: 0.00px; transform: rotate(0.00rad) translateZ(0px); -webkit-transform: rotate(0.00rad) translateZ(0px);" title="">
			</span>
<hr>
		</p>
	</div>
	<p class="c6 c10">
		<span class="c4"></span>
	</p>
	<a id="t.2983f64f2b1f99c5e93bd72decaa07795be55513"></a>
	<a id="t.0"></a>
	<table class="c11">
		<tbody>
			<tr class="c8">
				<td class="c2" colspan="1" rowspan="1"><p class="c6">
						<span class="c9">Prot. n&deg; '.$protocollo.'</span>
					</p></td>
				<td class="c2" colspan="1" rowspan="1"><p class="c6 c14">
						<span class="c4">'.$luogoIstituto.', '.$dataNomina.'</span>
					</p></td>
			</tr>
		</tbody>
	</table>
	<table class="c11">
		<tbody>
			<tr class="c8">
				<td class="c2" colspan="1" rowspan="1"><p class="c6">
					</p></td>
				<td class="c2" colspan="1" rowspan="1">
					<p class="c6 c14">
						<span class="c4">Gentile docente '.$docenteNomeCognome.'</span>
					</p>
				</td>
			</tr>
		</tbody>
	</table>
	</br>
	<p class="c3">
		<span class="c9">OGGETTO: conferimento incarico.</span>
	</p>
	<p class="c10 c12">
		<span class="c9"></span>
	</p>
	</br>
	<p class="c12">
		<span class="c9">IL DIRIGENTE SCOLASTICO</span>
	</p>
	</br>
	<p class="c3">
		<span class="c5">VISTA </span><span class="c1">la disponibilit&agrave; della S.V. ad accompagnare in '.$tipoViaggioNoArticolo.'</span>
	</p>
	<p class="c3">
		<span class="c5">CONSIDERATO </span><span class="c1">che l&#39;attivit&agrave; sotto descritta &egrave; stata approvata dal consiglio di classe;</span>
	</p>
	<p class="c3">
		<span class="c5 c16">VISTA  </span><span class="c1">la delibera del consiglio dell&#39;Istituzione di approvazione del REGOLAMENTO PER L&rsquo;ORGANIZZAZIONE DI VISITE GUIDATE E VIAGGI DI ISTRUZIONE</span>
	</p>
	<p class="c3">
		<span class="c5">TENUTO CONTO </span><span class="c1">che '.$tipoViaggio.' a '.$destinazione.' sar&agrave; effettuato/a per le classi '.$classe.'</span>
	</p>
	<p class="c3">
		<span class="c1">con partenza alle ore '.$oraPartenza.' del '.$dataPartenza.' e ritorno alle ore '.$oraRientro.' del '.$dataRientro.'</span>
	</p>
	<p class="c3">
		<span class="c5">TENUTO CONTO</span><span class="c1">&nbsp;che si autorizza l&#39;impegno FUIS, ovvero si prevede il riconoscimento delle ore nella misura prevista dalle vigenti disposizioni contrattuali, per complessivi n&deg; 1 accompagnatori</span>
	</p>
	<p class="c3">
		<span class="c5">VISTO </span><span class="c1">il CCPL vigente;</span>
	</p>
	</br>
	<p class="c12">
		<span class="c9">CONFERISCE</span>
	</p>
	</br>
	<p class="c3">
		<span class="c1">alla S.V. l&rsquo;incarico di accompagnatore degli studenti durante la predetta visita a '.$destinazione.'</span>
	</p>
	<p class="c3">
		<span class="c1">Detto incarico comporta l&rsquo;assunzione di responsabilit&agrave;, ai sensi dell&rsquo;art. 2047 CC, e quindi l&rsquo;obbligo</span>
	</p>
	<p class="c3">
		<span class="c1">di attenta e assidua vigilanza degli alunni, esercitata a tutela dell&rsquo;incolumit&agrave; degli stessi e del patrimonio artistico.</span>
	</p>
	<p class="c3">
		<span class="c1">Il dovere di vigilanza va esercitato per la durata dell&#39;attivit&agrave;, nei limiti esplicitati nella nota illustrativa dell&#39;attivit&agrave;</span>
	</p>
	<p class="c3">
		<span class="c1">e nelle dichiarazioni di responsabilit&agrave; sottoscritte dai genitori.</span>
	</p>
	<p class="c3">
		<span class="c1">La S.V. &egrave; tenuta ad informare il ds su eventuali anomalie, con riferimento ai servizi acquistati (vettore, vitto,</span>
	</p>
	<p class="c3">
		<span class="c1">alloggio, ecc.) prima della partenza, durante l&#39;attivit&agrave;, nonch&eacute; successivamente alla stessa.</span>
	</p>
	</br>
';

if ($note != null && strlen($note) > 0) {
	$data .= '<p class="c3"><span class="c5">NOTE: </span><span class="c1">'.$note.'</span></p></br>';
}

$data .= '
	<a id="t.f727949b760321cc972232d42b2d9fa1f8785d82"></a>
	<a id="t.1"></a>
	<table class="c11">
		<tbody>
			<tr class="c18">
				<td class="c2" colspan="1" rowspan="1"><p class="c17">
					</p></td>
				<td class="c2" colspan="1" rowspan="1">
				</td>
			</tr>
		</tbody>
	</table>
	<table class="c11">
		<tbody>
			<tr class="c18">
				<td class="c2" colspan="1" rowspan="1"><p class="c17">
						<span style="overflow: hidden; display: inline-block; margin: 0.00px 0.00px; border: 0.00px solid #000000; transform: rotate(0.00rad) translateZ(0px); -webkit-transform: rotate(0.00rad) translateZ(0px); width: 100.00px; height: 100.00px;">
							<img alt="" src="data:image/png;base64,'.base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'timbro.png'")).'" style="width: 100.00px; height: 100.00px; margin-left: 0.00px; margin-top: 0.00px; transform: rotate(0.00rad) translateZ(0px); -webkit-transform: rotate(0.00rad) translateZ(0px);" title="">
						</span>
					</p></td>
				<td class="c2" colspan="1" rowspan="1"><p class="c19">
						<span style="overflow: hidden; display: inline-block; margin: 0.00px 0.00px; border: 0.00px solid #000000; transform: rotate(0.00rad) translateZ(0px); -webkit-transform: rotate(0.00rad) translateZ(0px); height: 98.00px;">
							<img alt="" src="data:image/png;base64,'.base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'firma.png'")).'" style="height: 98.00px; margin-left: 0.00px; margin-top: 0.00px; transform: rotate(0.00rad) translateZ(0px); -webkit-transform: rotate(0.00rad) translateZ(0px);" title="">
						</span>
					</p></td>
			</tr>
		</tbody>
	</table>
';

// adesso viene il momento di produrre la pagina o il pdf
$pagina .= '<html><head>
<link rel="icon" href="'.$__application_base_path.'/ore-32.png" />
<link rel="stylesheet" href="'.$__application_base_path.'/css/releaseversion.css?v='. $__software_version.'">
<link rel="stylesheet" href="'.$__application_base_path.'/css/template-nomina.css?v='. $__software_version.'">';

$pagina .= '<title>' .$title . '</title>';
$pagina .='<meta content="text/html; charset=UTF-8" http-equiv="content-type"><style>';
$pagina .= $cssFileContent;
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

		// TODO: magari from il docente collegato?
		$sender = getSettingsValue('local', 'emailNoReplyFrom', '');
		$mail->setFrom($sender, 'no reply');
		$mail->addAddress($docenteEmail, $docenteNomeCognome);

		// cc to ufficio viaggi
		$cc = getSettingsValue('local', 'emailUfficioViaggi', '');
		if ($cc != '') {
			$mail->AddCC($cc, 'Ufficio Viaggi');
		}

		$connection = ($__settings->system->https)? 'https' : 'http';
		$url = "$connection://$_SERVER[HTTP_HOST]".$__application_base_path . '/index.php';

		// subject
		$mail->Subject = 'Incarico '.$tipoViaggio.' a '.$destinazione.' del '.$dataPartenza;
		// il testo del messaggio in html
		$html_msg = '<html><body>Gentile '.$docenteNomeCognome.'<p>in data '.$dataNomina.' il Dirigente Scolastico le ha conferito l&rsquo;incarico di accompagnatore degli studenti
			durante '.$tipoViaggio.' a <b>'.$destinazione.'</b> del giorno <b>'.$dataPartenza.'</b></p>
			<p>La preghiamo di confermare al pi&ugrave; presto la sua disponibilit&agrave; confermando sul sito di <a href=\''.$url.'\'>accettare l&rsquo;incarico</a></p><p>' . $__settings->name . ' ' . $__settings->local->nomeIstituto . '</p></body></html>';

		$mail->isHTML(TRUE);
		$mail->Body = $html_msg;
		$mail->AltBody = "Gentile $docenteNomeCognome, il DS ti ha conferito l'incarico per il viaggio a ".$destinazione." del giorno ".$dataPartenza;

		// allega il pdf
		$encoding = 'base64';
		$type = 'application/pdf';
		$mail->AddStringAttachment($outputPdf,$pdfFileName,$encoding,$type);

		$message = "Invio email incarico a $docenteNomeCognome viaggio a $destinazione del $dataPartenza: ";

		if(!$mail->send()){
			$message .= "errore nell'invio del messaggio. Errore: ".$mail->ErrorInfo;
			warning($message);
			echo $message;
		} else {
			$message .= "email inviata correttamente.";
			info($message);
			echo "<script>window.close();</script>";
			// marca che e' stato notificato (non necessario per i viaggi?)
		}
	} else if ($print) {
		// invia il pdf al browser che fa partire il download in automatico
		$dompdf->stream($pdfFileName);
	}
}
?>