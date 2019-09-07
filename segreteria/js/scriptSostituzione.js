/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var soloOggi=1;

function getDbDateFromPickrId(pickrId) {
	var data_str = $(pickrId).val();
	var data_date = Date.parseExact(data_str, 'd/M/yyyy');
	return data_date.toString('yyyy-MM-dd');
}

$('#soloOggiCheckBox').change(function() {
    // this si riferisce al checkbox 
    if (this.checked) {
		soloOggi = 1;
    } else {
		soloOggi = 0;
    }
    sostituzione_docenteReadRecords();
});

function sostituzione_docenteReadRecords() {
	$.get("sostituzioneReadRecords.php?soloOggi=" + soloOggi, {}, function (data, status) {
		$(".records_content").html(data);
	});
}

function sostituzione_docenteDelete(id, cognome, nome) {
    var conf = confirm("Sei sicuro di volere cancellare la sostituzione del docente " + cognome + " " + nome + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
				id: id,
				table: 'sostituzione_docente',
				name: "sostituzione del docente " + cognome + " " + nome
            },
            function (data, status) {
                sostituzione_docenteReadRecords();
            }
        );
    }
}

function openModal() {
	$("#add_new_record_modal").modal("show");
    dataSostituzione_pickr.setDate(Date.today().toString('d/M/yyyy'));
}

$(document).keypress(function(e) {
    if ( event.which == 43 ) {
        openModal();
        $('#docente_sostituzione').data('selectpicker').$searchbox.focus();
     }
});

function sostituzione_docenteAddRecord() {
    $.post("sostituzioneAddRecord.php", {
        docente_id: $("#docente_sostituzione").val(),
        dataSostituzione: getDbDateFromPickrId("#dataSostituzione")
    }, function (data, status) {
        $("#add_new_record_modal").modal("hide");
        sostituzione_docenteReadRecords();
    });
}

$(document).ready(function () {
    sostituzione_docenteReadRecords();
	dataSostituzione_pickr = flatpickr("#dataSostituzione", {
		locale: {
			firstDayOfWeek: 1
		},
		dateFormat: 'j/n/Y'
	});

	flatpickr.localize(flatpickr.l10ns.it);
});
