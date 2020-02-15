<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/importi_load.php';


function formatNoZero($value) {
    return ($value != 0) ? number_format($value,2) : ' ';
}

function formatNoZeroNoDecimal($value) {
    return ($value != 0) ? number_format($value,0) : ' ';
}

// calcola il totale degli assegnati
$totale_bonus_assegnato = dbGetValue("SELECT SUM(importo) FROM `bonus_assegnato`;");
debug('totale_bonus_assegnato=' . $totale_bonus_assegnato);


// calcola i punti totali delle varie opzioni
$query = "SELECT SUM(valore_previsto) FROM `bonus`;";
$totale_valore_previsto = dbGetValue($query);
debug('totale_valore_previsto=' . $totale_valore_previsto);

// calcola il totale in punti finora approvati
$query = "SELECT SUM(valore_previsto) FROM bonus LEFT JOIN bonus_docente ON bonus.id = bonus_docente.bonus_id WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND approvato is true;";
$totale_valore_approvato = dbGetValue($query);
debug('totale_valore_approvato=' . $totale_valore_approvato);

// importo totale disponibile per il bonus
$importo_totale_bonus = $__importo_bonus;

// quello che non e' stato ancora assegnato resta da dividere tra quelli approvati
$importo_totale_bonus_approvato = $importo_totale_bonus - $totale_bonus_assegnato;
if ($totale_valore_approvato != 0) {
    $importo_per_punto = $importo_totale_bonus_approvato / $totale_valore_approvato;
} else {
    $importo_per_punto = 0;
}

$data = '';
$data .= '
<div class="table-wrapper"><table id="bonus_docenti_table" class="table table-bordered table-striped table-green">
    <thead>
        <tr>
            <th class="text-center col-md-1">id</th>
            <th class="text-center col-md-2">Docente</th>
            <th class="text-center col-md-1">Richiesto</th>
            <th class="text-center col-md-1">Approvato</th>
            <th class="text-center col-md-1">Importo</th>
            <th class="text-center col-md-1">Assegnato</th>
            <th class="text-center col-md-1">da Pagare</th>
		</tr>
    </thead>
    <tbody>';

// prendi tutti i docenti e per ciascuno il suo bonus
$query = "	SELECT * FROM docente WHERE docente.attivo = true ORDER BY docente.cognome, docente.nome ASC ;";
$resultArray = dbGetAll($query);
foreach($resultArray as $docente) {
    $local_docente_id = $docente['id'];
    $docenteCognomeNome = $docente['cognome'].' '.$docente['nome'];
    // tutti quelli richiesti
    $query = "SELECT SUM(valore_previsto) FROM bonus LEFT JOIN bonus_docente ON bonus.id = bonus_docente.bonus_id WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND bonus_docente.docente_id = $local_docente_id;";
    $punti_richiesti = dbGetValue($query);
    // solo quelli approvati
    $query = "SELECT SUM(valore_previsto) FROM bonus LEFT JOIN bonus_docente ON bonus.id = bonus_docente.bonus_id WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND bonus_docente.docente_id = $local_docente_id AND approvato is true;";
    $punti_approvati = dbGetValue($query);
    $importo_approvato = $importo_per_punto * $punti_approvati;
    $query = "SELECT COUNT(id) FROM bonus_docente WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $local_docente_id AND ultima_modifica > ultimo_controllo;";
    $numero_modificati = dbGetValue($query);
    debug('docente='.$docenteCognomeNome.' numero_modificati='.$numero_modificati);
    $marker = ($numero_modificati == 0) ? '': '&ensp;<span class="label label-danger glyphicon glyphicon-star" style="color:yellow"> '. '' .'</span>';

    $query = "SELECT SUM(importo) FROM bonus_assegnato WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND bonus_assegnato.docente_id = $local_docente_id;";
    $importo_bonus_assegnato = dbGetValue($query);

    // il totale da pagare
    $importo_da_pagare = $importo_approvato + $importo_bonus_assegnato;

    $openTabMode = getSettingsValue('interfaccia','apriDocenteInNuovoTab', false) ? '_blank' : '_self';

    $data .= '<tr>
    			<td>'.$local_docente_id.'</td>
    			<td><a href="bonusDettaglioDocente.php?id='.$local_docente_id.'" target="'.$openTabMode.'">&ensp;'.$docenteCognomeNome.' '.$marker.' </a></td>
    			<td class="text-right viaggi">'.formatNoZeroNoDecimal($punti_richiesti).'</td>
    			<td class="text-right assegnato">'.formatNoZeroNoDecimal($punti_approvati).'</td>
    			<td class="text-right funzionale">'.formatNoZero($importo_approvato).'</td>
    			<td class="text-right funzionale">'.formatNoZero($importo_bonus_assegnato).'</td>

    			<td class="text-right totale">'.formatNoZero($importo_da_pagare).'</td>
    		</tr>';
}
$data .= '</tbody>';
$data .= '</table>
';
$data .= '</div>';
echo $data;
?>
