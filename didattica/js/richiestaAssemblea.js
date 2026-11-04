/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function dataLeggibile(dataMysql) {
// 1. Converti la stringa MySQL in un oggetto Data di JavaScript
    var dataObj = new Date(dataMysql);
    // 2. Configura le opzioni di formattazione in italiano
    var opzioni = { 
        weekday: 'long',  // "martedì"
        day: 'numeric',   // "12"
        month: 'long',    // "marzo"
        year: 'numeric'   // "2026"
    };
    // 3. Genera la stringa leggibile
    var dataLeggibile = new Intl.DateTimeFormat('it-IT', opzioni).format(dataObj);
    return dataLeggibile;
}

function setDbDateToPickr(pickr, data_str) {
	var data = Date.parseExact(data_str, 'yyyy-MM-dd');
	pickr.setDate(data);
}

function getDbDateFromPickrId(pickrId) {
	var data_str = $(pickrId).val();
	var data_date = Date.parseExact(data_str, 'd/M/yyyy');
	return data_date.toString('yyyy-MM-dd');
}

function generateGuidIntermedio() {
    let blocco1 = Math.random().toString(36).substring(2, 13).padEnd(11, '0');
    let blocco2 = Math.random().toString(36).substring(2, 12).padEnd(10, '0');
    
    return blocco1 + blocco2; // Sarà SEMPRE lungo esattamente 21 caratteri
}

function generateGuid() {
    // 1. Genera lo UUID standard (36 caratteri con i trattini)
    // 2. Rimuove tutti i trattini (diventano 32 caratteri puramente casuali)
    // 3. Prende i primi 21 caratteri
    return crypto.randomUUID().replace(/-/g, '').substring(0, 21);
}

function richiestaAssembleaReadRecords() {
	$.get("richiestaAssembleaReadRecords.php", {}, function (data, status) {
		$(".records_content").html(data);
	});
}

function richiestaAssembleaApprova(id) {
    $.post("../common/recordUpdate.php", {
        id: id,
        table: 'richiesta_assemblea',
        nome: 'stato',
        valore: 'approvata'
    },
    function (data, status) {
        richiestaAssembleaReadRecords();

        $.post("../didattica/richiestaAssembleaNotifica.php", {
            richiesta_id: id,
            stato: 'approvata'
        },
        function (data, status) {
        });
    });
}

function richiestaAssembleaAnnulla(id) {
    $.post("../common/recordUpdate.php", {
        id: id,
        table: 'richiesta_assemblea',
        nome: 'stato',
        valore: 'annullata'
    },
    function (data, status) {
        richiestaAssembleaReadRecords();

        $.post("../didattica/richiestaAssembleaNotifica.php", {
            richiesta_id: id,
            stato: 'annullata'
        },
        function (data, status) {
        });
    });
}

function richiestaAssembleaGetDetails(id) {
    $("#hidden_record_id").val(id);
    if (id > 0) {
        $.post("../studente/readAssembleaRecordDetails.php", {
			id: id,
            table: 'richiesta_assemblea'
		},
		function (data, status) {
			var richiesta = JSON.parse(data);
            setDbDateToPickr(data_assemblea_pickr, richiesta.data_assemblea);
            $('#docente_ora').selectpicker('val', richiesta.docente_id);
            $('#docente_coordinatore').selectpicker('val', richiesta.coordinatore_id);
			$("#hidden_uuid").val(richiesta.uuid);
			$("#classe").val(richiesta.classe);
            $('#ordine_del_giorno').summernote('code', richiesta.ordine_del_giorno);
            $('#verbale').summernote('code', richiesta.verbale);
		});
    } else {
        data_assemblea_pickr.setDate(Date.today().toString('d/M/yyyy'));
		$("#hidden_uuid").val(generateGuid());
        $('#ordine_del_giorno').summernote('code', '<ul><li><br></li></ul>');
        $('#verbale').summernote('code', '<p></p>');
        $('#richiedente').val($("#hidden_richiedente").val());
        $('#classe').val($("#hidden_classe").val());
    }
    $("#_error-part").hide();
	$("#richiesta_modal").modal("show");
}

