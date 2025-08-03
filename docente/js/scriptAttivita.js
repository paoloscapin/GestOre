/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function getSettingsValue(o, def) {
	if (o === undefined) {
		return def;
	}
	return o;
}

function setDbDateToPickr(pickr, data_str) {
	var data = Date.parseExact(data_str, 'yyyy-MM-dd');
	pickr.setDate(data);
}

function getDbDateFromPickrId(pickrId) {
	var data_str = $(pickrId).val();
	var data_date = Date.parseExact(data_str, 'd/M/yyyy');
	return data_date.toString('yyyy-MM-dd');
}

function oreFatteGetRegistroAttivita(attivita_id, registro_id) {
	$("#hidden_ore_fatte_registro_id").val(registro_id);
	$("#hidden_ore_fatte_attivita_id").val(attivita_id);
	$("#hidden_registro_clil").val('nonclil');
	// console.log('attivita_id=' + attivita_id + ' registro_id=' + registro_id);
	$.post("oreFatteAttivitaReadRegistro.php", {
			attivita_id: attivita_id
		},
		function (dati, status) {
			// console.log(dati);
			var attivita = JSON.parse(dati);
			$("#registro_tipo_attivita").html('<p class="form-control-static">' + attivita.nome + '</p>');
			$("#registro_attivita_dettaglio").html('<p class="form-control-static">' + attivita.dettaglio + '</p>');
			$("#registro_attivita_data").html('<p class="form-control-static">' + Date.parseExact(attivita.data, 'yyyy-MM-dd').toString('d/M/yyyy') + '</p>');
			$("#registro_attivita_ora_inizio").html('<p class="form-control-static">' + attivita.ora_inizio + '</p>');
			$("#registro_attivita_ore").html('<p class="form-control-static">' + attivita.ore + '</p>');
			if (registro_id > 0) {
				$("#registro_descrizione").val(attivita.descrizione);
				$("#registro_studenti").val(attivita.studenti);
			} else {
				$("#registro_descrizione").val('');
				$("#registro_studenti").val('');
			}
		}
	);

	$("#docente_registro_modal").modal("show");
}

function attivitaFattaRegistroUpdateDetails() {
	// console.log('hidden_registro_clil valore=' + $("#hidden_registro_clil").val());
	if ($("#hidden_registro_clil").val() === 'clil') {
	 	$.post("oreFatteClilUpdateRegistro.php", {
	    	registro_id: $("#hidden_ore_fatte_registro_id").val(),
	    	attivita_id: $("#hidden_ore_fatte_attivita_id").val(),
	    	descrizione: $("#registro_descrizione").val(),
	    	studenti: $("#registro_studenti").val()
	    }, function (data, status) {
	    	oreFatteReloadTables();
	    });
	    $("#docente_registro_modal").modal("hide");
	} else {
	 	$.post("oreFatteUpdateRegistro.php", {
	    	registro_id: $("#hidden_ore_fatte_registro_id").val(),
	    	attivita_id: $("#hidden_ore_fatte_attivita_id").val(),
	    	descrizione: $("#registro_descrizione").val(),
	    	studenti: $("#registro_studenti").val()
	    }, function (data, status) {
	    	oreFatteReloadTables();
	    });
	    $("#docente_registro_modal").modal("hide");
	}
}

function oreFatteGetRendicontoAttivita(attivita_id, rendiconto_id) {
	$("#hidden_ore_fatte_rendiconto_id").val(rendiconto_id);
	$("#hidden_ore_fatte_attivita_id").val(attivita_id);
	// console.log('attivita_id=' + attivita_id + ' rendiconto_id=' + rendiconto_id);
	$.post("oreFatteAttivitaReadRendiconto.php", {
			attivita_id: attivita_id
		},
		function (dati, status) {
			// console.log(dati);
			var attivita = JSON.parse(dati);
			$("#rendiconto_tipo_attivita").html('<p class="form-control-static">' + attivita.nome + '</p>');
			$("#rendiconto_attivita_dettaglio").html('<p class="form-control-static">' + attivita.dettaglio + '</p>');
			if (rendiconto_id > 0) {
				$("#rendiconto_rendiconto").val(attivita.rendiconto);
			} else {
				$("#rendiconto_rendiconto").val('');
			}
		}
	);

	$("#docente_rendiconto_modal").modal("show");
}

function attivitaFattaRendicontoUpdateDetails() {
 	$.post("oreFatteUpdateRendiconto.php", {
 		rendiconto_id: $("#hidden_ore_fatte_rendiconto_id").val(),
    	attivita_id: $("#hidden_ore_fatte_attivita_id").val(),
    	rendiconto: $("#rendiconto_rendiconto").val()
    }, function (data, status) {
    	oreFatteReloadTables();
    });
    $("#docente_rendiconto_modal").modal("hide");
}

