<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// read JSON settings file
$json = file_get_contents(__DIR__ . '/../GestOre.json');

// decode JSON
$json_data = json_decode($json,false);

$__settings = json_decode($json);

?>