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
$current_http_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
$toSearch = '/' . APPLICATION_NAME;
$__application_base_path = substr ( $uriBasePath, 0, strpos ( $uriBasePath, $toSearch ) + strlen ( $toSearch ) );
$__application_common_path = $__application_base_path.'/common';
$__common_include_path = '../common';
$__http_base_link = substr ( $current_http_link, 0, strpos ( $current_http_link, $toSearch ) + strlen ( $toSearch ) );
?>