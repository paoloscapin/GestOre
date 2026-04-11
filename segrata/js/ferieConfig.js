function fcSafeRefresh(sel) {
    if ($.fn.selectpicker && $(sel).length) {
        $(sel).selectpicker("refresh");
    }
}

function fcFmtDateIt(iso) {
    if (!iso) return "";
    const p = iso.split("-");
    if (p.length !== 3) return iso;
    return `${p[2]}/${p[1]}/${p[0]}`;
}

function fcNotify(msg, type) {
    $.notify(
        { message: msg },
        {
            type: type || "info",
            placement: { from: "top", align: "center" },
            delay: 3500
        }
    );
}

function fcBadgeValido(v) {
    return v ? '<span class="cfg-badge ok">Attiva</span>' : '<span class="cfg-badge off">Disattiva</span>';
}

function fcBadgeTipo(t) {
    const tx = (t || "").toUpperCase();
    if (tx === "ESCLUDI" || tx === "ESCLUSO") {
        return '<span class="cfg-badge exc">' + tx + '</span>';
    }
    return '<span class="cfg-badge info">' + tx + '</span>';
}

function fcRenderFinestre(rows) {
    $("#cfgFinestreMeta").text((rows || []).length + " finestra/e configurata/e");

    if (!rows || !rows.length) {
        $("#cfgFinestreWrap").html('<div class="cfg-empty">Nessuna finestra ferie configurata.</div>');
        return;
    }

    let html = '<div class="table-responsive"><table class="cfg-table">';
    html += '<thead><tr>';
    html += '<th>ID</th>';
    html += '<th>Codice</th>';
    html += '<th>Data inizio</th>';
    html += '<th>Data fine</th>';
    html += '<th>Stato</th>';
    html += '<th>Azioni</th>';
    html += '</tr></thead><tbody>';

    rows.forEach(r => {
        const json = encodeURIComponent(JSON.stringify(r));
        html += '<tr>';
        html += '<td>' + (r.id || "") + '</td>';
        html += '<td><strong>' + (r.codice || "") + '</strong></td>';
        html += '<td>' + (r.data_inizio_fmt || fcFmtDateIt(r.data_inizio || "")) + '</td>';
        html += '<td>' + (r.data_fine_fmt || fcFmtDateIt(r.data_fine || "")) + '</td>';
        html += '<td>' + fcBadgeValido(parseInt(r.valido || 0, 10) === 1) + '</td>';
        html += '<td>';
        html += '<button class="btn btn-xs btn-primary btn-edit-finestra" data-row="' + json + '"><span class="glyphicon glyphicon-pencil"></span></button> ';
        html += '<button class="btn btn-xs btn-danger btn-del-finestra" data-id="' + (r.id || 0) + '"><span class="glyphicon glyphicon-trash"></span></button>';
        html += '</td>';
        html += '</tr>';
    });

    html += '</tbody></table></div>';
    $("#cfgFinestreWrap").html(html);
}

function fcRenderGiorni(rows) {
    $("#cfgGiorniMeta").text((rows || []).length + " giorno/i speciale/i configurato/i");

    if (!rows || !rows.length) {
        $("#cfgGiorniWrap").html('<div class="cfg-empty">Nessun giorno speciale configurato.</div>');
        return;
    }

    let html = '<div class="table-responsive"><table class="cfg-table">';
    html += '<thead><tr>';
    html += '<th>ID</th>';
    html += '<th>Sottotipo</th>';
    html += '<th>Data</th>';
    html += '<th>Tipo</th>';
    html += '<th>Descrizione</th>';
    html += '<th>Stato</th>';
    html += '<th>Azioni</th>';
    html += '</tr></thead><tbody>';

    rows.forEach(r => {
        const json = encodeURIComponent(JSON.stringify(r));
        html += '<tr>';
        html += '<td>' + (r.id || "") + '</td>';
        html += '<td><strong>' + (r.sottotipo || "") + '</strong></td>';
        html += '<td>' + (r.data_fmt || fcFmtDateIt(r.data_giorno || "")) + '</td>';
        html += '<td>' + fcBadgeTipo(r.tipo || "") + '</td>';
        html += '<td class="wrap">' + (r.descrizione || "") + '</td>';
        html += '<td>' + fcBadgeValido(parseInt(r.valido || 0, 10) === 1) + '</td>';
        html += '<td>';
        html += '<button class="btn btn-xs btn-warning btn-edit-giorno" data-row="' + json + '"><span class="glyphicon glyphicon-pencil"></span></button> ';
        html += '<button class="btn btn-xs btn-danger btn-del-giorno" data-id="' + (r.id || 0) + '"><span class="glyphicon glyphicon-trash"></span></button>';
        html += '</td>';
        html += '</tr>';
    });

    html += '</tbody></table></div>';
    $("#cfgGiorniWrap").html(html);
}

