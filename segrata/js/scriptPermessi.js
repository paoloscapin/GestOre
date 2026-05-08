/**
 * Permessi ATA - Segreteria
 */


let ferieModalInitialSnapshot = null;
let ferieModalCurrentSnapshot = null;
let ferieModalKeepDirty = false;
const DASHBOARD_STATI = ["INVIATO", "AGGIORNATA", "APPROVATO", "PARZIALE", "RESPINTO", "ANNULLATO"];
const DASHBOARD_REGISTRAZIONI = ["DA_REGISTRARE", "REGISTRATO"];
let dashboardSelectedStates = DASHBOARD_STATI.slice();
let dashboardSelectedRegistrazioni = DASHBOARD_REGISTRAZIONI.slice();

function fmtDateTimeIT(dt) {
  if (!dt) return "";

  // formato: 2026-04-11 10:13:34
  const parts = dt.split(" ");
  if (parts.length < 1) return dt;

  const datePart = parts[0];
  const timePart = parts[1] || "";

  const d = datePart.split("-");
  if (d.length !== 3) return dt;

  return d[2] + "/" + d[1] + "/" + d[0] + (timePart ? " " + timePart : "");
}

function safeSelectpickerRefresh(sel) {
  if ($.fn.selectpicker && $(sel).length) {
    $(sel).selectpicker("refresh");
  }
}

function setDashboardStates(states) {
  const normalized = Array.isArray(states)
    ? states
      .map(function (stato) { return (stato || "").toString().trim().toUpperCase(); })
      .filter(function (stato, index, arr) {
        return DASHBOARD_STATI.indexOf(stato) !== -1 && arr.indexOf(stato) === index;
      })
    : [];

  $(".dash-item[data-stato]").removeClass("active");
  normalized.forEach(function (stato) {
    $('.dash-item[data-stato="' + stato + '"]').addClass("active");
  });

  dashboardSelectedStates = normalized;
}

function setDashboardDisabled(disabled) {
  $(".dash-item").toggleClass("disabled", !!disabled);
  $(".dash-item").attr("aria-disabled", disabled ? "true" : "false");
}

function setDashboardRegistrazioni(registrazioni) {
  const normalized = Array.isArray(registrazioni)
    ? registrazioni
      .map(function (item) { return (item || "").toString().trim().toUpperCase(); })
      .filter(function (item, index, arr) {
        return DASHBOARD_REGISTRAZIONI.indexOf(item) !== -1 && arr.indexOf(item) === index;
      })
    : [];

  $('[data-filter="registrazione"]').removeClass("active");
  normalized.forEach(function (item) {
    $('[data-reg-filter="' + item + '"]').addClass("active");
  });

  dashboardSelectedRegistrazioni = normalized;
}

function syncDashboardWithStatoFilter() {
  const stato = ($("#f_stato").val() || "").toString().trim().toUpperCase();

  if (stato) {
    setDashboardDisabled(true);
    setDashboardStates([]);
    setDashboardRegistrazioni([]);
    return;
  }

  setDashboardDisabled(false);
  setDashboardStates(DASHBOARD_STATI);
  setDashboardRegistrazioni(DASHBOARD_REGISTRAZIONI);
}

$(document).on("click", ".dash-item[data-stato]", function () {
  if ($(this).hasClass("disabled")) return;

  const stato = ($(this).data("stato") || "").toString().trim();
  if (!stato) return;

  const nextStates = dashboardSelectedStates.slice();
  const idx = nextStates.indexOf(stato);

  if (idx >= 0) {
    nextStates.splice(idx, 1);
  } else {
    nextStates.push(stato);
  }

  setDashboardStates(nextStates);
  permessiReadRecords();
});

$(document).on("click", "#d_da_registrare", function () {
  if ($(this).hasClass("disabled")) return;

  const filtro = ($(this).data("regFilter") || "").toString().trim().toUpperCase();
  if (!filtro) return;

  const nextFilters = dashboardSelectedRegistrazioni.slice();
  const idx = nextFilters.indexOf(filtro);

  if (idx >= 0) {
    nextFilters.splice(idx, 1);
  } else {
    nextFilters.push(filtro);
  }

  setDashboardRegistrazioni(nextFilters);
  permessiReadRecords();
});

$(document).on("click", "#d_registrato", function () {
  if ($(this).hasClass("disabled")) return;

  const filtro = ($(this).data("regFilter") || "").toString().trim().toUpperCase();
  if (!filtro) return;

  const nextFilters = dashboardSelectedRegistrazioni.slice();
  const idx = nextFilters.indexOf(filtro);

  if (idx >= 0) {
    nextFilters.splice(idx, 1);
  } else {
    nextFilters.push(filtro);
  }

  setDashboardRegistrazioni(nextFilters);
  permessiReadRecords();
});

