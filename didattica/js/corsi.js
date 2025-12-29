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
var $carenze_toggle = 0;
var $in_itinere_toggle = 0;
var $firma_esame = 0; // filtro esame firmato
var $carenza_sessione = 0; // 0=tutte, 1=S1, 2=S2

$('#futuri').change(function () {
    $futuri = this.checked ? 1 : 0;
    corsiReadRecords();
});

$('#carenze').change(function () {
    if (this.checked) {
        $carenze_toggle = 1;
        $("#carenza_sessione_box").show();
        $("#carenza_sessione").prop("disabled", false).selectpicker("refresh");
    } else {
        $carenze_toggle = 0;
        $carenza_sessione = 0;
        $("#carenza_sessione").selectpicker("val", "0");
        $("#carenza_sessione_box").hide();
        $("#carenza_sessione").prop("disabled", true).selectpicker("refresh");
    }
    corsiReadRecords();
});

$("#carenza_sessione").on("changed.bs.select", function () {
    $carenza_sessione = parseInt($(this).val(), 10) || 0;
    corsiReadRecords();
});

$('#esameFirmato').change(function () {
    $firma_esame = this.checked ? 1 : 0;
    corsiReadRecords();
});

$('#filtro_itinere').change(function () {
    $in_itinere_toggle = this.checked ? 1 : 0;
    corsiReadRecords();
});

function corsiReadRecords() {
    $.get(
        "corsiReadRecords.php?anni_id=" + $anni_filtro_id +
        "&docente_id=" + $docente_filtro_id +
        "&materia_id=" + $materia_filtro_id +
        "&futuri=" + $futuri +
        "&carenze=" + $carenze_toggle +
        "&itinere=" + $in_itinere_toggle +
        "&carenza_sessione=" + $carenza_sessione,
        {},
        function (data, status) {
            $(".records_content").html(data);
            $('[data-toggle="tooltip"]').tooltip({ container: 'body' });
        }
    );
}

// funzione per formattare le date
function formatDateTime(dateTimeStr) {
    var d = new Date(dateTimeStr);
    var giorno = String(d.getDate()).padStart(2, '0');
    var mese = String(d.getMonth() + 1).padStart(2, '0');
    var anno = d.getFullYear();
    var ore = String(d.getHours()).padStart(2, '0');
    var minuti = String(d.getMinutes()).padStart(2, '0');
    return giorno + "-" + mese + "-" + anno + " alle ore " + ore + ":" + minuti;
}

function aggiungiDate() {
    $('#hidden_data_id').val(-1);
    $('#mod_aula').val('');
    $('#error-modifica-data').hide();

    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const datetimeLocal = `${year}-${month}-${day}T${hours}:${minutes}`;
    $('#mod_data').val(datetimeLocal);

    $('#modificaDataModal').modal('show');
}

function salvaModificaData() {
    var corso_id = $('#hidden_corso_id').val();
    var data_id = $('#hidden_data_id').val();
    var nuova_data_inizio = $('#mod_data_inizio').val();
    var nuova_data_fine = $('#mod_data_fine').val();
    var nuova_aula = $('#mod_aula').val();

    if (!nuova_data_inizio || !nuova_data_fine || !nuova_aula) {
        $('#error-modifica-data').text("Compila tutti i campi").show();
        return;
    }
    $('#error-modifica-data').hide();

    $.post("corsiAggiornaData.php", {
        data_id: data_id,
        corso_id: corso_id,
        corso_data_inizio: nuova_data_inizio,
        corso_data_fine: nuova_data_fine,
        corso_aula: nuova_aula
    }, function (data, status) {
        if (data && (data.status === 'ok' || data.success === true)) {
            corsiGetDetails(corso_id);
            $('#modificaDataModal').modal('hide');
            showToast('Data aggiornata con successo');
        } else {
            $('#error-modifica-data').text("Errore durante il salvataggio").show();
            showToast('Errore durante il salvataggio', true);
        }
    }, 'json').fail(function () {
        $('#error-modifica-data').text("Errore di comunicazione").show();
        showToast('Errore di comunicazione', true);
    });
}

// ================================
// Apertura modal Registro Lezione
// ================================
function apriRegistroLezione(corso_id) {
    $("#hidden_corso_id").val(corso_id);
    $('#registroLezioneModal #select_data_corso').empty();
    $('#registroLezioneModal #tabellaStudenti tbody').empty();
    $('#registroLezioneModal #argomentiLezione').val('');

    $('#registroLezioneModal').modal('show');

    $.post("../didattica/get_date_corso.php", { corso_id: corso_id }, function (data) {
        if (data.success) {
            var $selectData = $('#select_data_corso');
            if (data.date.length === 0) {
                $selectData.append('<option value="">Nessuna data disponibile</option>').selectpicker('refresh');
                $('#tabellaStudenti tbody').html('<tr><td colspan="4" class="text-center text-danger">Nessuna data disponibile</td></tr>');
            } else {
                data.date.forEach(function (d) {
                    var dt = new Date(d.data_inizio);
                    var giorno = String(dt.getDate()).padStart(2, '0');
                    var mese = String(dt.getMonth() + 1).padStart(2, '0');
                    var anno = dt.getFullYear();
                    var ore = String(dt.getHours()).padStart(2, '0');
                    var minuti = String(dt.getMinutes()).padStart(2, '0');
                    var formatted = `${giorno}-${mese}-${anno} alle ore ${ore}:${minuti}`;
                    $selectData.append('<option value="' + d.id + '">' + formatted + ' - Aula: ' + d.aula + '</option>');
                });
                $selectData.selectpicker('refresh');

                var firstDataId = data.date[0].id;
                $selectData.val(firstDataId).selectpicker('refresh');
                caricaStudentiEArgomenti(firstDataId);
            }
        } else {
            showToast('Errore nel caricamento delle date del corso', true);
        }
    }, 'json');
}

