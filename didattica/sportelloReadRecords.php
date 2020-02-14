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

$ancheCancellati = $_GET["ancheCancellati"];

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="text-center col-md-2">Data</th>
						<th class="text-center col-md-1">Ora</th>
						<th class="text-center col-md-2">Materia</th>
						<th class="text-center col-md-2">Docente</th>
						<th class="text-center col-md-1">Ore</th>
						<th class="text-center col-md-1">Classe</th>
						<th class="text-center col-md-1">Stato</th>
						<th class="text-center col-md-1">Studenti</th>
						<th class="text-center col-md-1"></th>
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
				(	SELECT COUNT(*) FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id) AS numero_studenti
			FROM sportello sportello
			INNER JOIN docente docente
			ON sportello.docente_id = docente.id
			INNER JOIN materia materia
			ON sportello.materia_id = materia.id
			WHERE sportello.anno_scolastico_id = $__anno_scolastico_corrente_id
			";

if( ! $ancheCancellati) {
	$query .= "AND NOT viaggio.cancellato ";
}
$query .= "ORDER BY sportello.data DESC, docente_cognome ASC,docente_nome ASC";

$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}
foreach($resultArray as $row) {
	$cancellatoMarker = '';
	if ($row['sportello_cancellato']) {
		$statoMarker = '<span class="label label-danger">cancellato</span>';
	}

	$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
	$dataSportello = utf8_encode( strftime("%d %B %Y", strtotime($row['sportello_data'])));
	setlocale(LC_TIME, $oldLocale);

	$data .= '<tr>
		<td>'.$dataSportello.'</td>
		<td>'.$row['sportello_ora'].'</td>
		<td>'.$row['materia_nome'].'</td>
		<td>'.$row['docente_nome'].' '.$row['docente_cognome'].'</td>
		<td>'.$row['sportello_numero_ore'].'</td>
		<td>'.$row['sportello_classe'].'</td>
		<td>'.$cancellatoMarker.'</td>
		<td>'.$row['numero_studenti'].'</td>
		';
	$data .='
		<td class="text-center">
		<button onclick="sportelloGetDetails('.$row['sportello_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
		<button onclick="sportelloDelete('.$row['sportello_id'].', \''.$row['materia_nome'].'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
		</td>
		</tr>';
}

$data .= '</table></div>';

echo $data;
?>

