/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// Read records
function sostituzioneSituazioneDocenteReadRecords() {
	$.get("sostituzioneSituazioneDocenteReadRecords.php", {}, function (data, status) {
		$(".records_content").html(data);
	});
}

// Add record
function sostituzioneAddRecord() {
    // get values
    var data_sostituzione_str = $("#data_sostituzione").val();
	var data_sostituzione = Date.parseExact(data_sostituzione_str, 'd/M/yyyy');
    var ora_insegnamento_id = $("#ora_insegnamento").val();
    var docente_incaricato_id = $("#docente_incaricato").val();
    var classe_id = $("#classe").val();
    var aula_id = $("#aula").val();
    var docente_assente_id = $("#docente_assente").val();
    var tipo_sostituzione_id = $("#tipo_sostituzione").val();
    // Add record
    $.post("sostituzioneAddRecord.php", {
        data: data_sostituzione.toString('yyyy-MM-dd'),
        ora_insegnamento_id: ora_insegnamento_id,
        docente_incaricato_id: docente_incaricato_id,
        classe_id: classe_id,
        aula_id: aula_id,
        docente_assente_id: docente_assente_id,
        tipo_sostituzione_id: tipo_sostituzione_id
    }, function (data, status) {
        // close the popup
        $("#add_new_record_modal").modal("hide");

        // read records again
        sostituzioneReadRecords();

        // clear fields from the popup
// 		$("#data_sostituzione").val("");
        $("ora_insegnamento").val(0);
        $("docente_incaricato").val(0);
        $("classe").val(0);
        $("aula").val(0);
        $("tipo_sostituzione").val(0);
//        $("docente_assente").val(0);
    });
}

// Get details for update
function sostituzioneSituazioneDocenteGetDetails(docente_id,cognome,nome,sostituzioneSituazioneDocenteId,giorno_settimana_id,ora_insegnamento_id,ore_da_fare,ore_mancanti) {

	$("#hidden_docente_id").val(docente_id);
	$("#hidden_sostituzioneSituazioneDocenteId").val(sostituzioneSituazioneDocenteId);
	
	$("#update_cognome").val(cognome + ' ' + nome);
	$('#update_giorno_settimana').selectpicker('val', giorno_settimana_id);
	$('#update_ora_insegnamento').selectpicker('val', ora_insegnamento_id);
	$("#update_ore_da_fare").val(ore_da_fare);
	$("#update_ore_mancanti").val(ore_mancanti);

	// Open modal popup
	$("#update_record_modal").modal("show");
}

// Update details
function sostituzioneSituazioneDocenteUpdateDetails() {
    // get values
    var ora_insegnamento_id = $("#update_ora_insegnamento").val();
    var giorno_settimana_id = $("#update_giorno_settimana").val();
    var ore_da_fare = $("#update_ore_da_fare").val();

    // get hidden field value
    var docente_incaricato_id = $("#hidden_docente_id").val();
    var sostituzione_situazione_docente_id = $("#hidden_sostituzioneSituazioneDocenteId").val();

    // Update the details: use ajax to submit the request to the server
    $.post("sostituzioneSituazioneDocenteUpdateDetails.php", {
		docente_incaricato_id: docente_incaricato_id,
		sostituzione_situazione_docente_id: sostituzione_situazione_docente_id,
		ora_insegnamento_id: ora_insegnamento_id,
		giorno_settimana_id: giorno_settimana_id,
		ore_da_fare: ore_da_fare
        },
        function (data, status) {
            // hide modal popup
            $("#update_record_modal").modal("hide");
            // reload records
            sostituzioneSituazioneDocenteReadRecords();
        }
    );
}
//Read records on page load
$(document).ready(function () {
	sostituzioneSituazioneDocenteReadRecords();
});