// ================================
// Carica studenti e argomenti
// ================================
function caricaStudentiEArgomenti(data_id) {
    if (!data_id) return;

    $('#tabellaStudenti tbody').empty();
    $('#argomentiLezione').val('');
    $('#lezioneFirmata').prop('checked', false);

    $.post("corsoGetStudentiArgomenti.php", { data_id: data_id }, function (data) {
        if (data.success) {
            if (data.studenti.length === 0) {
                $('#tabellaStudenti tbody').html('<tr><td colspan="4" class="text-center">Nessuno studente iscritto</td></tr>');
            } else {
                data.studenti.forEach(function (s) {
                    var checkedPresente = s.presente ? 'checked' : '';
                    $('#tabellaStudenti tbody').append(
                        '<tr>' +
                        '<td>' + s.nominativo + '</td>' +
                        '<td>' + s.classe + '</td>' +
                        '<td class="text-center"><input type="checkbox" class="chkPresente" data-id="' + s.id + '" ' + checkedPresente + '></td>' +
                        '</tr>'
                    );
                });
            }

            if (data.argomento) $('#argomentiLezione').val(data.argomento);
            if (data.firmato !== undefined) $('#lezioneFirmata').prop('checked', data.firmato == 1);
        } else {
            showToast('Errore nel caricamento studenti/argomenti', true);
        }
    }, 'json');
}

function showToast(message, isError = false, duration = 3000) {
    var toast = $('#toastMessage');
    toast.css('background', isError ? '#dc3545' : '#28a745');
    toast.text(message).fadeIn(400).delay(duration).fadeOut(400);
}

// ================================
// Salva registro lezioni
// ================================
function salvaRegistroLezione() {
    var corso_id = $('#hidden_corso_id').val();
    var data_id = $('#select_data_corso').val();
    var argomenti = $('#argomentiLezione').val();
    var firmato = $('#lezioneFirmata').is(':checked') ? 1 : 0;

    var presenze = [];
    $('#tabellaStudenti tbody tr').each(function () {
        var id_studente = $(this).find('.chkPresente').data('id');
        var presente = $(this).find('.chkPresente').is(':checked') ? 1 : 0;
        presenze.push({ id_studente: id_studente, presente: presente });
    });

    $.post("corsiSalvaRegistroLezione.php", {
        data_id: data_id,
        corso_id: corso_id,
        argomenti: argomenti,
        presenze: presenze,
        firmato: firmato
    }, function (data) {
        if (data.success) {
            showToast('Registro salvato con successo');
            $('#registroLezioneModal').modal('hide');
            corsiReadRecords();
        } else {
            showToast('Errore nel salvataggio: ' + (data.error || ''), true);
        }
    }, 'json');
}

$('#select_data_corso').on('change', function () {
    var data_id = $(this).val();
    caricaStudentiEArgomenti(data_id);
});

function corsiGetDetails(corsi_id) {
    $("#hidden_corso_id").val(corsi_id);
    carenze = $("#carenze").prop('checked');

    if (corsi_id > 0) {
        $.post("../didattica/corsiReadDetails.php", { corsi_id: corsi_id }, function (data, status) {
            var corsi = data;

            $('#in_itinere').prop('checked', corsi.corso.in_itinere == 1).change();

            $('#titolo').val(corsi.corso.titolo);
            $('#titolo').prop('disabled', carenze);
            $('#materia').selectpicker('val', corsi.corso.materia_id);
            $('#docente').selectpicker('val', corsi.corso.doc_id);

            var tbodyDate = $('#date_table tbody');
            var tbodyStud = $('#iscritti_table tbody');
            tbodyDate.empty();
            tbodyStud.empty();

            corsi.date.forEach(function (d) {
                var tr = $('<tr>').attr('id', 'row_' + d.data_id);
                tr.append($('<td>').css({ textAlign: 'center' }).text(formatDateTime(d.corso_data_inizio)));
                tr.append($('<td>').css({ textAlign: 'center' }).text(formatDateTime(d.corso_data_fine)));
                tr.append($('<td>').css({ textAlign: 'center' }).text(d.corso_aula));

                var tdBtn = $('<td>').css({ textAlign: 'center' });
                var btnMod = $('<button>')
                    .attr('type', 'button')
                    .addClass('btn btn-sm btn-warning')
                    .html('<span class="glyphicon glyphicon-pencil"></span>')
                    .on('click', function () { modificaData(d.data_id, corsi_id); });

                var btnDel = $('<button>')
                    .attr('type', 'button')
                    .addClass('btn btn-sm btn-danger')
                    .html('<span class="glyphicon glyphicon-trash"></span>')
                    .on('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        cancellaData(d.data_id, corsi_id);
                    });

                tdBtn.append(btnMod).append(' ').append(btnDel);
                tr.append(tdBtn);
                tbodyDate.append(tr);
            });

            corsi.studenti.forEach(function (s) {
                var tr = $('<tr id="row_stud_' + s.iscrizione_id + '">');

                tr.append($('<td>').css({ textAlign: 'center', verticalAlign: 'middle' })
                    .text(s.stud_cognome + " " + s.stud_nome));
                tr.append($('<td>').css({ textAlign: 'center', verticalAlign: 'middle' })
                    .text(s.classe));

                var tdBtn = $('<td>').css({ textAlign: 'center', verticalAlign: 'middle' });

                var btnDelStud = $('<button>')
                    .attr('type', 'button')
                    .addClass('btn btn-sm btn-danger')
                    .html('<span class="glyphicon glyphicon-trash"></span>')
                    .on('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        cancellaIscritto(s.iscrizione_id);
                    });
                tdBtn.append(btnDelStud);

                // ============================
                // Pulsanti recupero/2ª sessione
                // ============================
                if (parseInt(s.ha_esito, 10) === 1 && (parseInt(s.presente, 10) === 0 || parseInt(s.recuperato, 10) === 0)) {

                    var haEsito = parseInt(s.ha_esito, 10) === 1;
                    var presente = parseInt(s.presente, 10) === 1;
                    var recuperato = parseInt(s.recuperato, 10) === 1;
                    var assG = parseInt(s.assenza_giustificata || 0, 10) === 1;

                    var secondoFirmato = parseInt(s.secondo_firmato, 10) === 1;
                    var secondoCreato = parseInt(s.secondo_tentativo, 10) === 1;

                    // Caso A) assenza GIUSTIFICATA: recupero (per lo studente è il primo svolgimento)
                    var serveRecuperoGiust = haEsito && !presente && assG;

                    // Caso B) presente ma NON ha recuperato: secondo tentativo "normale"
                    var serveSecondoTentativo = haEsito && presente && !recuperato;

                    // Caso C) assente NON giustificato: lo tratto come 2ª sessione (come prima)
                    var serveSecondoPerAssenzaNonGiust = haEsito && !presente && !assG;

                    var serveQualcosa = serveRecuperoGiust || serveSecondoTentativo || serveSecondoPerAssenzaNonGiust;

                    if (serveQualcosa && !secondoFirmato) {

                        if (secondoCreato) {
                            var btnAnnulla = $('<button>')
                                .attr('type', 'button')
                                .addClass('btn btn-sm btn-danger ml-1')
                                .attr('title', 'Cancella iscrizione')
                                .attr('data-toggle', 'tooltip')
                                .html('<span class="glyphicon glyphicon-remove-circle"></span>')
                                .on('click', function (e) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    cancellaSecondoTentativo(s.stud_id, s.iscrizione_id, corsi_id);
                                });
                            tdBtn.append(' ').append(btnAnnulla);

                        } else {
                            var isRecuperoAssenza = !!serveRecuperoGiust;

                            var titoloBtn = isRecuperoAssenza
                                ? 'Recupero (assenza giustificata)'
                                : 'Iscrivi al secondo tentativo';

                            var icona = isRecuperoAssenza
                                ? 'glyphicon-calendar'
                                : 'glyphicon-repeat';

                            var btnSecondoTent = $('<button>')
                                .attr('type', 'button')
                                .addClass('btn btn-sm btn-info ml-1')
                                .attr('title', titoloBtn)
                                .attr('data-toggle', 'tooltip')
                                .html('<span class="glyphicon ' + icona + '"></span>')
                                .on('click', function (e) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    // ✅ QUI PASSO IL FLAG AL MODALE/POST (recupero_assenza=1)
                                    iscriviSecondoTentativo(s.stud_id, s.iscrizione_id, corsi_id, { recupero_assenza: isRecuperoAssenza ? 1 : 0 });
                                });
                            tdBtn.append(' ').append(btnSecondoTent);
                        }
                    }
                }

                tr.append(tdBtn);
                tbodyStud.append(tr);
            });

            $('[data-toggle="tooltip"]').tooltip();

        }, 'json');
    } else {
        $('#titolo').val(carenze ? "Corso recupero carenze" : "").prop('disabled', carenze);
        $('#materia').val("0").selectpicker('refresh');
        $('#docente').val("0").selectpicker('refresh');
        $('#date_table tbody').empty();
        $('#iscritti_table tbody').empty();
    }

    $("#_error-corsi-part").hide();
    $("#corsi_modal").modal("show");
}

