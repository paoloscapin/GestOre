/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function oreFatteReloadTables(soloTotale = false) {

  const payload = {
    richiesta: "oreFatteAggiorna",
    soloTotale: soloTotale,
    operatore: $("#hidden_operatore").val(),
    ultimo_controllo: $("#hidden_ultimo_controllo").val(),
    docente_id: $("#hidden_docente_id").val()   // ✅ FONDAMENTALE
  };

  return $.ajax({
    url: "../docente/oreFatteAggiorna.php",
    type: "POST",
    data: payload,
    dataType: "json",
    cache: false,
    timeout: 20000
  })
  .done(function (data) {

    if (data && data.reason === "SESSION_EXPIRED") {
      window.location.href = data.redirect || "../index.php";
      return;
    }

    if (!soloTotale) {
      $(".corso_di_recupero_records_content").html(data.dataCdr || "");
      $(".diaria_records_content").html(data.dataDiaria || "");
      $(".attivita_fatte_records_content").html(data.dataAttivita || "");
      $(".attivita_fatte_clil_records_content").html(data.dataClilAttivita || "");
      $(".attivita_fatte_sportelli_records_content").html(data.dataSportelli || "");
      $(".attivita_fatte_gruppi_records_content").html(data.dataGruppi || "");
      $(".attribuite_records_content").html(data.dataAttribuite || "");
      $(".sostituzioni_records_content").html(data.dataSostituzioni || "");
      $(".viaggi_records_content").html(data.dataViaggi || "");
    }

    // previste orientamento
    $("#orientamento_previste_funzionali").html(getHtmlNumAndPrevisteVisual(data.oreOrientamentoFunzionaliPreviste, 0));
    $("#orientamento_previste_con_studenti").html(getHtmlNumAndPrevisteVisual(data.oreOrientamentoConStudentiPreviste, 0));

    // fatte orientamento
    $("#orientamento_fatte_funzionali").html(getHtmlNumAndFatteVisual(data.oreOrientamentoFunzionali, data.oreOrientamentoFunzionaliPreviste));
    $("#orientamento_fatte_con_studenti").html(getHtmlNumAndFatteVisual(data.oreOrientamentoConStudenti, data.oreOrientamentoConStudentiPreviste));

    if (Number(data.oreOrientamentoFunzionali) + Number(data.oreOrientamentoConStudenti) === 0) {
      $(".orientamento").addClass("hidden");
      $(".NOorientamento").removeClass("hidden");
    } else {
      $(".orientamento").removeClass("hidden");
      $(".NOorientamento").addClass("hidden");
    }

    // ore dovute
    $("#dovute_ore_40_sostituzioni_di_ufficio").html(getHtmlNum(data.oreSostituzioniDovute));
    $("#dovute_ore_40_aggiornamento").html(getHtmlNum(data.oreAggiornamentoDovute));
    $("#dovute_ore_70_funzionali").html(getHtmlNum(data.oreFunzionaliDovute));
    $("#dovute_totale_con_studenti").html(getHtmlNum(data.oreConStudentiDovute));

    // ore previste
    $("#previste_ore_40_aggiornamento").html(getHtmlNumAndPrevisteVisual(data.oreAggiornamentoPreviste, data.oreAggiornamentoDovute));
    $("#previste_ore_70_funzionali").html(getHtmlNumAndPrevisteVisual(data.oreFunzionaliPreviste, data.oreFunzionaliDovute));
    $("#previste_totale_con_studenti").html(getHtmlNumAndPrevisteVisual(data.oreConStudentiPreviste, data.oreConStudentiDovute));

    // ore fatte
    $("#fatte_ore_40_sostituzioni_di_ufficio").html(getHtmlNumAndFatteVisual(data.oreSostituzione, data.oreSostituzioniDovute));
    $("#fatte_ore_40_aggiornamento").html(getHtmlNumAndFatteVisual(data.oreAggiornamento, data.oreAggiornamentoDovute));
    $("#fatte_ore_70_funzionali").html(getHtmlNumAndFatteVisual(data.oreFunzionali, data.oreFunzionaliDovute));
    $("#fatte_totale_con_studenti").html(getHtmlNumAndFatteVisual(data.oreConStudenti, data.oreConStudentiDovute));

    // clil (previste + fatte)
    $("#clil_previste_funzionali").html(getHtmlNumAndPrevisteVisual(data.oreClilFunzionaliPreviste, 0));
    $("#clil_previste_con_studenti").html(getHtmlNumAndPrevisteVisual(data.oreClilConStudentiPreviste, 0));
    $("#clil_fatte_funzionali").html(getHtmlNumAndFatteVisual(data.oreClilFunzionali, data.oreClilFunzionaliPreviste));
    $("#clil_fatte_con_studenti").html(getHtmlNumAndFatteVisual(data.oreClilConStudenti, data.oreClilConStudentiPreviste));

    if (
      parseInt(data.oreClilFunzionali, 10) +
      parseInt(data.oreClilConStudenti, 10) +
      parseInt(data.oreClilFunzionaliPreviste, 10) +
      parseInt(data.oreClilConStudentiPreviste, 10) === 0
    ) {
      $(".clil").addClass("hidden");
      $(".NOclil").removeClass("hidden");
    } else {
      $(".clil").removeClass("hidden");
      $(".NOclil").addClass("hidden");
    }

    // messaggi
    if (data.messaggio && data.messaggio.length > 0) {
      $("#ore_message").html(data.messaggio).removeClass("hidden")
        .css({ "font-weight": "bold", "text-align": "center", "background-color": "#BAEED0" });
    } else {
      $("#ore_message").addClass("hidden");
    }

    if (data.messaggioEccesso && data.messaggioEccesso.length > 0) {
      $("#ore_eccesso_message").html(data.messaggioEccesso).removeClass("hidden")
        .css({ "font-weight": "bold", "text-align": "center", "background-color": "#BAEED0" });
    } else {
      $("#ore_eccesso_message").addClass("hidden");
    }

    // FUIS (fatte)
    $("#fuis_assegnato").html(number_format(data.fuisAssegnato, 2));
    $("#fuis_ore").html(number_format(data.fuisOre, 2));
    $("#fuis_diaria").html(number_format(data.diariaImporto, 2));

    $("#fuis_clil_funzionali").html(number_format(data.fuisClilFunzionale, 2));
    $("#fuis_clil_con_studenti").html(number_format(data.fuisClilConStudenti, 2));

    $("#fuis_orientamento_funzionali").html(number_format(data.fuisOrientamentoFunzionale, 2));
    $("#fuis_orientamento_con_studenti").html(number_format(data.fuisOrientamentoConStudenti, 2));

    $("#fuis_corsi_di_recupero").html(number_format(data.fuisExtraCorsiDiRecupero, 2));

    $("#fuis_docente_totale").html(number_format(
      parseFloat(data.fuisAssegnato) + parseFloat(data.fuisOre) + parseFloat(data.diariaImporto), 2
    ));
    $("#fuis_clil_totale").html(number_format(
      parseFloat(data.fuisClilFunzionale) + parseFloat(data.fuisClilConStudenti), 2
    ));
    $("#fuis_orientamento_totale").html(number_format(
      parseFloat(data.fuisOrientamentoFunzionale) + parseFloat(data.fuisOrientamentoConStudenti), 2
    ));
    $("#fuis_corsi_di_recupero_totale").html(number_format(data.fuisExtraCorsiDiRecupero, 2));

    $("#fuis_docente_totale,#fuis_clil_totale,#fuis_orientamento_totale,#fuis_corsi_di_recupero_totale")
      .css({ "font-weight": "bold" });

    if (data.messaggio && data.messaggio.length > 0) {
      $("#fuis_message").html(data.messaggio).removeClass("hidden")
        .css({ "font-weight": "bold", "text-align": "center", "background-color": "#FFC6B4" });
    } else {
      $("#fuis_message").addClass("hidden");
    }

    if (data.messaggioEccesso && data.messaggioEccesso.length > 0) {
      $("#fuis_eccesso_message").html(data.messaggioEccesso).removeClass("hidden")
        .css({ "font-weight": "bold", "text-align": "center", "background-color": "#FFC6B4" });
    } else {
      $("#fuis_eccesso_message").addClass("hidden");
    }

    // 80 ore
    $("#dovute_ore_80_collegi_docenti").html(getHtmlNum(data.ore80DovuteCollegiDocenti));
    $("#dovute_ore_80_udienze_generali").html(getHtmlNum(data.ore80DovuteUdienzeGenerali));
    $("#dovute_ore_80_dipartimenti").html(getHtmlNum(data.ore80DovuteDipartimenti));
    $("#dovute_ore_80_aggiornamento_facoltativo").html(getHtmlNum(data.ore80DovuteAggiornamento));
    $("#dovute_ore_80_consigli_di_classe").html(getHtmlNum(data.ore80DovuteConsigliDiClasse));

  })
  .fail(function (jqXHR) {
    if (jqXHR.status === 401) {
      try {
        const r = JSON.parse(jqXHR.responseText || "{}");
        window.location.href = r.redirect || "../index.php";
      } catch (e) {
        window.location.href = "../index.php";
      }
      return;
    }
    window.location.href = "../index.php";
  });
}


