/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
var listaCampi;
var listaTipi;
var listaObbligatori;
var listaValori;

function aggiorna() {
    documento = aggiornaContenutoDocumento();
    $("#modulo_compilato_id").html(documento);
}

function invia() {
    template_id = $("#hidden_template_id").val();
    documento = aggiornaContenutoDocumento();
    docente_id = $("#hidden_docente_id").val();
    email_to = $("#hidden_template_email_to").val();
    uuid = generateGuid();

    // salva la richiesta ed eventualmente genera l'aggancio per l'approvazione
    $.post("../docente/modulisticaModuloCompilatoSave.php", {
        template_id: template_id,
        docente_id: docente_id,
        listaCampi: JSON.stringify(listaCampi),
        listaCampiId: JSON.stringify(listaCampiId),
        listaValori: JSON.stringify(listaValori),
        uuid: uuid
    }, function (data, status) {
        // richiesta id viene tornato nel campo data
        richiesta_id = parseInt(data);

        // invia le email
        $.post("../docente/modulisticaInviaModulo.php", {
            template_id: template_id,
            richiesta_id: richiesta_id,
            documento: documento,
            docente_id: docente_id,
            email_to: email_to,
            approva_id: 0,
            listaEtichette: JSON.stringify(listaEtichette),
            listaValori: JSON.stringify(listaValori)    
        }, function (data, status) {
            // text.includes("world")
            if (data === '' && status === 'success') {
                $("#modulo_compilato_id").html("<p><b>Il modulo &egrave; stato inviato.</b></p><p>Una copia della richiesta &egrave; stata inoltrata alla tua casella di email per controllo.</p>");
            } else {
                console.log('data=[' + data) + ']';
                console.log('data type=' + typeof(data)+' len='+data.length+' char='+data.charCodeAt(0));
                console.log('status=' + status);
                $("#modulo_compilato_id").html(data);
            }
        });
    });
}

function aggiornaContenutoDocumento() {
    documento = $("#hidden_template").val();

    // per il controllo dei campi obbligatori
    obbligatoriCompletati = true;

    // svuota la lista dei valori
    listaValori = [];

    // sostituisce tutti i campi che trova con i valori inseriti dall'utente
    for(var i = 0; i < listaCampi.length; i++){
        var valore = $("#" + listaCampi[i]).val();

        // controlla se era obbligatorio e risulta vuoto deve essere riempito
        if (obbligatoriCompletati && valore == '' && listaObbligatori[i] == true) {
            obbligatoriCompletati = false;
            alert('Il campo ' + listaCampi[i] + ' è obbligatorio');
        }

        documento = documento.replaceAll('{{' + listaCampi[i] + '}}', valore);

        // aggiunge il valore letto alla lista dei valori
        listaValori.push(valore);
    }

    // todo: gli obbligatori sono stati completati, mette visibile il bottone "Invia"
    if (obbligatoriCompletati) {
        $("#inviaBtnId").show();
    }

    // sostituisce tutti i campi standard
    documento = documento.replaceAll('{{docente_nome_e_cognome}}', $("#hidden_docente_cognome_e_nome").val());
    documento = documento.replaceAll('{{docente_email}}', $("#hidden_docente_email").val());
    documento = documento.replaceAll('{{luogo_documento}}', 'Mezzolombardo');
    documento = documento.replaceAll('{{luogo_documento}}', 'Mezzolombardo');
    var data = new Date().toLocaleDateString("it-IT");
    documento = documento.replaceAll('{{data_documento}}', data);

    return documento;
}

// genera un identificatore univoco casuale
function generateGuid() {
    return Math.random().toString(36).substring(2, 15) +  Math.random().toString(36).substring(2, 15);
}

$(document).ready(function () {
    // memorizza la lista dei campi, tipi e se sono obbligatori oppure no
    listaCampi = JSON.parse($("#hidden_lista_campi").val());
    listaCampiId = JSON.parse($("#hidden_lista_campi_id").val());
    listaEtichette = JSON.parse($("#hidden_lista_etichette").val());
    listaTipi = JSON.parse($("#hidden_lista_tipi").val());
    listaObbligatori = JSON.parse($("#hidden_lista_obbligatori").val());
    $("#inviaBtnId").hide();
});