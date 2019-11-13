<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// controlla se deve gestire i minuti
$__minuti = getSettingsValue('config','minuti', false);
debug("minuti=$__minuti");
?>

<script type="text/javascript">var __minuti = "<?= $__minuti ?>";</script>
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/js/__minuti.js"></script>

<?php

// ritorna la scritta corretta per un certo numero di minuti es.: 152 -> 2:32
function orario($minuti) {
    if ($minuti < 60) {
        return '0:' . $minuti;
    }
    $displayOre = floor($minuti / 60);
    $displayMinuti = sprintf('%02d', $minuti % 60);

    return $displayOre . ':' . $displayMinuti;
}

function oreToDisplay($ore) {
    return orario($ore *60);
}

?>
