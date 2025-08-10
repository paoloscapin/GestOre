/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var soloAttivi = 1;
var classe_filtro_id = 0;

function genitoreReadRecords() {
    $.get("genitoreReadRecords.php?soloAttivi=" + soloAttivi + "&classeFiltroId=" + classe_filtro_id, {}, function (data, status) {
        $(".records_content").html(data);
    });
}

$('#soloAttiviCheckBox').change(function () {
    // this si riferisce al checkbox
    if (this.checked) {
        soloAttivi = 1;
    } else {
        soloAttivi = 0;
    }
    genitoreReadRecords();
});

function genitoreDelete(id, cognome, nome) {
    var conf = confirm("Sei sicuro di volere cancellare il genitore " + cognome + " " + nome + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
            id: id,
            table: 'genitore',
            name: "cognome " + cognome
        },
            function (data, status) {
                genitoreReadRecords();
            }
        );
    }
}

function genitoreImpersona(id, cognome, nome) {
    $.post("agisciComeGenitore.php", {
        genitore_id: id
    }, function (data, status) {
        window.open('/GestOre/genitore/index.php', '_blank');
    });
}

function genitoreSave() {

    attivo = $("#attivo").prop('checked') ? 1 : 0;

    if (attivo == 0 && ($("#hidden_attivo").val() == 1)) {
        var conf = confirm("Sei sicuro di volere disattivare il genitore " + $("#cognome").val() + " " + $("#nome").val() + "?");
        if (conf == false) {
            return;
        }
    }
    if (attivo == 1 && ($("#hidden_attivo").val() == 0)) {
        var conf = confirm("Sei sicuro di volere inserire per quest'anno lo genitore " + $("#cognome").val() + " " + $("#nome").val() + "?");
        if (conf == false) {
            return;
        }
    }

    $("#_error-classe-part").hide();
    $.post("genitoreSave.php", {
        id: $("#hidden_genitore_id").val(),
        cognome: $("#cognome").val(),
        nome: $("#nome").val(),
        email: $("#email").val(),
        id_classe: $("#classe_filtro").val(),
        id_anno: $("#hidden_anno_id").val(),
        attivo: $("#attivo").prop('checked') ? 1 : 0,
        era_attivo: $("#hidden_attivo").val()
    }, function (data, status) {
        $("#genitore_modal").modal("hide");
        genitoreReadRecords();
    });
}

function genitoreGetDetails(genitore_id) {
    $("#hidden_genitore_id").val(genitore_id);

    if (genitore_id > 0) {
        $.post("genitoreReadDetails.php", {
            id: genitore_id
        }, function (data, status) {

            var genitore = JSON.parse(data);
            console.log(genitore);
            $("#cognome").val(genitore.cognome);
            $("#nome").val(genitore.nome);
            $("#email").val(genitore.email.toLowerCase());
            $("#codice_fiscale").val(genitore.codice_fiscale.toUpperCase());
            $("#userId").val(genitore.username);
            $("#attivo").prop('checked', genitore.attivo != 0 && genitore.attivo != null);
            $('#relazioni_table tbody').empty();
            var markup = '';
            genitore.genitori_di.forEach(function (genitoreDi, index) {
                markup += "<tr>" +
                    "<td style=\"text-align: center; vertical-align: middle;\">" + genitoreDi + "</td>" +
                    "<td style=\"text-align: center; vertical-align: middle;\">" + genitore.relazioni[index] + "</td>" +
                    "</tr>";
            });
            $('#relazioni_table > tbody:last-child').append(markup);
        });
    } else {
        $("#cognome").val("");
        $("#nome").val("");
        $("#email").val("");
        $("#codice_fiscale").val("");
        $("#userId").val("");
        $("#attivo").prop('checked', true);
        $('#relazioni_table tbody').empty();
        $('#btn-save').show();
    }

    $("#genitore_modal").modal("show");

    $("#_error-classe-part").hide();
}

function importFile(file) {
    $('#result_text').html("Caricamento in corso... attendere...");
    var contenuto = "";
    const reader = new FileReader();
    reader.addEventListener('load', (event) => {
        contenuto = event.target.result;
        $.post("genitoreImport.php", {
            contenuto: contenuto
        },
            function (data, status) {
                $('#result_text').html(data);
                genitoreReadRecords();
            });
    });
    reader.readAsText(file);
}

$("#classe_filtro").on("changed.bs.select",
    function (e, clickedIndex, newValue, oldValue) {
        classe_filtro_id = this.value;
        genitoreReadRecords();
    });

$(document).ready(function () {
    genitoreReadRecords();

    $('#file_select_id').change(function (e) {
        importFile(e.target.files[0]);
    });

});
