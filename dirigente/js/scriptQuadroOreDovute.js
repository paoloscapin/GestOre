/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var warning = '<span class="glyphicon glyphicon-warning-sign text-error"></span>';
var okSymbol = '&ensp;<span class="glyphicon glyphicon-ok text-success"></span>';

function getHtmlNum(value) {
	return '&emsp;' + ((value >= 10) ? value : '&ensp;' + value);
}

function getHtmlNumAndPrevisteVisual(value, total) {
	var numString = (value >= 10) ? value : '&ensp;' + value;
	var diff = total - value;
	if (diff > 0) {
		numString += '&ensp;<span class="label label-warning">- '+ diff +'</span>';
	} else if (diff < 0) {
			numString += '&ensp;<span class="label label-danger">+ '+ (-diff) +'</span>';
	} else {
		numString += okSymbol;
	}
	return '&emsp;' + numString;
}

function getHtmlNumAndFatteVisual(value, total) {
	var numString = (value >= 10) ? value : '&ensp;' + value;
	var diff = total - value;
	if (diff > 0) {
		numString += '&ensp;<span class="label label-warning">- '+ diff +'</span>';
	} else if (diff < 0) {
			numString += '&ensp;<span class="label label-danger">+ '+ (-diff) +'</span>';
	} else {
		numString += okSymbol;
	}
	return '&emsp;' + numString;
}

function getHtmlNumAndFacoltativeVisual(value, total) {
	return '&emsp;' + ((value >= 10) ? value : '&ensp;' + value);
}

function getHtmlNumAndFatte80Visual(value, total) {
	return '&emsp;' + ((value >= 10) ? value : '&ensp;' + value);
}

function number_format (number, decimals, decPoint, thousandsSep) {
	  number = (number + '').replace(/[^0-9+\-Ee.]/g, '')
	  var n = !isFinite(+number) ? 0 : +number
	  var prec = !isFinite(+decimals) ? 0 : Math.abs(decimals)
	  var sep = (typeof thousandsSep === 'undefined') ? ',' : thousandsSep
	  var dec = (typeof decPoint === 'undefined') ? '.' : decPoint
	  var s = ''

	  var toFixedFix = function (n, prec) {
	    if (('' + n).indexOf('e') === -1) {
	      return +(Math.round(n + 'e+' + prec) + 'e-' + prec)
	    } else {
	      var arr = ('' + n).split('e')
	      var sig = ''
	      if (+arr[1] + prec > 0) {
	        sig = '+'
	      }
	      return (+(Math.round(+arr[0] + 'e' + sig + (+arr[1] + prec)) + 'e-' + prec)).toFixed(prec)
	    }
	  }

	  // @todo: for IE parseFloat(0.55).toFixed(0) = 0;
	  s = (prec ? toFixedFix(n, prec).toString() : '' + Math.round(n)).split('.')
	  if (s[0].length > 3) {
	    s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep)
	  }
	  if ((s[1] || '').length < prec) {
	    s[1] = s[1] || ''
	    s[1] += new Array(prec - s[1].length + 1).join('0')
	  }

	  return s.join(dec)
}

// ----------------------------------------------------------------------------------------------

