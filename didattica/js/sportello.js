/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var soloNuovi=1;
var categoria_filtro_id=0;
var docente_filtro_id=0;
var materia_filtro_id=0;
var classe_filtro_id=0;

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

function sportelloReadRecords() {
	$.get("sportelloReadRecords.php?ancheCancellati=true&soloNuovi=" + soloNuovi + "&categoria_filtro_id=" + categoria_filtro_id + "&docente_filtro_id=" + docente_filtro_id + "&classe_filtro_id=" + classe_filtro_id + "&materia_filtro_id=" + materia_filtro_id, {}, function (data, status) {
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
                sportelloReadRecords();
            }
        );
    }
}

function sportelloSave() {
	if ($("#materia").val() <= 0) {
		$("#_error-materia").text("Devi selezionare una materia");
		$("#_error-materia-part").show();
		return;
	}
    if ($("#classe").val() <= 0) {
		$("#_error-classe").text("Devi selezionare una classe");
		$("#_error-classe-part").show();
		return;
	}
	if ($("#docente").val() <= 0) {
		$("#_error-materia").text("Devi selezionare un docente");
		$("#_error-materia-part").show();
		return;
	}
	if ($("#numero_ore").val() <= 0) {
		$("#_error-materia").text("Il numero di ore non può essere 0");
		$("#_error-materia-part").show();
		return;
	}
    $("#_error-materia-part").hide();

    if ($("#hidden_lista_classi").val() == "testo") // se la classe è una casella di testo
    {
        if ($('#hidden_sezione_online_clil').val() == 'true')
        {
            $.post("sportelloSave.php", {
                id: $("#hidden_sportello_id").val(),
                data: getDbDateFromPickrId("#data"),
                ora: $("#ora").val(),
                docente_id: $("#docente").val(),
                materia_id: $("#materia").val(),
                numero_ore: $("#numero_ore").val(),
                argomento: $("#argomento").val(),
                luogo: $("#luogo").val(),
                max_iscrizioni: $("#max_iscrizioni").val(),
                classe: $("#classe").val(),
                classe_id: 0,
                cancellato: $("#cancellato").is(':checked')? 1: 0,
                firmato: $("#firmato").is(':checked')? 1: 0,
                online: $("#online").is(':checked')? 1: 0,
                clil: $("#clil").is(':checked')? 1: 0,
                orientamento: $("#orientamento").is(':checked')? 1: 0
            }, function (data, status) {
                $("#sportello_modal").modal("hide");
                sportelloReadRecords();
            });
        }
        else
        {
            $.post("sportelloSave.php", 
                {
                    id: $("#hidden_sportello_id").val(),
                    data: getDbDateFromPickrId("#data"),
                    ora: $("#ora").val(),
                    docente_id: $("#docente").val(),
                    materia_id: $("#materia").val(),
                    numero_ore: $("#numero_ore").val(),
                    argomento: $("#argomento").val(),
                    luogo: $("#luogo").val(),
                    max_iscrizioni: $("#max_iscrizioni").val(),
                    classe: $("#classe").val(),
                    classe_id: 0,
                    cancellato: $("#cancellato").is(':checked')? 1: 0,
                    firmato: $("#firmato").is(':checked')? 1: 0,
                    online: 0,
                    clil: 0,
                    orientamento: 0
                }, function (data, status) {
                    $("#sportello_modal").modal("hide");
                    sportelloReadRecords();
                });
        }
    }
    else                                    // se la classe è scelta da una lista
    {
        if ($('#hidden_sezione_online_clil').val() == 'true')
        {
            $.post("sportelloSave.php", {
                id: $("#hidden_sportello_id").val(),
                data: getDbDateFromPickrId("#data"),
                ora: $("#ora").val(),
                docente_id: $("#docente").val(),
                materia_id: $("#materia").val(),
                numero_ore: $("#numero_ore").val(),
                argomento: $("#argomento").val(),
                luogo: $("#luogo").val(),
                max_iscrizioni: $("#max_iscrizioni").val(),
                classe: "",
                classe_id: $("#classe").val(),
                cancellato: $("#cancellato").is(':checked')? 1: 0,
                firmato: $("#firmato").is(':checked')? 1: 0,
                online: $("#online").is(':checked')? 1: 0,
                clil: $("#clil").is(':checked')? 1: 0,
                orientamento: $("#orientamento").is(':checked')? 1: 0
            }, function (data, status) {
                $("#sportello_modal").modal("hide");
                sportelloReadRecords();
            });
        }
        else
        {
            $.post("sportelloSave.php", {
                id: $("#hidden_sportello_id").val(),
                data: getDbDateFromPickrId("#data"),
                ora: $("#ora").val(),
                docente_id: $("#docente").val(),
                materia_id: $("#materia").val(),
                numero_ore: $("#numero_ore").val(),
                argomento: $("#argomento").val(),
                luogo: $("#luogo").val(),
                max_iscrizioni: $("#max_iscrizioni").val(),
                classe: "",
                classe_id: $("#classe").val(),
                cancellato: $("#cancellato").is(':checked')? 1: 0,
                firmato: $("#firmato").is(':checked')? 1: 0,
                online: 0,
                clil: 0,
                orientamento: 0,
            }, function (data, status) {
                $("#sportello_modal").modal("hide");
                sportelloReadRecords();
            });
        }        
    }
}

