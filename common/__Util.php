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

// escape a string in general
function escapeString($string) {
    global $con;
    return mysqli_real_escape_string($con, $string);
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
    // admin viene sempre autorizzato
    if ($__utente_ruolo === 'admin') {
        return;
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
    // admin viene sempre autorizzato
    if ($__utente_ruolo === 'admin') {
        return true;
    }
    if ($__utente_ruolo === $ruolo) {
        return true;
    }
    return false;
}

// rimpiazza i caratteri speciali di una stringa in modo da poterla passare come parametro a js
function str2js($str) {
    return preg_replace("/\r\n|\r|\n/",'<br/>',str_replace("'", "\'", str_replace("\"", "", $str)));
}

// rimpiazza i caratteri non stampabili di una stringa con un punto in modo da poterla passare come parametro a js
function js_escape($str) {
	return preg_replace('/[^A-Za-z0-9\-\*\ \,\.\:\;\(\)\=\?\/]/', '.', $str);
}

// ricupera una label se ridefinita nel json
function getLabel($label) {
    global $__settings;
    if (property_exists($__settings->label, $label)) {
        return $__settings->label->$label;
    }
    return $label;
}

// scrive una label controllando se nel json viene ridefinita
function echoLabel($label) {
    echo getLabel($label);
}

// ricupera un valore se definito nel json oppure default
function getSettingsValue($section, $name, $default) {
    global $__settings;
    if (! property_exists($__settings, $section)) {
        return $default;
    }
    if (! property_exists($__settings->$section, $name)) {
        return $default;
    }
    return $__settings->$section->$name;
}

// ritorna il camelcase di una stringa
function camelCase($src) {
    return ucwords(strtolower($src));
}

?>