<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// Link di Home per i vari ruoli
function getHomeLink() {
    global $__utente_ruolo;
    global $__application_base_path;
     // TODO: si potrebbe usare il file json di setting per questo?
    $homelink = [
        'admin' =>
            $__application_base_path."/admin/index.php",
        'dirigente' =>
            $__application_base_path."/dirigente/index.php",
        'segreteria-didattica' =>
            $__application_base_path."/segreteria/index.php",
        'docente' =>
            $__application_base_path."/docente/index.php"
        ];

    if (array_key_exists($__utente_ruolo, $homelink) ){
        return $homelink[$__utente_ruolo];
    } else {
            return "#";
    }
}
// favicon ref
echo '<link rel="icon" href="'.$__application_base_path.'/ore-32.png" />';

?>
