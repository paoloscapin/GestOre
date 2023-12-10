<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST)) {
    $__importo_id = $_POST['importo_id'];
    $__importo_fuis = $_POST['importo_fuis'];
    $__importo_fuis_clil = $_POST['importo_fuis_clil'];
    $__importo_fuis_orientamento = $_POST['importo_fuis_orientamento'];
    $__importo_bonus = $_POST['importo_bonus'];

    if ($__importo_id > 0) {
        $query = "UPDATE importo SET fuis = '$__importo_fuis', fuis_clil = '$__importo_fuis_clil', fuis_orientamento = '$__importo_fuis_orientamento', bonus = '$__importo_bonus' WHERE id = '$__importo_id'";
        dbExec($query);
        info("aggiornato importo id=$id fuis=$__importo_fuis fuis_clil=$__importo_fuis_clil fuis_orientamento=$__importo_fuis_orientamento bonus=$__importo_bonus");
    } else {
        $query = "INSERT INTO importo (fuis, fuis_clil, fuis_orientamento, bonus, anno_scolastico_id) VALUES ('$__importo_fuis', '$__importo_fuis_clil', '$__importo_fuis_orientamento', '$__importo_bonus', $__anno_scolastico_corrente_id)";
        dbExec($query);
        $id = dblastId();
        info("inserito importo id=$id fuis=$__importo_fuis fuis_clil=$__importo_fuis_clil fuis_orientamento=$__importo_fuis_orientamento bonus=$__importo_bonus anno_scolastico_id=$__anno_scolastico_corrente_id");
    }
}
?>