<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

if(isset($_POST)) {
    require_once '../common/checkSession.php';
    ruoloRichiesto('dirigente');

    $tipo_attivita_id = $_POST['tipo_attivita_id'];
    $ordinamento = $_POST['ordinamento'];

    // la tabella e' sempre quella delle previste
    $tabella = 'ore_previste_attivita';
/*
    // per prima cosa deve capire se sono assegnate o inserite dal docente, per decidere da quale tabella cercarle
    $assegnate = dbGetValue("SELECT inserito_da_docente FROM ore_previste_tipo_attivita WHERE id=$tipo_attivita_id");
    if ($assegnate == 0) {
        $tabella = 'ore_previste_attivita';
    } else {
        $tabella = 'ore_fatte_attivita';
    }
*/

    switch ($ordinamento) {
        case 0:
            $orderBy = "SUM($tabella.ore) DESC";
            break;
        case 1:
            $orderBy = "SUM($tabella.ore)";
            break;
        case 2:
            $orderBy = "cognome,nome";
            break;
    }

    $openTabMode = getSettingsValue('interfaccia','apriDocenteInNuovoTab', false) ? '_blank' : '_self';

    // Design initial table header
    $data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
                        <tr>
                            <th>Docente</th>
                            <th class="text-center">Ore</th>
                        </tr>';

    $query = "SELECT SUM($tabella.ore), docente.* FROM $tabella
    INNER JOIN ore_previste_tipo_attivita ON ore_previste_tipo_attivita.id = $tabella.ore_previste_tipo_attivita_id
    INNER JOIN docente ON $tabella.docente_id = docente.id
    WHERE ore_previste_tipo_attivita.id = $tipo_attivita_id
    AND $tabella.anno_scolastico_id = $__anno_scolastico_corrente_id
    GROUP BY docente.id
    ORDER BY $orderBy ;";

    debug($query);
    $totale = 0;
    foreach(dbGetAll($query) as $row) {
        $docenteId = $row['id'];
        $docenteCognomeNome = $row['cognome'].' '.$row['nome'];
        $oreDocente = $row['SUM('.$tabella.'.ore)'];

        $data .= '<tr>
        <td><a href="../docente/previste.php?docente_id='.$docenteId.'" target="'.$openTabMode.'">&ensp;'.$docenteCognomeNome.'</a></td>
        <td class="text-center">'.$oreDocente.'</td>
            ';
        $data .='</tr>';
        $totale += $oreDocente;
    }

    $data .= '<tfoot><tr class="warning"><td class="text-center"><strong>Torale:</strong></td><td class="text-center"><strong>'.$totale.'</strong></td></tr></tfoot>';
    $data .= '</table></div>';
    echo $data;
}
?>
