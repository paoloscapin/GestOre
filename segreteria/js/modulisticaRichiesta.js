/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var anno_filtro_id=0;
var docente_filtro_id=0;
var stato_filtro_id=0;

function modulisticaRichiestaReadRecords() {
	$.get("../segreteria/modulisticaRichiestaReadRecords.php?anno_filtro_id=" + anno_filtro_id + "&docente_filtro_id=" + docente_filtro_id + "&stato_filtro_id=" + stato_filtro_id, {}, function (data, status) {
        $(".records_content").html(data);
	});
}

function modulisticaRichiestaAggiorna(stato) {
    $.get("../docente/modulisticaRichiestaApprova.php", {
        richiesta_id: $("#hidden_modulistica_richiesta_id").val(),
        uuid: $("#hidden_modulistica_richiesta_uuid").val(),
        comando: stato,
        messaggio: $("#messaggio").val(), 
    }, function (data, status) {
        modulisticaRichiestaReadRecords();
        $("#modulistica_richiesta_modal").modal("hide");
	});
}

function modulisticaRichiestaGetDetails(modulistica_richiesta_id, modulistica_richiesta_uuid) {
    $("#hidden_modulistica_richiesta_id").val(modulistica_richiesta_id);
    $("#hidden_modulistica_richiesta_uuid").val(modulistica_richiesta_uuid);

    if (modulistica_richiesta_id > 0) {
        $.post("../segreteria/modulisticaRichiestaReadDetails.php", {
            modulistica_richiesta_id: modulistica_richiesta_id
        }, function (data, status) {
            // console.log(data);
            var modulistica_richiesta = JSON.parse(data);
            $("#data").text(modulistica_richiesta.data_invio);
            $("#docente").text(modulistica_richiesta.docente);
            $("#anno").text(modulistica_richiesta.anno);
            $("#tabella").html(modulistica_richiesta.tabella);

            $("#_approva-part").hide();
            $("#_status-marker").show();
            $("#_approvata-marker").hide();
            $("#_respinta-marker").hide();
            $("#_annullata-marker").hide();
            $("#_chiusa-marker").hide();
            $("#_in_lavorazione-marker").hide();
            $("#chiudi-part").hide();
            $("#_messaggio_approvazione-part").show();

            if (modulistica_richiesta.approvata == 1) {
                $("#_status-marker").show();
                $("#_approvata-marker").show();
                $("#approva-part").hide();
            } else if (modulistica_richiesta.respinta == 1) {
                $("#_status-marker").show();
                $("#_respinta-marker").show();
                $("#approva-part").hide();
            } else if (modulistica_richiesta.annullata == 1) {
                $("#_status-marker").show();
                $("#_annullata-marker").show();
                $("#approva-part").hide();
            } else {
                if (modulistica_richiesta.template.approva == 1) {
                    $("#_in_lavorazione-marker").show();
                    $("#_approva-part").show();
                    $("#_messaggio_approvazione-part").hide();
                }
            }
            if (modulistica_richiesta.chiusa == 1) {
                $("#_status-marker").show();
                $("#_chiusa-marker").show();
            }
            $("#messaggio_approvazione").text(modulistica_richiesta.messaggio);

            // lo puoi chiudere se e' approvato, respinto o non richiede approvazione, oppure se e' annullato e comunque se non ancora chiuso
            if (modulistica_richiesta.chiusa != 1 && ((modulistica_richiesta.approvata == 1 || modulistica_richiesta.respinta == 1 || modulistica_richiesta.annullata == 1 ) || (modulistica_richiesta.annullata != 1 && modulistica_richiesta.template.approva != 1) )) {
                $("#chiudi-part").show();
            }
        });
    }
    $("#modulistica_richiesta_modal").modal("show");
}

$(document).ready(function () {

    // anno scolastico di default e' quello corrente
    if ($("#hidden_anno_scolastico_id").val() != '') {
        anno_filtro_id = $("#hidden_anno_scolastico_id").val();
        $('#anno_filtro').selectpicker('val', $("#hidden_anno_scolastico_id").val());
    }
    
    modulisticaRichiestaReadRecords();

    $("#anno_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        anno_filtro_id = this.value;
        modulisticaRichiestaReadRecords();
    });

    $("#docente_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        docente_filtro_id = this.value;
        modulisticaRichiestaReadRecords();
    });

    $("#stato_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        stato_filtro_id = this.value;
        modulisticaRichiestaReadRecords();
    });
});
