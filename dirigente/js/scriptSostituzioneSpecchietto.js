/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var viewDate = Date.today();

function backOneDay() {
	viewDate = viewDate.add(-1).days();
	sostituzioneSpecchiettoReadRecords();
}

function forwardOneDay() {
	viewDate = viewDate.add(1).days();
	sostituzioneSpecchiettoReadRecords();
}

function moveToToday() {
	viewDate = Date.today();
	sostituzioneSpecchiettoReadRecords();
}

function moveToTomorrow() {
	viewDate = Date.parse('tomorrow');
	sostituzioneSpecchiettoReadRecords();
}

function moveToDate(datestr) {
	viewDate = Date.parseExact(datestr, 'd/M/yyyy');
	sostituzioneSpecchiettoReadRecords();
}

// Read records
function sostituzioneSpecchiettoReadRecords() {
	$("#dataVisualizzazione").val(viewDate.toString('d/M/yyyy'));
	dataVisualizzazione_pickr.setDate(viewDate);

	$.get("sostituzioneSpecchiettoReadRecords.php?data=" + viewDate.toString('yyyy-MM-dd'), {}, function (data, status) {
		$(".records_content").html(data);

		$('#sostituzione_table td:nth-child(1),th:nth-child(1)').hide();

		$('#sostituzione_table :checkbox').change(function() {
			var sostituzione_id = $('td:first', $(this).parents('tr')).text();
			var effettuata = true;
			if(this.checked != true) {
				effettuata = false;
			}
			sostituzioneRegistraEffettuata(sostituzione_id, effettuata);
		});
	});
}

function sostituzioneRegistraEffettuata(sostituzione_id, effettuata) {
	$.post("sostituzioneRegistraEffettuata.php", {
			sostituzione_id: sostituzione_id,
			effettuata: effettuata
		},
		function (data, status) {
			sostituzioneSpecchiettoReadRecords();
		}
	);
}

function sostituzioneNuova() {
	data_sostituzione_pickr.setDate(Date.today().toString('d/M/yyyy'));
	// Open modal popup
	$("#add_new_record_modal").modal("show");
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
        sostituzioneSpecchiettoReadRecords();

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


// Delete records
function sostituzioneDelete(id, docente_incaricato_cognome, docente_assente_cognome) {
    var conf = confirm("Sei sicuro di volere cancellare la sostituzione\n" + docente_incaricato_cognome + " sostituisce " + docente_assente_cognome + " ?");
    if (conf == true) {
        $.post("sostituzioneDelete.php", {
                id: id
            },
            function (data, status) {
            	sostituzioneSpecchiettoReadRecords();
            }
        );
    }
}

// Get details for update
function sostituzioneGetDetails(id) {
	// Add record ID to the hidden field for future usage
	$("#hidden_sostituzione_id").val(id);
	$.post("sostituzioneReadDetails.php", {
			id: id
		},
		function (dati, status) {
			// PARSE json data
			var sostituzione = JSON.parse(dati);
			// setting existing values to the modal popup fields
			var data_sostituzione_str = sostituzione.data;
			var data_sostituzione = Date.parseExact(data_sostituzione_str, 'yyyy-MM-dd');
			$("#update_data_sostituzione").val(data_sostituzione.toString('d/M/yyyy'));
			update_data_sostituzione_pickr.setDate(data_sostituzione);
			$('#update_ora_insegnamento').selectpicker('val', sostituzione.ora_insegnamento_id);
			$('#update_docente_incaricato').selectpicker('val', sostituzione.docente_incaricato_id);
			$('#update_classe').selectpicker('val', sostituzione.classe_id);
			$('#update_aula').selectpicker('val', sostituzione.aula_id);
			$('#update_docente_assente').selectpicker('val', sostituzione.docente_assente_id);
			$('#update_tipo_sostituzione').selectpicker('val', sostituzione.tipo_sostituzione_id);
		}
    );

	// Open modal popup
	$("#update_record_modal").modal("show");
}

// Update details
function sostituzioneUpdateDetails() {
    // get values
    var data_sostituzione_str = $("#update_data_sostituzione").val();
	var data_sostituzione = Date.parseExact(data_sostituzione_str, 'd/M/yyyy');
    var ora_insegnamento_id = $("#update_ora_insegnamento").val();
    var docente_incaricato_id = $("#update_docente_incaricato").val();
    var classe_id = $("#update_classe").val();
    var aula_id = $("#update_aula").val();
    var docente_assente_id = $("#update_docente_assente").val();
    var tipo_sostituzione_id = $("#update_tipo_sostituzione").val();

    var data = $("#update_data").val();
    var numero_ore = $("#update_numero_ore").val();
    var studenti = $("#update_studenti").val();
    var tipo_intervento_altro_descrizione = $("#update_tipo_intervento_altro_descrizione").val();
    var materia_id = $("#update_materia").val();
    var tipo_intervento_didattico_id = $("#update_tipo_intervento").val();

    // get hidden field value
    var sostituzione_id = $("#hidden_sostituzione_id").val();

    // Update the details: use ajax to submit the request to the server
    $.post("sostituzioneUpdateDetails.php", {
            sostituzione_id: sostituzione_id,
			data: data_sostituzione.toString('yyyy-MM-dd'),
			ora_insegnamento_id: ora_insegnamento_id,
			docente_incaricato_id: docente_incaricato_id,
			classe_id: classe_id,
			aula_id: aula_id,
			docente_assente_id: docente_assente_id,
			tipo_sostituzione_id: tipo_sostituzione_id
        },
        function (data, status) {
            // hide modal popup
            $("#update_record_modal").modal("hide");
            // reload records
            sostituzioneSpecchiettoReadRecords();
        }
    );
}

var data_sostituzione_pickr;
var update_data_sostituzione_pickr;
var dataVisualizzazione_pickr;

// Read records on page load
$(document).ready(function () {
	data_sostituzione_pickr = flatpickr("#data_sostituzione", {
		locale: {
					firstDayOfWeek: 1
				},
		dateFormat: 'j/n/Y'
	});
	update_data_sostituzione_pickr = flatpickr("#update_data_sostituzione", {
		locale: {
					firstDayOfWeek: 1
				},
		dateFormat: 'j/n/Y'
	});
	dataVisualizzazione_pickr = flatpickr("#dataVisualizzazione", {
		locale: {
					firstDayOfWeek: 1
				},
		dateFormat: 'j/n/Y',
		onChange: function(dateObj, dateStr) {
			moveToDate(dateStr);
		}
	});
	$("#dataVisualizzazione").val(viewDate.toString('d/M/yyyy'));
	flatpickr.localize(flatpickr.l10ns.it);

    sostituzioneSpecchiettoReadRecords();
});
