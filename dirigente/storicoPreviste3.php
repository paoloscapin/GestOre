<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

$pagina = '';

require_once '../common/checkSession.php';
// require_once '../common/__Minuti.php';
require_once '../common/importi_load.php';
ruoloRichiesto('dirigente');
require_once '../common/dompdf/autoload.inc.php';

// controlla se deve gestire i minuti
$__minuti = getSettingsValue('config','minuti', false);

// include le functions di utilita'
require_once '../common/__MinutiFunction.php';

// la funzione per il calcolo delle ore fatte
require_once '../docente/orePrevisteAggiorna.php';

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

function formatDate($value) {

	$result = strftime("%d/%m/%Y", strtotime($value));
    return $result;
}

function sostituisciParte($src, $pattern, $sostituzione) {
	return str_replace($pattern, $sostituzione, $src);
}


// il processo potrebbe esssere molto lungo, specialmente in fase di stampa
set_time_limit(0);

// Intestazione pagina
$dataContenuto = '';
$dataCopertina = '';
$dataConsuntivo = '';

// azzera i totali di istituto
$totaleAssegnatoIstuto = 0;
$totaleDiariaIstuto = 0;
$totaleAttivitaIstuto = 0;
$totaleCorsiDiRecuperoExtraIstituto = 0;

