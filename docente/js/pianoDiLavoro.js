/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var soloTemplate=0;
var anno_filtro_id=0;
var materia_filtro_id=0;
var docente_filtro_id=0;
var stato_filtro_id=0;

$('#soloTemplateCheckBox').change(function() {
    // this si riferisce al checkbox
    if (this.checked) {
		soloTemplate = 1;
    } else {
		soloTemplate = 0;
    }
    pianoDiLavoroReadRecords();
});

function pianoDiLavoroReadRecords() {
	$.get("../docente/pianoDiLavoroReadRecords.php?anchePubblicati=true&soloTemplate=" + soloTemplate + "&anno_filtro_id=" + anno_filtro_id + "&materia_filtro_id=" + materia_filtro_id + "&docente_filtro_id=" + docente_filtro_id + "&stato_filtro_id=" + stato_filtro_id, {}, function (data, status) {
        $(".records_content").html(data);
	});
}

function pianoDiLavoroDelete(id, materia) {
    var conf = confirm("Sei sicuro di volere cancellare il piano di lavoro di " + materia + " ?");
    if (conf == true) {
        $.post("../docente/pianoDiLavoroDelete.php", {
				id: id,
				materia: materia
            },
            function (data, status) {
                if (data=='Application Error') {
                    errorNotify('Impossibile cancellare il piano di lavoro', 'Il piano di lavoro di <Strong>' + materia + '</Strong> contiene probabilmente dei riferimenti');
                } else {
                    infoNotify('Cancellazione effettuata', 'Il piano di lavoro di <Strong>' + materia + '</Strong> è stato cancellato regolarmente');
                }
                pianoDiLavoroReadRecords();
            }
        );
    }
}

function pianoDiLavoroSave() {
    // controlla che ci siano la materia ed il docente
    if ($("#materia").val() <= 0) {
		$("#_error-piano_di_lavoro").text("Devi selezionare una materia");
		$("#_error-piano_di_lavoro-part").show();
		return;
	}
    if ($("#docente").val() <= 0) {
		$("#_error-piano_di_lavoro").text("Devi selezionare un docente");
		$("#_error-piano_di_lavoro-part").show();
		return;
	}

    // se tutto bene nasconde il messaggio di errore e prosegue nel save
    $("#_error-piano_di_lavoro-part").hide();

    var competenze = $('#competenze').summernote('code');
    var note_aggiuntive = $('#note_aggiuntive').summernote('code');

    $.post("../docente/pianoDiLavoroSave.php", {
        id: $("#hidden_piano_di_lavoro_id").val(),
		docente_id: $("#docente").val(),
		materia_id: $("#materia").val(),
		anno_scolastico_id: $("#anno").val(),
        classe: $("#classe").val(),
        indirizzo_id: $("#indirizzo").val(),
        sezione: $("#sezione").val(),
        template: $("#template").is(':checked')? 1: 0,
        clil: $("#clil").is(':checked')? 1: 0,
        stato: $("#stato").val(),
        competenze: competenze,
        note_aggiuntive: note_aggiuntive,
        metodologie: $("#metodologia").val(),
        materiali: $("#materiale").val(),
        tic: $("#tic").val(),
        carenza: 0,
        studente_id: 0
    }, function (data, status) {
        $("#piano_di_lavoro_modal").modal("hide");
        pianoDiLavoroReadRecords();
    });
}

function pianoDiLavoroOpenDocument(piano_di_lavoro_id) {
    $.post("../docente/pianoDiLavoroControllaDiritti.php", {
        piano_di_lavoro_id: piano_di_lavoro_id
    }, function (data, status) {
        window.open('/GestOre/docente/pianoDiLavoroDocumento.php?piano_di_lavoro_id=' + piano_di_lavoro_id, '_blank');
    });
}

function pianoDiLavoroGetDetails(piano_di_lavoro_id) {
    $("#hidden_piano_di_lavoro_id").val(piano_di_lavoro_id);

    if (piano_di_lavoro_id > 0) {
        $.post("../docente/pianoDiLavoroReadDetails.php", {
            piano_di_lavoro_id: piano_di_lavoro_id
        }, function (data, status) {
            console.log(data);
            var piano_di_lavoro = JSON.parse(data);
            $('#docente').selectpicker('val', piano_di_lavoro.docente_id);
            $('#materia').selectpicker('val', piano_di_lavoro.materia_id);
            $('#classe').selectpicker('val', piano_di_lavoro.classe);
            $('#indirizzo').selectpicker('val', piano_di_lavoro.indirizzo_id);
            $("#sezione").val(piano_di_lavoro.sezione);
            $('#anno').selectpicker('val', piano_di_lavoro.anno_scolastico_id);
            $("#template").prop('checked', piano_di_lavoro.template != 0 && piano_di_lavoro.template != null);
            $("#clil").prop('checked', piano_di_lavoro.clil != 0 && piano_di_lavoro.clil != null);
            $('#stato').selectpicker('val', piano_di_lavoro.stato);
            $('#competenze').summernote('code', piano_di_lavoro.competenze);
            $('#note_aggiuntive').summernote('code', piano_di_lavoro.note_aggiuntive);
            $('#metodologia').selectpicker('val', piano_di_lavoro.metodologie);
            $('#materiale').selectpicker('val', piano_di_lavoro.materiali);
            $('#tic').selectpicker('val', piano_di_lavoro.tic);
        });
    } else {
        $('#docente').selectpicker('val', $("#hidden_docente_id").val());
        $('#materia').selectpicker('val', 1);
        $('#classe').selectpicker('val', 1);
        $('#indirizzo').selectpicker('val', 1);
        $("#sezione").val("");
        $('#anno').selectpicker('val', 1);
        $("#template").prop('checked', false);
        $("#clil").prop('checked', false);
        $('#stato').selectpicker('val', 'draft');
        $('#competenze').summernote('code', '');
        $('#note_aggiuntive').summernote('code', '');
        $('#metodologia').selectpicker('val', 0);
        $('#materiale').selectpicker('val', 0);
        $('#tic').selectpicker('val', 0);
    }
	$("#_error-piano_di_lavoro-part").hide();
    $("#piano_di_lavoro_modal").modal("show");
}

