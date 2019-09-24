/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function attivitaAddRecord() {
    $.post("attivitaAddRecord.php", {
        categoria: $("#categoria").val(),
        nome: $("#nome").val(),
        ore: $("#ore").val(),
        ore_max: $("#ore_max").val(),
        valido: $("#valido").is(':checked')? 1: 0,
        previsto_da_docente: $("#previsto_da_docente").is(':checked')? 1: 0,
        inserito_da_docente: $("#inserito_da_docente").is(':checked')? 1: 0,
        da_rendicontare: $("#da_rendicontare").is(':checked')? 1: 0
    }, function (data, status) {
        $("#add_record_modal").modal("hide");
        attivitaReadRecords();
        $("#categoria").val("");
        $("#nome").val("");
        $("#ore").val("");
        $("#ore_max").val("");
    });
}

function attivitaReadRecords() {
	$.get("attivitaReadRecords.php", {}, function (data, status) {
		$(".records_content").html(data);
	});
}

function attivitaDelete(id, nome) {
    var conf = confirm("Sei sicuro di volere cancellare la attivita " + nome + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
				id: id,
				table: 'ore_previste_tipo_attivita',
				name: "attivita " + nome
            },
            function (data, status) {
                attivitaReadRecords();
            }
        );
    }
}

function attivitaGetDetails(id) {
	$("#hidden_attivita_id").val(id);
	$.post("attivitaReadDetails.php", {
			id: id
		},
		function (data, status) {
			var record = JSON.parse(data);
			$("#update_categoria").val(record.categoria);
			$("#update_nome").val(record.nome);
			$("#update_ore").val(record.ore);
			$("#update_ore_max").val(record.ore_max);
			$('#update_valido').bootstrapToggle(record.valido == 1? 'on' : 'off');
			$('#update_previsto_da_docente').bootstrapToggle(record.previsto_da_docente == 1? 'on' : 'off');
			$('#update_inserito_da_docente').bootstrapToggle(record.inserito_da_docente == 1? 'on' : 'off');
			$('#update_da_rendicontare').bootstrapToggle(record.da_rendicontare == 1? 'on' : 'off');
		}
    );
	$("#update_record_modal").modal("show");
}

function attivitaUpdateDetails() {
    $.post("attivitaUpdate.php", {
            id: $("#hidden_attivita_id").val(),
            categoria: $("#update_categoria").val(),
            nome: $("#update_nome").val(),
            ore: $("#update_ore").val(),
            ore_max: $("#update_ore_max").val(),
            valido: $("#update_valido").is(':checked')? 1: 0,
            previsto_da_docente: $("#update_previsto_da_docente").is(':checked')? 1: 0,
            inserito_da_docente: $("#update_inserito_da_docente").is(':checked')? 1: 0,
            da_rendicontare: $("#update_da_rendicontare").is(':checked')? 1: 0
        },
        function (data, status) {
            $("#update_record_modal").modal("hide");
            attivitaReadRecords();
        }
    );
}

$(document).ready(function () {
    attivitaReadRecords();
});