/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// 🔽 Recupero parametro "d" passato nello <script src=...>
var scripts = document.getElementsByTagName('script');
var myScript = scripts[scripts.length - 1];
var url = new URL(myScript.src);
var params = new URLSearchParams(url.search);
var device = params.get("d") || "desktop"; // default "desktop"

function permessiReadRecords() {
    var endpoint = (device === "mobile")
        ? "permessiReadRecords_mobile.php"
        : "permessiReadRecords.php";

    $.post(endpoint, {
        studente_filtro_id: $('#hidden_studente_id').val()
    }, function (data, status) {
        $(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            trigger: 'hover',
            container: 'body'
        });
    });
}

// Dropdown studenti mobile
$('#studente_filtro').on('change', function () {
    $('#hidden_studente_id').val(this.value);
    permessiReadRecords();
});

document.addEventListener("DOMContentLoaded", function () {
    const rientroCheckbox = document.getElementById("rientro");
    const oraRientroGroup = document.getElementById("ora_rientro_group");
    const oraRientroInput = document.getElementById("ora_rientro");

    if (!rientroCheckbox || !oraRientroGroup || !oraRientroInput) return;

    rientroCheckbox.addEventListener("change", function () {
        if (this.checked) {
            oraRientroGroup.style.display = "flex";
        } else {
            oraRientroGroup.style.display = "none";
            oraRientroInput.value = "";
        }
    });
});

function getPermessiCutoffMinutes() {
    const config = window.GESTORE_PERMESSI_CONFIG || {};
    const raw = String(config.oraLimiteGenitori || "09:00").trim();
    const match = raw.match(/^(\d{1,2})(?::?(\d{2}))?$/);

    if (!match) {
        return 9 * 60;
    }

    const hours = Math.max(0, Math.min(23, Number(match[1]) || 0));
    const minutes = Math.max(0, Math.min(59, Number(match[2] || 0) || 0));
    return hours * 60 + minutes;
}

function getPermessiNow() {
    const config = window.GESTORE_PERMESSI_CONFIG || {};
    const serverNowMs = Number(config.serverNowMs || 0);
    const clientLoadedAtMs = Number(config.clientLoadedAtMs || 0);

    if (serverNowMs > 0 && clientLoadedAtMs > 0) {
        return new Date(serverNowMs + (Date.now() - clientLoadedAtMs));
    }

    return new Date();
}

function getPermessiTodayParts() {
    const timezone = (window.GESTORE_PERMESSI_CONFIG && window.GESTORE_PERMESSI_CONFIG.timezone) || "Europe/Rome";

    return new Intl.DateTimeFormat("en-CA", {
        timeZone: timezone,
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        weekday: "short",
        hour: "2-digit",
        minute: "2-digit",
        hourCycle: "h23"
    }).formatToParts(getPermessiNow()).reduce(function (acc, part) {
        if (part.type !== "literal") {
            acc[part.type] = part.value;
        }
        return acc;
    }, {});
}

function formatPermessiIsoDate(date) {
    const anno = date.getUTCFullYear();
    const mese = String(date.getUTCMonth() + 1).padStart(2, "0");
    const giorno = String(date.getUTCDate()).padStart(2, "0");
    return anno + "-" + mese + "-" + giorno;
}

function formatPermessiItalianDate(isoDate) {
    const parts = String(isoDate).split("-");
    if (parts.length !== 3) {
        return isoDate;
    }
    return parts[2] + "/" + parts[1] + "/" + parts[0];
}

function formatPermessiLongItalianDate(isoDate) {
    const parts = String(isoDate).split("-");
    if (parts.length !== 3) {
        return isoDate;
    }

    const date = new Date(Date.UTC(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2])));
    return new Intl.DateTimeFormat("it-IT", {
        timeZone: "UTC",
        weekday: "long",
        day: "numeric",
        month: "long",
        year: "numeric"
    }).format(date);
}

function getPermessiSelectableDates() {
    const config = window.GESTORE_PERMESSI_CONFIG || {};
    const holidays = new Set(Array.isArray(config.giorniFestivi) ? config.giorniFestivi : []);
    const maxDates = Math.max(1, Number(config.giorniSelezionabili || 4));
    const parts = getPermessiTodayParts();
    const currentMinutes = (Number(parts.hour) * 60) + Number(parts.minute || 0);
    const cutoffMinutes = getPermessiCutoffMinutes();
    const today = new Date(Date.UTC(
        Number(parts.year),
        Number(parts.month) - 1,
        Number(parts.day)
    ));
    const todayIso = formatPermessiIsoDate(today);
    const dates = [];
    let cursor = new Date(today.getTime());

    if (currentMinutes >= cutoffMinutes) {
        cursor.setUTCDate(cursor.getUTCDate() + 1);
    }

    for (let guard = 0; dates.length < maxDates && guard < 30; guard++) {
        const iso = formatPermessiIsoDate(cursor);
        const day = cursor.getUTCDay();
        if (day !== 0 && day !== 6 && !holidays.has(iso)) {
            let label = formatPermessiLongItalianDate(iso);
            if (iso === todayIso) {
                label = label + " (oggi)";
            } else {
                const tomorrow = new Date(today.getTime());
                tomorrow.setUTCDate(tomorrow.getUTCDate() + 1);
                if (iso === formatPermessiIsoDate(tomorrow)) {
                    label = label + " (domani)";
                }
            }
            dates.push({ value: iso, label: label });
        }
        cursor.setUTCDate(cursor.getUTCDate() + 1);
    }

    return {
        dates: dates,
        todayAvailable: dates.some(function (item) { return item.value === todayIso; })
    };
}

