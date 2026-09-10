/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function getDbDateFromPickrId(pickrId) {
	var data_str = $(pickrId).val();
	var data_date = Date.parseExact(data_str, 'd/M/yyyy');
	return data_date.toString('yyyy-MM-dd');
}

function richiestaReadRecords() {
}

function richiestaCompila(id) {
	data_assemblea_pickr.setDate(Date.today().toString('d/M/yyyy'));
    $('#ordine_del_giorno').summernote('code', '<p><b>Ordine del giorno</b></p><ul><li><br></li></ul>');
    $('#richiedente').val($("#hidden_richiedente").val());
    $('#classe').val($("#hidden_classe").val());
	$("#richiesta_modal").modal("show");
	$("#_error-part").hide();
}

function richiestaInoltra() {
	if ($("#docente_coordinatore").val() <= 0) {
		$("#_error").text("Devi selezionare il docente coordinatore");
		$("#_error-part").show();
		return;
	}
	if ($("#docente_ora").val() <= 0) {
		$("#_error").text("Devi selezionare il docente dell'ora");
		$("#_error-part").show();
		return;
	}

    var ordine_del_giorno = $('#ordine_del_giorno').summernote('code');

	$.post("richiestaAssembleaInoltra.php", {
        data_assemblea: getDbDateFromPickrId("#data_assemblea"),
        ora_assemblea: $("#ora_assemblea").val(),
        docente_coordinatore_id: $("#docente_coordinatore").val(),
        docente_ora_id: $("#docente_ora").val(),
		ordine_del_giorno: $('#ordine_del_giorno').summernote('code')
		},
		function (data, status) {
			$("#richiesta_modal").modal("hide");
            richiestaReadRecords();
    });
}

$(document).ready(function () {
	data_assemblea_pickr = flatpickr("#data_assemblea", {
		locale: {
			firstDayOfWeek: 1
		},
		dateFormat: 'j/n/Y'
	});

	flatpickr.localize(flatpickr.l10ns.it);

    $('.summernote').summernote({
		height: 120,                 // set editor height
		minHeight: null,             // set minimum height of editor
		maxHeight: null,             // set maximum height of editor
		focus: true                  // set focus to editable area after initializing summernote
	  });

    richiestaReadRecords();
});