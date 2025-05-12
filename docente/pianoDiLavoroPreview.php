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

if(! isset($_GET)) {
	return;
} else {
	$piano_di_lavoro_id = $_GET['piano_di_lavoro_id'];
	// controlla se e' richiesta la stampa
	if(isset($_GET['print'])) {
		$print = true;
	} else {
		$print = false;
	}
	// controlla se e' una carenza
	if(isset($_GET['carenza'])) {
		$carenza = true;
	} else {
		$carenza = false;
	}
	// controlla se e' una carenza
	if(isset($_GET['email'])) {
		$email = true;
	} else {
		$email = false;
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
$stato = $pianoDiLavoro['stato'];
$obiettivi_minimi = $pianoDiLavoro['obiettivi_minimi'];

// controllo lo stato
$statoMarker = '';
if ($stato == 'draft') {
	$statoMarker .= '<span class="label label-warning">draft</span>';
} elseif ($stato == 'annullato') {
	$statoMarker .= '<span class="label label-danger">annullato</span>';
} elseif ($stato == 'finale') {
	$statoMarker .= '<span class="label label-success">finale</span>';
} elseif ($stato == 'pubblicato') {
	$statoMarker .= '<span class="label label-info">pubblicato</span>';
}

// controlla se e' un template
$templateMarker = ($pianoDiLavoro['template'] == true)? '<span class="label label-success">'.getLabel('Template').'</span>' : '';

// controlla se e' un clil
$clilMarker = ($pianoDiLavoro['clil'] == true)? '<span class="label label-info">Clil</span>' : '';

// se e' una carenza, legge i dati dello studente
$studenteNomeCognome = '';
$studenteEmail = '';
$studenteClasse = '';
if ($carenza) {
	$studente_id = $pianoDiLavoro['studente_id'];
	if ($studente_id != null) {
		$studente = dbGetFirst("SELECT * FROM studente WHERE id = $studente_id");
		if ($studente != null) {
			$studenteNomeCognome = $studente['nome'] . ' ' . $studente['cognome'] ;
			$studenteEmail = $studente['email'] ;
			$studenteClasse = $studente['classe'] ;
		}
	}
}

// aggiunge nella pagina il titolo e gli stili
if ($carenza) {
	$pagina .= '<title>Carenza  ' . $studenteNomeCognome .' - ' . $materiaNome . ' - ' . $annoScolasticoNome . '</title>';
} else if ($obiettivi_minimi) {
	$pagina .= '<title>Obiettivi Minimi  ' . $nomeClasse .' - ' . $annoScolasticoNome . '</title>';
} else {
	$pagina .= '<title>Piano di Lavoro  ' . $nomeClasse .' - ' . $annoScolasticoNome . '</title>';
}
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

	.label {
        box-sizing: border-box;
    	padding: 0.2em 0.6em 0.2em;
    	border-radius: 0.25em;
        font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;
        font-weight: 700;
        font-size: 75%;
        vertical-align: baseline;
        color: white;
    }
	.label-success {
        background-color: #5cb85c;
    }
	.label-info {
        background-color: #5bc0de;
    }
	.label-warning {
        background-color: #eea236;
    }
	.label-danger {
        background-color: #d9534f;
    }
    .icon-play{
        background-image : url("../img/pdf-256.png");
        background-size: cover;
        display: inline-block;
        height: 24px;
        width: 24px;
    }

	.btn_print {
        box-sizing: border-box;
    	padding: 0.2em 0.6em 0.2em;
    	border-radius: 0.25em;
        font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;
        font-weight: 700;
        font-size: 75%;
        vertical-align: center;
		background-color: #4c3635;
        color: white;
		align-items: center;
		display: inline-flex;
    }

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
		<button onclick="pianoDiLavoroSavePdf('.$piano_di_lavoro_id.')" class="btn btn-orange4 btn-xs btn_print"><i class="icon-play"></i>&nbsp;Scarica il pdf</button>
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

// prima table: solo il titolo centrato
$pagina .= '
	<table style="width: 100%; border-collapse: collapse; border-style: none; border=0">
	<tbody>
	<tr>
	<td style="width: 18%;">
	</td>
	<td style="width: 64%;">
	<h1 style="text-align: center;">';

	// carenza o piano di lavoro: se pubblicato 'Programma effettivamente Svolto'
if ($carenza) {
	$pagina .= $studenteNomeCognome ;
} else if($obiettivi_minimi) {
	$pagina .= 'Obiettivi Minimi';
} else if($stato == 'pubblicato') {
	$pagina .= 'Programma Svolto';
} else {
	$pagina .= 'Piano di lavoro';
}

$pagina .= '</h1>
	</td>
	<td style="width: 18%;text-align: right;"><h3 style="text-align: right;">'.$studenteClasse.'</h3>
	</td>
	</tr>
	</tbody>
	</table>';

// seconda table: classe - materia - anno
$pagina .= '
	<table style="width: 100%; border-collapse: collapse; border-style: none; border=0">
	<tbody>
	<tr>
	<td style="width: 18%;">
	<h3 style="text-align: left;"><strong>'.($carenza? 'Recupero' : $nomeClasse).'</strong></h3>
	</td>
	<td style="width: 64%;">
	<h2 style="text-align: center;"><strong>'.$materiaNome.'</strong></h2>
	</td>
	<td style="width: 18%;">
	<h3 style="text-align: right;"><strong>'.$annoScolasticoNome.'</strong></h3>
	</td>
	</tr>
	</tbody>
	</table>';

// terza table: unused - docente/template - stato
$pagina .= '
	<table style="width: 100%; border-collapse: collapse; border-style: none; border=0">
	<tbody>
	<tr>
	<td style="width: 18%;">
	<h3 style="text-align: left;"><strong>'.' '.$clilMarker.'</strong></h3>
	</td>
	<td style="width: 64%;text-align: center;">';
if ($pianoDiLavoro['template'] == true) {
	$pagina .= $templateMarker;
} else {
	$pagina .= '<p style="text-align: center;">Docente: '.$nomeCognomeDocente.'</p>';
}
$pagina .= '
	</td>
	<td style="width: 18%;text-align: right;">';
if (!$carenza) {
	$pagina .= 'Stato: ' . $statoMarker . '';
}
$pagina .= '
	</td>
	</tr>
	</tbody>
	</table>';

$pagina .= '
	<p>&nbsp;</p>';

// nelle carenze inseriamo qui le indicazioni di studio che si trovano registrate nelle note aggiuntive
if ($carenza) {
	if (! empty($note_aggiuntive)) {
		$data = '';
		$data .= '
			<div style="page-break-inside: avoid">
			<hr>
			<h2 style="text-align: center;">INDICAZIONI DI STUDIO</h2>'.$note_aggiuntive.'<p>&nbsp;</p>
			</div>';

		$pagina .= $data;
	}
}

if (getSettingsValue('pianiDiLavoro','competenze', true)) {
	$pagina .= '
		<hr>
		<h2 style="text-align: center;">COMPETENZE</h2>'.$competenze.'<p>&nbsp;</p>';
}

// evita un page break in mezzo ad un blocco
$pagina .= '<div style="page-break-inside: avoid">';
$pagina .= '
	<hr>
	<h2 style="text-align: center;">MODULI</h2>
	<p>&nbsp;</p>';

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

	if (false) {
		// elimina eventuali tag con decoratori inseriti da ms-word o simile che creano problemi in generazione del pdf
		// TODO: andrebbe spostato in un posto dove il testo viene salvato, in modo però che venga poi riletto per visualizzare quello effettivamente salvato

		// elimina tutti gli span
		$piano_di_lavoro_contenuto_testo = preg_replace('|<span.*?>|s', '', $piano_di_lavoro_contenuto_testo);
		$piano_di_lavoro_contenuto_testo = preg_replace('|</span>|s', '', $piano_di_lavoro_contenuto_testo);

		// elimina i fastidiosi o:p
		$piano_di_lavoro_contenuto_testo = preg_replace('|<o:p>|s', '', $piano_di_lavoro_contenuto_testo);
		$piano_di_lavoro_contenuto_testo = preg_replace('|</o:p>|s', '', $piano_di_lavoro_contenuto_testo);

		// elimina tutti i decorator dei tag p, dei tag ul, dei tag li
		$piano_di_lavoro_contenuto_testo = preg_replace('|<p .*?>|s', '<p>', $piano_di_lavoro_contenuto_testo);
		$piano_di_lavoro_contenuto_testo = preg_replace('|<ul .*?>|s', '<ul>', $piano_di_lavoro_contenuto_testo);
		$piano_di_lavoro_contenuto_testo = preg_replace('|<li .*?>|s', '<li>', $piano_di_lavoro_contenuto_testo);
	}

	if (false) {
		// qyesto meccanismo obsoleto utilizzava una tabella, che tuttavia creava problemi nella realizzazione del pdf se si espandeva su più di una pagina
		$data .= '
			<table style="border-collapse: collapse; width: 100%; border=1">
			<tbody>
			<tr>
			<td style="width: 6%;text-align: center;">
			<h2 class="unita_titolo">&nbsp;'.$piano_di_lavoro_contenuto_posizione.'.</h2>
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
			</table>';

	} else {
		$data .= '
			<h2 class="unita_titolo">&nbsp;'.$piano_di_lavoro_contenuto_posizione.'.&nbsp;'.$piano_di_lavoro_contenuto_titolo.'</h2>'.
			'<div style="padding-left: 30px;">'.
			$piano_di_lavoro_contenuto_testo.
			'</div>';

	}

	// chiude il div del page break avoid
	$data .= '</div>';
	$data .= '<div style="page-break-inside: auto">';
	$data .= '<p>&nbsp;</p>';

	// qui la pagina potrebbe anche finire
	$data .= '</div>';

	// evita un page break in mezzo ad un blocco
	$data .= '<div style="page-break-inside: avoid">';
}

// chiudo l'ultimo div del page break perché qui si potrebbe anche interrompere la pagina
$data .= '</div>';

$pagina .= $data;

// le metodologie se presenti
if (getSettingsValue('pianiDiLavoro','metodologie', true)) {
	$data = '';
	$metodologieList = dbGetAll("SELECT * FROM piano_di_lavoro_metodologia INNER JOIN piano_di_lavoro_usa_metodologia ON piano_di_lavoro_metodologia.id = piano_di_lavoro_usa_metodologia.piano_di_lavoro_metodologia_id WHERE piano_di_lavoro_id = $piano_di_lavoro_id ;");
	if (! empty ($metodologieList)) {
		$data .= '<div style="page-break-inside: avoid">
			<hr>
			<h2 style="text-align: center;">METODOLOGIE</h2>';

		if (getSettingsValue('pianiDiLavoro','stampaEstesa', false)) {
			$data .= '<table style="border-collapse: collapse; width: 100%;"><tbody>';
			foreach($metodologieList as $metodologia) {
				$data .= '<tr padding-top: 50px;>
					<td style="width: 25%; padding-top: 4px; padding-bottom: 20px; text-align: right; padding-right: 30px; vertical-align: top;"><span class="nome">'.$metodologia['nome'].'</span></td>
					<td style="width: 75%; padding-top: 0px; padding-bottom: 20px; vertical-align: top;">'.$metodologia['descrizione'].'</td>
					</tr>';
			}
			$data .= '</tbody></table>';
		} else {
			$data .= '<span class="nomeSemplice">';
			$firstElement = true;
			foreach($metodologieList as $metodologia) {
				if (!$firstElement) {
					$data .= ', ';
				}
				$data .= $metodologia['nome'];
				$firstElement = false;
			}
			$data .= '</span>';
		}

		$data .= '</br></div>';

		$pagina .= $data;
	}
}

// i materiali se presenti
if (getSettingsValue('pianiDiLavoro','materiali', true)) {
	$data = '';
	$materialiList = dbGetAll("SELECT * FROM piano_di_lavoro_materiale INNER JOIN piano_di_lavoro_usa_materiale ON piano_di_lavoro_materiale.id = piano_di_lavoro_usa_materiale.piano_di_lavoro_materiale_id WHERE piano_di_lavoro_id = $piano_di_lavoro_id ;");
	if (! empty ($materialiList)) {
		$data .= '<div style="page-break-inside: avoid">
			<hr>
			<h2 style="text-align: center;">MATERIALI</h2>';

			if (getSettingsValue('pianiDiLavoro','stampaEstesa', false)) {
				$data .= '<table style="border-collapse: collapse; width: 100%;"><tbody>';
				foreach($materialiList as $materiale) {
					$data .= '<tr padding-top: 50px;>
						<td style="width: 25%; padding-top: 4px; padding-bottom: 20px; text-align: right; padding-right: 30px; vertical-align: top;"><span class="nome">'.$materiale['nome'].'</span></td>
						<td style="width: 75%; padding-top: 0px; padding-bottom: 20px; vertical-align: top;">'.$materiale['descrizione'].'</td>
						</tr>';
				}
				$data .= '</tbody></table>';
			} else {
				$data .= '<span class="nomeSemplice">';
				$firstElement = true;
				foreach($materialiList as $materiale) {
					if (!$firstElement) {
						$data .= ', ';
					}
					$data .= $materiale['nome'];
					$firstElement = false;
				}
				$data .= '</span>';
			}

	
		$data .= '</br></div>';

		$pagina .= $data;
	}
}

// TIC se presenti
if (getSettingsValue('pianiDiLavoro','tic', true)) {
	$data = '';
	$ticList = dbGetAll("SELECT * FROM piano_di_lavoro_tic INNER JOIN piano_di_lavoro_usa_tic ON piano_di_lavoro_tic.id = piano_di_lavoro_usa_tic.piano_di_lavoro_tic_id WHERE piano_di_lavoro_id = $piano_di_lavoro_id ;");
	if (! empty ($ticList)) {
		$data .= '<div style="page-break-inside: avoid">
			<hr>
			<h2 style="text-align: center;">TIC</h2>';

		if (getSettingsValue('pianiDiLavoro','stampaEstesa', false)) {
			$data .= '<table style="border-collapse: collapse; width: 100%;"><tbody>';
			foreach($ticList as $tic) {
				$data .= '
					<tr padding-top: 50px;>
					<td style="width: 25%; padding-top: 4px; padding-bottom: 20px; text-align: right; padding-right: 30px; vertical-align: top;"><span class="nome">'.$tic['nome'].'</span></td>
					<td style="width: 75%; padding-top: 0px; padding-bottom: 20px; vertical-align: top;">'.$tic['descrizione'].'</td>
					</tr>';
			}
			$data .= '</tbody></table>';
		} else {
			$data .= '<span class="nomeSemplice">';
			$firstElement = true;
			foreach($ticList as $tic) {
				if (!$firstElement) {
					$data .= ', ';
				}
				$data .= $tic['nome'];
				$firstElement = false;
			}
			$data .= '</span>';
		}

		$data .= '</br></div>';

		$pagina .= $data;
	}
}

// note aggiuntive se presenti e se non e' una carenze (nelle carenze si usano per le indicazioni di studio)
if (getSettingsValue('pianiDiLavoro','note_aggiuntive', true) && !$carenza) {
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
 
	// configura i parametri
	$dompdf->setPaper('A4', 'portrait');
	
	// Render html in pdf
	$dompdf->render();

	// produce il nome del file
	$annoStampabile = str_replace('/','-',$annoScolasticoNome);
	if (! $carenza) {
		$pdfFileName = "Piano di Lavoro $materiaNome - $nomeClasse - $annoStampabile.pdf";
	} else {
		$pdfFileName = "Recupero di $materiaNome - $studenteNomeCognome $studenteClasse - $annoStampabile.pdf";
	}

	// richiesta di invio di email
	if ($email) {
		// produce il pdf da inviare
		$outputPdf = $dompdf->output();
		
		$mail = new PHPMailer(true);

		$sender = getSettingsValue('local', 'emailNoReplyFrom', '');
		$mail->setFrom($sender, 'no reply');
		$mail->addAddress($studenteEmail, $studenteNomeCognome);

		// cc to carenze
		$cc = getSettingsValue('local', 'emailCarenze', '');
		if ($cc != '') {
			$mail->AddCC($cc, 'Carenze Repository');
		}

		// subject
		$mail->Subject = "$studenteNomeCognome: Recupero di $materiaNome";

		$mail->isHTML(TRUE);
		$mail->Body = "<html><body><p><strong>Carenza di $materiaNome</strong></p><p>Gentile $studenteNomeCognome, allegato troverai il programma di $materiaNome</p></body></html>";
		$mail->AltBody = "Gentile $studenteNomeCognome, allegato troverai il programma di $materiaNome";

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
			dbExec("UPDATE piano_di_lavoro SET stato = 'notificato' WHERE id = $piano_di_lavoro_id;");

			echo "<script>window.close();</script>";
		}
	} else if ($print) {
		// invia il pdf al browser che fa partire il download in automatico
		$dompdf->stream($pdfFileName);
	}
}
?>