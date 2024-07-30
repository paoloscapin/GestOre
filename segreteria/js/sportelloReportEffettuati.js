/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var passati=1;
var materia_filtro_id=0;
var docente_filtro_id=0;

$('#passatiCheckBox').change(function() {
    // this si riferisce al checkbox
    if (this.checked) {
		passati = 1;
    } else {
		passati = 0;
    }
    sportelloReportEffettuatiReadRecords();
});

function sportelloReportEffettuatiReadRecords() {
	$.get("sportelloReportEffettuatiReadRecords.php?ancheCancellati=false&passati=" + passati + "&docente_filtro_id=" + docente_filtro_id + "&materia_filtro_id=" + materia_filtro_id, {}, function (data, status) {
		$(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
	});
}

$(document).ready(function () {
    $("#materia_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        materia_filtro_id = this.value;
        sportelloReportEffettuatiReadRecords();
    });
    
    $("#docente_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        docente_filtro_id = this.value;
        sportelloReportEffettuatiReadRecords();
    });
});
