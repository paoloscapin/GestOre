/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var $anno_filtro_id = 0;
var $indirizzo_filtro_id = 0;
var $materia_filtro_id = 0;

function programmiReadRecords() {
    $.get("programmiReadRecords.php?anno_id=" + $anno_filtro_id + "&indirizzo_id=" + $indirizzo_filtro_id + "&materia_id=" +$materia_filtro_id, {}, function (data, status) {
        console.log(data);
        $(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
    });
}

$(document).ready(function () {


    programmiReadRecords();

    $("#annoCorso_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $anno_filtro_id = this.value;
            programmiReadRecords();
        });

    $("#indirizzoCorso_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $indirizzo_filtro_id = this.value;
            programmiReadRecords();
        });

    $("#materia_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $materia_filtro_id = this.value;
            programmiReadRecords();
        });

});     
