/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var docente_id = 0;
var gruppo_id = 0;
var ordinamento = 0;

function ricaricaTabella() {
	$.post("reportGruppiReadRecords.php", {
        docente_id: docente_id,
        gruppo_id: gruppo_id,
        ordinamento: ordinamento
    },
    function (data, status) {
        // console.log(data);
		$(".records_content").html(data);
    });
}

$(document).ready(function () {
	$("#docente").on("changed.bs.select", 
			function(e, clickedIndex, newValue, oldValue) {
				docente_id = this.value;
				ricaricaTabella();
	});
	$("#gruppo").on("changed.bs.select", 
			function(e, clickedIndex, newValue, oldValue) {
				gruppo_id = this.value;
				ricaricaTabella();
	});
	$("#ordinamento").on("changed.bs.select", 
			function(e, clickedIndex, newValue, oldValue) {
				ordinamento = this.value;
				ricaricaTabella();
	});
	ricaricaTabella();
});
