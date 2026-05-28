/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function materiaReadRecords() {
	$.get("materiaReadRecords.php", {}, function (data, status) {
		$(".records_content").html(data);
	});
}

function materiaDelete(id, nome) {
    var conf = confirm("Sei sicuro di volere cancellare la materia " + nome + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
				id: id,
				table: 'materia',
				name: "materia " + nome
            },
            function (data, status) {
                materiaReadRecords();
            }
        );
    }
}

function materiaGetDetails(id) {
    $("#hidden_record_id").val(id);
    if (id > 0) {
        $.post("../common/readRecordDetails.php", {
			id: id,
            table: 'materia'
		},
		function (data, status) {
			var record = JSON.parse(data);
			$("#nome").val(record.nome);
			$("#codice").val(record.codice);
			$("#id_dipartimento").val(record.id_dipartimento || 0);
		});
    } else {
        $("#nome").val("");
        $("#codice").val("");
        $("#id_dipartimento").val(0);
    }
	$("#update_modal").modal("show");
}

function materiaSave() {
    $.post("materiaSave.php", {
        id: $("#hidden_record_id").val(),
        nome: $("#nome").val(),
        codice: $("#codice").val(),
        id_dipartimento: $("#id_dipartimento").length ? $("#id_dipartimento").val() : 0
    },
    function (data, status) {
        $("#update_modal").modal("hide");
        materiaReadRecords();
    });
}

$(document).ready(function () {
    materiaReadRecords();
});
