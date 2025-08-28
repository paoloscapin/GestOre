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
    console.log(device);
    if (device === "mobile") {
        $.get("carenzeReadRecords_mobile.php?anni_filtro_id=" + $anni_filtro_id, {}, function (data, status) {
            $("#carenze_mobile_container").html(data);
            $('[data-toggle="tooltip"]').tooltip({
                trigger: 'hover',
                container: 'body'
            });
        });
    }
    else {


        $.get("carenzeReadRecords.php?anni_filtro_id=" + $anni_filtro_id, {}, function (data, status) {
            $(".records_content").html(data);
            $('[data-toggle="tooltip"]').tooltip({
                trigger: 'hover',
                container: 'body'
            });
        });
    }
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
        anno: $anni_filtro_id,
        titolo: 'Programma carenza formativa'
    },
        function (data, status) {
            if (data == 'sent') {
                Swal.fire({
                    icon: 'success',
                    title: 'Inviata!',
                    text: 'La carenza è stata spedita alla mail dello studente.',
                    confirmButtonText: 'OK',
                    timer: 2500,   // si chiude da sola dopo 2.5 sec
                    timerProgressBar: true
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Errore!',
                    text: 'Carenza NON spedita. Dettaglio: ' + data,
                    confirmButtonText: 'Chiudi'
                });
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
