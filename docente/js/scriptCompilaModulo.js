/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function aggiorna() {
    template = $("#hidden_template").val();
    console.log($("#hidden_lista_campi").val());

    listaCampi = JSON.parse($("#hidden_lista_campi").val());
    console.log(listaCampi);
    for(var i = 0; i < listaCampi.length; i++){
        console.log(listaCampi[i]);
    }

    $("#modulo_compilato_id").html(template);
}

$(document).ready(function () {
});