/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function refreshTotale() {
    var diffTotaleFuis = Math.round($("#hidden_fuis_budget").val() - $("#hidden_fuis_totale_fatto").val());
    var diffTotaleFuisNumber = new Number(diffTotaleFuis).toLocaleString("it-IT");
    var diffTotaleFuisClil = $("#hidden_fuis_budget_clil").val() - $("#hidden_fuis_totale_fatto_clil").val();
    var diffTotaleFuisClilNumber = new Number(diffTotaleFuisClil).toLocaleString("it-IT");

    var totHtml = '<strong>Totale FUIS: ' + $("#hidden_fuis_totale_fatto").val() + '</strong> (budget: ' + $("#hidden_fuis_budget").val() + ') ';
    if (diffTotaleFuis > 0) {
        var totHtml = totHtml + '<span class="label label-success">+ ' + diffTotaleFuisNumber + '</span>';
    } else {
        var totHtml = totHtml + '<span class="label label-danger">- ' + (-diffTotaleFuisNumber) + '</span>';
    }

    var totHtmlClil = '<strong>Totale CLIL: ' + $("#hidden_fuis_totale_fatto_clil").val() + '</strong> (budget: ' + $("#hidden_fuis_budget_clil").val() + ') ';
    if (diffTotaleFuisClil > 0) {
        var totHtmlClil = totHtmlClil + '<span class="label label-success">+ ' + diffTotaleFuisClilNumber + '</span>';
    } else {
        var totHtmlClil = totHtmlClil + '<span class="label label-danger">- ' + (-diffTotaleFuisClilNumber) + '</span>';
    }

    $("#totale_fatte").html(totHtml);
    $("#totale_fatte_clil").html(totHtmlClil);
    $("#totale_fatte_corsi_di_recupero").html('<strong>Totale Corsi di Recupero: ' + $("#hidden_fuis_totale_corsi_di_recupero").val() + '</strong>');
}

function refreshPagina() {
    location.reload();
}

$(document).ready(function () {
    refreshTotale();
});