function sportelloGetDetails(sportello_id) {
    $("#hidden_sportello_id").val(sportello_id);

    if (sportello_id > 0) {
        $.post("../docente/sportelloReadDetails.php", {
            sportello_id: sportello_id
        }, function (data, status) {
            var sportello = JSON.parse(data);
            setDbDateToPickr(data_pickr, sportello.sportello_data);
            $("#ora").val(sportello.sportello_ora);
            $('#docente').selectpicker('val', sportello.docente_id);
            $('#materia').selectpicker('val', sportello.materia_id);
            $("#numero_ore").val(sportello.sportello_numero_ore);
            $("#argomento").val(sportello.sportello_argomento);
            $("#luogo").val(sportello.sportello_luogo);
            $('#classe').selectpicker('val', sportello.classe_id);
            $("#max_iscrizioni").val(sportello.sportello_max_iscrizioni);
            $("#cancellato").prop('checked', sportello.sportello_cancellato != 0 && sportello.sportello_cancellato != null);
            $("#firmato").prop('checked', sportello.sportello_firmato != 0 && sportello.sportello_firmato != null);

            if ($('#hidden_sezione_online_clil').val() == 'true') {
                $("#online").prop('checked', sportello.sportello_online != 0 && sportello.sportello_online != null);
                $("#clil").prop('checked', sportello.sportello_clil != 0 && sportello.sportello_clil != null);
                $("#orientamento").prop('checked', sportello.sportello_orientamento != 0 && sportello.sportello_orientamento != null);
            }
            $('#studenti_table tbody').empty();
            var markup = '';
            // cicla su tutti gli studenti
            console.log(sportello.studenti);
            sportello.studenti.forEach(function (studenti) {
                console.log(studenti);
                markup = markup +
                    "<tr>" +
                    "<td>" + studenti.sportello_studente_id + "</td>" +
                    "<td>" + studenti.sportello_studente_presente + "</td>" +
                    "<td style=\"text-align: left; vertical-align: middle;\">" + studenti.studente_cognome + " " + studenti.studente_nome + "</td>" +
                    "<td style=\"text-align: left; vertical-align: middle;\">" + studenti.sportello_studente_argomento + "</td>" +
                    "<td style=\"text-align: center; vertical-align: middle;\">" +
                    "<input type=\"checkbox\" name=\"query_myTextEditBox\"" +
                    ((studenti.sportello_studente_presente == 0 || studenti.sportello_studente_presente == null) ? "" : " checked") +
                    " disabled='true'></td>" +
                    "</tr>";
            });
            $('#studenti_table > tbody:last-child').append(markup);
            $('#studenti_table td:nth-child(1),#studenti_table th:nth-child(1),#studenti_table td:nth-child(2),#studenti_table th:nth-child(2)').hide(); // nasconde la prima colonna con l'id
        });
    } 
    else 
    {
        data_pickr.setDate(Date.today().toString('d/M/yyyy'));
        $("#ora").val("14:00");
        $('#docente').val("0");
        $('#docente').selectpicker('refresh');
        $('#materia').val("0");
        $('#materia').selectpicker('refresh');
        $("#numero_ore").val("0");
        $("#argomento").val("");
        $("#luogo").val("");
        $('#classe').val("0");
        $("#classe").selectpicker('refresh');
        $("#max_iscrizioni").val($("#hidden_max_iscrizioni_default").val());
        $("#cancellato").prop('checked', false);
        $("#firmato").prop('checked', false);
        if ($('#hidden_sezione_online_clil').val() == 'true') 
        {
            $("#onine").prop('checked', false);
            $("#clil").prop('checked', false);
            $("#orientamento").prop('checked', false);
        }
    }
                  
        
	$("#_error-materia-part").hide();
	$("#sportello_modal").modal("show");
}

function importFile(file) {
    var contenuto = "";
    const reader = new FileReader();
    reader.addEventListener('load', (event) => {
        contenuto = event.target.result;
        $.post("sportelloImport.php", {
            contenuto: contenuto
        },
        function (data, status) {
            $('#result_text').html(data);
            sportelloReadRecords();
        });
    });
    reader.readAsText(file);
}

$(document).ready(function () {
	data_pickr = flatpickr("#data", {
		locale: {
			firstDayOfWeek: 1
		},
		dateFormat: 'j/n/Y'
	});

    sportelloReadRecords();
    
    $("#categoria_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        categoria_filtro_id = this.value;
        sportelloReadRecords();
    });
    
    $("#docente_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        docente_filtro_id = this.value;
        sportelloReadRecords();
    });
    
    $("#materia_filtro").on("changed.bs.select", 
        function(e, clickedIndex, newValue, oldValue) {
            materia_filtro_id = this.value;
            sportelloReadRecords();
        });
    
    $("#classe_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        classe_filtro_id = this.value;
        sportelloReadRecords();
    });

        $('#file_select_id').change(function (e) {
        importFile(e. target. files[0]);
    });
});
