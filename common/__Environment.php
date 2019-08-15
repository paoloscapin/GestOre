<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

define('__development_environment', '__development_environment');

define('__https', 'on');

if (defined('__production_environment')) {
    // durata cookies: un anno
    define('DURATA_SESSIONE', 60 * 60 * 24 * 365);

    // db connection
    define('DB_HOST', '');
    define('DB_USER', '');
    define('DB_PASSWORD', '');
    define('DB_DATABASE', '');
} else {
    // durata cookies: un ora
    define('DURATA_SESSIONE', 60 * 60);

    // db connection
    define('DB_HOST', 'localhost');
    define('DB_USER', 'gestore');
    define('DB_PASSWORD', 'password');
    define('DB_DATABASE', 'gestore');
}
