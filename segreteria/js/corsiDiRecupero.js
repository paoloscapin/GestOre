/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var originalCodice = '';
var originalAula = '';
var originalDocente_id = 0;
var originalMateria_id = 0;
var originalTestoLezioni = '';
var originalTestoStudenti = '';
var modificabile = true;

var inItinere = 0;

$('#inItinereCheckBox').change(function() {
    // this si riferisce al checkbox 
    if (this.checked) {
		inItinere = 1;
        corsiDiRecuperoReadRecords();
    } else {
		inItinere = 0;
        corsiDiRecuperoReadRecords();
    }
});

function corsiDiRecuperoReadRecords() {
	$.get("corsiDiRecuperoReadRecords.php?inItinere=" + inItinere, {}, function (data, status) {
		$(".records_content").html(data);
	});
}

function corsiDiRecuperoGetDetails(id) {
    // in principio modificabile, a meno che alcune lezioni siano gia' firmate
    modificabile = true;

    $("#hidden_record_id").val(id);
    if (id > 0) {
        $.post("../segreteria/corsiDiRecuperoReadDetails.php", {
			id: id
		},
		function (data, status) {
            // console.log(data);
            var record = JSON.parse(data);
            $("#codice").val(record.corso_di_recupero_codice);
            $("#aula").val(record.corso_di_recupero_aula);
            $('#docente').selectpicker('val', record.docente_id);
            $('#materia').selectpicker('val', record.materia_id);

            var testoLezioni = '';
            record.lezioni.forEach(function(lezione) {
                testoLezioni = testoLezioni + lezione.data + ' - ' + lezione.inizia_alle + ' - ' + lezione.numero_ore + ' - ' + lezione.orario + '\n';
                console.log(lezione);
                if (lezione.firmato == 1) {
                    modificabile = false;
                }
            });
            $("#lezioni").val(testoLezioni);

            var testoStudenti = '';
            record.studenti.forEach(function(studente) {
                testoStudenti = testoStudenti + studente.classe + ' - ' + studente.cognome + ' - ' + studente.nome + ' - ';
                if (studente.serve_voto == 0) {
                    testoStudenti += 'uditore - ';
                }
                testoStudenti += '\n';
            });
            $("#studenti").val(testoStudenti);

            // memorizza i valori per vedere poi se sono cambiati
            originalCodice = record.corso_di_recupero_codice;
            originalAula = record.corso_di_recupero_aula;
            originalDocente_id = record.docente_id;
            originalMateria_id = record.materia_id;
            originalTestoLezioni = testoLezioni;
            originalTestoStudenti = testoStudenti;
		});
    } else {
        $("#codice").val("");
        $("#aula").val("");
        $('#docente').selectpicker('val', 0);
        $('#materia').selectpicker('val', 0);
        originalTestoLezioni = '';
        originalTestoStudenti = '';
        originalCodice = '';
        originalAula = '';
        originalDocente_id = 0;
        originalMateria_id = 0;
        }
	$("#update_modal").modal("show");
}

function corsiDiRecuperoSave() {
    var datiModificati = (originalCodice != $("#codice").val() || originalAula != $("#aula").val() || originalDocente_id != $("#docente").val() || originalMateria_id != $("#materia").val());
    var testoLezioniModificato = $("#lezioni").val() != originalTestoLezioni;
    var testoStudentiModificato = $("#studenti").val() != originalTestoStudenti;

    // controlla se sono cambiati i campi
    if (datiModificati || testoLezioniModificato || testoStudentiModificato) {
    // se non e' modificabile, chiede una conferma che sia davvero voluto
        if (!modificabile) {
            var conf = confirm("Attenzione !!!\r\n\r\nIl corso di recupero " + originalCodice + " contiene delle lezioni firmate.\r\nModificando i dati del corso potrebbe portare a delle inconsistenze.\r\n\r\nSei sicuro di volere modificare i dati?");
            if (conf != true) {
                corsiDiRecuperoReadRecords();
                $("#update_modal").modal("hide");
                return;
            }
        }
        $.post("../segreteria/corsiDiRecuperoSave.php", {
                    id: $("#hidden_record_id").val(),
                    codice: $("#codice").val(),
                    aula: $("#aula").val(),
                    docente_id: $("#docente").val(),
                    materia_id: $("#materia").val(),
                    datiModificati: datiModificati ? 1 : 0,
                    testoLezioni: $("#lezioni").val(),
                    testoLezioniModificato: testoLezioniModificato ? 1 : 0,
                    testoStudenti: $("#studenti").val(),
                    testoStudentiModificato: testoStudentiModificato ? 1 : 0
                },
                function (data, status) {
                    corsiDiRecuperoReadRecords();
                });
        }
    $("#update_modal").modal("hide");
}

function corsiDiRecuperoDelete(id, nome) {
    var conf = confirm("Sei sicuro di volere cancellare l'elemento " + nome + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
				id: id,
				table: 'corso_di_recupero',
				name: nome
            },
            function (data, status) {
                corsiDiRecuperoReadRecords();
            }
        );
    }
}

function corsiDiRecuperoImport() {
    var selectDialogueLink = $('<a href="">Select files</a>');
    var fileSelector = $('<input type="file">');
    
    selectDialogueLink.click(function(){
        fileSelector.click();
        return false;
    });
    $('body').html(selectDialogueLink);
}

function importFile(file) {
    var contenuto = "";
    const reader = new FileReader();
    reader.addEventListener('load', (event) => {
        contenuto = event.target.result;
        $.post("corsiDiRecuperoImport.php", {
            contenuto: contenuto
        },
        function (data, status) {
            $('#result_text').html(data);
        });
    });
    reader.readAsText(file);
}

$(document).ready(function () {
    corsiDiRecuperoReadRecords();

    $('#file_select_id').change(function (e) {
        importFile(e. target. files[0]);
    });
});