function permessiReadRecords() {
  const stato = ($("#f_stato").val() || "").toString();
  const stati = stato ? [] : dashboardSelectedStates.slice();
  const registrazioni = stato ? [] : dashboardSelectedRegistrazioni.slice();
  const tipoId = ($("#f_tipo").val() || "").toString();
  const profiloId = ($("#f_profilo").val() || "").toString();
  const ufficioId = ($("#f_ufficio").val() || "").toString();
  const search = ($("#f_search").val() || "").toString();

  $.ajax({
    url: "permessiReadRecords.php",
    method: "GET",
    data: {
      stato: stato,
      stati: stati,
      registrazioni: registrazioni,
      tipo_id: tipoId,
      profilo_id: profiloId,
      ufficio_id: ufficioId,
      search: search
    },
    dataType: "html",
    success: function (html) {
      $(".records_content").html(html);
    },
    error: function (xhr) {
      console.error("permessiReadRecords ERROR", {
        status: xhr.status,
        statusText: xhr.statusText,
        responseText: (xhr.responseText || "").substring(0, 1500)
      });

      let r = null;
      try { r = JSON.parse(xhr.responseText); } catch (e) { }

      if (xhr.status === 401 && r && r.redirect) {
        window.location.href = r.redirect;
        return;
      }

      $(".records_content").html(
        '<div class="alert alert-danger">Errore caricamento elenco permessi. HTTP ' +
        xhr.status +
        "</div>"
      );

      $.notify(
        { message: "Errore server (lista) - HTTP " + xhr.status },
        { type: "danger", placement: { from: "top", align: "center" }, delay: 6000 }
      );
    }
  });
}

function fmtDateLabelIT(iso) {
  if (!iso) return "";
  const p = iso.split("-");
  if (p.length !== 3) return iso;
  return p[2] + "/" + p[1] + "/" + p[0];
}

function ferieMonthName(monthIndex) {
  const names = ["Gennaio", "Febbraio", "Marzo", "Aprile", "Maggio", "Giugno", "Luglio", "Agosto", "Settembre", "Ottobre", "Novembre", "Dicembre"];
  return names[monthIndex] || "";
}

function mondayBasedDow(jsDay) {
  // JS: DOM=0, LUN=1, ... SAB=6
  // noi vogliamo: LUN=0, MAR=1, ... DOM=6
  return (jsDay + 6) % 7;
}

function ferieDowShort(day) {
  const names = ["LUN", "MAR", "MER", "GIO", "VEN", "SAB", "DOM"];
  return names[day] || "";
}

function parseIsoDate(iso) {
  if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return null;
  const [y, m, d] = iso.split("-").map(Number);
  return new Date(y, m - 1, d);
}

