<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/__Environment.php';
require_once __DIR__ . '/path.php';
require_once __DIR__ . '/__Log.php';
require_once __DIR__ . '/__Util.php';
require_once __DIR__ . '/__GoogleClientConfig.php';

info('utente ' . $__username . ': logged out');
$session->logout();
//Unset token and user data from session
unset($_SESSION['token']);
unset($_SESSION['userData']);

//Reset OAuth access token
$gClient->revokeToken();

//Destroy entire session
session_destroy();

//Redirect to homepage
redirect('/' . 'index.php');
?>
