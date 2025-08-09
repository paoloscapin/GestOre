/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var soloAttivi = 1;

function studenteReadRecords() {
    $.get("studenteReadRecords.php?soloAttivi=" + soloAttivi, {}, function (data, status) {
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
    studenteReadRecords();
});

function studenteDelete(id, cognome, nome) {
    var conf = confirm("Sei sicuro di volere cancellare lo studente " + cognome + " " + nome + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
            id: id,
            table: 'studente',
            name: "cognome " + cognome
        },
            function (data, status) {
                studenteReadRecords();
            }
        );
    }
}

function studenteImpersona(id, cognome, nome) {
    $.post("agisciComeStudente.php", {
        studente_id: id
    }, function (data, status) {
        window.open('/GestOre/studente/index.php', '_blank');
    });
}

function studenteSave() {
    if ($("#classe_filtro").val() <= 0) {
        $("#_error-classe").text("Devi selezionare una classe per lo studente.");
        $("#_error-classe-part").show();
        return;
    }

    attivo = $("#attivo").prop('checked') ? 1 : 0;

    if (attivo == 0 && ($("#hidden_attivo").val() == 1)) {
        var conf = confirm("Sei sicuro di volere disattivare lo studente " + $("#cognome").val() + " " + $("#nome").val() + "?");
        if (conf == false) {
            return;
        }
    }
    if (attivo == 1 && ($("#hidden_attivo").val() == 0)) {
        var conf = confirm("Sei sicuro di volere inserire per quest'anno lo studente " + $("#cognome").val() + " " + $("#nome").val() + "?");
        if (conf == false) {
            return;
        }
    }

        $("#_error-classe-part").hide();
        $.post("studenteSave.php", {
            id: $("#hidden_studente_id").val(),
            cognome: $("#cognome").val(),
            nome: $("#nome").val(),
            email: $("#email").val(),
            id_classe: $("#classe_filtro").val(),
            id_anno: $("#hidden_anno_id").val(),
            attivo: $("#attivo").prop('checked') ? 1 : 0,
            era_attivo: $("#hidden_attivo").val()
        }, function (data, status) {
            $("#studente_modal").modal("hide");
            studenteReadRecords();
        });
    }

    function studenteGetDetails(studente_id) {
        $("#hidden_studente_id").val(studente_id);

        if (studente_id > 0) {
            $.post("studenteReadDetails.php", {
                id: studente_id
            }, function (data, status) {

                var studente = JSON.parse(data);

                $("#cognome").val(studente.cognome);
                $("#nome").val(studente.nome);
                $("#email").val(studente.email.toLowerCase());
                $("#classe_filtro").val(studente.id_classe);
                $("#classe_filtro").selectpicker('refresh');
                $('#hidden_anno_id').val(studente.id_anno_scolastico);
                $("#attivo").prop('checked', studente.attivo != 0 && studente.attivo != null);
                $('#hidden_attivo').val(studente.attivo != 0 && studente.attivo != null ? 1 : 0);
                $('#frequenta_table tbody').empty();
                var markup = '';
                studente.frequenze.forEach(function (frequenza) {

                    markup = markup +
                        "<tr>" +
                        "<td style=\"text-align: center; vertical-align: middle;\">" + frequenza.anno + "</td>" +
                        "<td style=\"text-align: center; vertical-align: middle;\">" + frequenza.classe + "</td>" +
                        "</tr>";
                });
                $('#frequenta_table > tbody:last-child').append(markup);
            });
        } else {
            $("#cognome").val("");
            $("#nome").val("");
            $("#email").val("");
            $("#classe_filtro").val("0");
            $("#classe_filtro").selectpicker('refresh');
            $("#hidden_anno_id").val("-1");
            $("#attivo").prop('checked', true);
            $('#hidden_studente_id').val("-1");
            $('#frequenta_table tbody').empty();
            $('#btn-save').show();
        }

        $("#studente_modal").modal("show");

        $("#_error-classe-part").hide();
    }

    function importFile(file) {
        var contenuto = "";
        const reader = new FileReader();
        reader.addEventListener('load', (event) => {
            contenuto = event.target.result;
            $.post("studenteImport.php", {
                contenuto: contenuto
            },
                function (data, status) {
                    $('#result_text').html(data);
                    studenteReadRecords();
                });
        });
        reader.readAsText(file);
    }

    $(document).ready(function () {
        studenteReadRecords();

        $('#file_select_id').change(function (e) {
            importFile(e.target.files[0]);
        });

    });