function corsiDuplicaOpen(corsoId) {
    $("#hidden_corso_id").val(corsoId);

    var $src = $("#docente");
    var $dst = $("#duplica_docente");
    $dst.html($src.html());

    $dst.selectpicker('val', $src.val() || "0");
    $dst.selectpicker('refresh');

    $("#duplica_err").hide().text("");
    $("#duplica_corso_modal").modal("show");
}

function corsiDuplicaConfirm() {
    var corsoId = parseInt($("#hidden_corso_id").val(), 10) || 0;
    var newDocId = parseInt($("#duplica_docente").val(), 10) || 0;

    if (corsoId <= 0) return;
    if (newDocId <= 0) {
        $("#duplica_err").show().text("Seleziona un docente valido.");
        return;
    }

    $.post("../didattica/corsiDuplica.php",
        { corsi_id: corsoId, new_doc_id: newDocId },
        function (resp) {
            if (resp && resp.ok && resp.new_corso_id) {
                $("#duplica_corso_modal").modal("hide");
                corsiGetDetails(parseInt(resp.new_corso_id, 10));
            } else {
                $("#duplica_err").show().text(resp && resp.msg ? resp.msg : "Errore duplicazione.");
            }
        },
        "json"
    ).fail(function () {
        $("#duplica_err").show().text("Errore di rete o server.");
    });
}

function cancellaSecondoTentativo(id_studente, iscrizione_id, id_corso) {
    bootbox.confirm({
        title: "Conferma",
        message: "Vuoi rimuovere lo studente dal secondo tentativo?",
        buttons: {
            cancel: { label: "Annulla", className: "btn-default" },
            confirm: { label: "Conferma", className: "btn-danger" }
        },
        callback: function (result) {
            if (result) {
                $.post("../didattica/corsiCancellaSecondoTentativo.php",
                    { id_corso: id_corso, id_studente: id_studente },
                    function (data) {
                        if (data.success) {
                            showToast("Studente rimosso dal secondo tentativo");
                            corsiGetDetails(id_corso);
                        } else {
                            showToast("Errore: " + data.error, true);
                        }
                    }, "json"
                );
            }
        }
    });
}

/**
 * ✅ aggiornato:
 * - accetta opts.recupero_assenza
 * - cambia testi UI
 * - passa recupero_assenza al PHP (corsiIscriviSecondoTentativo.php)
 *
 * NOTA: per cambiare davvero il titolo del corso creato, devi gestire recupero_assenza anche nel PHP.
 */
