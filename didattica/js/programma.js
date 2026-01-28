/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var $anno_filtro_id = 0;
var $indirizzo_filtro_id = 0;
var $materia_filtro_id = 0;

function programmiReadRecords() {
    $.get("programmiReadRecords.php?anno_id=" + $anno_filtro_id + "&indirizzo_id=" + $indirizzo_filtro_id + "&materia_id=" + $materia_filtro_id, {}, function (data, status) {
        $(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
    });
}

function moduliReadRecords(programma_id) {
    $.get("../didattica/moduliReadRecords.php", {
        programma_id: programma_id
    }, function (data, status) {
        $(".moduli_content").html(data);
    });

}

function programmaGetDetails(programma_id) {
    $("#hidden_programma_id").val(programma_id);

    if (programma_id > 0) {
        $.post("../didattica/programmaReadDetails.php", { programma_id: programma_id }, function (data, status) {
            var programma = JSON.parse(data);

            $('#anno').selectpicker('val', programma.programma_anno).selectpicker('refresh');
            $('#indirizzo').selectpicker('val', programma.programma_idindirizzo).selectpicker('refresh');
            $('#materia').selectpicker('val', programma.programma_idmateria).selectpicker('refresh');
        });

        moduliReadRecords(programma_id);
    } else {
        $('#anno').val("0").selectpicker('refresh');
        $('#indirizzo').val("0").selectpicker('refresh');
        $('#materia').val("0").selectpicker('refresh');
    }

    $("#_error-programma-part").hide();
    $("#programma_modal").modal("show");
}

function setModuloModalEditable(canEdit) {
    var $modal = $("#modulo_modal");

    // campi
    var $fields = $modal.find("input:not([type=hidden]), textarea, select");
    $fields.prop("disabled", !canEdit);

    // selectpicker refresh se presenti
    try { $modal.find("select.selectpicker").selectpicker("refresh"); } catch (e) {}

    // testo chiudi
    $("#btnModuloClose").text(canEdit ? "Annulla" : "Chiudi");

    // ✅ bottone salva: esiste sempre (grazie alla patch PHP)
    if (canEdit) {
        $("#btnModuloSave").prop("disabled", false).show();
    } else {
        $("#btnModuloSave").prop("disabled", true).hide(); // oppure show() ma disabled, a tua scelta
    }
}


function moduloGetDetails(modulo_id) {
    $("#hidden_modulo_id").val(modulo_id);
    var nmoduli = parseInt($("#hidden_nmoduli").val(), 10) || 0;

    // default: apro in read-only e poi eventualmente abilito
    setModuloModalEditable(false);

    $("#_error-modulo-part").hide();

    if (modulo_id > 0) {

        $.ajax({
            url: "../didattica/moduloReadDetails.php",
            type: "POST",
            dataType: "json",
            data: { modulo_id: modulo_id },
            success: function (programma) {

                if (!programma || !programma.ok) {
                    var msg = (programma && programma.error) ? programma.error : "Errore lettura modulo.";
                    alert(msg);
                    return;
                }

                $('#titolo').val(programma.modulo_nome || "");
                $('#ordine').val(programma.modulo_ordine || "");
                $('#conoscenze').val(programma.modulo_conoscenze || "");
                $('#abilita').val(programma.modulo_abilita || "");
                $('#competenze').val(programma.modulo_competenze || "");
                $('#periodo').val(programma.modulo_periodo || "");
                console.log(parseInt(programma.can_edit, 10) === 1);
                // ✅ qui la parte chiave: abilito se coordinatore (o segreteria/dirigente)
                setModuloModalEditable(parseInt(programma.can_edit, 10) === 1);

                $("#modulo_modal").modal("show");
            },
            error: function (xhr, st, err) {
                console.error("moduloReadDetails FAIL", st, err, xhr && xhr.responseText);
                alert("Errore lettura modulo (vedi console).");
            }
        });

    } else {
        // nuovo modulo
        $('#titolo').val("");
        $('#ordine').val(nmoduli + 1);
        $('#conoscenze').val("");
        $('#abilita').val("");
        $('#competenze').val("");
        $('#periodo').val("");
        setModuloModalEditable(true);
        // ⚠️ per il nuovo modulo serve comunque sapere se può editare:
        // se il tuo contesto “programma corrente” è noto (es. hidden_programma_id),
        // allora fai una chiamata a un endpoint che restituisce can_edit per quel programma.
        // Per ora (patch minima): lo lasciamo in read-only finché non mi dici come recuperi programma_id.

        $("#modulo_modal").modal("show");
    }
}

function programmaDelete(id, materia) {
    var conf = confirm("Sei sicuro di volere cancellare la materia di " + materia + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
            id: id,
            table: 'programma_materie',
            name: "materia" + materia
        },
            function (data, status) {
                programmiReadRecords();
            }
        );
    }
}

