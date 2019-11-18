/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var ancheChiusi = 0;

$('#ancheChiusiCheckBox').change(function() {
    // this si riferisce al checkbox
    if (this.checked) {
		ancheChiusi = 0;
		viaggioReadRecords();
    } else {
		ancheChiusi = 1;
		viaggioReadRecords();
    }
});

// Read records
function viaggioReadRecords() {
	$.get("viaggioReadRecords.php?ancheChiusi=" + ancheChiusi, {}, function (data, status) {
		$(".records_content").html(data);
	});
}

function viaggioAccetta(viaggio_id) {
    $.post("viaggioCambiaStato.php", {
    		viaggio_id: viaggio_id,
    		nuovo_stato: "accettato"
        },
        function (data, status) {
        	viaggioReadRecords();
        }
    );
}

function viaggioInoltra(viaggio_id) {
    var conf = confirm("Sei sicuro di volere inoltrare la richiesta ?");
    if (conf == true) {
        $.post("viaggioCambiaStato.php", {
    		viaggio_id: viaggio_id,
	    		nuovo_stato: "effettuato"
	        },
	        function (data, status) {
	        	viaggioReadRecords();
	        }
	    );
    }

}

// Get details for update
function viaggioGetDetails(id) {
	// console.log('viaggioGetDetails id='+id);
	$("#hidden_viaggio_id").val(id);
	$.post("viaggioReadDetails.php", {
			id: id
		},
		function (dati, status) {
			// PARSE json data
			var spesaViaggioArray = JSON.parse(dati);
			// setting existing values to the modal popup fields
			$("#update_viaggio_protocollo").val(spesaViaggioArray[0].viaggio_protocollo);
			$("#update_viaggio_destinazione").prop('defaultValue', spesaViaggioArray[0].viaggio_destinazione);
			var data_partenza_str = spesaViaggioArray[0].viaggio_data_partenza;
			var data_rientro_str = spesaViaggioArray[0].viaggio_data_rientro;
			var data_partenza = Date.parseExact(data_partenza_str, 'yyyy-MM-dd');
			var data_rientro = Date.parseExact(data_rientro_str, 'yyyy-MM-dd');
			update_viaggio_data_partenza_pickr.setDate(data_partenza);
			update_viaggio_data_rientro_pickr.setDate(data_rientro);

			$("#update_viaggio_ora_partenza").val(spesaViaggioArray[0].viaggio_ora_partenza);
			$("#update_viaggio_ora_rientro").val(spesaViaggioArray[0].viaggio_ora_rientro);
			$("#update_viaggio_ora_partenza").prop('defaultValue', spesaViaggioArray[0].viaggio_ora_partenza);
			$("#update_viaggio_ora_rientro").prop('defaultValue', spesaViaggioArray[0].viaggio_ora_rientro);
			$("#update_viaggio_classe").prop('defaultValue', spesaViaggioArray[0].viaggio_classe);
			$("#update_viaggio_stato").prop('defaultValue', spesaViaggioArray[0].viaggio_stato);
			$("#update_viaggio_ore_richieste").prop('defaultValue', spesaViaggioArray[0].viaggio_ore_richieste);
			$("#update_viaggio_richiesta_fuis").prop('checked', spesaViaggioArray[0].viaggio_richiesta_fuis != 0);

			// svuota il tbody della tabella spese;
			$('#update_viaggio_spese_table tbody').empty();
			var markup = '';
			spesaViaggioArray.forEach(function(spesa) {
				if (spesa.spesa_viaggio_id !== null) {
					markup = markup + 
					"<tr>" +
					"<td>" + spesa.spesa_viaggio_id + "</td>" +
					"<td class=\"col-md-2\">" + spesa.spesa_viaggio_data + "</td>" +
					"<td class=\"col-md-3\">" + spesa.spesa_viaggio_tipo + "</td>" +
					"<td style=\"white-space: pre-wrap;\" >" + spesa.spesa_viaggio_note + "</td>" +
					"<td class=\"col-md-2 text-right\">" + spesa.spesa_viaggio_importo + "</td>" +
					"<td class=\"col-md-2 text-center\">" +
					"<div onclick=\"viaggioModificaSpesa('" + spesa.spesa_viaggio_id + "')\" class=\"btn btn-warning btn-xs\"><span class=\"glyphicon glyphicon-pencil\"></div>&nbsp;" +
					"<div onclick=\"cancellaLineaSpesa('" + spesa.spesa_viaggio_id + "')\" class=\"btn btn-danger btn-xs\"><span class=\"glyphicon glyphicon-trash\"></div>" +
					"</td>" +
			"</tr>";
				}
			});
			$('#update_viaggio_spese_table > tbody:last-child').append(markup);
			$('#update_viaggio_spese_table td:nth-child(1),th:nth-child(1)').hide(); // nasconde la prima colonna con l'id
		}
    );
	$("#update_viaggio_modal").modal("show");
}