function iscriviSecondoTentativo(stud_id, iscrizione_id, corso_id, opts) {
    opts = opts || {};
    var recuperoAssenza = (parseInt(opts.recupero_assenza, 10) === 1) ? 1 : 0;

    $.getJSON("../didattica/corsiSecondaSessioneList.php", {
        id_corso: corso_id,
        recupero_assenza: recuperoAssenza
    }, function (resp) {

        if (!resp || !resp.success) {
            showToast("Errore caricamento corsi", true);
            return;
        }

        var labelCorsi = recuperoAssenza ? "Usa un corso di recupero assenza esistente" : "Usa un corso 2ª sessione esistente";
        var labelNew = recuperoAssenza ? "Oppure crea un nuovo corso di recupero assenza scegliendo il docente" : "Oppure crea un nuovo corso 2ª sessione scegliendo il docente";
        var titleDlg = recuperoAssenza ? "Recupero assenza giustificata: scegli corso o crea" : "2ª sessione: scegli corso o crea";

        var optCorsi = '<option value="0">-- Seleziona corso --</option>';
        (resp.corsi || []).forEach(function (c) {
            optCorsi += `<option value="${c.id}">${c.cognome} ${c.nome} - ${c.titolo} (ID:${c.id})</option>`;
        });

        var docOptions = $("#docente").html();

        var html = `
          <div class="form-group">
            <label>${labelCorsi}</label>
            <select id="choose_corso2" class="form-control">${optCorsi}</select>
          </div>
          <hr>
          <div class="form-group">
            <label>${labelNew}</label>
            <select id="choose_newdoc" class="form-control">${docOptions}</select>
            <small class="text-muted">Il corso sarà creato per la stessa materia/anno del corso di partenza.</small>
          </div>
        `;

        function closeCorso1AndOpenCorso2(idCorso2) {
            idCorso2 = parseInt(idCorso2, 10) || 0;

            bootbox.hideAll();

            // listener "one-shot" per fare sequenza pulita
            $("#corsi_modal")
                .off("hidden.bs.modal.openCorso2")
                .on("hidden.bs.modal.openCorso2", function () {
                    $("#corsi_modal").off("hidden.bs.modal.openCorso2");

                    // refresh elenco nella pagina (records_content)
                    corsiReadRecords();

                    // poi apro dettaglio corso2
                    if (idCorso2 > 0) {
                        corsiGetDetails(idCorso2);
                    }
                })
                .modal("hide");
        }

        bootbox.dialog({
            title: titleDlg,
            message: html,
            buttons: {
                cancel: { label: "Annulla", className: "btn-default" },

                attach: {
                    label: "Aggancia a corso esistente",
                    className: "btn-info",
                    callback: function () {
                        var id_corso2 = parseInt($("#choose_corso2").val(), 10) || 0;
                        if (id_corso2 <= 0) {
                            showToast("Seleziona un corso valido", true);
                            return false;
                        }

                        $.post("../didattica/corsiIscriviSecondoTentativo.php", {
                            id_corso: corso_id,
                            id_studente: stud_id,
                            id_corso_secondo: id_corso2,
                            recupero_assenza: recuperoAssenza // ✅ IMPORTANTISSIMO
                        }, function (data) {
                            if (data && data.success) {
                                showToast(recuperoAssenza ? "Studente assegnato al recupero assenza" : "Studente assegnato alla 2ª sessione");
                                closeCorso1AndOpenCorso2(data.id_corso_secondo || id_corso2);
                            } else {
                                showToast("Errore: " + (data && data.error ? data.error : ""), true);
                            }
                        }, "json");

                        return false; // non chiudere automaticamente il bootbox
                    }
                },

                create: {
                    label: "Crea nuovo corso + aggancia",
                    className: "btn-primary",
                    callback: function () {
                        var newDoc = parseInt($("#choose_newdoc").val(), 10) || 0;
                        if (newDoc <= 0) {
                            showToast("Seleziona un docente valido", true);
                            return false;
                        }

                        $.post("../didattica/corsiIscriviSecondoTentativo.php", {
                            id_corso: corso_id,
                            id_studente: stud_id,
                            new_docente_id: newDoc,
                            recupero_assenza: recuperoAssenza // ✅ IMPORTANTISSIMO
                        }, function (data) {
                            if (data && data.success) {
                                showToast(recuperoAssenza ? "Creato/agganciato recupero assenza" : "Creato/agganciato corso 2ª sessione");
                                closeCorso1AndOpenCorso2(data.id_corso_secondo);
                            } else {
                                showToast("Errore: " + (data && data.error ? data.error : ""), true);
                            }
                        }, "json");

                        return false;
                    }
                }
            }
        });
    });
}



function corsiDelete(id, materia, docente, nstudenti, stato) {
    var conf = confirm("Sei sicuro di volere cancellare il corso di " + materia + " a " + docente + " ?");
    if (!conf) return;

    if (nstudenti > 0) {
        var conf2 = confirm("Ci sono studenti iscritti al corso! Cancellare comunque il corso?");
        if (!conf2) return;
    }

    $.post("../didattica/corsoCancella.php", { id: id }, function (data, status) {
        corsiReadRecords();
    });
}

function aggiornaTabellaDate(corso_id) {
    $.post("../didattica/corsiReadDetails.php", { corsi_id: corso_id }, function (data) {
        var tbody = $('#date_table tbody');
        tbody.empty();

        data.date.forEach(function (d) {
            var tr = $('<tr>');
            tr.append($('<td>').text(formatDateTime(d.corso_data_inizio)));
            tr.append($('<td>').text(formatDateTime(d.corso_data_fine)));
            tr.append($('<td>').text(d.corso_aula));

            var tdBtn = $('<td>');

            var btnMod = $('<button>')
                .addClass('btn btn-sm btn-warning')
                .html('<span class="glyphicon glyphicon-pencil"></span>')
                .on('click', function () { modificaData(d.data_id, corso_id); });

            var btnDel = $('<button>')
                .addClass('btn btn-sm btn-danger')
                .html('<span class="glyphicon glyphicon-trash"></span>')
                .on('click', function () { cancellaData(d.data_id, corso_id); });

            tdBtn.append(btnMod).append(' ').append(btnDel);
            tr.append(tdBtn);
            tbody.append(tr);
        });
    });
}

function cancellaData(id, corso_id) {
    var conf = window.confirm("Sei sicuro di volere cancellare questa data?");
    if (conf) {
        $.post("../didattica/corsoCancellaData.php", { id: id }, function (data) {
            $('#row_' + id).remove();
        }, 'json');
    }
}

