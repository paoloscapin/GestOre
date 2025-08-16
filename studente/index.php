<?php


/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';

?>

<!DOCTYPE html>
<html>
<head>
		<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>GestOre studente</title>
<?php
require_once '../common/header-common.php';
require_once '../common/style.php';
ruoloRichiesto('studente', 'segreteria-didattica', 'dirigente');
?>
</head>

<body >
<?php 
	function isMobile()
	{
		return preg_match("/Android|iPhone|iPad|iPod|Opera Mini|IEMobile|Mobile|BlackBerry|webOS/i", $_SERVER['HTTP_USER_AGENT']);
	}

	if (isMobile()) {
		require_once '../common/header-studente-mobile.php';
	} else {
		require_once '../common/header-studente.php';
	}
	?>

<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">
</div>

</body>
</html>