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
	console.log(message);
	if (confirm(message)) {
		console.log('ok ' + message);
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
		console.log('annullato');
	}
}

$(document).ready(function () {
	viaggioDiariaReadRecords();
});
