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

function viaggioDiariaGetDetails(diaria_id, diaria_importo, viaggio_ore_recuperate_id, viaggio_ore_recuperate_ore, docente_id) {
	$("#hidden_diaria_id").val(diaria_id);
	$("#diaria").val(diaria_importo);
	$("#hidden_diaria").val(diaria_importo);
	if (diaria_id != 0) {
		$("#diaria-part").show();
	} else {
		if (viaggio_ore_recuperate_id == 0) {
			return;
		}
		$("#diaria-part").hide();
	}

	$("#hidden_ore_id").val(viaggio_ore_recuperate_id);
	$("#ore").val(viaggio_ore_recuperate_ore);
	$("#hidden_ore").val(viaggio_ore_recuperate_ore);
	$("#hidden_docente_id").val(docente_id);

	$("#viaggioDiariaModal").modal("show");
}

function viaggioDiariaSalva() {
	if ($("#hidden_diaria").val() != $("#diaria").val()) {
		$.post("../common/recordUpdate.php", {
			table: 'fuis_viaggio_diaria',
			id: $("#hidden_diaria_id").val(),
			nome:'importo',
			valore: $("#diaria").val()
		},
		function (data, status) {
			$.post("../docente/oreFatteAggiornaDocente.php", { docente_id: $("#hidden_docente_id").val() },
			function (data, status) {
				viaggioDiariaReadRecords();
			});
		});
	}
	
	if ($("#hidden_ore").val() != $("#ore").val()) {
		$.post("../common/recordUpdate.php", {
			table: 'viaggio_ore_recuperate',
			id: $("#hidden_ore_id").val(),
			nome:'ore',
			valore: $("#ore").val()
		},
		function (data, status) {
			$.post("../docente/oreFatteAggiornaDocente.php", { docente_id: $("#hidden_docente_id").val() },
			function (data, status) {
				viaggioDiariaReadRecords();
			});
		});
	}
	$("#viaggioDiariaModal").modal("hide");
}

$(document).ready(function () {
	viaggioDiariaReadRecords();
});