function cancellaLineaSpesa(id) {
    var viaggio_id = $("#hidden_viaggio_id").val();

    var conf = confirm("Sei sicuro di volere cancellare questa spesa ?");
    if (conf == true) {
        $.post("viaggioLineaSpesaDelete.php", {
                id: id
            },
            function (data, status) {
            	viaggioGetDetails(viaggio_id);
            }
        );
    }
}

function viaggioSpesaGetDetails(id) {
	$("#hidden_spesa_viaggio_id").val(id);
	if (id > 0) {
		$.post("viaggioLineaSpesaReadDetails.php", {
			id: id
		},
		function (dati, status) {
			var spesaViaggio = JSON.parse(dati);
			// console.log(spesaViaggio.tipo);
			var update_spesa_data_str = spesaViaggio.data;
			var update_spesa_data = Date.parseExact(update_spesa_data_str, 'yyyy-MM-dd');
			update_spesa_data_pickr.setDate(update_spesa_data);

			$("#update_spesa_tipo").val(spesaViaggio.tipo);
			$("#update_spesa_importo").val(spesaViaggio.importo);
			$("#update_spesa_note").val(spesaViaggio.note);
		}
 );
	} else {
		update_spesa_data_pickr.setDate(Date.today().toString('d/M/yyyy'));
		$("#update_spesa_tipo").val('');
		$("#update_spesa_importo").val('');
		$("#update_spesa_note").val('');
	}
	$("#update_spesa_row_modal").modal("show");
}

function viaggioModificaSpesa(id) {
	viaggioSpesaGetDetails(id);
}

function viaggioAddSpesa() {
	viaggioSpesaGetDetails(0);
}

function viaggioSpesaUpdateDetails() {
    var data_spesa_str = $("#update_spesa_data").val();
	var data_spesa_date = Date.parseExact(data_spesa_str, 'd/M/yyyy');
	var data = data_spesa_date.toString('yyyy-MM-dd');
    var tipo = $("#update_spesa_tipo").val();
    var importo = $("#update_spesa_importo").val();
	var note = $("#update_spesa_note").val();

    var spesa_viaggio_id = $("#hidden_spesa_viaggio_id").val();
    var viaggio_id = $("#hidden_viaggio_id").val();

	$.post("viaggioLineaSpesaUpdateDetails.php", {
    		spesa_viaggio_id: spesa_viaggio_id,
    		viaggio_id: viaggio_id,
    		data: data,
    		tipo: tipo,
    		importo: importo,
    		note: note
        },
        function (data, status) {
            $("#update_spesa_row_modal").modal("hide");
            viaggioGetDetails(viaggio_id);
        }
    );
}

function viaggioUpdateDetails() {
    var viaggio_id = $("#hidden_viaggio_id").val();
    var viaggio_destinazione = $("#update_viaggio_destinazione").val();
    var viaggio_classe = $("#update_viaggio_classe").val();

    var data_partenza_str = $("#update_viaggio_data_partenza").val();
    var data_rientro_str = $("#update_viaggio_data_rientro").val();
	var data_partenza_date = Date.parseExact(data_partenza_str, 'd/M/yyyy');
	var data_rientro_date = Date.parseExact(data_rientro_str, 'd/M/yyyy');
	var viaggio_data_partenza = data_partenza_date.toString('yyyy-MM-dd');
	var viaggio_data_rientro = data_rientro_date.toString('yyyy-MM-dd');

    var viaggio_ora_partenza = $("#update_viaggio_ora_partenza").val();
    var viaggio_ora_rientro = $("#update_viaggio_ora_rientro").val();
    var viaggio_ore_richieste = $("#update_viaggio_ore_richieste").val();
    var viaggio_richiesta_fuis = $("#update_viaggio_richiesta_fuis").prop('checked');
	$.post("viaggioUpdateDetails.php", {
		viaggio_id: viaggio_id,
		viaggio_destinazione: viaggio_destinazione,
		viaggio_classe: viaggio_classe,
		viaggio_data_partenza: viaggio_data_partenza,
		viaggio_data_rientro: viaggio_data_rientro,
		viaggio_ora_partenza: viaggio_ora_partenza,
		viaggio_ora_rientro: viaggio_ora_rientro,
		viaggio_ore_richieste: viaggio_ore_richieste,
		viaggio_richiesta_fuis: viaggio_richiesta_fuis
    },
    function (data, status) {
        $("#update_viaggio_modal").modal("hide");
        location.reload();
    }
);

}

