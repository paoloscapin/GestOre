/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function previsteReadRecords() {
	$.get("previsteReadRecords.php", {}, function (data, status) {
		$(".attivita_previste_records_content").html(data);
	});
}

function previstaUpdateDetails() {
	$.post("previsteSave.php", {
		docente_id: $("#hidden_docente_id").val(),
    	ore_previste_attivita_id: $("#hidden_ore_previste_attivita_id").val(),
    	update_tipo_attivita_id: $("#tipo_attivita").val(),
    	update_ore: $("#update_ore").val(),
    	update_dettaglio: $("#update_dettaglio").val()
    }, function (data, status) {
    	if (data !== '') {
    		bootbox.alert(data);
    	}
    	// console.log(data);
    	previsteReadRecords();
    });
    $("#update_attivita_modal").modal("hide");
}

function previsteGetDetails(attivita_id) {
	$("#hidden_ore_previste_attivita_id").val(attivita_id);
	if (attivita_id > 0) {
		$.post("previsteReadDetails.php", {
				attivita_id: attivita_id
			},
			function (dati, status) {
				// console.log(dati);
				var attivita = JSON.parse(dati);
				$('#tipo_attivita').selectpicker('val', attivita.ore_previste_tipo_attivita_id);
				$("#update_ore").val(attivita.ore);
				$("#update_dettaglio").val(attivita.dettaglio);
			}
		);
	} else {
		$("#tipo_attivita").val('');
		$('#tipo_attivita').selectpicker('val', 0);
		$("#update_ore").val('');
		$("#update_dettaglio").val('');
	}

	// Open modal popup
	$("#update_attivita_modal").modal("show");
}

function previstaModifica(id) {
	previsteGetDetails(id);
}

function attivitaPrevistaAdd() {
	previsteGetDetails(0);
}

function previstaDelete(id) {

    var conf = confirm("Sei sicuro di volere cancellare questa attività prevista ?");
    if (conf == true) {
        $.post("previsteDelete.php", {
			docente_id: $("#hidden_docente_id").val(),
			id: id
            },
            function (data, status) {
            	previsteReadRecords();
            }
        );
    }
}

//Read records on page load
$(document).ready(function () {
    previsteReadRecords();
});