function oreFatteGetAttivita(attivita_id) {
	$("#hidden_ore_fatte_attivita_id").val(attivita_id);
	if (attivita_id > 0) {
		$.post("oreFatteAttivitaReadDetails.php", {
				attivita_id: attivita_id
			},
			function (dati, status) {
				// console.log(dati);
				var attivita = JSON.parse(dati);
				$('#attivita_tipo_attivita').selectpicker('val', attivita.ore_previste_tipo_attivita_id);
				setOre('#attivita_ore', attivita.ore);
				$("#attivita_dettaglio").val(attivita.dettaglio);
				$("#attivita_ora_inizio").val(attivita.ora_inizio);
				$("#attivita_ora_inizio").prop('defaultValue', attivita.ora_inizio);
				setDbDateToPickr(attivita_data_pickr, attivita.data);
				$("#attivita_commento").val(attivita.commento);
			}
		);
	} else {
		$("#attivita_tipo_attivita").val('');
		$('#attivita_tipo_attivita').selectpicker('val', 0);
		setOre('#attivita_ore', 2);
		$("#attivita_dettaglio").val('');
		ora_inizio_pickr.setDate(new Date());
		attivita_data_pickr.setDate(Date.today().toString('d/M/yyyy'));
		if ($("#hidden_operatore").val() == 'dirigente') {
			var d = new Date();
			var strDate = d.getDate() + "/" + (d.getMonth()+1) + "/" + d.getFullYear();
			$("#attivita_commento").val('Inserito da dirigente (' + strDate + ')');
		} else {
			$("#attivita_commento").val('');
		}
	}
	if ($("#hidden_operatore").val() == 'dirigente') {
		$("#commento-part").show();
	} else {
		$("#commento-part").hide();
	}

	$("#_error-attivita-part").hide();
	$("#docente_attivita_modal").modal("show");
}

function attivitaFattaSave() {
	if ($("#attivita_tipo_attivita").val() <= 0) {
		$("#_error-attivita").text("Devi selezionare un tipo di attività");
		$("#_error-attivita-part").show();
		return;
	}
	$("#_error-attivita-part").hide();

 	$.post("oreFatteSave.php", {
		docente_id: $("#hidden_docente_id").val(),
    	attivita_id: $("#hidden_ore_fatte_attivita_id").val(),
    	tipo_attivita_id: $("#attivita_tipo_attivita").val(),
    	ore: getOre("#attivita_ore"),
    	dettaglio: $("#attivita_dettaglio").val(),
    	ora_inizio: $("#attivita_ora_inizio").val(),
    	data: getDbDateFromPickrId("#attivita_data"),
    	operatore: $("#hidden_operatore").val(),
    	commento: $("#attivita_commento").val()
    }, function (data, status) {
    	oreFatteReloadTables();
    });
    $("#docente_attivita_modal").modal("hide");
}

function oreFatteDeleteAttivita(id) {
    var conf = confirm("Sei sicuro di volere cancellare questa attività ?");
    if (conf == true) {
        $.post("oreFatteAttivitaDelete.php", {
                id: id
            },
            function (data, status) {
            	oreFatteReloadTables();
            }
        );
    }
}

function oreAttribuiteSommario() {
	$.get("oreAttribuiteReadSommarioAttivita.php", {}, function (data, status) {
		$(".sommario_attivita_records_content").html(data);
	});

	$("#docente_sommario_modal").modal("show");
}

function oreFatteSommario() {
	$.get("oreFatteReadSommarioAttivita.php", {}, function (data, status) {
		$(".sommario_attivita_records_content").html(data);
	});

	$("#docente_sommario_modal").modal("show");
}

//---------------------------- START Corso di Recupero ----------------------------------------

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
    	if (data !== '') {
    		// bootbox.alert(data);
    	}
		oreFatteReloadTables();
    });
    $("#corso_di_recupero_modal").modal("hide");
}

//----------------------------   END Corso di Recupero ----------------------------------------

//---------------------------- START CLIL ----------------------------------------

