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

function corsoDiRecuperoLezioniReadRecords() {
	$.get("corsoDiRecuperoLezioniReadRecords.php?corso_di_recupero_id=" + $("#hidden_corso_di_recupero_id").val(), {}, function (data, status) {
		$(".records_content").html(data);
	});
}

function corsoDiRecuperoLezioniGetDetails(id) {
	$("#hidden_corso_di_recupero_lezioni_id").val(id);

	if (id > 0) {
		$.post("../common/readRecordDetails.php", {
			id: id,
			table: 'lezione_corso_di_recupero'
		},
		function (data, status) {
			var lezione = JSON.parse(data);

            setDbDateToPickr(data_pickr, lezione.data);
			$("#inizia_alle").val(lezione.inizia_alle);
			$("#numero_ore").val(lezione.numero_ore);
		});
    } else {
		data_pickr.setDate(Date.today().toString('d/M/yyyy'));
		$("#inizia_alle").val("");
		$("#numero_ore").val("2");
	}
	$("#corso_di_recupero_lezioni_modal").modal("show");
}

function corsoDiRecuperoLezioniSave() {
    $.post("corsoDiRecuperoLezioniSave.php", {
        id: $("#hidden_corso_di_recupero_lezioni_id").val(),
		corso_di_recupero_id:  $("#hidden_corso_di_recupero_id").val(),
		data: getDbDateFromPickrId("#data"),
        numero_ore: $("#numero_ore").val(),
        inizia_alle: $("#inizia_alle").val()
    }, function (data, status) {
        $("#corso_di_recupero_lezioni_modal").modal("hide");
        corsoDiRecuperoLezioniReadRecords();
    });
}

function corsoDiRecuperoLezioniDelete(id, data) {
    var conf = confirm("Sei sicuro di volere cancellare la lezione del giorno " + data + " ?");
    if (conf == true) {
        $.post("corsoDiRecuperoLezioniDelete.php", {
				id: id,
				data: $(pickrId).val(),
            },
            function (data, status) {
		        corsoDiRecuperoLezioniReadRecords();
            }
        );
    }
}

$(document).ready(function () {
	data_pickr = flatpickr("#data", {
		locale: {
			firstDayOfWeek: 1
		},
		dateFormat: 'j/n/Y'
	});

	corsoDiRecuperoLezioniReadRecords();
});
