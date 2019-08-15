<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('dirigente');

$query = "
    SELECT
        SUM(viaggi) as sum_viaggi,
        SUM(assegnato) as sum_assegnato,
        SUM(sostituzioni_approvato) as sum_sostituzioni,
        SUM(funzionale_approvato) as sum_funzionale,
        SUM(con_studenti_approvato) as sum_con_studenti,
        SUM(clil_funzionale_approvato) as sum_clil_funzionale,
        SUM(clil_con_studenti_approvato) as sum_clil_con_studenti,
        SUM(totale_approvato) as sum_totale_ore
    FROM `fuis_docente`
    WHERE fuis_docente.anno_scolastico_id = '$__anno_scolastico_corrente_id'
";
$fuis = dbGetFirst($query);

$fuis_viaggi = number_format($fuis['sum_viaggi'],2);
$fuis_assegnato = number_format($fuis['sum_assegnato'],2);
$fuis_ore = number_format($fuis['sum_totale_ore'],2);
$fuis_clil_funzionale = number_format($fuis['sum_clil_funzionale'],2);
$fuis_clil_con_studenti = number_format($fuis['sum_clil_con_studenti'],2);

$fuis_sostituzioni = number_format($fuis['sum_sostituzioni'],2);
$fuis_funzionale = number_format($fuis['sum_funzionale'],2);
$fuis_con_studenti = number_format($fuis['sum_con_studenti'],2);


// il totale da visualizzare include quello delle ore e quello assegnato e i viaggi: il clil a parte
$fuis_totale = number_format($fuis['sum_totale_ore'] + $fuis['sum_assegnato'] + $fuis['sum_viaggi'],2);
$fuis_clil_totale = number_format($fuis['sum_clil_funzionale'] + $fuis['sum_clil_con_studenti'],2);
debug('fuis_totale=' . $fuis_totale);
debug('fuis_clil_totale=' . $fuis_clil_totale);

$response = compact('fuis_viaggi', 'fuis_assegnato', 'fuis_ore', 'fuis_sostituzioni', 'fuis_funzionale', 'fuis_con_studenti', 'fuis_clil_funzionale', 'fuis_clil_con_studenti', 'fuis_totale', 'fuis_clil_totale');
echo json_encode($response);

?>
