/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var soloNonFirmati = 1;
var soloCorsiDiOggi = 1;

$('#soloFirmatiCheckBox').change(function() {
    // this si riferisce al checkbox 
    if (this.checked) {
		soloNonFirmati = 0;
		corsoDiRecuperoReadRecords();
    } else {
		soloNonFirmati = 1;
		corsoDiRecuperoReadRecords();
    }
});

$('#soloOggiCheckBox').change(function() {
    // this si riferisce al checkbox 
    if (this.checked) {
		soloCorsiDiOggi = 1;
		corsoDiRecuperoReadRecords();
    } else {
		soloCorsiDiOggi = 0;
		corsoDiRecuperoReadRecords();
    }
});

// Read records
function corsoDiRecuperoReadRecords() {
	$.get("corsoDiRecuperoReadRecords.php?soloNonFirmati=" + soloNonFirmati + "&soloCorsiDiOggi=" + soloCorsiDiOggi, {}, function (data, status) {
		$(".records_content").html(data);
	});
}

function firma(lezione_corso_di_recupero_id, aggiungi_ore = 2) {
// registra la firma per questo id
    $.post("corsoDiRecuperoFirmaRegistro.php", {
            lezione_corso_di_recupero_id: lezione_corso_di_recupero_id
        },
        function (data, status) {
			corsoDiRecuperoReadRecords();
        }
    );
}

function togliFirma(lezione_corso_di_recupero_id) {
	// toglie le due ore
	firma(lezione_corso_di_recupero_id, -2);
}

function lezioneCorsoDiRecuperoGetDetails(id) {
	$("#hidden_lezione_corso_di_recupero_id").val(id);
	$.post("corsoDiRecuperoReadDetails.php", {
			id: id
		},
		function (dati, status) {
//			console.log(dati);
			var lezione = JSON.parse(dati);
//			console.log(lezione);
			// lezione 0 contiene i valori comuni
			$("#update_argomento").val(lezione[0].lezione_corso_di_recupero_argomento);
			$("#update_argomento").prop('defaultValue', lezione[0].lezione_corso_di_recupero_argomento);
			$("#update_note").val(lezione[0].lezione_corso_di_recupero_note);
			$("#update_note").prop('defaultValue', lezione[0].lezione_corso_di_recupero_note);

			// svuota il tbody della tabella studenti;
			$('#update_studenti_table tbody').empty();
			var markup = '';
			// cicla su tutti gli studenti
			lezione.forEach(function(element) {
				markup = markup + 
						"<tr>" +
						"<td>" + element.studente_partecipa_lezione_corso_di_recupero_id + "</td>" +
						"<td>" + element.studente_per_corso_di_recupero_cognome + "</td>" +
						"<td>" + element.studente_per_corso_di_recupero_nome + "</td>" +
						"<td>" + element.studente_per_corso_di_recupero_classe + "</td>" +
						"<td style=\"text-align: center; vertical-align: middle;\">" +
							"<input type=\"checkbox\" name=\"query_myTextEditBox\"" +
							((element.studente_partecipa_lezione_corso_di_recupero_ha_partecipato == 0) ? "" : " checked" ) +
						"></td>" +
				"</tr>";
			});
			$('#update_studenti_table > tbody:last-child').append(markup);
			$('#update_studenti_table td:nth-child(1),th:nth-child(1)').hide(); // nasconde la prima colonna con l'id
		}
    );

	$("#update_lezione_corso_di_recupero_modal").modal("show");
}

// Update details
function lezioneCorsoDiRecuperoUpdateDetails() {
    // get values
    var argomento = $("#update_argomento").val();
	var argomentoOriginal = $("#update_argomento").prop('defaultValue');
	var argomentoChanged = !(argomento === argomentoOriginal);
    var note = $("#update_note").val();
	var noteOriginal = $("#update_note").prop('defaultValue');
	var noteChanged = !(note === noteOriginal);
//	console.log("argomento=" + argomento);
//	console.log("argomentoOriginal=" + argomentoOriginal);
//	console.log("argomentoChanged=" + argomentoChanged);
//	console.log("note=" + note);
//	console.log("noteOriginal=" + noteOriginal);
//	console.log("noteChanged=" + noteChanged);
	var counter = 0;
	var studentiDaModificareIdList = [];
	$('#update_studenti_table tbody tr').each(function() {
		var row = $(this);
		var presenteCheckbox = row.find('input[type="checkbox"]');
		var presenteOriginal = presenteCheckbox.prop('defaultChecked');
		var presenteCorrente = presenteCheckbox.prop('checked');
		var id = row.children().eq(0).text();
//		console.log('id=' + id + ' checked=' + presenteCorrente + ' original=' + presenteOriginal);
		if (presenteCorrente != presenteOriginal) {
			studentiDaModificareIdList.push(id);
		}
		++counter;
	});

    // get hidden field value
    var lezione_corso_di_recupero_id = $("#hidden_lezione_corso_di_recupero_id").val();
//	console.log('lezione_corso_di_recupero_id=' + lezione_corso_di_recupero_id);
//	console.log('argomento=' + argomento);
//	console.log('note=' + note);

    // Update the details: use ajax to submit the request to the server
	$.post("corsoDiRecuperoUpdateDetails.php", {
			lezione_corso_di_recupero_id: lezione_corso_di_recupero_id,
            argomento: argomento,
			argomentoChanged: argomentoChanged,
            note: note,
			noteChanged: noteChanged,
            studentiDaModificareIdList: JSON.stringify(studentiDaModificareIdList)
        },
        function (data, status) {
//			console.log('corsoDiRecuperoUpdateDetails.php result=\n' + data);
            // hide modal popup
            $("#update_lezione_corso_di_recupero_modal").modal("hide");
            // reload records
            corsoDiRecuperoReadRecords();
        }
    );
}

// Read records on page load
$(document).ready(function () {
	$('.panelarrow').on('click', function() {
		alert('ok pa3');
		$(this).toggleClass('glyphicon-resize-full').toggleClass('glyphicon-resize-small');
	});
	$(".firmaBtnClass").click(function(){
		$(this).toggleClass('glyphicon glyphicon-check');
		$(this).text(' Firmato');
		$(this).removeClass("btn-warning");
		$(this).addClass("btn-success");
		$(this).prop('disabled', true);
	});
    corsoDiRecuperoReadRecords();
});
