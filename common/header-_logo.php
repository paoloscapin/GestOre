<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>

<?php
if (file_exists(__DIR__ . '/../version.php')) {
	include_once __DIR__ . '/../version.php';
} else {
	$__software_name = APPLICATION_NAME;
	$__software_version = 'unknown';
	$__software_release_date = 'unknown';
}

$changeLogLinkBegin = '<a href="../changelog.md" target="_blank" >';
$changeLogLinkEnd = '</a>';

?>

<div class="navbar-header">

	<div class="logo-tooltip-wrapper">
		<a href="../index.php" class="navbar-brand top-navbar-brand">
			<img style="height: 44px; margin-top: -10px;"
			     src="data:image/png;base64,<?php echo base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'logo.png'")); ?>"
			     alt="Logo">
		</a>

		<div class="release-tooltip">
			<strong><?php echo $__software_name; ?></strong><br>
			v<?php echo $__software_version; ?><br>
			<?php echo $__software_release_date; ?>
		</div>
	</div>

</div>