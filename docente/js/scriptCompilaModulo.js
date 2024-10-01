/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function aggiorna() {
    documento = aggiornaContenutoDocumento();
    $("#modulo_compilato_id").html(documento);
}

function invia() {
    template_id = $("#hidden_template_id").val();
    documento = aggiornaContenutoDocumento();
    docente_id = $("#hidden_docente_id").val();

    oldcmd = "../docente/modulisticaInviaModulo.php?template_id=" + template_id + "&documento=" + documento + "&docente_id=" + docente_id;
    console.log("invia non ancora fatto");
    
    $.get("../docente/modulisticaInviaModulo.php", {template_id: template_id, documento: documento, docente_id: docente_id}, function (data, status) {
        console.log("invia status=" + status + " data=" + data);
        $(".modulo_compilato_id").html("Inviato!");
	});
}

function aggiornaContenutoDocumento() {
    documento = $("#hidden_template").val();

    listaCampi = JSON.parse($("#hidden_lista_campi").val());
    // console.log(listaCampi);

    // sostituisce tutti i campi che trova con i valori inseriti dall'utente
    for(var i = 0; i < listaCampi.length; i++){
        var valore = $("#" + listaCampi[i]).val();
        documento = documento.replaceAll('{{' + listaCampi[i] + '}}', valore);
    }

    // sostituisce tutti i campi standard
    documento = documento.replaceAll('{{luogo_documento}}', 'Mezzolombardo');
    var data = new Date().toLocaleDateString("it-IT");
    documento = documento.replaceAll('{{data_documento}}', data);

    return documento;
}

$(document).ready(function () {
});