function setPermessiDateSelectValue(value) {
    const inputData = document.getElementById("data");
    if (!inputData) {
        return;
    }
    if (value && !Array.prototype.some.call(inputData.options, function (option) { return option.value === value; })) {
        const option = document.createElement("option");
        option.value = value;
        option.textContent = formatPermessiItalianDate(value);
        inputData.appendChild(option);
    }
    inputData.value = value || "";
}

function impostaDataPermesso() {
    const inputData = document.getElementById("data");
    const avviso = document.getElementById("avvisoData");

    if (!inputData || !avviso) {
        return;
    }

    const selectable = getPermessiSelectableDates();
    inputData.innerHTML = "";
    selectable.dates.forEach(function (item) {
        const option = document.createElement("option");
        option.value = item.value;
        option.textContent = item.label;
        inputData.appendChild(option);
    });

    avviso.textContent = "Il termine per richiedere permessi per oggi e' scaduto.";
    avviso.style.display = selectable.todayAvailable ? "none" : "block";

    if (selectable.dates.length > 0) {
        inputData.value = selectable.dates[0].value;
    }
}

function permessiDelete(id) {
    var conf = confirm("Sei sicuro di voler cancellare il permesso?");
    if (!conf) return;

    $.post("permessoDelete.php", {
        id: id
    }, function (response) {

        if (response && response.ok) {
            permessiReadRecords();
        } else {
            alert("Errore durante la cancellazione del permesso.");
        }

    }, 'json')
        .fail(function () {
            alert("Errore di comunicazione con il server.");
        });
}

function permessoSave() {
    if ($("#data").val() == "") {
        $("#_error-permesso").text("Devi selezionare una data per il permesso.");
        $("#_error-permesso-part").show();
        return;
    }
    if ($("#motivo").val() == "") {
        $("#_error-permesso").text("Devi indicare un motivo per il permesso.");
        $("#_error-permesso-part").show();
        return;
    }
    if ($("#ora_uscita").val() == "") {
        $("#_error-permesso").text("Devi selezionare un'ora di uscita per il permesso.");
        $("#_error-permesso-part").show();
        return;
    }
    let rientro = $("#rientro").prop('checked') ? 1 : 0;

    if (rientro == 1 && $("#ora_rientro").val() == "") {
    $("#_error-permesso").text("Devi selezionare un'ora di rientro.");
    $("#_error-permesso-part").show();
    return;
    }
    if (rientro == 0 && ($("#hidden_rientro").val() == 1)) {
        var conf = confirm("Sei sicuro di volere disattivare il rientro per il permesso?");
        if (conf == false) {
            return;
        }
    }
    if (rientro == 1 && ($("#hidden_rientro").val() == 0)) {
        var conf = confirm("Sei sicuro di voler attivare il rientro per il permesso?");
        if (conf == false) {
            return;
        }
    }

    $("#_error-permesso-part").hide();

    $.post("permessoSave.php", {
        id: $("#hidden_permesso_id").val(),
        data: $("#data").val(),
        ora_uscita: $("#ora_uscita").val(),
        motivo: $("#motivo").val(),
        ora_rientro: $("#ora_rientro").val(),
        rientro: $("#rientro").prop('checked') ? 1 : 0,
        id_studente: $('#hidden_studente_id').val()
    }, function (response) {
        if (response && response.ok) {
            $("#permesso_modal").modal("hide");
            permessiReadRecords();
        } else {
            const msg = response && response.error ? response.error : "Errore durante il salvataggio del permesso.";
            $("#_error-permesso").text(msg);
            $("#_error-permesso-part").show();
        }
    }, 'json').fail(function () {
        alert("Errore di comunicazione con il server.");
    });
}

function permessiGetDetails(permesso_id) {
    $("#hidden_permesso_id").val(permesso_id);

    if (permesso_id > 0) {
        $.post(device === "mobile" ? "permessiReadDetails_mobile.php" : "permessiReadDetails.php", {
            id: permesso_id
        }, function (data, status) {
            var permesso = (typeof data === "string") ? JSON.parse(data) : data;

            if ($("#data").length) setPermessiDateSelectValue(permesso.permesso_data || "");
            if ($("#ora_uscita").length) $("#ora_uscita").val(permesso.permesso_ora_uscita || "");
            if ($("#rientro").length) $("#rientro").prop('checked', Number(permesso.permesso_rientro) === 1);
            if ($("#motivo").length) $("#motivo").val(permesso.permesso_motivo || "");
            if ($("#ora_rientro").length) $("#ora_rientro").val(permesso.permesso_ora_rientro || "");

            if (Number(permesso.permesso_rientro) === 1) {
                $("#ora_rientro_group").show();
            } else {
                $("#ora_rientro_group").hide();
            }

            $("#hidden_rientro").val(permesso.permesso_rientro || 0);
        }, 'json');
    } else {
        if ($("#ora_uscita").length) $("#ora_uscita").val("");
        if ($("#rientro").length) $("#rientro").prop('checked', false);
        if ($("#motivo").length) $("#motivo").val("");
        if ($("#ora_rientro").length) $("#ora_rientro").val("");
        $("#hidden_rientro").val(0);
        $("#ora_rientro_group").hide();
        impostaDataPermesso();
        $('#btn-save').show();
    }

    $("#permesso_modal").modal("show");
    $("#_error-permesso-part").hide();
}

$(document).ready(function () {
    permessiReadRecords();

    $("#studente_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $('#hidden_studente_id').val(this.value);
            permessiReadRecords();
        });

});
