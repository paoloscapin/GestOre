/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function bonusRivisto() {
	$.post("rivistoUltimoControllo.php", {
		docente_id: $("#hidden_docente_id").val(),
		tabella: "bonus_docente"
	},
	function (data, status) {
		// rimuove tutti gli span della colonna 2 che sono i marker degli elementi nuovi
		$('#table-docente-bonus td:nth-child(2)').find('span').remove();
		
		var tzoffset = (new Date()).getTimezoneOffset() * 60000;
		var localISOTime = (new Date(Date.now() - tzoffset)).toISOString().slice(0, -1);
		var ultimo_controllo = localISOTime.replace('T', ' ');
		$("#rivistoUltimoControllo").val(ultimo_controllo);
		$.notify({
			icon: 'glyphicon glyphicon-ok',
			title: '<Strong>Bonus</Strong></br>',
			message: 'Revisione effettuata!' 
		},{
			placement: {
				from: "top",
				align: "center"
			},
			delay: 2000,
			timer: 100,
			mouse_over: "pause",
			type: 'warning'
		});
	});
}

function bonusChiudi() {
	$.notify({
		icon: 'glyphicon glyphicon-off',
		title: '<Strong>Chiusura Bonus</Strong></br>',
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

function bonusRendiconto(bonus_docente_id, bonus_codice, bonus_descrittori, bonus_evidenze) {
	$("#hidden_bonus_docente_id").val(bonus_docente_id);

	$.post("../docente/bonusDocenteReadDetails.php", {
			bonus_docente_id: bonus_docente_id
		},
		function (dati, status) {
			var bonus = JSON.parse(dati);
			$("#rendiconto_rendiconto").val(bonus.rendiconto_evidenze);
		}
	);

	$("#myModalLabel").text(bonus_codice + ": " + bonus_descrittori);
	$("#evidenze_text").text(bonus_evidenze);
	$("#bonus_docente_rendiconto_modal").modal("show");
}

function bonusRegistraApprovazione(bonus_docente_id, approvato) {
//	alert("id=" + bonus_docente_id +" val=" + approvato);
	$.post("bonusRegistraApprovazione.php", {
			bonus_docente_id: bonus_docente_id,
			approvato: approvato
		},
		function (data, status) {
			calcolaTotaleBonus();
		}
	);
}

function calculateColumn(index) {
    var total = 0;
    $('#table-docente-bonus tr').each(function() {
        var value = parseInt($('td', this).eq(index).text());
        if (!isNaN(value)) {
            total += value;
//        	alert('value='+value+' total='+total);
        }
    });
    return total;
}

function calcolaTotaleBonus() {
    var richiesto = 0;
    var pendente = 0;
    var approvato = 0;
    $('#table-docente-bonus tr').each(function() {
        var value = parseInt($('td', this).eq(3).text());
        if (!isNaN(value)) {
        	richiesto += value;
            var $chkbox = $(this).find('input[type="checkbox"]');
            if ($chkbox.prop('checked')) {
            	approvato += value;
            } else {
            	pendente += value;
            }
        }
    });

	$("#bonus_richiesto").text(richiesto);
	$("#bonus_pendente").text(pendente);
	$("#bonus_approvato").text(approvato);
	var perc = (approvato / richiesto) * 100;

	$('#progress-bar-approvate').css('width', perc + '%').attr('aria-valuenow', perc);
	$('#progress-bar-pendente').css('width', (100 - perc) + '%').attr('aria-valuenow', (100 - perc));
}

function bonusAssegnatoReadRecords() {
	var docente_id = $("#hidden_docente_id").val();
	$.get("bonusAssegnatoReadRecords.php?docente_id=" + docente_id, {}, function (data, status) {
		$(".records_content").html(data);
	});
}

function bonusAssegnatoGetDetails(id) {
    $("#hidden_record_id").val(id);
    if (id > 0) {
        $.post("../common/readRecordDetails.php", {
			id: id,
            table: 'bonus_assegnato'
		},
		function (data, status) {
			var record = JSON.parse(data);
			$("#commento").val(record.commento);
			$("#importo").val(record.importo);
		});
    } else {
        $("#commento").val("");
        $("#importo").val("");
    }
	$("#update_modal").modal("show");
}

function bonusAssegnatoSave() {
    $.post("bonusAssegnatoSave.php", {
        id: $("#hidden_record_id").val(),
        commento: $("#commento").val(),
		importo: $("#importo").val(),
		docente_id: $("#hidden_docente_id").val()
    },
    function (data, status) {
        $("#update_modal").modal("hide");
        bonusAssegnatoReadRecords();
    });
}

function bonusAssegnatoDelete(id) {
    var conf = confirm("Sei sicuro di volere cancellare qiuesto bonus ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
				id: id,
				table: 'bonus_assegnato',
				name: "bonus_assegnato "
            },
            function (data, status) {
                bonusAssegnatoReadRecords();
            }
        );
    }
}

function registraPunteggioBonus(bonus_docente_id, punteggio, valore_massimo, codice) {
    $.post("bonusPunteggioSave.php", {
        bonus_docente_id: bonus_docente_id,
        punteggio: punteggio
    },
    function (data, status) {
		$.notify({
			icon: 'glyphicon glyphicon-off',
			title: '<Strong>' + codice + '</Strong></br>',
			message: '<Strong>Attenzione:</Strong> assegnato ' + punteggio +' punti su ' + valore_massimo
		},{
			placement: {
				from: "top",
				align: "center"
			},
			delay: 4000,
			timer: 100,
			mouse_over: "pause",
			type: 'success'
		});
	
    });
}

$(document).ready(function () {

	bonusAssegnatoReadRecords();

	$('#table-docente-bonus td:nth-child(1)').hide(); // nasconde la prima colonna con l'id

	$('#table-docente-bonus :checkbox').change(function() {
		var bonus_docente_id = $('td:first', $(this).parents('tr')).text();
		var approvato = true;
		if(this.checked != true) {
			approvato = false;
		}
		bonusRegistraApprovazione(bonus_docente_id, this.checked);
	});
	calcolaTotaleBonus();


	$(".punteggioBonus").on('change', function(e){
		var punteggio = this.value;
		// ogni tanto lo chiama due volte una con undefined
		if (punteggio === undefined) {
			// console.log('skip undefined!');
			return;
		}
		var bonus_docente_id = $('td:first', $(this).parents('tr')).text();
		var codice = $('td:nth-child(2)', $(this).parents('tr')).text();
		var valore_massimo = $('td:nth-child(4)', $(this).parents('tr')).text();
		// console.log('bonus_docente_id=' + bonus_docente_id + ' punteggio=' + punteggio + ' di=' + valore_massimo);
		registraPunteggioBonus(bonus_docente_id, punteggio, valore_massimo, codice);
	});
});
