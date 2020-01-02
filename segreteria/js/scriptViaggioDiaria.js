/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function viaggioDiariaReadRecords() {
	$.get("viaggioDiariaReadRecords.php", {}, function (data, status) {
		$(".viaggioDiaria_records_content").html(data);
	});
}

function viaggioDiariaPaga(fuis_viaggio_diaria_id, docenteCognomeNome, destinazione, dataPartenza, importo) {
	message =	'docente: ' + docenteCognomeNome + '\n' + 'data: ' + dataPartenza + '\n' + 'destinazione: ' + destinazione + '\n' + 'importo: ' + importo;
	// console.log(message);
	if (confirm(message)) {
		// console.log('ok ' + message);
		$.post("viaggioDiariaPaga.php", {
			fuis_viaggio_diaria_id: fuis_viaggio_diaria_id,
			importo: importo,
			docenteCognomeNome: docenteCognomeNome,
			destinazione: destinazione,
			dataPartenza: dataPartenza
		},
		function (dati, status) {
//			console.log(dati);
			viaggioDiariaReadRecords();
		}
		);
	} else {
		// console.log('annullato');
	}
}

function viaggioDiariaGetDetails(diaria_id, diaria_importo, viaggio_ore_recuperate_id, viaggio_ore_recuperate_ore, viaggio_id, docente_cognome_nome, docente_id) {
	$("#hidden_diaria_id").val(diaria_id);
	$("#diaria").val(diaria_importo);
	$("#hidden_diaria").val(diaria_importo);
	$("#hidden_viaggio_id").val(viaggio_id);
	$("#hidden_docente_cognome_nome").val(docente_cognome_nome);

	$("#hidden_ore_id").val(viaggio_ore_recuperate_id);
	$("#ore").val(viaggio_ore_recuperate_ore);
	$("#hidden_ore").val(viaggio_ore_recuperate_ore);
	$("#hidden_docente_id").val(docente_id);

	$("#diaria-part").show();
	$("#viaggioDiariaModal").modal("show");
}

function viaggioDiariaSalva() {
	// la cosa piu semplice da fare e' quella di chiudere di nuovo il viaggio con i nuovi valori
	if ($("#hidden_diaria").val() != $("#diaria").val() || $("#hidden_ore").val() != $("#ore").val()) {
		// recupera i valori che ci servono
		var viaggio_id = $("#hidden_viaggio_id").val();
		var numero_ore = $("#ore").val();
		var idennita_forfettaria = $("#diaria").val();
		// problemi con la virgola: se bisogna trasformo in un punto.
		var importo_diaria = parseFloat(idennita_forfettaria.replace(',', '.'));
	
		$.post("viaggioChiudi.php", {
			viaggio_id: viaggio_id,
			importo_diaria: importo_diaria,
			numero_ore: numero_ore,
			docente_id: $("#hidden_docente_id").val(),
			docente_cognome_e_nome: $("#hidden_docente_cognome_nome").val()
			},
			function (data, status) {
				viaggioDiariaReadRecords();
			}
		);
	}

	$("#viaggioDiariaModal").modal("hide");
}

$(document).ready(function () {
	viaggioDiariaReadRecords();
});
