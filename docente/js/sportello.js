/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var soloNuovi=1;
var soloMiei=1;
var materia_filtro_id=0;

function setDbDateToPickr(pickr, data_str) {
	var data = Date.parseExact(data_str, 'yyyy-MM-dd');
	pickr.setDate(data);
}

function getDbDateFromPickrId(pickrId) {
	var data_str = $(pickrId).val();
	var data_date = Date.parseExact(data_str, 'd/M/yyyy');
	return data_date.toString('yyyy-MM-dd');
}

$('#soloNuoviCheckBox').change(function() {
    // this si riferisce al checkbox
    if (this.checked) {
		soloNuovi = 1;
    } else {
		soloNuovi = 0;
    }
    sportelloReadRecords();
});

$('#soloMieiCheckBox').change(function() {
    // this si riferisce al checkbox
    if (this.checked) {
		soloMiei = 1;
    } else {
		soloMiei = 0;
    }
    sportelloReadRecords();
});

function sportelloReadRecords() {
	$.get("../docente/sportelloReadRecords.php?ancheCancellati=true&soloNuovi=" + soloNuovi + "&soloMiei=" + soloMiei + "&materia_filtro_id=" + materia_filtro_id, {}, function (data, status) {
        $(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
	});
}

function sportelloDelete(id, materia) {
    var conf = confirm("Sei sicuro di volere cancellare lo sportello di " + materia + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
				id: id,
				table: 'sportello',
				name: "materia" + materia
            },
            function (data, status) {
                if (data=='Application Error') {
                    errorNotify('Impossibile cancellare lo sportello', 'Lo sportello della materia <Strong>' + materia + '</Strong> contiene probabilmente degli studenti iscritti o altri riferimenti');
                } else {
                    infoNotify('Cancellazione effettuata', 'Lo sportello della materia <Strong>' + materia + '</Strong> è stato cancellato regolarmente');
                }
                sportelloReadRecords();
            }
        );
    }
}

function sportelloSave() {
    // controlla che ci siano la materia ed il numero di ore
    if ($("#materia").val() <= 0) {
		$("#_error-materia").text("Devi selezionare una materia");
		$("#_error-materia-part").show();
		return;
	}
	if ($("#numero_ore").val() <= 0) {
		$("#_error-materia").text("Il numero di ore non può essere 0");
		$("#_error-materia-part").show();
		return;
	}

    // se tutto bene nasconde il messaggio di errore e prosegue nel save
    $("#_error-materia-part").hide();

    // controlla la lista di studenti segnati presenti
    var studentiDaModificareIdList = [];
    $('#studenti_table tbody tr').each(function() {
        var row = $(this);
        var presenteCheckbox = row.find('input[type="checkbox"]');
        var presenteOriginal = presenteCheckbox.prop('defaultChecked');
        var presenteCorrente = presenteCheckbox.prop('checked');
        var id = row.children().eq(0).text();
        if (presenteCorrente != presenteOriginal) {
            studentiDaModificareIdList.push(id);
        }
    });

    $.post("sportelloAggiorna.php", {

        id: $("#hidden_sportello_id").val(),
		data: getDbDateFromPickrId("#data"),
        ora: $("#ora").val(),
		materia_id: $("#materia").val(),
        numero_ore: $("#numero_ore").val(),
		argomento: $("#argomento").val(),
		luogo: $("#luogo").val(),
        max_iscrizioni: $("#max_iscrizioni").val(),
        classe: $("#classe").val(),
        cancellato: $("#cancellato").is(':checked')? 1: 0,
        firmato: $("#firmato").is(':checked')? 1: 0,
        online: $("#online").is(':checked')? 1: 0,
        clil: $("#clil").is(':checked')? 1: 0,
        orientamento: $("#orientamento").is(':checked')? 1: 0,
        studentiDaModificareIdList: JSON.stringify(studentiDaModificareIdList),
    }, function (data, status) {
        $("#sportello_modal").modal("hide");
        sportelloReadRecords();
    });
}

function sportelloFirma() {
    if ($("#firmato").is(':checked')) {
        // gia' firmato, non dovrebbe succedere0
        return;
    }
    // setta che è firmato
    $("#firmato").prop('checked', true);

    // lo salva
    $.post("sportelloFirma.php", {
        id: $("#hidden_sportello_id").val()
    }, function (data, status) {
    });
}

