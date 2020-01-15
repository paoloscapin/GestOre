/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function previsteReadRecords() {
	$.post("../docente/previsteReadRecords.php", {
		operatore: $("#hidden_operatore").val(),
		ultimo_controllo: $("#hidden_ultimo_controllo").val()
	},
	function (data, status) {
		$(".attivita_previste_records_content").html(data);
	});

}

function previstaUpdateDetails() {
	if ($("#tipo_attivita").val() <= 0) {
		$("#_error-previste").text("Devi selezionare un tipo di attività");
		$("#_error-previste-part").show();
		return;
	}
	$("#_error-previste-part").hide();

	$.post("../docente/previsteSave.php", {
		docente_id: $("#hidden_docente_id").val(),
    	ore_previste_attivita_id: $("#hidden_ore_previste_attivita_id").val(),
    	update_tipo_attivita_id: $("#tipo_attivita").val(),
		update_ore: getOre("#update_ore"),
    	update_dettaglio: $("#update_dettaglio").val(),
    	operatore: $("#hidden_operatore").val(),
    	update_commento: $("#update_commento").val()
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
		$.post("../docente/previsteReadDetails.php", {
				attivita_id: attivita_id
			},
			function (dati, status) {
				// console.log(dati);
				var attivita = JSON.parse(dati);
				$('#tipo_attivita').selectpicker('val', attivita.ore_previste_tipo_attivita_id);
				setOre('#update_ore', attivita.ore);
				$("#update_dettaglio").val(attivita.dettaglio);
				$("#update_commento").val(attivita.commento);
			}
		);
	} else {
		$("#tipo_attivita").val('');
		$('#tipo_attivita').selectpicker('val', 0);
		$("#update_ore").val('');
		$("#update_dettaglio").val('');
		$("#update_commento").val('');
	}
	if ($("#hidden_operatore").val() == 'dirigente') {
		$("#commento-part").show();
	} else {
		$("#commento-part").hide();
	}

	$("#_error-previste-part").hide();
	$("#update_attivita_modal").modal("show");
}

function previstaModifica(id) {
	previsteGetDetails(id);
}

function attivitaPrevistaAdd() {
	previsteGetDetails(0);
}

function previstaDelete(attivita_id) {
	if ($("#hidden_operatore").val() == 'dirigente') {
		previsteGetDetails(attivita_id);		
		bootbox.confirm({
			title: "Cancellazione Ore Previste",
			message: 'Per ragioni di storico non è possibile cancellare le ore previste.</br>Si consiglia di mettere a zero il loro valore',
			buttons: {
				confirm: {
					label: 'Va Bene',
					className: 'btn-lima4'
				},
				cancel: {
					label: 'Annulla'
				}
			},
			callback: function (result) {
				if (result === true) {
					setOre('#update_ore', 0);
				} else {
					$("#update_attivita_modal").modal("hide");
				}
			}
		});
	} else {
		var conf = confirm("Sei sicuro di volere cancellare questa attività prevista ?");
		if (conf == true) {
			$.post("../docente/previsteDelete.php", {
				docente_id: $("#hidden_docente_id").val(),
				id: attivita_id
				},
				function (data, status) {
					previsteReadRecords();
				}
			);
		}
	}
}

// ----------------------------------------------------------------------------------------------

function previsteEmail() {
	$.post("../dirigente/emailNotificaDocente.php", {
		docente_id: $("#hidden_docente_id").val(),
		oggetto_modifica: "Ore Previste"
	},
	function (data, status) {
		$.notify({
			icon: 'glyphicon glyphicon-envelope',
			title: '<Strong>Notifica docente</Strong></br>',
			message: data
		},{
			placement: {
				from: "top",
				align: "center"
			},
			delay: 2000,
			timer: 100,
			mouse_over: "pause",
			type: 'info'
		});
	});
}

function previsteRivisto() {
	$.post("../dirigente/rivistoUltimoControllo.php", {
		docente_id: $("#hidden_docente_id").val(),
		tabella:"ore_previste"
	},
	function (data, status) {
		var tzoffset = (new Date()).getTimezoneOffset() * 60000;
		var localISOTime = (new Date(Date.now() - tzoffset)).toISOString().slice(0, -1);
		var ultimo_controllo = localISOTime.replace('T', ' ');
		$("#hidden_ultimo_controllo").val(ultimo_controllo);
		previsteReadRecords();
		$.notify({
			icon: 'glyphicon glyphicon-ok',
			title: '<Strong>Previste</Strong></br>',
			message: 'Revisione effettuata!' 
		},{
			placement: {
				from: "top",
				align: "center"
			},
			delay: 2000,
			timer: 100,
			mouse_over: "pause",
			type: 'success'
		});
	});
}

function previsteAzzeraSostituzioni() {
	$.post("../dirigente/sostituzioniRimuovi.php", {
		docente_id: $("#hidden_docente_id").val()
	},
	function (data, status) {
		previsteReadRecords();
	});
}

function previsteChiudi() {
	$.notify({
		icon: 'glyphicon glyphicon-off',
		title: '<Strong>Chiusura Previste</Strong></br>',
		message: '<Strong>Attenzione:</Strong> la funzionalità non è ancora disponibile!'
	},{
		placement: {
			from: "top",
			align: "center"
		},
		delay: 5000,
		timer: 100,
		mouse_over: "pause",
		type: 'danger'
	});
}

//Read records on page load
$(document).ready(function () {
    previsteReadRecords();
	// questi campi potrebbero essere gestiti in minuti se settato nel json
	campiInMinuti(
		'#update_ore'
	);
});
