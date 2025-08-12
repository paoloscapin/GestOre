<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(! isset($_GET)) {
	return;
} else {
	$modulistica_template_id = $_GET['template_id'];
}

// tipo di campo
$tipoList = [];
$tipoList[1] = '<span class="glyphicon glyphicon-option-horizontal"> testo</span>';
$tipoList[2] = '<span class="glyphicon glyphicon-list"> combo</span>';
$tipoList[3] = '<span class="glyphicon glyphicon-check"> checkbox</span>';
$tipoList[4] = '<span class="glyphicon glyphicon-record"> radio</span>';
$tipoList[5] = '<span class="glyphicon glyphicon-font"> text area</span>';
$tipoList[6] = '<span class="glyphicon glyphicon-calendar"> calendar</span>';
$tipoList[7] = '<span class="glyphicon glyphicon-open"> upload</span>';
$tipoList[8] = '<span class="glyphicon glyphicon-header"> titolo</span>';

$query = "SELECT * FROM modulistica_template_campo WHERE modulistica_template_id = $modulistica_template_id ORDER BY modulistica_template_campo.posizione ASC;";

// rifaccio come tabella
$data = '';

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
            <tr>
                <th class="text-center col-md-3">Nome</th>
                <th class="text-center col-md-4">Etichetta</th>
                <th class="text-center col-md-1">Tipo</th>
                <th class="text-center col-md-1">Obbligatorio</th>
                <th class="text-center col-md-1">Salva</th>
                <th class="text-center col-md-1"></th>
                <th class="text-center col-md-1"></th>
            </tr>';

$counter = 0;
$campoList = dbGetAll("SELECT * FROM modulistica_template_campo WHERE modulistica_template_id = $modulistica_template_id ORDER BY modulistica_template_campo.posizione ASC;");
$numContenuti = count($campoList);

foreach($campoList as $campo) {
	++$counter;
	$posizione = $campo['posizione'];
	$data .= '<tr>
    <td>'.$campo['nome'].'</td>
    <td>'.$campo['etichetta'].'</td>
    <td>'.$tipoList[$campo['tipo']].'</td>
    <td>'.$campo['obbligatorio'].'</td>
    <td>'.$campo['salva_valore'].'</td>';

	$data .='
		<td class="text-center">
		<button class="btn btn-xs btn-teal4" onclick="moveDown('.$posizione.')" '. (($counter==$numContenuti)?'disabled':'') .'><span class="glyphicon glyphicon-chevron-down"></span></button>
		<button class="btn btn-xs btn-teal4" onclick="moveUp('.$posizione.')" '. (($counter==1)?'disabled':'') .'><span class="glyphicon glyphicon-chevron-up"></span></button>
		</td>
		<td class="text-center">
		<button class="btn btn-xs btn-teal4" onclick="modulisticaCampoGetDetails('.$campo['id'].')"><span class="glyphicon glyphicon-edit"></span></button>
		<button class="btn btn-xs btn-teal4" onclick="modulisticaCampoRemove('.$campo['id'].', '.$posizione.')"><span class="glyphicon glyphicon-remove"></span></button>
		</td>
		</tr>';
}
$data .= '</table></div>';
echo $data;
?>
