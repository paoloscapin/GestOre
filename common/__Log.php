<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/__Environment.php';
if (defined('__production_environment')) {
    require_once __DIR__ . '/Log.php';

    $level = PEAR_LOG_INFO;
    $__logger = Log::factory('file', __DIR__ . '/../log/gestionale.log', '', array("timeFormat"=>"%d/%m/%Y - %H:%M:%S"), $level);

    function console_log($message, $data = "") {
    }
    function console_log_data($message, $data = "") {
    }
    function debug($message) {
        global $__logger;
        global $__username;
        $page = basename ( $_SERVER ['PHP_SELF'] );
        $__logger->debug("$page: [$__username] $message");
    }
    function info($message) {
        global $__logger;
        global $__username;
        $page = basename ( $_SERVER ['PHP_SELF'] );
        $__logger->info("$page: [$__username] $message");
    }
    function warning($message) {
        global $__logger;
        global $__username;
        $page = basename ( $_SERVER ['PHP_SELF'] );
        $__logger->warning("$page: [$__username] $message");
    }
    function error($message) {
        global $__logger;
        global $__username;
        $page = basename ( $_SERVER ['PHP_SELF'] );
        $__logger->err("$page: [$__username] $message");
    }
} else if (defined('__test_environment') || defined('__development_environment')) {
    require_once __DIR__ . '/Log.php';

    $level = PEAR_LOG_DEBUG;
    $__logger = Log::factory('file', 'd:/Temp/log/gestionale.log', '', array("timeFormat"=>"%d/%m/%Y - %H:%M:%S"), $level);

    function console_log($message, $data = "") {
    }
    function console_log_data($message, $data = "") {
    }
    function debug($message) {
        global $__logger;
        global $__username;
        $page = basename ( $_SERVER ['PHP_SELF'] );
        $__logger->debug("$page: [$__username] $message");
    }
    function info($message) {
    	global $__logger;
    	global $__username;
    	$page = basename ( $_SERVER ['PHP_SELF'] );
        $__logger->info("$page: [$__username] $message");
    }
    function warning($message) {
        global $__logger;
        global $__username;
        $page = basename ( $_SERVER ['PHP_SELF'] );
        $__logger->warning("$page: [$__username] $message");
    }
    function error($message) {
        global $__logger;
        global $__username;
        $page = basename ( $_SERVER ['PHP_SELF'] );
        $__logger->err("$page: [$__username] $message");
    }
}

?>