$contatoreLimite = 0;
// cicla i docenti
foreach(dbGetAll("SELECT docente.id AS docente_id, docente.* FROM docente ORDER BY docente.cognome ASC, docente.nome ASC;") as $docente) {
	if ($anno_id == $__anno_scolastico_corrente_id && $docente['attivo'] == 0) {
		debug('Salto il docente '.$docente['cognome'] . ' ' . $docente['nome'].' non attivo');
		continue;
	}

	$contatoreLimite = $contatoreLimite + 1;
	if ($contatoreLimite > 10 && $docente['docente_id'] != 120) {
		continue;
	}
	// anche se non lo salto, controllo se effettivamente ci sta qualcosa di significativo
	$significativo = false;
	$data = '';
	$docente_id = $docente['docente_id'];
	$totaleAssegnatoDocente = 0;
	$totaleDiariaDocente = 0;
	$totaleAttivitaDocente = 0;
	$totaleClilDocente = 0;
	$totaleCorsiDiRecuperoExtraDocente = 0;

    // eventuale messaggio per chiarire la compensazione effettuata
    $messaggio = "";
    $messaggioEccesso = "";

	$ultimo_controllo = '';

	// per prima cosa richiede di calcolare le ore previste
	$fuisPrevisto = orePrevisteAggiorna(false, $docente_id, 'no-operatore', $ultimo_controllo, false);
//	debug(json_encode($fuisPrevisto,JSON_PRETTY_PRINT));
	$data .= '<h2 style="page-break-before: always;text-align: center;">'.$docente['cognome'] . ' ' . $docente['nome'].'</h2>';
	$data .= '';

	// fuis assegnato
	$dataFuisAssegnato = $fuisPrevisto['dataFuisAssegnato'];
	$importoFuisAssegnato = $fuisPrevisto['importoFuisAssegnato'];

	if (strpos($dataFuisAssegnato, '<tbody></tbody>') === false) {
		$dataFuisAssegnato = sostituisciParte($dataFuisAssegnato, '<div class="table-wrapper"><table', '<table');
		$dataFuisAssegnato = sostituisciParte($dataFuisAssegnato, '</table></div>', '</table>');

		$data .= '<h4 style="text-align: center;background-color: #eacaad !important;"><strong>FUIS Assegnato</strong><span style="float: right;"><strong>'.number_format($importoFuisAssegnato,2).'</strong></span></h4>';
		$data .= $dataFuisAssegnato;
		$significativo = true;
	}

	// diarie viaggio (non le ore)
	$dataDiaria = $fuisPrevisto['dataDiaria'];
	$diariaImportoPreviste = $fuisPrevisto['diariaImportoPreviste'];

	if (strpos($dataDiaria, '<tbody></tbody>') === false) {
		// elimina l'ultima colonna e allarga materia
		$dataDiaria = sostituisciParte($dataDiaria, '<div class="table-wrapper"><table', '<table');
		$dataDiaria = sostituisciParte($dataDiaria, '</table></div>', '</table>');
		$dataDiaria = sostituisciParte($dataDiaria, '<th class="col-md-6 text-left">Descrizione</th>', '<th class="col-md-7 text-left">Descrizione</th>');
//		$dataDiaria = sostituisciParte($dataDiaria, '<th class="col-md-1 text-center">Ore</th></tr>', '</tr>');
		$dataDiaria = sostituisciParte($dataDiaria, '<td></td><td></td></tr>', '</tr>');

		$data .= '<h4 style="text-align: center;background-color: #fcaebb !important;"><strong>Diaria Viaggi</strong><span style="float: right;"><strong>'.number_format($diariaImportoPreviste,2).'</strong></span></h4>';
		$data .= $dataDiaria;
		$significativo = true;
	}

	// per i totali delle attivita' somma clil e orientamento se presenti
	$oreConStudentiTotale = $fuisPrevisto['oreConStudentiPreviste'] + $fuisPrevisto['oreClilConStudentiPreviste'] + $fuisPrevisto['oreOrientamentoConStudentiPreviste'];
	$oreFunzionaliTotale = $fuisPrevisto['oreFunzionaliPreviste'] + $fuisPrevisto['oreClilFunzionaliPreviste'] + $fuisPrevisto['oreOrientamentoFunzionaliPreviste'];
	$fuisFunzionaleTotale = $fuisPrevisto['fuisFunzionale'] + $fuisPrevisto['fuisClilFunzionalePreviste'] + $fuisPrevisto['fuisOrientamentoFunzionalePreviste'];
	$fuisConStudentiTotale = $fuisPrevisto['fuisConStudenti'] + $fuisPrevisto['fuisClilConStudentiPreviste'] + $fuisPrevisto['fuisOrientamentoConStudentiPreviste'];

	// il totale delle ore e' gia' stato calcolato
	$totaleAttivitaDocente = $fuisPrevisto['fuisOrePreviste'];

	// attivita'
	$data .= '<h4 style="text-align: center;background-color: #9be3bf !important;"><strong>Attività</strong><span style="float: right;"><strong>'.number_format($totaleAttivitaDocente,2).'</strong></span></h4>';

	// Inserite da docente
	$dataPreviste = $fuisPrevisto['dataPreviste'];

	if (strpos($dataPreviste, '<tbody></tbody>') === false) {
		// elimina l'ultima colonna e allarga il dettaglio
		$dataPreviste = sostituisciParte($dataPreviste, '<div class="table-wrapper"><table', '<table');
		$dataPreviste = sostituisciParte($dataPreviste, '</table></div>', '</table>');
		$dataPreviste = sostituisciParte($dataPreviste, '<th colspan="7"', '<th colspan="5"');
		$dataPreviste = sostituisciParte($dataPreviste, '<th class="col-md-6 text-left">Dettaglio</th>', '<th class="col-md-7 text-left">Dettaglio</th>');
		$dataPreviste = sostituisciParte($dataPreviste, '<th class="col-md-1 text-center"></th>', '');
		$dataPreviste = sostituisciParte($dataPreviste, '<td class="text-center"></td></tr>', '</tr>');
		$data .= '<h4 style="text-align: center;background-color: #97D3CF !important;">Attività previste inserite da docente</h4>';
		$data .= $dataPreviste;
		$significativo = true;
	}

	// sportelli: nelle previste sono con le attivita'
	// gruppi di lavoro (non clil): nelle previste sono con le attivita'

	// attribuite
	$dataAttribuite = $fuisPrevisto['dataAttribuite'];

	if (strpos($dataAttribuite, '<tbody></tbody>') === false) {
		// elimina l'ultima colonna e allarga Tipo
		$dataAttribuite = sostituisciParte($dataAttribuite, '<div class="table-wrapper"><table', '<table');
		$dataAttribuite = sostituisciParte($dataAttribuite, '</table></div>', '</table>');
		$dataAttribuite = sostituisciParte($dataAttribuite, '<th class="col-md-2 text-left">Tipo</th>', '<th class="col-md-3 text-left">Tipo</th>');
		$dataAttribuite = sostituisciParte($dataAttribuite, '<th class="col-md-1 text-center"></th></tr>', '</tr>');
		$dataAttribuite = sostituisciParte($dataAttribuite, '<td></td></tr>', '</tr>');

		$data .= '<h4 style="text-align: center;background-color: #CCF3DD !important;">Attribuite</h4>';
		$data .= $dataAttribuite;
		$significativo = true;
	}

	// le ore dei viaggi (che vanno con gli studenti): nelle previste sono con le attivita'

	// eventuali corsi di recupero inizio anno o in itinere
	$dataCdr = $fuisPrevisto['dataCdr'];

	if (strpos($dataCdr, '<tbody></tbody>') === false) {
		// elimina l'ultima colonna e allarga materia
		$dataCdr = sostituisciParte($dataCdr, '<div class="table-wrapper"><table', '<table');
		$dataCdr = sostituisciParte($dataCdr, '</table></div>', '</table>');
		$dataCdr = sostituisciParte($dataCdr, '<th class="col-md-5 text-left">Materia</th>', '<th class="col-md-6 text-left">Materia</th>');
		$dataCdr = sostituisciParte($dataCdr, '<th class="text-center col-md-1"></th></tr>', '</tr>');
		$dataCdr = sostituisciParte($dataCdr, '<td></td></tr>', '</tr>');

		$fuisExtraCorsiDiRecupero = $fuisPrevisto['fuisExtraCorsiDiRecupero'];
		$extraPartHeader = ($fuisExtraCorsiDiRecupero > 0) ? '<span style="float: right;">'.number_format($fuisExtraCorsiDiRecupero,2).'</span>' : '';

		$data .= '<h4 style="text-align: center;background-color: #B9E6FB !important;">Corsi di recupero'. $extraPartHeader .'</h4>';
		$data .= $dataCdr;
		$significativo = true;
	}

	// eventuali messaggi
	$messaggio = $fuisPrevisto['messaggio'];
	$messaggioEccesso = $fuisPrevisto['messaggioEccesso'];

	// le sostituzioni non rientrano nelle previste
	$oreSostituzioneBilancio = 0;
	$oreFunzionaliBilancio = $oreFunzionaliTotale - $fuisPrevisto['oreFunzionaliDovute'];
	$oreConStudentiBilancio = $oreConStudentiTotale - $fuisPrevisto['oreConStudentiDovute'];

	// scrive il prospetto riassuntivo delle attivita'
	$data .= '<h4 style="text-align: center;;background-color: #FFA98F !important;">Totale Ore attività</h4>';
	$data .= '<table>';
	$data .= '<thead><tr><th class="col-sd-2 text-left">Tipo</th><th class="col-sd-3 text-center">Dovute</th><th class="col-sd-3 text-center">Previste</th><th class="col-sd-3 text-center">Bilancio</th><th class="col-sd-1 text-center">Importo</th></tr></thead><tbody>';
	$data .= '<tr><td class="col-sd-2 text-left">sostituzioni</td><td class="col-sd-3 text-center">'.$fuisPrevisto['oreSostituzioniDovute'] . '</td><td class="col-sd-3 text-center">' . '</td><td class="col-sd-3 text-center">' . $oreSostituzioneBilancio . '</td><td class="col-sd-1 text-right">' . '' . '</td></tr>';
	$data .= '<tr><td class="col-sd-2 text-left">funzionali</td><td class="col-sd-3 text-center">'.$fuisPrevisto['oreFunzionaliDovute'] . '</td><td class="col-sd-3 text-center">' . $oreFunzionaliTotale . '</td><td class="col-sd-3 text-center">' . $oreFunzionaliBilancio . '</td><td class="col-sd-1 text-right">' . number_format($fuisFunzionaleTotale,2) . '</td></tr>';
	$data .= '<tr><td class="col-sd-2 text-left">con studenti</td><td class="col-sd-3 text-center">'.$fuisPrevisto['oreConStudentiDovute'] . '</td><td class="col-sd-3 text-center">' . $oreConStudentiTotale . '</td><td class="col-sd-3 text-center">' . $oreConStudentiBilancio . '</td><td class="col-sd-1 text-right">' . number_format($fuisConStudentiTotale,2) . '</td></tr>';
	$data .= '</tbody><tfoot>';
	// inserisce nel footer eventuali messaggi di compensazione
	if ( ! empty($messaggio)) {
		$data .='<tr><td colspan="1" class="text-left"><strong>Attenzione:</strong></td><td class="text-left" colspan="4"><strong>' . $messaggio . '</strong></td></tr>';
	}
	if ( ! empty($messaggioEccesso)) {
		$data .='<tr><td colspan="1" class="text-left"><strong>Attenzione:</strong></td><td class="text-left" colspan="4"><strong>' . $messaggioEccesso . '</strong></td></tr>';
	}
	$data .='<tr><td colspan="4" class="text-right"><strong>Totale:</strong></td><td class="text-right funzionale"><strong>' . number_format($totaleAttivitaDocente,2) . '</strong></td></tr>';
	$data .='</tfoot></table>';
	$data .= '<hr>';

	// calcola i totali per questo docente
	$totaleAssegnatoDocente = $fuisPrevisto['importoFuisAssegnato'];
	$totaleDiariaDocente = $fuisPrevisto['diariaImportoPreviste'];
	$totaleCorsiDiRecuperoExtraDocente = $fuisPrevisto['fuisExtraCorsiDiRecupero'];
	$totaleAttivitaDocente = $fuisPrevisto['fuisOrePreviste'];
	// aggiunge eventuali clil e orientamento
	$totaleAttivitaDocente = $totaleAttivitaDocente + $fuisPrevisto['fuisClilFunzionalePreviste'] + $fuisPrevisto['fuisClilConStudentiPreviste'] + $fuisPrevisto['fuisOrientamentoFunzionalePreviste'] + $fuisPrevisto['fuisOrientamentoConStudentiPreviste'];

	// aggiorna i totali di istituto
	$totaleAssegnatoIstuto = $totaleAssegnatoIstuto + $totaleAssegnatoDocente;
	$totaleDiariaIstuto = $totaleDiariaIstuto + $totaleDiariaDocente;
	$totaleAttivitaIstuto = $totaleAttivitaIstuto + $totaleAttivitaDocente;
	$totaleCorsiDiRecuperoExtraIstituto = $totaleCorsiDiRecuperoExtraIstituto + $totaleCorsiDiRecuperoExtraDocente;

	// se ha trovato qualcosa di significativo, include il docente nello storico
	if ($significativo) {
		$dataContenuto = $dataContenuto . $data;
	}
}

