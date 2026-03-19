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
    // ymd = 'YYYY-MM-DD'
    if (!ymd) return "";
    const p = String(ymd).split("-");
    if (p.length !== 3) return ymd;
    return `${p[2]}/${p[1]}/${p[0]}`;
}

function updateFeriePeriodoUI() {
    const sottotipo = ($("#ferie_sottotipo").val() || "").toString().trim().toUpperCase();

    // box
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
        $(".records_content").html(html);
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
  <div class="well well-sm ferie-riga" style="margin-bottom:8px;">
    <div class="row">
      <div class="col-md-5">
        <label>Dal</label>
        <input type="date" class="form-control input-sm r_data_da" value="${data_da}">
      </div>
      <div class="col-md-5">
        <label>Al</label>
        <input type="date" class="form-control input-sm r_data_a" value="${data_a}">
      </div>
      <div class="col-md-2 text-right">
        <label>&nbsp;</label><br>
        <button type="button" class="btn btn-xs btn-danger btn_del_ferie">
          <span class="glyphicon glyphicon-trash"></span>
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

    // Per ORE usiamo data_da come data singola
    const data = (r && (r.data_da || r.data_a)) ? (r.data_da || r.data_a) : "";
    const data_da = (r && r.data_da) ? r.data_da : "";
    const data_a = (r && r.data_a) ? r.data_a : "";
    const ora_da = (r && r.ora_da) ? r.ora_da : "";
    const ora_a = (r && r.ora_a) ? r.ora_a : "";

    return `
  <div class="well well-sm riga-104" style="margin-bottom:8px;">
    <div class="row">
      <div class="col-md-2">
        <label>Unità</label>
        <select class="form-control input-sm r104_unita">
          <option value="GIORNI" ${unita === "GIORNI" ? "selected" : ""}>GIORNI</option>
          <option value="ORE" ${unita === "ORE" ? "selected" : ""}>ORE</option>
        </select>
      </div>

      <div class="col-md-4 r104_block_giorni">
        <label>Dal</label>
        <input type="date" class="form-control input-sm r104_data_da" value="${data_da}">
      </div>

      <div class="col-md-4 r104_block_giorni">
        <label>Al</label>
        <input type="date" class="form-control input-sm r104_data_a" value="${data_a}">
      </div>

      <div class="col-md-3 r104_block_ore" style="display:none;">
        <label>Data</label>
        <input type="date" class="form-control input-sm r104_data" value="${data}">
      </div>

      <div class="col-md-2 r104_block_ore" style="display:none;">
        <label>Ore da</label>
        <input type="time" class="form-control input-sm r104_ora_da" value="${ora_da}">
      </div>

      <div class="col-md-2 r104_block_ore" style="display:none;">
        <label>Ore a</label>
        <input type="time" class="form-control input-sm r104_ora_a" value="${ora_a}">
      </div>

      <div class="col-md-2 text-right">
        <label>&nbsp;</label><br>
        <button type="button" class="btn btn-xs btn-danger btn_del_104">
          <span class="glyphicon glyphicon-trash"></span>
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

        // pulisco ore
        $r.find(".r104_data").val("");
        $r.find(".r104_ora_da").val("");
        $r.find(".r104_ora_a").val("");
    } else {
        $r.find(".r104_block_giorni").hide();
        $r.find(".r104_block_ore").show();

        // pulisco range giorni
        $r.find(".r104_data_da").val("");
        $r.find(".r104_data_a").val("");
    }
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
    $("#singolo_hint").hide().text("");

    $("#btn_add_ferie").prop("disabled", true);
    $("#btn_add_104").prop("disabled", true);
}

function applyTipoUI() {
    const tipo = getTipoCodiceSelezionato();

    resetBlocks();

    // reset campi specifici fuori tipo
    if (tipo !== "FERIE") {
        $("#ferie_sottotipo").val("");
        $("#righe_ferie_container").empty();
        updateFeriePeriodoUI();
    }
    if (tipo !== "LEGGE_104") {
        $("#righe_104_container").empty();
    }

    if (tipo === "FERIE") {
        $("#block_ferie_sottotipo").show();
        $("#block_ferie_multi").show();
        $("#btn_add_ferie").prop("disabled", false);

        if ($("#righe_ferie_container .ferie-riga").length === 0) {
            $("#righe_ferie_container").html(rigaFerieTemplate());
        }
        return;
    }

    if (tipo === "LEGGE_104") {
        $("#block_104_multi").show();
        $("#btn_add_104").prop("disabled", false);

        if ($("#righe_104_container .riga-104").length === 0) {
            $("#righe_104_container").html(riga104Template({ unita: "GIORNI" }));
            apply104RowUI($("#righe_104_container .riga-104").first());
        } else {
            $("#righe_104_container .riga-104").each(function () {
                apply104RowUI($(this));
            });
        }
        return;
    }

    // altri tipi: singolo
    $("#block_singolo").show();

    if (tipo === "RECUPERO_ORE") {
        $("#block_singolo_ora_da").show();
        $("#block_singolo_ora_a").show();
        $("#singolo_hint").show().text("Inserisci una sola data e l'intervallo orario (ore da/ore a) obbligatorio.");
    } else if (tipo === "VISITA_MEDICA" || tipo === "VISITA_SPEC") {
        $("#block_singolo_ora_da").show();
        $("#block_singolo_ora_a").show();
        $("#singolo_hint").show().text("Inserisci una sola data. Le ore sono facoltative (se vuote = giornata intera).");
    } else {
        $("#block_singolo_ora_da").show();
        $("#block_singolo_ora_a").show();
        $("#singolo_hint").show().text("Inserisci una sola data. Le ore sono facoltative.");
    }
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
                righe.push({
                    unita: "ORE",
                    data_da: d,
                    data_a: d,
                    ora_da: $r.find(".r104_ora_da").val() || null,
                    ora_a: $r.find(".r104_ora_a").val() || null
                });
            }
        });
        return righe;
    }

    // singolo
    const data = $("#singolo_data").val();
    const ora_da = $("#singolo_ora_da").val();
    const ora_a = $("#singolo_ora_a").val();

    return [{
        unita: "ORE",
        data_da: data,
        data_a: data,
        ora_da: ora_da ? ora_da : null,
        ora_a: ora_a ? ora_a : null
    }];
}

function permessoHideError() {
    $("#permesso_alert").hide().text("");
}

function permessoShowError(msg) {
    $("#permesso_alert").text(msg).show();

    // porta l'utente in alto nel modale
    try {
        $("#permesso_modal .modal-body").animate({ scrollTop: 0 }, 200);
    } catch (e) { }
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

    $("#righe_ferie_container").empty();
    $("#righe_104_container").empty();

    resetBlocks();
    updateFeriePeriodoUI();

    $("#permesso_modal").modal("show");

    $("#permesso_tipo_id").prop("disabled", false);
    $("#permesso_note").prop("readonly", false);
    $("#btn_save_bozza").prop("disabled", false);
    $("#btn_invia").prop("disabled", false);
}

function permessoGetDetails(id) {
    permessoHideError();
    $.post("permessoReadDetails.php", { id: id }, function (r) {
        if (!r || r.ok !== true) {
            alert("Errore lettura dettagli.");
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
            const rr = (r.righe && r.righe.length) ? r.righe[0] : null;
            $("#singolo_data").val(rr ? (rr.data_da || "") : "");
            $("#singolo_ora_da").val(rr ? (rr.ora_da || "") : "");
            $("#singolo_ora_a").val(rr ? (rr.ora_a || "") : "");
        }

        const editable = ((p.stato || "") === "BOZZA");
        $("#permesso_tipo_id").prop("disabled", !editable);
        $("#permesso_note").prop("readonly", !editable);
        $("#ferie_sottotipo").prop("disabled", !editable);

        $("#btn_add_ferie").prop("disabled", !editable || (tipo_codice !== "FERIE"));
        $("#btn_add_104").prop("disabled", !editable || (tipo_codice !== "LEGGE_104"));

        $("#btn_save_bozza").prop("disabled", !editable);
        $("#btn_invia").prop("disabled", !editable);

        if (!editable) {
            $("#block_singolo :input").prop("disabled", true);
            $("#block_ferie_multi :input").prop("disabled", true);
            $("#block_104_multi :input").prop("disabled", true);
        } else {
            $("#block_singolo :input").prop("disabled", false);
            $("#block_ferie_multi :input").prop("disabled", false);
            $("#block_104_multi :input").prop("disabled", false);
        }

        $("#permesso_modal").modal("show");
    }, "json");
}

/* ===========================
 * NOTIFY + SAVE + DELETE
 * =========================== */
function notifyErr(msg) {
    $.notify(
        { icon: 'glyphicon glyphicon-warning-sign', title: '<strong>Permessi</strong><br>', message: msg },
        { placement: { from: "top", align: "center" }, delay: 5000, timer: 100, mouse_over: "pause", type: 'danger' }
    );
}

function permessoSave(azione) {
    const permesso_id = $("#permesso_id").val();
    const tipo_id = $("#permesso_tipo_id").val();
    const tipo_codice = getTipoCodiceSelezionato();
    const note = $("#permesso_note").val();
    const ferie_sottotipo = (tipo_codice === "FERIE") ? ($("#ferie_sottotipo").val() || "") : "";

    const righe = collectRighe();

    $.ajax({
        url: "permessoSave.php",
        method: "POST",
        dataType: "json",
        data: {
            permesso_id: permesso_id,
            permesso_tipo_id: tipo_id,
            note: note,
            ferie_sottotipo: ferie_sottotipo,
            azione: azione,
            righe_json: JSON.stringify(righe)
        },
        success: function (r) {
            console.log("SAVE response:", r);

            if (!r || r.ok !== true) {
                const msg = (r && r.error) ? r.error : "Errore salvataggio.";
                permessoShowError(msg);

                // opzionale: anche notify (se funziona)
                try { notifyErr(msg); } catch (e) { }

                return;
            }

            permessoHideError();
            $("#permesso_modal").modal("hide");
            permessiReadRecords();

            $.notify(
                { icon: 'glyphicon glyphicon-info-sign', title: '<strong>Permessi</strong><br>', message: (azione === "INVIA") ? 'Richiesta inviata.' : 'Bozza salvata.' },
                { placement: { from: "top", align: "center" }, delay: 2500, timer: 100, mouse_over: "pause", type: 'info' }
            );
        },
        error: function (xhr) {
            const msg = "Errore server: " + xhr.status;
            permessoShowError(msg);
            try { notifyErr(msg); } catch (e) { }
        }
    });
}

function permessoDelete(id) {
    if (!confirm("Eliminare la richiesta (solo se BOZZA)?")) return;

    $.ajax({
        url: "permessoDelete.php",
        method: "POST",
        dataType: "json",
        data: { id: id },
        success: function (r) {
            if (!r || r.ok !== true) {
                alert((r && r.error) ? r.error : "Errore cancellazione.");
                return;
            }
            permessiReadRecords();
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
    $("#righe_ferie_container").empty();
    $("#righe_104_container").empty();
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

$(document).on("click", "#btn_save_bozza", function () {
    permessoSave("BOZZA");
});
$(document).on("click", "#btn_invia", function () {
    permessoSave("INVIA");
});

$(document).ready(function () {
    permessiReadRecords();
});

$(document).on("change", "#ferie_sottotipo", function () {
    updateFeriePeriodoUI();
});

