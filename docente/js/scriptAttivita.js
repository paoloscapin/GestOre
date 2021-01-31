/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function setDbDateToPickr(pickr, data_str) {
	var data = Date.parseExact(data_str, 'yyyy-MM-dd');
	pickr.setDate(data);
}

function getDbDateFromPickrId(pickrId) {
	var data_str = $(pickrId).val();
	var data_date = Date.parseExact(data_str, 'd/M/yyyy');
	return data_date.toString('yyyy-MM-dd');
}

// genera un messaggio se le ore vengono compensate tra funzionali e con studenti
function messaggioCompensate(dovuteFunzionali, dovuteConStudenti, oreFunzionali, oreConStudenti) {
	var accetta_con_studenti_per_funzionali = $('#accetta_con_studenti_per_funzionali').val();
	var accetta_funzionali_per_con_studenti = $('#accetta_funzionali_per_con_studenti').val();
	var messaggio = "";
    var bilancioFunzionali = oreFunzionali - dovuteFunzionali;
    var bilancioConStudenti = oreConStudenti - dovuteConStudenti;

	if (accetta_con_studenti_per_funzionali != 0) {
		if (bilancioFunzionali < 0 && bilancioConStudenti > 0) {
			var daSpostare = -bilancioFunzionali;
			// se non ce ne sono abbastanza con studenti, sposta tutte quelle che ci sono
			if (bilancioConStudenti < daSpostare) {
				daSpostare = bilancioConStudenti;
			}
			bilancioConStudenti = bilancioConStudenti - daSpostare;
            bilancioFunzionali = bilancioFunzionali + daSpostare;
            messaggio = "" + daSpostare + " ore con studenti verranno spostate per coprire " + daSpostare + " ore funzionali mancanti. ";
		}
	}

	if (accetta_funzionali_per_con_studenti != 0) {
		if (bilancioConStudenti < 0 && bilancioFunzionali > 0) {
			var daSpostare = -bilancioConStudenti;
			// se non ce ne sono abbastanza con studenti, sposta tutte quelle che ci sono
			if (bilancioFunzionali < daSpostare) {
				daSpostare = bilancioFunzionali;
			}
			bilancioFunzionali = bilancioFunzionali - daSpostare;
			bilancioConStudenti = bilancioConStudenti + daSpostare;
			if (messaggio.length != 0) {
				messaggio = messaggio + "</br>";
			}
            messaggio = "" + daSpostare + " ore funzionali verranno spostate per coprire " + daSpostare + " ore con studenti mancanti. ";
		}
	}

	return messaggio;
}

// genera un messaggio se ore funzionali o con studenti fatte sono maggiori di quelle previste
function messaggioEccesso(dovuteFunzionali, dovuteConStudenti, previsteFunzionali, previsteConStudenti, fatteFunzionali, fatteConStudenti) {
	var segnala_fatte_eccedenti_previsione = $('#segnala_fatte_eccedenti_previsione').val();
	var messaggio = "";
    var bilancioPrevisteFunzionali = previsteFunzionali - dovuteFunzionali;
    var bilancioPrevisteConStudenti = previsteConStudenti - dovuteConStudenti;
    var bilancioFatteFunzionali = fatteFunzionali - dovuteFunzionali;
    var bilancioFatteConStudenti = fatteConStudenti - dovuteConStudenti;

	if (segnala_fatte_eccedenti_previsione != 0) {
		if (bilancioFatteFunzionali > 0 && bilancioFatteFunzionali > bilancioPrevisteFunzionali) {
			var bilancioDifferenzaFunzionali = bilancioFatteFunzionali - Math.max(bilancioPrevisteFunzionali,0);
            messaggio = messaggio + bilancioDifferenzaFunzionali + " ore funzionali non concordate non saranno incluse nel conteggio FUIS. ";
		}
		if (bilancioFatteConStudenti > 0 && bilancioFatteConStudenti > bilancioPrevisteConStudenti) {
			var bilancioDifferenzaConStudenti = bilancioFatteConStudenti - Math.max(bilancioPrevisteConStudenti,0);
			if (messaggio.length != 0) {
				messaggio = messaggio + "</br>";
			}
            messaggio = messaggio + bilancioDifferenzaConStudenti + " ore con studenti non concordate non saranno incluse nel conteggio FUIS. ";
		}

		if (messaggio.length > 0) {
			messaggio = messaggio + "</br>";
			messaggio = messaggio + "Contattare il Dirigente Scolastico.";
		}
	}
	return messaggio;
}

