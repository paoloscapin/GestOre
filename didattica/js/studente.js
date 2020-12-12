/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function studenteReadRecords() {
	$.get("studenteReadRecords.php?ancheCancellati=0", {}, function (data, status) {
		$(".records_content").html(data);
	});
}

function studenteDelete(id, materia) {
    var conf = confirm("Sei sicuro di volere cancellare lo studente " + cognome + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
				id: id,
				table: 'studente',
				name: "cognome " + cognome
            },
            function (data, status) {
                studenteReadRecords();
            }
        );
    }
}

function studenteSave() {
    $.post("studenteSave.php", {
        id: $("#hidden_studente_id").val(),
		cognome: $("#cognome").val(),
		nome: $("#nome").val(),
        email: $("#email").val(),
        classe: $("#classe").val(),
        anno: $("#anno").val()
    }, function (data, status) {
        $("#studente_modal").modal("hide");
        studenteReadRecords();
    });
}

function studenteGetDetails(studente_id) {
    $("#hidden_studente_id").val(studente_id);

    if (studente_id > 0) {
        $.post("../common/readRecordDetails.php", {
            id: studente_id,
            table: 'studente'
        }, function (data, status) {

            console.log("studente_id=" + data);
            var studente = JSON.parse(data);
            $("#cognome").val(studente.cognome);
            $("#nome").val(studente.nome);
            $("#email").val(studente.email);
            $("#classe").val(studente.classe);
            $("#anno").val(studente.anno);
        });
    } else {
        $("#cognome").val("");
        $("#nome").val("");
        $("#email").val("");
        $("#classe").val("");
        $("#anno").val("");
    }

	$("#studente_modal").modal("show");
}

function importFile(file) {
    var contenuto = "";
    const reader = new FileReader();
    reader.addEventListener('load', (event) => {
        contenuto = event.target.result;
        $.post("studenteImport.php", {
            contenuto: contenuto
        },
        function (data, status) {
            $('#result_text').html(data);
            studenteReadRecords();
        });
    });
    reader.readAsText(file);
}

$(document).ready(function () {
	studenteReadRecords();

    $('#file_select_id').change(function (e) {
        importFile(e. target. files[0]);
    });
});
