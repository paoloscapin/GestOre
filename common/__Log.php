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

$fileNameGmail = '';
if ($__settings->log->logIntoAppFolder) {
    $fileNameGmail = __DIR__ . "/../log/";
}
$fileNameGmail .= ($__settings->log->logGmailFile ?? 'gmail.log');

$fileNameProfili = '';
if ($__settings->log->logIntoAppFolder) {
    $fileNameProfili = __DIR__ . "/../log/";
}
$fileNameProfili .= ($__settings->log->logProfiliFile ?? 'profili.log');

$fileNameIscrizioniClassi = '';
if ($__settings->log->logIntoAppFolder) {
    $fileNameIscrizioniClassi = __DIR__ . "/../log/";
}
$fileNameIscrizioniClassi .= ($__settings->log->logIscrizioniClassiFile ?? 'iscrizioni_classi.log');

$fileNameFormazioneClassi = '';
if ($__settings->log->logIntoAppFolder) {
    $fileNameFormazioneClassi = __DIR__ . "/../log/";
}
$fileNameFormazioneClassi .= ($__settings->log->logFormazioneClassiFile ?? 'formazione_classi.log');

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
$__logger_gmail = Log::factory(
    'file',
    $fileNameGmail,
    '',
    array("timeFormat" => $__settings->log->timeFormat),
    $__logLevel
);
$__logger_iscrizioni_classi = Log::factory(
    'file',
    $fileNameIscrizioniClassi,
    '',
    array("timeFormat" => $__settings->log->timeFormat),
    $__logLevel
);
$__logger_formazione_classi = Log::factory(
    'file',
    $fileNameFormazioneClassi,
    '',
    array("timeFormat" => $__settings->log->timeFormat),
    $__logLevel
);

$__logChannel = 'app';

function setLogChannel(string $channel): void
{
    global $__logChannel;
    $channel = strtolower(trim($channel));
    $__logChannel = $channel !== '' ? $channel : 'app';
}

function getLogChannel(): string
{
    global $__logChannel;
    $channel = strtolower(trim((string)($__logChannel ?? 'app'))) ?: 'app';
    if ($channel === 'app') {
        $autoChannel = autoLogChannelForCurrentRequest();
        if ($autoChannel !== '') {
            return $autoChannel;
        }
    }
    return $channel;
}

function autoLogChannelForCurrentRequest(): string
{
    $script = strtolower(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '')));
    $page = strtolower(basename($script));

    if (strpos($page, 'formazioneclassi') === 0) {
        return 'formazione_classi';
    }

    if (
        strpos($script, '/iscrizioni/') !== false ||
        strpos($page, 'iscrizioni') === 0 ||
        strpos($page, 'entrateuscite') === 0 ||
        strpos($page, 'entrate_uscite') === 0 ||
        strpos($page, 'movimenti') === 0 ||
        strpos($page, 'studentimovimenti') === 0 ||
        strpos($page, 'colloquigenitori') === 0
    ) {
        return 'iscrizioni_classi';
    }

    return '';
}

