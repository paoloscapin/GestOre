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

var studente_filtro_id=1;

function permessiReadRecords() {
        var endpoint = (device === "mobile") 
        ? "permessiReadRecords_mobile.php" 
        : "permessiReadRecords.php";

	$.get(endpoint+"?studente_filtro_id=" + studente_filtro_id, {}, function (data, status) {
		$(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            trigger: 'hover',
            container: 'body'
        });
	});
}

function permessiDelete(id) {
    var conf = confirm("Sei sicuro di volere cancellare il permesso ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
            id: id,
            table: 'studente',
            name: "cognome " + cognome
        },
            function (data, status) {
                studenteReadRecords();
            }
        );
    }
}

function permessiSave() {
    if ($("#classe_filtro").val() <= 0) {
        $("#_error-classe").text("Devi selezionare una classe per lo studente.");
        $("#_error-classe-part").show();
        return;
    }
    rientro = $("#rientro").prop('checked') ? 1 : 0;

    if (rientro == 0 && ($("#hidden_rientro").val() == 1)) {
        var conf = confirm("Sei sicuro di volere disattivare il rientro per lo studente " + $("#cognome").val() + " " + $("#nome").val() + "?");
        if (conf == false) {
            return;
        }
    }
    if (attivo == 1 && ($("#hidden_attivo").val() == 0)) {
        var conf = confirm("Sei sicuro di volere inserire per quest'anno lo studente " + $("#cognome").val() + " " + $("#nome").val() + "?");
        if (conf == false) {
            return;
        }
    }

        $("#_error-classe-part").hide();
        $.post("studenteSave.php", {
            id: $("#hidden_studente_id").val(),
            cognome: $("#cognome").val(),
            nome: $("#nome").val(),
            email: $("#email").val(),
            id_classe: $("#classe_filtro").val(),
            id_anno: $("#hidden_anno_id").val(),
            attivo: $("#attivo").prop('checked') ? 1 : 0,
            era_attivo: $("#hidden_attivo").val()
        }, function (data, status) {
            $("#studente_modal").modal("hide");
            studenteReadRecords();
        });
    }

function permessiGetDetails(permesso_id) {
    $("#hidden_permesso_id").val(permesso_id);

    if (permesso_id > 0) {
        $.post("permessiReadDetails.php", {
            id: permesso_id
        }, function (data, status) {

            var permesso = JSON.parse(data);
            console.log(permesso);
            $("#data").val(permesso.permesso_data);
            $("#ora_uscita").val(permesso.permesso_ora_uscita);
            $("#rientro").val(permesso.permesso_rientro);
            $("#motivo").val(permesso.permesso_motivo);
            $("#ora_rientro").val(permesso.permesso_ora_rientro);
        });
    } else {
        $("#data").val("");
        $("#ora_uscita").val("");
        $("#rientro").val("");
        $("#motivo").val("");
        $("#ora_rientro").val("");
        $('#btn-save').show();
    }

    $("#permesso_modal").modal("show");

    $("#_error-permesso-part").hide();
}

$(document).ready(function () {
    permessiReadRecords();

    $("#studente_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        studente_filtro_id = this.value;
        permessiReadRecords();
    });
    
});
