/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function ricalcolaTutti() {
	$.get("fuisDocentiCalcola.php", {
	},
	function (data, status) {
		reloadTable();
	});
}



function reloadTable() {
	$.post("fuisReadRecords.php", {
	},
	function (dati, status) {
		console.log(dati);
		dati = JSON.parse(dati);
		$("#fuis_viaggi").text(dati.fuis_viaggi);
		$("#fuis_assegnato").text(dati.fuis_assegnato);
		$("#fuis_ore").text(dati.fuis_ore);
		$("#fuis_totale").text(dati.fuis_totale);
		$('#fuis_totale').css("font-weight","Bold");

		$("#fuis_clil_funzionale").text(dati.fuis_clil_funzionale);
		$("#fuis_clil_con_studenti").text(dati.fuis_clil_con_studenti);
		$("#fuis_clil_totale").text(dati.fuis_clil_totale);
		$('#fuis_clil_totale').css("font-weight","Bold");

		// non ci dovrebbero essere piu'
		$("#fuis_sostituzioni").text(dati.fuis_sostituzioni);
		$("#fuis_funzionale").text(dati.fuis_funzionale);
		$("#fuis_con_studenti").text(dati.fuis_con_studenti);
	});

}
$(document).ready(function () {
	ricalcolaTutti();
});
