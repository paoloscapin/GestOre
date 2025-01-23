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

$anno_filtro_id = $_GET["anno_filtro_id"];
$docente_filtro_id = $_GET["docente_filtro_id"];
$stato_filtro_id = $_GET["stato_filtro_id"];

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="text-center col-md-1">Anno Scolastico</th>
						<th class="text-center col-md-1">Codice</th>
						<th class="text-center col-md-2">Docente</th>
						<th class="text-center col-md-4">Richiesta</th>
						<th class="text-center col-md-1">Data</th>
						<th class="text-center col-md-2">Stato</th>
						<th class="text-center col-md-1"></th>
					</tr>
					</thead>';

$query = "	SELECT
				modulistica_richiesta.id AS modulistica_richiesta_id, modulistica_richiesta.*, modulistica_template.nome AS modulistica_template_nome, modulistica_template.*, docente.cognome AS docente_cognome, docente.nome AS docente_nome, anno_scolastico.anno AS anno FROM modulistica_richiesta modulistica_richiesta
			INNER JOIN docente docente
			ON modulistica_richiesta.docente_id = docente.id
			INNER JOIN modulistica_template modulistica_template
			ON modulistica_richiesta.modulistica_template_id = modulistica_template.id
			INNER JOIN anno_scolastico anno_scolastico
			ON modulistica_richiesta.anno_scolastico_id = anno_scolastico.id
			WHERE 1 = 1
			";

if( $anno_filtro_id > 0) {
	$query .= "AND modulistica_richiesta.anno_scolastico_id = $anno_filtro_id ";
}

if( $docente_filtro_id > 0) {
	$query .= "AND modulistica_richiesta.docente_id = $docente_filtro_id ";
}

if( $stato_filtro_id != '0') {
    if( $stato_filtro_id == 'pendente') {
        $query .= "AND NOT modulistica_richiesta.chiusa AND NOT modulistica_richiesta.approvata AND NOT modulistica_richiesta.respinta AND NOT modulistica_richiesta.annullata ";
    }
    if( $stato_filtro_id == 'approvata') {
        $query .= "AND modulistica_richiesta.approvata ";
    }
    if( $stato_filtro_id == 'respinta') {
        $query .= "AND modulistica_richiesta.respinta ";
    }
    if( $stato_filtro_id == 'annullata') {
        $query .= "AND modulistica_richiesta.annullata ";
    }
    if( $stato_filtro_id == 'chiusa') {
        $query .= "AND modulistica_richiesta.chiusa ";
    }
}

$query .= " ORDER BY modulistica_richiesta.id DESC, docente.cognome ASC, docente.nome ASC";

foreach(dbGetAll($query) as $row) {

	$modulistica_richiesta_id = $row['modulistica_richiesta_id'];
	$modulistica_richiesta_uuid = $row['uuid'];

	$statoMarker = '';
	if ($row['annullata']) {
		$statoMarker .= '<span class="label label-warning">annullata</span>';
	} else {
        if ($row['approvata']) {
            $statoMarker .= '<span class="label label-success">approvata</span>';
        }
        if ($row['respinta']) {
            $statoMarker .= '<span class="label label-danger">respinta</span>';
        }
        if ($row['chiusa']) {
            $statoMarker .= '<span class="label label-info">chiusa</span>';
        }
    }

	$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
	$dataInvio = utf8_encode( strftime("%d %B %Y", strtotime($row['data_invio'])));
	$dataApprovazione = utf8_encode( strftime("%d %B %Y", strtotime($row['data_approvazione'])));
	$dataChiusura = utf8_encode( strftime("%d %B %Y", strtotime($row['data_chiusura'])));
	setlocale(LC_TIME, $oldLocale);

	$docenteCognomeNome = $row['docente_cognome'] . ' ' . $row['docente_nome'];

	$data .= '<tr>
		<td>'.$row['anno'].'</td>
		<td>'.$modulistica_richiesta_id.'</td>
		<td>'.$docenteCognomeNome.'</td>
		<td>'.$row['modulistica_template_nome'].'</td>
		<td>'.$dataInvio.'</td>
        
		<td class="text-center">'.$statoMarker.'</td>';
    $data .='<td class="text-center">
		<button onclick="modulisticaRichiestaGetDetails('.$modulistica_richiesta_id.', \''.$modulistica_richiesta_uuid.'\')" class="btn btn-warning btn-xs" '.(true? ' ':'disabled ').' ><span class="glyphicon glyphicon-pencil"></span></button>
		</td>
		</tr>';
}

$data .= '</table></div>';
echo $data;
?>