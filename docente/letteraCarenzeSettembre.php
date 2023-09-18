<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

$pagina = '';

require_once '../common/checkSession.php';
require_once '../common/importi_load.php';
ruoloRichiesto('docente','dirigente','segreteria-docenti');
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
}

function printableVoto($voto) {
	if ($voto != 0) {
		if ($voto == 1) {
			return 'Assente';
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
			SELECT studente_per_corso_di_recupero.*,
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

$title = $studente_corso['cognome'] . ' ' . $studente_corso['nome'] . ' - Lettera Carenza ' . $studente_corso['materia_nome'];

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
		<span class="c9 c20">COMUNICAZIONE ESITO CARENZA ANNO SCOLASTICO PRECEDENTE</span>
	</p>
	</br>
	<p class="c12">
		<span class="c9 c20">I sessione di recupero</span>
	</p>
	</br>
	<table class="c11">
		<tbody>
			<tr class="c18">
				<td class="c2" colspan="1" rowspan="1"><p class="c6 c4 c21">'.$luogoIstituto.', '.$dataLettera.'
					</p></td>
				<td class="c2" colspan="1" rowspan="1">
					<p class="c6 c14">
						<span class="c4 c21">Ai genitori dell’alunno/a</span>
					</p>
				</td>
			</tr>
			<tr class="c18">
				<td class="c2" colspan="1" rowspan="1"><p class="c6">
					</p></td>
				<td class="c2" colspan="1" rowspan="1">
					<p class="c6 c14">
						<span class="c4 c21"><strong>'.$studente_corso['cognome'] . " " . $studente_corso['nome'].'</strong></span>
					</p>
				</td>
			</tr>
		</tbody>
	</table>
	</br>
	<p class="c3">
		<span class="c1 c21">
		Il Consiglio di classe comunica che, a seguito della
		<span class="c5 c21">Prima Sessione</span>
		 di prova di recupero della carenza maturata alla fine dell&#39;anno scolastico scorso, l&#39;alunno/a ha conseguito i seguenti risultati:
		</span>
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
						<p class="c40"><span class="c39">'.$studente_corso['materia_nome'].'</span></p>
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

	</br>
	<a id="t.f727949b760321cc972232d42b2d9fa1f8785d82"></a>
	<a id="t.1"></a>
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
<div id="scissors">
    <div></div>
</div>
</br>
<p class="c3">
    <span class="c1 c21">
    Il sottoscritto _______________________________________ genitore dello studente/essa '.$studente_corso['cognome'] . " " . $studente_corso['nome'].'
    della classe ____________ <strong>dichiara</strong> di aver ricevuto in data ____________ comunicazione dell’esito della prova
    di recupero carenze di '.$studente_corso['materia_nome'].' a.s. scorso.
</p>
<p class="c3">
</br>
FIRMA (studente maggiorenne o genitore per studente minorenne)
</br>
</br>
______________________________________________________________
</p>';

if($voto < 6)
$data .= '</br>
<p class="c3">
    <span class="c1 c21">
    <strong>CHIEDE</strong> che il/la figlio/a possa sostenere un\'ulteriore verifica
	per il superamento della carenza in '.$studente_corso['materia_nome'].' entro <strong>novembre</strong>,
	da concordare con il docente della classe.
</p>
<p class="c3">
</br>
FIRMA (studente maggiorenne o genitore per studente minorenne)
</br>
</br>
______________________________________________________________
</p>';

// adesso viene il momento di produrre la pagina o il pdf
$pagina .= '<html>
<head>
<link rel="icon" href="'.$__application_base_path.'/ore-32.png" />
<link rel="stylesheet" href="'.$__application_base_path.'/css/releaseversion.css?v='. $__software_version.'">
<link rel="stylesheet" href="'.$__application_base_path.'/css/template-nomina.css?v='. $__software_version.'">
';

$pagina .= '<title>' .$title . '</title>';
$pagina .='
<meta content="text/html; charset=UTF-8" http-equiv="content-type">
<style>
';
$pagina .= $cssFileContent;
$pagina .='
</style>';

// lo script non deve entrare nel pdf
if (! $print) {
	$pagina .='
	<script type="text/javascript">
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
$pagina .='
	</head>
	<body>';

// bottone di print solo se in visualizzazione
if (! $print) {
	$pagina .='
		<div class="text-center noprint" style="text-align: center;padding: 50px;">
		<button class="btn btn-orange4 btn-xs btn_print"><i class="icon-play"></i>&nbsp;Scarica il pdf</button>
		</div>';
}

// il resto deve entrare in entrambi i casi, pagina o pdf: copertina, consuntivo, poi tutto il resto
$pagina .= $data;

// chiude la pagina
$pagina .= '</body></html>';

// decide se visualizzarla o inviarla a pdf
if (! $print) {
	echo $pagina;
} else {
	$dompdf = new Dompdf();
	$dompdf->loadHtml($pagina);
 
	// configura i parametri
	$dompdf->setPaper('A4', 'portrait');
	
	// Render html in pdf
	$dompdf->render();

	// produce il nome del file
	$pdfFileName = "$title.pdf";

	// richiesta di invio di email
	if ($print) {
		// invia il pdf al browser che fa partire il download in automatico
		$dompdf->stream($pdfFileName);
	}
}

?>