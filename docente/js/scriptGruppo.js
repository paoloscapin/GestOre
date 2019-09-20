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

function gruppoIncontroReadRecords(group_id) {
	$.get("gruppoIncontroReadRecords.php?gruppo_id=" + group_id, {}, function (data, status) {
		$(".gruppo_records_content_" + group_id).html(data);
	});
}

function gruppoIncontroDelete(id, gruppo_id, data, ora) {
    var conf = confirm("Sei sicuro di volere cancellare l'incontro del " + data + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
				id: id,
				table: 'gruppo_incontro',
				name: "incontro del " + data + " alle " + ora
            },
            function (data, status) {
                gruppoIncontroReadRecords(gruppo_id);
            }
        );
    }
}

function gruppoIncontroGetDetails(id, gruppo_id) {
    $("#hidden_gruppo_id").val(gruppo_id);
    $("#hidden_record_id").val(id);
    if (id > 0) {
        $.post("../common/readRecordDetails.php", {
			id: id,
            table: 'gruppo_incontro'
		},
		function (data, status) {
            var record = JSON.parse(data);
            setDbDateToPickr(data_incontro_pickr, record.data);
			$("#ora_incontro").val(record.ora);
			$("#ordine_del_giorno").val(record.ordine_del_giorno);
		});
    } else {
        data_incontro_pickr.setDate(Date.today().toString('d/M/yyyy'));
        $("#ora_incontro").val("12");
        $("#ordine_del_giorno").val("");
    }
	$("#update_modal").modal("show");
}

function gruppoIncontroSave() {
    $.post("gruppoIncontroSave.php", {
        id: $("#hidden_record_id").val(),
        gruppo_id: $("#hidden_gruppo_id").val(),
        data: getDbDateFromPickrId("#data_incontro"),
        ora: $("#ora_incontro").val(),
        ordine_del_giorno: $("#ordine_del_giorno").val()
    },
    function (data, status) {
        $("#update_modal").modal("hide");
        gruppoIncontroReadRecords($("#hidden_gruppo_id").val());
    });
}

$(document).ready(function () {
    data_incontro_pickr = flatpickr("#data_incontro", {
		locale: {
			firstDayOfWeek: 1
		},
		dateFormat: 'j/n/Y'
	});

	ora_incontro_pickr = flatpickr("#ora_incontro", {
	    enableTime: true,
	    noCalendar: true,
	    dateFormat: "H:i",
	    time_24hr: true,
	    static: true
	});

	flatpickr.localize(flatpickr.l10ns.it);

    gruppoIncontroReadRecords(2);
    gruppoIncontroReadRecords(3);
});