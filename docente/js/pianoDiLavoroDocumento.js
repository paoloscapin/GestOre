/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// Read records
function pianoDiLavoroDocumentoReadRecords() {
	$.get("pianoDiLavoroDocumentoReadRecords.php?piano_di_lavoro_id=" + $("#hidden_piano_di_lavoro_id").val(), {}, function (data, status) {
		$(".records_content").html(data);
	});
}

function pianoDiLavoroDocumentoSave() {

	var contenuto = $('#testo').summernote('code');

    $.post("../docente/pianoDiLavoroDocumentoSave.php", {
        id: $("#hidden_piano_di_lavoro_documento_id").val(),
        titolo: $("#titolo").val(),
        testo: contenuto,
        piano_di_lavoro_id: $("#hidden_piano_di_lavoro_id").val(),
    }, function (data, status) {
        $("#piano_di_lavoro_documento_modal").modal("hide");
        pianoDiLavoroDocumentoReadRecords();
    });
}

function pianoDiLavoroDocumentoRemove(piano_di_lavoro_documento_id, piano_di_lavoro_contenuto_posizione) {
    var conf = confirm("Sei sicuro di volere cancellare il modulo " + piano_di_lavoro_contenuto_posizione + " ?");
    if (conf == true) {
        $.post("../docente/pianoDiLavoroDocumentoDelete.php", {
                id: piano_di_lavoro_documento_id,
                piano_di_lavoro_id: $("#hidden_piano_di_lavoro_id").val(),
				posizione: piano_di_lavoro_contenuto_posizione
            },
            function (data, status) {
                pianoDiLavoroDocumentoReadRecords();
            }
        );
    }
}

function pianoDiLavoroDocumentoGetDetails(piano_di_lavoro_documento_id) {
    $("#hidden_piano_di_lavoro_documento_id").val(piano_di_lavoro_documento_id);

    if (piano_di_lavoro_documento_id > 0) {
        $.post("../common/readRecordDetails.php", {
            id: piano_di_lavoro_documento_id,
            table: "piano_di_lavoro_contenuto"
        }, function (data, status) {
            console.log(data);
            var piano_di_lavoro = JSON.parse(data);
			$("#titolo").val(piano_di_lavoro.titolo);
			$('#testo').summernote('code', piano_di_lavoro.testo);
        });
    } else {
        $("#titolo").val("");
        $('#testo').summernote('code', '');
    }
    $("#piano_di_lavoro_documento_modal").modal("show");
}

function move(piano_di_lavoro_contenuto_posizione, diQuanto) {
    $.post("../docente/pianoDiLavoroDocumentoMove.php", {
        piano_di_lavoro_id: $("#hidden_piano_di_lavoro_id").val(),
        piano_di_lavoro_contenuto_posizione: piano_di_lavoro_contenuto_posizione,
		di_quanto: diQuanto
    }, function (data, status) {
        pianoDiLavoroDocumentoReadRecords();
    });
}

function pianoDiLavoroPreview(piano_di_lavoro_id) {
    window.open('/GestOre/docente/pianoDiLavoroPreview.php?piano_di_lavoro_id=' + piano_di_lavoro_id, '_blank');
}

function moveUp(piano_di_lavoro_contenuto_posizione) {
	// non posso spostarlo in su dalla prima posizione
	if (piano_di_lavoro_contenuto_posizione == 1) {
		return;
	}
	move(piano_di_lavoro_contenuto_posizione, -1);
}

function moveDown(piano_di_lavoro_contenuto_posizione) {
	move(piano_di_lavoro_contenuto_posizione, 1);
}

// Read records on page load
$(document).ready(function () {
	$('.summernote').summernote({
		height: 300,                 // set editor height
		minHeight: null,             // set minimum height of editor
		maxHeight: null,             // set maximum height of editor
		focus: true                  // set focus to editable area after initializing summernote
	  });
    pianoDiLavoroDocumentoReadRecords();
});
