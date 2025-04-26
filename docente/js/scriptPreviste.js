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
		orePrevisteReloadTables();
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
		if ($("#hidden_operatore").val() == 'dirigente') {
			var d = new Date();
			var strDate = d.getDate() + "/" + (d.getMonth()+1) + "/" + d.getFullYear();
			$("#update_commento").val('Inserito da dirigente (' + strDate + ')');
		} else {
			$("#update_commento").val('');
		}
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
					orePrevisteReloadTables();
				}
			);
		}
	}
}

function diariaPrevistaGetDetails(id) {
	$("#hidden_diaria_id").val(id);
	if (id > 0) {
		$.post("../docente/viaggioDiariaPrevistaReadDetails.php", {
			id: id
			},
			function (dati, status) {
				// console.log(dati);
				var diaria = JSON.parse(dati);
				$("#diaria_descrizione").val(diaria.descrizione);
				$("#diaria_giorni_senza_pernottamento").val(diaria.giorni_senza_pernottamento);
				$("#diaria_giorni_con_pernottamento").val(diaria.giorni_con_pernottamento);
				setOre('#diaria_ore', diaria.ore);
				$("#diaria_commento").val(diaria.commento);
			}
		);
	} else {
		$("#diaria_descrizione").val('');
		$("#diaria_giorni_senza_pernottamento").val('');
		$("#diaria_giorni_con_pernottamento").val('');
		setOre('#diaria_ore', 0);
		$("#diaria_commento").val('');
	}
	if ($("#hidden_operatore").val() == 'dirigente') {
		$("#diaria_commento-part").show();
	} else {
		$("#diaria_commento-part").hide();
	}

	if ($("#hidden_operatore").val() == 'dirigente') {
		$("#diaria_commento-part").show();
	} else {
		$("#diaria_commento-part").hide();
	}

    $("#_error-diaria-part").hide();
	$("#diaria_modal").modal("show");
}

function diariaSave() {
	if (($("#diaria_ore").val() != "0:00")&&(($("#diaria_giorni_con_pernottamento").val() > 0)||($("#diaria_giorni_con_pernottamento").val() > 0))) {
        $("#_error-diaria").text("Le ore di recupero, per le uscite in giornata oltre il proprio orario di servizio, possono essere inserite in alternativa alla diaria giornaliera se tali ore non finiscono a FUIS. In tutti gli altri casi va inserita solo la diaria giornaliera.");
        $("#_error-diaria-part").show();
        return;
    }
    // se tutto bene nasconde il messaggio di errore e prosegue nel save
    $("#_error-diaria-part").hide();

	$.post("../docente/viaggioDiariaPrevistaSave.php", {
    	id: $("#hidden_diaria_id").val(),
		docente_id: $("#hidden_diaria_docente_id").val(),
    	operatore: $("#hidden_diaria_operatore").val(),
    	descrizione: $("#diaria_descrizione").val(),
    	giorni_senza_pernottamento: $("#diaria_giorni_senza_pernottamento").val(),
    	giorni_con_pernottamento: $("#diaria_giorni_con_pernottamento").val(),
		ore: getOre('#diaria_ore'),
    	commento: $("#diaria_commento").val()
    }, function (data, status) {
		orePrevisteReloadTables();
    });
    $("#diaria_modal").modal("hide");
}

function diariaPrevistaDelete(id) {
	if ($("#hidden_operatore").val() == 'dirigente') {
		diariaPrevistaGetDetails(id);
		bootbox.confirm({
			title: "Cancellazione Diaria Prevista",
			message: 'Per ragioni di storico non è possibile cancellare le previsione.</br>Si consiglia di mettere a zero il loro valore',
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
					$("#diaria_giorni_senza_pernottamento").val(0);
					$("#diaria_giorni_con_pernottamento").val(0);
				} else {
					$("#diaria_modal").modal("hide");
				}
			}
		});
	} else {
		var conf = confirm("Sei sicuro di volere cancellare questa diaria prevista ?");
		if (conf == true) {
			$.post("../docente/viaggioDiariaPrevistaDelete.php", {
				docente_id: $("#hidden_docente_id").val(),
				id: id
				},
				function (data, status) {
					orePrevisteReloadTables();
				}
			);
		}
	}
}