function oreFatteClilGetAttivita(attivita_id) {
	$("#hidden_ore_fatte_clil_attivita_id").val(attivita_id);
	if (attivita_id > 0) {
		$.post("oreFatteClilAttivitaReadDetails.php", {
				attivita_id: attivita_id
			},
			function (dati, status) {
				// console.log(dati);
				var attivita = JSON.parse(dati);
				var con_studenti = attivita.con_studenti == 0 ? 1 : 2;
				$('#attivita_clil_tipo_attivita').selectpicker('val', con_studenti);
				setOre('#attivita_clil_ore', attivita.ore);
				$("#attivita_clil_dettaglio").val(attivita.dettaglio);
				$("#attivita_clil_ora_inizio").val(attivita.ora_inizio);
				$("#attivita_clil_ora_inizio").prop('defaultValue', attivita.ora_inizio);
				setDbDateToPickr(attivita_clil_data_pickr, attivita.data);
				$("#attivita_clil_commento").val(attivita.commento);
			}
		);
	} else {
		$("#attivita_clil_tipo_attivita").val('');
		$('#attivita_clil_tipo_attivita').selectpicker('val', 0);
		setOre('#attivita_clil_ore', 2);
		$("#attivita_clil_dettaglio").val('');
		ora_inizio_clil_pickr.setDate(new Date());
		attivita_clil_data_pickr.setDate(Date.today().toString('d/M/yyyy'));
		if ($("#hidden_operatore").val() == 'dirigente') {
			var d = new Date();
			var strDate = d.getDate() + "/" + (d.getMonth()+1) + "/" + d.getFullYear();
			$("#attivita_clil_commento").val('Inserito da dirigente (' + strDate + ')');
		} else {
			$("#attivita_clil_commento").val('');
		}
	}
	if ($("#hidden_operatore").val() == 'dirigente') {
		$("#clil_commento-part").show();
	} else {
		$("#clil_commento-part").hide();
	}

	$("#_error-attivita_clil-part").hide();
	$("#docente_attivita_clil_modal").modal("show");
}

function attivitaFattaClilSave() {
	if ($("#attivita_clil_tipo_attivita").val() <= 0) {
		$("#_error-attivita_clil").text("Devi selezionare un tipo di attività");
		$("#_error-attivita_clil-part").show();
		return;
	}
	$("#_error-attivita_clil-part").hide();
	var con_studenti = $("#attivita_clil_tipo_attivita").val() == 1 ? false: true;

 	$.post("oreFatteClilSave.php", {
		docente_id: $("#hidden_docente_id").val(),
    	attivita_id: $("#hidden_ore_fatte_clil_attivita_id").val(),
    	con_studenti: con_studenti,
    	ore: getOre("#attivita_clil_ore"),
    	dettaglio: $("#attivita_clil_dettaglio").val(),
    	ora_inizio: $("#attivita_clil_ora_inizio").val(),
    	data: getDbDateFromPickrId("#attivita_clil_data"),
    	operatore: $("#hidden_operatore").val(),
    	commento: $("#attivita_clil_commento").val()
    }, function (data, status) {
    	oreFatteReloadTables();
    });
    $("#docente_attivita_clil_modal").modal("hide");
}

function oreFatteClilDeleteAttivita(id) {
    var conf = confirm("Sei sicuro di volere cancellare questa attività Clil ?");
    if (conf == true) {
        $.post("oreFatteClilAttivitaDelete.php", {
                id: id
            },
            function (data, status) {
            	oreFatteReloadTables();
            }
        );
    }
}

function oreFatteClilGetRegistroAttivita(attivita_id, registro_id) {
	$("#hidden_ore_fatte_registro_id").val(registro_id);
	$("#hidden_ore_fatte_attivita_id").val(attivita_id);
	$("#hidden_registro_clil").val('clil');
	// console.log('clil: attivita_id=' + attivita_id + ' registro_id=' + registro_id);
	$.post("oreFatteClilAttivitaReadRegistro.php", {
			attivita_id: attivita_id
		},
		function (dati, status) {
			// console.log(dati);
			var attivita = JSON.parse(dati);
			$("#registro_tipo_attivita").html('<p class="form-control-static">' + 'CLIL' + '</p>');
			$("#registro_attivita_dettaglio").html('<p class="form-control-static">' + attivita.dettaglio + '</p>');
			$("#registro_attivita_data").html('<p class="form-control-static">' + Date.parseExact(attivita.data, 'yyyy-MM-dd').toString('d/M/yyyy') + '</p>');
			$("#registro_attivita_ora_inizio").html('<p class="form-control-static">' + attivita.ora_inizio + '</p>');
			$("#registro_attivita_ore").html('<p class="form-control-static">' + attivita.ore + '</p>');
			if (registro_id > 0) {
				$("#registro_descrizione").val(attivita.descrizione);
				$("#registro_studenti").val(attivita.studenti);
			} else {
				$("#registro_descrizione").val('');
				$("#registro_studenti").val('');
			}
		}
	);

	$("#docente_registro_modal").modal("show");
}

function oreFatteClilSommario() {
	$.get("oreFatteClilReadSommarioAttivita.php", {}, function (data, status) {
		$(".sommario_attivita_records_content").html(data);
	});

	$("#docente_sommario_modal").modal("show");
}