function aggiornaTabellaStudenti(corso_id) {
    $.post("../didattica/corsiReadDetails.php", { corsi_id: corso_id }, function (data) {
        var tbody = $('#iscritti_table tbody');
        tbody.empty();

        data.studenti.forEach(function (s) {
            var tr = $('<tr>').attr('id', 'row_stud_' + s.iscrizione_id);

            tr.append($('<td>').text(s.stud_cognome + " " + s.stud_nome));
            tr.append($('<td>').text(s.classe));

            var tdBtn = $('<td>');

            var btnDel = $('<button>')
                .attr('type', 'button')
                .addClass('btn btn-sm btn-danger')
                .html('<span class="glyphicon glyphicon-trash"></span>')
                .on('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    cancellaIscritto(s.iscrizione_id);
                });

            tdBtn.append(btnDel);
            tr.append(tdBtn);
            tbody.append(tr);
        });
    }, 'json');
}

// -------------------------
// funzione per aprire il modal e modificare una data esistente
// -------------------------
function modificaData(data_id, corso_id) {
    if (!data_id || !corso_id) {
        showToast("Parametri mancanti per modifica data", true);
        return;
    }

    $('#hidden_corso_id').val(corso_id);

    $.post("../didattica/get_date_corso.php", { corso_id: corso_id }, function (resp) {
        if (!resp || !resp.success) {
            showToast("Errore nel recupero delle date del corso", true);
            return;
        }

        var found = resp.date.find(function (d) {
            return String(d.id) === String(data_id) || String(d.id) === String(data_id);
        });

        if (!found) {
            showToast("Data non trovata", true);
            return;
        }

        $('#hidden_data_id').val(found.id);

        var raw = String(found.data_inizio || found.corso_data_inizio || found.data_corrente || "");
        var iso = raw.replace(' ', 'T').replace(/\.\d+$/, '');
        var dt = new Date(iso);

        if (isNaN(dt.getTime())) {
            var m = raw.match(/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})/);
            if (m) dt = new Date(m[1], parseInt(m[2], 10) - 1, m[3], m[4], m[5]);
        }

        var year = dt.getFullYear();
        var month = String(dt.getMonth() + 1).padStart(2, '0');
        var day = String(dt.getDate()).padStart(2, '0');
        var hours = String(dt.getHours()).padStart(2, '0');
        var minutes = String(dt.getMinutes()).padStart(2, '0');
        var datetimeLocal = year + '-' + month + '-' + day + 'T' + hours + ':' + minutes;

        $('#mod_data_inizio').val(datetimeLocal);

        raw = String(found.data_fine || found.corso_data_fine || found.data_corrente || "");
        iso = raw.replace(' ', 'T').replace(/\.\d+$/, '');
        dt = new Date(iso);

        if (isNaN(dt.getTime())) {
            var m = raw.match(/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})/);
            if (m) dt = new Date(m[1], parseInt(m[2], 10) - 1, m[3], m[4], m[5]);
        }

        year = dt.getFullYear();
        month = String(dt.getMonth() + 1).padStart(2, '0');
        day = String(dt.getDate()).padStart(2, '0');
        hours = String(dt.getHours()).padStart(2, '0');
        minutes = String(dt.getMinutes()).padStart(2, '0');
        datetimeLocal = year + '-' + month + '-' + day + 'T' + hours + ':' + minutes;

        $('#mod_data_fine').val(datetimeLocal);
        $('#mod_aula').val(found.aula || found.corso_aula || '');

        $('#error-modifica-data').hide();
        $('#modificaDataModal').modal('show');
    }, 'json').fail(function () {
        showToast("Errore di comunicazione durante il caricamento data", true);
    });
}

function cancellaIscritto(iscrizione_id) {
    var corsi_id = $("#hidden_corso_id").val();
    if (!window.confirm("Sei sicuro di volere cancellare questo studente?")) return;

    $.post("../didattica/corsoCancellaIscritto.php", { id: iscrizione_id, corso_id: corsi_id }, function (data) {
        $('#row_stud_' + iscrizione_id).remove();
    }, 'json');
    corsiGetDetails(corsi_id);
}

function corsiSave() {

    if ($("#docente").val() <= 0) {
        $("#_error-corsi").text("Devi selezionare un docente");
        $("#_error-corsi-part").show();
        return;
    }
    if ($("#materia").val() <= 0) {
        $("#_error-corsi").text("Devi selezionare una materia");
        $("#_error-corsi-part").show();
        return;
    }
    if ($("#titolo").val() == "") {
        $("#_error-corsi").text("Devi selezionare un titolo");
        $("#_error-corsi-part").show();
        return;
    }
    $("#_error-corsi-part").hide();

    var carenze = $("#carenze").prop('checked');
    var in_itinere = $('#in_itinere').prop('checked') ? 1 : 0;

    $.post("corsiSave.php", {
        id: $("#hidden_corso_id").val(),
        docente_id: $("#docente").val(),
        materia_id: $("#materia").val(),
        titolo: $("#titolo").val(),
        in_itinere: in_itinere,
        carenze: carenze
    }, function (data, status) {
        $("#corsi_modal").modal("hide");
        corsiReadRecords();
    });
}

var studentiDisponibili = [];

function iscriviStudenti() {
    var carenze = $("#carenze").prop('checked');
    var corso_id = $("#hidden_corso_id").val();
    $('#container_studenti').empty();
    $('#error-aggiungi-studenti').hide();

    $.getJSON('../didattica/elencoStudentiDisponibili.php', { corso_id: corso_id, carenze: carenze }, function (data) {
        studentiDisponibili = data.stud;
        studentiDisponibili = studentiDisponibili.filter((v, i, a) => a.findIndex(t => t.studente_id === v.studente_id) === i);
        aggiungiSelectStudente();
    });

    $('#aggiungiStudentiModal').modal('show');
}

