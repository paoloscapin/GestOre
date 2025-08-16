<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>

<?php 
	function isMobile()
	{
		return preg_match("/Android|iPhone|iPad|iPod|Opera Mini|IEMobile|Mobile|BlackBerry|webOS/i", $_SERVER['HTTP_USER_AGENT']);
	}

	if (isMobile()) {
		include 'notlogged_mobile.php';
	} else {
		include 'notlogged_desktop.php';
	}
	?>
