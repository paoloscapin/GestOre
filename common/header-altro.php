<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
?>
<?php if(getSettingsValue('config','extraComandi', false)) : ?>
	<a href="<?php echo $__application_base_path; ?>/docente/extraComandi.php" class="btn btn-default navbar-btn btn-yellow4" role="button"><span class="glyphicon glyphicon-option-horizontal"></span>&ensp;<?php echoLabel('Altro'); ?> </a>
<?php endif; ?>