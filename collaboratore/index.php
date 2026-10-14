<?php

/**
 *  This file is part of GestOre
 *  @author     Sebastiano Santini <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>

<!DOCTYPE html>
<html>
<head>
	<title>GestOre Collaboratore scolastico</title>
<?php
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
ruoloRichiesto('collaboratore','segreteria-didattica','dirigente');
$session->set ( 'admin_ruolo', 'collaboratore' );
$__admin_ruolo = $session->get ( 'collaboratore' );
?>
</head>

<body >
<?php require_once '../common/header-collaboratore.php'; ?>
<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">
</div>

</body>
</html>