function oreFatteReadRecords() {
	var ore_dovute, ore_previste, ore_fatte;

	$.post("oreDovuteReadDetails.php", {
		table_name: 'ore_dovute'
	},
	function (dati, status) {
		// console.log(dati);
        ore_dovute = JSON.parse(dati);
		var dovute_con_studenti_totale = parseFloat(ore_dovute.ore_70_con_studenti) + parseFloat(ore_dovute.ore_40_con_studenti);
		$("#dovute_ore_40_sostituzioni_di_ufficio").html(getHtmlNum(ore_dovute.ore_40_sostituzioni_di_ufficio));
		$("#dovute_ore_40_aggiornamento").html(getHtmlNum(ore_dovute.ore_40_aggiornamento));
		$("#dovute_ore_70_funzionali").html(getHtmlNum(ore_dovute.ore_70_funzionali));
		$("#dovute_totale_con_studenti").html(getHtmlNum(dovute_con_studenti_totale));
		$.post("oreDovuteReadDetails.php", {
			table_name: 'ore_previste'
		},
		function (dati, status) {
			// console.log(dati);
			ore_previste = JSON.parse(dati);
            var previste_con_studenti_totale = parseFloat(ore_previste.ore_70_con_studenti) + parseFloat(ore_previste.ore_40_con_studenti);
			$("#previste_ore_40_aggiornamento").html(getHtmlNumAndPrevisteVisual(ore_previste.ore_40_aggiornamento,ore_dovute.ore_40_aggiornamento));
			$("#previste_ore_70_funzionali").html(getHtmlNumAndPrevisteVisual(ore_previste.ore_70_funzionali,ore_dovute.ore_70_funzionali));
			$("#previste_totale_con_studenti").html(getHtmlNumAndPrevisteVisual(previste_con_studenti_totale,dovute_con_studenti_totale));
			$.post("oreDovuteReadDetails.php", {
				table_name: 'ore_fatte'
			},
			function (dati, status) {
				// console.log(dati);
				ore_fatte = JSON.parse(dati);
                var fatte_con_studenti_totale = parseFloat(ore_fatte.ore_70_con_studenti) + parseFloat(ore_fatte.ore_40_con_studenti);
				$("#fatte_ore_40_sostituzioni_di_ufficio").html(getHtmlNumAndFatteVisual(ore_fatte.ore_40_sostituzioni_di_ufficio,ore_dovute.ore_40_sostituzioni_di_ufficio));
				$("#fatte_ore_40_aggiornamento").html(getHtmlNumAndFatteVisual(ore_fatte.ore_40_aggiornamento,ore_dovute.ore_40_aggiornamento));
                $("#fatte_ore_70_funzionali").html(getHtmlNumAndFatteVisual(ore_fatte.ore_70_funzionali,ore_dovute.ore_70_funzionali));
				$("#fatte_totale_con_studenti").html(getHtmlNumAndFatteVisual(fatte_con_studenti_totale,dovute_con_studenti_totale));

				// messaggio
				var messaggio = messaggioCompensate(ore_dovute.ore_70_funzionali, dovute_con_studenti_totale, ore_fatte.ore_70_funzionali, fatte_con_studenti_totale);
				// console.log('messaggio compensate: ' + messaggio);
				if (messaggio.length > 0) {
					$("#ore_message").html(messaggio);
					$('#ore_message').css({ 'font-weight': 'bold' });
					$('#ore_message').css({ 'text-align': 'center' });
					$('#ore_message').css({ 'background-color': '#BAEED0' });
					$("#ore_message").removeClass('hidden');
				} else {
					$("#ore_message").addClass('hidden');
				}

				// messaggio eccesso
				messaggio = messaggioEccesso(ore_dovute.ore_70_funzionali, dovute_con_studenti_totale, ore_previste.ore_70_funzionali, previste_con_studenti_totale, ore_fatte.ore_70_funzionali, fatte_con_studenti_totale);
				if (messaggio.length > 0) {
					$("#ore_eccesso_message").html(messaggio);
					$('#ore_eccesso_message').css({ 'font-weight': 'bold' });
					$('#ore_eccesso_message').css({ 'text-align': 'center' });
					$('#ore_eccesso_message').css({ 'background-color': '#FFC6B4' });
					$("#ore_eccesso_message").removeClass('hidden');
				} else {
					$("#ore_eccesso_message").addClass('hidden');
				}

				$.post("oreDovuteClilReadDetails.php", {
					table_name: 'ore_fatte_attivita_clil'
				},
				function (dati, status) {
					// console.log(dati);
					ore_clil = JSON.parse(dati);
					$("#clil_previste_funzionali").html(getHtmlNumAndPrevisteVisual(ore_clil.funzionali_previste, 0));
					$("#clil_previste_con_studenti").html(getHtmlNumAndPrevisteVisual(ore_clil.con_studenti_previste, 0));
					$("#clil_fatte_funzionali").html(getHtmlNumAndFatteVisual(ore_clil.funzionali,ore_clil.funzionali_previste));
					$("#clil_fatte_con_studenti").html(getHtmlNumAndFatteVisual(ore_clil.con_studenti,ore_clil.con_studenti_previste));
					if (parseInt(ore_clil.funzionali,10) + parseInt(ore_clil.con_studenti,10) + parseInt(ore_clil.funzionali_previste,10) + parseInt(ore_clil.con_studenti_previste,10) == 0) {
						$(".clil").addClass('hidden');
						$(".NOclil").removeClass('hidden');
					} else {
						$(".clil").removeClass('hidden');
						$(".NOclil").addClass('hidden');
					}
				});
			});
		});
	});
}

