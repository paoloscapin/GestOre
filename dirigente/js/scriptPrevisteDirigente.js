/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function refreshTotale() {
    $("#totale_previste").html('<strong>Totale FUIS previsto: ' + $("#hidden_fuis_totale_previsto").val() + '</strong>');
}

$(document).ready(function () {
    refreshTotale();
});
