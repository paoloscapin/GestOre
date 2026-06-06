/**
 * GestOre - Permessi ATA (dipendente)
 * Regole:
 * - FERIE: intervalli date multipli, NO ore, sottotipo obbligatorio
 * - RECUPERO_ORE: data unica + ore OBBLIGATORIE
 * - VISITA_MEDICA / VISITA_SPEC: data unica, ore FACOLTATIVE
 * - LEGGE_104: può essere
 *    - 1 giorno con fascia oraria (riga ORE: data + ore)
 *    - più giorni / più intervalli (righe GIORNI: dal/al)
 *    - più righe anche miste
 * - Note: solo su richiesta (permesso_note)
 */

function formatDateIT(ymd) {
    if (!ymd) return "";
    const p = String(ymd).split("-");
    if (p.length !== 3) return ymd;
    return `${p[2]}/${p[1]}/${p[0]}`;
}

function scrollModalTop() {
    try {
        $("html, body").animate({
            scrollTop: $("#permesso_editor").offset().top - 10
        }, 200);
    } catch (e) { }
}

function focusFirstFieldInModal() {
    setTimeout(function () {
        $("#permesso_tipo_id").focus();
    }, 250);
}

function notifyCentered(type, title, msg, delay) {
    $.notify(
        {
            icon: type === "danger" ? 'glyphicon glyphicon-warning-sign' : 'glyphicon glyphicon-info-sign',
            title: '<strong>' + title + '</strong><br>',
            message: msg
        },
        {
            placement: { from: "top", align: "center" },
            offset: { x: 0, y: 70 },
            delay: delay || 3500,
            timer: 100,
            mouse_over: "pause",
            type: type,
            z_index: 9999
        }
    );
}

function normalizeAtaTime(value) {
    value = (value || "").toString().trim().replace(/[.,]/g, ":");
    if (value === "") return "";

    let h = "";
    let m = "";

    if (/^\d{1,2}:\d{1,2}(:\d{1,2})?$/.test(value)) {
        const parts = value.split(":");
        h = parts[0];
        m = parts[1];
    } else if (/^\d{3,4}$/.test(value)) {
        h = value.length === 3 ? value.substring(0, 1) : value.substring(0, 2);
        m = value.length === 3 ? value.substring(1) : value.substring(2);
    } else if (/^\d{1,2}$/.test(value)) {
        h = value;
        m = "00";
    } else {
        return value;
    }

    const hh = parseInt(h, 10);
    const mm = parseInt(m, 10);

    if (isNaN(hh) || isNaN(mm) || hh < 0 || hh > 23 || mm < 0 || mm > 59) {
        return value;
    }

    return String(hh).padStart(2, "0") + ":" + String(mm).padStart(2, "0");
}

function ataTimeInputHtml(className, value) {
    return `<input type="text" class="form-control input-sm time-input ${className}" list="ata_time_options" inputmode="numeric" maxlength="8" placeholder="HH:MM" autocomplete="off" value="${normalizeAtaTime(value || "")}">`;
}

function parseAtaOreIntere(value) {
    const raw = String(value || "").trim();
    if (!/^\d+$/.test(raw)) return null;
    const n = parseInt(raw, 10);
    return Number.isInteger(n) && n > 0 ? n : null;
}

function addHoursToAtaTime(time, hours) {
    const t = normalizeAtaTime(time);
    const n = parseAtaOreIntere(hours);
    if (!t || !n) return "";

    const parts = t.split(":");
    const minutes = (parseInt(parts[0], 10) * 60) + parseInt(parts[1], 10) + (n * 60);
    if (minutes > (23 * 60 + 59)) return "";

    const hh = Math.floor(minutes / 60);
    const mm = minutes % 60;
    return String(hh).padStart(2, "0") + ":" + String(mm).padStart(2, "0");
}

function diffHoursIntere(oraDa, oraA) {
    const da = normalizeAtaTime(oraDa);
    const a = normalizeAtaTime(oraA);
    if (!da || !a) return "";

    const pDa = da.split(":");
    const pA = a.split(":");
    const minDa = (parseInt(pDa[0], 10) * 60) + parseInt(pDa[1], 10);
    const minA = (parseInt(pA[0], 10) * 60) + parseInt(pA[1], 10);
    const diff = minA - minDa;
    if (diff <= 0 || diff % 60 !== 0) return "";
    return String(diff / 60);
}

