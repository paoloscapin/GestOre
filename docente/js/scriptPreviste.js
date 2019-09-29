/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function oreDovuteReadAttivita() {
	$.get("oreDovuteReadAttivita.php", {docente_id: $("#hidden_docente_id").val()}, function (data, status) {
		$(".attivita_previste_records_content").html(data);
	});
}

function attivitaPrevistaUpdateDetails() {
    $.post("oreDovuteUpdateAttivita.php", {
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
    	oreDovuteReadAttivita();
    });
    $("#update_attivita_modal").modal("hide");
}

function oreDovuteAttivitaGetDetails(attivita_id) {
	// Add record ID to the hidden field for future usage
	$("#hidden_ore_previste_attivita_id").val(attivita_id);
	if (attivita_id > 0) {
		$.post("oreDovuteAttivitaReadDetails.php", {
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

function attivitaPrevistaModifica(id) {
	oreDovuteAttivitaGetDetails(id);
}

function attivitaPrevistaAdd() {
	oreDovuteAttivitaGetDetails(0);
}

function attivitaPrevistaDelete(id) {

    var conf = confirm("Sei sicuro di volere cancellare questa attività ?");
    if (conf == true) {
        $.post("oreDovuteAttivitaDelete.php", {
			docente_id: $("#hidden_docente_id").val(),
			id: id
            },
            function (data, status) {
            	oreDovuteReadAttivita();
            }
        );
    }
}
//Read records on page load
$(document).ready(function () {
    oreDovuteReadAttivita();
});