function corsoDiRecuperoPrevisteEdit(id, codice, ore_totali, ore_recuperate, ore_extra) {
	$("#hidden_corso_di_recupero_id").val(id);
	$("#corso_di_recupero_codice").val(codice);
	$("#corso_di_recupero_ore_totali").val(ore_totali);
	$("#corso_di_recupero_ore_recuperate").val(ore_recuperate);
	$("#corso_di_recupero_ore_extra").val(ore_extra);
	$("#corso_di_recupero_modal").modal("show");
}

$('#corso_di_recupero_ore_recuperate').change(function() {
    var corso_di_recupero_ore_recuperate = $('#corso_di_recupero_ore_recuperate').val();
	var corso_di_recupero_ore_totali = $('#corso_di_recupero_ore_totali').val();
	corso_di_recupero_ore_recuperate = Math.min(corso_di_recupero_ore_recuperate, corso_di_recupero_ore_totali);
	var corso_di_recupero_ore_extra = corso_di_recupero_ore_totali - corso_di_recupero_ore_recuperate;
	$("#corso_di_recupero_ore_recuperate").val(corso_di_recupero_ore_recuperate);
	$("#corso_di_recupero_ore_extra").val(corso_di_recupero_ore_extra);
});

$('#corso_di_recupero_ore_extra').change(function() {
    var corso_di_recupero_ore_extra = $('#corso_di_recupero_ore_extra').val();
	var corso_di_recupero_ore_totali = $('#corso_di_recupero_ore_totali').val();
	corso_di_recupero_ore_extra = Math.min(corso_di_recupero_ore_extra, corso_di_recupero_ore_totali);
	var corso_di_recupero_ore_recuperate = corso_di_recupero_ore_totali - corso_di_recupero_ore_extra;
	$("#corso_di_recupero_ore_recuperate").val(corso_di_recupero_ore_recuperate);
	$("#corso_di_recupero_ore_extra").val(corso_di_recupero_ore_extra);
});


function corsoDiRecuperoPrevisteSave() {
	var ore_totali = $("#corso_di_recupero_ore_totali").val();
	var ore_recuperate = $("#corso_di_recupero_ore_recuperate").val();
	var ore_pagamento_extra = $("#corso_di_recupero_ore_extra").val();
	
	// per prima cosa le recuperate non possone essere piu' delle totali (ma non meno di 0)
	ore_recuperate = Math.min(ore_recuperate, ore_totali);
	ore_recuperate = Math.max(ore_recuperate, 0);
	// poi le extra si calcolano in automatico
	ore_pagamento_extra = ore_totali - ore_recuperate;

	// adesso le posso salvare
	$.post("../docente/corsoDiRecuperoPrevisteSave.php", {
    	id: $("#hidden_corso_di_recupero_id").val(),
		ore_recuperate: ore_recuperate,
    	ore_pagamento_extra: ore_pagamento_extra
    }, function (data, status) {
		orePrevisteReloadTables();
    });
    $("#corso_di_recupero_modal").modal("hide");
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
		orePrevisteReloadTables();
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
		orePrevisteReloadTables();
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

	// legge gli importi
	var getImporti = $.get("../common/readImporti.php", {}, function (data, status) {
		_importi = JSON.parse(data);
		// console.log(_importi);
	});

	// legge i settings
	var getSettings = $.get("../common/readSettings.php", {}, function (data, status) {
		_settings = JSON.parse(data);
		// console.log(_settings);

	});

	$.when(getImporti, getSettings).done(function (r1, r2) {
		orePrevisteReloadTables();
	});

	// questi campi potrebbero essere gestiti in minuti se settato nel json
	campiInMinuti(
		'#update_ore',
		'#diaria_ore'
	);
});
