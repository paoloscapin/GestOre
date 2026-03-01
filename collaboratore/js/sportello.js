/**
 * This file is part of GestOre (Versione per Collaboratore - Sola Lettura)
 * @author     Paolo Scapin <paolo.scapin@gmail.com>
 * @copyright  (C) 2018 Paolo Scapin
 * @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var soloNuovi = 1;
var docente_filtro_id = 0;
var materia_filtro_id = 0;

$('#soloNuoviCheckBox').change(function() {
    // this si riferisce al checkbox
    if (this.checked) {
		soloNuovi = 1;
    } else {
		soloNuovi = 0;
    }
    sportelloReadRecords();
});

function sportelloReadRecords() {
	$.get("sportelloReadRecords.php?ancheCancellati=true&soloNuovi=" + soloNuovi + "&docente_filtro_id=" + docente_filtro_id + "&materia_filtro_id=" + materia_filtro_id, {}, function (data, status) {
		$(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
	});
}

$(document).ready(function () {
	sportelloReadRecords();
       
    $("#docente_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        docente_filtro_id = this.value;
        sportelloReadRecords();
    });
 
    $("#materia_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        materia_filtro_id = this.value;
        sportelloReadRecords();
    });

});