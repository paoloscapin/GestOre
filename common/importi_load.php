<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

$query = "SELECT * FROM importo WHERE anno_scolastico_id = $__anno_scolastico_corrente_id;";
$__importi = dbGetFirst($query);

if (! empty($__importi)) {
    $__importo_id = $__importi['id'];
    $__importo_fuis = $__importi['fuis'];
    $__importo_fuis_clil = $__importi['fuis_clil'];
    $__importo_bonus = $__importi['bonus'];
    $__importo_ore_con_studenti = $__importi['importo_ore_con_studenti'];
    $__importo_ore_funzionali = $__importi['importo_ore_funzionali'];
    $__importo_ore_corsi_di_recupero = $__importi['importo_ore_corsi_di_recupero'];
    $__importo_diaria_con_pernottamento = $__importi['importo_diaria_con_pernottamento'];
    $__importo_diaria_senza_pernottamento = $__importi['importo_diaria_senza_pernottamento'];
} else {
    $__importo_id = -1;
    $__importo_fuis = 0;
    $__importo_fuis_clil = 0;
    $__importo_bonus = 0;
}
?>