function aggiungiSelectStudente() {
    var container = $('#container_studenti');
    var select = $('<select>').addClass('form-control mb-2').css({ maxWidth: '250px', margin: '0 auto' });
    select.append('<option value="">-- Seleziona uno studente --</option>');

    studentiDisponibili.forEach(function (s) {
        select.append('<option value="' + s.studente_id + '">' + s.cognome + ' ' + s.nome + ' (' + s.classe + ')</option>');
    });

    select.on('change', function () {
        var val = $(this).val();
        if (val) {
            studentiDisponibili = studentiDisponibili.filter(s => s.studente_id != val);
            if ($('#container_studenti select').last().val() != '') {
                aggiungiSelectStudente();
            }
        }
    });

    container.append(select);
}

function salvaNuoviStudenti() {
    var corso_id = $('#hidden_corso_id').val();
    var studenti_id = [];

    $('#container_studenti select').each(function () {
        let val = $(this).val();
        if (val && val != "" && val != "0") {
            studenti_id.push(val);
        }
    });

    if (studenti_id.length == 0) {
        showToast("Seleziona almeno uno studente", true);
        return;
    }

    $.post('../didattica/corsoAggiungiStudenti.php',
        { id_corso: corso_id, id_studente: studenti_id },
        function (data) {
            if (data.status === 'ok') {
                aggiornaTabellaStudenti(corso_id);
                $('#aggiungiStudentiModal').modal('hide');

                let msg = data.message;
                if (data.added.length > 0 && data.already.length > 0) {
                    msg = "Aggiunti: " + data.added.length + ", già presenti: " + data.already.length;
                }
                showToast(msg, false);
            } else {
                showToast(data.message || "Errore durante l'aggiunta", true);
            }
        }, 'json'
    );
}

function importFile(file) {
    var contenuto = "";
    const reader = new FileReader();
    reader.addEventListener('load', (event) => {
        contenuto = event.target.result;
        $.post("corsiImport.php", { contenuto: contenuto },
            function (data, status) {
                $('#result_text').html(data);
                corsiReadRecords();
                setTimeout(function () { $('#result_text').html(""); }, 5000);
            });
    });
    reader.readAsText(file);
}

function hideTooltip(el) {
    $(el).tooltip('hide');
}

// ================================
// Apertura modal Esame (NUOVO)
// ================================
function apriEsameModal(corso_id) {
    $("#hidden_corso_id").val(corso_id);

    // reset campi
    $('#hidden_esame_data_id').val('');
    $('#tabellaEsameStudenti tbody').empty();
    $('#argomentiEsame').val('');
    $('#esame_inizio_data').val('');
    $('#esame_inizio_ora').val('');
    $('#esame_fine_data').val('');
    $('#esame_fine_ora').val('');
    $('#esame_aula').val('');
    $('#select_tentativo').empty();
    $('#esameFirmato').prop('checked', false);

    // helper: label tentativo (E1, E2...) + stato
    function buildTentativoLabel(esame) {
        const t = parseInt(esame.tentativo, 10) || 1;

        let stato = '';
        if (parseInt(esame.firmato, 10) === 1) stato = 'FIR';
        else if (esame.data_inizio_esame) stato = 'PRG';
        else stato = 'BOZ';

        let label = `E${t}: ${stato}`;

        if (esame.data_inizio_esame) {
            const dt = new Date(String(esame.data_inizio_esame).replace(' ', 'T'));
            if (!isNaN(dt.getTime())) {
                label += ` (${dt.toLocaleDateString()})`;
            }
        }
        return label;
    }

    $('#esameModal').modal('show');

    $.post("../didattica/corsoEsamiReadDetails.php", { corso_id: corso_id }, function (data) {
        if (!data || !data.success) {
            showToast("Errore nel caricamento dati esame", true);
            return;
        }

        if (!data.esami || !Array.isArray(data.esami) || data.esami.length === 0) {
            $('#select_tentativo').html('<option value="0">Nessun esame programmato</option>');
            $('#tabellaEsameStudenti tbody').html(
                '<tr><td colspan="8" class="text-center text-danger">Nessun esame programmato</td></tr>'
            );
            return;
        }

        // Popola il select tentativo
        data.esami.forEach(function (esame) {
            const label = buildTentativoLabel(esame);
            $('#select_tentativo').append(
                `<option value="${esame.tentativo}" data-id="${esame.id}">${label}</option>`
            );
        });

        // Preseleziono il primo tentativo disponibile
        const first = data.esami[0];
        $('#select_tentativo').val(first.tentativo);
        $('#hidden_esame_data_id').val(first.id);

        // carico dati
        caricaDatiTentativo(data, first.tentativo);

        // Cambio tentativo
        $('#select_tentativo').off('change').on('change', function () {
            const t = parseInt($(this).val(), 10) || 1;
            const selected = data.esami.find(e => parseInt(e.tentativo, 10) === t);
            if (!selected) return;

            $('#hidden_esame_data_id').val(selected.id);
            caricaDatiTentativo(data, t);
        });

    }, 'json').fail(function () {
        showToast("Errore di comunicazione col server", true);
    });
}

// ================================
// Helper HTML escape (per note assenza)
// ================================
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/**
 * IMPORTANTISSIMO (richiesta): NON disabilitare mai nulla.
 * Qui facciamo solo "pulizia" dei campi incoerenti.
 */
function syncAssenzaUI($tr) {
    var presente = $tr.find(".chk-presente").is(":checked");

    var $chkG = $tr.find(".chk-assenza-giust");
    var $note = $tr.find(".input-assenza-note");

    var $tipo = $tr.find(".tipo-prova");
    var $voto = $tr.find(".voto-studente");
    var $rec = $tr.find(".recuperata");

    if (presente) {
        $chkG.prop("checked", false);
        $note.val("");
        var vv = parseFloat($voto.val());
        if (!isNaN(vv)) $rec.text(vv >= 6 ? "✅" : "❌");
        else $rec.text("-");
    } else {
        $tipo.val("");
        $voto.val("");
        $rec.text("-");
        if (!$chkG.is(":checked")) $note.val("");
    }
}