function ferieConfigLoad() {
    $.ajax({
        url: "ferieConfigRead.php",
        method: "GET",
        dataType: "json",
        success: function (r) {
            if (!r || r.ok !== true) {
                fcNotify((r && r.error) ? r.error : "Errore lettura configurazione", "danger");
                return;
            }

            fcRenderFinestre(r.finestre || []);
            fcRenderGiorni(r.giorni_speciali || []);
        },
        error: function (xhr) {
            console.error("ferieConfigRead ERROR", xhr.responseText);
            fcNotify("Errore lettura configurazione ferie", "danger");
        }
    });
}

function openNuovaFinestra() {
    $("#ff_id").val("0");
    $("#ff_codice").val("ESTIVE");
    $("#ff_data_inizio").val("");
    $("#ff_data_fine").val("");
    $("#ff_valido").prop("checked", true);
    fcSafeRefresh("#ff_codice");
    $("#modalFinestraLabel").text("Nuova finestra ferie");
    $("#modalFinestra").modal("show");
}

function openEditFinestra(row) {
    $("#ff_id").val(row.id || 0);
    $("#ff_codice").val(row.codice || "ESTIVE");
    $("#ff_data_inizio").val(row.data_inizio || "");
    $("#ff_data_fine").val(row.data_fine || "");
    $("#ff_valido").prop("checked", parseInt(row.valido || 0, 10) === 1);
    fcSafeRefresh("#ff_codice");
    $("#modalFinestraLabel").text("Modifica finestra ferie");
    $("#modalFinestra").modal("show");
}

function openNuovoGiorno() {
    $("#fg_id").val("0");
    $("#fg_sottotipo").val("ESTIVE");
    $("#fg_data_giorno").val("");
    $("#fg_tipo").val("ESCLUDI");
    $("#fg_descrizione").val("");
    $("#fg_valido").prop("checked", true);
    fcSafeRefresh("#fg_sottotipo");
    fcSafeRefresh("#fg_tipo");
    $("#modalGiornoLabel").text("Nuovo giorno speciale");
    $("#modalGiorno").modal("show");
}

function openEditGiorno(row) {
    $("#fg_id").val(row.id || 0);
    $("#fg_sottotipo").val(row.sottotipo || "ESTIVE");
    $("#fg_data_giorno").val(row.data_giorno || "");
    $("#fg_tipo").val(row.tipo || "ESCLUDI");
    $("#fg_descrizione").val(row.descrizione || "");
    $("#fg_valido").prop("checked", parseInt(row.valido || 0, 10) === 1);
    fcSafeRefresh("#fg_sottotipo");
    fcSafeRefresh("#fg_tipo");
    $("#modalGiornoLabel").text("Modifica giorno speciale");
    $("#modalGiorno").modal("show");
}

function saveFinestra() {
    $.ajax({
        url: "ferieFinestraSave.php",
        method: "POST",
        dataType: "json",
        data: {
            id: $("#ff_id").val(),
            codice: $("#ff_codice").val(),
            data_inizio: $("#ff_data_inizio").val(),
            data_fine: $("#ff_data_fine").val(),
            valido: $("#ff_valido").is(":checked") ? 1 : 0
        },
        success: function (r) {
            if (!r || r.ok !== true) {
                fcNotify((r && r.error) ? r.error : "Errore salvataggio finestra", "danger");
                return;
            }
            $("#modalFinestra").modal("hide");
            fcNotify("Finestra ferie salvata", "success");
            ferieConfigLoad();
        },
        error: function (xhr) {
            let msg = "Errore salvataggio finestra";
            try {
                const r = JSON.parse(xhr.responseText);
                if (r && r.error) msg = r.error;
            } catch (e) { }
            fcNotify(msg, "danger");
        }
    });
}