function sportelloGetDetails(sportello_id) {
    $("#hidden_sportello_id").val(sportello_id);

    if (sportello_id > 0) {
        $.post("../docente/sportelloReadDetails.php", {
            sportello_id: sportello_id
        }, function (data, status) {
            // console.log(data);
            var sportello = JSON.parse(data);
            var cancellato = sportello.sportello_cancellato != 0 && sportello.sportello_cancellato != null;
            var firmato = sportello.sportello_firmato != 0 && sportello.sportello_firmato != null;
            setDbDateToPickr(data_pickr, sportello.sportello_data);
            $("#ora").val(sportello.sportello_ora);
            $("#docente").val(sportello.docente_cognome + ' ' + sportello.docente_nome);
            $('#materia').selectpicker('val', sportello.materia_id);
            $("#numero_ore").val(sportello.sportello_numero_ore);
            $("#argomento").val(sportello.sportello_argomento);
            $("#luogo").val(sportello.sportello_luogo);
            $("#classe").val(sportello.sportello_classe);
            $("#max_iscrizioni").val(sportello.sportello_max_iscrizioni);
            $("#cancellato").prop('checked', sportello.sportello_cancellato != 0 && sportello.sportello_cancellato != null);
            $("#firmato").prop('checked', sportello.sportello_firmato != 0 && sportello.sportello_firmato != null);
            $("#online").prop('checked', sportello.sportello_online != 0 && sportello.sportello_online != null);
            $("#clil").prop('checked', sportello.sportello_clil != 0 && sportello.sportello_clil != null);
            $("#orientamento").prop('checked', sportello.sportello_orientamento != 0 && sportello.sportello_orientamento != null);
            // abilita la firma se non firmato
            if (! firmato && ! cancellato) {
                $("#firma_sportello_button_id").show();
            } else {
                $("#firma_sportello_button_id").hide();
            }
    
            $('#studenti_table tbody').empty();
            var markup = '';
            // cicla su tutti gli studenti
            // console.log(sportello.studenti);
            sportello.studenti.forEach(function(studenti) {
                // console.log(studenti);
                markup = markup + 
                        "<tr>" +
                        "<td>" + studenti.sportello_studente_id + "</td>" +
                        "<td>" + studenti.sportello_studente_presente + "</td>" +
                        "<td style=\"text-align: left; vertical-align: middle;\">" + studenti.studente_cognome + " " + studenti.studente_nome + "</td>" +
                        "<td style=\"text-align: left; vertical-align: middle;\">" + studenti.sportello_studente_argomento + "</td>" +
                        "<td style=\"text-align: center; vertical-align: middle;\">" +
                            "<input type=\"checkbox\" name=\"query_myTextEditBox\"" +
                            ((studenti.sportello_studente_presente == 0 || studenti.sportello_studente_presente == null) ? "" : " checked" ) +
                        "></td>" +
                "</tr>";
            });
            $('#studenti_table > tbody:last-child').append(markup);
            $('#studenti_table td:nth-child(1),#studenti_table th:nth-child(1),#studenti_table td:nth-child(2),#studenti_table th:nth-child(2)').hide(); // nasconde la prima colonna con l'id
        });
    } else {
        data_pickr.setDate(Date.today().toString('d/M/yyyy'));
        $("#ora").val("14");
        $('#docente').val($("#hidden_docente_cognome_nome").val());
        $('#docente').selectpicker('refresh');
        $('#materia').val("0");
        $('#materia').selectpicker('refresh');
        $("#numero_ore").val("0");
        $("#argomento").val("");
        $("#luogo").val("");
        $("#classe").val("");
        $("#max_iscrizioni").val($("#hidden_max_iscrizioni_default").val());
        $("#cancellato").prop('checked', false);
        $("#firmato").prop('checked', false);
        $("#onine").prop('checked', false);
        $("#clil").prop('checked', false);
        $("#orientamento").prop('checked', false);
    }
	$("#_error-materia-part").hide();
    $("#sportello_modal").modal("show");
}

$(document).ready(function () {
	data_pickr = flatpickr("#data", {
		locale: {
			firstDayOfWeek: 1
		},
		dateFormat: 'j/n/Y'
	});

    sportelloReadRecords();
    
    $("#materia_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        materia_filtro_id = this.value;
        sportelloReadRecords();
    });
});
