/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function materiaAddRecord() {
    $.post("materiaAddRecord.php", {
        nome: $("#nome").val(),
        codice: $("#codice").val()
    }, function (data, status) {
        $("#add_record_modal").modal("hide");
        materiaReadRecords();
        $("#nome").val("");
        $("#codice").val("");
    });
}

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
	$("#hidden_materia_id").val(id);
	$.post("materiaReadDetails.php", {
			id: id
		},
		function (data, status) {
			var record = JSON.parse(data);
			$("#update_nome").val(record.nome);
			$("#update_codice").val(record.codice);
		}
    );
	$("#update_record_modal").modal("show");
}

function materiaUpdateDetails() {
    $.post("materiaUpdate.php", {
            id: $("#hidden_materia_id").val(),
            nome: $("#update_nome").val(),
            codice: $("#update_codice").val()
        },
        function (data, status) {
            $("#update_record_modal").modal("hide");
            materiaReadRecords();
        }
    );
}

$(document).ready(function () {
    materiaReadRecords();
});