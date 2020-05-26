/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var warning = '<span class="glyphicon glyphicon-warning-sign text-error"></span>';
var okSymbol = '&ensp;<span class="glyphicon glyphicon-ok text-success"></span>';

function getHtmlNum(value) {
	return '&emsp;' + ((value >= 10) ? trasformaFloatInStringa(value) : '&ensp;' + trasformaFloatInStringa(value));
}

function getHtmlNumAndPrevisteVisual(value, total) {
	var numString = (value >= 10) ? trasformaFloatInStringa(value) : '&ensp;' + trasformaFloatInStringa(value);
	var diff = total - value;
	if (diff > 0) {
		numString += '&ensp;<span class="label label-warning">- '+ trasformaFloatInStringa(diff) +'</span>';
	} else if (diff < 0) {
			numString += '&ensp;<span class="label label-danger">+ '+ trasformaFloatInStringa(-diff) +'</span>';
	} else {
		numString += okSymbol;
	}
	return '&emsp;' + numString;
}

function getHtmlNumAndFatteVisual(value, total) {
	var numString = (value >= 10) ? trasformaFloatInStringa(value) : '&ensp;' + trasformaFloatInStringa(value);
	var diff = total - value;
	if (diff > 0) {
		numString += '&ensp;<span class="label label-warning">- '+ trasformaFloatInStringa(diff) +'</span>';
	} else if (diff < 0) {
			numString += '&ensp;<span class="label label-danger">+ '+ trasformaFloatInStringa(-diff) +'</span>';
	} else {
		numString += okSymbol;
	}
	return '&emsp;' + numString;
}

function getHtmlNumAndFacoltativeVisual(value, total) {
	return '&emsp;' + ((value >= 10) ? trasformaFloatInStringa(value) : '&ensp;' + trasformaFloatInStringa(value));
}

function getHtmlNumAndFatte80Visual(value, total) {
	return '&emsp;' + ((value >= 10) ? trasformaFloatInStringa(value) : '&ensp;' + trasformaFloatInStringa(value));
}

