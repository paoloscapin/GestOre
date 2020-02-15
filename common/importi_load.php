<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

$query = "SELECT * FROM importo WHERE anno_scolastico_id = $__anno_scolastico_corrente_id;";
$importi = dbGetFirst($query);

if (! empty($importi)) {
    $__importo_id = $importi['id'];
    $__importo_fuis = $importi['fuis'];
    $__importo_fuis_clil = $importi['fuis_clil'];
    $__importo_bonus = $importi['bonus'];
} else {
    $__importo_id = -1;
    $__importo_fuis = 0;
    $__importo_fuis_clil = 0;
    $__importo_bonus = 0;
}
?>