function corsoDiRecuperoPrevisteReadRecords() {
	$.post("../docente/corsoDiRecuperoPrevisteReadRecords.php", {
		operatore: $("#hidden_operatore").val(),
		ultimo_controllo: $("#hidden_ultimo_controllo").val()
	},
	function (data, status) {
		$(".corso_di_recupero_records_content").html(data);
	});
}

function oreFatteReadAttivitaRecords() {
	oreFatteReadRecords();

	$.get("oreFatteReadAttivita.php", {}, function (data, status) {
		$(".attivita_fatte_records_content").html(data);
	});
	$.get("oreFatteClilReadAttivita.php", {}, function (data, status) {
		$(".attivita_fatte_clil_records_content").html(data);
	});
	$.get("oreFatteReadGruppi.php", {}, function (data, status) {
		$(".attivita_fatte_gruppi_records_content").html(data);
	});
	$.get("oreFatteReadAttribuite.php", {}, function (data, status) {
		$(".attribuite_records_content").html(data);
	});
	$.get("oreFatteReadSostituzioni.php", {}, function (data, status) {
		$(".sostituzioni_records_content").html(data);
	});
	$.get("oreFatteReadViaggi.php", {}, function (data, status) {
		$(".viaggi_records_content").html(data);
	});
	corsoDiRecuperoPrevisteReadRecords();
	fuisAggiornaDocente();
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
	    	oreFatteReadAttivitaRecords();
	    });
	    $("#docente_registro_modal").modal("hide");
	} else {
	 	$.post("oreFatteUpdateRegistro.php", {
	    	registro_id: $("#hidden_ore_fatte_registro_id").val(),
	    	attivita_id: $("#hidden_ore_fatte_attivita_id").val(),
	    	descrizione: $("#registro_descrizione").val(),
	    	studenti: $("#registro_studenti").val()
	    }, function (data, status) {
	    	oreFatteReadAttivitaRecords();
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
    	oreFatteReadAttivitaRecords();
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
			}
		);
	} else {
		$("#attivita_tipo_attivita").val('');
		$('#attivita_tipo_attivita').selectpicker('val', 0);
		setOre('#attivita_ore', 2);
		$("#attivita_dettaglio").val('');
		ora_inizio_pickr.setDate(new Date());
		attivita_data_pickr.setDate(Date.today().toString('d/M/yyyy'));
	}

	$("#_error-attivita-part").hide();
	$("#docente_attivita_modal").modal("show");
}

function attivitaFattaUpdateDetails() {
	if ($("#attivita_tipo_attivita").val() <= 0) {
		$("#_error-attivita").text("Devi selezionare un tipo di attività");
		$("#_error-attivita-part").show();
		return;
	}
	$("#_error-attivita-part").hide();

 	$.post("oreFatteUpdateAttivita.php", {
    	attivita_id: $("#hidden_ore_fatte_attivita_id").val(),
    	tipo_attivita_id: $("#attivita_tipo_attivita").val(),
    	ore: getOre("#attivita_ore"),
    	dettaglio: $("#attivita_dettaglio").val(),
    	ora_inizio: $("#attivita_ora_inizio").val(),
    	data: getDbDateFromPickrId("#attivita_data")
    }, function (data, status) {
    	oreFatteReadAttivitaRecords();
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
            	oreFatteReadAttivitaRecords();
            }
        );
    }
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
    		bootbox.alert(data);
    	}
    	corsoDiRecuperoPrevisteReadRecords();
		oreFatteReadRecords();
		// TODO: insert for dirigente if used fuisFatteAggiornaDocente();
    });
    $("#corso_di_recupero_modal").modal("hide");
}

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
				setOre('#attivita_clil_ore', attivita.ore);
				$("#attivita_clil_dettaglio").val(attivita.dettaglio);
				$("#attivita_clil_ora_inizio").val(attivita.ora_inizio);
				$("#attivita_clil_ora_inizio").prop('defaultValue', attivita.ora_inizio);
				setDbDateToPickr(attivita_clil_data_pickr, attivita.data);
			}
		);
	} else {
		$("#attivita_clil_tipo_attivita").val('');
		$('#attivita_clil_tipo_attivita').selectpicker('val', 0);
		setOre('#attivita_clil_ore', 2);
		$("#attivita_clil_dettaglio").val('');
		ora_inizio_clil_pickr.setDate(new Date());
		attivita_clil_data_pickr.setDate(Date.today().toString('d/M/yyyy'));
	}

	// Open modal popup
	$("#docente_attivita_clil_modal").modal("show");
}

