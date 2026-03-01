/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var ore_richieste = 0;
var diaria = 0;

function setDbDateToPickr(pickr, data_str) {
	var data = Date.parseExact(data_str, 'yyyy-MM-dd');
	pickr.setDate(data);
}

function getDbDateFromPickrId(pickrId) {
	var data_str = $(pickrId).val();
	var data_date = Date.parseExact(data_str, 'd/M/yyyy');
	return data_date.toString('yyyy-MM-dd');
}

function viaggioReadRecords() {
	$.get("viaggioReadRecords.php", {}, function (data, status) {
		$(".records_content").html(data);
	});
}

function viaggioDelete(id, data_partenza, destinazione) {
    var conf = confirm("Sei sicuro di volere cancellare il viaggio del " + data_partenza + " a " + destinazione + " ?");
    if (conf == true) {
        $.post("viaggioDelete.php", {
                id: id
            },
            function (data, status) {
                viaggioReadRecords();
            }
        );
    }
}

function viaggioGetDetails(viaggio_id) {
	$("#hidden_viaggio_id").val(viaggio_id);
    if (viaggio_id > 0) {
		$.post("viaggioReadDetails.php", {
				id: viaggio_id
			},
			function (data, status) {
				// PARSE json data
				var viaggio = JSON.parse(data);
				// console.log(viaggio);
				// setting existing values to the modal popup fields
				$("#update_protocollo").val(viaggio.protocollo);
				$('#update_tipo_viaggio').selectpicker('val', viaggio.tipo_viaggio);
				setDbDateToPickr(update_data_nomina_pickr, viaggio.data_nomina);
				setDbDateToPickr(update_data_partenza_pickr, viaggio.data_partenza);
				setDbDateToPickr(update_data_rientro_pickr, viaggio.data_rientro);
				$('#update_docente_incaricato').selectpicker('val', viaggio.docente_id);
				$("#update_classe").val(viaggio.classe);
				$("#update_note").val(viaggio.note);
				$("#update_destinazione").val(viaggio.destinazione);
				$("#update_ora_partenza").val(viaggio.ora_partenza);
				$("#update_ora_rientro").val(viaggio.ora_rientro);
				$('#update_stato').selectpicker('val', viaggio.stato);
			}
		);
    } else {
		update_data_nomina_pickr.setDate(Date.today().toString('d/M/yyyy'));
		update_data_partenza_pickr.setDate(Date.today().toString('d/M/yyyy'));
		update_data_rientro_pickr.setDate(Date.today().toString('d/M/yyyy'));
		$("#update_protocollo").val('');
        $('#update_tipo_viaggio').val("0");
		$('#update_tipo_viaggio').selectpicker('refresh');
        $('#update_docente_incaricato').val("0");
		$('#update_docente_incaricato').selectpicker('refresh');
		$("#update_classe").val('');
		$("#update_note").val('');
		$("#update_destinazione").val('');
		$("#update_ora_partenza").val('12');
		$("#update_ora_rientro").val('12');
        $('#update_stato').val("assegnato");
		$('#update_stato').selectpicker('refresh');
	}
	$("#viaggio_modal").modal("show");
}

function viaggioSave() {
	$.post("viaggioSave.php", {
		viaggio_id: $("#hidden_viaggio_id").val(),
        protocollo: $("#update_protocollo").val(),
        tipo_viaggio: $("#update_tipo_viaggio").val(),
        data_nomina: getDbDateFromPickrId("#update_data_nomina"),
        data_partenza: getDbDateFromPickrId("#update_data_partenza"),
        data_rientro: getDbDateFromPickrId("#update_data_rientro"),
        docente_incaricato_id: $("#update_docente_incaricato").val(),
        destinazione: $("#update_destinazione").val(),
        classe: $("#update_classe").val(),
        note: $("#update_note").val(),
        ora_partenza: $("#update_ora_partenza").val(),
		ora_rientro: $("#update_ora_rientro").val(),
		stato: $("#update_stato").val()
		},
		function (data, status) {
			$("#viaggio_modal").modal("hide");
            viaggioReadRecords();		}
    );
}

// produci e stampa nomina
function viaggioNominaStampa(id) {
	var url = 'viaggioNomina.php?viaggio_id=' + id;
	window.open(url, "_blank");
}

function viaggioNominaEmail(viaggio_id) {
	var url = 'viaggioNomina.php?viaggio_id=' + viaggio_id + '&email=true';
	window.open(url, "_blank");
}

function viaggioProtocolla(viaggio_id) {
	$.post("viaggioProtocolla.php", {
		viaggio_id: viaggio_id
		},
		function (data, status) {
			alert(data);
		}
    );
}

function viaggioCalcola() {
	var importo_senza_pernottamento = $("#hidden_chiusura_viaggio_importo_senza_pernottamento").val();
	var importo_con_pernottamento = $("#hidden_chiusura_viaggio_importo_con_pernottamento").val();
	var giorni_senza_pernottamento = $("#chiusura_senza_pernottamento").val();
	var giorni_con_pernottamento = $("#chiusura_con_pernottamento").val();
	console.log('importo_senza_pernottamento='+importo_senza_pernottamento);

	var totale = giorni_senza_pernottamento * importo_senza_pernottamento + giorni_con_pernottamento * importo_con_pernottamento;

	$("#chiusura_diaria").val(totale);
}

