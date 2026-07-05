/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var scripts = document.getElementsByTagName('script');
var myScript = scripts[scripts.length - 1];
var url = new URL(myScript.src);
var params = new URLSearchParams(url.search);

var device = params.get("d") || "desktop";
var anni_filtro_id = params.get("a") || "0";

function carenzeReadRecords() {
    var $target = (device === "mobile") ? $("#carenze_mobile_container") : $(".records_content");

    if (!$target.length) $target = $(".records_content");
    if (!$target.length) $target = $("#carenze_mobile_container");

    var endpoint = (device === "mobile")
        ? "carenzeReadRecords_mobile.php"
        : "carenzeReadRecords.php";

    $.post(endpoint, {
        anni_filtro_id: anni_filtro_id
    }, function (data) {
        $target.html(data);
        $('[data-toggle="tooltip"]').tooltip({
            trigger: 'hover',
            container: 'body'
        });
    });
}

function carenzaPrint(id_carenza) {
    var form = $('<form>', {
        action: '../didattica/stampaCarenza.php',
        method: 'POST',
        target: '_blank'
    });

    form.append($('<input>', { type: 'hidden', name: 'id', value: id_carenza }));
    form.append($('<input>', { type: 'hidden', name: 'print', value: 0 }));
    form.append($('<input>', { type: 'hidden', name: 'mail', value: 0 }));
    form.append($('<input>', { type: 'hidden', name: 'genera', value: 0 }));
    form.append($('<input>', { type: 'hidden', name: 'view', value: 1 }));
    form.append($('<input>', { type: 'hidden', name: 'anno', value: anni_filtro_id }));
    form.append($('<input>', { type: 'hidden', name: 'titolo', value: 'Programma carenza formativa' }));

    form.appendTo('body').submit().remove();
}

function carenzaSend(id_carenza) {
    $.post("../didattica/stampaCarenza.php", {
        id: id_carenza,
        print: 0,
        mail: 1,
        genera: 0,
        view: 0,
        anno: anni_filtro_id,
        titolo: 'Programma carenza formativa'
    }, function (data) {
        if (data === 'sent') {
            Swal.fire({
                icon: 'success',
                title: 'Inviata',
                text: 'La carenza è stata spedita alla mail dello studente.',
                confirmButtonText: 'OK',
                timer: 2500,
                timerProgressBar: true
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Errore',
                text: 'Carenza non spedita. Dettaglio: ' + data,
                confirmButtonText: 'Chiudi'
            });
        }

        carenzeReadRecords();
    });
}

function carenzaCorsoRead(id_corso) {
    $.ajax({
        url: "carenzeCorsoRead.php",
        method: "POST",
        dataType: "json",
        data: {
            id_corso: id_corso
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

    $("#anni_filtro").on("changed.bs.select change", function () {
        anni_filtro_id = this.value || "0";
        carenzeReadRecords();
    });
});
