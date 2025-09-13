/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// 🔽 Recupero parametro "d" passato nello <script src=...>
var scripts = document.getElementsByTagName('script');
var myScript = scripts[scripts.length - 1];
var url = new URL(myScript.src);
var params = new URLSearchParams(url.search);
var device = params.get("d") || "desktop"; // default "desktop"

// 🔹 Memorizza l’ultima colonna ordinata
var lastSort = { columnIndex: null, asc: true };

function permessiReadRecords() {
    var endpoint = (device === "mobile")
        ? "permessiReadRecords_mobile.php"
        : "permessiReadRecords.php";

    var studenteId = $('#hidden_studente_id').val();
    var dataFiltro = $('#data_filtro').val(); // nuovo filtro data
    var soloRichiesti = $('#solo_richiesti').is(':checked') ? 1 : 0; // nuovo filtro

    hideAllTooltips();
    $.get(endpoint, { studente_filtro_id: studenteId, data_filtro: dataFiltro, solo_richiesti: soloRichiesti}, function (data, status) {
        $(".records_content").html(data);

        $('[data-toggle="tooltip"]').tooltip({
            trigger: 'hover',
            container: 'body'
        });

        var $table = $(".records_content table");
        var $ths = $table.find("th.sortable");

        if (lastSort.columnIndex !== null) {
            sortTable($table, lastSort.columnIndex, lastSort.asc);
            $ths.removeClass("sorted-asc sorted-desc").removeData("asc");
            $ths.eq(lastSort.sortableIndex)
                .addClass(lastSort.asc ? "sorted-asc" : "sorted-desc")
                .data("asc", lastSort.asc);
        } else {
            var defaultColumnIndex = 2;      // colonna reale "Ora uscita"
            var defaultSortableIndex = 1;    // indice tra i soli th.sortable

            sortTable($table, defaultColumnIndex, true);
            $ths.removeClass("sorted-asc sorted-desc").removeData("asc");
            $ths.eq(defaultSortableIndex)
                .addClass("sorted-asc")
                .data("asc", true);

            lastSort = {
                columnIndex: defaultColumnIndex,
                sortableIndex: defaultSortableIndex,
                asc: true
            };
        }
    });
}


// Ricarica quando il checkbox cambia
$(document).on("change", "#solo_richiesti", function () {
    permessiReadRecords();
});

// Dropdown studenti mobile
$('#studente_filtro').on('change', function () {
    $('#hidden_studente_id').val(this.value);
    permessiReadRecords();
});

document.addEventListener("DOMContentLoaded", function () {
    const rientroCheckbox = document.getElementById("rientro");
    const oraRientroGroup = document.getElementById("ora_rientro_group");

    if (rientroCheckbox) {
        rientroCheckbox.addEventListener("change", function () {
            if (this.checked) {
                oraRientroGroup.style.display = "flex";
            } else {
                oraRientroGroup.style.display = "none";
                document.getElementById("ora_rientro").value = "";
            }
        });
    }
});

function impostaDataPermesso() {
    const inputData = document.getElementById("data");
    const avviso = document.getElementById("avvisoData");

    const now = new Date();
    const oggi = new Date();
    const domani = new Date();
    domani.setDate(oggi.getDate() + 1);

    const ore = now.getHours();

    function formatDate(date) {
        return date.toISOString().split("T")[0];
    }

    if (ore < 9) {
        inputData.value = formatDate(oggi);
        avviso.style.display = "none";
    } else {
        inputData.value = formatDate(domani);
        avviso.style.display = "block";
    }
}

function permessiDelete(id) {
    hideAllTooltips();
    var conf = confirm("Sei sicuro di volere cancellare il permesso ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
            id: id,
            table: 'permessi_uscita'
        }, function (data, status) {
            permessiReadRecords();
        });
    }
}

function permessoConfirm(id) {
    hideAllTooltips();
    $.post("permessoConfirm.php", { id: id }, function (data, status) {
        permessiReadRecords();
    });
}

function hideAllTooltips() {
    try { $('[data-toggle="tooltip"]').tooltip('hide'); } catch (e) { }
    $('.tooltip').remove();
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
    rientro = $("#rientro").prop('checked') ? 1 : 0;

    if (rientro == 0 && ($("#hidden_rientro").val() == 1)) {
        var conf = confirm("Sei sicuro di volere disattivare il rientro per il permesso?");
        if (conf == false) return;
    }
    if (rientro == 1 && ($("#hidden_rientro").val() == 0)) {
        var conf = confirm("Sei sicuro di voler attivare il rientro per il permesso?");
        if (conf == false) return;
    }

    $("#_error-permessi-part").hide();

    $.post("permessoSave.php", {
        id: $("#hidden_permesso_id").val(),
        data: $("#data").val(),
        ora_uscita: $("#ora_uscita").val(),
        motivo: $("#motivo").val(),
        ora_rientro: $("#ora_rientro").val(),
        rientro: $("#rientro").prop('checked') ? 1 : 0,
        id_studente: $('#hidden_studente_id').val(),
        stato: $("#stato").val(),
        note_segreteria: $("#note_segreteria").val()
    }, function (data, status) {
        $("#permesso_modal").modal("hide");
        permessiReadRecords();
    });
}

