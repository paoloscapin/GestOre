/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function corsoDiRecuperoStudentiReadRecords() {
	$.get("corsoDiRecuperoStudentiReadRecords.php?corso_di_recupero_id=" + $("#hidden_corso_di_recupero_id").val(), {}, function (data, status) {
		$(".records_content").html(data);
	});
}

function corsoDiRecuperoStudentiGetDetails(id) {
	$("#hidden_corso_di_recupero_studenti_id").val(id);

	if (id > 0) {
		$.post("../common/readRecordDetails.php", {
			id: id,
			table: 'studente_per_corso_di_recupero'
		},
		function (data, status) {
			var studente = JSON.parse(data);

			$("#cognome").val(studente.cognome);
			$("#nome").val(studente.nome);
			$("#email").val(studente.email);
			$("#classe").val(studente.classe);
		});
    } else {
		$("#cognome").val("");
		$("#nome").val("");
		$("#email").val("");
		$("#classe").val("");
	}
	$("#corso_di_recupero_studenti_modal").modal("show");
}

function corsoDiRecuperoStudentiSave() {
    $.post("corsoDiRecuperoStudentiSave.php", {
        id: $("#hidden_corso_di_recupero_studenti_id").val(),
		corso_di_recupero_id:  $("#hidden_corso_di_recupero_id").val(),
        cognome: $("#cognome").val(),
        nome: $("#nome").val(),
        email: $("#email").val(),
        classe: $("#classe").val()
    }, function (data, status) {
        $("#corso_di_recupero_studenti_modal").modal("hide");
        corsoDiRecuperoStudentiReadRecords();
    });
}

function corsoDiRecuperoStudentiDelete(id, cognome, nome) {
    var conf = confirm("Sei sicuro di volere cancellare lo studente " + cognome + " " + nome + " ?");
    if (conf == true) {
        $.post("corsoDiRecuperoStudentiDelete.php", {
				id: id
            },
            function (data, status) {
		        corsoDiRecuperoStudentiReadRecords();
            }
        );
    }
}

$(document).ready(function () {
	corsoDiRecuperoStudentiReadRecords();
});