// totale della spesa fuis
$totaleSpesaFuis = $totaleDiariaIstuto + $totaleAssegnatoIstuto + $totaleAttivitaIstuto + $totaleCorsiDiRecuperoExtraIstituto;

// serve il nome dell'anno scolastico
$nome_anno_scolastico = dbGetValue("SELECT anno FROM anno_scolastico WHERE id=$anno_id");

// stampa i totali di istituto
$dataConsuntivo .= '<hr style="page-break-before: always;">';
$dataConsuntivo .= '<h2 style="text-align: center; padding-top: 3cm; padding-bottom: 2cm;">Totale FUIS anno scolastico '.$nome_anno_scolastico.'</h2>';
$dataConsuntivo .= '<table class="table table-bordered table-striped table-green">';

$dataConsuntivo .= '<thead><tr><th class="col-sd-11 text-left">Tipo</th><th class="col-sd-1 text-center">Importo</th></tr></thead><tbody>';
$dataConsuntivo .= '<tr><td class="col-sd-11 text-left">Totale Diaria Viaggi</td><td class="col-sd-1 text-right">' . number_format($totaleDiariaIstuto,2) . '</td></tr>';
$dataConsuntivo .= '<tr><td class="col-sd-11 text-left">Totale FUIS Assegnato</td><td class="col-sd-1 text-right">' . number_format($totaleAssegnatoIstuto,2) . '</td></tr>';
$dataConsuntivo .= '<tr><td class="col-sd-11 text-left">Totale FUIS Attività</td><td class="col-sd-1 text-right">' . number_format($totaleAttivitaIstuto,2) . '</td></tr>';
$dataConsuntivo .= '<tr><td class="col-sd-11 text-left">Totale Corsi di Recepero Extra</td><td class="col-sd-1 text-right">' . number_format($totaleCorsiDiRecuperoExtraIstituto,2) . '</td></tr>';
$dataConsuntivo .= '</tbody><tfoot>';
$dataConsuntivo .= '<tr><td class="col-sd-11 text-left"><strong>Totale Spesa FUIS</strong></td><td class="col-sd-1 text-right"><strong>' . number_format($totaleSpesaFuis,2) . '</strong></td></tr>';
$dataConsuntivo .= '</tfoot></table>';
$dataConsuntivo .= '<hr>';

