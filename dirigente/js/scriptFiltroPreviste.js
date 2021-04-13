/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var tipo_attivita_id = 0;
var ordinamento = 0;

function selezionaTipoAttivita() {
	$.post("filtroPrevisteReadRecords.php", {
        tipo_attivita_id: tipo_attivita_id,
        ordinamento: ordinamento
    },
    function (data, status) {
        // console.log(data);
		$(".records_content").html(data);
    });
}

$(document).ready(function () {
	$("#tipo_attivita").on("changed.bs.select", 
			function(e, clickedIndex, newValue, oldValue) {
				tipo_attivita_id = this.value;
				selezionaTipoAttivita();
	});
	$("#ordinamento").on("changed.bs.select", 
			function(e, clickedIndex, newValue, oldValue) {
				ordinamento = this.value;
				selezionaTipoAttivita();
	});
});