function orePrevisteReloadTables(soloTotale = false) {

  const payload = {
    richiesta: "orePrevisteAggiorna",
    soloTotale: soloTotale,
    operatore: $("#hidden_operatore").val(),
    ultimo_controllo: $("#hidden_ultimo_controllo").val(),
    docente_id: $("#hidden_docente_id").val()   // ✅ FONDAMENTALE
  };

  return $.ajax({
    url: "../docente/orePrevisteAggiorna.php",
    type: "POST",
    data: payload,
    dataType: "json",
    cache: false,
    timeout: 20000
  })
  .done(function (data, textStatus, jqXHR) {

    // ✅ se il backend risponde {ok:false, reason:"SESSION_EXPIRED", redirect:"..."}
    if (data && data.reason === "SESSION_EXPIRED") {
      window.location.href = data.redirect || "../index.php";
      return;
    }

    // ✅ aggiornamento tabelle html
    if (!soloTotale) {
      $(".corso_di_recupero_records_content").html(data.dataCdr || "");
      $(".diaria_records_content").html(data.dataDiaria || "");
      $(".attivita_previste_records_content").html(data.dataPreviste || "");
      $(".attribuite_records_content").html(data.dataAttribuite || "");
    }

    // orientamento
    $("#orientamento_fatte_funzionali").html(getHtmlNumAndFatteVisual(data.oreOrientamentoFunzionali, data.oreOrientamentoFunzionaliPreviste));
    $("#orientamento_fatte_con_studenti").html(getHtmlNumAndFatteVisual(data.oreOrientamentoConStudenti, data.oreOrientamentoConStudentiPreviste));
    $("#orientamento_previste_funzionali").html(getHtmlNumAndPrevisteVisual(data.oreOrientamentoFunzionaliPreviste, 0));
    $("#orientamento_previste_con_studenti").html(getHtmlNumAndPrevisteVisual(data.oreOrientamentoConStudentiPreviste, 0));

    if (Number(data.oreOrientamentoFunzionali) + Number(data.oreOrientamentoConStudenti) === 0) {
      $(".orientamento").addClass("hidden");
      $(".NOorientamento").removeClass("hidden");
    } else {
      $(".orientamento").removeClass("hidden");
      $(".NOorientamento").addClass("hidden");
    }

    // ore dovute
    $("#dovute_ore_40_sostituzioni_di_ufficio").html(getHtmlNum(data.oreSostituzioniDovute));
    $("#dovute_ore_40_aggiornamento").html(getHtmlNum(data.oreAggiornamentoDovute));
    $("#dovute_ore_70_funzionali").html(getHtmlNum(data.oreFunzionaliDovute));
    $("#dovute_totale_con_studenti").html(getHtmlNum(data.oreConStudentiDovute));

    // ore previste
    $("#previste_ore_40_aggiornamento").html(getHtmlNumAndPrevisteVisual(data.oreAggiornamentoPreviste, data.oreAggiornamentoDovute));
    $("#previste_ore_70_funzionali").html(getHtmlNumAndPrevisteVisual(data.oreFunzionaliPreviste, data.oreFunzionaliDovute));
    $("#previste_totale_con_studenti").html(getHtmlNumAndPrevisteVisual(data.oreConStudentiPreviste, data.oreConStudentiDovute));

    // clil previste
    $("#clil_previste_funzionali").html(getHtmlNumAndPrevisteVisual(data.oreClilFunzionaliPreviste, 0));
    $("#clil_previste_con_studenti").html(getHtmlNumAndPrevisteVisual(data.oreClilConStudentiPreviste, 0));

    if (parseInt(data.oreClilFunzionaliPreviste, 10) + parseInt(data.oreClilConStudentiPreviste, 10) === 0) {
      $(".clil").addClass("hidden");
      $(".NOclil").removeClass("hidden");
    } else {
      $(".clil").removeClass("hidden");
      $(".NOclil").addClass("hidden");
    }

    // messaggio ore
    if (data.messaggio && data.messaggio.length > 0) {
      $("#ore_message").html(data.messaggio).removeClass("hidden")
        .css({ "font-weight": "bold", "text-align": "center", "background-color": "#BAEED0" });
    } else {
      $("#ore_message").addClass("hidden");
    }

    // FUIS (previste)
    $("#fuis_assegnato").html(number_format(data.fuisAssegnato, 2));
    $("#fuis_ore").html(number_format(data.fuisOrePreviste, 2));
    $("#fuis_diaria").html(number_format(data.diariaImportoPreviste, 2));

    $("#fuis_clil_funzionali").html(number_format(data.fuisClilFunzionalePreviste, 2));
    $("#fuis_clil_con_studenti").html(number_format(data.fuisClilConStudentiPreviste, 2));

    $("#fuis_orientamento_funzionali").html(number_format(data.fuisOrientamentoFunzionalePreviste, 2));
    $("#fuis_orientamento_con_studenti").html(number_format(data.fuisOrientamentoConStudentiPreviste, 2));

    $("#fuis_corsi_di_recupero").html(number_format(data.fuisExtraCorsiDiRecupero, 2));

    $("#fuis_docente_totale").html(number_format(
      parseFloat(data.fuisAssegnato) + parseFloat(data.fuisOrePreviste) + parseFloat(data.diariaImportoPreviste), 2
    ));
    $("#fuis_clil_totale").html(number_format(
      parseFloat(data.fuisClilFunzionalePreviste) + parseFloat(data.fuisClilConStudentiPreviste), 2
    ));
    $("#fuis_orientamento_totale").html(number_format(
      parseFloat(data.fuisOrientamentoFunzionalePreviste) + parseFloat(data.fuisOrientamentoConStudentiPreviste), 2
    ));
    $("#fuis_corsi_di_recupero_totale").html(number_format(data.fuisExtraCorsiDiRecupero, 2));

    $("#fuis_docente_totale,#fuis_clil_totale,#fuis_orientamento_totale,#fuis_corsi_di_recupero_totale")
      .css({ "font-weight": "bold" });

    // messaggio fuis
    if (data.messaggio && data.messaggio.length > 0) {
      $("#fuis_message").html(data.messaggio).removeClass("hidden")
        .css({ "font-weight": "bold", "text-align": "center", "background-color": "#FFC6B4" });
    } else {
      $("#fuis_message").addClass("hidden");
    }

  })
  .fail(function (jqXHR) {
    // ✅ session scaduta
    if (jqXHR.status === 401) {
      try {
        const r = JSON.parse(jqXHR.responseText || "{}");
        window.location.href = r.redirect || "../index.php";
      } catch (e) {
        window.location.href = "../index.php";
      }
      return;
    }

    // fallback (errore generico)
    window.location.href = "../index.php";
  });
}