function initCronLog(string $name = ''): void
{
    static $registered = false;
    static $startedAt = 0.0;
    static $cronName = '';

    $cronName = trim($name) !== '' ? trim($name) : basename($_SERVER['PHP_SELF'] ?? 'cli');
    $startedAt = microtime(true);
    setLogChannel('cron');
    infocron("Avvio $cronName");

    if ($registered) {
        return;
    }

    $registered = true;
    register_shutdown_function(function () use (&$startedAt, &$cronName) {
        $lastError = error_get_last();
        if (is_array($lastError) && in_array((int)($lastError['type'] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            errorcron(
                "Errore fatale $cronName: " .
                ($lastError['message'] ?? '') .
                ' in ' . ($lastError['file'] ?? '') .
                ':' . ($lastError['line'] ?? '')
            );
        }

        $elapsed = $startedAt > 0 ? round(microtime(true) - $startedAt, 3) : 0;
        infocron("Fine $cronName durata={$elapsed}s");
    });
}

function logChannelLogger(string $channel)
{
    global $__logger;
    global $__logger_cron;
    global $__logger_import_sostituzioni;
    global $__logger_telegram;
    global $__logger_calendar;
    global $__logger_calendar_mbapp;
    global $__logger_gmail;
    global $__logger_iscrizioni_classi;
    global $__logger_formazione_classi;

    switch (strtolower(trim($channel))) {
        case 'cron':
            return $__logger_cron;
        case 'import_sostituzioni':
        case 'sostituzioni':
            return $__logger_import_sostituzioni;
        case 'telegram':
            return $__logger_telegram;
        case 'google_calendar':
        case 'calendar':
            return $__logger_calendar;
        case 'google_calendar_mbapp':
        case 'calendar_mbapp':
            return $__logger_calendar_mbapp;
        case 'gmail':
        case 'ticket_mail':
            return $__logger_gmail;
        case 'formazione_classi':
            return $__logger_formazione_classi;
        case 'iscrizioni_classi':
        case 'iscrizioni':
        case 'entrate_uscite':
        case 'movimenti':
        case 'studenti_movimenti':
        case 'colloqui_genitori':
            return $__logger_iscrizioni_classi;
        default:
            return $__logger;
    }
}

function dbDebug(string $message): void
{
    global $__username;
    $page = basename($_SERVER['PHP_SELF'] ?? 'cli');
    $channel = getLogChannel();
    $logger = logChannelLogger($channel);
    $logger->debug("$page: [$__username] [DB] $message");
}

function dbError(string $message): void
{
    global $__username;
    $page = basename($_SERVER['PHP_SELF'] ?? 'cli');
    $channel = getLogChannel();
    $logger = logChannelLogger($channel);
    $logger->err("$page: [$__username] [DB] $message");
}

function debug($message) {
    global $__username;
    $page = basename($_SERVER['PHP_SELF'] ?? 'cli');
    $logger = logChannelLogger(getLogChannel());
    $logger->debug("$page: [$__username] $message");
}

function info($message) {
    global $__username;
    $page = basename($_SERVER['PHP_SELF'] ?? 'cli');
    $logger = logChannelLogger(getLogChannel());
    $logger->info("$page: [$__username] $message");
}

function infoLogin($message) {
    global $__logger_login;
    $page = basename ( $_SERVER ['PHP_SELF'] );
    $__logger_login->info("$page: $message");
}

function warning($message) {
    global $__username;
    $page = basename($_SERVER['PHP_SELF'] ?? 'cli');
    $logger = logChannelLogger(getLogChannel());
    $logger->warning("$page: [$__username] $message");
}

function error($message) {
    global $__username;
    $page = basename($_SERVER['PHP_SELF'] ?? 'cli');
    $logger = logChannelLogger(getLogChannel());
    $logger->err("$page: [$__username] $message");
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

    global $fileNameGmail;
    global $__logger_gmail;
    $rotateFileName = buildRotatedLogFileName($fileNameGmail);
    $__logger_gmail->info("rotating into $rotateFileName");
    $__logger_gmail->flush();
    $__logger_gmail->close();
    if (file_exists($fileNameGmail)) {
        rename($fileNameGmail, $rotateFileName);
    }
    $__logger_gmail->open();
    $__logger_gmail->info("old log was saved into $rotateFileName");

    global $fileNameIscrizioniClassi;
    global $__logger_iscrizioni_classi;
    $rotateFileName = buildRotatedLogFileName($fileNameIscrizioniClassi);
    $__logger_iscrizioni_classi->info("rotating into $rotateFileName");
    $__logger_iscrizioni_classi->flush();
    $__logger_iscrizioni_classi->close();
    if (file_exists($fileNameIscrizioniClassi)) {
        rename($fileNameIscrizioniClassi, $rotateFileName);
    }
    $__logger_iscrizioni_classi->open();
    $__logger_iscrizioni_classi->info("old log was saved into $rotateFileName");

    global $fileNameFormazioneClassi;
    global $__logger_formazione_classi;
    $rotateFileName = buildRotatedLogFileName($fileNameFormazioneClassi);
    $__logger_formazione_classi->info("rotating into $rotateFileName");
    $__logger_formazione_classi->flush();
    $__logger_formazione_classi->close();
    if (file_exists($fileNameFormazioneClassi)) {
        rename($fileNameFormazioneClassi, $rotateFileName);
    }
    $__logger_formazione_classi->open();
    $__logger_formazione_classi->info("old log was saved into $rotateFileName");

    global $fileNameProfili;
    $rotateFileName = buildRotatedLogFileName($fileNameProfili);
    if (file_exists($fileNameProfili)) {
        rename($fileNameProfili, $rotateFileName);
        @file_put_contents(
            $fileNameProfili,
            json_encode([
                'ts' => date('Y-m-d H:i:s'),
                'level' => 'info',
                'page' => basename((string)($_SERVER['PHP_SELF'] ?? 'cli')),
                'action' => 'profilo_log_rotated',
                'details' => ['saved_into' => basename($rotateFileName)],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
        infocron("old profili log was saved into $rotateFileName");
    }
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

/**
 * ================================
 * LOG GMAIL / TICKET MAIL
 * ================================
 */

function infoGmail(string $msg): void
{
    global $__logger_gmail;
    $__logger_gmail->info('[GMAIL] ' . $msg);
}

function warningGmail(string $msg): void
{
    global $__logger_gmail;
    $__logger_gmail->warning('[GMAIL] ' . $msg);
}

function errorGmail(string $msg): void
{
    global $__logger_gmail;
    $__logger_gmail->err('[GMAIL] ' . $msg);
}

function debugGmail(string $msg): void
{
    global $__logger_gmail;
    $__logger_gmail->debug('[GMAIL] ' . $msg);
}

/**
 * ================================
 * LOG ISCRIZIONI / ENTRATE-USCITE
 * ================================
 */

function infoIscrizioniClassi(string $msg): void
{
    global $__logger_iscrizioni_classi;
    $__logger_iscrizioni_classi->info('[ISCRIZIONI_CLASSI] ' . $msg);
}

function warningIscrizioniClassi(string $msg): void
{
    global $__logger_iscrizioni_classi;
    $__logger_iscrizioni_classi->warning('[ISCRIZIONI_CLASSI] ' . $msg);
}

function errorIscrizioniClassi(string $msg): void
{
    global $__logger_iscrizioni_classi;
    $__logger_iscrizioni_classi->err('[ISCRIZIONI_CLASSI] ' . $msg);
}

function debugIscrizioniClassi(string $msg): void
{
    global $__logger_iscrizioni_classi;
    $__logger_iscrizioni_classi->debug('[ISCRIZIONI_CLASSI] ' . $msg);
}

/**
 * ================================
 * LOG FORMAZIONE CLASSI
 * ================================
 */

function infoFormazioneClassi(string $msg): void
{
    global $__logger_formazione_classi;
    $__logger_formazione_classi->info('[FORMAZIONE_CLASSI] ' . $msg);
}

function warningFormazioneClassi(string $msg): void
{
    global $__logger_formazione_classi;
    $__logger_formazione_classi->warning('[FORMAZIONE_CLASSI] ' . $msg);
}

function errorFormazioneClassi(string $msg): void
{
    global $__logger_formazione_classi;
    $__logger_formazione_classi->err('[FORMAZIONE_CLASSI] ' . $msg);
}

function debugFormazioneClassi(string $msg): void
{
    global $__logger_formazione_classi;
    $__logger_formazione_classi->debug('[FORMAZIONE_CLASSI] ' . $msg);
}

?>
