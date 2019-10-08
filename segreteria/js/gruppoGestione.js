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

function sostituzione_docenteDelete(id, cognome, nome) {/*
    var conf = confirm("Sei sicuro di volere cancellare il gruppo " + cognome + " " + nome + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
				id: id,
				table: 'sostituzione_docente',
				name: "sostituzione del docente " + cognome + " " + nome
            },
            function (data, status) {
                gruppoGestioneReadRecords();
            }
        );
    }*/
}

function openModal() {
	$("#add_new_record_modal").modal("show");
}

function gruppoGestioneAddRecord() {
    $.post("gruppoGestioneAddRecord.php", {
        nome: $("#nome").val(),
        commento: $("#commento").val(),
        max_ore: $("#max_ore").val(),
        dipartimento: 0,
        responsabile_docente_id: $("#responsabile").val(),
        ore_responsabile: $("#ore_responsabile").val()
    }, function (data, status) {
        $("#add_new_record_modal").modal("hide");
        gruppoGestioneReadRecords();
    });
}

$(document).ready(function () {
    gruppoGestioneReadRecords();
});
