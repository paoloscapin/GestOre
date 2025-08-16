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

console.log("Device:", device);

var soloNuovi=1;
var soloIscritto=0;
var ancheCancellati=0;
var docente_filtro_id=0;
var materia_filtro_id=0;
var classe_filtro_id=0;
var categoria_filtro_id=1; // sportello didattico

$('#soloNuoviCheckBox').change(function() {
    // this si riferisce al checkbox
    if (this.checked) {
		soloNuovi = 1;
    } else {
		soloNuovi = 0;
    }
    sportelloReadRecords();
});

$('#soloIscrittoCheckBox').change(function() {
    // this si riferisce al checkbox
    if (this.checked) {
		soloIscritto = 1;
    } else {
		soloIscritto = 0;
    }
    sportelloReadRecords();
});

$('#ancheCancellatiCheckBox').change(function() {
    // this si riferisce al checkbox
    if (this.checked) {
		ancheCancellati = 1;
    } else {
		ancheCancellati = 0;
    }
    sportelloReadRecords();
});

function sportelloReadRecords() {
        var endpoint = (device === "mobile") 
        ? "sportelloReadRecords_mobile.php" 
        : "sportelloReadRecords.php";

	$.get(endpoint+"?ancheCancellati=" + ancheCancellati + "&soloNuovi=" + soloNuovi + "&soloIscritto=" + soloIscritto + "&docente_filtro_id=" + docente_filtro_id + "&classe_filtro_id=" + classe_filtro_id + "&materia_filtro_id=" + materia_filtro_id + "&categoria_filtro_id=" + categoria_filtro_id, {}, function (data, status) {
		console.log(data);
        $(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            trigger: 'hover',
            container: 'body'
        });
	});
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
            sportelloReadRecords();
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

    var dialog = bootbox.prompt({
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
                sportelloReadRecords();
            });
        }
    });
    dialog.on('shown.bs.modal', function() {
    $(this).attr('aria-hidden', 'false'); // ora il modal è accessibile
});
}

$(document).ready(function () {
    sportelloReadRecords();

    $("#categoria_filtro").on("changed.bs.select", 
        function(e, clickedIndex, newValue, oldValue) {
            categoria_filtro_id = this.value;
            sportelloReadRecords();
        });

    $("#docente_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        docente_filtro_id = this.value;
        sportelloReadRecords();
    });
 
    $("#materia_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        materia_filtro_id = this.value;
        sportelloReadRecords();
    });

    $("#classe_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        classe_filtro_id = this.value;
        sportelloReadRecords();
    });
    
});
