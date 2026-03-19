/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function euro(v) {
  // accetta stringhe o numeri
  const n = Number(String(v).replace(',', '.'));
  if (!isFinite(n)) return "";
  return new Intl.NumberFormat('it-IT', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(n) + " €";
}

function euroNoSymbol(v) {
  const n = Number(String(v).replace(',', '.'));
  if (!isFinite(n)) return "";
  return new Intl.NumberFormat('it-IT', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(n);
}

function refreshTotale() {

  var totaleFuisFatto = Number($("#hidden_fuis_totale_fatto").val());
  var budgetFuis = Number($("#hidden_fuis_budget").val());
  var diffTotaleFuis = budgetFuis - totaleFuisFatto;

  var totaleFuisFattoDiaria = Number($("#hidden_fuis_totale_fatto_diaria").val());
  var totaleFuisFattoAssegnato = Number($("#hidden_fuis_totale_fatto_assegnato").val());
  var totaleFuisFattoOre = Number($("#hidden_fuis_totale_fatto_ore").val());  
  var totaleFuisFattoClil = Number($("#hidden_fuis_totale_fatto_clil").val());
  var budgetClil = Number($("#hidden_fuis_budget_clil").val());
  var diffTotaleFuisClil = budgetClil - totaleFuisFattoClil;

  var totaleFuisFattoOrientamento = Number($("#hidden_fuis_totale_fatto_orientamento").val());
  var budgetOrient = Number($("#hidden_fuis_budget_orientamento").val());
  var diffTotaleFuisOrientamento = budgetOrient - totaleFuisFattoOrientamento;

  var totHtml =
    '<strong>Totale FUIS: ' + euro(totaleFuisFatto) + '</strong><br>' +
    '(budget: ' + euro(budgetFuis) + ') ';

  if (diffTotaleFuis > 0) {
    totHtml += '<span class="label label-success">+ ' + euro(diffTotaleFuis) + '</span>';
  } else {
    totHtml += '<span class="label label-danger">- ' + euro(Math.abs(diffTotaleFuis)) + '</span>';
  }

  var totHtmlDiaria =
    '<strong>Totale Diaria: ' + euro(totaleFuisFattoDiaria) + '</strong><br>';
  var totHtmlAssegnato =
    '<strong>Totale Assegnato: ' + euro(totaleFuisFattoAssegnato) + '</strong><br>';
  var totHtmlOre =
    '<strong>Totale Ore: ' + euro(totaleFuisFattoOre) + '</strong><br>';

  var totHtmlClil =
    '<strong>Totale CLIL: ' + euro(totaleFuisFattoClil) + '</strong><br>' +
    '(budget: ' + euro(budgetClil) + ') ';

  if (diffTotaleFuisClil > 0) {
    totHtmlClil += '<span class="label label-success">+ ' + euro(diffTotaleFuisClil) + '</span>';
  } else {
    totHtmlClil += '<span class="label label-danger">- ' + euro(Math.abs(diffTotaleFuisClil)) + '</span>';
  }

  var totHtmlOrientamento =
    '<strong>Totale Orientamento: ' + euro(totaleFuisFattoOrientamento) + '</strong><br>' +
    '(budget: ' + euro(budgetOrient) + ') ';

  if (diffTotaleFuisOrientamento > 0) {
    totHtmlOrientamento += '<span class="label label-success">+ ' + euro(diffTotaleFuisOrientamento) + '</span>';
  } else {
    totHtmlOrientamento += '<span class="label label-danger">- ' + euro(Math.abs(diffTotaleFuisOrientamento)) + '</span>';
  }

  var msgCdr =
    ($("#hidden_corsi_di_recupero_pagati_da_provincia").val() == 0)
      ? '(già incluso nel totale fuis)'
      : '(pagato da Provincia)';

  $("#totale_fatte").html(totHtml);
  $("#totale_fatte_diaria").html(totHtmlDiaria);
  $("#totale_fatte_assegnato").html(totHtmlAssegnato);
  $("#totale_fatte_fuis_ore").html(totHtmlOre);
  $("#totale_fatte_clil").html(totHtmlClil);
  $("#totale_fatte_orientamento").html(totHtmlOrientamento);

  $("#totale_fatte_corsi_di_recupero").html(
    '<strong>Totale Corsi di Recupero: ' +
    euro($("#hidden_fuis_totale_corsi_di_recupero").val()) +
    '</strong><br>' + msgCdr
  );
}

function refreshPagina() {
    location.reload();
}

$(document).ready(function () {
    refreshTotale();
});
