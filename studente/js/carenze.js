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
var $anni_filtro_id = params.get("a") || "1"; // default 

function carenzeReadRecords() {
    var endpoint = (device === "mobile")
        ? "carenzeReadRecords_mobile.php?anni_filtro_id=" + $anni_filtro_id
        : "carenzeReadRecords.php?anni_filtro_id=" + $anni_filtro_id;

    $.get(endpoint, {}, function (data, status) {
        $(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            trigger: 'hover',
            container: 'body'
        });
    });
}

function carenzaPrint(id_carenza) {
    // creo form nascosto
    var form = $('<form>', {
        action: '../didattica/stampaCarenza.php',
        method: 'POST',
        target: '_black'    // apre in un nuovo tab
    });
    // aggiungo i campi
    form.append($('<input>', { type: 'hidden', name: 'id', value: id_carenza }));
    form.append($('<input>', { type: 'hidden', name: 'print', value: 0 }));
    form.append($('<input>', { type: 'hidden', name: 'mail', value: 0 }));
    form.append($('<input>', { type: 'hidden', name: 'genera', value: 0 }));
    form.append($('<input>', { type: 'hidden', name: 'view', value: 1 }));
    form.append($('<input>', { type: 'hidden', name: 'anno', value: $anni_filtro_id }));
    form.append($('<input>', { type: 'hidden', name: 'titolo', value: 'Programma carenza formativa' }));
    // lo “submitto” e lo rimuovo
    form.appendTo('body').submit().remove();
}

function carenzaSend(id_carenza) {
    $.post("../didattica/stampaCarenza.php", {
        id: id_carenza,
        print: 0,
        mail: 1,
        genera: 0,
        view: 0,
        titolo: 'Programma carenza formativa'
    },
        function (data, status) {
            if (data == 'sent') {
                alert("Carenza spedita alla mail dello studente!");
            }
            else {
                alert("Carenza NON spedita! " + data);
            }
            carenzeReadRecords();
        }
    );
}

$(document).ready(function () {
    carenzeReadRecords();

    $("#anni_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $anni_filtro_id = this.value;
            carenzeReadRecords();
        });
});
