/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var ancheClil=1;

$('#ancheClilCheckBox').change(function() {
    if (this.checked) {
        ancheClil = 1;
        $('#previste_docenti_table td:nth-child(7),th:nth-child(7),td:nth-child(8),th:nth-child(8)').show();
    } else {
        ancheClil = 0;
        $('#previste_docenti_table td:nth-child(7),th:nth-child(7),td:nth-child(8),th:nth-child(8)').hide();
    }
});

function refreshTotale() {
    $("#totale_previste").html('<strong>Totale FUIS previsto: ' + $("#hidden_fuis_totale_previsto").val() + '</strong>');
    $("#totale_previste_clil").html('<strong>Totale CLIL previsto: ' + $("#hidden_fuis_totale_previsto_clil").val() + '</strong>');
}

$(document).ready(function () {
    refreshTotale();
});
