/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var $docente_filtro_id = 0;
var $classe_filtro_id = 0;
var $materia_filtro_id = 0;
var $studente_filtro_id = 0;
var $anno_filtro_id = 0;

function carenzeReadRecords() {
    $.get("carenzeReadRecords.php?anno=" + $anno_filtro_id + "&docente_id=" + $docente_filtro_id + "&classe_id=" + $classe_filtro_id + "&materia_id=" + $materia_filtro_id + "&studente_id=" + $studente_filtro_id, {}, function (data, status) {
        $(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
    });
}

function carenzeGetDetails(carenza_id) {
    $("#hidden_carenza_id").val(carenza_id);

    if (carenza_id > 0) {
        $.post("../didattica/carenzeReadDetails.php", {
            carenza_id: carenza_id
        }, function (data, status) {
            var carenza = JSON.parse(data);
            $('#classe').selectpicker('val', carenza.carenza_id_classe);
            $('#materia').selectpicker('val', carenza.carenza_id_materia);
            $('#studente').selectpicker('val', carenza.carenza_id_studente);
        });
        carenzeReadRecords(carenza_id);
    }
    else {
        $('#classe').val("0");
        $('#classe').selectpicker('refresh');
        $('#materia').val("0");
        $('#materia').selectpicker('refresh');
        $('#studente').val("0");
        $('#studente').selectpicker('refresh');
    }
    $("#_error-carenza-part").hide();
    $("#carenza_modal").modal("show");
}

function carenzaDelete(id, materia, studente) {
    var conf = confirm("Sei sicuro di volere cancellare la carenza di " + materia + " a " + studente + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
            id: id,
            table: 'carenze',
            name: materia + '-' + studente
        },
            function (data, status) {
                carenzeReadRecords();
            }
        );
    }
}

function carenzaValida(id, id_utente, stato) {
    conf = true;

    if (stato == 1) {
        conf = confirm("Confermi che vuoi togliere la validazione a questa carenza?");
    }
    if (conf == true) {
        $.post("../didattica/carenzaValida.php", {
            id: id,
            id_utente: id_utente,
            stato: stato
        },
            function (data, status) {
                carenzeReadRecords();
            }
        );
    }
}

function carenzaSave() {

    if ($("#studente").val() <= 0) {
        $("#_error-carenza").text("Devi selezionare uno studente");
        $("#_error-carenza-part").show();
        return;
    }
    if ($("#classe").val() <= 0) {
        $("#_error-carenza").text("Devi selezionare una classe");
        $("#_error-carenza-part").show();
        return;
    }
    if ($("#materia").val() <= 0) {
        $("#_error-carenza").text("Devi selezionare una materia");
        $("#_error-carenza-part").show();
        return;
    }

    $("#_error-carenza-part").hide();

    $.post("carenzaSave.php", {
        id: $("#hidden_carenza_id").val(),
        studente_id: $("#studente").val(),
        classe_id: $("#classe").val(),
        materia_id: $("#materia").val(),
    }, function (data, status) {
        $("#carenza_modal").modal("hide");
        carenzeReadRecords();
    });

}

function importFile(file) {
    var contenuto = "";
    const reader = new FileReader();
    reader.addEventListener('load', (event) => {
        contenuto = event.target.result;
        $.post("carenzeImport.php", {
            contenuto: contenuto
        },
            function (data, status) {
                $('#result_text').html(data);
                carenzeReadRecords();
                setTimeout(function () { $('#result_text').html(""); }, 5000);
            });
    });
    reader.readAsText(file);
}

function hideTooltip(el) {
    $(el).tooltip('hide');
}

function exportFile() {
    var $docente_filtro_id = 0;
    var $classe_filtro_id = 0;
    var $materia_filtro_id = 0;
    var $studente_filtro_id = 0;
    var $anno_filtro_id = 0;
    const url = "carenzeExport.php"
        + "?id_docente=" + encodeURIComponent($docente_filtro_id)
        + "&id_classe=" + encodeURIComponent($classe_filtro_id)
        + "&id_materia=" + encodeURIComponent($materia_filtro_id)
        + "&id_studente=" + encodeURIComponent($studente_filtro_id)
        + "&id_anno=" + encodeURIComponent($anno_filtro_id);

    // Forza il browser a scaricare il file
    window.open(url, '_blank');
};

$(document).ready(function () {


    carenzeReadRecords();

    $("#docente_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $docente_filtro_id = this.value;
            carenzeReadRecords();
        });

    $("#classe_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $classe_filtro_id = this.value;
            carenzeReadRecords();
        });

    $("#materia_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $materia_filtro_id = this.value;
            carenzeReadRecords();
        });

    $("#studente_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $studente_filtro_id = this.value;
            carenzeReadRecords();
        });

    $("#anno_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $anno_filtro_id = this.value;
            carenzeReadRecords();
        });

    $('#export_btn').on('click', function (e) {
        exportFile();
    });

    $('#file_select_id').change(function (e) {
        importFile(e.target.files[0]);
    });

    $('#docente_filtro').val("0");
    $('#docente_filtro').selectpicker('refresh');
});     
