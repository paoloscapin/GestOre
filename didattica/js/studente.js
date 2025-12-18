/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var soloAttivi = 1;
var classe_filtro_id = 0;

function studenteReadRecords() {
    $.get("studenteReadRecords.php?soloAttivi=" + soloAttivi + "&classeFiltroId=" + classe_filtro_id, {}, function (data, status) {
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

    if ($("#classe_filtro_stud").val() <= 0) {
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
            id_classe: $("#classe_filtro_stud").val(),
            id_anno: $("#hidden_anno_id").val(),
            codice_fiscale: $("#codice_fiscale").val(),
            userid: $("#userId").val(),
            attivo: $("#attivo").prop('checked') ? 1 : 0,
            esterno: $("#esterno").prop('checked') ? 1 : 0,
            era_attivo: $("#hidden_attivo").val()
        }, function (data, status) {
            $("#studente_modal").modal("hide");
            studenteReadRecords();
        });
    }

    function studenteGetDetails(studente_id,anno_id) {
        $("#hidden_studente_id").val(studente_id);

        if (studente_id > 0) {
            $.post("studenteReadDetails.php", {
                id: studente_id
            }, function (data, status) {

                var studente = JSON.parse(data);

                $("#cognome").val(studente.cognome);
                $("#nome").val(studente.nome);
                $("#email").val(studente.email.toLowerCase());
                $("#codice_fiscale").val(studente.codice_fiscale.toUpperCase());
                $("#userId").val(studente.username);
                $("#classe_filtro_stud").val(studente.id_classe);
                $("#classe_filtro_stud").selectpicker('refresh');
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

                var $btnPassa = $("#btn-passa-genitore");
                // mostra/nasconde bottone
                if (studente.genitori && studente.genitori.length > 0) {
                    $btnPassa.show();
                } else {
                    $btnPassa.hide();
                }
                // Popola selectpicker genitori
                var $sel = $("#genitore_select");
                $sel.empty();

                // opzionale: placeholder
                $sel.append('<option value="">-- Seleziona genitore --</option>');

                if (studente.genitori && studente.genitori.length > 0) {
                    studente.genitori.forEach(function (g) {
                        $sel.append(
                            '<option value="' + g.id + '">' +
                            (g.cognome || '') + ' ' + (g.nome || '') +
                            '</option>'
                        );
                    });
                }
                $sel.val(studente.genitori[0].id);
                // refresh bootstrap-select
                $sel.selectpicker('refresh');

                $("#btn-passa-genitore").off("click").on("click", function () {
                var genitoreId = $("#genitore_select").val(); // se non-multiple => stringa/id
                if (!genitoreId) return;

                // cambia qui con la tua pagina reale:
                window.location.href = "genitore.php?id=" + encodeURIComponent(genitoreId);
                });
            });


        } else {
            $("#cognome").val("");
            $("#nome").val("");
            $("#email").val("");
            $("#classe_filtro_stud").val("0");
            $("#classe_filtro_stud").selectpicker('refresh');
            $("#codice_fiscale").val("");
            $("#userId").val("");
            $("#hidden_anno_id").val(anno_id);
            $("#attivo").prop('checked', true);
            $('#hidden_studente_id').val("-1");
            $('#frequenta_table tbody').empty();
            $("#genitore_select").empty()
            .append('<option value="">-- Seleziona genitore --</option>')
            .selectpicker('refresh');
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

                // Dopo 10 secondi svuota il testo
                setTimeout(function () {
                    $('#result_text').html('');
                }, 10000);
            });
    });
    reader.readAsText(file);
}
    
    $("#classe_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            classe_filtro_id = this.value;
            studenteReadRecords();
        });

$(document).ready(function () {

    studenteReadRecords();

    $('#file_select_id').off('change').on('change', function (e) {
        importFile(e.target.files[0]);
    });

    // IMPORTANTISSIMO: il modal di collegamento deve stare sotto <body>
    $("#collega_genitore_modal").appendTo("body");

    function cleanupBackdrops() {
        // rimuove QUALSIASI backdrop rimasto
        $(".modal-backdrop").remove();
        $("body").removeClass("modal-open").css("padding-right", "");
    }

    // click: collega genitore
    $(document).off("click", "#btn-collega-genitore").on("click", "#btn-collega-genitore", function () {
        var studenteId = parseInt($("#hidden_studente_id").val(), 10);
        if (!studenteId || studenteId <= 0) {
            alert("Seleziona prima uno studente esistente.");
            return;
        }

        // quando studente_modal è veramente chiuso...
        $("#studente_modal").one("hidden.bs.modal", function () {
            cleanupBackdrops();

            $("#collega_genitore_error").hide().find("div").text("");

            // carica genitori attivi
            $.get("genitoreListAttivi.php", {}, function (data) {
                var genitori = JSON.parse(data);
                var $sel = $("#genitore_select_link");
                $sel.empty().append('<option value=""></option>');
                genitori.forEach(function (g) {
                    $sel.append($("<option>", { value: g.id, text: g.cognome + " " + g.nome }));
                });
                $sel.selectpicker("refresh");
            });

            // carica relazioni
            $.get("relazioniList.php", {}, function (data) {
                var rel = JSON.parse(data);
                var $r = $("#relazione_select_link");
                $r.empty().append('<option value=""></option>');
                rel.forEach(function (x) {
                    $r.append($("<option>", { value: x.id, text: x.nome }));
                });
                $r.selectpicker("refresh");
            }).fail(function () {
                var $r = $("#relazione_select_link");
                $r.empty()
                  .append('<option value=""></option>')
                  .append('<option value="1">Padre</option><option value="2">Madre</option><option value="3">Tutore</option>');
                $r.selectpicker("refresh");
            });

            // apri il modal DOPO aver ripulito backdrop
            setTimeout(function () {
                $("#collega_genitore_modal").modal({ backdrop: "static", keyboard: false });
            }, 50);
        });

        // chiudi il modale studente
        $("#studente_modal").modal("hide");
    });

    // conferma collegamento (puoi lasciare il tuo, ma metto delegato)
    $(document).off("click", "#btn-conferma-collega-genitore").on("click", "#btn-conferma-collega-genitore", function () {
        var studenteId = parseInt($("#hidden_studente_id").val(), 10);
        var genitoreId = parseInt($("#genitore_select_link").val(), 10);
        var relazioneId = parseInt($("#relazione_select_link").val(), 10);

        if (!genitoreId) {
            $("#collega_genitore_error").show().find("div").text("Seleziona un genitore.");
            return;
        }
        if (!relazioneId) {
            $("#collega_genitore_error").show().find("div").text("Seleziona una relazione.");
            return;
        }

        $.post("genitoreCollegaStudente.php", {
            id_genitore: genitoreId,
            id_studente: studenteId,
            id_relazione: relazioneId
        }, function (data) {
            var res = JSON.parse(data);
            if (res.error) {
                $("#collega_genitore_error").show().find("div").text(res.error);
                return;
            }

            $("#collega_genitore_modal").modal("hide");

            var annoId = parseInt($("#hidden_anno_id").val(), 10) || 0;
            studenteGetDetails(studenteId, annoId);
        });
        
    });

});
