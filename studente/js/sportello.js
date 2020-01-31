/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function setDbDateToPickr(pickr, data_str) {
	var data = Date.parseExact(data_str, 'yyyy-MM-dd');
	pickr.setDate(data);
}

function getDbDateFromPickrId(pickrId) {
	var data_str = $(pickrId).val();
	var data_date = Date.parseExact(data_str, 'd/M/yyyy');
	return data_date.toString('yyyy-MM-dd');
}

function sportelloReadRecords() {
	$.get("sportelloReadRecords.php?ancheCancellati=true", {}, function (data, status) {
		$(".records_content").html(data);
	});
}

function sportelloDelete(id, nome) {
    var conf = confirm("Sei sicuro di volere cancellare lo sportello " + nome + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
				id: id,
				table: 'sportello',
				name: "sportello" + nome
            },
            function (data, status) {
                sportelloReadRecords();
            }
        );
    }
}

function sportelloNuovo() {
	data_pickr.setDate(Date.today().toString('d/M/yyyy'));
    $("#ora").val("14");
    $('#docente').val("0");
    $('#docente').selectpicker('refresh');
    $('#materia').val("0");
    $('#materia').selectpicker('refresh');
	$("#numero_ore").val("0");
	$("#luogo").val("");
	$("#classe").val("");
	$("#add_new_record_modal").modal("show");
}

function sportelloSave() {
    $.post("sportelloAddRecord.php", {
		data: getDbDateFromPickrId("#data"),
        ora: $("#ora").val(),
        docente_id: $("#docente").val(),
		materia_id: $("#materia").val(),
        numero_ore: $("#numero_ore").val(),
		luogo: $("#commento").val(),
        classe: $("#classe").val()
    }, function (data, status) {
        $("#add_new_record_modal").modal("hide");
        sportelloReadRecords();
    });
}


function sportelloGetDetails(sportello_id) {
    $("#hidden_gruppo_id").val(sportello_id);

    $.post("sportelloReadRecord.php", {
        gruppo_id: gruppo_id
    }, function (data, status) {
//        console.log(data);
        var record = JSON.parse(data);
        var idList = new Array();
        var i;
        for (i = 0; i < record.length; ++i) {
            idList.push(record[i]);
        }
        $("#partecipanti").val(idList).trigger('change');
        $("#partecipanti_modal").modal("show");
    });
}

function gruppoPartecipantiSave() {
//    console.log($('#partecipanti').val());
    $.post("sportelloPartecipantiSave.php", {

        gruppo_id: $("#hidden_sportello_id").val(),
        partecipantiArray: JSON.stringify($('#partecipanti').val())
    }, function (data, status) {
        $("#partecipanti_modal").modal("hide");
    });
}

$(document).ready(function () {

	sportelloReadRecords();

});
