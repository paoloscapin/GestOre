/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var soloValidi=1;

$('#soloValidiCheckBox').change(function() {
    // this si riferisce al checkbox
    if (this.checked) {
		soloValidi = 1;
    } else {
		soloValidi = 0;
    }
    modulisticaReadRecords();
});

function modulisticaReadRecords() {
	$.get("../admin/modulisticaReadRecords.php?soloValidi=" + soloValidi, {}, function (data, status) {
		$(".records_content").html(data);
	});
}

function modulisticaDelete(id, nome) {
    var conf = confirm("Sei sicuro di volere cancellare l'elemento " + nome + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
				id: id,
				table: 'modulistica_template',
				name: "nome template: " + nome
            },
            function (data, status) {
                modulisticaReadRecords();
            }
        );
    }
}

function modulisticaGetDetails(id) {
    $("#hidden_record_id").val(id);
    if (id > 0) {
        $.post("../common/readRecordDetails.php", {
			id: id,
            table: 'modulistica_template'
		},
		function (data, status) {
			var record = JSON.parse(data);
			$("#nome").val(record.nome);
            $("#intestazione").prop('checked', record.intestazione != 0 && record.intestazione != null);
			$("#email_to").val(record.email_to);
            $("#approva").prop('checked', record.approva != 0 && record.approva != null);
			$("#email_approva").val(record.email_approva);
            $("#firma_forte").prop('checked', record.firma_forte != 0 && record.firma_forte != null);
            $("#valido").prop('checked', record.valido != 0 && record.valido != null);
		});
    } else {
        $("#nome").val("");
    }
	$("#update_modal").modal("show");
}

function modulisticaSave() {
    $.post("../admin/modulisticaSave.php", {
        id: $("#hidden_record_id").val(),
        nome: $("#nome").val(),
        intestazione: $("#intestazione").is(':checked')? 1: 0,
        email_to: $("#email_to").val(),
        approva: $("#approva").is(':checked')? 1: 0,
        email_approva: $("#email_approva").val(),
        firma_forte: $("#firma_forte").is(':checked')? 1: 0,
        valido: $("#valido").is(':checked')? 1: 0
    },
    function (data, status) {
        $("#update_modal").modal("hide");
        modulisticaReadRecords();
    });
}

function modulisticaOpenTemplate(id) {
    window.open('/GestOre/admin/modulisticaCampo.php?template_id=' + id, '_blank');
}

$(document).ready(function () {
    modulisticaReadRecords();
});