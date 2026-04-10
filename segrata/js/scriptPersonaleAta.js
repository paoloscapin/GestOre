/**
 * This file is part of GestOre
 */

var soloAttivi = 1;

var orderBy = localStorage.getItem("personaleAta_orderBy") || "cognome";
var orderDir = localStorage.getItem("personaleAta_orderDir") || "ASC";

function personaleAtaExport() {
    var filters = personaleAtaGetFilters();

    var params = $.param({
        soloAttivi: soloAttivi,
        search: filters.search,
        id_ufficio: filters.id_ufficio,
        id_profilo: filters.id_profilo,
        tipo_contratto: filters.tipo_contratto,
        order_by: orderBy,
        order_dir: orderDir
    });

    window.location.href = "personaleAtaExport.php?" + params;
}

function personaleAtaImportaFile() {
    var fileInput = $("#import_file")[0];
    if (!fileInput.files || !fileInput.files.length) {
        alert("Seleziona un file CSV.");
        return;
    }

    var fd = new FormData();
    fd.append("import_file", fileInput.files[0]);
    fd.append("has_header", $("#import_has_header").is(":checked") ? "1" : "0");

    $("#import_result").html("Importazione in corso...");

    $.ajax({
        url: "personaleAtaImportProcess.php",
        type: "POST",
        data: fd,
        processData: false,
        contentType: false,
        dataType: "json",
        success: function (resp) {
            if (!resp || resp.ok === false) {
                $("#import_result").html('<div class="text-danger">' + ((resp && resp.message) ? resp.message : 'Errore durante l\'importazione.') + '</div>');
                return;
            }

            var html = '<div class="text-success"><strong>Import completato.</strong></div>';
            html += '<div>Inseriti: <strong>' + (resp.inserted || 0) + '</strong></div>';
            html += '<div>Aggiornati: <strong>' + (resp.updated || 0) + '</strong></div>';
            html += '<div>Assegnazioni ufficio nuove/modificate: <strong>' + (resp.office_changes || 0) + '</strong></div>';

            if (resp.errors && resp.errors.length) {
                html += '<hr><div class="text-danger"><strong>Righe con errori:</strong></div><ul style="max-height:180px; overflow:auto;">';
                for (var i = 0; i < resp.errors.length; i++) {
                    html += '<li>' + personaleAtaEscapeHtml(resp.errors[i]) + '</li>';
                }
                html += '</ul>';
            }

            $("#import_result").html(html);
            personaleAtaReadRecords();
        },
        error: function (xhr) {
            var msg = "Errore durante l'importazione.";
            try {
                var r = JSON.parse(xhr.responseText);
                if (r && r.message) msg = r.message;
            } catch (e) {}
            $("#import_result").html('<div class="text-danger">' + personaleAtaEscapeHtml(msg) + '</div>');
        }
    });
}

function personaleAtaGetCodiceProfilo(idProfilo) {
    if (!window.PERSONALE_ATA_PROFILI || !PERSONALE_ATA_PROFILI.length) return "";

    var idStr = String(idProfilo || "");
    for (var i = 0; i < PERSONALE_ATA_PROFILI.length; i++) {
        var p = PERSONALE_ATA_PROFILI[i];
        if (String(p.id) === idStr) {
            return p.codice || "";
        }
    }
    return "";
}

function personaleAtaSyncRuoloDaProfilo() {
    var codice = personaleAtaGetCodiceProfilo($("#id_profilo").val());
    $("#ruolo").val(codice);
}

function personaleAtaSyncRuoloDaProfiloUpdate() {
    var codice = personaleAtaGetCodiceProfilo($("#update_id_profilo").val());
    $("#update_ruolo").val(codice);
}

