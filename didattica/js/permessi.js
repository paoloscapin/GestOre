/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
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

	$.get(endpoint+"?studente_filtro_id=" + $('#hidden_studente_id').val(), {}, function (data, status) {
		$(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            trigger: 'hover',
            container: 'body'
        });
	});
}

// Dropdown studenti mobile
$('#studente_filtro').on('change', function(){
    $('#hidden_studente_id').val(this.value);
    permessiReadRecords();
});

document.addEventListener("DOMContentLoaded", function () {
    const rientroCheckbox = document.getElementById("rientro");
    const oraRientroGroup = document.getElementById("ora_rientro_group");

    rientroCheckbox.addEventListener("change", function () {
        if (this.checked) {
            oraRientroGroup.style.display = "flex"; // mostro il campo
        } else {
            oraRientroGroup.style.display = "none";  // lo nascondo
            document.getElementById("ora_rientro").value = ""; // pulisco eventuale valore
        }
    });
});

function impostaDataPermesso() {
    const inputData = document.getElementById("data");
    const avviso = document.getElementById("avvisoData");

    const now = new Date();
    const oggi = new Date();
    const domani = new Date();
    domani.setDate(oggi.getDate() + 1);

    const ore = now.getHours();

    // Funzione formattazione YYYY-MM-DD
    function formatDate(date) {
        return date.toISOString().split("T")[0];
    }

    if (ore < 9) {
        // Prima delle 9 -> oggi
        inputData.value = formatDate(oggi);
        avviso.style.display = "none";
    } else {
        // Dopo le 9 -> domani
        inputData.value = formatDate(domani);
        avviso.style.display = "block"; // mostra avviso
    }
}


function permessiDelete(id) {
    var conf = confirm("Sei sicuro di volere cancellare il permesso ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
            id: id,
            table: 'permessi_uscita'
        },
            function (data, status) {
                permessiReadRecords();
            }
        );
    }
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

        $("#_error-permessi-part").hide();

        $.post("permessoSave.php", {
            id: $("#hidden_permesso_id").val(),
            data: $("#data").val(),
            ora_uscita: $("#ora_uscita").val(),
            motivo: $("#motivo").val(),
            ora_rientro: $("#ora_rientro").val(),
            rientro: $("#rientro").prop('checked') ? 1 : 0,
            id_studente:$('#hidden_studente_id').val()
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
        });
} else {
    // Solo se è nuova aggiunta, azzeriamo ma poi impostiamo automaticamente la data
    if ($("#ora_uscita").length) $("#ora_uscita").val("");
    if ($("#rientro").length) $("#rientro").prop('checked', false);
    if ($("#motivo").length) $("#motivo").val("");
    if ($("#ora_rientro").length) $("#ora_rientro").val("");

    // Imposta la data automatica SOLO per nuovo permesso
    impostaDataPermesso();

    $('#btn-save').show();
}

    $("#permesso_modal").modal("show");

    $("#_error-permesso-part").hide();
}

$(document).ready(function () {
    permessiReadRecords();

    $("#studente_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        $('#hidden_studente_id').val(this.value);
        permessiReadRecords();
    });
    
});
