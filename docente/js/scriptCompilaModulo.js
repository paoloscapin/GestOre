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

// checked e unchecked checkbox e radio
chekboxChecked = '<b><input type="checkbox" value="" style="vertical-align: bottom;" checked></b> ';
chekboxUnchecked = '<b><input type="checkbox" value="" style="vertical-align: bottom;"></b> ';
radioChecked = '<b><label><input type="checkbox" value="" style="vertical-align: bottom;" checked></label></b> ';
radioUnchecked = '<b><label><input type="checkbox" value="" style="vertical-align: bottom;"></label></b> ';

function aggiorna() {
    documento = aggiornaContenutoDocumento();
    $("#modulo_compilato_id").html(documento);
}

function invia() {
    template_id = $("#hidden_template_id").val();
    documento = aggiornaContenutoDocumento();
    docente_id = $("#hidden_docente_id").val();
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
            approva_id: 0,
            listaValori: JSON.stringify(listaValori)    
        }, function (data, status) {
            if (data.trim() === '' && status === 'success') {
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
    var valore = '';

    // per il controllo dei campi obbligatori
    obbligatoriCompletati = true;

    // svuota la lista dei valori
    listaValori = [];

    // sostituisce tutti i campi che trova con i valori inseriti dall'utente
    for(var i = 0; i < listaCampi.length; i++){
        // a seconda del tipo trova il valore
        if (listaTipi[i] == 1 || listaTipi[i] == 2) {
            // tipo 1 = testo semplice e tipo 2 = combo box (select option): prende il valore inserito
            valore = $("#" + listaCampi[i]).val();
            documento = documento.replaceAll('{{' + listaCampi[i] + '}}', valore);

        } else if (listaTipi[i] == 3 || listaTipi[i] == 4) {
            // tipo 3 = checkbox e tipo 4 = radio: deve prendere tutti i valori dei checkbox del gruppo
            valore = '';
            // costruisce la stringa html da sostituire al placeholder
            risultato = '';
            valoriSelezionabili = listaValoriSelezionabili[i].split('::');
            for (j = 0; j < listaCampoNumeroElementi[i]; j++) {
                if ($("#" + listaCampi[i] + "_" + j).is(':checked')) {
                    if (valore != '') {
                        valore += '::';
                    }
//                    documento = documento.replaceAll('{{' + listaCampi[i] + "_" + j + '}}', '<b>&#x2612;</b> ');
                    // documento = documento.replaceAll('{{' + listaCampi[i] + "_" + j + '}}', chekboxChecked + valoriSelezionabili[j]);
                    risultato = risultato + chekboxChecked + valoriSelezionabili[j] + '</br>';
                    valore += j;
                } else {
                    // documento = documento.replaceAll('{{' + listaCampi[i] + "_" + j + '}}', chekboxUnchecked + valoriSelezionabili[j]);
                    risultato = risultato + chekboxUnchecked + valoriSelezionabili[j] + '</br>';
                }
            }
            documento = documento.replaceAll('{{' + listaCampi[i] + '}}', risultato);
        }

        // controlla se era obbligatorio e risulta vuoto deve essere riempito
        if (obbligatoriCompletati && valore == '' && listaObbligatori[i] == true) {
            obbligatoriCompletati = false;
            alert('Il campo ' + listaCampi[i] + ' è obbligatorio');
        }

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
    listaValoriSelezionabili = JSON.parse($("#hidden_lista_valori_selezionabili").val());
    listaCampoNumeroElementi = JSON.parse($("#hidden_lista_campo_numero_elementi").val());
    listaCampiId = JSON.parse($("#hidden_lista_campi_id").val());
    listaEtichette = JSON.parse($("#hidden_lista_etichette").val());
    listaTipi = JSON.parse($("#hidden_lista_tipi").val());
    listaObbligatori = JSON.parse($("#hidden_lista_obbligatori").val());
    $("#inviaBtnId").hide();
});