$(document).ready(function () {
	update_viaggio_data_partenza_pickr = flatpickr("#update_viaggio_data_partenza", {
		locale: {
			firstDayOfWeek: 1
		},
		dateFormat: 'j/n/Y'
	});
	update_viaggio_data_rientro_pickr = flatpickr("#update_viaggio_data_rientro", {
		locale: {
			firstDayOfWeek: 1
		},
		dateFormat: 'j/n/Y'
	});
	update_spesa_data_pickr = flatpickr("#update_spesa_data", {
		locale: {
			firstDayOfWeek: 1
		},
		dateFormat: 'j/n/Y'
	});

	update_viaggio_ora_partenza_pickr = flatpickr("#update_viaggio_ora_partenza", {
	    enableTime: true,
	    noCalendar: true,
	    dateFormat: "H:i",
	    time_24hr: true,
	    static: true
	});

	update_viaggio_ora_rientro_pickr = flatpickr("#update_viaggio_ora_rientro", {
	    enableTime: true,
	    noCalendar: true,
	    dateFormat: "H:i",
	    time_24hr: true,
	    static: true
	});

	flatpickr.localize(flatpickr.l10ns.it);
/*
	$('#update_viaggio_richiesta_fuis').change(function() {
		if(this.checked) {
			$("#update_viaggio_ore_richieste").val(0);
		}
	});

	$('#update_viaggio_ore_richieste').change(function() {
		var val = $("#update_viaggio_ore_richieste").val() > 16 ? 16 : $("#update_viaggio_ore_richieste").val();
		$("#update_viaggio_ore_richieste").val(val);
	});
*/

    viaggioReadRecords();
});

// stack modal dialogs
$(document)
.on('show.bs.modal', '.modal', function(e) {
    $(this).appendTo($('body'));
})
.on('shown.bs.modal', '.modal.in', function(e) {
    setModalsAndBackdropsOrder();
})
.on('hidden.bs.modal', '.modal', function(e) {
    setModalsAndBackdropsOrder();
});

function setModalsAndBackdropsOrder() {
var modalZIndex = 1040;

$('.modal.in').each(function(index) {
    var $modal = $(this);

    modalZIndex++;

    $modal.css('zIndex', modalZIndex);
    $modal.next('.modal-backdrop.in').addClass('hidden').css('zIndex', modalZIndex - 1);
});

$('.modal.in:visible:last').focus().next('.modal-backdrop.in').removeClass('hidden');
}
$('.modal').on('hidden.bs.modal', function(event) {
    $(this).removeClass('fv-modal-stack');
    $('body').data('fv_open_modals', $('body').data('fv_open_modals') - 1);
});

$('.modal').on('shown.bs.modal', function(event) {
    if(typeof($('body').data('fv_open_modals')) == 'undefined') {
        $('body').data( 'fv_open_modals', 0);
    }

    if($(this).hasClass('fv-modal-stack')) {
        return;
    }
                   
    $(this).addClass('fv-modal-stack');
    $('body').data('fv_open_modals', $('body').data('fv_open_modals') + 1);
    $(this).css('z-index', 1040 + (10 * $('body').data('fv_open_modals')));
    $('.modal-backdrop').not('.fv-modal-stack')
                            .css('z-index', 1039 + (10 * $('body').data('fv_open_modals')));

    $('.modal-backdrop').not('fv-modal-stack')
        .addClass( 'fv-modal-stack' ); 
});
