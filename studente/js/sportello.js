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
    if (! argomento) {
        bootbox.prompt({
            title: "<p>Sportello: " + materia + "</p>",
            message: '<p>Inserire l\'argomento:</p>',
            inputType: 'textarea',
            callback: function (testo) {
                if (testo != null) {
                    argomento = testo;
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
        });;
    } else {
        $.post("../studente/sportelloIscriviStudente.php", {
                id: sportello_id,
                materia: materia,
                argomento: argomento
            },
            function (data, status) {
                sportelloReadRecords();
        });

    }
}

$(document).ready(function () {
	sportelloReadRecords();
});
