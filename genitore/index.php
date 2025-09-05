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
	<title>GestOre genitore</title>
	<?php
	require_once '../common/header-common.php';
	require_once '../common/style.php';
	ruoloRichiesto('genitore', 'segreteria-didattica', 'dirigente');
	?>
</head>

<body>
	<?php
	function isMobile()
	{
		return preg_match("/Android|iPhone|iPad|iPod|Opera Mini|IEMobile|Mobile|BlackBerry|webOS/i", $_SERVER['HTTP_USER_AGENT']);
	}

	$query = "SELECT COUNT(s.id) AS num_studenti FROM studente s INNER JOIN genitori_studenti gs ON gs.id_studente = s.id INNER JOIN genitori g ON g.id = gs.id_genitore WHERE s.attivo = '1' AND g.id = $__genitore_id";
	$ris = dbGetValue($query);
	if (isMobile()) {
		header("location: ../error/error_mobile.php?message=Non hai studenti attivi associati al tuo account.&num_studenti=$ris");
	} else {
		header("location: ../error/error_desktop.php?message=Non hai studenti attivi associati al tuo account.&num_studenti=$ris");
	}
	
	if (isMobile()) {
		require_once '../common/header-genitore-mobile.php';
	} else {
		require_once '../common/header-genitore.php';
	}
	?>
	<!-- Content Section -->
	<div class="container-fluid" style="margin-top:60px">
	</div>
</body>

</html>