//----------------------------   END CLIL ----------------------------------------

//----------------------------   START DIARIA ------------------------------------

function diariaFattaGetDetails(id) {
	$("#hidden_diaria_id").val(id);
	if (id > 0) {
		$.post("../docente/viaggioDiariaFattaReadDetails.php", {
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
				setDbDateToPickr(diaria_data_pickr, diaria.data_partenza);
			}
		);
	} else {
		$("#diaria_descrizione").val('');
		$("#diaria_giorni_senza_pernottamento").val('');
		$("#diaria_giorni_con_pernottamento").val('');
		$("#diaria_commento").val('');
		setOre('#diaria_ore', 0);
		diaria_data_pickr.setDate(Date.today().toString('d/M/yyyy'));
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

	$.post("../docente/viaggioDiariaFattaSave.php", {
    	id: $("#hidden_diaria_id").val(),
		docente_id: $("#hidden_docente_id").val(),
		operatore: $("#hidden_operatore").val(),
		data_partenza: getDbDateFromPickrId("#diaria_data"),
    	descrizione: $("#diaria_descrizione").val(),
    	giorni_senza_pernottamento: $("#diaria_giorni_senza_pernottamento").val(),
		giorni_con_pernottamento: $("#diaria_giorni_con_pernottamento").val(),
		ore: getOre('#diaria_ore'),
    	commento: $("#diaria_commento").val()
    }, function (data, status) {
		oreFatteReloadTables();
    });
    $("#diaria_modal").modal("hide");
}

function diariaFattaDelete(id) {
	if ($("#hidden_operatore").val() == 'dirigente') {
		diariaFattaGetDetails(id);
		bootbox.confirm({
			title: "Cancellazione Diaria Fatta",
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
		var conf = confirm("Sei sicuro di volere cancellare questa diaria Fatta ?");
		if (conf == true) {
			$.post("../docente/viaggioDiariaFattaDelete.php", {
				docente_id: $("#hidden_docente_id").val(),
				id: id
				},
				function (data, status) {
					oreFatteReloadTables();
				}
			);
		}
	}
}

//----------------------------   END DIARIA ----------------------------------------

// --------------------------- Email, Rivisto, Chiudi ------------------------------

function fatteEmail() {
	$.post("../dirigente/emailNotificaDocente.php", {
		docente_id: $("#hidden_docente_id").val(),
		oggetto_modifica: "Ore Fatte"
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

function fatteRivisto() {
	$.post("../dirigente/rivistoUltimoControllo.php", {
		docente_id: $("#hidden_docente_id").val(),
		tabella: "ore_fatte"
	},
	function (data, status) {
		var tzoffset = (new Date()).getTimezoneOffset() * 60000;
		var localISOTime = (new Date(Date.now() - tzoffset)).toISOString().slice(0, -1);
		var ultimo_controllo = localISOTime.replace('T', ' ');
		$("#hidden_ultimo_controllo").val(ultimo_controllo);
		oreFatteReloadTables();
		$.notify({
			icon: 'glyphicon glyphicon-ok',
			title: '<Strong>FUIS</Strong></br>',
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

function fatteChiudi() {
	$.notify({
		icon: 'glyphicon glyphicon-off',
		title: '<Strong>Chiusura FUIS</Strong></br>',
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

$(document).ready(function () {
	attivita_data_pickr = flatpickr("#attivita_data", {
		locale: {
			firstDayOfWeek: 1
		},
		dateFormat: 'j/n/Y'
	});
	
	diaria_data_pickr = flatpickr("#diaria_data", {
		locale: {
			firstDayOfWeek: 1
		},
		dateFormat: 'j/n/Y'
	});
	
	ora_inizio_pickr = flatpickr("#attivita_ora_inizio", {
	    enableTime: true,
	    noCalendar: true,
	    dateFormat: "H:i",
	    time_24hr: true,
	    static: true
	});

	attivita_clil_data_pickr = flatpickr("#attivita_clil_data", {
		locale: {
			firstDayOfWeek: 1
		},
		dateFormat: 'j/n/Y'
	});
	
	ora_inizio_clil_pickr = flatpickr("#attivita_clil_ora_inizio", {
	    enableTime: true,
	    noCalendar: true,
	    dateFormat: "H:i",
	    time_24hr: true,
	    static: true
	});

	flatpickr.localize(flatpickr.l10ns.it);

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
		oreFatteReloadTables();
	});

	// questi campi potrebbero essere gestiti in minuti se settato nel json
	campiInMinuti(
		'#attivita_ore',
		'#attivita_clil_ore',
		'#diaria_ore'
	);
});
