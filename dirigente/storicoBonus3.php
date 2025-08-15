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
ruoloRichiesto('dirigente');
require_once '../common/dompdf/autoload.inc.php';

use Dompdf\Dompdf;

if(! isset($_GET)) {
	return;
} else {
	$anno_id = $_GET['anno_id'];
	// controlla se e' richiesta la stampa
	if(isset($_GET['print'])) {
		$print = true;
	} else {
		$print = false;
	}
}

function formatNoZero($value) {
    return ($value != 0) ? number_format($value,2) : ' ';
}

function formatNoZeroNoDecimal($value) {
    return ($value != 0) ? number_format($value,0) : ' ';
}

// il processo potrebbe esssere molto lungo, specialmente in fase di stampa
set_time_limit(0);

// calcola il totale degli assegnati
$totale_bonus_assegnato = dbGetValue("SELECT SUM(importo) FROM `bonus_assegnato` WHERE anno_scolastico_id = $anno_id;");
debug('totale_bonus_assegnato=' . $totale_bonus_assegnato);

// calcola il totale in punti finora approvati
if (getSettingsValue('bonus','punteggio_variabile', false)) {
    $query = "SELECT COALESCE(SUM(approvato), 0) FROM bonus LEFT JOIN bonus_docente ON bonus.id = bonus_docente.bonus_id WHERE anno_scolastico_id = $__anno_scolastico_corrente_id;";
} else {
    $query = "SELECT SUM(valore_previsto) FROM bonus LEFT JOIN bonus_docente ON bonus.id = bonus_docente.bonus_id WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND approvato is true;";
}
$totale_valore_approvato = dbGetValue($query);
debug('totale_valore_approvato=' . $totale_valore_approvato);

// importo totale disponibile per il bonus
$importo_totale_bonus = $__importo_bonus;
debug('importo_totale_bonus=' . $importo_totale_bonus);

// quello che non e' stato ancora assegnato resta da dividere tra quelli approvati
$importo_totale_bonus_approvato = $importo_totale_bonus - $totale_bonus_assegnato;
debug('importo_totale_bonus_approvato=' . $importo_totale_bonus_approvato);
if ($totale_valore_approvato != 0) {
    $importo_per_punto = $importo_totale_bonus_approvato / $totale_valore_approvato;
} else {
    $importo_per_punto = 0;
}
debug('importo_per_punto=' . $importo_per_punto);

// Intestazione pagina
$data = '';
$dataCopertina = '';
$dataConsuntivo = '';

// azzera i totali di istituto
$totaleAssegnatoIstuto = 0;
$totaleApprovatoIstuto = 0;