// prima pagina
$dataCopertina .= '<h2 style="text-align: center; padding-bottom: 1cm;"><img style="text-align: center;" alt="" src="data:image/png;base64,'. base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'Logo.png'")).'" title=""></h2>';
$dataCopertina .= '<h3 style="text-align: center; padding-bottom: 3cm;">'.getSettingsValue('local','nomeIstituto', '').'</h3>';
$dataCopertina .= '<h2 style="text-align: center;">FUIS Docenti anno scolastico '.$nome_anno_scolastico.'</h2>';

// titolo
$annoStampabile = str_replace('/','-',$nome_anno_scolastico);
$title = 'Storico Previste ' . $annoStampabile.' - '.getSettingsValue('local','nomeIstituto', '');

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

	.btn-lightblue4 {
		background-color: hsl(199, 92%, 73%) !important;
		background-repeat: repeat-x;
		filter: progid:DXImageTransform.Microsoft.gradient(startColorstr="#f0f9fe", endColorstr="#7ad1f9");
		background-image: -khtml-gradient(linear, left top, left bottom, from(#f0f9fe), to(#7ad1f9));
		background-image: -moz-linear-gradient(top, #f0f9fe, #7ad1f9);
		background-image: -ms-linear-gradient(top, #f0f9fe, #7ad1f9);
		background-image: -webkit-gradient(linear, left top, left bottom, color-stop(0%, #f0f9fe), color-stop(100%, #7ad1f9));
		background-image: -webkit-linear-gradient(top, #f0f9fe, #7ad1f9);
		background-image: -o-linear-gradient(top, #f0f9fe, #7ad1f9);
		background-image: linear-gradient(#f0f9fe, #7ad1f9);
		border-color: #7ad1f9 #7ad1f9 hsl(199, 92%, 67%);
		color: #333 !important;
		text-shadow: 0 1px 1px rgba(255, 255, 255, 0.39);
		-webkit-font-smoothing: antialiased;
	}
  
	.btn-beige {
		background-color: #b89b5d !important;
		background-repeat: repeat-x;
		filter: progid:DXImageTransform.Microsoft.gradient(startColorstr="#fad8c1", endColorstr="#b89b5d");
		background-image: -khtml-gradient(linear, left top, left bottom, from(#fad8c1), to(#b89b5d));
		background-image: -moz-linear-gradient(top, #fad8c1, #b89b5d);
		background-image: -ms-linear-gradient(top, #fad8c1, #b89b5d);
		background-image: -webkit-gradient(linear, left top, left bottom, color-stop(0%, #fad8c1), color-stop(100%, #b89b5d));
		background-image: -webkit-linear-gradient(top, #fad8c1, #b89b5d);
		background-image: -o-linear-gradient(top, #fad8c1, #b89b5d);
		background-image: linear-gradient(#fad8c1, #b89b5d);
		border-color: #b89b5d #b89b5d #b89b5d;
		color: #215;
		text-shadow: 0 1px 1px rgba(255, 255, 255, 0.59);
		-webkit-font-smoothing: antialiased;
	}

	.btn-purple {
		background-color: #cfB0B0;
		background-repeat: repeat-x;
		filter: progid:DXImageTransform.Microsoft.gradient(startColorstr="#fad8c1", endColorstr="#cfB0B0");
		background-image: -khtml-gradient(linear, left top, left bottom, from(#fad8c1), to(#ec6c17));
		background-image: -moz-linear-gradient(top, #fad8c1, #cfB0B0);
		background-image: -ms-linear-gradient(top, #fad8c1, #cfB0B0);
		background-image: -webkit-gradient(linear, left top, left bottom, color-stop(0%, #fad8c1), color-stop(100%, #cfB0B0));
		background-image: -webkit-linear-gradient(top, #fad8c1, #cfB0B0);
		background-image: -o-linear-gradient(top, #fad8c1, #cfB0B0);
		background-image: linear-gradient(#fad8c1, #cfB0B0);
		border-color: #cfB0B0 #cfB0B0 #cfB0B0;
		color: #215;
		text-shadow: 0 1px 1px rgba(255, 255, 255, 0.59);
		-webkit-font-smoothing: antialiased;
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
$pagina .= $dataContenuto;

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