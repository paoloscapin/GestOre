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
	$.get("../docente/sportelloReadRecords.php?ancheCancellati=true", {}, function (data, status) {
		$(".records_content").html(data);
	});
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
    $("#hidden_sportello_id").val(sportello_id);
    $.post("../docente/sportelloReadDetails.php", {
        sportello_id: sportello_id
    }, function (data, status) {
        console.log(data);
        var sportello = JSON.parse(data);
        $("#data").val(sportello.sportello_data);
        $("#ora").val(sportello.sportello_ora);
        $("#docente").val(sportello.docente_nome + ' ' + sportello.docente_nome);
        $("#materia").val(sportello.materia_nome);
        $("#numero_ore").val(sportello.sportello_numero_ore);
        $("#luogo").val(sportello.sportello_luogo);
        $("#classe").val(sportello.sportello_classe);
    });
    $("#sportello_modal").modal("show");
}

$(document).ready(function () {
	sportelloReadRecords();
});
