<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>

<?php
if(file_exists(__DIR__ . '/../versiona.php')) {
	include_once __DIR__ . '/../versiona.php';
} else {
	$__software_name = APPLICATION_NAME;
	$__software_version = 'unknown';
	$__software_release_date = 'unknown';
}
?>

	<div class="navbar-header">
		<div class="releaseversion">&nbsp;
			<span class="releaseversiontext"><p><strong><?php echo $__software_name;?></strong></p><hr><p>Version: <?php echo $__software_version;?></p><p>Release date: <?php echo $__software_release_date;?></p></span>
		</div>
			<a href="<?php echo $__application_base_path; ?>/index.php" class="navbar-brand top-navbar-brand" >
				<img style="height: 44px; margin-top: -10px;" src="data:image/png;base64,<?php echo base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'logo.png'")); ?>" alt="Logo">
			</a>
			<a class="navbar-brand top-navbar-brand" href="#"> </a>
	</div>
