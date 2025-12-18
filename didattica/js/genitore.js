/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var soloAttivi = 1;
var classe_filtro_id = 0;
var anche_senza_figli = 1; // Variabile per gestire la visualizzazione dei genitori senza figli

function genitoreReadRecords(done) {
    $.get(
        "genitoreReadRecords.php?soloAttivi=" + soloAttivi +
        "&classeFiltroId=" + classe_filtro_id +
        "&ancheSenzaStudenti=" + anche_senza_figli,
        {},
        function (data, status) {
            $(".records_content").html(data);
            if (typeof done === "function") done();
        }
    );
}

function getQueryParam(name) {
    var params = new URLSearchParams(window.location.search);
    return params.get(name);
}

$('#ancheSenzaStudentiCheckBox').change(function () {
    // this si riferisce al checkbox
    if (this.checked) {
        anche_senza_figli = 1;
    } else {
        anche_senza_figli = 0;
    }
    genitoreReadRecords();
});

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
    var conf = confirm("Sei sicuro di volere cancellare il genitore " + cognome + " " + nome + " ? In alternativa, puoi disattivarlo.");
    if (conf == true) {
        $.post("genitoreDelete.php", {
            id: id
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
        var conf = confirm("Sei sicuro di volere attivare il genitore " + $("#cognome").val() + " " + $("#nome").val() + "?");
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
        codice_fiscale: $("#codice_fiscale").val(),
        userId: $("#userId").val(),
        attivo: $("#attivo").prop('checked') ? 1 : 0,
        era_attivo: $("#hidden_attivo").val()
    }, function (data, status) {
        $("#genitore_modal").modal("hide");
        genitoreReadRecords();
    });
}

function genitoreGetDetails(genitore_id) {
    $("#hidden_genitore_id").val(genitore_id);
    $("#_error-classe-part").hide();
    $("#hidden_attivo").val(0);
    if (genitore_id > 0) {
        $.post("genitoreReadDetails.php", {
            id: genitore_id
        }, function (data, status) {

            var genitore = JSON.parse(data);
            console.log(genitore);
            $("#cognome").val(genitore.cognome);
            $("#nome").val(genitore.nome);
            $("#email").val((genitore.email || "").toLowerCase());
            $("#codice_fiscale").val((genitore.codice_fiscale || "").toUpperCase());
            $("#userId").val(genitore.username);
            $("#attivo").prop('checked', genitore.attivo != 0 && genitore.attivo != null);
            $("#hidden_attivo").val(genitore.attivo);
            $('#relazioni_table tbody').empty();
            var markup = '';
            genitore.genitori_di.forEach(function (genitoreDi, index) {
                markup += "<tr>" +
                    "<td style=\"text-align: center; vertical-align: middle;\">" + genitoreDi + "</td>" +
                    "<td style=\"text-align: center; vertical-align: middle;\">" + genitore.relazioni[index] + "</td>" +
                    "</tr>";
            });
            $('#relazioni_table > tbody:last-child').append(markup);
            $("#genitore_modal").modal("show");
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
        $("#genitore_modal").modal("show");
    }
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

                // Dopo 10 secondi cancella il contenuto
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
        genitoreReadRecords();
    });

$(document).ready(function () {
    // 1) Carica elenco e (se c'è ?id=) apri subito il dettaglio
    var idFromUrl = getQueryParam('id');
    var openId = (idFromUrl && parseInt(idFromUrl, 10) > 0) ? parseInt(idFromUrl, 10) : null;

    genitoreReadRecords(function () {
        if (openId) {
            genitoreGetDetails(openId);
            history.replaceState(null, '', 'genitore.php'); // pulisci URL
        }
    });

    // 2) Import
    $('#file_select_id').off('change').on('change', function (e) {
        importFile(e.target.files[0]);
    });

    // 3) Apri modal "Collega studente"
    $("#btn-collega-studente").off("click").on("click", function () {
        var genitoreId = parseInt($("#hidden_genitore_id").val(), 10);

        if (!genitoreId || genitoreId <= 0) {
            alert("Salva prima il genitore, poi puoi collegare uno studente.");
            return;
        }

        $("#collega_error").hide().find("div").text("");

        // studenti attivi
        $.get("studenteListAttivi.php", {}, function (data) {
            var studenti = JSON.parse(data);
            var $sel = $("#studente_select");
            $sel.empty().append('<option value=""></option>');

            studenti.forEach(function (s) {
                $sel.append($("<option>", {
                    value: s.id,
                    text: s.cognome + " " + s.nome + (s.classe ? " (" + s.classe + ")" : "")
                }));
            });

            $sel.selectpicker("refresh");
        });

        // relazioni
        $.get("relazioniList.php", {}, function (data) {
            var rel = JSON.parse(data);
            var $r = $("#relazione_select");
            $r.empty().append('<option value=""></option>');

            rel.forEach(function (x) {
                $r.append($("<option>", { value: x.id, text: x.nome }));
            });

            $r.selectpicker("refresh");
        }).fail(function () {
            var $r = $("#relazione_select");
            $r.empty()
              .append('<option value=""></option>')
              .append('<option value="1">Padre</option><option value="2">Madre</option><option value="3">Tutore</option>');
            $r.selectpicker("refresh");
        });

        $("#collega_studente_modal").modal("show");
    });

    // 4) Conferma collegamento (INSERT in genitori_studenti)
    $("#btn-conferma-collega").off("click").on("click", function () {
        var genitoreId = parseInt($("#hidden_genitore_id").val(), 10);
        var studenteId = parseInt($("#studente_select").val(), 10);
        var relazioneId = parseInt($("#relazione_select").val(), 10);

        if (!studenteId) {
            $("#collega_error").show().find("div").text("Seleziona uno studente.");
            return;
        }
        if (!relazioneId) {
            $("#collega_error").show().find("div").text("Seleziona una relazione.");
            return;
        }

        $.post("genitoreCollegaStudente.php", {
            id_genitore: genitoreId,
            id_studente: studenteId,
            id_relazione: relazioneId
        }, function (data) {
            var res = JSON.parse(data);
            if (res.error) {
                $("#collega_error").show().find("div").text(res.error);
                return;
            }

            $("#collega_studente_modal").modal("hide");
            genitoreGetDetails(genitoreId); // aggiorna tabella "Studenti collegati"
        });
    });
});

