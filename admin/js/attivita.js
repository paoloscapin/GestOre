/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function attivitaReadRecords() {
	$.get("attivitaReadRecords.php", {}, function (data, status) {
		$(".records_content").html(data);
	});
}

function attivitaGetDetails(id) {
    $("#hidden_record_id").val(id);
    if (id > 0) {

        $.post("../common/readRecordDetails.php", {
                id: id,
                table: 'ore_previste_tipo_attivita'
            },
            function (data, status) {
                var record = JSON.parse(data);
                $('#categoria').selectpicker('val', record.categoria);
                $("#nome").val(record.nome);
                $("#ore").val(record.ore);
                $("#ore_max").val(record.ore_max);
                $('#check_valido').bootstrapToggle(record.valido == 1? 'on' : 'off');
                $('#check_previsto_da_docente').bootstrapToggle(record.previsto_da_docente == 1? 'on' : 'off');
                $('#check_inserito_da_docente').bootstrapToggle(record.inserito_da_docente == 1? 'on' : 'off');
                $('#check_da_rendicontare').bootstrapToggle(record.da_rendicontare == 1? 'on' : 'off');
            }
        );
    } else {
        $("#categoria").selectpicker('val', 0);
        $("#nome").val("");
        $("#ore").val("");
        $("#ore_max").val("");
        $('#check_valido').bootstrapToggle('on');
        $('#check_previsto_da_docente').bootstrapToggle('on');
        $('#check_inserito_da_docente').bootstrapToggle('on');
        $('#check_da_rendicontare').bootstrapToggle('on');
    }
	$("#record_modal").modal("show");
}

function attivitaSave() {
    $.post("attivitaSave.php", {
        id: $("#hidden_record_id").val(),
        categoria: $("#categoria").val(),
        nome: $("#nome").val(),
        ore: $("#ore").val(),
        ore_max: $("#ore_max").val(),
        valido: $("#check_valido").is(':checked')? 1: 0,
        previsto_da_docente: $("#check_previsto_da_docente").is(':checked')? 1: 0,
        inserito_da_docente: $("#check_inserito_da_docente").is(':checked')? 1: 0,
        da_rendicontare: $("#check_da_rendicontare").is(':checked')? 1: 0
    },
    function (data, status) {
        $("#record_modal").modal("hide");
        attivitaReadRecords();
        $("#categoria").val("");
        $("#nome").val("");
        $("#ore").val("");
        $("#ore_max").val("");
        $('#check_valido').bootstrapToggle('on');
        $('#check_previsto_da_docente').bootstrapToggle('on');
        $('#check_inserito_da_docente').bootstrapToggle('on');
        $('#check_da_rendicontare').bootstrapToggle('on');
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

$(document).ready(function () {
    attivitaReadRecords();
});