function saveGiorno() {
    $.ajax({
        url: "ferieGiornoSpecialeSave.php",
        method: "POST",
        dataType: "json",
        data: {
            id: $("#fg_id").val(),
            sottotipo: $("#fg_sottotipo").val(),
            data_giorno: $("#fg_data_giorno").val(),
            tipo: "ESCLUDI",
            descrizione: $("#fg_descrizione").val(),
            valido: $("#fg_valido").is(":checked") ? 1 : 0
        },
        success: function (r) {
            if (!r || r.ok !== true) {
                fcNotify((r && r.error) ? r.error : "Errore salvataggio giorno speciale", "danger");
                return;
            }
            $("#modalGiorno").modal("hide");
            fcNotify("Giorno speciale salvato", "success");
            ferieConfigLoad();
        },
        error: function (xhr) {
            let msg = "Errore salvataggio giorno speciale";
            try {
                const r = JSON.parse(xhr.responseText);
                if (r && r.error) msg = r.error;
            } catch (e) { }
            fcNotify(msg, "danger");
        }
    });
}

function deleteFinestra(id) {
    if (!id) return;
    if (!confirm("Eliminare questa finestra ferie?")) return;

    $.ajax({
        url: "ferieFinestraDelete.php",
        method: "POST",
        dataType: "json",
        data: { id: id },
        success: function (r) {
            if (!r || r.ok !== true) {
                fcNotify((r && r.error) ? r.error : "Errore eliminazione finestra", "danger");
                return;
            }
            fcNotify("Finestra ferie eliminata", "success");
            ferieConfigLoad();
        },
        error: function (xhr) {
            let msg = "Errore eliminazione finestra";
            try {
                const r = JSON.parse(xhr.responseText);
                if (r && r.error) msg = r.error;
            } catch (e) { }
            fcNotify(msg, "danger");
        }
    });
}

function deleteGiorno(id) {
    if (!id) return;
    if (!confirm("Eliminare questo giorno speciale?")) return;

    $.ajax({
        url: "ferieGiornoSpecialeDelete.php",
        method: "POST",
        dataType: "json",
        data: { id: id },
        success: function (r) {
            if (!r || r.ok !== true) {
                fcNotify((r && r.error) ? r.error : "Errore eliminazione giorno speciale", "danger");
                return;
            }
            fcNotify("Giorno speciale eliminato", "success");
            ferieConfigLoad();
        },
        error: function (xhr) {
            let msg = "Errore eliminazione giorno speciale";
            try {
                const r = JSON.parse(xhr.responseText);
                if (r && r.error) msg = r.error;
            } catch (e) { }
            fcNotify(msg, "danger");
        }
    });
}

$(document).ready(function () {
    $("#btnCfgRefresh").on("click", ferieConfigLoad);

    $("#btnNuovaFinestra, #btnNuovaFinestraTop").on("click", openNuovaFinestra);
    $("#btnNuovoGiorno, #btnNuovoGiornoTop").on("click", openNuovoGiorno);

    $("#btnSaveFinestra").on("click", saveFinestra);
    $("#btnSaveGiorno").on("click", saveGiorno);

    $(document).on("click", ".btn-edit-finestra", function () {
        let row = null;
        try {
            row = JSON.parse(decodeURIComponent($(this).attr("data-row") || ""));
        } catch (e) { }
        if (row) openEditFinestra(row);
    });

    $(document).on("click", ".btn-edit-giorno", function () {
        let row = null;
        try {
            row = JSON.parse(decodeURIComponent($(this).attr("data-row") || ""));
        } catch (e) { }
        if (row) openEditGiorno(row);
    });

    $(document).on("click", ".btn-del-finestra", function () {
        deleteFinestra(parseInt($(this).data("id") || 0, 10));
    });

    $(document).on("click", ".btn-del-giorno", function () {
        deleteGiorno(parseInt($(this).data("id") || 0, 10));
    });

    fcSafeRefresh("#ff_codice");
    fcSafeRefresh("#fg_sottotipo");
    fcSafeRefresh("#fg_tipo");

    ferieConfigLoad();
});