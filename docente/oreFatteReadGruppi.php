<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/__MinutiFunction.php';

function oreFatteReadGruppi($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile) {
	global $__anno_scolastico_corrente_id;

    // valori da restituire come totali
    $gruppiOre = 0;
    $gruppiOreClil = 0;
    $gruppiOreOrientamento = 0;
    $dataGruppi = '';

    if($soloTotale) {
        $query = "SELECT gruppo.clil, gruppo.orientamento, SUM(gruppo_incontro_partecipazione.ore) AS ore FROM gruppo_incontro_partecipazione
                    INNER JOIN gruppo_incontro ON gruppo_incontro_partecipazione.gruppo_incontro_id = gruppo_incontro.id
                    INNER JOIN gruppo ON gruppo_incontro.gruppo_id = gruppo.id
                    WHERE gruppo_incontro_partecipazione.docente_id = $docente_id AND gruppo_incontro_partecipazione.ha_partecipato = true AND gruppo.anno_scolastico_id = $__anno_scolastico_corrente_id AND gruppo_incontro.effettuato = true AND gruppo.dipartimento = false
                    GROUP BY gruppo.clil, gruppo.orientamento;";
        foreach(dbGetAll($query) as $gruppo) {
            // aggiorna il totale delle ore:
            if ($gruppo['clil']) {
                $gruppiOreClil += $gruppo['ore'];
            } elseif ($gruppo['orientamento']) {
                $gruppiOreOrientamento += $gruppo['ore'];
            } else {
                $gruppiOre += $gruppo['ore'];
            }    
        }

        $result = compact('dataGruppi', 'gruppiOre', 'gruppiOreClil', 'gruppiOreOrientamento');
        return $result;
    }

    // Design initial table header
    $dataGruppi .= '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
                            <thead><tr>
                                <th class="col-md-10 text-left">Gruppo</th>
                                <th class="col-md-1 text-center">Data</th>
                                <th class="col-md-1 text-center">Ore</th>
                            </tr></thead><tbody>';

    $query = "SELECT
                gruppo.nome AS gruppo_nome,
                gruppo.clil AS gruppo_clil,
                gruppo.orientamento AS gruppo_orientamento,
                gruppo_incontro.data AS gruppo_incontro_data,
                gruppo_incontro_partecipazione.ore AS gruppo_incontro_partecipazione_ore

                FROM gruppo_incontro_partecipazione
                INNER JOIN docente ON gruppo_incontro_partecipazione.docente_id = docente.id
                INNER JOIN gruppo_incontro ON gruppo_incontro_partecipazione.gruppo_incontro_id = gruppo_incontro.id
                INNER JOIN gruppo ON gruppo_incontro.gruppo_id = gruppo.id
                WHERE gruppo_incontro_partecipazione.docente_id = $docente_id
                AND gruppo_incontro_partecipazione.ha_partecipato = true
                AND gruppo.anno_scolastico_id = $__anno_scolastico_corrente_id
                AND gruppo_incontro.effettuato = true
                AND gruppo.dipartimento = false
                ORDER BY gruppo.orientamento ASC, gruppo.clil ASC, gruppo_incontro.data DESC;";

    $lastWasClil = false;
    $lastWasOrientamento = false;
    foreach(dbGetAll($query) as $gruppo) {
		// controlla se iniziano qui i gruppi clil
		if ($gruppo['gruppo_clil'] && ! $lastWasClil) {
			$dataGruppi .= '<thead><tr><th colspan="3" class="col-md-12 text-center btn-lightblue4" style="padding: 0px">Clil</th></tr></thead>';
			$lastWasClil = true;
		}

		// controlla se iniziano qui i gruppi orientamento
		if ($gruppo['gruppo_orientamento'] && ! $lastWasOrientamento) {
			$dataGruppi .= '<tr><th colspan="3" class="col-md-12 text-center btn-beige" style="padding: 0px">Orientamento</th></tr>';
			$lastWasOrientamento = true;
		}

        $ore_con_minuti = oreToDisplay($gruppo['gruppo_incontro_partecipazione_ore']);
        $dataGruppi .= '<tr>
            <td>'.$gruppo['gruppo_nome'].'</td>
            <td class="text-center">'.strftime("%d/%m/%Y", strtotime($gruppo['gruppo_incontro_data'])).'</td>
            <td class="text-center">'.$ore_con_minuti.'</td></tr>';

        // aggiorna il totale delle ore:
        if ($gruppo['gruppo_clil']) {
            $gruppiOreClil += $gruppo['gruppo_incontro_partecipazione_ore'];
        } elseif ($gruppo['gruppo_orientamento']) {
            $gruppiOreOrientamento += $gruppo['gruppo_incontro_partecipazione_ore'];
        } else {
            $gruppiOre += $gruppo['gruppo_incontro_partecipazione_ore'];
        }    
    }
    $dataGruppi .= '</tbody></table></div>';

	$result = compact('dataGruppi', 'gruppiOre', 'gruppiOreClil', 'gruppiOreOrientamento');
	return $result;
}
?>