function personaleAtaEscapeHtml(value) {
    value = (value == null ? '' : '' + value);
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function personaleAtaIsoToIt(iso) {
    if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return iso || "";
    var parts = iso.split("-");
    return parts[2] + "/" + parts[1] + "/" + parts[0];
}

function personaleAtaSetStats(stats) {
    stats = stats || {};
    $("#stats_totale").text(stats.totale || 0);
    $("#stats_attivi").text(stats.attivi || 0);
    $("#stats_con_ufficio").text(stats.con_ufficio || 0);
    $("#stats_con_profilo").text(stats.con_profilo || 0);
}

function personaleAtaGetFilters() {
    return {
        search: $("#filter_text").val() || "",
        id_ufficio: $("#filter_ufficio").val() || "",
        id_profilo: $("#filter_profilo").val() || "",
        tipo_contratto: $("#filter_tipo_contratto").val() || ""
    };
}

$('#testCheckBox').change(function () {
    soloAttivi = this.checked ? 1 : 0;
    personaleAtaReadRecords();
});

function ordina(campo) {
    if (orderBy === campo) {
        orderDir = (orderDir === "ASC") ? "DESC" : "ASC";
    } else {
        orderBy = campo;
        orderDir = "ASC";
    }

    personaleAtaReadRecords();
}

function ordina(campo) {
    if (orderBy === campo) {
        orderDir = (orderDir === "ASC") ? "DESC" : "ASC";
    } else {
        orderBy = campo;
        orderDir = "ASC";
    }

    localStorage.setItem("personaleAta_orderBy", orderBy);
    localStorage.setItem("personaleAta_orderDir", orderDir);

    personaleAtaReadRecords();
}

function personaleAtaReadRecords() {
    var filters = personaleAtaGetFilters();

    $.get("personaleAtaReadRecords.php", {
        soloAttivi: soloAttivi,
        search: filters.search,
        id_ufficio: filters.id_ufficio,
        id_profilo: filters.id_profilo,
        tipo_contratto: filters.tipo_contratto,
        order_by: orderBy,
        order_dir: orderDir
    }, function (data) {
        $(".records_content").html(data);

        if (window.personaleAtaStats) {
            personaleAtaSetStats(window.personaleAtaStats);
        } else {
            personaleAtaSetStats({ totale: 0, attivi: 0, con_ufficio: 0, con_profilo: 0 });
        }
    });
}

function personaleAtaResetFilters() {
    $("#filter_text").val("");
    $("#filter_ufficio").val("");
    $("#filter_profilo").val("");
    $("#filter_tipo_contratto").val("");
    personaleAtaReadRecords();
}

function personaleAtaClearAddModal() {
    $("#nome,#cognome,#email,#username,#matricola,#tipo_contratto,#codice_fiscale,#ruolo").val("");
    $("#id_ufficio").val("");
    $("#id_profilo").val("");
    $("#data_inizio_assegnazione").val(new Date().toISOString().slice(0, 10));
    $("#attivo").prop("checked", true).change();
}

function personaleAtaAddRecord() {
    $.post("personaleAtaAddRecord.php", {
        nome: $("#nome").val(),
        cognome: $("#cognome").val(),
        email: $("#email").val(),
        username: $("#username").val(),
        matricola: $("#matricola").val(),
        tipo_contratto: $("#tipo_contratto").val(),
        codice_fiscale: $("#codice_fiscale").val(),
        id_profilo: $("#id_profilo").val(),
        ruolo: $("#ruolo").val(),
        attivo: $("#attivo").is(':checked') ? 1 : 0,
        id_ufficio: $("#id_ufficio").val(),
        data_inizio_assegnazione: $("#data_inizio_assegnazione").val()
    }, function (resp) {
        if (resp && resp.ok === false) {
            alert(resp.message || "Errore durante il salvataggio.");
            return;
        }

        $("#add_new_record_modal").modal("hide");
        personaleAtaReadRecords();
        personaleAtaClearAddModal();
    }, "json").fail(function (xhr) {
        var msg = "Errore durante il salvataggio.";
        try {
            var r = JSON.parse(xhr.responseText);
            if (r && r.message) msg = r.message;
        } catch (e) { }
        alert(msg);
    });
}

function personaleAtaRenderStorico(storico) {
    if (!storico || !storico.length) {
        return '<div class="empty-box">Nessuno storico disponibile.</div>';
    }

    var html = '';
    for (var i = 0; i < storico.length; i++) {
        var item = storico[i] || {};
        var ufficio = item.ufficio_nome ? personaleAtaEscapeHtml(item.ufficio_nome) : '<span class="text-muted">Nessun ufficio</span>';
        var dal = item.data_inizio ? personaleAtaIsoToIt(item.data_inizio) : '-';
        var al = item.data_fine ? personaleAtaIsoToIt(item.data_fine) : 'in corso';

        html += ''
            + '<div class="history-item">'
            + '  <div class="history-title">' + ufficio + '</div>'
            + '  <div class="history-dates">Dal ' + dal + ' al ' + al + '</div>'
            + '</div>';
    }

    return html;
}

function personaleAtaGetDetails(id) {
    $("#hidden_id").val(id);

    $.post("personaleAtaReadDetails.php", { id: id }, function (resp) {
        if (!resp || resp.ok === false) {
            alert((resp && resp.message) ? resp.message : "Errore nel caricamento dei dettagli.");
            return;
        }

        var p = resp.personale || {};
        var assegn = resp.assegnazione_corrente || null;
        var storico = resp.storico || [];

        $("#update_nome").val(p.nome || "");
        $("#update_cognome").val(p.cognome || "");
        $("#update_email").val(p.email || "");
        $("#update_username").val(p.username || "");
        $("#update_matricola").val(p.matricola || "");
        $("#update_tipo_contratto").val(p.tipo_contratto || "");
        $("#update_codice_fiscale").val(p.codice_fiscale || "");

        var profiloVal = "";
        if (p.id_profilo !== null && p.id_profilo !== undefined && p.id_profilo !== "") {
            profiloVal = String(p.id_profilo);
        }
        $("#update_id_profilo").val(profiloVal);
        personaleAtaSyncRuoloDaProfiloUpdate();

        $("#current_username_originale").val(p.username || "");

        $('#update_attivo').bootstrapToggle((parseInt(p.attivo, 10) === 1) ? 'on' : 'off');

        $("#current_assignment_id").val(assegn ? (assegn.id || "") : "");
        $("#current_assignment_ufficio_id").val(assegn ? (assegn.id_ufficio || "") : "");
        $("#current_assignment_data_inizio").val(assegn ? (assegn.data_inizio || "") : "");
        $("#update_id_ufficio").val(assegn && assegn.id_ufficio ? assegn.id_ufficio : "");
        $("#update_data_inizio_assegnazione").val(new Date().toISOString().slice(0, 10));

        $("#storico_assegnazioni").html(personaleAtaRenderStorico(storico));
        $("#update_modal").modal("show");
    }, "json").fail(function () {
        alert("Errore nel caricamento dei dettagli.");
    });
}

function personaleAtaUpdateDetails() {
    var currentUfficio = $("#current_assignment_ufficio_id").val() || "";
    var newUfficio = $("#update_id_ufficio").val() || "";

    if (currentUfficio !== newUfficio) {
        var conferma = confirm("Stai per cambiare l'ufficio assegnato a questo dipendente. Vuoi continuare?");
        if (!conferma) return;
    }

    $.post("personaleAtaUpdateDetails.php", {
        id: $("#hidden_id").val(),
        nome: $("#update_nome").val(),
        cognome: $("#update_cognome").val(),
        email: $("#update_email").val(),
        username: $("#update_username").val(),
        matricola: $("#update_matricola").val(),
        tipo_contratto: $("#update_tipo_contratto").val(),
        codice_fiscale: $("#update_codice_fiscale").val(),
        id_profilo: $("#update_id_profilo").val(),
        ruolo: $("#update_ruolo").val(),
        attivo: $("#update_attivo").is(':checked') ? 1 : 0,
        username_originale: $("#current_username_originale").val(),
        current_assignment_id: $("#current_assignment_id").val(),
        current_assignment_ufficio_id: $("#current_assignment_ufficio_id").val(),
        current_assignment_data_inizio: $("#current_assignment_data_inizio").val(),
        id_ufficio: $("#update_id_ufficio").val(),
        data_inizio_assegnazione: $("#update_data_inizio_assegnazione").val()
    }, function (resp) {
        if (resp && resp.ok === false) {
            alert(resp.message || "Errore durante il salvataggio.");
            return;
        }

        $("#update_modal").modal("hide");
        personaleAtaReadRecords();
    }, "json").fail(function (xhr) {
        var msg = "Errore durante il salvataggio.";
        try {
            var r = JSON.parse(xhr.responseText);
            if (r && r.message) msg = r.message;
        } catch (e) { }
        alert(msg);
    });
}

function personaleAtaDelete(id, cognome, nome) {
    var conf = confirm("Sei sicuro di volere cancellare il personale ATA " + cognome + " " + nome + " ?");
    if (!conf) return;

    $.post("../common/deleteRecord.php", {
        id: id,
        table: 'personale_ata',
        name: "personale ATA " + cognome + " " + nome
    }, function () {
        personaleAtaReadRecords();
    });
}

$(document).ready(function () {
    $("#filter_text").on("input", function () {
        personaleAtaReadRecords();
    });

    $("#filter_ufficio, #filter_profilo, #filter_tipo_contratto").on("change", function () {
        personaleAtaReadRecords();
    });

    $("#id_profilo").on("change", function () {
        personaleAtaSyncRuoloDaProfilo();
    });

    $("#update_id_profilo").on("change", function () {
        personaleAtaSyncRuoloDaProfiloUpdate();
    });

    personaleAtaReadRecords();
});