// ================================
// Carica i dati di un tentativo (con assenza giustificata + motivo)
// ================================
function caricaDatiTentativo(data, tentativo) {
    $('#tabellaEsameStudenti tbody').empty();
    $('#argomentiEsame').val('');
    $('#esame_inizio_data').val('');
    $('#esame_inizio_ora').val('');
    $('#esame_fine_data').val('');
    $('#esame_fine_ora').val('');
    $('#esame_aula').val('');
    $('#esameFirmato').prop('checked', false);

    // ====== DATI ESAME ======
    let esame = (data.esami || []).find(e => String(e.tentativo) === String(tentativo));
    if (esame) {
        if (esame.data_inizio_esame) {
            let parts = esame.data_inizio_esame.split(/[- :]/);
            let dt = new Date(parts[0], parts[1] - 1, parts[2], parts[3], parts[4], parts[5] || 0);
            if (!isNaN(dt.getTime())) {
                $('#esame_inizio_data').val(
                    dt.getFullYear() + '-' + String(dt.getMonth() + 1).padStart(2, '0') + '-' + String(dt.getDate()).padStart(2, '0')
                );
                $('#esame_inizio_ora').val(
                    String(dt.getHours()).padStart(2, '0') + ':' + String(dt.getMinutes()).padStart(2, '0')
                );
            }
        }
        if (esame.data_fine_esame) {
            let parts = esame.data_fine_esame.split(/[- :]/);
            let dt = new Date(parts[0], parts[1] - 1, parts[2], parts[3], parts[4], parts[5] || 0);
            if (!isNaN(dt.getTime())) {
                $('#esame_fine_data').val(
                    dt.getFullYear() + '-' + String(dt.getMonth() + 1).padStart(2, '0') + '-' + String(dt.getDate()).padStart(2, '0')
                );
                $('#esame_fine_ora').val(
                    String(dt.getHours()).padStart(2, '0') + ':' + String(dt.getMinutes()).padStart(2, '0')
                );
            }
        }
        if (esame.aula) $('#esame_aula').val(esame.aula);
        $('#esameFirmato').prop('checked', esame.firmato == 1);
    }

    let primoConArg = (data.studenti || []).find(s =>
        String(s.tentativo) === String(tentativo) &&
        s.argomenti && String(s.argomenti).trim() !== ""
    );
    if (primoConArg) $('#argomentiEsame').val(primoConArg.argomenti);

    let studenti = (data.studenti || []).filter(s => String(s.tentativo) === String(tentativo));

    if (studenti.length === 0) {
        $('#tabellaEsameStudenti tbody').html(
            '<tr><td colspan="8" class="text-center text-danger">Nessuno studente iscritto</td></tr>'
        );
        return;
    }

    studenti.forEach(function (s) {
        var presenteVal = parseInt(s.presente || 0, 10);
        var assG = parseInt(s.assenza_giustificata || 0, 10);
        var assNote = (s.assenza_note || "");
        var tipo = (s.tipo_prova || "");

        if (presenteVal === 1) {
            assG = 0;
            assNote = "";
        }

        let votoVal = (s.voto !== null && s.voto !== undefined) ? s.voto : '';

        let row = `
            <tr>
                <td>${s.cognome} ${s.nome}</td>
                <td>${s.classe}</td>

                <td class="text-center">
                    <input type="checkbox" class="chk-presente" data-id="${s.stud_id}" ${presenteVal === 1 ? 'checked' : ''}>
                </td>

                <td class="text-center">
                    <input type="checkbox" class="chk-assenza-giust" data-id="${s.stud_id}" ${assG === 1 ? 'checked' : ''}>
                </td>

                <td>
                    <input type="text" class="form-control input-assenza-note" data-id="${s.stud_id}"
                           value="${escapeHtml(assNote)}"
                           placeholder="Es. certificato medico">
                </td>

                <td>
                    <select class="form-control tipo-prova" data-id="${s.stud_id}">
                        <option value="" ${tipo === '' ? 'selected' : ''}>—</option>
                        <option value="scritto" ${tipo === 'scritto' ? 'selected' : ''}>Scritto</option>
                        <option value="orale"   ${tipo === 'orale' ? 'selected' : ''}>Orale</option>
                        <option value="pratico" ${tipo === 'pratico' ? 'selected' : ''}>Pratico</option>
                    </select>
                </td>

                <td>
                    <input type="number" class="form-control voto-studente" data-id="${s.stud_id}"
                           min="0" max="10" step="0.5" value="${votoVal}">
                </td>

                <td class="recuperata text-center">
                    ${(parseFloat(s.voto) >= 6) ? '✅' : (s.voto ? '❌' : '-')}
                </td>
            </tr>
        `;

        $('#tabellaEsameStudenti tbody').append(row);
        syncAssenzaUI($('#tabellaEsameStudenti tbody tr').last());
    });

    $("#tabellaEsameStudenti")
        .off("change", ".chk-presente")
        .on("change", ".chk-presente", function () {
            syncAssenzaUI($(this).closest("tr"));
        });

    $("#tabellaEsameStudenti")
        .off("change", ".chk-assenza-giust")
        .on("change", ".chk-assenza-giust", function () {
            syncAssenzaUI($(this).closest("tr"));
        });

    $("#tabellaEsameStudenti")
        .off("input", ".voto-studente")
        .on("input", ".voto-studente", function () {
            let voto = parseFloat($(this).val());
            let cell = $(this).closest("tr").find(".recuperata");
            if (!isNaN(voto)) cell.text(voto >= 6 ? "✅" : "❌");
            else cell.text("-");
        });
}

// Alias per compatibilità se il bottone chiama "apriEsame(...)"
function apriEsame(corso_id) {
    return apriEsameModal(corso_id);
}

