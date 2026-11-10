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
ruoloRichiesto('docente','dirigente','segreteria-docenti','segreteria-didattica');
require_once '../common/dompdf/autoload.inc.php';

use Dompdf\Dompdf;

if(! isset($_GET)) {
	return;
} else {
	$studente_corso_id = $_GET['id'];
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

function printableVoto($voto) {
	if ($voto != 0) {
		// 1 vuole dire che non c'era
		if ($voto == 1) {
			$voto = 'Assente';
		}
		// 2 che non ha voluto farlo (eg non ha presentato la richiesta)
		if ($voto == 2) {
			$voto = 'Non Richiesto';
		}
		return $voto;
	}
	return null;
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

// contenuto della pagina
$data = '';

$query = "
			SELECT studente_per_corso_di_recupero.id AS studente_per_corso_di_recupero_id,
				studente_per_corso_di_recupero.*,
				docente_voto_settembre.id AS docente_voto_settembre_id,
				docente_voto_settembre.nome AS docente_voto_settembre_nome,
				docente_voto_settembre.cognome AS docente_voto_settembre_cognome,
				docente_voto_novembre.id AS docente_voto_novembre_id,
				docente_voto_novembre.nome AS docente_voto_novembre_nome,
				docente_voto_novembre.cognome AS docente_voto_novembre_cognome,
				materia.nome AS materia_nome

			FROM
				`studente_per_corso_di_recupero` AS studente_per_corso_di_recupero

			LEFT JOIN docente as docente_voto_settembre
			ON docente_voto_settembre.id = studente_per_corso_di_recupero.docente_voto_settembre_id

			LEFT JOIN docente as docente_voto_novembre
			ON docente_voto_novembre.id = studente_per_corso_di_recupero.docente_voto_novembre_id

			INNER JOIN corso_di_recupero corso_di_recupero
			ON studente_per_corso_di_recupero.corso_di_recupero_id = corso_di_recupero.id
			INNER JOIN materia materia
			ON corso_di_recupero.materia_id = materia.id

			WHERE studente_per_corso_di_recupero.id=$studente_corso_id";

$studente_corso = dbGetFirst($query);

$studenteId = $studente_corso['studente_per_corso_di_recupero_id'];
$studenteEmail = $studente_corso['email'];
$studenteNomeCognome = $studente_corso['nome'] . ' ' . $studente_corso['cognome'];
$materiaNome = $studente_corso['materia_nome'];

$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
$dataLettera = utf8_encode( strftime("%d %B %Y", strtotime('today GMT')));
setlocale(LC_TIME, $oldLocale);

// calcola il voto (controlla se passato a settembre o novembre)
$voto = $studente_corso['voto_settembre'];
$data_voto = $studente_corso['data_voto_settembre'];
if ($studente_corso['voto_novembre'] > 0) {
	$voto = $studente_corso['voto_novembre'];
	$data_voto = $studente_corso['data_voto_novembre'];
}
$luogoIstituto = $__settings->local->luogoIstituto;
$superata = ($voto >= 6)? '<span class="c39" style="color:#08661a;">superata</span>' : '<span class="c39" style="color:#cc0000;">NON superata</span>';

$title = $studente_corso['cognome'] . ' ' . $studente_corso['nome'] . ' - Lettera Carenza ' . $materiaNome;

$annoScorso = dbGetValue("SELECT anno FROM anno_scolastico WHERE id=".$__anno_scolastico_scorso_id);

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
	<p class="c12">
		<span class="c9 c20">COMUNICAZIONE ESITO VERIFICA CARENZA</br>RELATIVA ALLO SCRUTINIO a.s. '.$annoScorso.'</span>
	</p>
	</br>
	<p class="c12">
		<span class="c9 c20">II sessione di recupero</span>
	</p>
	</br>
	<table class="c11">
		<tbody>
			<tr class="c18">
				<td class="c2" colspan="1" rowspan="1"><p class="c6 c4 c21">'.$luogoIstituto.', '.$dataLettera.'
					</p></td>
				<td class="c2" colspan="1" rowspan="1">
					<p class="c6 c14">
						<span class="c4 c21">Ai genitori dell&#39;alunno/a</span>
					</p>
				</td>
			</tr>
			<tr class="c18">
				<td class="c2" colspan="1" rowspan="1"><p class="c6">
					</p></td>
				<td class="c2" colspan="1" rowspan="1">
					<p class="c6 c14">
						<span class="c4 c21"><strong>'.$studente_corso['cognome'] . " " . $studente_corso['nome'].'</strong> - via mail</span>
					</p>
				</td>
			</tr>
		</tbody>
	</table>
	</br>

<ul class="trattino">
  <li>Visti gli esiti dello scrutinio finale di giugno 2026</li>
  <li>considerata la presenza della carenza formativa in '.$materiaNome.'</li>
  <li>visti gli atti a disposizione dell&#39;Amministrazione relativi a percorsi di recupero e prova di verifica</li>
  <li>visto l&#39;esito della prova di verifica di data '.printableDate($data_voto).'</li>
</ul>
<p class="c3">si comunica che nel corso della II sessione di recupero si &egrave; registrato il seguente esito
</p>
</br>
<table class="c31">
	<tbody>
		<tr class="c33">
			<td class="c34" colspan="1" rowspan="1">
				<p class="c32"><span class="c38">Materia</span></p>
			</td>
			<td class="c35" colspan="1" rowspan="1">
				<p class="c32"><span class="c38">Data</span></p>
			</td>
			<td class="c36" colspan="1" rowspan="1">
				<p class="c32 c100C"><span class="c38">Esito</span></p>
			</td>
			<td class="c37" colspan="1" rowspan="1">
				<p class="c32 c100C"><span class="c38">Voto</span></p>
			</td>
		</tr>
		<tr class="c33">
			<td class="c34" colspan="1" rowspan="1">
				<p class="c40"><span class="c39">'.$materiaNome.'</span></p>
			</td>
			<td class="c35" colspan="1" rowspan="1">
				<p class="c40"><span class="c39">'.printableDate($data_voto).'</span></p>
			</td>
			<td class="c36" colspan="1" rowspan="1">
				<p class="c40 c100C">'.$superata.'</p>
			</td>
			<td class="c37 colspan="1" rowspan="1">
				<p class="c40 c100C"><span class="c39">'.printableVoto($voto).'</span></p>
			</td>
		</tr>
	</tbody>
</table>
</br>';

// messaggio se non superata
if ($voto < 6) {
	$data .= '<p class="c3">Si segnala che, come previsto dal <a href="https://www.martinomartini.eu/documento/regolamento-di-valutazione/">Regolamento di Valutazione</a>, articolo 9, <q style="font-style: italic;">[...]
	Le carenze formative relative all&#39;anno scolastico precedente e non recuperate costituiscono,
	per l&#39;anno scolastico in corso, una ulteriore carenza formativa, col voto deliberato dal consiglio di classe in sede di valutazione finale al termine dell&#39;anno scolastico precedente.
	Le carenze riferite a discipline che si interrompono al termine del biennio sono ugualmente considerate come insufficienza
	fino al termine della classe terza (questo criterio sarà adottato con decorrenza da a.s. 2021/2022).</q>
	</p></br>
	<p class="c3">Delucidazioni e chiarimenti possono essere forniti dal coordinatore di classe e/o dal docente che ha somministrato la prova di recupero.</p></br>
	<p class="c3">Cordiali saluti</p></br>';
}

$data .= '
<p class="c3">
</p>
</br>
	<table class="c11 c1 c21">
		<tbody>
			<tr class="c18">
				<td class="c2" colspan="1" rowspan="1"><p class="c17">
					</p></td>
				<td class="c2" colspan="1" rowspan="1">Il coordinatore del Consiglio di Classe
				</td>
			</tr>
			<tr class="c18">
				<td class="c2" colspan="1" rowspan="1"><p class="c17">
					</p></td>
				<td class="c2" colspan="1" rowspan="1">'.(getSettingsValue('corsiDiRecupero','corsiDiRecuperoFirmaDocente', true)? $__docente_nome . ' ' . $__docente_cognome : ' ').'
				</td>
			</tr>
		</tbody>
    </table>
</br>
';

// a novembre non serve la ricevuta da parte della famiglia

// a novembre non si puo' piu' chiedere di rifare l'esame

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
		$mail->addAddress($studenteEmail, $studenteNomeCognome);

		// cc to carenze
		$cc = getRealEmailAddress("@carenze repository");
		if ($cc != '') {
			$mail->AddCC($cc, 'Carenze Repository');
		}

		// subject
		$mail->Subject = "$studenteNomeCognome: Risultato del Corso di Recupero di $materiaNome";

		$mail->isHTML(TRUE);
		$mail->Body = "<html><body><p><strong>Carenza di $materiaNome</strong></p><p>Gentile $studenteNomeCognome, allegato troverai il risultato del Corso di Recupero di $materiaNome</p></body></html>";
		$mail->AltBody = "Gentile $studenteNomeCognome, allegato troverai il risultato del Corso di Recupero di $materiaNome";

		// allega il pdf
		$encoding = 'base64';
		$type = 'application/pdf';
		$mail->AddStringAttachment($outputPdf,$pdfFileName,$encoding,$type);

		// send the message
		if(!$mail->send()){
			echo 'Message could not be sent.';
			echo 'Mailer Error: ' . $mail->ErrorInfo;
		} else {
			// marca che e' stato notificato
			dbExec("UPDATE studente_per_corso_di_recupero SET voto_novembre_notificato = true WHERE id = $studenteId;");
			echo "<script>window.close();</script>";
		}
	} else if ($print) {
		// invia il pdf al browser che fa partire il download in automatico
		$dompdf->stream($pdfFileName);
	}
}
?>