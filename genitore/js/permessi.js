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

function impostaDataPermesso() {
    const inputData = document.getElementById("data");
    const avviso = document.getElementById("avvisoData");

    const now = new Date();
    let dataSelezionata = new Date();

    const ore = now.getHours();

    // Se dopo le 9, parte da domani
    if (ore >= 9) {
        dataSelezionata.setDate(dataSelezionata.getDate() + 1);
        avviso.style.display = "block";
    } else {
        avviso.style.display = "none";
    }

    // Salta sabato (6) e domenica (0)
    while (dataSelezionata.getDay() === 0 || dataSelezionata.getDay() === 6) {
        dataSelezionata.setDate(dataSelezionata.getDate() + 1);
    }

    // Formato YYYY-MM-DD
    inputData.value = dataSelezionata.toISOString().split("T")[0];
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
            alert("Errore durante il salvataggio del permesso.");
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

            if ($("#data").length) $("#data").val(permesso.permesso_data || "");
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
