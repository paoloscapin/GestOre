<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

$pagina = '';

require_once '../common/checkSession.php';
ruoloRichiesto('docente','dirigente','segreteria-docenti');
require_once '../common/connect.php';
require_once '../common/dompdf/autoload.inc.php';

use Dompdf\Dompdf;

if(! isset($_GET)) {
	return;
} else {
	$piano_di_lavoro_id = $_GET['piano_di_lavoro_id'];
	if(isset($_GET['print'])) {
		$print = true;
	} else {
		$print = false;
	}
}

$pagina .= '<html>
<head>
<link rel="icon" href="'.$__application_base_path.'/ore-32.png" />
<link rel="stylesheet" href="'.$__application_base_path.'/css/releaseversion.css">
';

// recupera dal db i dati di questo piano di lavoro
$query = "	SELECT
				piano_di_lavoro.id AS piano_di_lavoro_id, piano_di_lavoro.*, materia.nome AS materia_nome,
				docente.id AS docente_id, docente.cognome AS docente_cognome, docente.nome AS docente_nome,
				indirizzo.nome_breve AS indirizzo_nome_breve, indirizzo.nome AS indirizzo_nome,
				anno_scolastico.anno AS anno_scolastico_anno, anno_scolastico.id AS anno_scolastico_id FROM piano_di_lavoro piano_di_lavoro
			INNER JOIN docente docente
			ON piano_di_lavoro.docente_id = docente.id
			INNER JOIN materia materia
			ON piano_di_lavoro.materia_id = materia.id
			INNER JOIN indirizzo indirizzo
			ON piano_di_lavoro.indirizzo_id = indirizzo.id
			INNER JOIN anno_scolastico anno_scolastico
			ON piano_di_lavoro.anno_scolastico_id = anno_scolastico.id
			WHERE piano_di_lavoro.id = $piano_di_lavoro_id
			";

$pianoDiLavoro = dbGetFirst($query);

$nomeClasse = $pianoDiLavoro['classe'] . $pianoDiLavoro['indirizzo_nome_breve'] . $pianoDiLavoro['sezione'];
$nomeCognomeDocente = $pianoDiLavoro['docente_nome'] . ' ' . $pianoDiLavoro['docente_cognome'];
$annoScolasticoNome = $pianoDiLavoro['anno_scolastico_anno'];
$materiaNome = $pianoDiLavoro['materia_nome'];
$competenze = $pianoDiLavoro['competenze'];
$note_aggiuntive = $pianoDiLavoro['note_aggiuntive'];

// controllo lo stato
$statoMarker = '';
if ($pianoDiLavoro['stato'] == 'draft') {
	$statoMarker .= '<span class="label label-warning">draft</span>';
} elseif ($pianoDiLavoro['stato'] == 'annullato') {
	$statoMarker .= '<span class="label label-danger">annullato</span>';
} elseif ($pianoDiLavoro['stato'] == 'finale') {
	$statoMarker .= '<span class="label label-success">finale</span>';
} elseif ($pianoDiLavoro['stato'] == 'pubblicato') {
	$statoMarker .= '<span class="label label-info">pubblicato</span>';
}

// controlla se e' un template
$templateMarker = ($pianoDiLavoro['template'] == true)? '<span class="label label-success">Template</span>' : '';

// aggiunge nella pagina il titolo e gli stili
$pagina .= '<title>Piano di Lavoro  ' . $nomeClasse.' - '. $annoScolasticoNome . '</title>';
$pagina .='
<meta content="text/html; charset=UTF-8" http-equiv="content-type">
<style>
	h1,h2,h3,h4,h5 { color: #0e2c50; font-family: Helvetica, Sans-Serif; }
	.unita_titolo { display:inline-block; vertical-align: middle; }
	.nome { text-transform:uppercase; color: #0e2c50; font-family: Helvetica, Sans-Serif; display: block; font-weight: bold; font-size: .83em; }
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
</style>
';

// lo script non deve entrare nel pdf
if (! $print) {
	$pagina .='
	<script type="text/javascript">
	window.onload = (event) => {
		console.log("ready");
		var printBtn = document.querySelector(".btn_print");
		printBtn.onclick = function(event) {
			event.preventDefault();
			window.location.search += "&print=true";
		}
	};
	</script>
	';
}

// chiude l'intestazione
$pagina .='
	</head>
	<body>
	';

// bottone di print solo se in visualizzazione
if (! $print) {
	$pagina .='
		<div class="text-center noprint" style="padding: 20px;">
			<input type="button" value="Print" class="btn btn-info btn_print">
		</div>';
}

// il resto deve entrare in entrambi i casi, pagina o pdf
$pagina .= '
	<div style="; text-align: center;">
		<span style="overflow: hidden; display: inline-block; margin: 0.00px 0.00px; border: 0.00px solid #000000; transform: rotate(0.00rad) translateZ(0px); -webkit-transform: rotate(0.00rad) translateZ(0px); width: 642.82px;">
			<img alt="" src="data:image/png;base64,'.base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'intestazione.png'")).'" style="width: 642.82px; margin-left: 0.00px; margin-top: 0.00px; transform: rotate(0.00rad) translateZ(0px); -webkit-transform: rotate(0.00rad) translateZ(0px);" title="">
		</span>
		<hr>
	</div>';

