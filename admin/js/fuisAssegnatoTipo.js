/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var soloAttivi=1;

$('#soloAttiviCheckBox').change(function() {
    // this si riferisce al checkbox
    if (this.checked) {
		soloAttivi = 1;
    } else {
		soloAttivi = 0;
    }
    fuisAssegnatoTipoReadRecords();
});

function fuisAssegnatoTipoReadRecords() {
	$.get("fuisAssegnatoTipoReadRecords.php?soloAttivi=" + soloAttivi, {}, function (data, status) {
		$(".records_content").html(data);
	});
}

function fuisAssegnatoTipoDelete(id, nome) {
    var conf = confirm("Sei sicuro di volere cancellare l'elemento " + nome + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
				id: id,
				table: 'fuis_assegnato_tipo',
				name: "fuis_assegnato_tipo " + nome
            },
            function (data, status) {
                fuisAssegnatoTipoReadRecords();
            }
        );
    }
}

function fuisAssegnatoTipoGetDetails(id) {
    $("#hidden_record_id").val(id);
    if (id > 0) {
        $.post("../common/readRecordDetails.php", {
			id: id,
            table: 'fuis_assegnato_tipo'
		},
		function (data, status) {
			var record = JSON.parse(data);
            console.log(record);
			$("#nome").val(record.nome);
			$("#codice_citrix").val(record.codice_citrix);
            $("#attivo").prop('checked', record.attivo != 0 && record.attivo != null);
		});
    } else {
        $("#nome").val("");
        $("#codice_citrix").val("");
    }
	$("#update_modal").modal("show");
}

function fuisAssegnatoTipoSave() {
    $.post("fuisAssegnatoTipoSave.php", {
        id: $("#hidden_record_id").val(),
        nome: $("#nome").val(),
        codice_citrix: $("#codice_citrix").val(),
        attivo: $("#attivo").is(':checked')? 1: 0
    },
    function (data, status) {
        $("#update_modal").modal("hide");
        fuisAssegnatoTipoReadRecords();
    });
}

$(document).ready(function () {
    fuisAssegnatoTipoReadRecords();
});