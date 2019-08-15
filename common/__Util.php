<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/path.php';
require_once __DIR__ . '/__Session.php';
require_once __DIR__ . '/__Log.php';

// escape a string before posting to the db
function escapePost($parameter) {
    global $con;
    return mysqli_real_escape_string($con, $_POST[$parameter]);
}

// redirect to a specific url and die
function redirect($url) {
    global $__application_base_path;
    $landing = $__application_base_path . $url;
    ob_start ();
    header ( 'Location: ' . $landing );
    ob_end_flush ();
    die ();
}

// assicura che lo user abbia il ruolo richiesto
function ruoloRichiesto(...$ruoli) {
    global $__utente_ruolo;
    if (empty($__utente_ruolo)) {
        redirect("/error/unauthorized.php");
    }
    foreach ($ruoli as $ruolo) {
        if ($__utente_ruolo === $ruolo) {
            return;
        }
    }
    redirect("/error/unauthorized.php");
}

// Controlla se lo user ha il ruolo specificato
function haRuolo($ruolo) {
    global $__utente_ruolo;
    if (empty($__utente_ruolo)) {
        return false;
    }
    if ($__utente_ruolo === $ruolo) {
        return true;
    }
    return false;
}

// rimpiazza i caratteri speciali di una stringa in modo da poterla passare come parametro a js
function str2js($str) {
    return preg_replace("/\r\n|\r|\n/",'<br/>',str_replace("'", "\'", $str));
}

?>