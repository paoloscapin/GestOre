/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function refreshTotale() {
    $("#totale_previste").html('<strong>Totale FUIS previsto: ' + $("#hidden_fuis_totale_previsto").val() + '</strong> (budget: ' + $("#hidden_fuis_budget").val() + ')');
    $("#totale_previste_clil").html('<strong>Totale CLIL previsto: ' + $("#hidden_fuis_totale_previsto_clil").val() + '</strong> (budget: ' + $("#hidden_fuis_budget_clil").val() + ')');
    $("#totale_previste_corsi_di_recupero").html('<strong>Totale Corsi di Recupero: ' + $("#hidden_fuis_totale_corsi_di_recupero").val() + '</strong>');
}

function refreshPagina() {
    location.reload();
}

$(document).ready(function () {
    refreshTotale();
});
