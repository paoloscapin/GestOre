/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function refreshTotale() {
    var totaleFuisFatto = new Number($("#hidden_fuis_totale_fatto").val()).toFixed(2);
    var diffTotaleFuis = Math.round($("#hidden_fuis_budget").val() - $("#hidden_fuis_totale_fatto").val());
    var diffTotaleFuisNumber = new Number(diffTotaleFuis).toFixed(2);

    var totaleFuisFattoClil = new Number($("#hidden_fuis_totale_fatto_clil").val()).toFixed(2);
    var diffTotaleFuisClil = $("#hidden_fuis_budget_clil").val() - $("#hidden_fuis_totale_fatto_clil").val();
    var diffTotaleFuisClilNumber = new Number(diffTotaleFuisClil).toFixed(2);

    var totaleFuisFattoOrientamento = new Number($("#hidden_fuis_totale_fatto_orientamento").val()).toFixed(2);
    var diffTotaleFuisOrientamento = $("#hidden_fuis_budget_orientamento").val() - $("#hidden_fuis_totale_fatto_orientamento").val();
    var diffTotaleFuisOrientamentoNumber = new Number(diffTotaleFuisOrientamento).toFixed(2);

    var totHtml = '<strong>Totale FUIS: ' + totaleFuisFatto + '</strong> </br>(budget: ' + $("#hidden_fuis_budget").val() + ') ';
    if (diffTotaleFuis > 0) {
        var totHtml = totHtml + '<span class="label label-success">+ ' + diffTotaleFuisNumber + '</span>';
    } else {
        var totHtml = totHtml + '<span class="label label-danger">- ' + (-diffTotaleFuisNumber) + '</span>';
    }

    var totHtmlClil = '<strong>Totale CLIL: ' + totaleFuisFattoClil + '</strong>  </br>(budget: ' + $("#hidden_fuis_budget_clil").val() + ') ';
    if (diffTotaleFuisClil > 0) {
        var totHtmlClil = totHtmlClil + '<span class="label label-success">+ ' + diffTotaleFuisClilNumber + '</span>';
    } else {
        var totHtmlClil = totHtmlClil + '<span class="label label-danger">- ' + (-diffTotaleFuisClilNumber) + '</span>';
    }

    var totHtmlOrientamento = '<strong>Totale Orientamento: ' + totaleFuisFattoOrientamento + '</strong>  </br>(budget: ' + $("#hidden_fuis_budget_orientamento").val() + ') ';
    if (diffTotaleFuisOrientamento > 0) {
        var totHtmlOrientamento = totHtmlOrientamento + '<span class="label label-success">+ ' + diffTotaleFuisOrientamentoNumber + '</span>';
    } else {
        var totHtmlOrientamento = totHtmlOrientamento + '<span class="label label-danger">- ' + (-diffTotaleFuisOrientamentoNumber) + '</span>';
    }

    var $messaggioCorsoDiRecuperoExtra = ($("#hidden_corsi_di_recupero_pagati_da_provincia").val() == 0)? '(già incluso nel totale fuis)' : '(pagato da Provincia)';

    $("#totale_fatte").html(totHtml);
    $("#totale_fatte_clil").html(totHtmlClil);
    $("#totale_fatte_orientamento").html(totHtmlOrientamento);
    $("#totale_fatte_corsi_di_recupero").html('<strong>Totale Corsi di Recupero: ' + $("#hidden_fuis_totale_corsi_di_recupero").val() + ': ' + '</strong>  </br>' + $messaggioCorsoDiRecuperoExtra);
}

function refreshPagina() {
    location.reload();
}

$(document).ready(function () {
    refreshTotale();
});
