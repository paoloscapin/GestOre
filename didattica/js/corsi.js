/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
// 🔽 Recupero parametro "d" passato nello <script src=...>
var scripts = document.getElementsByTagName('script');
var myScript = scripts[scripts.length - 1];
var url = new URL(myScript.src);
var params = new URLSearchParams(url.search);
var $anni_filtro_id = params.get("a") || "1"; // default 

var $docente_filtro_id = 0;
var $materia_filtro_id = 0;
var $futuri = 0;

$('#futuri').change(function () {
    // this si riferisce al checkbox
    if (this.checked) {
        $futuri = 1;    
    } else {
        $futuri = 0;
    }
    corsiReadRecords();
});

function corsiReadRecords() {
    $.get("corsiReadRecords.php?anni_id=" + $anni_filtro_id + "&docente_id=" + $docente_filtro_id + "&materia_id=" + $materia_filtro_id + "&futuri=" + $futuri, {}, function (data, status) {
        $(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
    });
}

function corsiGetDetails(corsi_id) {
    $("#hidden_corsi_id").val(corsi_id);

    if (corsi_id > 0) {
        $.post("../didattica/corsiReadDetails.php", {
            corsi_id: corsi_id
        }, function (data, status) {
            var corsi = JSON.parse(data);
            $('#classe').selectpicker('val', corsi.corsi_id_classe);
            $('#materia').selectpicker('val', corsi.corsi_id_materia);
            $('#docente').selectpicker('val', corsi.corsi_id_docente);
        });
        corsiReadRecords(corsi_id);
    }
    else {
        $('#classe').val("0");
        $('#classe').selectpicker('refresh');
        $('#materia').val("0");
        $('#materia').selectpicker('refresh');
        $('#docente').val("0");
        $('#docente').selectpicker('refresh');
    }
    $("#_error-corsi-part").hide();
    $("#corsi_modal").modal("show");
}

function corsiDelete(id, materia, docente) {
    var conf = confirm("Sei sicuro di volere cancellare il corso di " + materia + " a " + docente + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
            id: id,
            table: 'corso',
            name: materia + '-' + docente
        },
            function (data, status) {
                corsiReadRecords();
            }
        );
    }
}


function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function mostraOverlay() {
    $('#progressOverlay').show();
}

function nascondiOverlay() {
    $('#progressOverlay').hide();
}

function aggiornaProgressBar() {
    completati++;
    const percentuale = Math.round((completati / totale) * 100);
    $('#progressBar').css('width', percentuale + '%').text(percentuale + '%');

    if (completati === totale) {
        setTimeout(() => {
            nascondiOverlay();
            alert("Tutte le operazioni sono stato concluse correttamente!");
            carenzeReadRecords();
        }, 500);
    }
}

function corsiSave() {

    if ($("#studente").val() <= 0) {
        $("#_error-corsi").text("Devi selezionare uno studente");
        $("#_error-corsi-part").show();
        return;
    }
    if ($("#classe").val() <= 0) {
        $("#_error-corsi").text("Devi selezionare una classe");
        $("#_error-corsi-part").show();
        return;
    }
    if ($("#materia").val() <= 0) {
        $("#_error-corsi").text("Devi selezionare una materia");
        $("#_error-corsi-part").show();
        return;
    }

    $("#_error-corsi-part").hide();

    $.post("corsiSave.php", {
        id: $("#hidden_corsi_id").val(),
        studente_id: $("#studente").val(),
        classe_id: $("#classe").val(),
        materia_id: $("#materia").val()
    }, function (data, status) {
        $("#corsi_modal").modal("hide");
        corsiReadRecords();
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

$(document).ready(function () {

   corsiReadRecords();

    $("#docente_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $docente_filtro_id = this.value;
            corsiReadRecords();
        });

    $("#materia_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $materia_filtro_id = this.value;
            corsiReadRecords();
        });
       
    $("#anni_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $anni_filtro_id = this.value;
            corsiReadRecords();
        });

    $('#file_select_id').change(function (e) {
        importFile(e.target.files[0]);
    });

});     
