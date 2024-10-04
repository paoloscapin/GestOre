/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var soloNuovi=1;
var ancheCancellati=1;
var docente_filtro_id=0;
var materia_filtro_id=0;
var classe_filtro_id=0;

$('#soloNuoviCheckBox').change(function() {
    // this si riferisce al checkbox
    if (this.checked) {
		soloNuovi = 1;
    } else {
		soloNuovi = 0;
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
	$.get("sportelloReadRecords.php?ancheCancellati=" + ancheCancellati + "&soloNuovi=" + soloNuovi + "&docente_filtro_id=" + docente_filtro_id + "&classe_filtro_id=" + classe_filtro_id + "&materia_filtro_id=" + materia_filtro_id, {}, function (data, status) {
		$(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
	});
}

function sportelloCancellaIscrizione(sportello_id, materia) {
    var conf = confirm("Sei sicuro di volere cancellare la tua iscrizione dallo sportello di " + materia + " ?");
    if (conf == true) {
        $.post("../studente/sportelloCancellaIscrizione.php", {
                id: sportello_id,
				materia: materia
            },
            function (data, status) {
                sportelloReadRecords();
            }
        );
    }
}

function sportelloIscriviti(sportello_id, materia, argomento) {
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
                argomento: argomento
            },
            function (data, status) {
                sportelloReadRecords();
            });
        }
    });
}

$(document).ready(function () {
	sportelloReadRecords();
       
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
