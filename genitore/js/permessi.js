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

var soloNuovi=1;
var soloIscritto=0;
var ancheCancellati=0;
var docente_filtro_id=0;
var materia_filtro_id=0;
var classe_filtro_id=0;
var categoria_filtro_id=1; // sportello didattico
var studente_filtro_id=1;

$('#soloNuoviCheckBox').change(function() {
    // this si riferisce al checkbox
    if (this.checked) {
		soloNuovi = 1;
    } else {
		soloNuovi = 0;
    }
    permessiReadRecords();
});

$('#soloIscrittoCheckBox').change(function() {
    // this si riferisce al checkbox
    if (this.checked) {
		soloIscritto = 1;
    } else {
		soloIscritto = 0;
    }
    permessiReadRecords();
});

$('#ancheCancellatiCheckBox').change(function() {
    // this si riferisce al checkbox
    if (this.checked) {
		ancheCancellati = 1;
    } else {
		ancheCancellati = 0;
    }
    permessiReadRecords();
});

function permessiReadRecords() {
        var endpoint = (device === "mobile") 
        ? "permessiReadRecords_mobile.php" 
        : "permessiReadRecords.php";

	$.get(endpoint+"?studente_filtro_id=" + studente_filtro_id, {}, function (data, status) {
		$(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            trigger: 'hover',
            container: 'body'
        });
	});
}

function permessiGetDetails(permesso_id) { // continuare da qui
    $("#hidden_permesso_id").val(permesso_id);

    if (permesso_id > 0) {
        $.post("permessiReadDetails.php", {
            id: permesso_id
        }, function (data, status) {

            var permesso = JSON.parse(data);
            console.log(permesso);
            $("#cognome").val(permesso.cognome);
            $("#nome").val(genitore.nome);
            $("#email").val(genitore.email.toLowerCase());
            $("#codice_fiscale").val(genitore.codice_fiscale.toUpperCase());
            $("#userId").val(genitore.username);
            $("#attivo").prop('checked', genitore.attivo != 0 && genitore.attivo != null);
            $('#relazioni_table tbody').empty();
            var markup = '';
            genitore.genitori_di.forEach(function (genitoreDi, index) {
                markup += "<tr>" +
                    "<td style=\"text-align: center; vertical-align: middle;\">" + genitoreDi + "</td>" +
                    "<td style=\"text-align: center; vertical-align: middle;\">" + genitore.relazioni[index] + "</td>" +
                    "</tr>";
            });
            $('#relazioni_table > tbody:last-child').append(markup);
        });
    } else {
        $("#cognome").val("");
        $("#nome").val("");
        $("#email").val("");
        $("#codice_fiscale").val("");
        $("#userId").val("");
        $("#attivo").prop('checked', true);
        $('#relazioni_table tbody').empty();
        $('#btn-save').show();
    }

    $("#genitore_modal").modal("show");

    $("#_error-classe-part").hide();
}

function sportelloCancellaIscrizione(sportello_id, materia, categoria, argomento, data, ora, numero_ore, luogo, studente_cognome, studente_nome, studente_email, studente_classe, docente_cognome, docente_nome, docente_email) 
{
    var conf = confirm("Sei sicuro di volere cancellare la tua iscrizione dallo sportello di " + materia + " ?");


    if (conf == true) {

        $.post("../studente/sportelloCancellaIscrizione.php", {
            id: sportello_id,
            argomento: argomento,
            materia: materia,
            categoria: categoria,
            data: data,
            ora: ora,
            numero_ore: numero_ore,
            luogo: luogo,
            studente_cognome: studente_cognome,
            studente_nome: studente_nome,
            studente_email: studente_email,
            studente_classe: studente_classe,
            docente_cognome: docente_cognome,
            docente_nome: docente_nome,
            docente_email: docente_email
        },
        function (data, status) {
            permessiReadRecords();
        });

    }
}

function sportelloIscriviti(sportello_id, materia, categoria, argomento, data, ora, numero_ore, luogo, studente_cognome, studente_nome, studente_email, studente_classe, docente_cognome, docente_nome, docente_email) {
    var unSoloArgomento = $("#hidden_unSoloArgomento").val() == 0 ? false : true;
    // console.log('unSoloArgomento=' + unSoloArgomento);
    // console.log('argomento=' + argomento);

    var primoIscritto = argomento ? false : true;
    // console.log('primoIscritto=' + primoIscritto);

    // per il primo iscritto chiede argomento, oppure anche se gli argomenti possono essere diversi
    var chiediArgomento = ! unSoloArgomento || primoIscritto;
    // console.log('chiediArgomento=' + chiediArgomento);

    // ma se era stato già previsto dal docente, allora si puo' solo accettare
    if (argomento != null && argomento.length != 0) {
        chiediArgomento = false;
    }
    // console.log('chiediArgomento finale=' + chiediArgomento);

    var titolo = "<p>Sportello: " + materia + "</p>";
    var messaggio = chiediArgomento ? "<p>Inserire l\'argomento per lo sportello:</p>" : "<p>Confermare l\'argomento per lo sportello:</p>" + argomento;
    var inputType = chiediArgomento ? 'textarea' : 'checkbox';
    var inputOptions = chiediArgomento ? [] : [{text: 'Confermo',value: '1',}];
    var value = chiediArgomento ? [] : ['1'];

    bootbox.prompt({
        title: titolo,
        message: messaggio,
        inputType: inputType,
        inputOptions: inputOptions,
        value: value,
        required: true,

        callback: function (result) {
            // console.log('result='+result);
            // null se cancel
            if (!result) {
                // console.log('result is null: ritorno');
                return;
            }
            if (argomento) {
                // controlla il checkbox
                if (result != 1) {
                    // console.log('result='+result + ' ritorno');
                    return;
                }
            } else {
                argomento = result;
            }
            $.post("../studente/sportelloIscriviStudente.php", {
                id: sportello_id,
                materia: materia,
                argomento: argomento,
                categoria: categoria,
                data: data,
                ora: ora,
                numero_ore: numero_ore,
                luogo: luogo,
                studente_cognome: studente_cognome,
                studente_nome: studente_nome,
                studente_email: studente_email,
                studente_classe: studente_classe,
                docente_cognome: docente_cognome,
                docente_nome: docente_nome,
                docente_email: docente_email
            },
            function (data, status) {
                permessiReadRecords();
            });
        }
    });
}

$(document).ready(function () {
    permessiReadRecords();

    $("#categoria_filtro").on("changed.bs.select", 
        function(e, clickedIndex, newValue, oldValue) {
            categoria_filtro_id = this.value;
            permessiReadRecords();
        });

    $("#docente_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        docente_filtro_id = this.value;
        permessiReadRecords();
    });
 
    $("#materia_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        materia_filtro_id = this.value;
        permessiReadRecords();
    });

    $("#classe_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        classe_filtro_id = this.value;
        permessiReadRecords();
    });

    $("#studente_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        studente_filtro_id = this.value;
        permessiReadRecords();
    });
    
});