function viaggioChiusura(id) {
	$("#hidden_chiusura_viaggio_id").val(id);
	//console.log("chiusura id="+id);
	$.post("viaggioReadDetails.php", {
			id: id
		},
		function (data, status) {
			var viaggio = JSON.parse(data);
			// memorizza docente id e cognome e nome da usare poi
			// console.log(spesaViaggioArray);
			$("#hidden_chiusura_viaggio_docente_id").val(viaggio.docente_id);
			$("#hidden_chiusura_viaggio_docente_cognome_e_nome").val(viaggio.cognome + " " + viaggio.nome);
			$("#chiusura_label_docente").text(viaggio.cognome + " " + viaggio.nome);

			var stato = viaggio.stato;
			if (stato != "chiuso" && stato != "effettuato") {
				alert('il viaggio risulta in stato ' + stato + ' e non può essere chiuso');
				return;
			}

			$("#chiusura_destinazione").text(viaggio.destinazione);
			$("#chiusura_classe").text(viaggio.classe);

			var data_nomina_str = viaggio.data_nomina;
			var data_partenza_str = viaggio.data_partenza;
			var data_rientro_str = viaggio.data_rientro;
			var data_nomina = Date.parseExact(data_nomina_str, 'yyyy-MM-dd');
			var data_partenza = Date.parseExact(data_partenza_str, 'yyyy-MM-dd');
			var data_rientro = Date.parseExact(data_rientro_str, 'yyyy-MM-dd');
			$("#chiusura_data_partenza").text(data_partenza.toLocaleDateString("it-IT", { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }));
			$("#chiusura_data_rientro").text(data_rientro.toLocaleDateString("it-IT", { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }));
			$("#chiusura_ora_partenza").text(viaggio.ora_partenza);
			$("#chiusura_ora_rientro").text(viaggio.ora_rientro);

			$("#chiusura_label_data").text(data_partenza.toLocaleDateString("it-IT", { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }));

			// controlla che sia richiesta la diaria o le ore
			ore_richieste = viaggio.ore_richieste;
			diaria = viaggio.richiesta_fuis;

			$("#chiusura_ore_richieste").text(viaggio.ore_richieste);
			$("#chiusura_richiesta_fuis").prop('checked', diaria == true);

			$("#chiusura_ore").val(viaggio.ore);
			$("#chiusura_diaria").val(viaggio.diaria);

			$("#chiusura_viaggio_modal").modal("show");
		}
    );
}

function viaggioChiudi() {
	var viaggio_id = $("#hidden_chiusura_viaggio_id").val();
	var numero_ore = $("#chiusura_ore").val();
	var chiusura_diaria = $("#chiusura_diaria").val();
	// problemi con la virgola: se bisogna trasformo in un punto.
	var importo_diaria = parseFloat(chiusura_diaria.replace(',', '.'));

	$.post("viaggioChiudi.php", {
		viaggio_id: viaggio_id,
		importo_diaria: importo_diaria,
		numero_ore: numero_ore,
		docente_id: $("#hidden_chiusura_viaggio_docente_id").val(),
		docente_cognome_e_nome: $("#hidden_chiusura_viaggio_docente_cognome_e_nome").val()
        },
        function (data, status) {
			$("#chiusura_viaggio_modal").modal("hide");
			diaria = false;
			ore_richieste = 0;
        	viaggioReadRecords();
        }
    );
}

function viaggioDiariaLiquida(id) {
	$.post("viaggioReadDetails.php", {
			id: id
		},
		function (data, status) {
			var viaggio = JSON.parse(data);

			var docente = viaggio.nome + ' ' + viaggio.cognome;
			var destinazione = viaggio.destinazione;
			var importo = viaggio.diaria;
			bootbox.confirm({
				title: "Liquidazione diaria <strong>" + importo + '€</strong>',
				message: 'Effettuare la liquidazione di <strong>' + importo +' €</strong> a <strong>' + docente + '</strong></br>per il viaggio a ' + destinazione,
				buttons: {
					confirm: {
						label: 'Va Bene',
						className: 'btn-lima4'
					},
					cancel: {
						label: 'Annulla'
					}
				},
				callback: function (result) {
					if (result === true) {

						$.post("../common/recordUpdate.php", {
							table: 'viaggio',
							id: id,
							nome: 'rimborsato',
							valore: 1
						}, function (data, status) {
							$.notify({
									icon: 'glyphicon glyphicon-ok',
									title: "Liquidazione diaria " + importo + '€',
									message: 'Liquidato importo di <strong>' + importo +' €</strong> a <strong>' + docente + '</strong></br>per il viaggio a ' + destinazione 
								},{ placement: { from: "top", align: "center" }, delay: 3000, timer: 1000, mouse_over: "pause", type: 'success' });
							viaggioReadRecords();
						});
					} else {
						bootbox.alert('Liquidazione diaria: operazione cancellata');
					}
				}
			});
		}
    );
}

// Read records on page load
$(document).ready(function () {
	update_data_nomina_pickr = flatpickr("#update_data_nomina", {
		locale: {
			firstDayOfWeek: 1
		},
		dateFormat: 'j/n/Y'
	});
	update_data_partenza_pickr = flatpickr("#update_data_partenza", {
		locale: {
			firstDayOfWeek: 1
		},
		dateFormat: 'j/n/Y'
	});
	update_data_rientro_pickr = flatpickr("#update_data_rientro", {
		locale: {
			firstDayOfWeek: 1
		},
		dateFormat: 'j/n/Y'
	});

	update_ora_partenza_pickr = flatpickr("#update_ora_partenza", {
	    enableTime: true,
	    noCalendar: true,
	    dateFormat: "H:i",
	    time_24hr: true,
	    static: true
	});

	update_ora_rientro_pickr = flatpickr("#update_ora_rientro", {
	    enableTime: true,
	    noCalendar: true,
	    dateFormat: "H:i",
	    time_24hr: true,
	    static: true
	});

	flatpickr.localize(flatpickr.l10ns.it);

    viaggioReadRecords();
});