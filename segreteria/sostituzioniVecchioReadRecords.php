<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */


require_once '../common/checkSession.php';

$warning = '<span class="glyphicon glyphicon-warning-sign text-error"></span>';
$okSymbol = '&ensp;<span class="glyphicon glyphicon-ok text-success"></span>';

function getHtmlNum($value) {
    return '&emsp;' . (($value >= 10) ? $value : '&ensp;' . $value);
}

function getHtmlNumAndPrevisteVisual($diff) {
    global $okSymbol;
    global $warning;

    $numString = ($diff >= 10) ? $diff : '&ensp;' . $diff;
    if ($diff > 0) {
        $numString .= '&ensp;<span class="label label-warning">- '. $diff .'</span>';
    } else if ($diff < 0) {
        $numString .= '&ensp;<span class="label label-danger">+ '. (-$diff) .'</span>';
    } else {
        $numString .= $okSymbol;
    }
    return '&emsp;' . $numString;
}

$data = '';
$data .= '
<div class="table-wrapper"><table id="sostituzioni_table" class="table table-bordered table-striped table-green">
    <thead>
        <tr>
            <th class="text-center col-md-1">id</th>
            <th class="text-center col-md-6">Docente</th>
            <th class="text-center col-md-1">Dovute</th>
            <th class="text-center col-md-1">Fatte</th>
            <th class="text-center col-md-1"></th>
		</tr>
    </thead>
    <tbody>';
$query = "
    SELECT
		ore_previste.id AS ore_previste_id,
		ore_previste.ore_40_sostituzioni_di_ufficio AS ore_previste_ore_40_sostituzioni_di_ufficio,
		ore_fatte.id AS ore_fatte_id,
		ore_fatte.ore_40_sostituzioni_di_ufficio AS ore_fatte_ore_40_sostituzioni_di_ufficio,
		docente.id AS docente_id,
		docente.nome AS docente_nome,
		docente.cognome AS docente_cognome
		
	FROM ore_previste ore_previste
	INNER JOIN ore_fatte ore_fatte
	ON ore_fatte.docente_id = ore_previste.docente_id
	INNER JOIN docente docente
	ON ore_previste.docente_id = docente.id
	WHERE
        ore_previste.anno_scolastico_id = $__anno_scolastico_corrente_id
    AND
        ore_fatte.anno_scolastico_id = $__anno_scolastico_corrente_id
	ORDER BY
		docente.cognome ASC,
		docente.nome ASC
	";
$resultArray = dbGetAll($query);
foreach($resultArray as $sostituzioni) {
    $id = $sostituzioni['ore_fatte_id'];
    $docenteCognomeNome = $sostituzioni['docente_cognome'].' '.$sostituzioni['docente_nome'];
    $previste = $sostituzioni['ore_previste_ore_40_sostituzioni_di_ufficio'];
    $fatte = $sostituzioni['ore_fatte_ore_40_sostituzioni_di_ufficio'];
    $differenza = $previste - $fatte;
    $data .= '<tr>
    			<td>'.$id.'</td>
    			<td>'.$docenteCognomeNome.'</td>
    			<td class="text-center">'.$previste.'</td>
    			<td class="text-center">
                    <input type="text" class="form-control text-center numeroSostituzioniFatte" value="'.$fatte.'" />
                </td>
    			<td class="text-center">'.getHtmlNumAndPrevisteVisual($differenza).'</td>
    		</tr>';
}
$data .= '</tbody>';
$data .= '</table>
';
$data .= '</div>';
echo $data;
?>
