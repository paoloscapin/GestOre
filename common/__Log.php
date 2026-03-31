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

$fileNameLogin = '';
if ($__settings->log->logIntoAppFolder) {
    $fileNameLogin = __DIR__ . "/../log/";
}
$fileNameLogin .= $__settings->log->logLoginFile;

$fileNameCron = '';
if ($__settings->log->logIntoAppFolder) {
    $fileNameCron = __DIR__ . "/../log/";
}
$fileNameCron .= $__settings->log->logCronFile;

$fileNameImportSostituzioni = '';
if ($__settings->log->logIntoAppFolder) {
    $fileNameImportSostituzioni = __DIR__ . "/../log/";
}
$fileNameImportSostituzioni .= $__settings->log->logImportSostituzioniFile;

$__logger = Log::factory('file', $fileName, '', array("timeFormat"=>$__settings->log->timeFormat), $__logLevel);
$__logger_login = Log::factory('file', $fileNameLogin, '', array("timeFormat"=>$__settings->log->timeFormat), PEAR_LOG_INFO);
$__logger_cron = Log::factory('file', $fileNameCron, '', array("timeFormat"=>$__settings->log->timeFormat), PEAR_LOG_INFO);
$__logger_import_sostituzioni = Log::factory(
    'file',
    $fileNameImportSostituzioni,
    '',
    array("timeFormat" => $__settings->log->timeFormat),
    PEAR_LOG_INFO
);

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

function infoLogin($message) {
    global $__logger_login;
    $page = basename ( $_SERVER ['PHP_SELF'] );
    $__logger_login->info("$page: $message");
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

function buildRotatedLogFileName($fullPath)
{
    $dir = dirname($fullPath);
    $info = pathinfo($fullPath);

    $filename = isset($info['filename']) ? $info['filename'] : 'log';
    $extension = isset($info['extension']) && $info['extension'] !== '' ? $info['extension'] : 'log';

    $dt = new DateTime('now', new DateTimeZone('Europe/Rome'));
    $timestamp = $dt->format('d_m_Y_H_i_s');

    return $dir . '/' . $filename . '_' . $timestamp . '.' . $extension;
}

function rotateLog() {
    global $fileName;
    global $__logger;

    $rotateFileName = buildRotatedLogFileName($fileName);
    $__logger->info("rotating into $rotateFileName");
    $__logger->flush();
    $__logger->close();
    if (file_exists($fileName)) {
        rename($fileName, $rotateFileName);
    }
    $__logger->open();
    $__logger->info("old log was saved into $rotateFileName");

    global $fileNameLogin;
    global $__logger_login;

    $rotateFileName = buildRotatedLogFileName($fileNameLogin);
    $__logger_login->info("rotating into $rotateFileName");
    $__logger_login->flush();
    $__logger_login->close();
    if (file_exists($fileNameLogin)) {
        rename($fileNameLogin, $rotateFileName);
    }
    $__logger_login->open();
    $__logger_login->info("old log was saved into $rotateFileName");

    global $fileNameCron;
    global $__logger_cron;

    $rotateFileName = buildRotatedLogFileName($fileNameCron);
    $__logger_cron->info("rotating into $rotateFileName");
    $__logger_cron->flush();
    $__logger_cron->close();
    if (file_exists($fileNameCron)) {
        rename($fileNameCron, $rotateFileName);
    }
    $__logger_cron->open();
    $__logger_cron->info("old log was saved into $rotateFileName");

    global $fileNameImportSostituzioni;
    global $__logger_import_sostituzioni;

    $rotateFileName = buildRotatedLogFileName($fileNameImportSostituzioni);
    $__logger_import_sostituzioni->info("rotating into $rotateFileName");
    $__logger_import_sostituzioni->flush();
    $__logger_import_sostituzioni->close();
    if (file_exists($fileNameImportSostituzioni)) {
        rename($fileNameImportSostituzioni, $rotateFileName);
    }
    $__logger_import_sostituzioni->open();
    $__logger_import_sostituzioni->info("old log was saved into $rotateFileName");
}
/**
 * ================================
 * LOG CRON (wrapper dedicati)
 * ================================
 */

function infocron(string $msg): void
{
    global $__logger_cron;
    $__logger_cron->info('[CRON] ' . $msg);
}

function warningcron(string $msg): void
{
    global $__logger_cron;
    $__logger_cron->warning('[CRON] ' . $msg);
}

function errorcron(string $msg): void
{
    global $__logger_cron;
    $__logger_cron->err('[CRON] ' . $msg);
}

/**
 * ================================
 * LOG IMPORT SOSTITUZIONI
 * ================================
 */

function infoimportsost(string $msg): void
{
    global $__logger_import_sostituzioni;
    $__logger_import_sostituzioni->info('[IMPORT_SOSTITUZIONI] ' . $msg);
}

function warningimportsost(string $msg): void
{
    global $__logger_import_sostituzioni;
    $__logger_import_sostituzioni->warning('[IMPORT_SOSTITUZIONI] ' . $msg);
}

function errorimportsost(string $msg): void
{
    global $__logger_import_sostituzioni;
    $__logger_import_sostituzioni->err('[IMPORT_SOSTITUZIONI] ' . $msg);
}

?>