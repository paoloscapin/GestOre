<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

$soloOggi = $_GET["soloOggi"];

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<tr>
                        <th>Data</th>
                        <th>Cognome</th>
						<th>Nome</th>
						<th>Ora</th>
						<th></th>
					</tr>';

$query = "	SELECT
				sostituzione_docente.id AS local_sostituzione_docente_id,
				sostituzione_docente.*, docente.*,
				docente.id AS docente_id
			FROM sostituzione_docente
			INNER JOIN docente docente
			ON sostituzione_docente.docente_id = docente.id
            WHERE anno_scolastico_id = $__anno_scolastico_corrente_id
			";
if( $soloOggi) {
	$query .= "AND sostituzione_docente.data = CURDATE() ";
}
$query .= "ORDER BY sostituzione_docente.data DESC, docente.cognome ASC";

$lastData = "";
foreach(dbGetAll($query) as $row) {
	$nuovaData = $row['data'];
	$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
	$dataSostituzione = utf8_encode( strftime("%d %B %Y", strtotime($nuovaData)));
	setlocale(LC_TIME, $oldLocale);
	if ($nuovaData != $lastData) {
		$lastData = $nuovaData;
		$data .= '<tr>
				<th colspan="5">'.$dataSostituzione.'</th>
			</tr>';
	}
	$data .= '<tr>
        <td></td>
        <td>'.$row['cognome'].'</td>
		<td>'.$row['nome'].'</td>
		<td>'.$row['ora'].'</td>
		';
	$data .='
		<td>
		<button onclick="sostituzione_docenteDelete('.$row['local_sostituzione_docente_id'].', '.$row['docente_id'].', \''.str2js($row['cognome']).'\', \''.str2js($row['nome']).'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
		</td>
		</tr>';
}

$data .= '</table></div>';
echo $data;
?>