function moduloDelete(id, id_programma, titolo) {
    var conf = confirm("Sei sicuro di volere cancellare il modulo  " + titolo + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
            id: id,
            table: 'programma_moduli',
            name: "nome" + titolo
        },
            function (data, status) {
                moduliReadRecords(id_programma);
                 //$("#programma_modal").modal("hide");
            }
        );
    }
}

function programmaSave() {

    if ($("#anno").val() <= 0) {
        $("#_error-programma").text("Devi selezionare un anno");
        $("#_error-programma-part").show();
        return;
    }
    if ($("#indirizzo").val() <= 0) {
        $("#_error-programma").text("Devi selezionare un indirizzo");
        $("#_error-programma-part").show();
        return;
    }
    if ($("#materia").val() <= 0) {
        $("#_error-programma").text("Devi selezionare una materia");
        $("#_error-programma-part").show();
        return;
    }

    $("#_error-programma-part").hide();

    $.post("programmaSave.php", {
        id: $("#hidden_programma_id").val(),
        anno_id: $("#anno").val(),
        indirizzo_id: $("#indirizzo").val(),
        materia_id: $("#materia").val(),
    }, function (data, status) {
        $("#programma_modal").modal("hide");
        programmiReadRecords();
    });

}

function programmaPrint(id_programma) {
  // creo form nascosto
  var form = $('<form>', {
    action: 'stampaProgramma.php',
    method: 'POST',
    target: '_black'    // apre in un nuovo tab
  });
  // aggiungo i campi
  form.append($('<input>', {type:'hidden', name:'id',     value:id_programma}));
  form.append($('<input>', {type:'hidden', name:'print',  value:0}));
  form.append($('<input>', {type:'hidden', name:'titolo', value:'Programma didattico'}));
  // lo “submitto” e lo rimuovo
  form.appendTo('body').submit().remove();
}

function moduloSave() {

    if ($.trim($("#ordine").val()).length <= 0) {
        $("#_error-modulo").text("Devi indicare l'ordine del modulo, ad es. 1");
        $("#_error-modulo-part").show();
        return;
    }
    if ($.trim($("#titolo").val()).length <= 0) {
        $("#_error-modulo").text("Devi indicare il titolo del modulo");
        $("#_error-modulo-part").show();
        return;
    }
    if ($.trim($("#conoscenze").val()).length <= 0) {
        $("#_error-modulo").text("Devi indicare tutte le conoscenze");
        $("#_error-modulo-part").show();
        return;
    }
    if ($.trim($("#abilita").val()).length <= 0) {
        $("#_error-modulo").text("Devi indicare almeno una abilità");
        $("#_error-modulo-part").show();
        return;
    }
    if ($.trim($("#competenze").val()).length <= 0) {
        $("#_error-modulo").text("Devi indicare almeno una competenza");
        $("#_error-modulo-part").show();
        return;
    }
    if ($.trim($("#periodo").val()).length <= 0) {
        $("#_error-modulo").text("Devi indicare il periodo di svolgimento");
        $("#_error-modulo-part").show();
        return;
    }
    $("#_error-modulo-part").hide();
      console.log("salvataggio in corso");
    $.post("moduloSave.php", {
        id: $("#hidden_modulo_id").val(),
        id_programma: $("#hidden_programma_id").val(),
        ordine: $("#ordine").val(),
        titolo: $("#titolo").val(),
        conoscenze: $("#conoscenze").val(),
        abilita: $("#abilita").val(),
        competenze: $("#competenze").val(),
        periodo: $("#periodo").val()
    }, function (data, status) {
        $("#modulo_modal").modal("hide");
        $("#programma_modal").modal("show");
        moduliReadRecords($("#hidden_programma_id").val());
    });

}


$(document).ready(function () {

    programmiReadRecords();

    $("#annoCorso_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $anno_filtro_id = this.value;
            programmiReadRecords();
        });

    $("#indirizzoCorso_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $indirizzo_filtro_id = this.value;
            programmiReadRecords();
        });

    $("#materia_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $materia_filtro_id = this.value;
            programmiReadRecords();
        });

});     