function pianoDiLavoroDuplicate(original_piano_di_lavoro_id) {
    bootbox.confirm({
        message: "<p><strong>Attenzione</strong></br></p>"
                + "<p>Il piano di lavoro sta per essere duplicato.</br>"
                + "Al termine della copia i dati del nuovo piano possono essere modificati."
                + "Nel nuovo piano si potrà poi cambiare i contenuti dei moduli.</p>"
                + "<hr style=\"border-top: 2px solid #6699ff;\">"
                + "<p>Vuoi creare una copia di questo piano di lavoro?</p>",
        buttons: {
            confirm: {
                label: 'Si',
                className: 'btn-success'
            },
            cancel: {
                label: 'No',
                className: 'btn-danger'
            }
        },
        callback: function (result) {
            if (result === true) {
                $.post("../docente/pianoDiLavoroDuplica.php", {
                    id: original_piano_di_lavoro_id
                },
                function (data, status) {
                    piano_di_lavoro_id = data;
                    $("#hidden_piano_di_lavoro_id").val(piano_di_lavoro_id);

                    // dopo la duplicazione in genere non voglio essere nella lista dei template
                    soloTemplate=0;
                    $("#soloTemplateCheckBox").prop("checked", false);
                    $('#soloTemplateCheckBox').bootstrapToggle('off');
                    pianoDiLavoroGetDetails(piano_di_lavoro_id);
                });
            } else {
                bootbox.alert('Duplicazione piano di lavoro: operazione cancellata');
            }
        }
    });
}

function pianoDiLavoroCarenza(original_piano_di_lavoro_id) {
    bootbox.confirm({
        message: "<p><strong>Attenzione</strong></br></p>"
                + "<p>Stai creando una nuova carenza.</br>"
                + "Al termine della copia i dati del nuovo piano possono essere modificati."
                + "Nel nuovo piano si potrà poi cambiare i contenuti dei moduli.</p>"
                + "<hr style=\"border-top: 2px solid #6699ff;\">"
                + "<p>Vuoi creare una carenza a partire da questo piano di lavoro?</p>",
        buttons: {
            confirm: {
                label: 'Si',
                className: 'btn-success'
            },
            cancel: {
                label: 'No',
                className: 'btn-danger'
            }
        },
        callback: function (result) {
            if (result === true) {
                $.post("../docente/pianoDiLavoroCreaCarenza.php", {
                    id: original_piano_di_lavoro_id
                },
                function (data, status) {
                    piano_di_lavoro_id = data;
                    $("#hidden_piano_di_lavoro_id").val(piano_di_lavoro_id);
                    pianoDiLavoroGetDetails(piano_di_lavoro_id);
                });
            } else {
                bootbox.alert('Creazione carenza: operazione cancellata');
            }
        }
    });
}

function pianoDiLavoroPreview(piano_di_lavoro_id) {
    window.open('/GestOre/docente/pianoDiLavoroPreview.php?piano_di_lavoro_id=' + piano_di_lavoro_id, '_blank');
}

function pianoDiLavoroSavePdf(piano_di_lavoro_id) {
    window.open('/GestOre/docente/pianoDiLavoroPreview.php?piano_di_lavoro_id=' + piano_di_lavoro_id + '&print=true', '_blank');
}

$(document).ready(function () {

    // se e' collegato un docente, filtra direttamente i suoi piani quando apre la pagina
    if ($("#hidden_docente_id").val() != '') {
        docente_filtro_id = $("#hidden_docente_id").val();
        $('#docente_filtro').selectpicker('val', $("#hidden_docente_id").val());
    }

    pianoDiLavoroReadRecords();

    $('.summernote').summernote({
		height: 120,                 // set editor height
		minHeight: null,             // set minimum height of editor
		maxHeight: null,             // set maximum height of editor
		focus: true                  // set focus to editable area after initializing summernote
	  });

      $('.summernote-small').summernote({
		height: 80,                 // set editor height
		minHeight: null,             // set minimum height of editor
		maxHeight: null,             // set maximum height of editor
		focus: true                  // set focus to editable area after initializing summernote
	  });

    $("#materia_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        materia_filtro_id = this.value;
        pianoDiLavoroReadRecords();
    });

    $("#anno_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        anno_filtro_id = this.value;
        pianoDiLavoroReadRecords();
    });

    $("#docente_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        docente_filtro_id = this.value;
        pianoDiLavoroReadRecords();
    });

    $("#stato_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        stato_filtro_id = this.value;
        pianoDiLavoroReadRecords();
    });

});
