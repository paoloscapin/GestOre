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
var device = params.get("d") || "desktop"; // default "desktop"
var anni_filtro_id = params.get("a") || "1"; // default 

var studente_filtro_id = params.get("id");

function carenzeReadRecords() {
    var $target = $(".records_content");
    if (!$target.length) $target = $("#carenze_mobile_container");

    var endpoint = (device === "mobile")
        ? "carenzeReadRecords_mobile.php"
        : "carenzeReadRecords.php";

    $.post(endpoint, {
        studente_filtro_id: studente_filtro_id,
        anni_filtro_id: anni_filtro_id
    }, function (data) {
        $target.html(data);
        $('[data-toggle="tooltip"]').tooltip({ trigger: 'hover', container: 'body' });
    });
}

function carenzaPrint(id_carenza,id_anno_carenza) {
    // creo form nascosto
    console.log(anni_filtro_id);
    var form = $('<form>', {
        action: '../didattica/stampaCarenza.php',
        method: 'POST',
        target: '_blank'    // apre in un nuovo tab
    });
    // aggiungo i campi
    form.append($('<input>', { type: 'hidden', name: 'id', value: id_carenza }));
    form.append($('<input>', { type: 'hidden', name: 'print', value: 0 }));
    form.append($('<input>', { type: 'hidden', name: 'mail', value: 0 }));
    form.append($('<input>', { type: 'hidden', name: 'genera', value: 0 }));
    form.append($('<input>', { type: 'hidden', name: 'view', value: 1 }));
    form.append($('<input>', { type: 'hidden', name: 'anno', value: id_anno_carenza }));
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
                alert("Carenza spedita alla mail del genitore!");
            }
            else {
                alert("Carenza NON spedita perchè genitore senza mail! " + data);
            }
            carenzeReadRecords();
        }
    );
}

function carenzaCorsoRead(id_corso) {
    $.ajax({
        url: "carenzeCorsoRead.php",
        method: "POST",
        dataType: "json",
        data: {
            id_corso: id_corso,
            id_studente: studente_filtro_id
        },
        success: function (res) {
            if (!res || !res.success) {
                Swal.fire({
                    icon: 'error',
                    title: 'Corso non disponibile',
                    text: (res && res.error) ? res.error : 'Impossibile leggere il dettaglio del corso.',
                    confirmButtonText: 'Chiudi'
                });
                return;
            }

            Swal.fire({
                title: res.title || 'Dettaglio corso',
                html: res.html || '',
                width: '900px',
                customClass: {
                    popup: 'carenze-course-modal'
                },
                confirmButtonText: 'Chiudi'
            });
        },
        error: function () {
            Swal.fire({
                icon: 'error',
                title: 'Errore',
                text: 'Impossibile leggere il dettaglio del corso.',
                confirmButtonText: 'Chiudi'
            });
        }
    });
}

$(document).ready(function () {
    $('.selectpicker').selectpicker();
    carenzeReadRecords();

    $("#studente_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            studente_filtro_id = this.value;
            if (typeof carenzeAnnoDefaultByStudente !== 'undefined' && carenzeAnnoDefaultByStudente[studente_filtro_id]) {
                anni_filtro_id = String(carenzeAnnoDefaultByStudente[studente_filtro_id]);
                $("#anni_filtro").selectpicker('val', anni_filtro_id);
            }
            carenzeReadRecords();
        });

    $("#anni_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            anni_filtro_id = this.value;
            carenzeReadRecords();
        });

});
