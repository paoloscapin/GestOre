/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var soloNuovi = 1;
var soloPrenotati = 0;
var categoria_filtro_id = 1; // sportello didattico
var docente_filtro_id = 0;
var studente_filtro_id = 0;
var materia_filtro_id = 0;
var classe_filtro_id = 0;
var bozza_filtro_id = 0;
var selections = [];
var data_pickr = null;

window.toastAfterModalClose = null;

function ensureSelectHasOption($sel, value, label) {
    value = (value || "").trim();
    if (!value) return;
    if ($sel.find("option[value='" + value.replace(/'/g, "\\'") + "']").length === 0) {
        $sel.append($("<option>", { value: value, text: label || value }));
    }
}

function setOraSelect(oraValue) {
    var ora = (oraValue || "").trim();
    if (!ora) return;

    var $ora = $("#ora");
    // se l'ora non è presente nella lista (es: 14:00), la aggiungo
    ensureSelectHasOption($ora, ora, ora + " (attuale)");
    $ora.selectpicker('val', ora).selectpicker('refresh');
}

function verificaAulaCorrenteDidattica(opts) {
    opts = opts || {};
    var includiAulaCorrente = !!opts.includiAulaCorrente;

    var data = getDbDateFromPickrId("#data");
    var ora = ($("#ora").val() || "").trim();
    if (!data || !ora) {
        console.log("[verificaAulaCorrenteDidattica] SKIP: manca data/ora", { data: data, ora: ora });
        return;
    }

    var aulaCorrente = ($("#luogo").val() || "").trim();

    console.log("[verificaAulaCorrenteDidattica] START", {
        includiAulaCorrente: includiAulaCorrente,
        data: data,
        ora: ora,
        aulaCorrente: aulaCorrente
    });

    $.post("../common/checkAuleLibere.php", {
        dataGiorno: data,
        ora: ora,
        tipo: 'TUTTE'
    }, function (resp) {

        console.log("[verificaAulaCorrenteDidattica] RESPONSE raw=", resp);

        if (typeof resp === "string") {
            try { resp = JSON.parse(resp); } catch (e) { resp = null; }
        }

        console.log("[verificaAulaCorrenteDidattica] RESPONSE parsed=", resp);

        var $luogo = $("#luogo");
        $luogo.empty();

        if (!resp || resp.status !== "ok" || !resp.data || !resp.data.length) {
            console.log("[verificaAulaCorrenteDidattica] NO aule libere", {
                includiAulaCorrente: includiAulaCorrente,
                aulaCorrente: aulaCorrente
            });

            if (includiAulaCorrente && aulaCorrente) {
                $luogo.append($("<option>", { value: aulaCorrente, text: aulaCorrente + " (attuale)" }));
                $luogo.val(aulaCorrente);
            } else {
                $luogo.append('<option value="">Nessuna aula disponibile</option>');
                $luogo.val("");
            }

            $luogo.selectpicker('refresh');

            console.log("[verificaAulaCorrenteDidattica] FINAL", {
                options: $("#luogo option").map(function () { return this.value }).get(),
                selected: $("#luogo").val()
            });

            return;
        }

        // aule libere
        resp.data.forEach(function (aula) {
            var label = aula.nroAula;
            if (aula.descrizione) label += " – " + aula.descrizione;
            $luogo.append($("<option>", { value: aula.nroAula, text: label }));
        });

        // se richiesto e l'aula corrente non è tra le libere, aggiungila
        if (includiAulaCorrente && aulaCorrente) {
            ensureSelectHasOption($luogo, aulaCorrente, aulaCorrente + " (attuale)");
            $luogo.val(aulaCorrente);
        } else {
            if (aulaCorrente && $luogo.find("option[value='" + aulaCorrente.replace(/'/g, "\\'") + "']").length) {
                $luogo.val(aulaCorrente);
            } else {
                $luogo.val("");
            }
        }

        $luogo.selectpicker('refresh');

        console.log("[verificaAulaCorrenteDidattica] FINAL", {
            options: $("#luogo option").map(function () { return this.value }).get(),
            selected: $("#luogo").val()
        });

    }, "json").fail(function (xhr, status, err) {
        console.error("[verificaAulaCorrenteDidattica] AJAX FAIL", status, err, xhr && xhr.responseText);
    });
}


function setDbDateToPickr(pickr, data_str) {
    var data = Date.parseExact(data_str, 'yyyy-MM-dd');
    pickr.setDate(data);
}

function getDbDateFromPickrId(pickrId) {
    var data_str = $(pickrId).val();
    var data_date = Date.parseExact(data_str, 'd/M/yyyy');
    return data_date.toString('yyyy-MM-dd');
}

$('#soloNuoviCheckBox').change(function () {
    // this si riferisce al checkbox
    if (this.checked) {
        soloNuovi = 1;
    } else {
        soloNuovi = 0;
    }
    sportelloReadRecords();
});

$('#bozzaCheckBox').change(function () {
    // this si riferisce al checkbox
    if (this.checked) {
        bozza_filtro_id = 1;
    } else {
        bozza_filtro_id = 0;
    }
    sportelloReadRecords();
});

$('#soloPrenotatiCheckBox').change(function () {
    // this si riferisce al checkbox
    if (this.checked) {
        soloPrenotati = 1;
    } else {
        soloPrenotati = 0;
    }
    sportelloReadRecords();
});

function sportelloReadRecords() {
    $.get("sportelloReadRecords.php?ancheCancellati=true&soloNuovi=" + soloNuovi + "&soloPrenotati=" + soloPrenotati + "&categoria_filtro_id=" + categoria_filtro_id + "&docente_filtro_id=" + docente_filtro_id + "&classe_filtro_id=" + classe_filtro_id + "&materia_filtro_id=" + materia_filtro_id + "&bozza_filtro_id=" + bozza_filtro_id, {}, function (data, status) {
        $(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
    });
}

function iscriviStudente(studente_id, selezione) {
    $.post("sportelloIscriviStudente.php", {
        id_studente: studente_id,
        lista_sportelli: selezione
    }, function (data, status) {
        $('#riga_iscrizioni_sportelli').hide();
        $('#result_text').html(data);
        $('#file_select_students').change(function (e) { });
        $("#sportelli_selezionati").html("");
        selections.length = 0;
        setTimeout(function () { $('#result_text').html(""); }, 5000);
        sportelloReadRecords();
    });
}

function sportelloSelect(id) {
    if ($("#selecticon" + id).hasClass("glyphicon-remove")) {
        $("#selecticon" + id).removeClass("glyphicon-remove");
        $("#selecticon" + id).addClass("glyphicon-ok");
        $("#selectbutton" + id).removeClass("btn-info");
        $("#selectbutton" + id).addClass("btn-primary");
        $("#selectbutton" + id).trigger("blur");
        $("#selecticon" + id).trigger("blur");
        selections.push(id);
    }
    else {
        $("#selecticon" + id).addClass("glyphicon-remove");
        $("#selecticon" + id).removeClass("glyphicon-ok");
        $("#selectbutton" + id).removeClass("btn-primary");
        $("#selectbutton" + id).addClass("btn-info");
        $("#selectbutton" + id).trigger("blur");
        $("#selecticon" + id).trigger("blur");
        selections.splice(selections.indexOf(id), 1);
    }
    if (selections.length > 0) {
        $("#numero_studenti").html('NUMERO ATTIVITA SELEZIONATE: ' + selections.length);
        $('#file_select_students').change(function (e) {
            importStudents(e.target.files[0], selections);
        });
        $('#riga_iscrizioni_sportelli').show();

    }
    else {
        $("#numero_studenti").html("");
        $('#file_select_students').change(function (e) { });
        $('#select_studente').on("click", function (e) { });
        $('#riga_iscrizioni_sportelli').hide();
    }
}

function showSpinner(msg = "Operazione in corso…") {
    $("#overlaySpinner div div:last").text(msg);
    $("#overlaySpinner").css("display", "flex");
}

function hideSpinner() {
    $("#overlaySpinner").hide();
}

function sportelloDelete(id, materia) {

    Swal.fire({
        title: 'Eliminare lo sportello?',
        html: `
            <b>${materia}</b><br><br>
            L'operazione è <b>definitiva</b> e rimuove anche
            eventuali prenotazioni su <b>MBApp</b>.
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sì, elimina',
        cancelButtonText: 'Annulla',
        confirmButtonColor: '#d33'
    }).then((result) => {

        if (!result.isConfirmed) return;

        showSpinner("Eliminazione sportello in corso…");

        $.post(
            "sportelloDelete.php",
            { id: id },
            function (res) {
                hideSpinner();

                if (!res || res.ok !== true) {
                    Swal.fire(
                        'Errore',
                        res?.error ?? 'Errore durante la cancellazione',
                        'error'
                    );
                    return;
                }

                Swal.fire(
                    'Eliminato',
                    'Lo sportello è stato eliminato correttamente',
                    'success'
                );

                sportelloReadRecords();
            },
            "json"
        ).fail(function (xhr) {
            hideSpinner();
            Swal.fire(
                'Errore',
                'Errore di comunicazione con il server',
                'error'
            );
            console.error(xhr.responseText);
        });
    });
}

function showSuccessMessage(text, timeout = 3000) {

    let $container = $('#alert-container');

    // se non esiste, lo creo e lo appendo al body
    if ($container.length === 0) {
        $container = $('<div id="alert-container"></div>').appendTo('body');
    }

    // posizione: in basso e centrato
    $container.css({
        position: 'fixed',
        bottom: '40px',
        left: '50%',
        transform: 'translateX(-50%)',
        zIndex: 99999,
        textAlign: 'center'
    });

    const $msg = $('<div></div>').text(text).css({
        backgroundColor: '#198754',      // verde Bootstrap "success"
        color: '#ffffff',
        padding: '14px 22px',
        borderRadius: '10px',
        fontSize: '16px',
        fontWeight: '500',
        boxShadow: '0 8px 24px rgba(0,0,0,0.25)',
        display: 'inline-block',
        minWidth: '320px'
    });

    $container.append($msg);
    $msg.hide().fadeIn(200);

    setTimeout(function () {
        $msg.fadeOut(400, function () { $(this).remove(); });
    }, timeout);
}


function sportelloSave() {
  console.log("[sportelloSave] START");

  // Validazioni base
  if (parseInt($("#materia").val(), 10) <= 0) {
    $("#_error-materia").text("Devi selezionare una materia");
    $("#_error-materia-part").show();
    return;
  }
  if (parseInt($("#categoria").val(), 10) <= 0) {
    $("#_error-materia").text("Devi selezionare una categoria");
    $("#_error-materia-part").show();
    return;
  }
  if (parseInt($("#classe").val(), 10) <= 0) {
    $("#_error-materia").text("Devi selezionare una classe");
    $("#_error-materia-part").show();
    return;
  }
  if (parseInt($("#numero_ore").val(), 10) <= 0) {
    $("#_error-materia").text("Il numero di ore non può essere 0");
    $("#_error-materia-part").show();
    return;
  }
  $("#_error-materia-part").hide();

  // Studenti presenti/cancellazioni
  var studentiDaModificareIdList = [];
  var studentiDaCancellareIdList = [];

  $('#studenti_table tbody tr').each(function () {
    var row = $(this);
    var id = row.children().eq(0).text().trim();

    var presenteCheckbox = row.find('.chk-presenza');
    var presenteOriginal = presenteCheckbox.prop('defaultChecked');
    var presenteCorrente = presenteCheckbox.prop('checked');
    if (presenteCorrente !== presenteOriginal) {
      studentiDaModificareIdList.push(id);
    }

    var cancellaCheckbox = row.find('.chk-cancella');
    if (cancellaCheckbox.prop('checked')) {
      studentiDaCancellareIdList.push(id);
    }
  });

  console.log("[sportelloSave] studenti", {
    mod: studentiDaModificareIdList.length,
    del: studentiDaCancellareIdList.length
  });

  if (studentiDaCancellareIdList.length > 0) {
    var ok = confirm("Stai per cancellare " + studentiDaCancellareIdList.length + " studente/i. Vuoi continuare?");
    if (!ok) return;
  }

  // Payload
  var docenteId = parseInt($("#docente").val(), 10) || 0;
  var luogoTrim = ($("#luogo").val() || "").trim();
  var attivo = (docenteId > 0 && luogoTrim !== "") ? 1 : 0;

  // classe legacy
  var classeTextLegacy = "";
  if ($("#hidden_lista_classi").val() == "testo") {
    classeTextLegacy = ($("#classe").val() || "").trim();
  } else {
    classeTextLegacy = ($("#classe option:selected").text() || "").trim();
  }

  var payload = {
    id: $("#hidden_sportello_id").val(),
    data: getDbDateFromPickrId("#data"),
    ora: $("#ora").val(),
    docente_id: $("#docente").val(),
    materia_id: $("#materia").val(),
    categoria_id: $("#categoria").val(),
    numero_ore: $("#numero_ore").val(),
    argomento: $("#argomento").val(),
    luogo: $("#luogo").val(),
    max_iscrizioni: $("#max_iscrizioni").val(),
    classe: classeTextLegacy,
    classe_id: ($("#hidden_lista_classi").val() == "testo") ? 0 : $("#classe").val(),
    cancellato: $("#cancellato").is(':checked') ? 1 : 0,
    firmato: $("#firmato").is(':checked') ? 1 : 0,
    online: ($("#online").length && $("#online").is(':checked')) ? 1 : 0,
    clil: ($("#clil").length && $("#clil").is(':checked')) ? 1 : 0,
    orientamento: ($("#orientamento").length && $("#orientamento").is(':checked')) ? 1 : 0,
    attivo: attivo,
    studentiDaModificareIdList: JSON.stringify(studentiDaModificareIdList),
    studentiDaCancellareIdList: JSON.stringify(studentiDaCancellareIdList)
  };

  console.log("[sportelloSave] payload", payload);

  // Busy lock (sicuro)
  setSportelloModalBusy(true, "sportelloSave");

  $.post("sportelloSave.php", payload, function (data, status) {
    console.log("[sportelloSave] DONE", { status: status, data: data });

    // chiudi + refresh lista
    window.toastAfterModalClose = "Sportello aggiornato correttamente";
    $('#sportello_modal').modal('hide');
    sportelloReadRecords();

  }, "json")
    .fail(function (xhr, st, err) {
      console.error("[sportelloSave] FAIL", st, err, xhr && xhr.responseText);
      alert("Errore salvataggio sportello (vedi console/log).");
    })
    .always(function () {
      console.log("[sportelloSave] ALWAYS -> unlock modal");
      setSportelloModalBusy(false, "sportelloSave always");
    });

  console.log("[sportelloSave] END (request sent)");
}

function setSportelloModalBusy(isBusy, reason) {
  const $m = $("#sportello_modal");
  $m.data("busy", isBusy ? 1 : 0);

  console.log("[modalBusy]", { isBusy: !!isBusy, reason: reason || "" });

  // Disabilita solo i campi del form + save, NON i pulsanti di chiusura
  const $toDisable = $m.find("input, textarea, select, button")
    .not('[data-dismiss="modal"]')
    .not('.close')
    .not('.btn-close');

  $toDisable.prop("disabled", !!isBusy);

  // bootstrap-select refresh
  $m.find("select.selectpicker").each(function () {
    const $s = $(this);
    if ($s.is('[data-dismiss="modal"]')) return;
    $s.prop("disabled", !!isBusy).selectpicker("refresh");
  });
}

function resetSportelloModalEnabledState() {
  const $m = $("#sportello_modal");

  console.log("[resetSportelloModalEnabledState] BEFORE", {
    disabled_inputs: $m.find(":input:disabled").length,
    backdrops: $(".modal-backdrop").length,
    body_modal_open: $("body").hasClass("modal-open")
  });

  $m.data("busy", 0);

  // Riabilita tutto
  $m.find("input, textarea, select, button").prop("disabled", false).removeClass("disabled");
  $m.find("select.selectpicker").each(function () {
    $(this).prop("disabled", false).selectpicker("refresh");
  });

  // Pulisci eventuali backdrops zombie
  const $b = $(".modal-backdrop");
  if ($b.length > 1) $b.not(":last").remove();

  console.log("[resetSportelloModalEnabledState] AFTER", {
    disabled_inputs: $m.find(":input:disabled").length,
    backdrops: $(".modal-backdrop").length,
    body_modal_open: $("body").hasClass("modal-open")
  });
}

// Aggancia una sola volta (mettilo in document.ready)
$(document).off("hidden.bs.modal.sportelloFix", "#sportello_modal");
$(document).on("hidden.bs.modal.sportelloFix", "#sportello_modal", function () {
  console.log("[sportello_modal] hidden.bs.modal -> force reset enable");
  resetSportelloModalEnabledState();
});


function sportelloGetDetails(sportello_id) {
    console.log("[sportelloGetDetails] OPEN sportello_id =", sportello_id);

    // 🔧 FIX CRITICO: resettiamo SEMPRE lo stato del modale prima di usarlo
    try {
        resetSportelloModalEnabledState();
        setSportelloModalBusy(false, "open_reset");
    } catch (e) {
        console.warn("[sportelloGetDetails] reset/busy reset error", e);
    }

    $("#hidden_sportello_id").val(sportello_id);

    if (sportello_id > 0) {

        // (opzionale ma utile) busy mentre carichi
        try { setSportelloModalBusy(true, "loading_details"); } catch (e) { }

        $.post("../docente/sportelloReadDetails.php", { sportello_id: sportello_id }, function (data, status) {

            console.log("[sportelloGetDetails] ReadDetails OK", { status: status, data: data });

            var sportello = data;

            // 🔧 tolgo busy appena ho i dati
            try { setSportelloModalBusy(false, "details_loaded"); } catch (e) { }

            setDbDateToPickr(data_pickr, sportello.sportello_data);
            setOraSelect(sportello.sportello_ora);

            // selectpicker: dopo val, refresh è buona pratica
            $('#docente').selectpicker('val', sportello.docente_id).selectpicker('refresh');
            $('#materia').selectpicker('val', sportello.materia_id).selectpicker('refresh');
            $('#categoria').selectpicker('val', sportello.categoria_id).selectpicker('refresh');

            $("#numero_ore").val(sportello.sportello_numero_ore);
            $("#argomento").val(sportello.sportello_argomento);

            // aula attuale
            var aulaDb = (sportello.sportello_luogo || "").trim();
            if (aulaDb) {
                ensureSelectHasOption($("#luogo"), aulaDb, aulaDb + " (attuale)");
                $("#luogo").val(aulaDb).selectpicker('refresh');
            } else {
                $("#luogo").val("").selectpicker('refresh');
            }

            var isAttivo = (parseInt(sportello.sportello_attivo, 10) === 1);
            console.log("[sportelloGetDetails] isAttivo =", isAttivo, "aulaDb=", aulaDb);

            // aggiorna lista aule (include aula corrente se attivo)
            setTimeout(function () {
                verificaAulaCorrenteDidattica({ includiAulaCorrente: isAttivo });
            }, 150);

            // classe
            $('#classe').selectpicker('val', sportello.classe_id).selectpicker('refresh');

            // altri campi
            $("#max_iscrizioni").val(sportello.sportello_max_iscrizioni);
            $("#cancellato").prop('checked', sportello.sportello_cancellato != 0 && sportello.sportello_cancellato != null);
            $("#firmato").prop('checked', sportello.sportello_firmato != 0 && sportello.sportello_firmato != null);

            if ($('#hidden_sezione_online_clil').val() == 'true') {
                $("#online").prop('checked', sportello.sportello_online != 0 && sportello.sportello_online != null);
                $("#clil").prop('checked', sportello.sportello_clil != 0 && sportello.sportello_clil != null);
                $("#orientamento").prop('checked', sportello.sportello_orientamento != 0 && sportello.sportello_orientamento != null);
            }

            // studenti
            $('#studenti_table tbody').empty();

            var markup = '';
            (sportello.studenti || []).forEach(function (studenti) {
                markup +=
                    "<tr>" +
                    "<td>" + studenti.sportello_studente_id + "</td>" +
                    "<td>" + studenti.sportello_studente_presente + "</td>" +
                    "<td style=\"text-align: left; vertical-align: middle;\">" + studenti.studente_cognome + " " + studenti.studente_nome + "</td>" +
                    "<td style=\"text-align: left; vertical-align: middle;\">" + studenti.sportello_studente_argomento + "</td>" +
                    "<td style=\"text-align: center; vertical-align: middle;\">" +
                    "<input type=\"checkbox\" class=\"chk-presenza\" " +
                    ((studenti.sportello_studente_presente == 0 || studenti.sportello_studente_presente == null) ? "" : " checked") +
                    "></td>" +
                    "<td style=\"text-align: center; vertical-align: middle;\">" +
                    "<input type=\"checkbox\" class=\"chk-cancella\">" +
                    "</td>" +
                    "</tr>";
            });

            $('#studenti_table > tbody:last-child').append(markup);
            $('#studenti_table td:nth-child(1),#studenti_table th:nth-child(1),#studenti_table td:nth-child(2),#studenti_table th:nth-child(2)').hide();

            $("#_error-materia-part").hide();

            console.log("[sportelloGetDetails] SHOW modal with values", {
                data: $("#data").val(),
                ora: $("#ora").val(),
                luogo: $("#luogo").val(),
                disabled_inputs: $("#sportello_modal :input:disabled").length
            });

            $("#sportello_modal").modal("show");

            setTimeout(function () {
                console.log("[sportello_modal] AFTER SHOW", {
                    disabled_inputs: $("#sportello_modal :input:disabled").length,
                    backdrops: $(".modal-backdrop").length,
                    body_modal_open: $("body").hasClass("modal-open")
                });
            }, 100);

        }, "json").fail(function (xhr, st, err) {
            console.error("[sportelloGetDetails] ReadDetails FAIL", st, err, xhr && xhr.responseText);
            try { setSportelloModalBusy(false, "details_fail"); } catch (e) { }
            alert("Errore lettura sportello (vedi console).");
        });

    } else {

        console.log("[sportelloGetDetails] NEW sportello init");

        data_pickr.setDate(Date.today().toString('d/M/yyyy'));
        $("#ora").selectpicker('val', "13:50").selectpicker('refresh');

        $('#docente').val("0").selectpicker('refresh');
        $('#materia').val("0").selectpicker('refresh');
        $('#categoria').val("0").selectpicker('refresh');

        $("#numero_ore").val("0");
        $("#argomento").val("");

        $("#luogo").val("").selectpicker('refresh');
        setTimeout(function () { verificaAulaCorrenteDidattica({ includiAulaCorrente: false }); }, 100);

        $('#classe').val("0").selectpicker('refresh');

        $("#max_iscrizioni").val($("#hidden_max_iscrizioni_default").val());
        $("#cancellato").prop('checked', false);
        $("#firmato").prop('checked', false);

        if ($('#hidden_sezione_online_clil').val() == 'true') {
            // 🔧 FIX: era "#onine" -> "#online"
            $("#online").prop('checked', false);
            $("#clil").prop('checked', false);
            $("#orientamento").prop('checked', false);
        }

        $('#studenti_table tbody').empty();
        $("#_error-materia-part").hide();

        $("#sportello_modal").modal("show");

        setTimeout(function () {
            console.log("[sportello_modal] AFTER SHOW (NEW)", {
                disabled_inputs: $("#sportello_modal :input:disabled").length,
                backdrops: $(".modal-backdrop").length,
                body_modal_open: $("body").hasClass("modal-open")
            });
        }, 100);
    }
}


function importFile(file) {
    if (!file) {
        alert("Nessun file selezionato.");
        return;
    }

    const reader = new FileReader();

    reader.onload = function (event) {
        const contenuto = event.target.result;

        $.post("sportelloImport.php", { contenuto: contenuto })
            .done(function (data) {
                $('#result_text').stop(true, true).html(data).fadeIn();

                // 🔹 Dopo 10 secondi, nascondi il messaggio
                setTimeout(function () {
                    $('#result_text').fadeOut('slow', function () {
                        $(this).html("");
                    });
                }, 10000);

                sportelloReadRecords();
            })
            .fail(function (xhr, status, error) {
                console.error("Errore durante l'import:", error);
                $('#result_text')
                    .stop(true, true)
                    .html("<b style='color:red;'>Errore durante l'importazione del file.</b>")
                    .fadeIn()
                    .delay(10000)
                    .fadeOut('slow', function () {
                        $(this).html("");
                    });
            });
    };

    reader.onerror = function (err) {
        console.error("Errore di lettura file:", err);
        alert("Errore nella lettura del file. Controlla il formato.");
    };

    reader.readAsText(file, "UTF-8");
}

function sportelloDuplica(id) {
    console.log("[sportelloDuplica] START id=", id);

    $.post("sportelloDuplica.php", { sportello_id: id }, function (resp) {
        console.log("[sportelloDuplica] RESPONSE", resp);

        if (resp && resp.ok && resp.new_sportello_id) {

            sportelloReadRecords();

            console.log("[sportelloDuplica] OPEN NEW sportello_id=", resp.new_sportello_id);
            sportelloGetDetails(parseInt(resp.new_sportello_id, 10));

        } else {
            alert((resp && resp.msg) ? resp.msg : "Errore duplicazione.");
        }
    }, "json").fail(function (xhr, st, err) {
        console.error("[sportelloDuplica] FAIL", st, err, xhr && xhr.responseText);
        alert("Errore di rete o server durante la duplicazione.");
    });
}


function importStudents(file, selezione) {
    var contenuto = "";
    const reader = new FileReader();
    reader.addEventListener('load', (event) => {
        contenuto = event.target.result;
        $.post("sportelloStudentiImport.php", {
            contenuto: contenuto,
            lista_sportelli: selezione
        },
            function (data, status) {
                $('#result_text').html(data);
                $('#file_select_students').change(function (e) { });
                $("#sportelli_selezionati").html("");
                selections.length = 0;
                sportelloReadRecords();
                setTimeout(function () { $('#result_text').html(""); }, 5000);
            });
    });
    reader.readAsText(file);
}

$(document).ready(function () {

    // DEBUG: eventi modale + backdrop
    $(document).on('shown.bs.modal', '#sportello_modal', function () {
        console.log("[sportello_modal] SHOWN", {
            disabled_inputs: $("#sportello_modal :input:disabled").length,
            backdrop: $(".modal-backdrop").length,
            body_modal_open: $("body").hasClass("modal-open")
        });
    });

    $(document).on('hidden.bs.modal', '#sportello_modal', function () {
        console.log("[sportello_modal] HIDDEN", {
            backdrop: $(".modal-backdrop").length,
            body_modal_open: $("body").hasClass("modal-open")
        });
    });

    data_pickr = flatpickr("#data", {
        locale: { firstDayOfWeek: 1 },
        dateFormat: 'j/n/Y',
        onChange: function () {
            // se lo sportello è attivo lo capisci solo quando lo carichi;
            // per ora: includi aula corrente SOLO se esiste un valore già settato
            const aulaCorr = ($("#luogo").val() || "").trim();
            verificaAulaCorrenteDidattica({ includiAulaCorrente: (aulaCorr !== "") });
        }
    });

    $(document).on('click', ".bootstrap-select[data-id='luogo'] button", function () {
        const aulaCorr = ($("#luogo").val() || "").trim();
        // includi aula corrente (così vedi sempre quella dello sportello)
        verificaAulaCorrenteDidattica({ includiAulaCorrente: true });
    });


    $('#ora').selectpicker();
    $('#luogo').selectpicker();

    $("#ora").on("change", function () {
        // se lo sportello è attivo lo capisci solo quando lo carichi;
        // per ora: includi aula corrente SOLO se esiste un valore già settato
        const aulaCorr = ($("#luogo").val() || "").trim();
        verificaAulaCorrenteDidattica({ includiAulaCorrente: (aulaCorr !== "") });
    });


    sportelloReadRecords();

    $("#categoria_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            categoria_filtro_id = this.value;
            sportelloReadRecords();
        });

    $("#docente_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            docente_filtro_id = this.value;
            sportelloReadRecords();
        });

    $("#studente_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            studente_filtro_id = this.value;
        });

    $("#materia_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            materia_filtro_id = this.value;
            sportelloReadRecords();
        });

    $("#classe_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            classe_filtro_id = this.value;
            sportelloReadRecords();
        });

    $('#riga_iscrizioni_sportelli').hide();

    $('#file_select_id').change(function (e) {
        const file = e.target.files[0];
        if (!file) return;

        importFile(file);

        // 🔹 resetta l'input per permettere di riselezionare lo stesso file
        e.target.value = '';
    });

    // delegato: funziona anche se #sportelloModal viene ricreato
    $(document).on('hidden.bs.modal', '#sportello_modal', function () {
        if (window.toastAfterModalClose) {
            showSuccessMessage(window.toastAfterModalClose);
            window.toastAfterModalClose = null;
        }
    });


});