function oreDovuteReadRecords() {
	var ore_dovute, ore_previste, ore_fatte;

	$.post("oreDovuteReadDetails.php", {
		table_name: 'ore_dovute'
	},
	function (dati, status) {
		// console.log(dati);
        ore_dovute = JSON.parse(dati);
        var dovute_con_studenti_totale = parseFloat(ore_dovute.ore_70_con_studenti) + parseFloat(ore_dovute.ore_40_con_studenti);
        $("#dovute_totale_con_studenti").html(getHtmlNum(dovute_con_studenti_totale));
		$("#dovute_ore_70_funzionali").html(getHtmlNum(ore_dovute.ore_70_funzionali));
		$("#dovute_ore_70_con_studenti").html(getHtmlNum(ore_dovute.ore_70_con_studenti));

		$("#dovute_ore_40_sostituzioni_di_ufficio").html(getHtmlNum(ore_dovute.ore_40_sostituzioni_di_ufficio));
		$("#dovute_ore_40_aggiornamento").html(getHtmlNum(ore_dovute.ore_40_aggiornamento));
		$("#dovute_ore_40_con_studenti").html(getHtmlNum(ore_dovute.ore_40_con_studenti));
		
		$("#dovute_ore_80_collegi_docenti").html(getHtmlNum(ore_dovute.ore_80_collegi_docenti));
		$("#dovute_ore_80_udienze_generali").html(getHtmlNum(ore_dovute.ore_80_udienze_generali));
		$("#dovute_ore_80_dipartimenti").html(getHtmlNum(ore_dovute.ore_80_dipartimenti));
		$("#dovute_ore_80_aggiornamento_facoltativo").html(getHtmlNum(ore_dovute.ore_80_aggiornamento_facoltativo));
		$("#dovute_ore_80_consigli_di_classe").html(getHtmlNum(ore_dovute.ore_80_consigli_di_classe));
		$.post("oreDovuteReadDetails.php", {
			table_name: 'ore_previste'
		},
		function (dati, status) {
			// console.log(dati);
			ore_previste = JSON.parse(dati);
            var previste_con_studenti_totale = parseFloat(ore_previste.ore_70_con_studenti) + parseFloat(ore_previste.ore_40_con_studenti);
            $("#previste_totale_con_studenti").html(getHtmlNumAndPrevisteVisual(previste_con_studenti_totale,dovute_con_studenti_totale));
            $("#previste_ore_70_funzionali").html(getHtmlNumAndPrevisteVisual(ore_previste.ore_70_funzionali,ore_dovute.ore_70_funzionali));
			$("#previste_ore_70_con_studenti").html(getHtmlNumAndPrevisteVisual(ore_previste.ore_70_con_studenti,ore_dovute.ore_70_con_studenti));

			// $("#previste_ore_40_sostituzioni_di_ufficio").html(getHtmlNumAndPrevisteVisual(ore_previste.ore_40_sostituzioni_di_ufficio,ore_dovute.ore_40_sostituzioni_di_ufficio));
			$("#previste_ore_40_aggiornamento").html(getHtmlNumAndPrevisteVisual(ore_previste.ore_40_aggiornamento,ore_dovute.ore_40_aggiornamento));
			$("#previste_ore_40_con_studenti").html(getHtmlNumAndPrevisteVisual(ore_previste.ore_40_con_studenti,ore_dovute.ore_40_con_studenti));
			
			$("#previste_ore_80_collegi_docenti").html(getHtmlNumAndPrevisteVisual(ore_previste.ore_80_collegi_docenti,ore_dovute.ore_80_collegi_docenti));
			$("#previste_ore_80_udienze_generali").html(getHtmlNumAndPrevisteVisual(ore_previste.ore_80_udienze_generali,ore_dovute.ore_80_udienze_generali));
			$("#previste_ore_80_dipartimenti").html(getHtmlNumAndPrevisteVisual(ore_previste.ore_80_dipartimenti,ore_dovute.ore_80_dipartimenti));
			// usiamo getHtmlNumAndFatteVisual per le facoltative in quanto non devono segnalare mancanze eventuali
			$("#previste_ore_80_aggiornamento_facoltativo").html(getHtmlNumAndFacoltativeVisual(ore_previste.ore_80_aggiornamento_facoltativo,ore_dovute.ore_80_aggiornamento_facoltativo));
			$("#previste_ore_80_consigli_di_classe").html(getHtmlNumAndPrevisteVisual(ore_previste.ore_80_consigli_di_classe,ore_dovute.ore_80_consigli_di_classe));
			$.post("oreDovuteReadDetails.php", {
				table_name: 'ore_fatte'
			},
			function (dati, status) {
				// console.log(dati);
				ore_fatte = JSON.parse(dati);
                var fatte_con_studenti_totale = parseFloat(ore_fatte.ore_70_con_studenti) + parseFloat(ore_fatte.ore_40_con_studenti);
                $("#fatte_totale_con_studenti").html(getHtmlNumAndFatteVisual(fatte_con_studenti_totale,dovute_con_studenti_totale));
                $("#fatte_ore_70_funzionali").html(getHtmlNumAndFatteVisual(ore_fatte.ore_70_funzionali,ore_dovute.ore_70_funzionali));
				$("#fatte_ore_70_con_studenti").html(getHtmlNumAndFatteVisual(ore_fatte.ore_70_con_studenti,ore_dovute.ore_70_con_studenti));

				$("#fatte_ore_40_sostituzioni_di_ufficio").html(getHtmlNumAndFatteVisual(ore_fatte.ore_40_sostituzioni_di_ufficio,ore_dovute.ore_40_sostituzioni_di_ufficio));
				$("#fatte_ore_40_aggiornamento").html(getHtmlNumAndFatteVisual(ore_fatte.ore_40_aggiornamento,ore_dovute.ore_40_aggiornamento));
				$("#fatte_ore_40_con_studenti").html(getHtmlNumAndFatteVisual(ore_fatte.ore_40_con_studenti,ore_dovute.ore_40_con_studenti));
				
				$("#fatte_ore_80_collegi_docenti").html(getHtmlNumAndFatte80Visual(ore_fatte.ore_80_collegi_docenti,ore_dovute.ore_80_collegi_docenti));
				$("#fatte_ore_80_udienze_generali").html(getHtmlNumAndFatte80Visual(ore_fatte.ore_80_udienze_generali,ore_dovute.ore_80_udienze_generali));
				$("#fatte_ore_80_dipartimenti").html(getHtmlNumAndFatte80Visual(ore_fatte.ore_80_dipartimenti,ore_dovute.ore_80_dipartimenti));
				$("#fatte_ore_80_aggiornamento_facoltativo").html(getHtmlNumAndFatte80Visual(ore_fatte.ore_80_aggiornamento_facoltativo,ore_dovute.ore_80_aggiornamento_facoltativo));
				$("#fatte_ore_80_consigli_di_classe").html(getHtmlNumAndFatte80Visual(ore_fatte.ore_80_consigli_di_classe,ore_dovute.ore_80_consigli_di_classe));
				$.post("oreDovuteClilReadDetails.php", {
					table_name: 'ore_fatte_attivita_clil'
				},
				function (dati, status) {
					// console.log(dati);
					ore_clil = JSON.parse(dati);
					$("#clil_previste_funzionali").html(getHtmlNum(ore_clil.funzionali_previste));
					$("#clil_previste_con_studenti").html(getHtmlNum(ore_clil.con_studenti_previste));
					$("#clil_fatte_funzionali").html(getHtmlNumAndFatteVisual(ore_clil.funzionali,ore_clil.funzionali_previste));
					$("#clil_fatte_con_studenti").html(getHtmlNumAndFatteVisual(ore_clil.con_studenti,ore_clil.con_studenti_previste));
					if (parseInt(ore_clil.funzionali,10) + parseInt(ore_clil.con_studenti,10) + parseInt(ore_clil.funzionali_previste,10) + parseInt(ore_clil.con_studenti_previste,10) == 0) {
						$("#panel-clil").addClass('hidden');
					} else {
						$("#panel-clil").removeClass('hidden');
					}
				});
			});
		});
	});
}

function fuisDocenteReadRecords() {
	$.post("../docente/fuisDocenteReadRecords.php", {
	},
	function (data, status) {
		$(".fuis_docente_records_content").html(data);
	});
}

//Read records on page load
$(document).ready(function () {
    oreDovuteReadRecords();
    fuisDocenteReadRecords();
});
