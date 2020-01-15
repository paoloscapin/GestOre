<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

 // controlla se deve gestire i minuti
$__minuti = getSettingsValue('config','minuti', false);

// include le functions di utilita'
require_once __DIR__ . '/__MinutiFunction.php';
?>

<script type="text/javascript">var __minuti = "<?= $__minuti ?>";</script>
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/js/__minuti.js"></script>
