/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function utenteReadRecords() {
	$.get("utenteReadRecords.php", {}, function (data, status) {
		$(".records_content").html(data);
	});
}

function utenteDelete(id, nome) {
    var conf = confirm("Sei sicuro di volere cancellare la utente " + nome + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
				id: id,
				table: 'utente',
				name: "utente " + nome
            },
            function (data, status) {
                utenteReadRecords();
            }
        );
    }
}

function utenteGetDetails(id) {
    $("#hidden_record_id").val(id);
    if (id > 0) {
        $.post("../common/readRecordDetails.php", {
			id: id,
            table: 'utente'
		},
		function (data, status) {
			var record = JSON.parse(data);
			$("#username").val(record.username);
			$("#cognome").val(record.cognome);
			$("#nome").val(record.nome);
			$("#ruolo").val(record.ruolo);
			$("#email").val(record.email);
		});
    } else {
        $("#nome").val("");
        $("#codice").val("");
    }
	$("#update_modal").modal("show");
}

function utenteSave() {
    $.post("utenteSave.php", {
        id: $("#hidden_record_id").val(),
        username: $("#username").val(),
        cognome: $("#cognome").val(),
        nome: $("#nome").val(),
        ruolo: $("#ruolo").val(),
        email: $("#email").val()
    },
    function (data, status) {
        $("#update_modal").modal("hide");
        utenteReadRecords();
    });
}

$(document).ready(function () {
    utenteReadRecords();
});