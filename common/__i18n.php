<?php
$dir = __DIR__; //per xdebug

require_once $dir . '/__Settings.php';

function __($text, $plural=null, $number=null) {
    if (!isset($plural)) {
        return _($text);
    }
    return ngettext($text, $plural, $number);
}

$language = $__settings->locale->language;
putenv("LANGUAGE=" . $language);
setlocale(LC_ALL, $language);

$domain = $__settings->locale->domain;
$locales_dir = "$dir/../".$__settings->locale->language_folder;

bindtextdomain($domain, $locales_dir);
bind_textdomain_codeset($domain, 'UTF-8');

textdomain($domain);
