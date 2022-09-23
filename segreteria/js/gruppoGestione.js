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
                gruppoGestioneReadRecords();
            }
        );
    }
}

function openModal() {
    $("#nome").val("");
    $("#commento").val("");
    $("#max_ore").val("");
    $('#responsabile').val("0");
    $('#responsabile').selectpicker('refresh');
	$("#add_new_record_modal").modal("show");
}

function gruppoGestioneSave() {
    $.post("gruppoGestioneAddRecord.php", {
        nome: $("#nome").val(),
        commento: $("#commento").val(),
        max_ore: $("#max_ore").val(),
        dipartimento: 0,
        responsabile_docente_id: $("#responsabile").val()
    }, function (data, status) {
        $("#add_new_record_modal").modal("hide");
        gruppoGestioneReadRecords();
    });
}


function gruppoGestioneGetDetails(gruppo_id) {
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
