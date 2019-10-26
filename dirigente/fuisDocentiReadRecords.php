<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';


function formatNoZero($value) {
    return ($value != 0) ? number_format($value,2) : ' ';
}

$data = '';
$data .= '
<div class="table-wrapper"><table id="fuis_docenti_table" class="table table-bordered table-striped table-green">
    <thead>
        <tr>
            <th class="text-center col-md-1">id</th>
            <th class="text-center col-md-2">Docente</th>
            <th class="text-center col-md-1">Viaggi (diaria)</th>
            <th class="text-center col-md-1">Assegnate</th>
            <th class="text-center col-md-1">Sostituzioni</th>
            <th class="text-center col-md-1">Funzionali</th>
            <th class="text-center col-md-1">Con Studenti</th>
            <th class="text-center col-md-1">Clil Funzionali</th>
            <th class="text-center col-md-1">Clil Con Studenti</th>
            <th class="text-center col-md-1">Da Pagare</th>
		</tr>
    </thead>
    <tbody>';
$query = "
    SELECT
		docente.id AS local_docente_id,
		docente.*,
        fuis_docente.*
	FROM docente
    INNER JOIN fuis_docente
    ON fuis_docente.docente_id = docente.id
    WHERE
        docente.attivo = true
    AND
        fuis_docente.anno_scolastico_id = $__anno_scolastico_corrente_id
    ORDER BY
        docente.cognome ASC, docente.nome ASC
    ";

$resultArray = dbGetAll($query);
foreach($resultArray as $docente) {
    $local_docente_id = $docente['local_docente_id'];
    $docenteCognomeNome = $docente['cognome'].' '.$docente['nome'];
    $viaggi = $docente['viaggi'];
    $assegnato = $docente['assegnato'];
    $sostituzioni = $docente['sostituzioni_approvato'];
    $funzionale = $docente['funzionale_approvato'];
    $con_studenti = $docente['con_studenti_approvato'];
    $clil_funzionale = $docente['clil_funzionale_approvato'];
    $clil_con_studenti = $docente['clil_con_studenti_approvato'];
    $totale = $docente['totale_da_pagare'];
    
    // controlla se sono state modificate delle attivita:
    $ultimo_controllo = $docente['ultimo_controllo'];
    $q2 = "SELECT COUNT(ultima_modifica) from ore_fatte_attivita WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $local_docente_id AND ultima_modifica > '$ultimo_controllo';";
    $numChanges = dbGetValue($q2);
    if ($numChanges == 0) {
        $q3 = "SELECT COUNT(ultima_modifica) from ore_fatte_attivita_clil WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $local_docente_id AND ultima_modifica > '$ultimo_controllo';";
        $numChanges = dbGetValue($q3);
    }
    $marker = ($numChanges == 0) ? '': '&ensp;<span class="label label-danger glyphicon glyphicon-star" style="color:yellow"> '. '' .'</span>';

    $data .= '<tr>
    			<td>'.$local_docente_id.'</td>
    			<td><a href="quadroDocente.php?id='.$local_docente_id.'" target="_blank">&ensp;'.$docenteCognomeNome.' '.$marker.' </a></td>
    			<td class="text-right viaggi">'.formatNoZero($viaggi).'</td>
    			<td class="text-right assegnato">'.formatNoZero($assegnato).'</td>
    			<td class="text-right sostituzioni">'.formatNoZero($sostituzioni).'</td>
    			<td class="text-right funzionale">'.formatNoZero($funzionale).'</td>
    			<td class="text-right con_studenti">'.formatNoZero($con_studenti).'</td>
    			<td class="text-right clil_funzionale">'.formatNoZero($clil_funzionale).'</td>
    			<td class="text-right clil_con_studenti">'.formatNoZero($clil_con_studenti).'</td>
    			<td class="text-right totale">'.formatNoZero($totale).'</td>
    		</tr>';
}
$data .= '</tbody>';
$data .= '</table>
';
$data .= '</div>';
echo $data;
?>