// cicla i docenti
foreach(dbGetAll("SELECT * FROM docente ORDER BY docente.cognome, docente.nome ASC ;") as $docente) {
	// per l'anno corrente (caso tipico) esclude i docenti che non sono attivi (per gli altri anni non si puo' sapere)
	if ($anno_id == $__anno_scolastico_corrente_id && $docente['attivo'] == 0) {
		debug('Salto il docente '.$docente['cognome'] . ' ' . $docente['nome'].' non attivo');
		continue;
	}

	$docente_id = $docente['id'];
	$totaleAssegnatoDocente = 0;
	$totaleApprovatoDocente = 0;
	
	$data .= '<h2 style="page-break-before: always;text-align: center;">'.$docente['cognome'] . ' ' . $docente['nome'].'</h2>';
	$data .= '';

	// bonus assegnato
	$assegnatoList = dbGetAll("SELECT * FROM bonus_assegnato WHERE bonus_assegnato.anno_scolastico_id = $anno_id AND bonus_assegnato.docente_id = $docente_id");
	if (!empty($assegnatoList)) {
		$data .= '<h4 style="text-align: center;background-color: #f1e6b2 !important;"><strong>Bonus Assegnato</strong></h4>';
		$data .= '<table><thead><tr><th class="col-md-11">Commento</th><th class="text-center col-md-1">Importo</th></tr></thead><tbody>';
		foreach($assegnatoList as $assegnato) {
			$data .= '<tr><td>'.$assegnato['commento'].'</td><td class="text-right funzionale">'.$assegnato['importo'].'</td></tr>';
			$totaleAssegnatoDocente = $totaleAssegnatoDocente + $assegnato['importo'];
		}
		$data .= '</tbody></table>';
		$data .= '<hr>';
	}

	// bonus
	$data .= '<h4 style="text-align: center;background-color: #9be3bf !important;"><strong>Bonus Richiesto</strong></h4>';

	$data .='<table>';
	$data .='<thead><tr><th class="text-center col-md-1">Codice</th><th class="text-center col-md-8">Descrittore</th><th class="text-center col-md-1">Valore</th><th class="text-center col-md-1">Approvato</th><th class="text-center col-md-1">Importo</th></tr>';
	$data .='</thead><tbody>';

	$query = "
	SELECT
		bonus_docente.id AS bonus_docente_id,
		bonus_docente.approvato AS bonus_docente_approvato,
		bonus_docente.rendiconto_evidenze AS bonus_docente_rendiconto_evidenze,
		bonus_docente.ultimo_controllo AS bonus_docente_ultimo_controllo,
		bonus_docente.ultima_modifica AS bonus_docente_ultima_modifica,
		
		bonus_area.codice AS bonus_area_codice,
		bonus_area.descrizione AS bonus_area_descrizione,
		bonus_area.valore_massimo AS bonus_area_valore_massimo,
		bonus_area.peso_percentuale AS bonus_area_peso_percentuale,
		
		bonus_indicatore.codice AS bonus_indicatore_codice,
		bonus_indicatore.descrizione AS bonus_indicatore_descrizione,
		bonus_indicatore.valore_massimo AS bonus_indicatore_valore_massimo,
		
		bonus.codice AS bonus_codice,
		bonus.descrittori AS bonus_descrittori,
		bonus.evidenze AS bonus_evidenze,
		bonus.valore_previsto AS bonus_valore_previsto
		
	FROM bonus_docente
	
	INNER JOIN bonus
	ON bonus_docente.bonus_id = bonus.id
	
	INNER JOIN bonus_indicatore
	ON bonus.bonus_indicatore_id = bonus_indicatore.id
	
	INNER JOIN bonus_area
	ON bonus_indicatore.bonus_area_id = bonus_area.id
	
	WHERE
		bonus_docente.docente_id = ".$docente_id."
	AND
		bonus_docente.anno_scolastico_id = $anno_id
		
	ORDER BY
		bonus.codice;
	";
	$resultArray2 = dbGetAll($query);
	foreach($resultArray2 as $bonus) {
		$bonusDocenteApprovato = $bonus['bonus_docente_approvato'];
		if ($bonusDocenteApprovato == null) {
			$bonusDocenteApprovato = 0;
		}
		if (getSettingsValue('bonus','punteggio_variabile', false)) {
			// se il punteggio e' variabile, i punti approvati sono quelli riportati nella voce approvato
			$puntiApprovati = $bonusDocenteApprovato;
		} else {
			// se il punteggio e' fisso, i punti approvati sono quelli previsti oppure zero se non approvato
			if ($bonusDocenteApprovato == 0) {
				$puntiApprovati = 0;
			} else {
				$puntiApprovati = $bonus['bonus_valore_previsto'];
			}
		}

		// calcola l'importo
		$importo = $importo_per_punto * $puntiApprovati;
		$data .= '<tr>
				<td class="text-left">'.$bonus['bonus_codice'].'</td>
				<td class="text-left">'.$bonus['bonus_descrittori'].'<hr><strong>Rendiconto:</strong></br>'.$bonus['bonus_docente_rendiconto_evidenze'].'</td>
				<td class="text-center">'.$bonus['bonus_valore_previsto'].'</td>
			';
			if (getSettingsValue('bonus','punteggio_variabile', false)) {
				$data .= '<td class="text-center">'.$bonusDocenteApprovato.'</td>';
			} else {
				if ($bonus['bonus_docente_approvato']) {
					$data .= '<td class="text-center"><input type="checkbox" checked ></td>';
				} else {
					$data .= '<td class="text-center"><input type="checkbox" ></td>';
				}
			}
			if ($bonus['bonus_docente_approvato']) {
				$data .= '<td class="text-right funzionale">'.formatNoZero($importo).'</td>';
				$totaleApprovatoDocente = $totaleApprovatoDocente + $importo;
			} else {
				$data .= '<td></td>';
			}

			$data .= '</tr>';
	}
	$data .='</tbody>';
	$data .='<tfoot>';
	$data .='<tr><td colspan="4" class="text-right"><strong>Totale approvato:</strong></td><td class="text-right funzionale"><strong>' . formatNoZero($totaleApprovatoDocente) . '</strong></td></tr>';
	$data .='</tfoot>';
	$data .='</table>';

	$totaleDocente = $totaleAssegnatoDocente + $totaleApprovatoDocente;

	$data .= '<p><strong>'.$docente['cognome'] . ' ' . $docente['nome'].': Totale da pagare = ' . number_format($totaleDocente,2) . ' €</strong></p>';
	$data .= '<hr>';

	// aggiorna i totali di istituto
	$totaleAssegnatoIstuto = $totaleAssegnatoIstuto + $totaleAssegnatoDocente;
	$totaleApprovatoIstuto = $totaleApprovatoIstuto + $totaleApprovatoDocente;
}

