<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// include Database connection file
require_once '../common/checkSession.php';
require_once '../common/connect.php';

$anchePubblicati = $_GET["anchePubblicati"];
$soloTemplate = $_GET["soloTemplate"];
$anno_filtro_id = $_GET["anno_filtro_id"];
$materia_filtro_id = $_GET["materia_filtro_id"];
$docente_filtro_id = $_GET["docente_filtro_id"];
$stato_filtro_id = $_GET["stato_filtro_id"];

$direzioneOrdinamento="ASC";

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="text-center col-md-1">Anno Scolastico</th>
						<th class="text-center col-md-2">Materia</th>
						<th class="text-center col-md-1">Creato il</th>
						<th class="text-center col-md-1">Ultima modifica</th>
						<th class="text-center col-md-1">Classe</th>
						<th class="text-center col-md-2">Docente</th>
						<th class="text-center col-md-1">Stato</th>
						<th class="text-center col-md-1">Moduli</th>
						<th class="text-center col-md-2"></th>
					</tr>
					</thead>';
					
$query = "	SELECT
				piano_di_lavoro.id AS piano_di_lavoro_id, piano_di_lavoro.*, materia.nome AS materia_nome, docente.cognome AS docente_cognome, docente.nome AS docente_nome, indirizzo.nome_breve AS indirizzo_nome_breve , anno_scolastico.anno AS anno FROM piano_di_lavoro piano_di_lavoro
			INNER JOIN docente docente
			ON piano_di_lavoro.docente_id = docente.id
			INNER JOIN materia materia
			ON piano_di_lavoro.materia_id = materia.id
			INNER JOIN indirizzo indirizzo
			ON piano_di_lavoro.indirizzo_id = indirizzo.id
			INNER JOIN anno_scolastico anno_scolastico
			ON piano_di_lavoro.anno_scolastico_id = anno_scolastico.id
			WHERE 1 = 1
			";

if( $materia_filtro_id > 0) {
	$query .= "AND piano_di_lavoro.materia_id = $materia_filtro_id ";
}

if( $anno_filtro_id > 0) {
	$query .= "AND piano_di_lavoro.anno_scolastico_id = $anno_filtro_id ";
}

if( $docente_filtro_id > 0) {
	$query .= "AND piano_di_lavoro.docente_id = $docente_filtro_id ";
}

if( $stato_filtro_id != '0') {
	$query .= "AND piano_di_lavoro.stato = '$stato_filtro_id' ";
}

if( $soloTemplate) {
	$query .= " AND piano_di_lavoro.template ";
} else {
	$query .= " AND NOT piano_di_lavoro.template ";
}

$query .= " AND NOT piano_di_lavoro.carenza ";

$query .= "ORDER BY piano_di_lavoro.creazione $direzioneOrdinamento";

foreach(dbGetAll($query) as $row) {

	$piano_di_lavoro_id = $row['piano_di_lavoro_id'];

	$statoMarker = '';
	if ($row['stato'] == 'draft') {
		$statoMarker .= '<span class="label label-warning">draft</span>';
	} elseif ($row['stato'] == 'annullato') {
		$statoMarker .= '<span class="label label-danger">annullato</span>';
	} elseif ($row['stato'] == 'finale') {
		$statoMarker .= '<span class="label label-success">finale</span>';
	} elseif ($row['stato'] == 'pubblicato') {
		$statoMarker .= '<span class="label label-info">pubblicato</span>';
	}

	$templateMarker = '';
	if ($row['template'] == true) {
		$templateMarker .= '<span class="label label-success">'.getLabel('Template').'</span>';
	}

	$clilMarker = '';
	if ($row['clil'] == true) {
		$clilMarker .= '<span class="label label-info">Clil</span>';
	}

	$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
	$dataCreazione = utf8_encode( strftime("%d %B %Y", strtotime($row['creazione'])));
	$dataUltimaModifica = utf8_encode( strftime("%d %B %Y", strtotime($row['ultima_modifica'])));
	setlocale(LC_TIME, $oldLocale);

	$classe = $row['classe'].$row['indirizzo_nome_breve'].$row['sezione'];

	$docenteNomeCognome = $row['docente_nome'] . ' ' . $row['docente_cognome'];

	$data .= '<tr>
		<td>'.$row['anno'].'</td>
		<td>'.$row['materia_nome'].'</td>
		<td>'.$dataCreazione.'</td>
		<td>'.$dataUltimaModifica.'</td>
		<td>'.$classe.'</td>
		<td>'.$docenteNomeCognome.'</td>
		<td class="text-center">'.$templateMarker.'&nbsp;'.$clilMarker.'&nbsp;'.$statoMarker.'</td>
		';
	$data .='
		<td class="text-center">
			<button onclick="pianoDiLavoroOpenDocument('.$row['piano_di_lavoro_id'].')" class="btn btn-teal4 btn-xs"><span class="glyphicon glyphicon-file">&nbsp;Moduli</span></button>
		</td>
		<td class="text-center">
		<button onclick="pianoDiLavoroPreview('.$row['piano_di_lavoro_id'].')" class="btn btn-info btn-xs"><span class="glyphicon glyphicon-blackboard"></span>&nbsp;Preview</button>';
	
	// bottone carenze solo se abilitata funzionalita'
	if(getSettingsValue('config','carenze', false)) {
		// bottone carenze solo da piani di lavoro non template e pubblicati
		if ($row['stato'] == 'pubblicato' && ! $row['template'] == true) {
			$data .='<button onclick="pianoDiLavoroCarenza('.$row['piano_di_lavoro_id'].')" class="btn btn-yellow4 btn-xs"><span class="glyphicon glyphicon-flag"></span>&nbsp;Carenze</button>';
		}
	}		
	$data .='
		<button onclick="pianoDiLavoroDuplicate('.$row['piano_di_lavoro_id'].')" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-copy">&nbsp;Duplica</span></button>
		<button onclick="pianoDiLavoroSavePdf('.$row['piano_di_lavoro_id'].')" class="btn btn-orange4 btn-xs" style="display: inline-flex;align-items: center;"><i class="icon-play"></i>&nbsp;Pdf</button>
		<button onclick="pianoDiLavoroGetDetails('.$row['piano_di_lavoro_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></span></button>
		<button onclick="pianoDiLavoroDelete('.$row['piano_di_lavoro_id'].', \''.$row['materia_nome'].'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
		</td>
		</tr>';
}

$data .= '</table></div>';
echo $data;
?>

