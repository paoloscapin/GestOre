/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// 🔽 Recupero parametro "d" passato nello <script src=...>
var scripts = document.getElementsByTagName('script');
var myScript = scripts[scripts.length - 1];
var url = new URL(myScript.src);
var params = new URLSearchParams(url.search);
var device = params.get("d") || "desktop"; // default "desktop"

var soloNuovi=1;
var soloIscritto=0;
var ancheCancellati=0;
var docente_filtro_id=0;
var materia_filtro_id=0;
var classe_filtro_id=0;
var categoria_filtro_id=1; // sportello didattico
var studente_filtro_id=1;

$('#soloNuoviCheckBox').change(function() {
    // this si riferisce al checkbox
    if (this.checked) {
		soloNuovi = 1;
    } else {
		soloNuovi = 0;
    }
    sportelloReadRecords();
});

$('#soloIscrittoCheckBox').change(function() {
    // this si riferisce al checkbox
    if (this.checked) {
		soloIscritto = 1;
    } else {
		soloIscritto = 0;
    }
    sportelloReadRecords();
});

$('#ancheCancellatiCheckBox').change(function() {
    // this si riferisce al checkbox
    if (this.checked) {
		ancheCancellati = 1;
    } else {
		ancheCancellati = 0;
    }
    sportelloReadRecords();
});

function sportelloReadRecords() {
        var endpoint = (device === "mobile") 
        ? "sportelloReadRecords_mobile.php" 
        : "sportelloReadRecords.php";

	$.get(endpoint+"?ancheCancellati=" + ancheCancellati + "&soloNuovi=" + soloNuovi + "&soloIscritto=" + soloIscritto + "&docente_filtro_id=" + docente_filtro_id + "&classe_filtro_id=" + classe_filtro_id + "&materia_filtro_id=" + materia_filtro_id + "&categoria_filtro_id=" + categoria_filtro_id + "&studente_filtro_id=" + studente_filtro_id, {}, function (data, status) {
		$(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            trigger: 'hover',
            container: 'body'
        });
	});
}

$(document).ready(function () {
    sportelloReadRecords();

    $("#categoria_filtro").on("changed.bs.select", 
        function(e, clickedIndex, newValue, oldValue) {
            categoria_filtro_id = this.value;
            sportelloReadRecords();
        });

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

    $("#classe_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        classe_filtro_id = this.value;
        sportelloReadRecords();
    });

    $("#studente_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        studente_filtro_id = this.value;
        sportelloReadRecords();
    });
    
});
