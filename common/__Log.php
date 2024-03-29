<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/Log.php';

$__logLevel = PEAR_LOG_INFO;
if ($__settings->log->debug) {
    $__logLevel = PEAR_LOG_DEBUG;
}

$fileName = '';
if ($__settings->log->logIntoAppFolder) {
    $fileName = __DIR__ . "/../log/";
}
$fileName .= $__settings->log->logFile;

$__logger = Log::factory('file', $fileName, '', array("timeFormat"=>$__settings->log->timeFormat), $__logLevel);

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

function rotateLog() {
    global $fileName;
    global $__logger;
    $rotateFileName = $fileName . date("Y-m-d_H.i.s").'.log';
    $__logger->info("rotating into $rotateFileName");
    $__logger->flush();
    $__logger->close();
    rename($fileName, $rotateFileName);
    $__logger->open();
    $__logger->info("old log was saved into $rotateFileName");
}

?>