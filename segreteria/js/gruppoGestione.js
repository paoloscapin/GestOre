/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function gruppoGestioneReadRecords() {
	$.get("gruppoGestioneReadRecords.php", {}, function (data, status) {
		$(".records_content").html(data);
	});
}

function gruppoGestioneDelete(id, nome) {
    var conf = confirm("Sei sicuro di volere cancellare il gruppo " + nome + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
				id: id,
				table: 'gruppo',
				name: "gruppo" + nome
            },
            function (data, status) {
                if (data=='Application Error') {
                    errorNotify('Impossibile cancellare il gruppo', 'Il gruppo <Strong>' + nome + '</Strong> contiene probabilmente dei docenti partecipanti, incontri effettuati o altri riferimenti');
                } else {
                    infoNotify('Cancellazione effettuata', 'Il gruppo <Strong>' + nome + '</Strong> è stato cancellato regolarmente');
                }
                    
                gruppoGestioneReadRecords();
            }
        );
    }
}

function gruppoGestioneGetDetails(id) {
    $("#hidden_gruppo_id").val(id);
    if (id > 0) {
        $.post("gruppoGestioneReadDetails.php", {
			id: id
		},
		function (data, status) {
			var gruppo = JSON.parse(data);
            $("#nome").val(gruppo.nome);
            $("#commento").val(gruppo.commento);
            $("#max_ore").val(gruppo.max_ore);
            $("#clil").prop('checked', gruppo.clil != 0 && gruppo.clil != null);
            $('#responsabile').val(gruppo.responsabile_docente_id);
            $('#responsabile').selectpicker('refresh');
		});
    } else {
        $("#nome").val("");
        $("#commento").val("");
        $("#max_ore").val("");
        $("#clil").val("");
        $('#responsabile').val("0");
        $('#responsabile').selectpicker('refresh');
    }
	$("#gruppo_gestione_modal").modal("show");
}

function gruppoGestioneSave() {
    $.post("gruppoGestioneSave.php", {
        id: $("#hidden_gruppo_id").val(),
        nome: $("#nome").val(),
        commento: $("#commento").val(),
        max_ore: $("#max_ore").val(),
        dipartimento: 0,
        clil: $("#clil").is(':checked')? 1: 0,
        responsabile_docente_id: $("#responsabile").val()
    }, function (data, status) {
        $("#gruppo_gestione_modal").modal("hide");
        gruppoGestioneReadRecords();
    });
}


function gruppoPartecipantiGetDetails(gruppo_id) {
    $("#hidden_gruppo_id").val(gruppo_id);

    $.post("gruppoGestionePartecipantiRead.php", {
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
    $.post("gruppoGestionePartecipantiSave.php", {

        gruppo_id: $("#hidden_gruppo_id").val(),
        partecipantiArray: JSON.stringify($('#partecipanti').val())
    }, function (data, status) {
        $("#partecipanti_modal").modal("hide");
    });
}

function importFile(file) {
    var contenuto = "";
    const reader = new FileReader();
    reader.addEventListener('load', (event) => {
        contenuto = event.target.result;
        $.post("gruppiImport.php", {
            contenuto: contenuto
        },
        function (data, status) {
            $('#result_text').html(data);
            gruppoGestioneReadRecords();
        });
    });
    reader.readAsText(file);
}

function esporta() {
	var anno_id = $("#anno_select").val();
	window.open("gruppiExport.php" + "?anno_id=" + anno_id, '_blank');
}

$(document).ready(function () {
    gruppoGestioneReadRecords();

    $("#partecipanti").select2( {
        placeholder: "Seleziona i docenti",
        allowClear: false,
        language: "it",
        multiple: true
      });      

      $('#file_select_id').change(function (e) {
        importFile(e. target. files[0]);
    });

});