function toIsoDate(d) {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${y}-${m}-${day}`;
}

function sameDay(a, b) {
  return toIsoDate(a) === toIsoDate(b);
}

function dateRangeDays(startIso, endIso) {
  const out = [];
  const start = parseIsoDate(startIso);
  const end = parseIsoDate(endIso);
  if (!start || !end || end < start) return out;

  const cur = new Date(start.getTime());
  while (cur <= end) {
    out.push(toIsoDate(cur));
    cur.setDate(cur.getDate() + 1);
  }
  return out;
}

function buildSelectedDateMap(righe) {
  const selected = {};
  (righe || []).forEach(r => {
    const da = r.data_da || r.data_dal;
    const a = r.data_a || r.data_al || da;
    const stato = (r.stato_giorno || "RICHIESTO").toUpperCase();
    const variazione = (r.variazione_modifica || "").toUpperCase();
    const nota = r.nota_approvatore || "";

    if (!da) return;

    dateRangeDays(da, a).forEach(iso => {
      selected[iso] = {
        selected: true,
        row_id: r.id || null,
        unita: r.unita || "",
        stato_giorno: stato,
        variazione_modifica: variazione,
        nota_approvatore: nota
      };
    });
  });
  return selected;
}

function buildFerieModalSnapshot() {
  const noteSegreteria = ($("#fm_note_segreteria").val() || "").trim();
  const registratoSegreteria = $("#fm_registrato_segreteria").is(":checked") ? 1 : 0;
  const richiestaId = ($("#fm_hidden_permesso_id").val() || "").toString().trim();

  const selectedMap = window.__FM_SELECTED_DATE_MAP || {};
  const days = Object.keys(selectedMap).sort().map(function (iso) {
    const d = selectedMap[iso] || {};
    return {
      iso: iso,
      row_id: String(d.row_id || ""),
      stato_giorno: String(d.stato_giorno || "RICHIESTO").toUpperCase(),
      nota_approvatore: String(d.nota_approvatore || "").trim()
    };
  });

  return {
    richiesta_id: richiestaId,
    note_segreteria: noteSegreteria,
    registrato_segreteria: registratoSegreteria,
    days: days
  };
}

function ferieSnapshotsEqual(a, b) {
  return JSON.stringify(a || {}) === JSON.stringify(b || {});
}

function updateFerieSaveButtonState() {
  ferieModalCurrentSnapshot = buildFerieModalSnapshot();
  const changed = !ferieSnapshotsEqual(ferieModalInitialSnapshot, ferieModalCurrentSnapshot);

  $("#fm_btn_save_notes").prop("disabled", !changed);
  return changed;
}

function getOtherDayCellStateClass(otherInfo) {
  const stato = ((otherInfo && otherInfo.stato_giorno) || "").toUpperCase();
  if (stato === "APPROVATO") return " other-approved";
  if (stato === "AGGIUNTO") return " other-requested";
  if (stato === "RIMOSSO") return " other-removed";
  if (stato === "RESPINTO") return " other-rejected";
  if (stato === "RICHIESTO") return " other-requested";
  if (stato === "BOZZA") return " other-draft";
  return "";
}

function buildOtherRequestsHtml(otherRequests) {
  if (!Array.isArray(otherRequests) || !otherRequests.length) {
    return "";
  }

  let html = '<div class="alert alert-warning" style="margin-bottom:12px;">' +
    '<strong>Attenzione:</strong> per questo dipendente esistono già ' +
    otherRequests.length +
    ' altra/e richiesta/e ferie nella stessa finestra.' +
    '</div>';

  html += '<div class="well well-sm" style="margin-bottom:12px;">';
  html += '<strong>Richieste precedenti:</strong><br>';

  otherRequests.forEach(function (rq) {
    html +=
      'Richiesta #' + rq.id +
      ' · stato ' + (rq.stato || '-') +
      ' · creata il ' + fmtDateTimeIT(rq.created_at || '') +
      '<br>';
  });

  html += '</div>';
  return html;
}

function getDayCellStateClass(dayInfo) {
  const stato = ((dayInfo && dayInfo.stato_giorno) || "").toUpperCase();
  if (stato === "APPROVATO") return " day-approved";
  if (stato === "AGGIUNTO") return " day-added";
  if (stato === "RIMOSSO") return " day-removed";
  if (stato === "RESPINTO") return " day-rejected";
  if (stato === "RICHIESTO") return " day-requested";
  return "";
}

function openFerieDayDecisionModal(iso, dayInfo, dip) {
  if (!dayInfo || !dayInfo.row_id) return;

  $("#fg_riga_id").val(dayInfo.row_id);
  $("#fg_iso").text(iso);
  $("#fg_dipendente").text((dip && dip.nome) ? dip.nome : "");
  const statoGiorno = ((dayInfo.stato_giorno || "RICHIESTO").toString().toUpperCase() === "AGGIUNTO")
    ? "RICHIESTO"
    : (dayInfo.stato_giorno || "RICHIESTO");
  $("#fg_stato_giorno").val(statoGiorno);
  $("#fg_nota_approvatore").val(dayInfo.nota_approvatore || "");

  $("#ferie_giorno_modal").modal("show");
}

function saveFerieNotesOnly() {
  const id = $("#fm_hidden_permesso_id").val();
  const note = $("#fm_note_segreteria").val();
  const registrato = $("#fm_registrato_segreteria").is(":checked") ? 1 : 0;

  if (!updateFerieSaveButtonState()) {
    return;
  }

  $.ajax({
    url: "permessoUpdateSegreteria.php",
    method: "POST",
    dataType: "json",
    data: {
      id: id,
      note_segreteria: note,
      finalizza_ferie: 1,
      registrato_segreteria: registrato
    },
    success: function (r) {
      if (!r || r.ok !== true) {
        $.notify({
          message: (r && r.error) ? r.error : "Salvataggio note fallito"
        }, { type: "danger" });
        return;
      }
      ferieModalInitialSnapshot = buildFerieModalSnapshot();
      updateFerieSaveButtonState();
      $("#permesso_ferie_modal").modal("hide");
      $.notify({
        icon: "glyphicon glyphicon-ok",
        title: "<strong>Ferie</strong>&nbsp;",
        message: "Note segreteria salvate."
      }, { type: "success", placement: { from: "top", align: "center" }, delay: 2500 });

      permessiReadRecords();
      dashboardLoad();
    },
    error: function (xhr) {
      console.error("permessoUpdateSegreteria NOTE ERROR", xhr.responseText);
      $.notify({ message: "Errore salvataggio note segreteria" }, { type: "danger" });
    }
  });
}

function saveFerieAllDays(statoGiorno) {
  const richiestaId = $("#fm_hidden_permesso_id").val();
  const nota = $("#fg_nota_approvatore").val();

  if (!richiestaId) {
    $.notify({ message: "Richiesta ferie non selezionata." }, { type: "danger" });
    return;
  }

  $.ajax({
    url: "permessoFerieRichiestaBulkUpdate.php",
    method: "POST",
    dataType: "json",
    data: {
      richiesta_id: richiestaId,
      stato_giorno: statoGiorno,
      nota_approvatore: nota
    },
    success: function (r) {
      if (!r || r.ok !== true) {
        $.notify({
          message: (r && r.error) ? r.error : "Aggiornamento massivo fallito"
        }, { type: "danger" });
        return;
      }

      $("#ferie_giorno_modal").modal("hide");

      if (richiestaId) {
        ferieModalKeepDirty = true;
        permessoOpen(richiestaId);
      }

      permessiReadRecords();
      dashboardLoad();

      $.notify({
        icon: "glyphicon glyphicon-ok",
        title: "<strong>Ferie</strong>&nbsp;",
        message: "Tutti i giorni aggiornati."
      }, { type: "success", placement: { from: "top", align: "center" }, delay: 2500 });
    },
    error: function (xhr) {
      console.error("permessoFerieRichiestaBulkUpdate ERROR", xhr.responseText);
      $.notify({ message: "Errore aggiornamento massivo ferie" }, { type: "danger" });
    }
  });
}

function saveFerieSingleDay(statoOverride) {
  const rigaId = $("#fg_riga_id").val();
  const stato = statoOverride || $("#fg_stato_giorno").val();
  const nota = $("#fg_nota_approvatore").val();

  $.ajax({
    url: "permessoFerieGiornoUpdate.php",
    method: "POST",
    dataType: "json",
    data: {
      riga_id: rigaId,
      stato_giorno: stato,
      nota_approvatore: nota
    },
    success: function (r) {
      if (!r || r.ok !== true) {
        $.notify({ message: (r && r.error) ? r.error : "Salvataggio giorno fallito" }, { type: "danger" });
        return;
      }

      $("#ferie_giorno_modal").modal("hide");

      const richiestaId = $("#fm_hidden_permesso_id").val();
      if (richiestaId) {
        ferieModalKeepDirty = true;
        permessoOpen(richiestaId);
      }

      permessiReadRecords();
      dashboardLoad();

      $.notify({
        icon: "glyphicon glyphicon-ok",
        title: "<strong>Ferie</strong>&nbsp;",
        message: "Giorno aggiornato."
      }, { type: "success", placement: { from: "top", align: "center" }, delay: 2500 });
    },
    error: function (xhr) {
      console.error("permessoFerieGiornoUpdate ERROR", xhr.responseText);
      $.notify({ message: "Errore salvataggio giorno ferie" }, { type: "danger" });
    }
  });
}

function buildFerieTooltip(iso, reason, tooltipByDate, dip) {
  const tip = tooltipByDate[iso] || {};
  const profNames = Array.isArray(tip.same_profile_names) ? tip.same_profile_names : [];
  const offNames = Array.isArray(tip.same_office_names) ? tip.same_office_names : [];

  const profLabel = (dip && dip.profilo_codice)
    ? dip.profilo_codice
    : (dip && dip.profilo_nome ? dip.profilo_nome : "Profilo");
  const uffLabel = (dip && dip.ufficio) ? dip.ufficio : "Ufficio";

  const parts = [];

  if (reason) {
    parts.push(reason);
  }

  // PROFILO
  if (profNames.length) {
    parts.push("In ferie " + profLabel + ":\n- " + profNames.join("\n- "));
  } else {
    parts.push("In ferie " + profLabel + ":\n- nessuno");
  }

  // UFFICIO
  if (offNames.length) {
    parts.push("In ferie " + uffLabel + ":\n- " + offNames.join("\n- "));
  } else {
    parts.push("In ferie " + uffLabel + ":\n- nessuno");
  }

  return parts.join("\n\n");
}

function renderPermessoFerieModal(r) {
  const perm = r.permesso || {};
  $("#fm_btn_print_permesso")
  .attr("href", permessoPdfUrl(perm.id || 0))
  .attr("target", "_blank");
  const dip = r.dipendente || {};
  const righe = r.righe || [];
  const finestra = r.ferie_finestra || {};
  const giorniSpeciali = Array.isArray(r.giorni_speciali) ? r.giorni_speciali : [];
  const totals = r.totali || { profilo: 0, ufficio: 0 };
  const otherDaysByDate = r.other_days_by_date || {};
  const otherRequests = Array.isArray(r.other_requests_summary) ? r.other_requests_summary : [];

  $("#fm_registrato_segreteria").prop("checked", String(perm.registrato_segreteria || "0") === "1");

  if (perm.registrato_da_label) {
    $("#fm_registrato_da").text(perm.registrato_da_label);
    $("#fm_registrato_il").text(perm.registrato_il_fmt ? (" il " + perm.registrato_il_fmt) : "");
    $("#fm_registrazione_info").show();
  } else {
    $("#fm_registrato_da").text("");
    $("#fm_registrato_il").text("");
    $("#fm_registrazione_info").hide();
  }
  window.__FM_SELECTED_DATE_MAP = buildSelectedDateMap(righe);

  $("#fm_hidden_permesso_id").val(perm.id || "");
  $("#fm_title").text(perm.tipo || "Dettaglio ferie");
  $("#fm_subtitle").text("Richiesta ferie di " + (dip.nome || ""));
  $("#fm_stato_badge").text(perm.stato || "-");
  const statoPerm = (perm.stato || "").toString().toUpperCase();
  let gestitoLabel = "Aggiornata da:";

  if (statoPerm === "APPROVATO") gestitoLabel = "Approvata da:";
  else if (statoPerm === "RESPINTO") gestitoLabel = "Respinta da:";
  else if (statoPerm === "PARZIALE") gestitoLabel = "Aggiornata da:";

  if (perm.gestito_da_label) {
    $("#fm_gestito_label").text(gestitoLabel);
    $("#fm_gestito_da").text(perm.gestito_da_label);

    if (perm.gestito_il_fmt) {
      $("#fm_gestito_il").text(" il " + perm.gestito_il_fmt);
    } else {
      $("#fm_gestito_il").text("");
    }

    $("#fm_gestito_wrap").show();
  } else {
    $("#fm_gestito_label").text("Aggiornata da:");
    $("#fm_gestito_da").text("");
    $("#fm_gestito_il").text("");
    $("#fm_gestito_wrap").hide();
  }
  $("#fm_nome").text(dip.nome || "");
  $("#fm_email").text(dip.email || "");
  $("#fm_matricola").text(dip.matricola || "");
  $("#fm_contratto").text(dip.tipo_contratto || "");
  $("#fm_profilo").text(dip.profilo || "");
  $("#fm_ufficio").text(dip.ufficio || "");

  $("#fm_note_richiedente").val(perm.note_richiedente || "");
  $("#fm_note_segreteria").val(perm.note_segreteria || "");
  const extraHtml = buildOtherRequestsHtml(otherRequests);
  $("#fm_other_requests_box").html(extraHtml);

  if (perm.is_additional_request) {
    $("#fm_subtitle").html(
      'Richiesta ferie di ' + (dip.nome || "") +
      ' <span class="label label-warning" style="margin-left:8px;">' +
      (perm.previous_requests_count + 1) + 'ª richiesta</span>'
    );
  } else {
    $("#fm_subtitle").text("Richiesta ferie di " + (dip.nome || ""));
  }
  const selectedMap = buildSelectedDateMap(righe);
  const selectedDates = Object.keys(selectedMap).sort();
  const activeSelectedDates = selectedDates.filter(function (iso) {
    return ((selectedMap[iso] && selectedMap[iso].stato_giorno) || "").toUpperCase() !== "RIMOSSO";
  });
  $("#fm_count_selected").text(activeSelectedDates.length);

  const winStart = finestra.data_inizio || perm.ferie_win_da || "";
  const winEnd = finestra.data_fine || perm.ferie_win_a || "";

  if (!winStart || !winEnd) {
    $("#fm_months_wrap").html('<div class="alert alert-warning">Finestra ferie non disponibile.</div>');
    return;
  }

  const start = parseIsoDate(winStart);
  const end = parseIsoDate(winEnd);

  if (!start || !end || end < start) {
    $("#fm_months_wrap").html('<div class="alert alert-warning">Finestra ferie non valida.</div>');
    return;
  }

  const specialMap = {};
  giorniSpeciali.forEach(g => {
    const iso = (g.data || "").trim();
    if (!iso) return;
    specialMap[iso] = {
      tipo: (g.tipo || "").toUpperCase(),
      descrizione: g.descrizione || ""
    };
  });

  const countsByDate = r.counts_by_date || {};
  const tooltipByDate = r.tooltip_by_date || {};
  let html = "";
  const isOrdinarie = (perm.ferie_sottotipo || "").toString().trim().toUpperCase() === "ORDINARIE";
  const monthsToRender = [];

  if (isOrdinarie && selectedDates.length) {
    const seenMonths = {};

    selectedDates.forEach(function (iso) {
      const dt = parseIsoDate(iso);
      if (!dt) return;

      const monthKey = dt.getFullYear() + "-" + String(dt.getMonth() + 1).padStart(2, "0");
      if (seenMonths[monthKey]) return;

      seenMonths[monthKey] = true;
      monthsToRender.push(new Date(dt.getFullYear(), dt.getMonth(), 1));
    });
  } else {
    let curMonth = new Date(start.getFullYear(), start.getMonth(), 1);
    const endMonth = new Date(end.getFullYear(), end.getMonth(), 1);

    while (curMonth <= endMonth) {
      monthsToRender.push(new Date(curMonth.getFullYear(), curMonth.getMonth(), 1));
      curMonth = new Date(curMonth.getFullYear(), curMonth.getMonth() + 1, 1);
    }
  }

  monthsToRender.forEach(function (curMonth) {
    const year = curMonth.getFullYear();
    const month = curMonth.getMonth();

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const totalDays = lastDay.getDate();
    const startDow = mondayBasedDow(firstDay.getDay());

    html += `<div class="ferie-month-card">`;
    html += `<div class="ferie-month-head">${ferieMonthName(month)} ${year}</div>`;
    html += `<div class="ferie-month-grid">`;

    for (let i = 0; i < startDow; i++) {
      html += `<div class="ferie-day-cell locked" style="visibility:hidden;"></div>`;
    }

    for (let day = 1; day <= totalDays; day++) {
      const dt = new Date(year, month, day);
      const iso = toIsoDate(dt);
      const dowJs = dt.getDay();
      const dow = mondayBasedDow(dowJs);

      const inWindow = dt >= start && dt <= end;
      const isWeekend = (dowJs === 0 || dowJs === 6);
      const special = specialMap[iso] || null;
      const isExcludedSpecial = !!special && ["ESCLUSO", "ESCLUDI"].includes(special.tipo);
      const isSelected = !!selectedMap[iso];
      const dayInfo = selectedMap[iso] || null;
      const otherInfo = otherDaysByDate[iso] || null;
      const hasOtherRequest = !!otherInfo;
      const counts = countsByDate[iso] || { same_profile: 0, same_office: 0 };
      const profCount = parseInt(counts.same_profile || 0, 10);
      const offCount = parseInt(counts.same_office || 0, 10);

      let reason = "";
      if (isWeekend) {
        reason = (dowJs === 0) ? "Domenica" : "Sabato";
      } else if (isExcludedSpecial) {
        reason = special.descrizione || "Escluso";
      }
      if (isSelected && dayInfo) {
        const statoSel = (dayInfo.stato_giorno || "").toUpperCase();
        if (statoSel === "AGGIUNTO") reason = "Aggiunto";
        if (statoSel === "RIMOSSO") reason = "Rimosso";
      }

      const showCounters = inWindow && !isWeekend && !isExcludedSpecial;

      let cls = "ferie-day-cell";
      if (!inWindow || isWeekend || isExcludedSpecial) {
        cls += " locked";
      }

      if (hasOtherRequest && !isSelected) {
        cls += " locked";
        cls += getOtherDayCellStateClass(otherInfo);
      }

      if (isSelected) {
        cls += " selected";
        cls += getDayCellStateClass(dayInfo);
      }

      let extraReason = reason;
      if (otherInfo) {
        extraReason = (extraReason ? extraReason + " | " : "") +
          "Altra richiesta #" + otherInfo.richiesta_id + " - " + (otherInfo.label || otherInfo.stato_giorno || "");
      }

      const tooltipText = buildFerieTooltip(iso, extraReason, tooltipByDate, dip).replace(/&/g, "&amp;")
        .replace(/"/g, "&quot;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;");

      const rightHtml = showCounters
        ? `
      <div class="ferie-day-right">
        <div class="ferie-day-meta ferie-day-meta-counts">
        <span class="p-line">P: ${profCount}/${totals.profilo || 0}</span>
          <span class="u-line">U: ${offCount}/${totals.ufficio || 0}</span>
        </div>
      </div>`
        : `<div class="ferie-day-right"></div>`;

      const clickableDay = isSelected && inWindow && !isWeekend && !isExcludedSpecial &&
        ((dayInfo && (dayInfo.stato_giorno || "").toUpperCase()) !== "RIMOSSO");
      const clickAttrs = clickableDay
        ? `data-row-id="${dayInfo.row_id}" data-clickable="1"`
        : `data-clickable="0"`;

      html += `
  <div class="${cls}" data-iso="${iso}" ${clickAttrs} title="${tooltipText}">
      <div class="ferie-day-left">
        <div class="ferie-day-dow">${ferieDowShort(dow)}</div>
        <div class="ferie-day-num">${day}</div>
        <div class="ferie-day-meta ferie-day-meta-reason">
          ${reason ? reason : "&nbsp;"}
        </div>
      </div>
      ${rightHtml}
    </div>
  `;
    }

    html += `</div></div>`;
  });

  $("#fm_months_wrap").html(html);
  $("#fm_months_wrap .ferie-day-cell[data-clickable='1']").off("click").on("click", function () {
    const iso = $(this).data("iso");
    const dayInfo = selectedMap[iso] || null;
    openFerieDayDecisionModal(iso, dayInfo, dip);
  });

  if (!ferieModalKeepDirty || !ferieModalInitialSnapshot) {
    ferieModalInitialSnapshot = buildFerieModalSnapshot();
  }
  ferieModalKeepDirty = false;
  updateFerieSaveButtonState();
}

function permessoPdfUrl(id) {
  id = parseInt(id, 10) || 0;
  return "permessoPdf.php?id=" + encodeURIComponent(id);
}

function openStandardPermessoModal(r, id) {
  $("#hidden_permesso_id").val(id);
  $("#btn_print_permesso")
  .attr("href", permessoPdfUrl(id))
  .attr("target", "_blank");
  $("#d_nome").text((r.dipendente && r.dipendente.nome) || "");
  $("#d_email").text((r.dipendente && r.dipendente.email) || "");
  $("#d_matricola").text((r.dipendente && r.dipendente.matricola) || "");
  $("#d_contratto").text((r.dipendente && r.dipendente.tipo_contratto) || "");
  $("#d_profilo").text((r.dipendente && r.dipendente.profilo) || "");
  $("#d_ufficio").text((r.dipendente && r.dipendente.ufficio) || "");

  $("#p_tipo").text((r.permesso && r.permesso.tipo) || "");
  $("#p_stato").text((r.permesso && r.permesso.stato) || "");
  $("#p_created").text(fmtDateTimeIT(r.permesso && r.permesso.created_at));
  $("#p_updated").text(fmtDateTimeIT(r.permesso && r.permesso.updated_at));
  const perm = r.permesso || {};
  $("#p_registrato_segreteria").prop("checked", String(perm.registrato_segreteria || "0") === "1");

  if (perm.registrato_da_label) {
    $("#p_registrato_da").text(perm.registrato_da_label);
    $("#p_registrato_il").text(perm.registrato_il_fmt ? (" il " + perm.registrato_il_fmt) : "");
    $("#p_registrazione_info").show();
  } else {
    $("#p_registrato_da").text("");
    $("#p_registrato_il").text("");
    $("#p_registrazione_info").hide();
  }
  const statoPerm = (perm.stato || "").toString().toUpperCase();
  let gestitoLabel = "Gestito da:";

  if (statoPerm === "APPROVATO") gestitoLabel = "Approvato da:";
  else if (statoPerm === "RESPINTO") gestitoLabel = "Respinto da:";
  else if (statoPerm === "ANNULLATO") gestitoLabel = "Annullato da:";
  else if (statoPerm === "PARZIALE") gestitoLabel = "Aggiornato da:";

  let det = {};
  try {
    det = JSON.parse(perm.dettagli_json || "{}");
  } catch (e) { }

  if (det.auto_approvato === true) {

    $("#p_gestito_label").text("Esito:");
    $("#p_gestito_da").text("Auto-approvato");

    if (perm.gestito_il_fmt) {
      $("#p_gestito_il").text(" il " + perm.gestito_il_fmt);
    } else {
      $("#p_gestito_il").text("");
    }

    $("#p_gestito_wrap").show();

  } else if (perm.gestito_da_label) {

    $("#p_gestito_label").text(gestitoLabel);
    $("#p_gestito_da").text(perm.gestito_da_label);
    $("#p_gestito_il").text(perm.gestito_il_fmt ? " il " + perm.gestito_il_fmt : "");

    $("#p_gestito_wrap").show();

  } else {

    $("#p_gestito_wrap").hide();

  }
  $("#p_note_richiedente").val((r.permesso && r.permesso.note_richiedente) || "");
  $("#p_note_segreteria").val((r.permesso && r.permesso.note_segreteria) || "");

  $("#righe_list").load("permessoRigheReadRecords.php?id=" + encodeURIComponent(id), function (response, status) {
    if (status !== "success") {
      $("#righe_list").html('<div class="text-danger">Errore caricamento intervalli.</div>');
    }
  });

  $("#permesso_modal").modal("show");
}

function permessoOpen(id) {
  $.ajax({
    url: "permessoReadDetails.php",
    method: "POST",
    dataType: "json",
    data: { id: id },
    success: function (r) {
      if (!r || r.ok !== true) {
        $.notify({ message: (r && r.error) ? r.error : "Errore lettura dettagli" }, { type: "danger" });
        return;
      }

      const tipoCodice = ((r.permesso && r.permesso.tipo_codice) || "").toUpperCase();

      if (tipoCodice === "FERIE") {
        renderPermessoFerieModal(r);
        $("#permesso_ferie_modal").modal("show");
        return;
      }

      openStandardPermessoModal(r, id);
    },
    error: function (xhr) {
      console.error("permessoReadDetails ERROR", xhr.responseText);
      $.notify({ message: "Errore server (dettagli)" }, { type: "danger" });
    }
  });
}

function permessoSave(statoOverride) {
  const id = $("#hidden_permesso_id").val();
  const note = $("#p_note_segreteria").val();
  const registrato = $("#p_registrato_segreteria").is(":checked") ? 1 : 0;

  const payload = {
    id: id,
    note_segreteria: note,
    registrato_segreteria: registrato
  };

  if (statoOverride) {
    payload.stato = statoOverride;
  }

  $.ajax({
    url: "permessoUpdateSegreteria.php",
    method: "POST",
    dataType: "json",
    data: payload,
    success: function (r) {
      if (!r || r.ok !== true) {
        $.notify({
          message: (r && r.error) ? r.error : "Salvataggio fallito"
        }, { type: "danger" });
        return;
      }

      $("#permesso_modal").modal("hide");

      $.notify({
        icon: "glyphicon glyphicon-ok",
        title: "<strong>Permessi</strong>&nbsp;",
        message: statoOverride
          ? ("Stato aggiornato a " + statoOverride)
          : "Salvato"
      }, {
        type: "success",
        placement: { from: "top", align: "center" },
        delay: 2500
      });

      permessiReadRecords();
      dashboardLoad();
    },
    error: function (xhr) {
      console.error("permessoUpdateSegreteria ERROR", xhr.responseText);
      $.notify({ message: "Errore server (salvataggio)" }, { type: "danger" });
    }
  });
}

function permessoSaveFerie(statoOverride) {
  const id = $("#fm_hidden_permesso_id").val();
  const stato = statoOverride || $("#fm_stato_edit").val();
  const note = $("#fm_note_segreteria").val();
  const registrato = $("#fm_registrato_segreteria").is(":checked") ? 1 : 0;

  $.ajax({
    url: "permessoUpdateSegreteria.php",
    method: "POST",
    dataType: "json",
    data: {
      id: id,
      stato: stato,
      note_segreteria: note,
      registrato_segreteria: registrato
    },
    success: function (r) {
      if (!r || r.ok !== true) {
        $.notify({ message: (r && r.error) ? r.error : "Salvataggio fallito" }, { type: "danger" });
        return;
      }

      $("#permesso_ferie_modal").modal("hide");
      permessiReadRecords();
      dashboardLoad();

      $.notify({
        icon: "glyphicon glyphicon-ok",
        title: "<strong>Permessi</strong>&nbsp;",
        message: "Salvato."
      }, { type: "success", placement: { from: "top", align: "center" }, delay: 2500 });
    },
    error: function (xhr) {
      console.error("permessoUpdateSegreteria ERROR FERIE", xhr.responseText);
      $.notify({ message: "Errore server (salvataggio ferie)" }, { type: "danger" });
    }
  });
}

function renderTrend(vals, labels) {
  const max = Math.max.apply(null, vals.concat([1]));

  return vals.map((v, i) => {
    const ratio = v / max;
    let cls = "trend-low";
    if (ratio > 0.80) cls = "trend-high";
    else if (ratio > 0.50) cls = "trend-mid";

    const title = (labels && labels[i] ? labels[i] : "") + ": " + v;
    return `<span class="trend-dot ${cls}" title="${title}">▇</span>`;
  }).join("");
}

function dashboardLoad() {
  $.getJSON("permessiDashboard.php", {}, function (r) {
    if (!r || r.ok !== true) return;

    const s = { INVIATO: 0, APPROVATO: 0, PARZIALE: 0, RESPINTO: 0, ANNULLATO: 0 };
    (r.byStato || []).forEach(x => {
      if (s.hasOwnProperty(x.stato)) s[x.stato] = parseInt(x.n || 0, 10);
    });

    $("#d_inviato .badge").text(s.INVIATO);
    $("#d_approvato .badge").text(s.APPROVATO);
    $("#d_parziale .badge").text(s.PARZIALE);
    $("#d_respinto .badge").text(s.RESPINTO);
    $("#d_annullato .badge").text(s.ANNULLATO);
    $("#d_da_registrare .badge").text(parseInt(r.daRegistrare || 0, 10));
    $("#d_registrato .badge").text(parseInt(r.registrato || 0, 10));

    const mesi = (r.byMese || []).slice(-6);
    const vals = mesi.map(m =>
    (parseInt(m.inviati || 0, 10) +
      parseInt(m.approvati || 0, 10) +
      parseInt(m.respinti || 0, 10))
    );

    const mesiLabel = ["GEN", "FEB", "MAR", "APR", "MAG", "GIU", "LUG", "AGO", "SET", "OTT", "NOV", "DIC"];
    const lab = mesi.map(m => {
      if (!m.ym) return "";
      const parts = m.ym.split("-");
      const y = parts[0];
      const mo = parts[1];
      const idx = parseInt(mo, 10) - 1;
      const yy = y ? y.slice(2) : "";
      return (mesiLabel[idx] || mo) + " " + yy;
    });

    if (mesi.length) {
      const htmlTrend = renderTrend(vals, lab);
      $("#d_trend").html(`Trend: <span class="trend-wrap">${htmlTrend}</span> <span class="trend-labels">(${lab.join(" ")})</span>`);
    } else {
      $("#d_trend").html("");
    }
  });
}

$(document).ready(function () {
  if ($.fn.selectpicker) {
    $("#f_stato, #f_tipo, #f_profilo, #f_ufficio").selectpicker();
  }

  syncDashboardWithStatoFilter();
  dashboardLoad();
  permessiReadRecords();

  $("#btn_refresh").on("click", function (e) {
    e.preventDefault();
    permessiReadRecords();
  });

  $("#f_tipo, #f_profilo, #f_ufficio").on("change", function () {
    permessiReadRecords();
  });

  $("#f_stato").on("change", function () {
    syncDashboardWithStatoFilter();
    permessiReadRecords();
  });

  let t = null;
  $("#f_search").on("keyup", function (e) {
    if (e.key === "Enter") {
      clearTimeout(t);
      permessiReadRecords();
      return;
    }
    clearTimeout(t);
    t = setTimeout(function () {
      permessiReadRecords();
    }, 300);
  });

  $("#btn_save_permesso").on("click", function () { permessoSave(null); });
  $("#btn_approve").on("click", function () { permessoSave("APPROVATO"); });
  $("#btn_reject").on("click", function () { permessoSave("RESPINTO"); });

  $(document).off("click", "#fm_btn_save_notes").on("click", "#fm_btn_save_notes", function (e) {
    e.preventDefault();
    e.stopPropagation();
    saveFerieNotesOnly();
  });

  $(document).off("click", "#fg_btn_save").on("click", "#fg_btn_save", function (e) {
    e.preventDefault();
    e.stopPropagation();
    saveFerieSingleDay(null);
  });

  $(document).off("click", "#fg_btn_approve").on("click", "#fg_btn_approve", function (e) {
    e.preventDefault();
    e.stopPropagation();
    saveFerieSingleDay("APPROVATO");
  });

  $(document).off("click", "#fg_btn_reject").on("click", "#fg_btn_reject", function (e) {
    e.preventDefault();
    e.stopPropagation();
    saveFerieSingleDay("RESPINTO");
  });

  $(document).off("input", "#fm_note_segreteria").on("input", "#fm_note_segreteria", function () {
    updateFerieSaveButtonState();
  });

  $(document).off("change", "#fm_registrato_segreteria").on("change", "#fm_registrato_segreteria", function () {
  updateFerieSaveButtonState();
});

  $(document).off("click", "#fg_btn_approve_all").on("click", "#fg_btn_approve_all", function (e) {
    e.preventDefault();
    e.stopPropagation();
    saveFerieAllDays("APPROVATO");
  });

  $(document).off("click", "#fg_btn_reject_all").on("click", "#fg_btn_reject_all", function (e) {
    e.preventDefault();
    e.stopPropagation();
    saveFerieAllDays("RESPINTO");
  });
});
