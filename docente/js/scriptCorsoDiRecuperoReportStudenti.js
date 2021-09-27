/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function letteraCarenze(id) {
	var url = 'letteraCarenze.php?id=' + id;
	window.open(url, "_blank");
}

function letteraCarenzeSettembre(id) {
	var url = 'letteraCarenzeSettembre.php?id=' + id;
	window.open(url, "_blank");
}

function letteraCarenzeNovembre(id) {
	var url = 'letteraCarenze.php?id=' + id;
	window.open(url, "_blank");
}

function registraVotoNovembre(studente_per_corso_di_recupero_id, voto, __this) {
	$.post("corsoDiRecuperoRegistraVoto.php", {
		studente_per_corso_di_recupero_id: studente_per_corso_di_recupero_id,
		dbFieldName: 'voto_novembre',
		voto: voto
		},
		function (data, status) {
			//  cambiato il voto: scrive se passato o no
			if (__this.value > 5) {
				$('td:nth-child(10)', $(__this).parents('tr')).html('<span class=\'label label-success\'>passato</span>');
				$('td:nth-child(11)', $(__this).parents('tr')).html('<button onclick="letteraCarenze('+studente_per_corso_di_recupero_id+')" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-envelope"></button>');
			} else if (__this.value < 1) {
				$('td:nth-child(10)', $(__this).parents('tr')).html('');
				$('td:nth-child(11)', $(__this).parents('tr')).html('');
			} else {
				$('td:nth-child(10)', $(__this).parents('tr')).html('<span class=\'label label-danger\'>non passato</span>');
			}
		}
	);
}

function registraDocenteNovembre(studente_per_corso_di_recupero_id, docente_id, docente_incaricato_cognomenome, __this) {
	$.post("corsoDiRecuperoRegistraDocente.php", {
		studente_per_corso_di_recupero_id: studente_per_corso_di_recupero_id,
		dbFieldName: 'docente_voto_novembre_id',
		docente_id: docente_id
		},
		function (data, status) {
			// scrive il nome del docente
			$('td:nth-child(9)', $(__this).parents('tr')).html('<small>' + docente_incaricato_cognomenome + '</small>');
		}
	);
}
function registraDataVoto(studente_per_corso_di_recupero_id, value, dbFieldName){
	$.post("corsoDiRecuperoRegistraDataVoto.php", {
		studente_per_corso_di_recupero_id: studente_per_corso_di_recupero_id,
		dbFieldName: dbFieldName,
		value: value
		},
		function (data, status) {
		}
	);
}

$(document).ready(function () {
	dataVotoNovembre_pickr = flatpickr(".dataVotoNovembre", {
		onChange: function(selectedDates, dateStr, instance) {
			// difficile da ricavare: da instance il tr che lo contiene
			var element = instance.input.parentElement.parentElement;
			var studente_per_corso_di_recupero_id = $('td:first', element).text();
			var data_voto_date = Date.parseExact(dateStr, 'd/M/yyyy');
			var data = data_voto_date.toString('yyyy-MM-dd');
			instance.close();
			registraDataVoto(studente_per_corso_di_recupero_id, data,'data_voto_novembre');
	    },
		locale: {
			firstDayOfWeek: 1
		},
		dateFormat: 'j/n/Y'
	});

	flatpickr.localize(flatpickr.l10ns.it);

	$(".votoNovembre").on('change', function(e){
			var voto = this.value;
			// ogni tanto lo chiama due volte una con undefined
			if (voto === undefined) {
				// console.log('skip undefined!');
				return;
			}
			var studente_per_corso_di_recupero_id = $('td:first', $(this).parents('tr')).text();
			var docente_incaricato_id = $("#hidden_docente_id").val();
			var docente_incaricato_cognomenome = $("#hidden_docente_cognomenome").val();
			registraVotoNovembre(studente_per_corso_di_recupero_id, voto, this);
			if (docente_incaricato_id >= 0) {
				registraDocenteNovembre(studente_per_corso_di_recupero_id, docente_incaricato_id, docente_incaricato_cognomenome, this);
			}
			registraDataVoto(studente_per_corso_di_recupero_id, Date.today().toString('yyyy-MM-dd'),'data_voto_novembre');
			$('td:nth-child(9)', $(this).parents('tr')).children(":first").val(Date.today().toString('dd/MM/yyyy'));
		});
	
	$('.table td:nth-child(1),th:nth-child(1)').hide(); // nasconde la prima colonna con l'id
});
