/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function sportelloReadRecords() {
	$.get("sportelloReadRecords.php?ancheCancellati=true", {}, function (data, status) {
		$(".records_content").html(data);
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
    var primoIscritto = argomento ? false : true;
    var titolo = "<p>Sportello: " + materia + "</p>";
    var messaggio = primoIscritto ? "<p>Inserire l\'argomento per lo sportello:</p>" : "<p>Confermare l\'argomento per lo sportello:</p>" + argomento;
    var inputType = primoIscritto ? 'textarea' : 'checkbox';
    var inputOptions = primoIscritto ? [] : [{text: 'Confermo',value: '1',}];
    var value = primoIscritto ? [] : ['1'];

    bootbox.prompt({
        title: titolo,
        message: messaggio,
        inputType: inputType,
        inputOptions: inputOptions,
        value: value,
        required: true,

        callback: function (result) {
            console.log('result='+result);
            // null se cancel
            if (!result) {
                console.log('result is null: ritorno');
                return;
            }
            if (argomento) {
                // controlla il checkbox
                if (result != 1) {
                    console.log('result='+result + ' ritorno');
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
});