function permessiGetDetails(permesso_id) {
    $("#hidden_permesso_id").val(permesso_id);

    if (permesso_id > 0) {
        $.post(device === "mobile" ? "permessiReadDetails_mobile.php" : "permessiReadDetails.php", {
            id: permesso_id
        }, function (data, status) {
            var permesso = JSON.parse(data);

            if ($("#data").length) $("#data").val(permesso.permesso_data);
            if ($("#ora_uscita").length) $("#ora_uscita").val(permesso.permesso_ora_uscita);
            if ($("#rientro").length) $("#rientro").val(permesso.permesso_rientro);
            if ($("#motivo").length) $("#motivo").val(permesso.permesso_motivo);
            if ($("#ora_rientro").length) $("#ora_rientro").val(permesso.permesso_ora_rientro);
            if ($("#stato").length) $("#stato").val(permesso.permesso_stato);
            if ($("#note_segreteria").length) $("#note_segreteria").val(permesso.permesso_note_segreteria);
            if ($("#studente_nome").length) $("#studente_nome").val(permesso.studente_nome + " " + permesso.studente_cognome);
            if ($("#genitore_nome").length) $("#genitore_nome").val(permesso.genitore_nome + " " + permesso.genitore_cognome);
            if ($("#studente_classe").length) $("#studente_classe").val(permesso.studente_classe);
        });
    } else {
        if ($("#ora_uscita").length) $("#ora_uscita").val("");
        if ($("#rientro").length) $("#rientro").prop('checked', false);
        if ($("#motivo").length) $("#motivo").val("");
        if ($("#ora_rientro").length) $("#ora_rientro").val("");
        if ($("#stato").length) $("#stato").val("in_attesa");
        if ($("#note_segreteria").length) $("#note_segreteria").val("");
        if ($("#studente_nome").length) $("#studente_nome").val("");
        if ($("#genitore_nome").length) $("#genitore_nome").val("");
        if ($("#studente_classe").length) $("#studente_classe").val("");
        impostaDataPermesso();
        $('#btn-save').show();
    }

    $("#permesso_modal").modal("show");
    $("#_error-permesso-part").hide();
}

// 🔹 Ordinamento
function sortTable($table, columnIndex, asc) {
    var rows = $table.find("tbody tr").toArray().sort(function (a, b) {
        var A = $(a).children("td").eq(columnIndex).text().trim().toUpperCase();
        var B = $(b).children("td").eq(columnIndex).text().trim().toUpperCase();

        if (/^\d{2}:\d{2}$/.test(A) && /^\d{2}:\d{2}$/.test(B)) {
            A = parseInt(A.split(":")[0], 10) * 60 + parseInt(A.split(":")[1], 10);
            B = parseInt(B.split(":")[0], 10) * 60 + parseInt(B.split(":")[1], 10);
        }

        if (A < B) return asc ? -1 : 1;
        if (A > B) return asc ? 1 : -1;
        return 0;
    });

    $.each(rows, function (index, row) {
        $table.children("tbody").append(row);
    });
}

// 🔹 Gestione click sulle intestazioni
$(document).on("click", "th.sortable", function () {
    var $th = $(this);
    var $table = $th.closest("table");
    var columnIndex = $th.index();

    // toggle asc/desc
    var asc = !$th.hasClass("sorted-asc");

    // reset classi su tutte le intestazioni
    $th.closest("tr").find("th.sortable").removeClass("sorted-asc sorted-desc");

    // aggiungi la nuova classe
    $th.addClass(asc ? "sorted-asc" : "sorted-desc");

    // salva stato
    lastSort = { columnIndex: columnIndex, asc: asc };

    // ordina
    sortTable($table, columnIndex, asc);
});

$(document).ready(function () {
    // Flatpickr su #data_filtro
    flatpickr("#data_filtro", {
        altInput: true,
        altFormat: "d/m/Y",
        dateFormat: "Y-m-d",
        defaultDate: new Date(),
        locale: "it",
        onChange: function () {
            permessiReadRecords(); // aggiorna tabella al cambio data
        }
    });

    permessiReadRecords();

    $("#studente_filtro").on("changed.bs.select", function (e, clickedIndex, newValue, oldValue) {
        $('#hidden_studente_id').val(this.value);
        permessiReadRecords();
    });

});
