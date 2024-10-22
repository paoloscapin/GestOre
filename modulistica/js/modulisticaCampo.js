/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// Read records
function modulisticaCampoReadRecords() {
	$.get("modulisticaCampoReadRecords.php?template_id=" + $("#hidden_template_id").val(), {}, function (data, status) {
		$(".records_content").html(data);
        readTemplate();
	});
}

function readTemplate() {
    $.post("../common/readRecordDetails.php", {
        id: $("#hidden_template_id").val(),
        table: 'modulistica_template'
    },
    function (data, status) {
        var record = JSON.parse(data);
        $('#template').summernote('code', record.template);
    });
}

function modulisticaCampoSave() {
    $.post("../modulistica/modulisticaCampoSave.php", {
        id: $("#hidden_template_campo_id").val(),
        nome: $("#nome").val(),
        etichetta: $("#etichetta").val(),
        tip: $("#tip").val(),
        tipo: $("#tipo").val(),
        lista_valori: $("#lista_valori").val(),
        valore_default: $("#valore_default").val(),
        salva_valore: $("#salva_valore").is(':checked')? 1: 0,
        obbligatorio: $("#obbligatorio").is(':checked')? 1: 0,
        modulistica_id: $("#hidden_template_id").val(),
    }, function (data, status) {
        $("#modulistica_campo_modal").modal("hide");
        modulisticaCampoReadRecords();
    });
}

function modulisticaTemplateSave() {
	var contenuto = $('#template').summernote('code');

    $.post("../common/recordUpdate.php", {
        table: 'modulistica_template',
        id: $("#hidden_template_id").val(),
        nome: 'template',
        valore: contenuto
    }, function (data, status) {
        $("#modulistica_campo_modal").modal("hide");
        modulisticaCampoReadRecords();
    });
}

function modulisticaCampoRemove(modulistica_campo_id, modulistica_contenuto_posizione) {
    var conf = confirm("Sei sicuro di volere cancellare il modulo " + modulistica_contenuto_posizione + " ?");
    if (conf == true) {
        $.post("../modulistica/modulisticaCampoDelete.php", {
                id: modulistica_campo_id,
                modulistica_template_id: $("#hidden_template_id").val(),
				posizione: modulistica_contenuto_posizione
            },
            function (data, status) {
                modulisticaCampoReadRecords();
            }
        );
    }
}

function modulisticaCampoGetDetails(modulistica_campo_id) {
    $("#hidden_template_campo_id").val(modulistica_campo_id);

    if (modulistica_campo_id > 0) {
        $.post("../common/readRecordDetails.php", {
            id: modulistica_campo_id,
            table: "modulistica_template_campo"
        }, function (data, status) {
            var modulistica = JSON.parse(data);
			$("#nome").val(modulistica.nome);
			$("#etichetta").val(modulistica.etichetta);
			$("#tip").val(modulistica.tip);
			$('#tipo').selectpicker('val', modulistica.tipo);
			$("#lista_valori").val(modulistica.lista_valori);
			$("#valore_default").val(modulistica.valore_default);
            $("#salva_valore").prop('checked', modulistica.salva_valore != 0 && modulistica.salva_valore != null);
            $("#obbligatorio").prop('checked', modulistica.obbligatorio != 0 && modulistica.obbligatorio != null);
        });
    } else {
        $("#nome").val("");
        $("#etichetta").val("");
        $("#tip").val("");
        $("#tipo").selectpicker('val', 0);
        $("#valore_default").val("");
        $("#nome").val("");
    }
    $("#modulistica_campo_modal").modal("show");
}

function move(modulistica_campo_posizione, diQuanto) {
    $.post("../modulistica/modulisticaCampoMove.php", {
        template_id: $("#hidden_template_id").val(),
        modulistica_campo_posizione: modulistica_campo_posizione,
		di_quanto: diQuanto
    }, function (data, status) {
        modulisticaCampoReadRecords();
    });
}

function modulisticaPreview(modulistica_id) {
    window.open('/GestOre/docente/modulisticaPreview.php?modulistica_id=' + modulistica_id, '_blank');
}

function moveUp(modulistica_contenuto_posizione) {
	// non posso spostarlo in su dalla prima posizione
	if (modulistica_contenuto_posizione == 1) {
		return;
	}
	move(modulistica_contenuto_posizione, -1);
}

function moveDown(modulistica_contenuto_posizione) {
	move(modulistica_contenuto_posizione, 1);
}

// Read records on page load
$(document).ready(function () {
	$('.summernote').summernote({
		height: 200,                 // set editor height
		minHeight: null,             // set minimum height of editor
		maxHeight: null,             // set maximum height of editor
		focus: true                  // set focus to editable area after initializing summernote
	  });
    modulisticaCampoReadRecords();
});