$pagina .= '
	<h1 style="text-align: center;">Piano di lavoro</h1>
	<table style="width: 100%; border-collapse: collapse; border-style: none; border=0">
	<tbody>
	<tr>
	<td style="width: 18%;">
	<h3 style="text-align: center;"><strong>'.$nomeClasse.'</strong></h3>
	</td>
	<td style="width: 64%;">
	<h2 style="text-align: center;"><strong>'.$materiaNome.'</strong></h2>
	</td>
	<td style="width: 18%;">
	<h3 style="text-align: center;"><strong>'.$annoScolasticoNome.'</strong></h3>
	</td>
	</tr>
	</tbody>
	</table>
	<p style="text-align: center;">Docente: '.$nomeCognomeDocente.'</p>
	<p>&nbsp;</p>';

if (getSettingsValue('pianiDiLavoro','competenze', true)) {
	$pagina .= '
		<hr>
		<h2 style="text-align: center;">COMPETENZE</h2>'.$competenze.'<p>&nbsp;</p>';
}

$pagina .= '
	<hr>
	<h2 style="text-align: center;">UNIT&Agrave; DIDATTICHE</h2>
	<p>&nbsp;</p>
	';

$query = "	SELECT
				piano_di_lavoro_contenuto.id AS piano_di_lavoro_contenuto_id,
				piano_di_lavoro_contenuto.titolo AS piano_di_lavoro_contenuto_titolo,
				piano_di_lavoro_contenuto.testo AS piano_di_lavoro_contenuto_testo,
				piano_di_lavoro_contenuto.posizione AS piano_di_lavoro_contenuto_posizione
			FROM piano_di_lavoro_contenuto
			WHERE piano_di_lavoro_id = $piano_di_lavoro_id
			ORDER BY piano_di_lavoro_contenuto.posizione ASC;";

$data = '';

foreach(dbGetAll($query) as $row) {
	$piano_di_lavoro_contenuto_posizione = $row['piano_di_lavoro_contenuto_posizione'];
	$piano_di_lavoro_contenuto_titolo = $row['piano_di_lavoro_contenuto_titolo'];
	$piano_di_lavoro_contenuto_testo = $row['piano_di_lavoro_contenuto_testo'];

	// evita un page break in mezzo ad un blocco
	$data .= '<div style="page-break-inside: avoid">';

    $data .= '
		<table style="border-collapse: collapse; width: 100%; border=1">
		<tbody>
		<tr>
		<td style="width: 6%;text-align: center;">
		<h2 class="unita_titolo">&nbsp;'.$piano_di_lavoro_contenuto_posizione.'</h2>
		</td>
		<td style="width: 94%;">
		<h2 class="unita_titolo">'.$piano_di_lavoro_contenuto_titolo.'</h2>
		</td>
		</tr>
		</tbody>
		</table>
		<table style="border-collapse: collapse; width: 100%; border=0">
		<tbody>
		<tr>
		<td style="width: 6%;">&nbsp;</td>
		<td style="width: 94%;">
		'.$piano_di_lavoro_contenuto_testo.'
		</td>
		</tr>
		</tbody>
		</table>
        ';

		// chiude il div del page break avoid
		$data .= '</div>';
		$data .= '<div style="page-break-inside: auto">';
		$data .= '<p>&nbsp;</p>';
		$data .= '</div>';
	}

$pagina .= $data;