function updateFeriePeriodoUI() {
    const sottotipo = ($("#ferie_sottotipo").val() || "").toString().trim().toUpperCase();

    const $box = $("#ferie_periodo_box");
    const $txt = $("#ferie_periodo_testo");

    if (!sottotipo) {
        $box.hide();
        $txt.text("");
        return;
    }

    if (sottotipo === "GENERICHE") {
        $box.show();
        $txt.text("Periodo possibile: nessun vincolo (ferie generiche).");
        return;
    }

    const m = (window.__FERIE_FINESTRE || {});
    const f = m[sottotipo];

    if (f && f.data_inizio && f.data_fine) {
        $box.show();
        $txt.text(`Periodo possibile: ${formatDateIT(f.data_inizio)} – ${formatDateIT(f.data_fine)}`);
    } else {
        $box.show();
        $txt.text("Periodo possibile: non configurato (contatta la segreteria).");
    }
}

function permessiReadRecords() {
    $.get("permessiReadRecords.php", {}, function (html) {
        $("#records_content").html(html);
    });
}

function escapeHtmlAta(value) {
    return String(value == null ? "" : value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function renderFerieSummary(data) {
    if (!data || data.ok !== true) {
        return '<div class="ferie-summary-empty">Riepilogo non disponibile.</div>';
    }

    const months = Array.isArray(data.months) ? data.months : [];
    const ranges = Array.isArray(data.ranges) ? data.ranges : [];
    const counts = data.state_counts || {};
    const totalDays = parseInt(data.total_days || 0, 10) || 0;
    const requestCount = parseInt(data.request_count || 0, 10) || 0;
    const clickedDays = parseInt(data.clicked_days || 0, 10) || 0;
    const title = escapeHtmlAta(data.title || "Ferie");
    const windowLabel = escapeHtmlAta(data.window && data.window.label ? data.window.label : "-");

    let html = `
        <div class="ferie-summary-cards">
            <div class="ferie-summary-card">
                <span>Totale giorni</span>
                <strong>${totalDays}</strong>
            </div>
            <div class="ferie-summary-card">
                <span>Richieste considerate</span>
                <strong>${requestCount}</strong>
            </div>
            <div class="ferie-summary-card">
                <span>Giorni richiesta aperta</span>
                <strong>${clickedDays}</strong>
            </div>
        </div>
        <div class="ferie-summary-ranges">
            <strong>${title}</strong><br>
            Finestra: ${windowLabel}<br>
            Periodi: ${ranges.length ? ranges.map(escapeHtmlAta).join(", ") : "nessun giorno attivo"}<br>
            Approvati: ${parseInt(counts.APPROVATO || 0, 10) || 0}
            &middot; Richiesti/aggiunti: ${(parseInt(counts.RICHIESTO || 0, 10) || 0) + (parseInt(counts.AGGIUNTO || 0, 10) || 0)}
            &middot; Respinti: ${parseInt(counts.RESPINTO || 0, 10) || 0}
            &middot; Bozza: ${parseInt(counts.BOZZA || 0, 10) || 0}
        </div>
    `;

    if (months.length === 0) {
        html += '<div class="ferie-summary-empty">Non ci sono giorni ferie attivi per questa tipologia.</div>';
        return html;
    }

    for (const month of months) {
        const days = Array.isArray(month.days) ? month.days : [];
        html += `
            <div class="ferie-summary-month">
                <div class="ferie-summary-month-title">${escapeHtmlAta(month.label || "")}</div>
                <div class="ferie-summary-days">
        `;

        for (const day of days) {
            const state = String(day.state || "RICHIESTO").toUpperCase().replace(/[^A-Z_]/g, "");
            html += `
                <div class="ferie-summary-day ${state} ${day.current ? "current" : ""}">
                    <span class="weekday">${escapeHtmlAta(day.weekday || "")}</span>
                    <span class="number">${escapeHtmlAta(day.day || "")}</span>
                    <span class="state">${escapeHtmlAta(day.state_label || "")}</span>
                </div>
            `;
        }

        html += `
                </div>
            </div>
        `;
    }

    return html;
}

function openFerieSummary(id, sottotipo) {
    $("#ferie_summary_content").html('<div class="ferie-summary-empty">Caricamento riepilogo...</div>');
    $("#ferie_summary_modal").modal("show");

    $.ajax({
        url: "ferieRiepilogoRead.php",
        method: "POST",
        dataType: "json",
        data: {
            id: id,
            sottotipo: sottotipo
        },
        success: function (response) {
            if (!response || response.ok !== true) {
                $("#ferie_summary_content").html('<div class="ferie-summary-empty">' + escapeHtmlAta((response && response.error) ? response.error : "Errore lettura riepilogo.") + '</div>');
                return;
            }

            $("#ferie_summary_content").html(renderFerieSummary(response));
        },
        error: function (xhr) {
            $("#ferie_summary_content").html('<div class="ferie-summary-empty">Errore server: ' + escapeHtmlAta(xhr.status) + '</div>');
        }
    });
}

function getTipoCodiceSelezionato() {
    const $opt = $("#permesso_tipo_id option:selected");
    return ($opt.data("codice") || "").toString().trim();
}

/* ===========================
 * TEMPLATE FERIE (date only)
 * =========================== */
function rigaFerieTemplate(r) {
    const data_da = (r && r.data_da) ? r.data_da : "";
    const data_a = (r && r.data_a) ? r.data_a : "";
    return `
  <div class="well well-sm ferie-riga">
    <div class="row">
      <div class="col-md-5 col-sm-5 col-xs-12">
        <label>Dal</label>
        <input type="date" class="form-control input-sm r_data_da" value="${data_da}">
      </div>
      <div class="col-md-5 col-sm-5 col-xs-12">
        <label>Al</label>
        <input type="date" class="form-control input-sm r_data_a" value="${data_a}">
      </div>
      <div class="col-md-2 col-sm-2 col-xs-12 text-right">
        <label>&nbsp;</label><br>
        <button type="button" class="btn btn-danger btn_del_ferie">
          <span class="glyphicon glyphicon-trash"></span> Elimina
        </button>
      </div>
    </div>
  </div>`;
}

/* ===========================
 * TEMPLATE LEGGE 104 (mixed)
 * =========================== */
function riga104Template(r) {
    const unita = (r && r.unita) ? String(r.unita).toUpperCase() : "GIORNI";

    const data = (r && (r.data_da || r.data_a)) ? (r.data_da || r.data_a) : "";
    const data_da = (r && r.data_da) ? r.data_da : "";
    const data_a = (r && r.data_a) ? r.data_a : "";
    const ora_da = (r && r.ora_da) ? r.ora_da : "";
    const ora_a = (r && r.ora_a) ? r.ora_a : "";
    const durata_ore = (r && r.durata_ore) ? r.durata_ore : diffHoursIntere(ora_da, ora_a);

    return `
  <div class="well well-sm riga-104">
    <div class="row">
      <div class="col-md-2 col-sm-3 col-xs-12">
        <label>Unità</label>
        <select class="form-control input-sm r104_unita">
          <option value="GIORNI" ${unita === "GIORNI" ? "selected" : ""}>GIORNI</option>
          <option value="ORE" ${unita === "ORE" ? "selected" : ""}>ORE</option>
        </select>
      </div>

      <div class="col-md-4 col-sm-4 col-xs-12 r104_block_giorni">
        <label>Dal</label>
        <input type="date" class="form-control input-sm r104_data_da" value="${data_da}">
      </div>

      <div class="col-md-4 col-sm-4 col-xs-12 r104_block_giorni">
        <label>Al</label>
        <input type="date" class="form-control input-sm r104_data_a" value="${data_a}">
      </div>

      <div class="col-md-3 col-sm-4 col-xs-12 r104_block_ore" style="display:none;">
        <label>Data</label>
        <input type="date" class="form-control input-sm r104_data" value="${data}">
      </div>

      <div class="col-md-2 col-sm-4 col-xs-6 r104_block_ore" style="display:none;">
        <label>Dalle ore</label>
        ${ataTimeInputHtml("r104_ora_da", ora_da)}
      </div>

      <div class="col-md-2 col-sm-4 col-xs-6 r104_block_ore" style="display:none;">
        <label>Per ore</label>
        <input type="number" class="form-control input-sm r104_durata_ore" min="1" step="1" inputmode="numeric" placeholder="N" value="${durata_ore || ""}">
      </div>

      <div class="col-md-2 col-sm-3 col-xs-12 text-right">
        <label>&nbsp;</label><br>
        <button type="button" class="btn btn-danger btn_del_104">
          <span class="glyphicon glyphicon-trash"></span> Elimina
        </button>
      </div>
    </div>
  </div>`;
}

function apply104RowUI($r) {
    const unita = ($r.find(".r104_unita").val() || "GIORNI").toString();

    if (unita === "GIORNI") {
        $r.find(".r104_block_giorni").show();
        $r.find(".r104_block_ore").hide();

        $r.find(".r104_data").val("");
        $r.find(".r104_ora_da").val("");
        $r.find(".r104_durata_ore").val("");
    } else {
        $r.find(".r104_block_giorni").hide();
        $r.find(".r104_block_ore").show();

        $r.find(".r104_data_da").val("");
        $r.find(".r104_data_a").val("");
    }
}

/* ===========================
 * TEMPLATE PERMESSI A RIGHE ORARIE
 * =========================== */
function singoloExtraTemplate(r) {
    r = r || {};
    const data = r.data_da || r.data_a || "";
    const ora_da = normalizeAtaTime(r.ora_da || "");
    const ora_a = normalizeAtaTime(r.ora_a || "");
    const durata_ore = r.durata_ore || diffHoursIntere(ora_da, ora_a);

    return `
  <div class="well well-sm riga-singolo-extra">
    <div class="row">
      <div class="col-md-3 col-sm-4 col-xs-12">
        <label>Data</label>
        <input type="date" class="form-control input-sm s_data" value="${data}">
      </div>

      <div class="col-md-3 col-sm-4 col-xs-12 singolo_extra_ora_da">
        <label>Dalle ore</label>
        ${ataTimeInputHtml("s_ora_da", ora_da)}
      </div>

      <div class="col-md-3 col-sm-4 col-xs-12 singolo_extra_ora_a">
        <label>Ora rientro</label>
        ${ataTimeInputHtml("s_ora_a", ora_a)}
      </div>

      <div class="col-md-3 col-sm-4 col-xs-12 singolo_extra_durata_ore" style="display:none;">
        <label>Recupero di ore</label>
        <input type="number" class="form-control input-sm s_durata_ore" min="1" step="1" inputmode="numeric" placeholder="N ore" value="${durata_ore || ""}">
      </div>

      <div class="col-md-2 col-sm-3 col-xs-12 text-right">
        <label>&nbsp;</label><br>
        <button type="button" class="btn btn-danger btn_del_singolo">
          <span class="glyphicon glyphicon-trash"></span> Elimina
        </button>
      </div>
    </div>
  </div>`;
}

function applySingoloExtraRowUI($r) {
    const tipo = getTipoCodiceSelezionato();
    const isRecupero = tipo === "RECUPERO_ORE";

    $r.find(".singolo_extra_ora_da").show();
    $r.find(".singolo_extra_ora_a").toggle(!isRecupero);
    $r.find(".singolo_extra_durata_ore").toggle(isRecupero);

    if (isRecupero) {
        $r.find(".s_ora_a").val("");
    } else {
        $r.find(".s_durata_ore").val("");
    }
}

function appendSingoloExtraRow(r) {
    $("#righe_singolo_extra_container").append(singoloExtraTemplate(r));
    applySingoloExtraRowUI($("#righe_singolo_extra_container .riga-singolo-extra").last());
}

/* ===========================
 * UI SWITCH BY TIPO
 * =========================== */
function resetBlocks() {
    $("#block_ferie_sottotipo").hide();
    $("#block_singolo").hide();
    $("#block_ferie_multi").hide();
    $("#block_104_multi").hide();

    $("#block_singolo_ora_da").hide();
    $("#block_singolo_ora_a").hide();
    $("#block_singolo_durata_ore").hide();
    $("#singolo_hint").hide().text("");

    $("#btn_add_ferie").prop("disabled", true);
    $("#btn_add_104").prop("disabled", true);
    $("#btn_add_singolo").prop("disabled", true).hide();
    $("#btn_add_104").hide();
}

function applyTipoUI() {
    const tipo = getTipoCodiceSelezionato();

    resetBlocks();

    if (tipo !== "FERIE") {
        $("#ferie_sottotipo").val("");
        $("#righe_ferie_container").empty();
        updateFeriePeriodoUI();
    }
    if (tipo !== "LEGGE_104") {
        $("#righe_104_container").empty();
    }
    if (tipo === "FERIE" || tipo === "LEGGE_104") {
        $("#righe_singolo_extra_container").empty();
    }

    if (!tipo) {
        scrollModalTop();
        return;
    }

    if (tipo === "FERIE") {
        $("#block_ferie_sottotipo").show();
        $("#block_ferie_multi").show();
        $("#btn_add_ferie").prop("disabled", false);

        if ($("#righe_ferie_container .ferie-riga").length === 0) {
            $("#righe_ferie_container").html(rigaFerieTemplate());
        }

        scrollModalTop();
        return;
    }

    if (tipo === "LEGGE_104") {
        $("#block_104_multi").show();
        $("#btn_add_104").prop("disabled", false).show();

        if ($("#righe_104_container .riga-104").length === 0) {
            $("#righe_104_container").html(riga104Template({ unita: "GIORNI" }));
            apply104RowUI($("#righe_104_container .riga-104").first());
        } else {
            $("#righe_104_container .riga-104").each(function () {
                apply104RowUI($(this));
            });
        }

        scrollModalTop();
        return;
    }

    $("#block_singolo").show();
    $("#btn_add_singolo").prop("disabled", false).show();

    if (tipo === "RECUPERO_ORE") {
        $("#block_singolo_ora_da").show();
        $("#block_singolo_durata_ore").show();
        $("#singolo_hint").show().text("Inserisci una o piu' date, l'ora di inizio e il numero intero di ore da recuperare.");
    } else if (tipo === "VISITA_MEDICA" || tipo === "VISITA_SPEC") {
        $("#block_singolo_ora_da").show();
        $("#block_singolo_ora_a").show();
        $("#singolo_hint").show().text("Inserisci una o piu' date. L'ora di rientro e' facoltativa.");
    } else {
        $("#block_singolo_ora_da").show();
        $("#block_singolo_ora_a").show();
        $("#singolo_hint").show().text("Inserisci una o piu' date. Per i permessi brevi l'ora di rientro e' facoltativa.");
    }

    $("#righe_singolo_extra_container .riga-singolo-extra").each(function () {
        applySingoloExtraRowUI($(this));
    });

    scrollModalTop();
}

/* ===========================
 * COLLECT
 * =========================== */
function collectRighe() {
    const tipo = getTipoCodiceSelezionato();

    if (tipo === "FERIE") {
        const righe = [];
        $("#righe_ferie_container .ferie-riga").each(function () {
            const $r = $(this);
            righe.push({
                unita: "GIORNI",
                data_da: $r.find(".r_data_da").val(),
                data_a: $r.find(".r_data_a").val(),
                ora_da: null,
                ora_a: null
            });
        });
        return righe;
    }

    if (tipo === "LEGGE_104") {
        const righe = [];
        $("#righe_104_container .riga-104").each(function () {
            const $r = $(this);
            const unita = ($r.find(".r104_unita").val() || "GIORNI").toString();

            if (unita === "GIORNI") {
                righe.push({
                    unita: "GIORNI",
                    data_da: $r.find(".r104_data_da").val(),
                    data_a: $r.find(".r104_data_a").val(),
                    ora_da: null,
                    ora_a: null
                });
            } else {
                const d = $r.find(".r104_data").val();
                const durataOre = parseAtaOreIntere($r.find(".r104_durata_ore").val());
                const oraDa = normalizeAtaTime($r.find(".r104_ora_da").val()) || null;
                righe.push({
                    unita: "ORE",
                    data_da: d,
                    data_a: d,
                    ora_da: oraDa,
                    ora_a: addHoursToAtaTime(oraDa, durataOre) || null,
                    durata_ore: durataOre
                });
            }
        });
        return righe;
    }

    const righe = [];
    const data = $("#singolo_data").val();
    const ora_da = normalizeAtaTime($("#singolo_ora_da").val());
    const ora_a = normalizeAtaTime($("#singolo_ora_a").val());
    const durata_ore = parseAtaOreIntere($("#singolo_durata_ore").val());

    righe.push({
        unita: "ORE",
        data_da: data,
        data_a: data,
        ora_da: ora_da ? ora_da : null,
        ora_a: tipo === "RECUPERO_ORE" ? (addHoursToAtaTime(ora_da, durata_ore) || null) : (ora_a ? ora_a : null),
        durata_ore: tipo === "RECUPERO_ORE" ? durata_ore : null
    });

    $("#righe_singolo_extra_container .riga-singolo-extra").each(function () {
        const $r = $(this);
        const rowData = $r.find(".s_data").val();
        const rowOraDa = normalizeAtaTime($r.find(".s_ora_da").val());
        const rowOraA = normalizeAtaTime($r.find(".s_ora_a").val());
        const rowDurataOre = parseAtaOreIntere($r.find(".s_durata_ore").val());

        righe.push({
            unita: "ORE",
            data_da: rowData,
            data_a: rowData,
            ora_da: rowOraDa ? rowOraDa : null,
            ora_a: tipo === "RECUPERO_ORE" ? (addHoursToAtaTime(rowOraDa, rowDurataOre) || null) : (rowOraA ? rowOraA : null),
            durata_ore: tipo === "RECUPERO_ORE" ? rowDurataOre : null
        });
    });

    return righe;
}

function permessoHideError() {
    $("#permesso_alert").hide().text("");
}

function permessoShowError(msg) {
    $("#permesso_alert").text(msg).show();
    scrollModalTop();
}

function showPermessoEditor() {
    $("#permesso_editor").show();
    $("#permessi_records_wrap").hide();
    scrollModalTop();
}

function hidePermessoEditor() {
    $("#permesso_editor").hide();
    $("#permessi_records_wrap").show();
    permessoHideError();
}

/* ===========================
 * MODAL OPEN / DETAILS
 * =========================== */
function openNewPermesso() {
    permessoHideError();

    $("#permesso_id").val("");
    $("#permesso_tipo_id").val("");
    $("#permesso_note").val("");
    $("#permesso_stato").val("BOZZA");

    $("#ferie_sottotipo").val("");
    $("#singolo_data").val("");
    $("#singolo_ora_da").val("");
    $("#singolo_ora_a").val("");
    $("#singolo_durata_ore").val("");

    $("#righe_ferie_container").empty();
    $("#righe_104_container").empty();
    $("#righe_singolo_extra_container").empty();

    resetBlocks();
    updateFeriePeriodoUI();

    $("#permesso_tipo_id").prop("disabled", false);
    $("#permesso_note").prop("readonly", false);
    $("#btn_save_bozza").prop("disabled", false);
    $("#btn_invia").prop("disabled", false);

    $("#block_singolo :input").prop("disabled", false);
    $("#block_ferie_multi :input").prop("disabled", false);
    $("#block_104_multi :input").prop("disabled", false);
    $("#btn_add_singolo").prop("disabled", false);
    $("#btn_cancel_permesso").show();
    $("#btn_save_bozza").show();
    $("#btn_invia").show();
    $("#btn_rimetti_bozza").hide();
    $("#permesso_editor_title").text("Nuova richiesta");
    showPermessoEditor();
    focusFirstFieldInModal();
}

function permessoGetDetails(id) {
    permessoHideError();

    $.post("permessoReadDetails.php", { id: id }, function (r) {
        if (!r || r.ok !== true) {
            notifyCentered("danger", "Permessi ATA", "Errore lettura dettagli.", 5000);
            return;
        }

        const p = r.richiesta;
        const tipo_codice = (p.tipo_codice || "").toString().trim();

        $("#permesso_id").val(p.id);
        $("#permesso_tipo_id").val(p.permesso_ata_tipo_id);
        $("#permesso_note").val(p.note || "");
        $("#permesso_stato").val(p.stato || "");
        $("#ferie_sottotipo").val(p.ferie_sottotipo || "");

        applyTipoUI();

        if (tipo_codice === "FERIE") {
            $("#righe_ferie_container").empty();
            if (r.righe && r.righe.length) {
                r.righe.forEach(rr => $("#righe_ferie_container").append(rigaFerieTemplate(rr)));
            } else {
                $("#righe_ferie_container").append(rigaFerieTemplate());
            }
        } else if (tipo_codice === "LEGGE_104") {
            $("#righe_104_container").empty();
            if (r.righe && r.righe.length) {
                r.righe.forEach(rr => $("#righe_104_container").append(riga104Template(rr)));
            } else {
                $("#righe_104_container").append(riga104Template({ unita: "GIORNI" }));
            }
            $("#righe_104_container .riga-104").each(function () {
                apply104RowUI($(this));
            });
        } else {
            $("#righe_singolo_extra_container").empty();
            const righe = r.righe || [];
            const rr = righe.length ? righe[0] : null;
            $("#singolo_data").val(rr ? (rr.data_da || "") : "");
            $("#singolo_ora_da").val(rr ? normalizeAtaTime(rr.ora_da || "") : "");
            $("#singolo_ora_a").val(rr ? normalizeAtaTime(rr.ora_a || "") : "");
            $("#singolo_durata_ore").val(rr ? (rr.durata_ore || diffHoursIntere(rr.ora_da || "", rr.ora_a || "")) : "");
            righe.slice(1).forEach(extra => appendSingoloExtraRow(extra));
        }

        const stato = String(p.stato || "").toUpperCase();
        const isBozza = (stato === "BOZZA");
        const isInviato = (stato === "INVIATO" || stato === "INVIATA");
        const isApprovato = (stato === "APPROVATO" || stato === "APPROVATA" || stato === "APPROVATO_PARZIALE");
        const editable = isBozza;

        $("#permesso_tipo_id").prop("disabled", !editable);
        $("#permesso_note").prop("readonly", !editable);
        $("#ferie_sottotipo").prop("disabled", !editable);

        $("#btn_add_ferie").prop("disabled", !editable || (tipo_codice !== "FERIE"));
        $("#btn_add_104").prop("disabled", !editable || (tipo_codice !== "LEGGE_104"));
        $("#btn_add_singolo").prop("disabled", !editable || tipo_codice === "FERIE" || tipo_codice === "LEGGE_104");

        if (!editable) {
            $("#block_singolo :input").prop("disabled", true);
            $("#block_ferie_multi :input").prop("disabled", true);
            $("#block_104_multi :input").prop("disabled", true);
            $("#righe_singolo_extra_container :input").prop("disabled", true);
        } else {
            $("#block_singolo :input").prop("disabled", false);
            $("#block_ferie_multi :input").prop("disabled", false);
            $("#block_104_multi :input").prop("disabled", false);
            $("#righe_singolo_extra_container :input").prop("disabled", false);
        }

        /* visibilità pulsanti footer */
        $("#btn_cancel_permesso").show();

        if (isBozza) {
            $("#btn_save_bozza").show().prop("disabled", false);
            $("#btn_invia").show().prop("disabled", false);
            $("#btn_rimetti_bozza").hide();
        } else if (isInviato) {
            $("#btn_save_bozza").hide();
            $("#btn_invia").hide();
            $("#btn_rimetti_bozza").show().prop("disabled", false);
        } else if (isApprovato) {
            $("#btn_save_bozza").hide();
            $("#btn_invia").hide();
            $("#btn_rimetti_bozza").hide();
        } else {
            $("#btn_save_bozza").hide();
            $("#btn_invia").hide();
            $("#btn_rimetti_bozza").hide();
        }

        $("#permesso_editor_title").text("Modifica richiesta");
        showPermessoEditor();
        focusFirstFieldInModal();
    }, "json");
}

/* ===========================
 * NOTIFY + SAVE + DELETE
 * =========================== */
function notifyErr(msg) {
    notifyCentered("danger", "Permessi ATA", msg, 5000);
}

function permessoSave(azione) {
    const permesso_id = $("#permesso_id").val();
    const tipo_id = $("#permesso_tipo_id").val();
    const tipo_codice = getTipoCodiceSelezionato();
    const note = $("#permesso_note").val();

    const righe = collectRighe();

    $.ajax({
        url: "permessoSave.php",
        method: "POST",
        dataType: "json",
        data: {
            permesso_id: permesso_id,
            permesso_tipo_id: tipo_id,
            note: note,
            azione: azione,
            righe_json: JSON.stringify(righe)
        },
        success: function (r) {
            console.log("SAVE response:", r);

            if (!r || r.ok !== true) {
                const msg = (r && r.error) ? r.error : "Errore salvataggio.";
                permessoShowError(msg);
                notifyErr(msg);
                return;
            }

            permessoHideError();
            hidePermessoEditor();
            permessiReadRecords();

            notifyCentered(
                "info",
                "Permessi ATA",
                (azione === "INVIA") ? "Richiesta inviata." : "Bozza salvata.",
                2500
            );
        },
        error: function (xhr) {
            const msg = "Errore server: " + xhr.status;
            permessoShowError(msg);
            notifyErr(msg);
        }
    });
}

function permessoDelete(id) {
    if (!confirm("Vuoi eliminare questa bozza di richiesta?")) return;

    $.ajax({
        url: "permessoDelete.php",
        method: "POST",
        dataType: "json",
        data: { id: id },
        success: function (r) {
            if (!r || r.ok !== true) {
                notifyErr((r && r.error) ? r.error : "Errore cancellazione.");
                return;
            }

            permessiReadRecords();
            notifyCentered("info", "Permessi ATA", "Bozza eliminata.", 2200);
        },
        error: function (xhr) {
            notifyErr("Errore server: " + xhr.status);
        }
    });
}

function permessoRimettiInBozza(id) {
    if (!confirm("Vuoi rimettere questa richiesta in bozza?")) return;

    $.ajax({
        url: "permessoRimettiBozza.php",
        method: "POST",
        dataType: "json",
        data: { id: id },
        success: function (r) {
            if (!r || r.ok !== true) {
                notifyErr((r && r.error) ? r.error : "Errore aggiornamento stato.");
                return;
            }

            hidePermessoEditor();
            permessiReadRecords();
            notifyCentered("info", "Permessi ATA", "Richiesta rimessa in bozza.", 2200);
        },
        error: function (xhr) {
            notifyErr("Errore server: " + xhr.status);
        }
    });
}

/* ===========================
 * EVENTS
 * =========================== */
$(document).on("click", "#btn_new", function () {
    openNewPermesso();
});

$(document).on("change", "#permesso_tipo_id", function () {
    $("#singolo_data").val("");
    $("#singolo_ora_da").val("");
    $("#singolo_ora_a").val("");
    $("#singolo_durata_ore").val("");
    $("#righe_ferie_container").empty();
    $("#righe_104_container").empty();
    $("#righe_singolo_extra_container").empty();
    $("#ferie_sottotipo").val("");
    applyTipoUI();
});

/* FERIE */
$(document).on("click", "#btn_add_ferie", function () {
    $("#righe_ferie_container").append(rigaFerieTemplate());
});

$(document).on("click", ".btn_del_ferie", function () {
    const $all = $("#righe_ferie_container .ferie-riga");
    if ($all.length <= 1) {
        $(this).closest(".ferie-riga").find(":input").val("");
        return;
    }
    $(this).closest(".ferie-riga").remove();
});

/* LEGGE_104 */
$(document).on("click", "#btn_add_104", function () {
    $("#righe_104_container").append(riga104Template({ unita: "GIORNI" }));
    apply104RowUI($("#righe_104_container .riga-104").last());
});

$(document).on("change", ".riga-104 .r104_unita", function () {
    apply104RowUI($(this).closest(".riga-104"));
});

$(document).on("click", ".btn_del_104", function () {
    const $all = $("#righe_104_container .riga-104");
    if ($all.length <= 1) {
        $(this).closest(".riga-104").find(":input").val("");
        apply104RowUI($(this).closest(".riga-104"));
        return;
    }
    $(this).closest(".riga-104").remove();
});

/* PERMESSI A RIGHE ORARIE */
$(document).on("click", "#btn_add_singolo", function () {
    appendSingoloExtraRow();
});

$(document).on("click", ".btn_del_singolo", function () {
    $(this).closest(".riga-singolo-extra").remove();
});

$(document).on("blur change", ".time-input", function () {
    $(this).val(normalizeAtaTime($(this).val()));
});

$(document).on("click", "#btn_cancel_permesso", function () {
    hidePermessoEditor();
});

$(document).on("click", "#btn_rimetti_bozza", function () {
    const id = parseInt($("#permesso_id").val(), 10) || 0;
    if (id > 0) permessoRimettiInBozza(id);
});

$(document).on("click", "#btn_save_bozza", function () {
    permessoSave("BOZZA");
});

$(document).on("click", "#btn_invia", function () {
    permessoSave("INVIA");
});

$(document).on("change", "#ferie_sottotipo", function () {
    updateFeriePeriodoUI();
});

$(document).on("click", ".btn-open-permesso", function () {
    const id = parseInt($(this).data("id"), 10) || 0;
    if (id > 0) permessoGetDetails(id);
});

$(document).on("click", ".btn-ferie-riepilogo", function () {
    const id = parseInt($(this).data("id"), 10) || 0;
    const sottotipo = String($(this).data("sottotipo") || "").toUpperCase().trim();
    if (id > 0 && sottotipo) openFerieSummary(id, sottotipo);
});

$(document).on("click", ".btn-delete-permesso", function () {
    const id = parseInt($(this).data("id"), 10) || 0;
    const codice = String($(this).data("codice") || "").toUpperCase().trim();
    const ferieSottotipo = String($(this).data("ferie-sottotipo") || "").toUpperCase().trim();

    if (id <= 0) return;

    if (codice === "FERIE" && ferieSottotipo) {
        if (!confirm("Vuoi eliminare questa bozza di richiesta ferie?")) return;

        $.ajax({
            url: "ferieRichiestaDelete.php",
            method: "POST",
            dataType: "json",
            data: {
                id: id,
                sottotipo: ferieSottotipo
            },
            success: function (r) {
                if (!r || r.ok !== true) {
                    notifyErr((r && r.error) ? r.error : "Errore cancellazione.");
                    return;
                }

                permessiReadRecords();
                notifyCentered("info", "Permessi ATA", "Bozza eliminata.", 2200);
            },
            error: function (xhr) {
                notifyErr("Errore server: " + xhr.status);
            }
        });

        return;
    }

    permessoDelete(id);
});

$(document).ready(function () {
    hidePermessoEditor();
    permessiReadRecords();
});
