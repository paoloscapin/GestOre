/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// 🔽 Recupero parametro "d" passato nello <script src=...>
var scripts = document.getElementsByTagName('script');
var myScript = scripts[scripts.length - 1];
var url = new URL(myScript.src);
var params = new URLSearchParams(url.search);
var device = params.get("d") || "desktop";

var soloNuovi = 1;
var soloIscritto = 0;
var ancheCancellati = 0;
var docente_filtro_id = 0;
var materia_filtro_id = 0;
var categoria_filtro_id = 1;
var studente_filtro_id = 0;

$('#soloNuoviCheckBox').change(function () {
    soloNuovi = this.checked ? 1 : 0;
    sportelloReadRecords();
});

$('#soloIscrittoCheckBox').change(function () {
    soloIscritto = this.checked ? 1 : 0;
    sportelloReadRecords();
});

$('#ancheCancellatiCheckBox').change(function () {
    ancheCancellati = this.checked ? 1 : 0;
    sportelloReadRecords();
});

function sportelloReadRecords() {
    var endpoint = (device === "mobile")
        ? "sportelloReadRecords_mobile.php"
        : "sportelloReadRecords.php";

    $.post(endpoint, {
        ancheCancellati: ancheCancellati,
        soloNuovi: soloNuovi,
        soloIscritto: soloIscritto,
        docente_filtro_id: docente_filtro_id,
        materia_filtro_id: materia_filtro_id,
        categoria_filtro_id: categoria_filtro_id,
        studente_filtro_id: studente_filtro_id
    }, function (data) {
        $(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            trigger: 'hover',
            container: 'body'
        });
    });
}

$(document).ready(function () {
    studente_filtro_id = $('#studente_filtro').val() || 0;
    categoria_filtro_id = $('#categoria_filtro').val() || 1;
    docente_filtro_id = $('#docente_filtro').val() || 0;
    materia_filtro_id = $('#materia_filtro').val() || 0;

    sportelloReadRecords();

    $("#categoria_filtro").on("changed.bs.select", function () {
        categoria_filtro_id = this.value;
        sportelloReadRecords();
    });

    $("#docente_filtro").on("changed.bs.select", function () {
        docente_filtro_id = this.value;
        sportelloReadRecords();
    });

    $("#materia_filtro").on("changed.bs.select", function () {
        materia_filtro_id = this.value;
        sportelloReadRecords();
    });

    $("#studente_filtro").on("changed.bs.select", function () {
        studente_filtro_id = this.value;
        sportelloReadRecords();
    });
});
