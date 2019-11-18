<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// controlla se deve gestire i minuti
if (! isset($__minuti)) {
    $__minuti = getSettingsValue('config','minuti', false);
}

// ritorna la scritta corretta per un certo numero di minuti es.: 152 -> 2:32
function orario($minuti) {
    $segno = '';
    if ($minuti < 0) {
        $segno = '-';
        $minuti = -$minuti;
    }
    if ($minuti < 60) {
        return '0:' . $minuti;
    }
    $displayOre = floor($minuti / 60);

    $displayMinuti = ($minuti % 60 == 0) ? '&ensp;&ensp;&ensp;' : sprintf(':%02d', $minuti % 60);

    return $segno . $displayOre . $displayMinuti;
}

function oreToDisplay($ore) {
    global $__minuti;
    if (! $__minuti) {
        return round($ore);
    }
    return orario(round($ore *60));
}

?>
