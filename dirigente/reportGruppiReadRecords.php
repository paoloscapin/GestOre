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

    $docente_id = $_POST['docente_id'];
    $gruppo_id = $_POST['gruppo_id'];
    $ordinamento = $_POST['ordinamento'];

    // la tabella e' sempre quella delle previste
    $tabella = 'ore_previste_attivita';

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

    $gruppiList = array();
    $incontriList = array();
    $totale = 0;

    // se non ho selezionato niente allora tabella con elenco gruppi e le ore totali sono di tutti i docenti insieme
    if ($docente_id <= 0 && $gruppo_id <= 0) {

        foreach(dbGetAll("SELECT * FROM gruppo WHERE not dipartimento AND anno_scolastico_id = $__anno_scolastico_corrente_id;") as $grp) {
            $gruppoId = $grp['id'];
            $gruppo = array();
            $gruppo['id'] = $grp['id'];
            $gruppo['nome'] = $grp['nome'];
            $gruppo['max_ore'] = $grp['max_ore'];

            $gruppo['ore_incontri'] = dbGetValue("SELECT COALESCE(SUM(gruppo_incontro.durata), 0) AS durata FROM gruppo_incontro WHERE gruppo_incontro.gruppo_id = $gruppoId AND gruppo_incontro.effettuato;");
            $gruppo['ore_totale']  = dbGetValue("SELECT COALESCE(SUM(ore), 0) AS ore_totale from gruppo_incontro_partecipazione LEFT JOIN gruppo_incontro ON gruppo_incontro_partecipazione.gruppo_incontro_id = gruppo_incontro.id WHERE gruppo_incontro.gruppo_id = $gruppoId AND gruppo_incontro.effettuato;");
            $totale += $gruppo['ore_totale'];

            $gruppiList[] = $gruppo;
        }
        // eventuale sort
        if ($ordinamento == 0) {
            usort($gruppiList, function ($item1, $item2) { return $item2['ore_totale'] <=> $item1['ore_totale']; });
        } else if ($ordinamento == 1) {
            usort($gruppiList, function ($item2, $item1) { return $item2['ore_totale'] <=> $item1['ore_totale']; });
        } else if ($ordinamento == 2) {
            usort($gruppiList, function ($item1, $item2) { return $item1['nome'] <=> $item2['nome']; });
        }

        // costruzione tabella html
        $data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green"><tr><th class="text-left">nome</th><th class="text-center">max Ore</th><th class="text-center">ore Incontri</th><th class="text-center">ore totale</th></tr>';
        foreach($gruppiList as $gruppo) {
            $data .= '<tr><td>'.$gruppo['nome'].'</td><td class="text-center">'.$gruppo['max_ore'].'</td><td class="text-center">'.$gruppo['ore_incontri'].'</td><td class="text-center">'.$gruppo['ore_totale'].'</td></tr>';
        }

        $data .= '<tfoot><tr class="warning"><td colspan="3" class="text-center"><strong>Totale:</strong></td><td class="text-center"><strong>'.$totale.'</strong></td></tr></tfoot>';
        $data .= '</table></div>';
        echo $data;
    
    // se ho selezionato il docente la tabella contiene solo i gruppi a cui partecipa e le ore totali sue
    } else if ($docente_id > 0 && $gruppo_id <= 0) {
        $query = "SELECT gruppo.id, gruppo.* FROM gruppo gruppo LEFT JOIN gruppo_partecipante ON gruppo_partecipante.gruppo_id = gruppo.id WHERE not gruppo.dipartimento AND gruppo.anno_scolastico_id = $__anno_scolastico_corrente_id AND gruppo_partecipante.docente_id = $docente_id;";

        foreach(dbGetAll($query) as $grp) {
            $gruppoId = $grp['id'];
            $gruppo = array();
            $gruppo['id'] = $grp['id'];
            $gruppo['nome'] = $grp['nome'];
            $gruppo['max_ore'] = $grp['max_ore'];

            $gruppo['ore_incontri'] = dbGetValue("SELECT COALESCE(SUM(gruppo_incontro.durata), 0) AS durata FROM gruppo_incontro WHERE gruppo_incontro.gruppo_id = $gruppoId AND gruppo_incontro.effettuato;");

            // le ore totale in questo caso sono le ore che sono attribuite a questo docente
            $gruppo['ore_totale']  = dbGetValue("SELECT COALESCE(SUM(ore), 0) AS ore_totale from gruppo_incontro_partecipazione LEFT JOIN gruppo_incontro ON gruppo_incontro_partecipazione.gruppo_incontro_id = gruppo_incontro.id WHERE gruppo_incontro.gruppo_id = $gruppoId AND gruppo_incontro.effettuato AND gruppo_incontro_partecipazione.docente_id = $docente_id;");
            $totale += $gruppo['ore_totale'];

            $gruppiList[] = $gruppo;
        }
        // eventuale sort
        if ($ordinamento == 0) {
            usort($gruppiList, function ($item1, $item2) { return $item2['ore_totale'] <=> $item1['ore_totale']; });
        } else if ($ordinamento == 1) {
            usort($gruppiList, function ($item2, $item1) { return $item2['ore_totale'] <=> $item1['ore_totale']; });
        } else if ($ordinamento == 2) {
            usort($gruppiList, function ($item1, $item2) { return $item1['nome'] <=> $item2['nome']; });
        }

        // costruzione tabella html
        $data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green"><tr><th class="text-left">nome</th><th class="text-center">max Ore</th><th class="text-center">ore Incontri</th><th class="text-center">ore totale</th></tr>';
        foreach($gruppiList as $gruppo) {
            $data .= '<tr><td>'.$gruppo['nome'].'</td><td class="text-center">'.$gruppo['max_ore'].'</td><td class="text-center">'.$gruppo['ore_incontri'].'</td><td class="text-center">'.$gruppo['ore_totale'].'</td></tr>';
        }

        $data .= '<tfoot><tr class="warning"><td colspan="3" class="text-center"><strong>Totale:</strong></td><td class="text-center"><strong>'.$totale.'</strong></td></tr></tfoot>';
        $data .= '</table></div>';
        echo $data;

    // se ho selezionato un gruppo 
    } else {
        $totaleDurata = 0;
        $totale = 0;
        $incontroList = array();

        foreach(dbGetAll("SELECT * FROM gruppo_incontro WHERE gruppo_incontro.gruppo_id = $gruppo_id;") as $inc) {
            $incontroId = $inc['id'];
            $incontro = array();
            $incontro['id'] = $inc['id'];
            $incontro['data'] = $inc['data'];
            $incontro['ora'] = $inc['ora'];
            $incontro['durata'] = $inc['durata'];
            $incontro['ordine_del_giorno'] = $inc['ordine_del_giorno'];
            $incontro['verbale'] = $inc['verbale'];

            $partecipantiList = array();

            $query = "SELECT * FROM gruppo_incontro_partecipazione
                        INNER JOIN gruppo_incontro ON gruppo_incontro_partecipazione.gruppo_incontro_id = gruppo_incontro.id
                        INNER JOIN docente ON gruppo_incontro_partecipazione.docente_id = docente.id
                        WHERE gruppo_incontro.id = $incontroId AND ha_partecipato ";
            // se ho selezionato anche il docente lo metto nella query
            if ($docente_id > 0) {
                $query .= "AND gruppo_incontro_partecipazione.docente_id =  $docente_id ";
            }
            $query .= ";";
            $partecipazioneResult = dbGetAll($query);
            if (! empty($partecipazioneResult) || $docente_id <= 0) {
                foreach(dbGetAll($query) as $partecipazione) {
                    $partecipante = array();
                    $partecipante['cognome_e_nome'] = $partecipazione['cognome'] . ' '. $partecipazione['nome'];
                    $partecipante['ore'] = $partecipazione['ore'];
                    $totale += $partecipazione['ore'];
                    $partecipantiList[] = $partecipante;
                }
                $incontro['partecipantiList'] = $partecipantiList;

                $incontroList[] = $incontro;

                $totaleDurata += $incontro['durata'];
            }
        }
        // debug(json_encode($incontroList, JSON_PRETTY_PRINT));

        // costruzione tabella html
        $data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green"><tr><th class="col-md-1 text-left">data</th><th class="col-md-1 text-left">ora</th><th class="col-md-6 text-left">Ordine del giorno</th><th class="col-md-1 text-center">durata</th><th class="col-md-2 text-center">partecipanti</th><th class="col-md-1 text-center">ore</th></tr>';
        foreach($incontroList as $incontro) {
            $durata = $incontro['durata'];
            $data .= '<tr><td>'.$incontro['data'].'</td>';

            $data .= '<td>'.$incontro['ora'].'</td>';
            $data .= '<td class="text-left">'.$incontro['ordine_del_giorno'].'</td>';

            $data .= '<td class="text-center">'.$durata.'</td>';

            $data .= '<td class="text-left">';
            foreach($incontro['partecipantiList'] as $partecipante) {
                $data .= $partecipante['cognome_e_nome'].'</br>';
            }
            $data .= '</td>';

            $data .= '<td class="text-center">';
            foreach($incontro['partecipantiList'] as $partecipante) {
                $data .= $partecipante['ore'].'</br>';
            }
            $data .= '</td>';
            $data .= '</tr>';
        }

        $data .= '<tfoot><tr class="warning"><td colspan="3" class="text-center"><strong>Totale:</strong></td><td class="text-center"><strong>'.$totaleDurata.'</strong></td><td class="text-center"></td><td class="text-center"><strong>'.$totale.'</strong></td></tr></tfoot>';
        $data .= '</table></div>';
        echo $data;
    }
}
?>
