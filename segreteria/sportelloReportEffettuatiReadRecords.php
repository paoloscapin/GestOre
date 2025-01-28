<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

$passati = $_GET["passati"];
$docente_filtro_id = $_GET["docente_filtro_id"];
$materia_filtro_id = $_GET["materia_filtro_id"];
$materiaNome = dbGetValue("SELECT nome FROM materia WHERE id=$materia_filtro_id;");

// testo da scrivere per intestazione
if ($passati) {
	$intestazione = $materiaNome . ' - Sportelli effettuati fino al: ' . date('d/m/Y');
} else {
	$intestazione = $materiaNome . ' - Sportelli ancora da effettuare: ' . date('d/m/Y');
}

// totali
$totaleOreFatte = 0;
$totaleSportelliFatti = 0;
$totaleOreSaltate = 0;
$totaleSportelliSaltati = 0;

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="text-center col-md-12" colspan="8">' . $intestazione . '</th>
					</tr>
					<tr>
						<th class="text-center col-md-1">Data</th>
						<th class="text-center col-md-1">Ora</th>
						<th class="text-center col-md-2">Materia</th>
						<th class="text-center col-md-2">Docente</th>
						<th class="text-center col-md-1">Ore</th>
						<th class="text-center col-md-2">Classe</th>
						<th class="text-center col-md-1">Stato</th>
						<th class="text-center col-md-1">Iscritti</th>
						<th class="text-center col-md-1">Presenti</th>
					</tr>
					</thead>';

$query = "	SELECT
				sportello.id AS sportello_id,
				sportello.data AS sportello_data,
				sportello.ora AS sportello_ora,
				sportello.numero_ore AS sportello_numero_ore,
				sportello.luogo AS sportello_luogo,
				sportello.classe AS sportello_classe,
				sportello.firmato AS sportello_firmato,
				sportello.cancellato AS sportello_cancellato,
				materia.nome AS materia_nome,
				docente.cognome AS docente_cognome,
				docente.nome AS docente_nome,
				( SELECT COUNT(id) FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.presente) AS numero_presenti,
				( SELECT COUNT(id) FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.iscritto) AS numero_iscritti
			FROM sportello sportello
			INNER JOIN docente docente ON sportello.docente_id = docente.id
			INNER JOIN materia materia ON sportello.materia_id = materia.id
			WHERE sportello.anno_scolastico_id = $__anno_scolastico_corrente_id AND NOT sportello.cancellato ";

if( $docente_filtro_id > 0) {
	$query .= "AND sportello.docente_id = $docente_filtro_id ";
}
if( $materia_filtro_id > 0) {
	$query .= "AND sportello.materia_id = $materia_filtro_id ";
}
if( $passati) {
	$query .= "AND sportello.data <= CURDATE() ";
} else {
	$query .= "AND sportello.data > CURDATE() ";
}
$query .= "ORDER BY sportello.data ASC, docente_cognome ASC,docente_nome ASC";

$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}
foreach($resultArray as $row) {
	$sportello_id = $row['sportello_id'];
	$statoMarker = '';
	if ($row['sportello_cancellato']) {
		$statoMarker = '<span class="label label-danger">cancellato</span>';
	} elseif ($row['sportello_firmato']) {
		$statoMarker = '<span class="label label-success">firmato</span>';
	}

	$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
	$dataSportello = utf8_encode( strftime("%d %B %Y", strtotime($row['sportello_data'])));
	setlocale(LC_TIME, $oldLocale);

	// se ci sono prenotazioni, cerca la lista di studenti che sono prenotati
	$iscrittiTip = '';
	$presentiTip = '';

	if ($row['numero_iscritti'] > 0) {
		$query2 = "SELECT
				sportello_studente.id AS sportello_studente_id,
				sportello_studente.iscritto AS sportello_studente_iscritto,
				sportello_studente.presente AS sportello_studente_presente,
				sportello_studente.note AS sportello_studente_note,

				studente.cognome AS studente_cognome,
				studente.nome AS studente_nome,
				studente.classe AS studente_classe,
				studente.id AS studente_id

			FROM
				sportello_studente
			INNER JOIN studente
			ON sportello_studente.studente_id = studente.id
			WHERE sportello_studente.sportello_id = '$sportello_id';";

		$studenti = dbGetAll($query2);
		foreach($studenti as $studente) {
			if ($studente['sportello_studente_iscritto']) {
				$iscrittiTip = $iscrittiTip . $studente['studente_cognome'] . " " . $studente['studente_nome'] ." " . $studente['studente_classe'] . "</br>";
			}
			if ($studente['sportello_studente_presente']) {
				$presentiTip = $presentiTip . $studente['studente_cognome'] . " " . $studente['studente_nome'] ." " . $studente['studente_classe'] . "</br>";
			}
		}
	}

	$data .= '<tr>
		<td>'.$dataSportello.'</td>
		<td>'.$row['sportello_ora'].'</td>
		<td>'.$row['materia_nome'].'</td>
		<td>'.$row['docente_nome'].' '.$row['docente_cognome'].'</td>
		<td class="text-center">'.$row['sportello_numero_ore'].'</td>
		<td class="text-center">'.$row['sportello_classe'].'</td>
		<td class="text-center">'.$statoMarker.'</td>
		<td class="text-center" data-toggle="tooltip" data-placement="left" data-html="true" title="'.$iscrittiTip.'">'.$row['numero_iscritti'].'</td>
		<td class="text-center" data-toggle="tooltip" data-placement="left" data-html="true" title="'.$presentiTip.'">'.$row['numero_presenti'].'</td>
		</tr>';

	// aggiorna i totali
	if ($row['numero_presenti'] > 0) {
		$totaleSportelliFatti += 1;
		$totaleOreFatte += $row['sportello_numero_ore'];
	} else {
		$totaleSportelliSaltati += 1;
		$totaleOreSaltate += $row['sportello_numero_ore'];
	}
}
$data .= '<tfoot><tr class="btn-lima4"><td class="text-right" colspan="5"><strong>Totale Sportelli Fatti:</strong></td><td class="text-center"><strong>'.$totaleSportelliFatti.'</strong></td><td class="text-right"><strong>Ore:</strong></td><td class="text-center"><strong>'.$totaleOreFatte.'</strong></td></tr>';
$data .= '<tr class="btn-salmon"><td class="text-right" colspan="5"><strong>Totale Sportelli Saltati:</strong></td><td class="text-center"><strong>'.$totaleSportelliSaltati.'</strong></td><td class="text-right"><strong>Ore:</strong></td><td class="text-center"><strong>'.$totaleOreSaltate.'</strong></td></tr></tfoot>';

$data .= '</table></div>';

echo $data;
?>