// le metodologie se presenti
if (getSettingsValue('pianiDiLavoro','metodologie', true)) {
	$data = '';
	$metodologieList = dbGetAll("SELECT * FROM piano_di_lavoro_metodologia INNER JOIN piano_di_lavoro_usa_metodologia ON piano_di_lavoro_metodologia.id = piano_di_lavoro_usa_metodologia.piano_di_lavoro_metodologia_id WHERE piano_di_lavoro_id = $piano_di_lavoro_id ;");
	if (! empty ($metodologieList)) {
		$data .= '
			<div style="page-break-inside: avoid">
			<hr>
			<h2 style="text-align: center;">METODOLOGIE</h2>
			<table style="border-collapse: collapse; width: 100%;">
			<tbody>';

		foreach($metodologieList as $metodologia) {
			$data .= '
				<tr padding-top: 50px;>
				<td style="width: 25%; padding-top: 4px; padding-bottom: 20px; text-align: right; padding-right: 30px; vertical-align: top;"><span class="nome">'.$metodologia['nome'].'</span></td>
				<td style="width: 75%; padding-top: 0px; padding-bottom: 20px; vertical-align: top;">'.$metodologia['descrizione'].'</td>
				</tr>';
		}

		$data .= '
			</tbody>
			</table>
			</br>
			</div>';

		$pagina .= $data;
	}
}

// i materiali se presenti
if (getSettingsValue('pianiDiLavoro','materiali', true)) {
	$data = '';
	$materialiList = dbGetAll("SELECT * FROM piano_di_lavoro_materiale INNER JOIN piano_di_lavoro_usa_materiale ON piano_di_lavoro_materiale.id = piano_di_lavoro_usa_materiale.piano_di_lavoro_materiale_id WHERE piano_di_lavoro_id = $piano_di_lavoro_id ;");
	if (! empty ($materialiList)) {
		$data .= '
			<div style="page-break-inside: avoid">
			<hr>
			<h2 style="text-align: center;">MATERIALI</h2>

			<table style="border-collapse: collapse; width: 100%;">
			<tbody>';

		foreach($materialiList as $materiale) {
			$data .= '
				<tr padding-top: 50px;>
				<td style="width: 25%; padding-top: 4px; padding-bottom: 20px; text-align: right; padding-right: 30px; vertical-align: top;"><span class="nome">'.$materiale['nome'].'</span></td>
				<td style="width: 75%; padding-top: 0px; padding-bottom: 20px; vertical-align: top;">'.$materiale['descrizione'].'</td>
				</tr>';
		}


		$data .= '
			</tbody>
			</table>
			</br>
			</div>';

		$pagina .= $data;
	}
}

// TIC se presenti
if (getSettingsValue('pianiDiLavoro','tic', true)) {
	$data = '';
	$ticList = dbGetAll("SELECT * FROM piano_di_lavoro_tic INNER JOIN piano_di_lavoro_usa_tic ON piano_di_lavoro_tic.id = piano_di_lavoro_usa_tic.piano_di_lavoro_tic_id WHERE piano_di_lavoro_id = $piano_di_lavoro_id ;");
	if (! empty ($ticList)) {
		$data .= '
			<div style="page-break-inside: avoid">
			<hr>
			<h2 style="text-align: center;">TIC</h2>

			<table style="border-collapse: collapse; width: 100%;">
			<tbody>';

		foreach($ticList as $tic) {
			$data .= '
				<tr padding-top: 50px;>
				<td style="width: 25%; padding-top: 4px; padding-bottom: 20px; text-align: right; padding-right: 30px; vertical-align: top;"><span class="nome">'.$tic['nome'].'</span></td>
				<td style="width: 75%; padding-top: 0px; padding-bottom: 20px; vertical-align: top;">'.$tic['descrizione'].'</td>
				</tr>';
		}


		$data .= '
			</tbody>
			</table>
			</br>
			</div>';

		$pagina .= $data;
	}
}

// note aggiuntive se presenti
if (getSettingsValue('pianiDiLavoro','note_aggiuntive', true)) {
	if (! empty($note_aggiuntive)) {
		$data = '';
		$data .= '
			<div style="page-break-inside: avoid">
			<hr>
			<h2 style="text-align: center;">NOTE AGGIUNTIVE</h2>'.$note_aggiuntive.'<p>&nbsp;</p>
			</div>';

		$pagina .= $data;
	}
}

// chiude la pagina
$pagina .= '</body></html>';

// decide se visualizzarla o inviarla a pdf
if (! $print) {
	echo $pagina;
} else {
	$dompdf = new Dompdf();
	$dompdf->loadHtml($pagina);
 
	// (Optional) Setup the paper size and orientation
	$dompdf->setPaper('A4', 'portrait');
	
	// Render the HTML as PDF
	$dompdf->render();
	
	// Output the generated PDF to Browser
	$annoStampabile = str_replace('/','-',$annoScolasticoNome);
	$dompdf->stream("Piano di Lavoro $materiaNome - $nomeClasse - $annoStampabile.pdf");
}
?>