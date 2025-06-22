/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var $classi_filtro_id = 0;
var $materia_filtro_id = 0;
var $docenti_filtro_id = 0;

function programmiSvoltiReadRecords() {
    if ($("#hidden_docente_id").val()>0)
        $docenti_filtro_id=$("#hidden_docente_id").val();
    $.get("programmiSvoltiReadRecords.php?classi_id=" + $classi_filtro_id + "&materia_id=" + $materia_filtro_id + "&docenti_id=" + $docenti_filtro_id, {}, function (data, status) {
        $(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
    });
}

function moduliSvoltiReadRecords(programma_id) {
    $.get("../didattica/moduliSvoltiReadRecords.php", {
        programma_id: programma_id
    }, function (data, status) {
        $(".moduli_content").val("");
        $(".moduli_content")
        $(".moduli_content").html(data);
    });

}

function programmiSvoltiGetDetails(programma_id,duplica) {
    $("#hidden_programma_id").val(programma_id);
    $("#hidden_duplica").val(duplica);
    if (duplica=='true')
    {
        $("#myModalLabel1").html("Duplica il programma per un altra classe");
    }
    else
    {
        $("#myModalLabel1").html("Programma svolto");
    }
    if (programma_id > 0) {
        $.post("../didattica/programmiSvoltiReadDetails.php", {
            programma_id: programma_id
        }, function (data, status) {
            var programma = JSON.parse(data);
            if (duplica=='true')
            {
                $('#classe').selectpicker('val', 0);
            }
            else
            {
                $('#classe').selectpicker('val', programma.programma_classe);
            }
            $('#docente').selectpicker('val', programma.programma_iddocente);
             
            $('#materia').selectpicker('val', programma.programma_idmateria);
            
            if (duplica=='false')
            {
                $('#classe').attr('disabled', true);
            }
            else
            {
                $('#classe').attr('disabled', false);
            }
            $('#docente').attr('disabled', true);
            $('#materia').attr('disabled', true);
            $('#classe').selectpicker('refresh');
            $('#materia').selectpicker('refresh');
            $('#docente').selectpicker('refresh');
        });
        moduliSvoltiReadRecords(programma_id);
    }
    else {
        $('#classe').attr('disabled', false);
        if (id_docente!=0)
        {
            $('#docente').attr('disabled', true);
        }
        else
        {
            $('#docente').attr('disabled', false);
        }
        $('#materia').attr('disabled', false);
        $('#classe').val("0");
        $('#classe').selectpicker('refresh');
        $('#classe').disabled=true;
        $('#docente').val(id_docente);
        $('#docente').selectpicker('refresh');
        $('#materia').val("0");
        $('#materia').selectpicker('refresh');
        $(".moduli_content").html("");

    }
    $("#_error-programma-part").hide();
    $("#programma_modal").modal("show");
}

function moduliSvoltiImport()
{
    programma_id = $("#hidden_programma_id").val();
    if (programma_id>0)
    {
    var conf = confirm("Sei sicuro di volere importare il programma di dipartimento ? Eventuali moduli già presenti saranno sovrascritti.");

    if (conf == true) {
        $.post("../didattica/moduliSvoltiImport.php", {
            programma_modulo_id: programma_id,
            classe_id: $('#classe').val(),
            materia_id: $('#materia').val()
        },
            function (data, status) {
                moduliSvoltiReadRecords($("#hidden_programma_id").val());
            }
        );
    }
    }
}

function moduloSvoltiGetDetails(modulo_id) {
    programma_id = $("#hidden_programma_id").val();
    if (programma_id>0)
    {
    $("#hidden_modulo_id").val(modulo_id);
    nmoduli = parseInt($("#hidden_nmoduli").val());

    if (modulo_id > 0) {
        $.post("../didattica/moduloSvoltiReadDetails.php", {
            modulo_id: modulo_id
        }, function (data, status) {
            var programma = JSON.parse(data);
            $('#titolo').val(programma.modulo_nome);
            $('#ordine').val(programma.modulo_ordine);
            $('#contenuto').val(programma.modulo_contenuto);

        });
    }
    else {
            $('#titolo').val("");
            $('#ordine').val(nmoduli+1);
            $('#contenuto').val("");
            $("#moduli_content").html("");
          }
    $("#_error-modulo-part").hide();
    $("#modulo_modal").modal("show");
    }
}


function programmiSvoltiDelete(id, materia) {
    var conf = confirm("Sei sicuro di volere cancellare il programma di " + materia + " ?");
    if (conf == true) {
        $.post("../didattica/moduliElimina.php", {
            id: id
        });
        $.post("../common/deleteRecord.php", {
            id: id,
            table: 'programmi_svolti',
            name: "materia" + materia
        },
            function (data, status) {
                programmiSvoltiReadRecords();
            }
        );
    }
}

function moduloSvoltiDelete(id, id_programma, titolo) {
    var conf = confirm("Sei sicuro di volere cancellare il modulo  " + titolo + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
            id: id,
            table: 'programmi_svolti_moduli',
            name: "nome" + titolo
        },
            function (data, status) {
                moduliSvoltiReadRecords(id_programma);
                 //$("#programma_modal").modal("hide");
            }
        );
    }
}

function programmiSvoltiSave() {

    if ($("#docente").val() <= 0) {
        $("#_error-programma").text("Devi selezionare un docente");
        $("#_error-programma-part").show();
        return;
    }
    if ($("#classe").val() <= 0) {
        $("#_error-programma").text("Devi selezionare una classe");
        $("#_error-programma-part").show();
        return;
    }
    if ($("#materia").val() <= 0) {
        $("#_error-programma").text("Devi selezionare una materia");
        $("#_error-programma-part").show();
        return;
    }

    $("#_error-programma-part").hide();

    $.post("programmiSvoltiSave.php", {
        id: $("#hidden_programma_id").val(),
        docente_id: $("#docente").val(),
        classe_id: $("#classe").val(),
        materia_id: $("#materia").val(),
        duplica: $("#hidden_programma_id").val()
    }, function (data, status) {
        $("#programma_modal").modal("hide");
        programmiSvoltiReadRecords();
    });

}

function moduloSvoltiSave() {

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
    if ($.trim($("#contenuto").val()).length <= 0) {
        $("#_error-modulo").text("Devi indicare il contenuto");
        $("#_error-modulo-part").show();
        return;
    }
    $("#_error-modulo-part").hide();
      console.log("salvataggio in corso");
    $.post("moduloSvoltiSave.php", {
        id: $("#hidden_modulo_id").val(),
        id_programma: $("#hidden_programma_id").val(),
        ordine: $("#ordine").val(),
        titolo: $("#titolo").val(),
        contenuto: $("#contenuto").val(),
    }, function (data, status) {
        $("#modulo_modal").modal("hide");
        moduliSvoltiReadRecords($("#hidden_programma_id").val());
    });

}


$(document).ready(function () {


    programmiSvoltiReadRecords();

    $("#classi_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $classi_filtro_id = this.value;
            programmiSvoltiReadRecords();
        });

    $("#materia_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $materia_filtro_id = this.value;
            programmiSvoltiReadRecords();
        });

    $("#docente_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $docenti_filtro_id = this.value;
            programmiSvoltiReadRecords();
        });

    });     
