/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var soloNuovi = 1;
var soloPrenotati = 0;
var categoria_filtro_id = 1; // sportello didattico
var docente_filtro_id = 0;
var studente_filtro_id = 0;
var materia_filtro_id = 0;
var classe_filtro_id = 0;
var bozza_filtro_id = 0;
var selections = [];
var data_pickr = null;

window.toastAfterModalClose = null;

function ensureSelectHasOption($sel, value, label) {
    value = (value || "").trim();
    if (!value) return;
    if ($sel.find("option[value='" + value.replace(/'/g, "\\'") + "']").length === 0) {
        $sel.append($("<option>", { value: value, text: label || value }));
    }
}

function setOraSelect(oraValue) {
    var ora = (oraValue || "").trim();
    if (!ora) return;

    var $ora = $("#ora");
    // se l'ora non è presente nella lista (es: 14:00), la aggiungo
    ensureSelectHasOption($ora, ora, ora + " (attuale)");
    $ora.selectpicker('val', ora).selectpicker('refresh');
}
function verificaAulaCorrenteDidattica(opts) {
    opts = opts || {};
    var includiAulaCorrente = !!opts.includiAulaCorrente;
    var tipoAula = opts.tipoAula || "TUTTE";
    var includeAula = (opts.includeAula || "").trim();

    // Data/ora/durata correnti UI
    var dataDb = "";
    try {
        dataDb = getDbDateFromPickrId("#data"); // YYYY-MM-DD
    } catch (e) {
        dataDb = ($("#data").val() || "").trim();
    }

    var ora = ($("#ora").val() || "").trim();
    var durataOre = parseInt($("#numero_ore").val(), 10) || 1;
    if (durataOre < 1) durataOre = 1;
    if (durataOre > 2) durataOre = 2;

    // Se devo includere aula corrente e non è stata passata, la prendo dal select
    if (includiAulaCorrente && !includeAula) {
        includeAula = ($("#luogo").val() || "").trim();
    }

    console.log("[verificaAulaCorrenteDidattica] START", {
        includiAulaCorrente: includiAulaCorrente,
        tipoAula: tipoAula,
        includeAula: includeAula,
        dataDb: dataDb,
        ora: ora,
        durataOre: durataOre
    });

    if (!dataDb || !ora) {
        console.warn("[verificaAulaCorrenteDidattica] SKIP missing data/ora", { dataDb, ora });
        return;
    }

    // (opzionale) disabilita select mentre carica
    try { $("#luogo").prop("disabled", true).selectpicker("refresh"); } catch (e) { }

    $.post("../common/checkAuleLibere.php", {
        tipo: tipoAula,
        dataGiorno: dataDb,
        ora: ora,
        durataOre: durataOre,
        includeAula: includeAula
    }, function (resp) {

        console.log("[verificaAulaCorrenteDidattica] RESPONSE", resp);

        if (!resp || resp.status !== "ok") {
            console.warn("[verificaAulaCorrenteDidattica] BAD RESPONSE", resp);
            return;
        }

        var rows = Array.isArray(resp.data) ? resp.data : [];
        var $sel = $("#luogo");

        // salvo selezione attuale per ripristino
        var selectedBefore = ($sel.val() || "").trim();

        // rebuild opzioni
        $sel.empty();
        $sel.append('<option value=""></option>');

        // Se includeAula è richiesto, assicurati che sia presente anche se non tornasse dal DB
        // (es. aula non prenotabile / non presente in tabella aula)
        if (includeAula) {
            var found = rows.some(function (r) {
                return (String(r.nroAula || "").trim() === includeAula);
            });
            if (!found) {
                $sel.append('<option value="' + includeAula + '">' + includeAula + ' (attuale)</option>');
            }
        }

        rows.forEach(function (r) {
            var nro = (r.nroAula != null) ? String(r.nroAula).trim() : "";
            if (!nro) return;

            var label = nro;
            if (parseInt(r.is_current, 10) === 1) label = nro + " (attuale)";

            $sel.append('<option value="' + nro + '">' + label + '</option>');
        });

        // Ripristino selezione:
        // 1) se avevo scelto qualcosa a mano, mantienilo
        // 2) altrimenti se includeAula è presente, selezionala
        var toSelect = selectedBefore || includeAula || "";
        if (toSelect) $sel.val(toSelect);

        $sel.selectpicker("refresh");

        console.log("[verificaAulaCorrenteDidattica] DONE", {
            selected: $sel.val(),
            options: $sel.find("option").length
        });

    }, "json")
        .fail(function (xhr, st, err) {
            console.error("[verificaAulaCorrenteDidattica] FAIL", st, err, xhr && xhr.responseText);
        })
        .always(function () {
            try { $("#luogo").prop("disabled", false).selectpicker("refresh"); } catch (e) { }
        });
}

function setDbDateToPickr(pickr, data_str) {
    var data = Date.parseExact(data_str, 'yyyy-MM-dd');
    pickr.setDate(data);
}

function getDbDateFromPickrId(pickrId) {
    var data_str = $(pickrId).val();
    var data_date = Date.parseExact(data_str, 'd/M/yyyy');
    return data_date.toString('yyyy-MM-dd');
}

$('#soloNuoviCheckBox').change(function () {
    // this si riferisce al checkbox
    if (this.checked) {
        soloNuovi = 1;
    } else {
        soloNuovi = 0;
    }
    sportelloReadRecords();
});

$('#bozzaCheckBox').change(function () {
    // this si riferisce al checkbox
    if (this.checked) {
        bozza_filtro_id = 1;
    } else {
        bozza_filtro_id = 0;
    }
    sportelloReadRecords();
});

$('#soloPrenotatiCheckBox').change(function () {
    // this si riferisce al checkbox
    if (this.checked) {
        soloPrenotati = 1;
    } else {
        soloPrenotati = 0;
    }
    sportelloReadRecords();
});

function sportelloReadRecords() {
    $.get("sportelloReadRecords.php?ancheCancellati=true&soloNuovi=" + soloNuovi + "&soloPrenotati=" + soloPrenotati + "&categoria_filtro_id=" + categoria_filtro_id + "&docente_filtro_id=" + docente_filtro_id + "&classe_filtro_id=" + classe_filtro_id + "&materia_filtro_id=" + materia_filtro_id + "&bozza_filtro_id=" + bozza_filtro_id, {}, function (data, status) {
        $(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
    });
}

function iscriviStudente(studente_id, selezione) {
    $.post("sportelloIscriviStudente.php", {
        id_studente: studente_id,
        lista_sportelli: selezione
    }, function (data, status) {
        $('#riga_iscrizioni_sportelli').hide();
        $('#result_text').html(data);
        $('#file_select_students').change(function (e) { });
        $("#sportelli_selezionati").html("");
        selections.length = 0;
        setTimeout(function () { $('#result_text').html(""); }, 5000);
        sportelloReadRecords();
    });
}

function sportelloSelect(id) {
    if ($("#selecticon" + id).hasClass("glyphicon-remove")) {
        $("#selecticon" + id).removeClass("glyphicon-remove");
        $("#selecticon" + id).addClass("glyphicon-ok");
        $("#selectbutton" + id).removeClass("btn-info");
        $("#selectbutton" + id).addClass("btn-primary");
        $("#selectbutton" + id).trigger("blur");
        $("#selecticon" + id).trigger("blur");
        selections.push(id);
    }
    else {
        $("#selecticon" + id).addClass("glyphicon-remove");
        $("#selecticon" + id).removeClass("glyphicon-ok");
        $("#selectbutton" + id).removeClass("btn-primary");
        $("#selectbutton" + id).addClass("btn-info");
        $("#selectbutton" + id).trigger("blur");
        $("#selecticon" + id).trigger("blur");
        selections.splice(selections.indexOf(id), 1);
    }
    if (selections.length > 0) {
        $("#numero_studenti").html('NUMERO ATTIVITA SELEZIONATE: ' + selections.length);
        $('#file_select_students').change(function (e) {
            importStudents(e.target.files[0], selections);
        });
        $('#riga_iscrizioni_sportelli').show();

    }
    else {
        $("#numero_studenti").html("");
        $('#file_select_students').change(function (e) { });
        $('#select_studente').on("click", function (e) { });
        $('#riga_iscrizioni_sportelli').hide();
    }
}

function showSpinner(msg) {
    msg = msg || "Operazione in corso...";
    if ($("#globalSpinner").length) return;

    $("body").append(`
        <div id="globalSpinner" style="
            position:fixed; inset:0; z-index:2000;
            background:rgba(0,0,0,.4);
            display:flex; align-items:center; justify-content:center;
        ">
            <div style="
                background:#fff; padding:20px 30px;
                border-radius:12px; font-size:15px;
                box-shadow:0 10px 40px rgba(0,0,0,.3);
            ">
                <div class="spinner-border text-primary" role="status"></div>
                <div style="margin-top:10px">${msg}</div>
            </div>
        </div>
    `);
}

function hideSpinner() {
    $("#globalSpinner").remove();
}


function sportelloDelete(id, materia) {

    Swal.fire({
        title: 'Eliminare lo sportello?',
        html: `
            <b>${materia}</b><br><br>
            L'operazione è <b>definitiva</b> e rimuove anche
            eventuali prenotazioni su <b>MBApp</b>.
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sì, elimina',
        cancelButtonText: 'Annulla',
        confirmButtonColor: '#d33'
    }).then((result) => {

        if (!result.isConfirmed) return;

        showSpinner("Eliminazione sportello in corso…");

        $.post(
            "sportelloDelete.php",
            { id: id },
            function (res) {
                hideSpinner();

                if (!res || res.ok !== true) {
                    Swal.fire(
                        'Errore',
                        res?.error ?? 'Errore durante la cancellazione',
                        'error'
                    );
                    return;
                }

                Swal.fire(
                    'Eliminato',
                    'Lo sportello è stato eliminato correttamente',
                    'success'
                );

                sportelloReadRecords();
            },
            "json"
        ).fail(function (xhr) {
            hideSpinner();
            Swal.fire(
                'Errore',
                'Errore di comunicazione con il server',
                'error'
            );
            console.error(xhr.responseText);
        });
    });
}

function showSuccessMessage(text, timeout = 3000) {

    let $container = $('#alert-container');

    // se non esiste, lo creo e lo appendo al body
    if ($container.length === 0) {
        $container = $('<div id="alert-container"></div>').appendTo('body');
    }

    // posizione: in basso e centrato
    $container.css({
        position: 'fixed',
        bottom: '40px',
        left: '50%',
        transform: 'translateX(-50%)',
        zIndex: 99999,
        textAlign: 'center'
    });

    const $msg = $('<div></div>').text(text).css({
        backgroundColor: '#198754',      // verde Bootstrap "success"
        color: '#ffffff',
        padding: '14px 22px',
        borderRadius: '10px',
        fontSize: '16px',
        fontWeight: '500',
        boxShadow: '0 8px 24px rgba(0,0,0,0.25)',
        display: 'inline-block',
        minWidth: '320px'
    });

    $container.append($msg);
    $msg.hide().fadeIn(200);

    setTimeout(function () {
        $msg.fadeOut(400, function () { $(this).remove(); });
    }, timeout);
}

function sportelloRimettiBozza(id, materia) {
    id = parseInt(id, 10) || 0;
    materia = materia || "";

    if (id <= 0) {
        alert("ID non valido.");
        return;
    }

    var doIt = function () {
        $.post("sportelloRimettiBozza.php", { id: id }, function (resp) {
            console.log("[sportelloRimettiBozza] RESPONSE", resp);

            if (!resp || !resp.ok) {
                var msg = (resp && (resp.error || resp.msg)) ? (resp.error || resp.msg) : "Errore: rimessa in bozza non riuscita.";
                if (window.Swal) Swal.fire({ icon: "error", title: "Errore", text: msg });
                else alert(msg);
                return;
            }

            sportelloReadRecords();

            var mb = resp.mbapp || null;
            var text = "Sportello rimesso in bozza.";
            if (mb && typeof mb === "object") {
                text += " " + (mb.msg || "");
            }

            if (window.Swal) {
                Swal.fire({
                    icon: "success",
                    title: "OK",
                    text: text,
                    timer: 1600,
                    showConfirmButton: false
                });
            } else {
                alert(text);
            }

        }, "json").fail(function (xhr, st, err) {
            console.error("[sportelloRimettiBozza] FAIL", st, err, xhr && xhr.responseText);
            if (window.Swal) Swal.fire({ icon: "error", title: "Errore", text: "Errore di rete o server." });
            else alert("Errore di rete o server.");
        });
    };

    if (window.Swal) {
        Swal.fire({
            icon: "warning",
            title: "Rimettere in bozza?",
            text: "Questo azzera docente e aula. Se esiste prenotazione MBApp verrà cancellata.",
            showCancelButton: true,
            confirmButtonText: "Sì, rimetti in bozza",
            cancelButtonText: "Annulla"
        }).then(function (r) {
            if (r && r.isConfirmed) doIt();
        });
    } else {
        var conf = confirm("Rimettere in bozza lo sportello di " + materia + "?\nAzzera docente e aula. Se esiste prenotazione MBApp verrà cancellata.");
        if (conf) doIt();
    }
}

function getDateYmdFromPickerOrInput() {
    // 1) prova da flatpickr
    try {
        if (window.data_pickr && data_pickr.selectedDates && data_pickr.selectedDates.length > 0) {
            var d = data_pickr.selectedDates[0]; // Date object
            var yyyy = d.getFullYear();
            var mm = String(d.getMonth() + 1).padStart(2, "0");
            var dd = String(d.getDate()).padStart(2, "0");
            return yyyy + "-" + mm + "-" + dd;
        }
    } catch (e) { /* ignore */ }

    // 2) fallback: input #data (potrebbe essere d/m/Y o Y-m-d)
    var raw = ($("#data").val() || "").trim();
    if (!raw) return "";

    // già ISO
    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return raw;

    // d/m/Y o d-m-Y
    var m = raw.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/);
    if (m) {
        var dd2 = String(parseInt(m[1], 10)).padStart(2, "0");
        var mm2 = String(parseInt(m[2], 10)).padStart(2, "0");
        var yyyy2 = m[3];
        if (yyyy2.length === 2) yyyy2 = "20" + yyyy2; // fallback
        return yyyy2 + "-" + mm2 + "-" + dd2;
    }

    return "";
}

function sportelloSave() {

    // overlay + disabilita bottone
    $("#sportelloModalLoadingOverlay").show();
    $("#btnSportelloSave").prop("disabled", true);

    try { setSportelloModalBusy(true, "saving"); } catch (e) { }

    // ✅ ID corretto (0 = nuovo)
    var id = parseInt($("#hidden_sportello_id").val(), 10) || 0;

    // ✅ costruisco liste studenti (prima di creare payload)
    var studentiDaModificareIdList = [];
    var studentiDaCancellareIdList = [];

    try {
        $("#studenti_table tbody tr").each(function () {
            var $tr = $(this);

            // 1a colonna = sportello_studente_id (nascosta ma presente)
            var ssid = parseInt($.trim($tr.find("td:eq(0)").text()), 10) || 0;

            // 2a colonna = presente (0/1) (nascosta ma presente)
            var presenteOld = parseInt($.trim($tr.find("td:eq(1)").text()), 10) || 0;

            // checkbox presenza
            var presenteNew = $tr.find(".chk-presenza").is(":checked") ? 1 : 0;

            // checkbox cancella
            var toDelete = $tr.find(".chk-cancella").is(":checked");

            if (ssid > 0) {
                if (toDelete) {
                    studentiDaCancellareIdList.push(ssid);
                } else {
                    if (presenteNew !== presenteOld) {
                        studentiDaModificareIdList.push(ssid);
                    }
                }
            }
        });
    } catch (e) {
        console.warn("[sportelloSave] build studenti lists error", e);
    }

    // ✅ valori da modal
    var oraVal = ($("#ora").val() || "").trim();

    // Data: deve essere Y-m-d
    var dataVal = getDateYmdFromPickerOrInput();
    if (!dataVal) {
        $("#sportelloModalLoadingOverlay").hide();
        $("#btnSportelloSave").prop("disabled", false);
        try { setSportelloModalBusy(false, "save_invalid_date"); } catch (e) { }

        var msg = "Data non valida: impossibile salvare. (Verifica il datepicker)";
        if (window.Swal) Swal.fire({ icon: "error", title: "Errore", text: msg });
        else alert(msg);
        return;
    }

    // selectpicker values
    var docente_id   = parseInt($("#docente").val(), 10) || 0;
    var materia_id   = parseInt($("#materia").val(), 10) || 0;
    var categoria_id = parseInt($("#categoria").val(), 10) || 0;
    var classe_id    = parseInt($("#classe").val(), 10) || 0;

    var luogoVal = ($("#luogo").val() || "").trim();

    var numero_ore = parseInt($("#numero_ore").val(), 10) || 1;
    if (numero_ore < 1) numero_ore = 1;
    if (numero_ore > 2) numero_ore = 2;

    // ---------------------------------------------------------
    // ✅ PATCH 2: VALIDAZIONI (in particolare per NUOVO sportello)
    // ---------------------------------------------------------
    var errors = [];

    if (!oraVal) errors.push("Seleziona l'ora.");
    if (docente_id <= 0) errors.push("Seleziona il docente.");
    if (materia_id <= 0) errors.push("Seleziona la materia.");
    if (!luogoVal) errors.push("Seleziona l'aula.");
    if (!(numero_ore === 1 || numero_ore === 2)) errors.push("Numero ore non valido (1 o 2).");

    // Se vuoi rendere più permissivo sugli update, qui è già “strict”.
    // Io lo applico sempre, perché mi hai chiesto creazione “completa”.
    if (errors.length > 0) {
        $("#sportelloModalLoadingOverlay").hide();
        $("#btnSportelloSave").prop("disabled", false);
        try { setSportelloModalBusy(false, "save_validation_fail"); } catch (e) { }

        var msg = errors.join("\n");
        if (window.Swal) {
            Swal.fire({
                icon: "warning",
                title: "Dati mancanti",
                text: msg,
                customClass: { popup: "swal-wide" } // opzionale, se vuoi usare classi custom
            });
        } else {
            alert(msg);
        }
        return;
    }

    // classe testuale (fallback utile per sportelloSave.php che accetta anche "classe")
    var classeTxt = "";
    try { classeTxt = ($("#classe option:selected").text() || "").trim(); } catch (e) { classeTxt = ""; }

    // Payload
    var payload = {
        id: id,
        data: dataVal,
        ora: oraVal,

        categoria_id: categoria_id,
        materia_id: materia_id,
        docente_id: docente_id,

        numero_ore: numero_ore,
        luogo: luogoVal,

        classe_id: classe_id,
        classe: classeTxt,

        argomento: $("#argomento").val() || "",
        max_iscrizioni: parseInt($("#max_iscrizioni").val(), 10) || 0,

        cancellato: $("#cancellato").is(":checked") ? 1 : 0,
        firmato: $("#firmato").is(":checked") ? 1 : 0
    };

    if ($('#hidden_sezione_online_clil').val() == 'true') {
        payload.online = $("#online").is(":checked") ? 1 : 0;
        payload.clil = $("#clil").is(":checked") ? 1 : 0;
        payload.orientamento = $("#orientamento").is(":checked") ? 1 : 0;
    } else {
        payload.online = 0;
        payload.clil = 0;
        payload.orientamento = 0;
    }

    payload.studentiDaModificareIdList = JSON.stringify(studentiDaModificareIdList);
    payload.studentiDaCancellareIdList = JSON.stringify(studentiDaCancellareIdList);

    console.log("[sportelloSave] PAYLOAD", payload);

    $.post("sportelloSave.php", payload, function (resp) {

        $("#sportelloModalLoadingOverlay").hide();
        $("#btnSportelloSave").prop("disabled", false);
        try { setSportelloModalBusy(false, "save_done"); } catch (e) { }

        if (!resp || !resp.ok) {
            var msg = (resp && (resp.error || resp.msg)) ? (resp.error || resp.msg) : "Errore salvataggio sportello.";
            console.error("[sportelloSave] FAIL resp=", resp);

            if (window.Swal) Swal.fire({ icon: "error", title: "Errore", text: msg });
            else alert(msg);
            return;
        }

        // aggiorna tabella principale
        sportelloReadRecords();

        // ✅ PATCH 1: ID “certo” + aggiorna hidden
        var rid = parseInt((resp && resp.id) ? resp.id : payload.id, 10) || payload.id;
        $("#hidden_sportello_id").val(rid);

        // ricarico dettaglio (stesso id) così rivedo aule/valori coerenti
        sportelloGetDetails(rid);

        // messaggio MBApp
        var mb = resp.mbapp || null;
        if (mb && typeof mb === "object") {
            var text = (mb.action ? ("MBApp: " + mb.action + " — ") : "MBApp: ") + (mb.msg || "");
            if (window.Swal) {
                Swal.fire({
                    icon: (mb.ok ? "success" : "warning"),
                    title: "Salvato",
                    text: text,
                    timer: 1700,
                    showConfirmButton: false
                });
            } else {
                if (!mb.ok) alert(text);
            }
        } else {
            if (window.Swal) {
                Swal.fire({
                    icon: "success",
                    title: "Salvato",
                    timer: 1200,
                    showConfirmButton: false
                });
            }
        }

    }, "json").fail(function (xhr, st, err) {

        $("#sportelloModalLoadingOverlay").hide();
        $("#btnSportelloSave").prop("disabled", false);
        try { setSportelloModalBusy(false, "save_fail"); } catch (e) { }

        console.error("[sportelloSave] AJAX FAIL", st, err, xhr && xhr.responseText);

        if (window.Swal) Swal.fire({ icon: "error", title: "Errore", text: "Errore di rete o server durante il salvataggio." });
        else alert("Errore di rete o server durante il salvataggio.");
    });
}


function setSportelloModalBusy(isBusy, reason) {
    const $m = $("#sportello_modal");
    $m.data("busy", isBusy ? 1 : 0);

    console.log("[modalBusy]", { isBusy: !!isBusy, reason: reason || "" });

    // Disabilita solo i campi del form + save, NON i pulsanti di chiusura
    const $toDisable = $m.find("input, textarea, select, button")
        .not('[data-dismiss="modal"]')
        .not('.close')
        .not('.btn-close');

    $toDisable.prop("disabled", !!isBusy);

    // bootstrap-select refresh
    $m.find("select.selectpicker").each(function () {
        const $s = $(this);
        if ($s.is('[data-dismiss="modal"]')) return;
        $s.prop("disabled", !!isBusy).selectpicker("refresh");
    });
}

function resetSportelloModalEnabledState() {
    const $m = $("#sportello_modal");

    console.log("[resetSportelloModalEnabledState] BEFORE", {
        disabled_inputs: $m.find(":input:disabled").length,
        backdrops: $(".modal-backdrop").length,
        body_modal_open: $("body").hasClass("modal-open")
    });

    $m.data("busy", 0);

    // Riabilita tutto
    $m.find("input, textarea, select, button").prop("disabled", false).removeClass("disabled");
    $m.find("select.selectpicker").each(function () {
        $(this).prop("disabled", false).selectpicker("refresh");
    });

    // Pulisci eventuali backdrops zombie
    const $b = $(".modal-backdrop");
    if ($b.length > 1) $b.not(":last").remove();

    console.log("[resetSportelloModalEnabledState] AFTER", {
        disabled_inputs: $m.find(":input:disabled").length,
        backdrops: $(".modal-backdrop").length,
        body_modal_open: $("body").hasClass("modal-open")
    });
}

// Aggancia una sola volta (mettilo in document.ready)
$(document).off("hidden.bs.modal.sportelloFix", "#sportello_modal");
$(document).on("hidden.bs.modal.sportelloFix", "#sportello_modal", function () {
    console.log("[sportello_modal] hidden.bs.modal -> force reset enable");
    resetSportelloModalEnabledState();
});


function sportelloGetDetails(sportello_id) {
    console.log("[sportelloGetDetails] OPEN sportello_id =", sportello_id);

    // 🔧 FIX CRITICO: resettiamo SEMPRE lo stato del modale prima di usarlo
    try {
        resetSportelloModalEnabledState();
        setSportelloModalBusy(false, "open_reset");
    } catch (e) {
        console.warn("[sportelloGetDetails] reset/busy reset error", e);
    }

    // ✅ NEW: se arriva -1 (nuovo) NON deve finire nel hidden come -1
    // perché sportelloSave.php aggiornato si aspetta id > 0 per UPDATE
    // e per INSERT deve ricevere id=0.
    if (sportello_id <= 0) {
        $("#hidden_sportello_id").val(0);
    } else {
        $("#hidden_sportello_id").val(sportello_id);
    }

    if (sportello_id > 0) {

        // (opzionale ma utile) busy mentre carichi
        try { setSportelloModalBusy(true, "loading_details"); } catch (e) { }

        $.post("../docente/sportelloReadDetails.php", { sportello_id: sportello_id }, function (data, status) {

            console.log("[sportelloGetDetails] ReadDetails OK", { status: status, data: data });

            var sportello = data;

            // 🔧 tolgo busy appena ho i dati
            try { setSportelloModalBusy(false, "details_loaded"); } catch (e) { }

            setDbDateToPickr(data_pickr, sportello.sportello_data);
            setOraSelect(sportello.sportello_ora);

            // selectpicker: dopo val, refresh è buona pratica
            $('#docente').selectpicker('val', sportello.docente_id).selectpicker('refresh');
            $('#materia').selectpicker('val', sportello.materia_id).selectpicker('refresh');
            $('#categoria').selectpicker('val', sportello.categoria_id).selectpicker('refresh');

            $("#numero_ore").val(sportello.sportello_numero_ore);
            $("#argomento").val(sportello.sportello_argomento);

            // aula attuale
            var aulaDb = (sportello.sportello_luogo || "").trim();

            // ✅ memorizzo sempre l’aula “corrente”
            try { $("#luogo").attr("data-current", aulaDb); } catch (e) { }

            if (aulaDb) {
                ensureSelectHasOption($("#luogo"), aulaDb, aulaDb + " (attuale)");
                $("#luogo").val(aulaDb).selectpicker('refresh');
            } else {
                $("#luogo").val("").selectpicker('refresh');
            }

            var isAttivo = (parseInt(sportello.sportello_attivo, 10) === 1);
            console.log("[sportelloGetDetails] isAttivo =", isAttivo, "aulaDb=", aulaDb);

            setTimeout(function () {
                verificaAulaCorrenteDidattica({
                    includiAulaCorrente: isAttivo,
                    includeAula: (isAttivo ? aulaDb : "")
                });
            }, 150);

            // classe
            $('#classe').selectpicker('val', sportello.classe_id).selectpicker('refresh');

            // altri campi
            $("#max_iscrizioni").val(sportello.sportello_max_iscrizioni);
            $("#cancellato").prop('checked', sportello.sportello_cancellato != 0 && sportello.sportello_cancellato != null);
            $("#firmato").prop('checked', sportello.sportello_firmato != 0 && sportello.sportello_firmato != null);

            if ($('#hidden_sezione_online_clil').val() == 'true') {
                $("#online").prop('checked', sportello.sportello_online != 0 && sportello.sportello_online != null);
                $("#clil").prop('checked', sportello.sportello_clil != 0 && sportello.sportello_clil != null);
                $("#orientamento").prop('checked', sportello.sportello_orientamento != 0 && sportello.sportello_orientamento != null);
            }

            // studenti
            $('#studenti_table tbody').empty();

            var markup = '';
            (sportello.studenti || []).forEach(function (studenti) {
                markup +=
                    "<tr>" +
                    "<td>" + studenti.sportello_studente_id + "</td>" +
                    "<td>" + studenti.sportello_studente_presente + "</td>" +
                    "<td style=\"text-align: left; vertical-align: middle;\">" + studenti.studente_cognome + " " + studenti.studente_nome + "</td>" +
                    "<td style=\"text-align: left; vertical-align: middle;\">" + studenti.sportello_studente_argomento + "</td>" +
                    "<td style=\"text-align: center; vertical-align: middle;\">" +
                    "<input type=\"checkbox\" class=\"chk-presenza\" " +
                    ((studenti.sportello_studente_presente == 0 || studenti.sportello_studente_presente == null) ? "" : " checked") +
                    "></td>" +
                    "<td style=\"text-align: center; vertical-align: middle;\">" +
                    "<input type=\"checkbox\" class=\"chk-cancella\">" +
                    "</td>" +
                    "</tr>";
            });

            $('#studenti_table > tbody:last-child').append(markup);
            $('#studenti_table td:nth-child(1),#studenti_table th:nth-child(1),#studenti_table td:nth-child(2),#studenti_table th:nth-child(2)').hide();

            $("#_error-materia-part").hide();

            console.log("[sportelloGetDetails] SHOW modal with values", {
                data: $("#data").val(),
                ora: $("#ora").val(),
                luogo: $("#luogo").val(),
                disabled_inputs: $("#sportello_modal :input:disabled").length
            });

            $("#sportello_modal").modal("show");

            setTimeout(function () {
                console.log("[sportello_modal] AFTER SHOW", {
                    disabled_inputs: $("#sportello_modal :input:disabled").length,
                    backdrops: $(".modal-backdrop").length,
                    body_modal_open: $("body").hasClass("modal-open")
                });
            }, 100);

        }, "json").fail(function (xhr, st, err) {
            console.error("[sportelloGetDetails] ReadDetails FAIL", st, err, xhr && xhr.responseText);
            try { setSportelloModalBusy(false, "details_fail"); } catch (e) { }
            alert("Errore lettura sportello (vedi console).");
        });

    } else {

        console.log("[sportelloGetDetails] NEW sportello init");

        // ✅ oggi
        data_pickr.setDate(Date.today().toString('d/M/yyyy'));

        // ✅ 13:50
        $("#ora").selectpicker('val', "13:50").selectpicker('refresh');

        // select iniziali
        $('#docente').val("0").selectpicker('refresh');
        $('#materia').val("0").selectpicker('refresh');
        $('#categoria').val("0").selectpicker('refresh');

        // ✅ numero ore = 1
        $("#numero_ore").val("1");
        $("#argomento").val("");

        // ✅ reset aula corrente (nuovo sportello)
        try { $("#luogo").attr("data-current", ""); } catch (e) { }

        $("#luogo").val("").selectpicker('refresh');

        // ✅ lista aule libere per oggi/13:50/durata 1
        setTimeout(function () {
            verificaAulaCorrenteDidattica({
                includiAulaCorrente: false,
                includeAula: "",
                durataOre: 1 // se la tua funzione lo supporta; altrimenti ignora
            });
        }, 100);

        $('#classe').val("0").selectpicker('refresh');

        $("#max_iscrizioni").val($("#hidden_max_iscrizioni_default").val());
        $("#cancellato").prop('checked', false);
        $("#firmato").prop('checked', false);

        if ($('#hidden_sezione_online_clil').val() == 'true') {
            $("#online").prop('checked', false);
            $("#clil").prop('checked', false);
            $("#orientamento").prop('checked', false);
        }

        $('#studenti_table tbody').empty();
        $("#_error-materia-part").hide();

        $("#sportello_modal").modal("show");

        setTimeout(function () {
            console.log("[sportello_modal] AFTER SHOW (NEW)", {
                disabled_inputs: $("#sportello_modal :input:disabled").length,
                backdrops: $(".modal-backdrop").length,
                body_modal_open: $("body").hasClass("modal-open")
            });
        }, 100);
    }
}


function importFile(file) {
    if (!file) {
        alert("Nessun file selezionato.");
        return;
    }

    const reader = new FileReader();

    reader.onload = function (event) {
        const contenuto = event.target.result;

        $.post("sportelloImport.php", { contenuto: contenuto })
            .done(function (data) {
                $('#result_text').stop(true, true).html(data).fadeIn();

                // 🔹 Dopo 10 secondi, nascondi il messaggio
                setTimeout(function () {
                    $('#result_text').fadeOut('slow', function () {
                        $(this).html("");
                    });
                }, 10000);

                sportelloReadRecords();
            })
            .fail(function (xhr, status, error) {
                console.error("Errore durante l'import:", error);
                $('#result_text')
                    .stop(true, true)
                    .html("<b style='color:red;'>Errore durante l'importazione del file.</b>")
                    .fadeIn()
                    .delay(10000)
                    .fadeOut('slow', function () {
                        $(this).html("");
                    });
            });
    };

    reader.onerror = function (err) {
        console.error("Errore di lettura file:", err);
        alert("Errore nella lettura del file. Controlla il formato.");
    };

    reader.readAsText(file, "UTF-8");
}

function sportelloDuplica(id) {

    if (typeof Swal === 'undefined') {
        // fallback
        if (!confirm("Vuoi duplicare lo sportello? Il nuovo sportello sarà in bozza (aula vuota).")) return;
        doDup();
        return;
    }

    Swal.fire({
        title: 'Duplicare lo sportello?',
        text: 'Verrà creato un nuovo sportello in BOZZA (aula vuota) e senza collegamenti MBApp.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sì, duplica',
        cancelButtonText: 'Annulla'
    }).then((res) => {
        if (res.isConfirmed) doDup();
    });

    function doDup() {
        console.log("[sportelloDuplica] START id=", id);
        showSpinner();

        $.post("sportelloDuplica.php", { sportello_id: id }, function (resp) {
            console.log("[sportelloDuplica] RESPONSE", resp);
            hideSpinner();

            if (resp && resp.ok && resp.new_sportello_id) {

                // ricarica lista
                sportelloReadRecords();

                // apri dettaglio del nuovo sportello
                console.log("[sportelloDuplica] OPEN NEW sportello_id=", resp.new_sportello_id);
                sportelloGetDetails(parseInt(resp.new_sportello_id, 10));

                Swal.fire({
                    title: 'Duplicato!',
                    text: 'Creato nuovo sportello ID ' + resp.new_sportello_id + ' (aula vuota, bozza).',
                    icon: 'success',
                    timer: 1800,
                    showConfirmButton: false
                });

            } else {
                Swal.fire({
                    title: 'Errore duplicazione',
                    text: (resp && resp.msg) ? resp.msg : 'Errore duplicazione.',
                    icon: 'error'
                });
            }
        }, "json").fail(function (xhr, st, err) {
            hideSpinner();
            console.error("[sportelloDuplica] FAIL", st, err, xhr && xhr.responseText);
            Swal.fire({
                title: 'Errore di rete o server',
                text: 'Impossibile duplicare lo sportello.',
                icon: 'error'
            });
        });
    }
}

function importStudents(file, selezione) {
    var contenuto = "";
    const reader = new FileReader();
    reader.addEventListener('load', (event) => {
        contenuto = event.target.result;
        $.post("sportelloStudentiImport.php", {
            contenuto: contenuto,
            lista_sportelli: selezione
        },
            function (data, status) {
                $('#result_text').html(data);
                $('#file_select_students').change(function (e) { });
                $("#sportelli_selezionati").html("");
                selections.length = 0;
                sportelloReadRecords();
                setTimeout(function () { $('#result_text').html(""); }, 5000);
            });
    });
    reader.readAsText(file);
}

$(document).ready(function () {

    // DEBUG: eventi modale + backdrop
    $(document).on('shown.bs.modal', '#sportello_modal', function () {
        console.log("[sportello_modal] SHOWN", {
            disabled_inputs: $("#sportello_modal :input:disabled").length,
            backdrop: $(".modal-backdrop").length,
            body_modal_open: $("body").hasClass("modal-open")
        });
    });

    $(document).on('hidden.bs.modal', '#sportello_modal', function () {
        console.log("[sportello_modal] HIDDEN", {
            backdrop: $(".modal-backdrop").length,
            body_modal_open: $("body").hasClass("modal-open")
        });
    });

    data_pickr = flatpickr("#data", {
        locale: { firstDayOfWeek: 1 },
        dateFormat: 'j/n/Y',
        onChange: function () {
            // se lo sportello è attivo lo capisci solo quando lo carichi;
            // per ora: includi aula corrente SOLO se esiste un valore già settato
            const aulaCorr = ($("#luogo").val() || "").trim();
            verificaAulaCorrenteDidattica({ includiAulaCorrente: (aulaCorr !== "") });
        }
    });

    $(document).on('click', ".bootstrap-select[data-id='luogo'] button", function () {
        var aulaCorr = ($("#luogo").attr("data-current") || "").trim() || ($("#luogo").val() || "").trim();
        verificaAulaCorrenteDidattica({ includiAulaCorrente: (aulaCorr !== ""), includeAula: aulaCorr });

    });


    $('#ora').selectpicker();
    $('#luogo').selectpicker();

    $("#ora").on("change", function () {
        var aulaCorr = ($("#luogo").attr("data-current") || "").trim() || ($("#luogo").val() || "").trim();
        verificaAulaCorrenteDidattica({ includiAulaCorrente: (aulaCorr !== ""), includeAula: aulaCorr });

    });

    $("#numero_ore").on("change", function () {
        var aulaCorr = ($("#luogo").attr("data-current") || "").trim() || ($("#luogo").val() || "").trim();
        verificaAulaCorrenteDidattica({ includiAulaCorrente: (aulaCorr !== ""), includeAula: aulaCorr });

    });

    // se anche data cambia:
    $("#data").on("change", function () {
        var aulaCorr = ($("#luogo").attr("data-current") || "").trim() || ($("#luogo").val() || "").trim();
        verificaAulaCorrenteDidattica({ includiAulaCorrente: (aulaCorr !== ""), includeAula: aulaCorr });

    });

    sportelloReadRecords();

    $("#categoria_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            categoria_filtro_id = this.value;
            sportelloReadRecords();
        });

    $("#docente_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            docente_filtro_id = this.value;
            sportelloReadRecords();
        });

    $("#studente_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            studente_filtro_id = this.value;
        });

    $("#materia_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            materia_filtro_id = this.value;
            sportelloReadRecords();
        });

    $("#classe_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            classe_filtro_id = this.value;
            sportelloReadRecords();
        });

    $('#riga_iscrizioni_sportelli').hide();

    $('#file_select_id').change(function (e) {
        const file = e.target.files[0];
        if (!file) return;

        importFile(file);

        // 🔹 resetta l'input per permettere di riselezionare lo stesso file
        e.target.value = '';
    });

    // delegato: funziona anche se #sportelloModal viene ricreato
    $(document).on('hidden.bs.modal', '#sportello_modal', function () {
        if (window.toastAfterModalClose) {
            showSuccessMessage(window.toastAfterModalClose);
            window.toastAfterModalClose = null;
        }
    });


});
