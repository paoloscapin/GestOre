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
        // console.log(data);
        var record = JSON.parse(data);
        $(".gruppo_records_content_" + group_id).html(record.table);
        $("#totale_ore_" + group_id).html(record.totale_ore);
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
        $("#verbale-part").show();
        $('#effettuato-part').show();
        $('#partecipanti-part').show();
        $.post("../docente/gruppoIncontroReadDetails.php", {
                id: id,
                table: 'gruppo_incontro'
        },
		function (data, status) {
            var record = JSON.parse(data);
            setDbDateToPickr(data_incontro_pickr, record.data);
			$("#ora_incontro").val(record.ora);
			$("#ordine_del_giorno").val(record.ordine_del_giorno);
			$("#verbale").val(record.verbale);
            setOre('#durata', record.durata);
            $('#effettuato').bootstrapToggle(record.effettuato == 1? 'on' : 'off');
            $('#partecipanti_table tbody').empty();
			var markup = '';
			// cicla su tutti gli studenti
			record.partecipanti.forEach(function(partecipanti) {
				markup = markup + 
						"<tr>" +
						"<td>" + partecipanti.gruppo_incontro_partecipazione_id + "</td>" +
						"<td>" + partecipanti.docente_id + "</td>" +
						"<td>" + partecipanti.cognome_e_nome + "</td>" +
						"<td style=\"text-align: center; vertical-align: middle;\">" +
							"<input type=\"checkbox\" name=\"query_myTextEditBox\"" +
							((partecipanti.ha_partecipato == 0) ? "" : " checked" ) +
						"></td>" +
				"</tr>";
			});
			$('#partecipanti_table > tbody:last-child').append(markup);
            $('#partecipanti_table td:nth-child(1),#partecipanti_table th:nth-child(1),#partecipanti_table td:nth-child(2),#partecipanti_table th:nth-child(2)').hide(); // nasconde la prima colonna con l'id
        });
    } else {
        data_incontro_pickr.setDate(Date.today().toString('d/M/yyyy'));
        $("#ora_incontro").val("12");
        setOre('#durata', 2);
        $("#ordine_del_giorno").val("");
        $("#verbale").val("");
        $("#verbale-part").hide();
        $('#effettuato-part').hide();
        $('#partecipanti-part').hide();
    }
	$("#update_modal").modal("show");
}

function gruppoIncontroSave() {
    var partecipantiDaModificareIdList = [];
    var partecipantiDaModificareDocenteIdList = [];
    if ($("#hidden_record_id").val() > 0) {
        $('#partecipanti_table tbody tr').each(function() {
            var row = $(this);
            var presenteCheckbox = row.find('input[type="checkbox"]');
            var presenteOriginal = presenteCheckbox.prop('defaultChecked');
            var presenteCorrente = presenteCheckbox.prop('checked');
            var id = row.children().eq(0).text();
            var docente_id = row.children().eq(1).text();
            if (presenteCorrente != presenteOriginal) {
                partecipantiDaModificareIdList.push(id);
                partecipantiDaModificareDocenteIdList.push(docente_id);
            }
        });
    }

    $.post("gruppoIncontroSave.php", {
        id: $("#hidden_record_id").val(),
        gruppo_id: $("#hidden_gruppo_id").val(),
        data: getDbDateFromPickrId("#data_incontro"),
        ora: $("#ora_incontro").val(),
        durata: getOre("#durata"),
        effettuato: $("#effettuato").is(':checked')? 1: 0,
        ordine_del_giorno: $("#ordine_del_giorno").val(),
        verbale: $("#verbale").val(),
        partecipantiDaModificareIdList: JSON.stringify(partecipantiDaModificareIdList),
        partecipantiDaModificareDocenteIdList: JSON.stringify(partecipantiDaModificareDocenteIdList)
    },
    function (data, status) {
        $("#update_modal").modal("hide");
        gruppoIncontroReadRecords($("#hidden_gruppo_id").val());
    });
}

function gruppoGestionePartecipantiRead(gruppo_id) {
    $("#hidden_gruppo_id").val(gruppo_id);

    $.post("../segreteria/gruppoGestionePartecipantiRead.php", {
        gruppo_id: gruppo_id
    }, function (data, status) {
//        console.log(data);
        var record = JSON.parse(data);
        var idList = new Array();
        var i;
        for (i = 0; i < record.length; ++i) {
            idList.push(record[i]);
        }
        $("#partecipanti").val(idList).trigger('change');
        $("#partecipanti_modal").modal("show");
    });
}

function gruppoPartecipantiSave() {
//    console.log($('#partecipanti').val());
    $.post("../segreteria/gruppoGestionePartecipantiSave.php", {

        gruppo_id: $("#hidden_gruppo_id").val(),
        partecipantiArray: JSON.stringify($('#partecipanti').val())
    }, function (data, status) {
        $("#partecipanti_modal").modal("hide");
    });
}

$(document).ready(function () {
	// questi campi potrebbero essere gestiti in minuti se settato nel json
	campiInMinuti(
		'#durata'
	);

    $("#partecipanti").select2( {
        placeholder: "Seleziona i docenti",
        allowClear: false,
        language: "it",
        multiple: true
    });      

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

    var gruppoList = JSON.parse($("#hidden_list_gruppo_id").val());
    gruppoList.forEach(function(gruppo_id) {
        gruppoIncontroReadRecords(gruppo_id);
      });
});