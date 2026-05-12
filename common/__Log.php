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

$fileNameTelegram = '';
if ($__settings->log->logIntoAppFolder) {
    $fileNameTelegram = __DIR__ . "/../log/";
}
$fileNameTelegram .= $__settings->log->logTelegramFile;

$fileNameCalendar = '';
if ($__settings->log->logIntoAppFolder) {
    $fileNameCalendar = __DIR__ . "/../log/";
}
$fileNameCalendar .= $__settings->log->logGoogleCalendarFile;

$fileNameCalendarMBApp = '';
if ($__settings->log->logIntoAppFolder) {
    $fileNameCalendarMBApp = __DIR__ . "/../log/";
}
$fileNameCalendarMBApp .= $__settings->log->logGoogleCalendarMBAppFile;

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
$__logger_telegram = Log::factory(
    'file',
    $fileNameTelegram,
    '',
    array("timeFormat" => $__settings->log->timeFormat),
    PEAR_LOG_INFO
);
$__logger_calendar = Log::factory(
    'file',
    $fileNameCalendar,
    '',
    array("timeFormat" => $__settings->log->timeFormat),
    PEAR_LOG_INFO
);
$__logger_calendar_mbapp = Log::factory(
    'file',
    $fileNameCalendarMBApp,
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

    global $fileNameTelegram;
    global $__logger_telegram;
    $rotateFileName = buildRotatedLogFileName($fileNameTelegram);
    $__logger_telegram->info("rotating into $rotateFileName");
    $__logger_telegram->flush();
    $__logger_telegram->close();
    if (file_exists($fileNameTelegram)) {
        rename($fileNameTelegram, $rotateFileName);
    }
    $__logger_telegram->open();
    $__logger_telegram->info("old log was saved into $rotateFileName");

    global $fileNameCalendar;
    global $__logger_calendar;
    $rotateFileName = buildRotatedLogFileName($fileNameCalendar);
    $__logger_calendar->info("rotating into $rotateFileName");
    $__logger_calendar->flush();
    $__logger_calendar->close();
    if (file_exists($fileNameCalendar)) {
        rename($fileNameCalendar, $rotateFileName);
    }   
    $__logger_calendar->open();
    $__logger_calendar->info("old log was saved into $rotateFileName");

    global $fileNameCalendarMBApp;
    global $__logger_calendar_mbapp;
    $rotateFileName = buildRotatedLogFileName($fileNameCalendarMBApp);
    $__logger_calendar_mbapp->info("rotating into $rotateFileName");
    $__logger_calendar_mbapp->flush();
    $__logger_calendar_mbapp->close();
    if (file_exists($fileNameCalendarMBApp)) {
        rename($fileNameCalendarMBApp, $rotateFileName);
    }
    $__logger_calendar_mbapp->open();
    $__logger_calendar_mbapp->info("old log was saved into $rotateFileName");
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

/**
 * ================================
 * LOG TELEGRAM
 * ================================
 */

function infoTelegram(string $msg): void
{
    global $__logger_telegram;
    $__logger_telegram->info('[TELEGRAM] ' . $msg);
}

function warningTelegram(string $msg): void
{
    global $__logger_telegram;
    $__logger_telegram->warning('[TELEGRAM] ' . $msg);
}

function errorTelegram(string $msg): void
{
    global $__logger_telegram;
    $__logger_telegram->err('[TELEGRAM] ' . $msg);
}

/**
 * ================================
 * LOG GOOGLE CALENDAR
 * ================================
 */

function infoGoogleCalendar(string $msg): void
{
    global $__logger_calendar;
    $__logger_calendar->info('[GOOGLE_CALENDAR] ' . $msg);
}

function warningGoogleCalendar(string $msg): void
{
    global $__logger_calendar;
    $__logger_calendar->warning('[GOOGLE_CALENDAR] ' . $msg);
}

function errorGoogleCalendar(string $msg): void
{
    global $__logger_calendar;
    $__logger_calendar->err('[GOOGLE_CALENDAR] ' . $msg);
}

function debugGoogleCalendar(string $msg): void
{
    global $__logger_calendar;
    $__logger_calendar->debug('[GOOGLE_CALENDAR] ' . $msg);
}

function infoGoogleCalendarMBApp(string $msg): void
{
    global $__logger_calendar_mbapp;
    $__logger_calendar_mbapp->info('[GOOGLE_CALENDAR_MBAPP] ' . $msg);
}

function warningGoogleCalendarMBApp(string $msg): void
{
    global $__logger_calendar_mbapp;
    $__logger_calendar_mbapp->warning('[GOOGLE_CALENDAR_MBAPP] ' . $msg);
}

function errorGoogleCalendarMBApp(string $msg): void
{
    global $__logger_calendar_mbapp;
    $__logger_calendar_mbapp->err('[GOOGLE_CALENDAR_MBAPP] ' . $msg);
}

function debugGoogleCalendarMBApp(string $msg): void
{
    global $__logger_calendar_mbapp;
    $__logger_calendar_mbapp->debug('[GOOGLE_CALENDAR_MBAPP] ' . $msg);
}

?>