function richiestaAssembleaSave() {
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

	$.post("richiestaAssembleaSave.php", {
            richiesta_id: $("#hidden_record_id").val(),
            data_assemblea: getDbDateFromPickrId("#data_assemblea"),
            ora_assemblea: $("#ora_assemblea").val(),
            uuid: $("#uuid").val(),
            classe: $("#classe").val(),
            stato: 'in attesa',
            docente_coordinatore_id: $("#docente_coordinatore").val(),
            docente_ora_id: $("#docente_ora").val(),
            ordine_del_giorno: $('#ordine_del_giorno').summernote('code')
		},
		function (data, status) {
			$("#richiesta_modal").modal("hide");
            richiestaAssembleaReadRecords();
    });
}

function richiestaAssembleaInoltra() {
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

    // lo salva per prima cosa
    richiestaSave();

	$.post("richiestaAssembleaInoltra.php", {
            richiesta_id: $("#hidden_record_id").val(),
            data_assemblea: getDbDateFromPickrId("#data_assemblea"),
            ora_assemblea: $("#ora_assemblea").val(),
            uuid: $("#uuid").val(),
            classe: $("#classe").val(),
            stato: 'inoltrata',
            docente_coordinatore_id: $("#docente_coordinatore").val(),
            docente_ora_id: $("#docente_ora").val(),
            ordine_del_giorno: $('#ordine_del_giorno').summernote('code')
		},
		function (data, status) {
			$("#richiesta_modal").modal("hide");
            richiestaAssembleaReadRecords();
    });
}

function richiestaAssembleaOrdineDelGiorno(id) {
    $.post("../common/readRecordDetails.php", {
        id: id,
        table: 'richiesta_assemblea'
    },
    function (data, status) {
        var richiesta = JSON.parse(data);
        $("#classeodg").val(richiesta.classe);
        $("#data_assembleaodg").val(dataLeggibile(richiesta.data_assemblea));
        $('#ordine_del_giorno_odg').html(richiesta.ordine_del_giorno);
    });
    $("#odg_modal").modal("show");
}

function richiestaAssembleaVerbale(id) {
    $("#hidden_record_id").val(id);
    $.post("../common/readRecordDetails.php", {
        id: id,
        table: 'richiesta_assemblea'
    },
    function (data, status) {
        var richiesta = JSON.parse(data);

        $("#classeverbalero").val(richiesta.classe);
        $("#data_assembleaverbalero").val(dataLeggibile(richiesta.data_assemblea));
        $('#ordine_del_giorno_verbalero').html(richiesta.ordine_del_giorno);
        if (richiesta.verbale == '') {
            $('#verbale').summernote('code', '<p><b>Verbale</b></p><p></p>');
        } else {
            $('#verbale_verbalero').html(richiesta.verbale);
        }
        $("#verbalero_modal").modal("show");
    });
}

$(document).ready(function () {

    // Calcola la data di oggi + 5 giorni
    const dataMinima = new Date();

    data_assemblea_pickr = flatpickr("#data_assemblea", {
		locale: { firstDayOfWeek: 1 },
        // Imposta la data predefinita a oggi + 5 giorni
        defaultDate: dataMinima, 
        // l'utente non puo' scegliere date passate
        minDate: dataMinima, 

		dateFormat: 'j/n/Y'
	});

	flatpickr.localize(flatpickr.l10ns.it);

    $('.summernote').summernote({
		height: 120,                 // set editor height
		minHeight: null,             // set minimum height of editor
		maxHeight: null,             // set maximum height of editor
		focus: true                  // set focus to editable area after initializing summernote
	  });

    richiestaAssembleaReadRecords();
});