function fuisEmail() {
	$.post("fuisEmailDocente.php", {
		docente_id: $("#hidden_docente_id").val()
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

function fuisRivisto() {
	$.post("fuisDocenteRivisto.php", {
		docente_id: $("#hidden_docente_id").val()
	},
	function (data, status) {
		var tzoffset = (new Date()).getTimezoneOffset() * 60000;
		var localISOTime = (new Date(Date.now() - tzoffset)).toISOString().slice(0, -1);
		var ultimo_controllo = localISOTime.replace('T', ' ');
		$("#hidden_ultimo_controllo").val(ultimo_controllo);
		viewAttivitaFatte();
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

function fuisChiudi() {
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

function viewAttivitaPreviste(id, docente) {
	$.post("../docente/previsteReadRecords.php", {
			docente_id: id
		},
		function (data, status) {
			$(".attivita_previste_records_content").html(data);
			$("#myModalPrevisteTitleLabel").text('Attività Previste (' + docente + ')');
		    $("#previste_modal").modal("show");
		});
	$.post("../docente/previsteReadSommarioAttivita.php", {
		docente_id: id
	},
	function (data, status) {
		$(".sommario_attivita_previste_records_content").html(data);
	});
}

function viewQuadroOrario() {
	var ore_dovute, ore_previste, ore_fatte;

	$.post("../docente/oreDovuteReadDetails.php", {
		docente_id: $("#hidden_docente_id").val(),
		table_name: 'ore_dovute'
	},
	function (dati, status) {
		// console.log(dati);
		ore_dovute = JSON.parse(dati);

		$.post("../docente/oreDovuteReadDetails.php", {
			docente_id: $("#hidden_docente_id").val(),
			table_name: 'ore_fatte'
		},
		function (dati, status) {
			// console.log(dati);
			ore_fatte = JSON.parse(dati);
			var ore_fatte_ore_40_aggiornamento = parseInt(ore_fatte.ore_40_aggiornamento);
			var ore_dovute_ore_40_aggiornamento = parseInt(ore_dovute.ore_40_aggiornamento);
			// aggiornamento non genera extra
			if (ore_fatte_ore_40_aggiornamento > ore_dovute_ore_40_aggiornamento) {
				ore_fatte_ore_40_aggiornamento = ore_dovute_ore_40_aggiornamento;
			}
			$("#fatte_ore_70_funzionali").html(getHtmlNumAndFatteVisual(ore_fatte.ore_70_funzionali,ore_dovute.ore_70_funzionali));
			$("#fatte_ore_70_con_studenti").html(getHtmlNumAndFatteVisual(ore_fatte.ore_70_con_studenti,ore_dovute.ore_70_con_studenti));
	
			$("#fatte_ore_40_sostituzioni_di_ufficio").html(getHtmlNumAndFatteVisual(ore_fatte.ore_40_sostituzioni_di_ufficio,ore_dovute.ore_40_sostituzioni_di_ufficio));
			$("#fatte_ore_40_aggiornamento").html(getHtmlNumAndFatteVisual(ore_fatte_ore_40_aggiornamento,ore_dovute_ore_40_aggiornamento));
			$("#fatte_ore_40_con_studenti").html(getHtmlNumAndFatteVisual(ore_fatte.ore_40_con_studenti,ore_dovute.ore_40_con_studenti));
			$.post("../docente/oreDovuteClilReadDetails.php", {
				docente_id: $("#hidden_docente_id").val(),
				table_name: 'ore_fatte_attivita_clil'
			},
			function (dati, status) {
				// console.log(dati);
				ore_fatte_clil = JSON.parse(dati);
				$("#clil_fatte_funzionali").html(getHtmlNumAndFatteVisual(ore_fatte_clil.funzionali,0));
				$("#clil_fatte_con_studenti").html(getHtmlNumAndFatteVisual(ore_fatte_clil.con_studenti,0));
				if (parseInt(ore_fatte_clil.funzionali,0) + parseInt(ore_fatte_clil.con_studenti,0) == 0) {
					$("#panel-clil").addClass('hidden');
				} else {
					$("#panel-clil").removeClass('hidden');
				}
			});
		});
	});
}

function viewFuis() {
	var id = $("#hidden_docente_id").val();
	
	$.post("fuisDocentiCalcolaUno.php", {
		docente_id: id
	},
	function (data, status) {
		// console.log(data);
		$.post("fuisDocentiLoadRecord.php", {
			docente_id: id
		},
		function (data, status) {
			// console.log(data);
			fuis = JSON.parse(data);
			$("#sostituzioni_ore").html(number_format(fuis.sostituzioni_ore,0));
			$("#funzionale_ore").html(number_format(fuis.funzionale_ore,0));
			$("#con_studenti_ore").html(number_format(fuis.con_studenti_ore,0));
			$("#clil_funzionale_ore").html(number_format(fuis.clil_funzionale_ore,0));
			$("#clil_con_studenti_ore").html(number_format(fuis.clil_con_studenti_ore,0));

			$("#sostituzioni_proposto").html(number_format(fuis.sostituzioni_proposto,2));
			$("#funzionale_proposto").html(number_format(fuis.funzionale_proposto,2));
			$("#con_studenti_proposto").html(number_format(fuis.con_studenti_proposto,2));
			$("#clil_funzionale_proposto").html(number_format(fuis.clil_funzionale_proposto,2));
			$("#clil_con_studenti_proposto").html(number_format(fuis.clil_con_studenti_proposto,2));
			$("#totale_proposto").html(number_format(fuis.totale_proposto,2));
			$("#clil_totale_proposto").html(number_format(fuis.clil_totale_proposto,2));
			$("#assegnato_proposto").html(number_format(fuis.assegnato,2));

			$("#sostituzioni_approvato").html(number_format(fuis.sostituzioni_approvato,2));
			$("#funzionale_approvato").html(number_format(fuis.funzionale_approvato,2));
			$("#con_studenti_approvato").html(number_format(fuis.con_studenti_approvato,2));
			$("#clil_funzionale_approvato").html(number_format(fuis.clil_funzionale_approvato,2));
			$("#clil_con_studenti_approvato").html(number_format(fuis.clil_con_studenti_approvato,2));
			$("#totale_approvato").html(number_format(fuis.totale_approvato,2));
			$("#clil_totale_approvato").html(number_format(fuis.clil_totale_approvato,2));
			$("#assegnato_approvato").html(number_format(fuis.assegnato,2));

			$("#fuis_totale_da_pagare_id").html('<strong>Totale da pagare: € ' + number_format(fuis.totale_da_pagare,2) + '</strong>');
		});
	});
}

function viewAttivitaFatte() {
	var id = $("#hidden_docente_id").val();
	var nome = $("#hidden_docente_nome").val();
	var ultimo_controllo = $("#hidden_ultimo_controllo").val();
	
	$.post("../docente/oreFatteReadAttivita.php", {
		docente_id: id,
		ultimo_controllo: ultimo_controllo
	},
	function (data, status) {
		$(".attivita_fatte_records_content").html(data);
	});
	$.post("../docente/oreFatteReadSommarioAttivita.php", {
		docente_id: id
	},
	function (data, status) {
		$(".sommario_attivita_records_content").html(data);
	});

	$.post("../docente/oreFatteClilReadAttivita.php", {
		docente_id: id,
		ultimo_controllo: ultimo_controllo
	},
	function (data, status) {
		$(".attivita_fatte_clil_records_content").html(data);
	});
	$.post("../docente/oreFatteClilReadSommarioAttivita.php", {
		docente_id: id
	},
	function (data, status) {
		$(".sommario_attivita_clil_records_content").html(data);
	});

	$.post("../docente/oreFatteReadAttribuite.php", {
		docente_id: id
	},
	function (data, status) {
		$(".attribuite_records_content").html(data);
	});

	$.post("../docente/oreFatteReadViaggi.php", {
		docente_id: id
	},
	function (data, status) {
		$(".viaggi_records_content").html(data);
	});
	viewQuadroOrario();
}

function oreFatteAggiornaStatoAttivita(attivita_id, commento, contestata, clilmode) {
	$.post("oreFatteAggiornaStatoAttivita.php", {
		attivita_id: attivita_id,
		docente_id: $("#hidden_docente_id").val(),
		contestata: contestata,
		commento: commento,
		clilmode: clilmode
	},
	function (data, status) {
		viewAttivitaFatte();
		viewFuis();
	}
	);
}

function oreFatteRipristrinaAttivita(attivita_id, dettaglio, ore, commento, clilmode) {
    bootbox.confirm({
	    message: "<p><strong>Attività:</strong></br>" + dettaglio + "</p>"
	    		+ "<p><strong>Commento:</strong></br>" + commento + "</p>"
	    		+ "<hr style=\"border-top: 2px solid #6699ff;\">"
	    		+ "<p>Vuoi ripristinare questa attività e rimuovere il commento?</p>",
	    buttons: {
	        confirm: {
	            label: 'Si',
	            className: 'btn-success'
	        },
	        cancel: {
	            label: 'No',
	            className: 'btn-danger'
	        }
	    },
	    callback: function (result) {
	    	if (result === true) {
		        oreFatteAggiornaStatoAttivita(attivita_id, "ripristinata", false, clilmode);
		        viewAttivitaFatte();
	    	}
	    }
	});
}

function oreFatteControllaAttivita(attivita_id, dettaglio, ore, clilmode) {
	bootbox.prompt({
	    title: "<p>ore: " + ore + "</p><p>" + dettaglio + "</p>",
	    message: '<p>Seleziona il messaggio:</p>',
	    inputType: 'radio',
	    inputOptions: [
	    {
	        text: 'attività già inserita (duplicato)',
	        value: 'attività già inserita (duplicato)',
	    },
	    {
	        text: 'attività non concordata con DS',
	        value: 'attività non concordata con DS',
	    },
	    {
	        text: 'registro non compilato',
	        value: 'registro non compilato',
	    },
	    {
	        text: 'Altro (specificare)...',
	        value: '',
	    }
	    ],
	    callback: function (result) {
	    	if (result == null) {
	    		return;
	    	}
	    	if (result !== "") {
		        oreFatteAggiornaStatoAttivita(attivita_id, result, true, clilmode);
		        viewAttivitaFatte();
	    	} else {
	    		bootbox.prompt({
	    		    title: "<p>ore: " + ore + "</p><p>" + dettaglio + "</p>",
	    		    message: '<p>Inserire il commento:</p>',
	    		    inputType: 'textarea',
	    		    callback: function (commento) {
	    		    	if (commento != null) {
		    		        oreFatteAggiornaStatoAttivita(attivita_id, commento, true, clilmode);
		    		        viewAttivitaFatte();
	    		    	}
	    		    }
	    		});;
	    	}
	    }
	});
}

function oreFatteGetRegistroAttivita(attivita_id, registro_id) {
	$.post("../docente/oreFatteAttivitaReadDetails.php", {
			attivita_id: attivita_id
		},
		function (dati, status) {
			var attivita = JSON.parse(dati);
			$("#registro_tipo_attivita").html('<p class="form-control-static">' + attivita.nome + '</p>');
			$("#registro_attivita_dettaglio").html('<p class="form-control-static">' + attivita.dettaglio + '</p>');
			$("#registro_attivita_data").html('<p class="form-control-static">' + Date.parseExact(attivita.data, 'yyyy-MM-dd').toString('d/M/yyyy') + '</p>');
			$("#registro_attivita_ora_inizio").html('<p class="form-control-static">' + attivita.ora_inizio + '</p>');
			$("#registro_attivita_ore").html('<p class="form-control-static">' + attivita.ore + '</p>');
			$("#registro_descrizione").html('<p class="form-control-static" style="white-space: pre-wrap;">' + attivita.descrizione + '</p>');
			$("#registro_studenti").html('<p class="form-control-static" style="white-space: pre-wrap;">' + attivita.studenti + '</p>');
		}
	);

	$("#docente_registro_modal").modal("show");
}

function oreFatteClilGetRegistroAttivita(attivita_id, registro_id) {
	$.post("../docente/oreFatteClilAttivitaReadDetails.php", {
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
			$("#registro_descrizione").html('<p class="form-control-static" style="white-space: pre-wrap;">' + attivita.descrizione + '</p>');
			$("#registro_studenti").html('<p class="form-control-static" style="white-space: pre-wrap;">' + attivita.studenti + '</p>');
		}
	);

	$("#docente_registro_modal").modal("show");
}

$(document).ready(function () {
	viewAttivitaFatte();
	viewFuis();
});