// serve il nome dell'anno scolastico
$nome_anno_scolastico = dbGetValue("SELECT anno FROM anno_scolastico WHERE id=$anno_id");

// stampa i totali di istituto
$dataConsuntivo .= '<hr style="page-break-before: always;">';
$dataConsuntivo .= '<h2 style="text-align: center; padding-top: 3cm; padding-bottom: 2cm;">Totale Bonus anno scolastico '.$nome_anno_scolastico.'</h2>';
$dataConsuntivo .= '<table>';

$dataConsuntivo .= '<thead><tr><th class="col-md-11 text-left">Tipo</th><th class="col-md-1 text-center">Importo</th></tr></thead><tbody>';
$dataConsuntivo .= '<tr><td class="col-md-11 text-left">Totale Bonus Assegnato</td><td class="col-md-1 text-right">' . number_format($totaleAssegnatoIstuto,2) . '</td></tr>';
$dataConsuntivo .= '<tr><td class="col-md-11 text-left">Totale Bonus Approvato</td><td class="col-md-1 text-right">' . number_format($totaleApprovatoIstuto,2) . '</td></tr>';
$dataConsuntivo .= '</tbody><tfoot>';
$dataConsuntivo .='<tr><td colspan="1" class=""><strong>Totale Spesa Bonus</strong></td><td class="text-right"><strong>' . formatNoZero($totaleAssegnatoIstuto + $totaleApprovatoIstuto) . '</strong></td></tr>';
$dataConsuntivo .='</tfoot></table>';
$dataConsuntivo .= '<hr>';

// prima pagina
$dataCopertina .= '<h2 style="text-align: center; padding-bottom: 1cm;"><img style="text-align: center;" alt="" src="data:image/png;base64,'. base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'Logo.png'")).'" title=""></h2>';
$dataCopertina .= '<h3 style="text-align: center; padding-bottom: 3cm;">'.getSettingsValue('local','nomeIstituto', '').'</h3>';
$dataCopertina .= '<h2 style="text-align: center;">Bonus Docenti anno scolastico '.$nome_anno_scolastico.'</h2>';

// titolo
$annoStampabile = str_replace('/','-',$nome_anno_scolastico);
$title = 'Storico Bonus ' . $annoStampabile.' - '.getSettingsValue('local','nomeIstituto', '');

// adesso viene il momento di produrre la pagina o il pdf
$pagina .= '<html>
<head>
<link rel="icon" href="'.$__application_base_path.'/ore-32.png" />
<link rel="stylesheet" href="'.$__application_base_path.'/css/releaseversion.css">
';

$pagina .= '<title>' .$title . '</title>';
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

	table {
		font-family: Helvetica, Sans-Serif;
		font-size: .75em;
		width: 100%;
	}

	table, td, th {
		border: 1px solid black;
		border-collapse: collapse;
		padding: 5px;
	}
	  
	.text-left {
		text-align: left;
	}
	.text-right {
		text-align: right;
	}
	.text-center {
		text-align: center;
	}

	.col-md-1 {
		width: 8%;
	}
	.col-md-2 {
		width: 17%;
	}
	.col-md-3 {
		width: 25%;
	}
	.col-md-4 {
		width: 33%;
	}
	.col-md-5 {
		width: 42%;
	}
	.col-md-6 {
		width: 50%;
	}
	.col-md-7 {
		width: 58%;
	}
	.col-md-8 {
		width: 67%;
	}
	.col-md-9 {
		width: 75%;
	}
	.col-md-11 {
		width: 92%;
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
		<button onclick="storicoBonusSavePdf('.$anno_id.')" class="btn btn-orange4 btn-xs btn_print"><i class="icon-play"></i>&nbsp;Scarica il pdf</button>
		</div>';
}

// il resto deve entrare in entrambi i casi, pagina o pdf: copertina, consuntivo, poi tutto il resto
$pagina .= $dataCopertina;
$pagina .= $dataConsuntivo;
$pagina .= $data;

// chiude la pagina
$pagina .= '</body></html>';

// decide se visualizzarla o inviarla a pdf
if (! $print) {
	echo $pagina;
} else {
	// aumenta il limite di memoria visto che ne potrebbe usare molta
	ini_set('memory_limit','1024M');

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