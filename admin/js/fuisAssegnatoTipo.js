/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function fuisAssegnatoTipoReadRecords() {
	$.get("fuisAssegnatoTipoReadRecords.php", {}, function (data, status) {
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
			$("#nome").val(record.nome);
		});
    } else {
        $("#nome").val("");
    }
	$("#update_modal").modal("show");
}

function fuisAssegnatoTipoSave() {
    $.post("fuisAssegnatoTipoSave.php", {
        id: $("#hidden_record_id").val(),
        nome: $("#nome").val()
    },
    function (data, status) {
        $("#update_modal").modal("hide");
        fuisAssegnatoTipoReadRecords();
    });
}

$(document).ready(function () {
    fuisAssegnatoTipoReadRecords();
});