/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function sostituzioniReadRecords() {
	$.get("sostituzioniReadRecords.php", {}, function (data, status) {
		$(".sostituzioni_records_content").html(data);
		$('#sostituzioni_table td:nth-child(1),th:nth-child(1)').hide(); // nasconde la prima colonna con l'id
		$("input").change(function(e){
			var numeroFatte = this.value;
			// ogni tanto lo chiama due volte una con undefined
			if (numeroFatte === undefined) {
				console.log('skip undefined!');
				return;
			}
			var fatte_id = $('td:first', $(this).parents('tr')).text();
			var docente_incaricato_cognomenome = $('td:nth-child(2)', $(this).parents('tr')).text();
			registraSostituzioniFatte(fatte_id, numeroFatte, docente_incaricato_cognomenome, this);
		});
	});
}

function registraSostituzioniFatte(fatte_id, numeroFatte, docente_incaricato_cognomenome, __this) {
	$.post("sostituzioniUpdateDetails.php", {
		fatte_id: fatte_id,
		numeroFatte: numeroFatte,
		docente_incaricato_cognomenome: docente_incaricato_cognomenome
		},
		function (data, status) {
			sostituzioniReadRecords();
		}
    );
}

$(document).ready(function () {
	sostituzioniReadRecords();
});
