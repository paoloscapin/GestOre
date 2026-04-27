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
    $.get("programmaMinimiReadRecords.php?anno_id=" + $anno_filtro_id + "&indirizzo_id=" + $indirizzo_filtro_id + "&materia_id=" + $materia_filtro_id, {}, function (data, status) {
        $(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
    });
}

function moduliReadRecords(programma_id) {
    $.get("../didattica/moduliMinimiReadRecords.php", {
        programma_id: programma_id
    }, function (data, status) {
        $(".moduli_content").html(data);
    });

}

function setModuloModalEditable(canEdit) {
    var $modal = $("#modulo_modal");
    var $fields = $modal.find("input:not([type=hidden]), textarea, select");
    $fields.prop("disabled", !canEdit);

    try { $modal.find("select.selectpicker").selectpicker("refresh"); } catch (e) {}

    $("#btnModuloClose").text(canEdit ? "Annulla" : "Chiudi");
    if (canEdit) {
        $("#btnModuloSave").prop("disabled", false).show();
    } else {
        $("#btnModuloSave").prop("disabled", true).hide();
    }
}

function setProgrammaModalEditable(canEdit) {
    var $modal = $("#programma_modal");
    var $fields = $modal.find("#anno, #indirizzo, #materia");
    $fields.prop("disabled", !canEdit);

    try { $modal.find("select.selectpicker").selectpicker("refresh"); } catch (e) {}

    $("#btnProgrammaClose").text(canEdit ? "Annulla" : "Chiudi");
    if (canEdit) {
        $("#btnProgrammaSave").prop("disabled", false).show();
    } else {
        $("#btnProgrammaSave").prop("disabled", true).hide();
    }
}

function programmaGetDetails(programma_id) {
    $("#hidden_programma_id").val(programma_id);
    setProgrammaModalEditable(false);

    if (programma_id > 0) {
        $.post("../didattica/programmaMinimiReadDetails.php", {
            programma_id: programma_id
        }, function (data, status) {
            var programma = (typeof data === "string") ? JSON.parse(data) : data;
            if (!programma || !programma.ok) {
                alert((programma && programma.error) ? programma.error : "Errore lettura programma.");
                return;
            }
            $('#anno').selectpicker('val', programma.programma_anno);
            $('#indirizzo').selectpicker('val', programma.programma_idindirizzo);
            $('#materia').selectpicker('val', programma.programma_idmateria);
            setProgrammaModalEditable(parseInt(programma.can_edit, 10) === 1);
            $("#programma_modal").modal("show");
        });
        moduliReadRecords(programma_id);
    }
    else {
        $('#anno').val("0");
        $('#anno').selectpicker('refresh');
        $('#indirizzo').val("0");
        $('#indirizzo').selectpicker('refresh');
        $('#materia').val("0");
        $('#materia').selectpicker('refresh');
        setProgrammaModalEditable(false);
        $("#programma_modal").modal("show");
    }
    $("#_error-programma-part").hide();
}

function moduloGetDetails(modulo_id) {
    $("#hidden_modulo_id").val(modulo_id);
    nmoduli = parseInt($("#hidden_nmoduli").val(), 10) || 0;
    setModuloModalEditable(false);

    if (modulo_id > 0) {
        $.post("../didattica/moduloMinimiReadDetails.php", {
            modulo_id: modulo_id
        }, function (data, status) {
            var programma = (typeof data === "string") ? JSON.parse(data) : data;
            if (!programma || !programma.ok) {
                alert((programma && programma.error) ? programma.error : "Errore lettura modulo.");
                return;
            }
            $('#titolo').val(programma.modulo_nome);
            $('#ordine').val(programma.modulo_ordine);
            $('#conoscenze').val(programma.modulo_conoscenze);
            $('#abilita').val(programma.modulo_abilita);
            setModuloModalEditable(parseInt(programma.can_edit, 10) === 1);
            $("#modulo_modal").modal("show");
        });
    }
    else {
            $('#titolo').val("");
            $('#ordine').val(nmoduli+1);
            $('#conoscenze').val("");
            $('#abilita').val("");
            setModuloModalEditable(false);
            $("#modulo_modal").modal("show");
    }
    $("#_error-modulo-part").hide();
}

function programmaDelete(id, materia) {
    var conf = confirm("Sei sicuro di volere cancellare la materia di " + materia + " ?");
    if (conf == true) {
        $.post("../didattica/programmaMinimiDelete.php", {
            id: id
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
        $.post("../didattica/moduloMinimiDelete.php", {
            id: id
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

    $.post("programmaMinimiSave.php", {
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
    action: 'stampaProgrammaMinimi.php',
    method: 'POST',
    target: '_black'    // apre in un nuovo tab
  });
  // aggiungo i campi
  form.append($('<input>', {type:'hidden', name:'id',     value:id_programma}));
  form.append($('<input>', {type:'hidden', name:'print',  value:0}));
  form.append($('<input>', {type:'hidden', name:'titolo', value:'Programma obiettivi minimi'}));
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

    $("#_error-modulo-part").hide();
      console.log("salvataggio in corso");
    $.post("moduloMinimiSave.php", {
        id: $("#hidden_modulo_id").val(),
        id_programma: $("#hidden_programma_id").val(),
        ordine: $("#ordine").val(),
        titolo: $("#titolo").val(),
        conoscenze: $("#conoscenze").val(),
        abilita: $("#abilita").val()
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
