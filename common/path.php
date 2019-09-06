<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// cerca il base path della applicazione
define ( 'APPLICATION_NAME', 'GestOre' );

$uriBasePath = $_SERVER['REQUEST_URI'];
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
// echo 'uriBasePath=' . $uriBasePath . '</br>';
$current_http_link = "$protocol://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
// echo 'current_http_link=' . $current_http_link . '</br>';

$toSearch = '/' . APPLICATION_NAME;

// se non trova il nome dell'applicazione, e' installato sulla root
$pos = strpos ( $uriBasePath, $toSearch );
if ($pos === false) {
    $__application_base_path = '';
    $__application_common_path = '/common';
} else {
    $__application_base_path = substr ( $uriBasePath, 0, $pos  + strlen ( $toSearch ));
    $__application_common_path = $__application_base_path.'/common';
}

// per gli include e' diverso, bisogna tornare indietro con ..
$__common_include_path = '../common';

// cerchiamo il base link
// se non trova il nome dell'applicazione, e' installato sulla root
$posLink = strpos ( $current_http_link, $toSearch );
if ($posLink === false) {
    $__http_base_link = "$protocol://{$_SERVER['SERVER_NAME']}";
} else {
    $__http_base_link = substr ( $current_http_link, 0, $posLink  + strlen ( $toSearch ));
}

// echo '__application_base_path=' . $__application_base_path . '</br>';
// echo '__application_common_path=' . $__application_common_path . '</br>';
// echo '__http_base_link=' . $__http_base_link . '</br>';
?>