// ================================
// Salvataggio Esame (NUOVO)
// ================================
function salvaEsame() {
    var corso_id = parseInt($("#hidden_corso_id").val(), 10) || 0;

    var id_esame_data_raw = $("#hidden_esame_data_id").val();
    var id_esame_data = parseInt(id_esame_data_raw, 10);
    if (isNaN(id_esame_data) || id_esame_data <= 0) id_esame_data = -1;

    var argomenti = $('#argomentiEsame').val().trim();
    var firmato = $('#esameFirmato').is(':checked') ? 1 : 0;

    if (corso_id <= 0) {
        showToast("Corso non valido", true);
        return;
    }

    var studenti = [];
    $('#tabellaEsameStudenti tbody tr').each(function () {
        let id = $(this).find('.chk-presente').data('id');
        id = parseInt(id, 10);
        if (!id) return;

        let presente = $(this).find('.chk-presente').is(':checked') ? 1 : 0;

        let assenza_giustificata = $(this).find('.chk-assenza-giust').is(':checked') ? 1 : 0;
        let assenza_note = $(this).find('.input-assenza-note').val();

        if (presente === 1) {
            assenza_giustificata = 0;
            assenza_note = null;
        } else {
            if (assenza_giustificata !== 1) {
                assenza_note = null;
            } else {
                assenza_note = (assenza_note && assenza_note.trim() !== "") ? assenza_note.trim() : null;
            }
        }

        let tipo = $(this).find('.tipo-prova').val();
        if (tipo === "") tipo = null;

        let votoRaw = $(this).find('.voto-studente').val();
        let voto = (votoRaw !== '' && votoRaw !== null && votoRaw !== undefined) ? votoRaw : null;

        if (presente === 0) {
            tipo = null;
            voto = null;
        }

        studenti.push({
            id_studente: id,
            presente: presente,
            assenza_giustificata: assenza_giustificata,
            assenza_note: assenza_note,
            tipo: tipo,
            voto: voto
        });
    });

    var data_inizio_esame = $('#esame_inizio_data').val();
    var ora_inizio_esame = $('#esame_inizio_ora').val();
    var data_fine_esame = $('#esame_fine_data').val();
    var ora_fine_esame = $('#esame_fine_ora').val();
    var aula_esame = $('#esame_aula').val().trim();

    var datetime_inizio_esame = null;
    var datetime_fine_esame = null;

    if (data_inizio_esame && ora_inizio_esame) datetime_inizio_esame = data_inizio_esame + " " + ora_inizio_esame + ":00";
    if (data_fine_esame && ora_fine_esame) datetime_fine_esame = data_fine_esame + " " + ora_fine_esame + ":00";

    if (!data_inizio_esame || !ora_inizio_esame) {
        showToast("Inserisci data e ora di inizio dell'esame", true);
        return;
    }
    if (!data_fine_esame || !ora_fine_esame) {
        showToast("Inserisci data e ora di fine dell'esame", true);
        return;
    }
    if (!aula_esame) {
        showToast("Inserisci l'aula dell'esame", true);
        return;
    }

    if (datetime_inizio_esame && datetime_fine_esame) {
        var di = new Date(datetime_inizio_esame.replace(' ', 'T'));
        var df = new Date(datetime_fine_esame.replace(' ', 'T'));
        if (!isNaN(di.getTime()) && !isNaN(df.getTime()) && df < di) {
            showToast("La data/ora di fine non può essere precedente all'inizio", true);
            return;
        }
    }

    if (firmato === 1 && !argomenti) {
        showToast("Inserisci gli argomenti della prova prima di firmare", true);
        return;
    }

    let bad = studenti.find(x =>
        x.presente === 0 &&
        x.assenza_giustificata === 1 &&
        (!x.assenza_note || String(x.assenza_note).trim() === '')
    );
    if (bad) {
        showToast("Per le assenze giustificate inserisci anche il motivo", true);
        return;
    }

    $.post("../didattica/corsoEsamiSave.php", {
        corso_id: corso_id,
        id_esame_data: id_esame_data,
        argomenti: argomenti,
        data_inizio: datetime_inizio_esame,
        data_fine: datetime_fine_esame,
        aula: aula_esame,
        firmato: firmato,
        studenti: studenti
    }, function (data) {
        if (data && data.success) {
            showToast('Esame salvato con successo');
            $('#esameModal').modal('hide');
            corsiReadRecords();
        } else {
            showToast('Errore nel salvataggio esame: ' + (data && data.error ? data.error : ''), true);
        }
    }, 'json').fail(function () {
        showToast("Errore di comunicazione col server", true);
    });
}

$(document).ready(function () {
    corsiReadRecords();

    $("#btn-invia-esiti").on("click", function () {
        showToast("Invio in corso...", false, 10000);
        $.post("inviaEsitiCoordinatori.php", {}, function (res) {
            let risposta = JSON.parse(res);
            if (risposta.success) showToast("Invio completato!");
            else showToast("Errore: " + risposta.msg);
        }).fail(function () {
            showToast("Errore nella comunicazione col server");
        });
    });

    $('#in_itinere').change(function () {
        if ($(this).prop('checked')) $('#date_section').hide();
        else $('#date_section').show();
    });

    $("#incompleti").on("click", function (e) {
        e.preventDefault();
        window.location.href = "corsiIncompleti.php";
    });

    $("#export_btn").on("click", function (e) {
        e.preventDefault();
        window.location.href = "exportEsami.php";
    });

    $("#docente_filtro").on("changed.bs.select", function () {
        $docente_filtro_id = this.value;
        corsiReadRecords();
    });

    $("#materia_filtro").on("changed.bs.select", function () {
        $materia_filtro_id = this.value;
        corsiReadRecords();
    });

    $("#anni_filtro").on("changed.bs.select", function () {
        $anni_filtro_id = this.value;
        corsiReadRecords();
    });

    $('#file_select_id').change(function (e) {
        importFile(e.target.files[0]);
    });

    // quando chiudi il modale dettagli corso, aggiorna l'elenco corsi
    $("#corsi_modal").on("hidden.bs.modal", function () {
        corsiReadRecords();
    });

    // init filtro sessione carenze (all'avvio)
    $("#carenza_sessione").selectpicker();
    $("#carenza_sessione_box").hide();
    $("#carenza_sessione").prop("disabled", true).selectpicker("refresh");
});