function attivitaFattaClilUpdateDetails() {
 	$.post("oreFatteClilUpdateAttivita.php", {
    	attivita_id: $("#hidden_ore_fatte_clil_attivita_id").val(),
    	con_studenti: $("#clil_con_studenti").is(':checked'),
    	ore: getOre("#attivita_clil_ore"),
    	dettaglio: $("#attivita_clil_dettaglio").val(),
    	ora_inizio: $("#attivita_clil_ora_inizio").val(),
    	data: getDbDateFromPickrId("#attivita_clil_data")
    }, function (data, status) {
    	oreFatteReadAttivitaRecords();
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
            	oreFatteReadAttivitaRecords();
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


function fuisAggiornaDocente() {
	if ($("#hidden_operatore").val() != 'dirigente') {
		return;
	}

	$.post("../dirigente/fuisFatteCalcolaDocente.php", {
		docente_id: $("#hidden_docente_id").val()
	},
	function (dati, status) {
		fuisPrevisto = JSON.parse(dati);
		$("#fuis_assegnato").html(number_format(fuisPrevisto.assegnato,2));
		$("#fuis_ore").html(number_format(fuisPrevisto.ore,2));
		$("#fuis_diaria").html(number_format(fuisPrevisto.diaria,2));

		$("#fuis_clil_funzionali").html(number_format(fuisPrevisto.clilFunzionale,2));
		$("#fuis_clil_con_studenti").html(number_format(fuisPrevisto.clilConStudenti,2));

		$("#fuis_corsi_di_recupero").html(number_format(fuisPrevisto.extraCorsiDiRecupero,2));

		// totali
		$("#fuis_docente_totale").html(number_format(parseFloat(fuisPrevisto.assegnato) + parseFloat(fuisPrevisto.ore) + parseFloat(fuisPrevisto.diaria),2));
		$("#fuis_clil_totale").html(number_format(parseFloat(fuisPrevisto.clilFunzionale) + parseFloat(fuisPrevisto.clilConStudenti), 2));
		$("#fuis_corsi_di_recupero_totale").html(number_format(fuisPrevisto.extraCorsiDiRecupero,2));
		$('#fuis_docente_totale').css({ 'font-weight': 'bold' });
		$('#fuis_clil_totale').css({ 'font-weight': 'bold' });
		$('#fuis_corsi_di_recupero_totale').css({ 'font-weight': 'bold' });

		// messaggio
		if (fuisPrevisto.messaggio.length > 0) {
			$("#fuis_message").html(fuisPrevisto.messaggio);
			$('#fuis_message').css({ 'font-weight': 'bold' });
			$('#fuis_message').css({ 'text-align': 'center' });
			$('#fuis_message').css({ 'background-color': '#FFC6B4' });
			$("#fuis_message").removeClass('hidden');
		} else {
			$("#fuis_message").addClass('hidden');
		}

		// messaggio eccesso
		if (fuisPrevisto.messaggioEccesso.length > 0) {
			$("#fuis_messageEccesso").html(fuisPrevisto.messaggioEccesso);
			$('#fuis_messageEccesso').css({ 'font-weight': 'bold' });
			$('#fuis_messageEccesso').css({ 'text-align': 'center' });
			$('#fuis_messageEccesso').css({ 'background-color': '#FFC6B4' });
			$("#fuis_messageEccesso").removeClass('hidden');
		} else {
			$("#fuis_messageEccesso").addClass('hidden');
		}
	});
}

$(document).ready(function () {
	attivita_data_pickr = flatpickr("#attivita_data", {
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

	oreFatteReadAttivitaRecords();

	fuisAggiornaDocente();

	// questi campi potrebbero essere gestiti in minuti se settato nel json
	campiInMinuti(
		'#attivita_ore',
		'#attivita_clil_ore'
	);
});
