/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// ================================
// Helper UI: toggle prevede_esami (regole carenze / itinere)
// ================================
function initPrevedeEsamiToggleIfNeeded() {
    var $t = $('#prevede_esami');
    if (!$t.length) return;

    if ($.fn.bootstrapToggle) {
        // se non inizializzato
        if (!$t.parent().hasClass('toggle')) {
            try { $t.bootstrapToggle(); } catch (e) { }
        }
    }
}

function setPrevedeEsamiUI(value, locked) {
    var $t = $('#prevede_esami');
    if (!$t.length) return;

    initPrevedeEsamiToggleIfNeeded();

    $t.prop('checked', !!value);

    if ($.fn.bootstrapToggle) {
        try { $t.bootstrapToggle(!!value ? 'on' : 'off'); } catch (e) { }
    }

    // lock/unlock (qui puoi disabilitare: è ok, è un campo di gestione)
    $t.prop('disabled', !!locked);

    // hint
    if (locked) $('#prevede_esami_forzato_msg').show();
    else $('#prevede_esami_forzato_msg').hide();
}

    function syncPrevedeEsamiByFlags() {
        // carenze: deriva dal filtro elenco (#carenze) e/o dal corso letto
        // itinere: deriva dal toggle in modale
        var carenze = $("#carenze").prop('checked') ? 1 : 0;
        var in_itinere = $('#in_itinere').prop('checked') ? 1 : 0;

        // regola: carenze o itinere => esami obbligatori
        if (carenze === 1 || in_itinere === 1) {
            setPrevedeEsamiUI(1, true);
        } else {
            // sblocco, ma NON cambio il valore se l'utente lo ha impostato
            // (quindi qui non faccio niente sul value, solo unlock)
            setPrevedeEsamiUI($('#prevede_esami').is(':checked') ? 1 : 0, false);
        }
    }

    // ================================
    // Date corso: aggiungi / modifica / salva / cancella
    // ================================
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

    // funzione per aprire il modal e modificare una data esistente
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
                var m2 = raw.match(/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})/);
                if (m2) dt = new Date(m2[1], parseInt(m2[2], 10) - 1, m2[3], m2[4], m2[5]);
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

    // ================================
    // Studenti iscritti: aggiorna / cancella / aggiungi
    // ================================
    function aggiornaTabellaStudenti(corso_id) {
        $.ajax({
            url: "../didattica/corsiReadDetails.php",
            method: "POST",
            dataType: "json",
            data: { corsi_id: corso_id },
            success: function (data) {
                try {
                    if (typeof data === "string") data = JSON.parse(data);
                } catch (e) { }

                var tbody = $('#iscritti_table tbody');
                tbody.empty();

                if (!data || !Array.isArray(data.studenti)) {
                    console.error("corsiReadDetails.php risposta inattesa:", data);
                    tbody.html('<tr><td colspan="3" class="text-center text-danger">Errore caricamento studenti</td></tr>');
                    showToast("Errore caricamento studenti (risposta non valida)", true);
                    return;
                }

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
            },
            error: function (xhr) {
                console.error("Errore AJAX corsiReadDetails.php:", xhr && xhr.responseText ? xhr.responseText : xhr);
                var tbody = $('#iscritti_table tbody');
                tbody.empty().html('<tr><td colspan="3" class="text-center text-danger">Errore comunicazione server</td></tr>');
                showToast("Errore comunicazione nel caricamento studenti", true);
            }
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

    // ================================
    // Dettagli corso + save + duplica + delete
    // ================================
    function corsiGetDetails(corsi_id) {
        $("#hidden_corso_id").val(corsi_id);
        var carenze = $("#carenze").prop('checked');

        if (corsi_id > 0) {
            $.post("../didattica/corsiReadDetails.php", { corsi_id: corsi_id }, function (data, status) {
                var corsi = data;

                $('#in_itinere').prop('checked', corsi.corso.in_itinere == 1).change();

                // ✅ prevede_esami dal backend
                var pe = parseInt(corsi.corso.prevede_esami ?? 0, 10) === 1 ? 1 : 0;

                // regola: carenza o itinere => obbligatorio
                var carenzaCorso = parseInt(corsi.corso.carenza ?? 0, 10) === 1 ? 1 : 0;
                var itinereCorso = parseInt(corsi.corso.in_itinere ?? 0, 10) === 1 ? 1 : 0;

                if (carenzaCorso === 1 || itinereCorso === 1) {
                    setPrevedeEsamiUI(1, true);
                } else {
                    setPrevedeEsamiUI(pe, false);
                }

                $('#titolo').val(corsi.corso.titolo);
                $('#titolo').prop('disabled', carenze);
                $('#materia').selectpicker('val', corsi.corso.materia_id);

                // ✅ docenti multipli: se presenti uso corso_docenti, altrimenti fallback su corso.doc_id
                var ids = [];
                if (corsi.docenti && Array.isArray(corsi.docenti) && corsi.docenti.length > 0) {
                    ids = corsi.docenti.map(x => String(x.id_docente));
                } else {
                    ids = [String(corsi.corso.doc_id)];
                }

                // set multi
                $('#docenti_multi').selectpicker('val', ids);
                $('#docenti_multi').selectpicker('refresh');

                // compat: set anche #docente (principale = primo)
                var mainId = (ids.length > 0) ? ids[0] : String(corsi.corso.doc_id);
                $('#docente').selectpicker('val', mainId);
                $('#docente').selectpicker('refresh');

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
                    var haEsito = parseInt(s.ha_esito ?? 0, 10) === 1;
                    var presente = parseInt(s.presente ?? 0, 10) === 1;
                    var recuperato = parseInt(s.recuperato ?? 0, 10) === 1;
                    var assG = parseInt(s.assenza_giustificata ?? 0, 10) === 1;

                    var secondoFirmato = parseInt(s.secondo_firmato ?? 0, 10) === 1;
                    var secondoCreato = parseInt(s.secondo_tentativo ?? 0, 10) === 1;

                    if (!haEsito && s.voto !== undefined && s.voto !== null && String(s.voto) !== "") {
                        haEsito = true;
                        var vv = parseFloat(s.voto);
                        if (!isNaN(vv)) recuperato = (vv >= 6);
                    }

                    if (haEsito && (!presente || !recuperato) && !secondoFirmato) {
                        var serveRecuperoGiust = (!presente && assG);
                        var serveSecondoTentativo = (presente && !recuperato);
                        var serveSecondoPerAssenzaNonGiust = (!presente && !assG);

                        var serveQualcosa = serveRecuperoGiust || serveSecondoTentativo || serveSecondoPerAssenzaNonGiust;

                        if (serveQualcosa) {
                            if (secondoCreato) {
                                var idCorso2 = parseInt(s.id_corso_secondo || 0, 10) || 0;
                                var titoloCorso2 = (s.titolo_corso_secondo || "").toString();
                                var recAss = parseInt(s.recupero_assenza || 0, 10) === 1;

                                var tip = "Iscritto a: " + (idCorso2 > 0 ? ("ID " + idCorso2) : "ID non disponibile");
                                if (titoloCorso2) tip += " — " + titoloCorso2;
                                tip += recAss ? " (RECUPERO ASSENZA)" : " (2ª SESSIONE)";

                                var btnApri = $('<button>')
                                    .attr('type', 'button')
                                    .addClass('btn btn-sm btn-success ml-1')
                                    .attr('title', tip)
                                    .attr('data-toggle', 'tooltip')
                                    .html('<span class="glyphicon glyphicon-share-alt"></span>')
                                    .on('click', function (e) {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        if (idCorso2 > 0) corsiGetDetails(idCorso2);
                                        else showToast("Manca id_corso_secondo nel backend (corsiReadDetails.php)", true);
                                    });

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

                                tdBtn.append(' ').append(btnApri).append(' ').append(btnAnnulla);
                            }
                            else {
                                var isRecuperoAssenza = serveRecuperoGiust ? 1 : 0;

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
                                        iscriviSecondoTentativo(
                                            s.stud_id,
                                            s.iscrizione_id,
                                            corsi_id,
                                            { recupero_assenza: isRecuperoAssenza }
                                        );
                                    });

                                tdBtn.append(' ').append(btnSecondoTent);
                            }
                        }
                    }

                    tr.append(tdBtn);
                    tbodyStud.append(tr);
                });

                $('[data-toggle="tooltip"]').tooltip({ container: 'body' });

            }, 'json');
        } else {
            $('#titolo').val(carenze ? "Corso recupero carenze" : "").prop('disabled', carenze);
            $('#materia').val("0").selectpicker('refresh');

            // nuovo corso: docenti vuoti
            $('#docenti_multi').selectpicker('val', []);
            $('#docenti_multi').selectpicker('refresh');

            // compat: reset #docente
            $('#docente').val("0").selectpicker('refresh');
            // nuovo corso: default prevede_esami = NO (ma se carenze/itinere lo forzo)
            setPrevedeEsamiUI(0, false);
            syncPrevedeEsamiByFlags();

            $('#date_table tbody').empty();
            $('#iscritti_table tbody').empty();
        }
        // ogni volta che cambi itinere dentro la modale, aggiorna regola prevede_esami
        $('#in_itinere').off('change.prevede_esami').on('change.prevede_esami', function () {
            // mantiene anche la tua logica date_section (se già presente altrove non rompe)
            syncPrevedeEsamiByFlags();
        });

        $("#_error-corsi-part").hide();
        $("#corsi_modal").modal("show");
    }

    function corsiSave() {
        var docenti_multi = $('#docenti_multi').val() || [];
        if (!docenti_multi || docenti_multi.length === 0) {
            $("#_error-corsi").text("Devi selezionare almeno un docente");
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
        var prevede_esami = $('#prevede_esami').prop('checked') ? 1 : 0;

        // compat: docente principale = primo selezionato
        var docente_principale = docenti_multi[0];

        $.post("corsiSave.php", {
            id: $("#hidden_corso_id").val(),
            docente_id: docente_principale,
            docenti_multi: docenti_multi,
            materia_id: $("#materia").val(),
            titolo: $("#titolo").val(),
            in_itinere: in_itinere,
            carenze: carenze,
            prevede_esami: prevede_esami   // ✅ AGGIUNTO
        }, function (resp) {
            try {
                if (typeof resp === "string") resp = JSON.parse(resp);
            } catch (e) { }

            if (resp && resp.success) {
                $("#corsi_modal").modal("hide");
                corsiReadRecords();
            } else {
                showToast("Errore salvataggio corso: " + (resp && resp.error ? resp.error : ""), true);
            }
        }, 'json').fail(function () {
            showToast("Errore di rete o server (salvataggio corso)", true);
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

    function corsiDuplicaOpen(corsoId) {
        $("#hidden_corso_id").val(corsoId);

        var $dst = $("#duplica_docente");

        var htmlOpt = "";
        if ($("#docenti_multi").length) {
            htmlOpt = $("#docenti_multi").html();
        }
        if (!htmlOpt && $("#docente").length) {
            htmlOpt = $("#docente").html();
        }
        $dst.html(htmlOpt);

        var pre = 0;
        if ($("#docenti_multi").length) {
            var arr = $("#docenti_multi").val() || [];
            if (arr && arr.length > 0) pre = parseInt(arr[0], 10) || 0;
        }
        if (!pre && $("#docente").length) {
            pre = parseInt($("#docente").val(), 10) || 0;
        }

        $dst.selectpicker('val', pre > 0 ? String(pre) : "0");
        $dst.selectpicker('refresh');

        $("#duplica_err").hide().text("");
        $("#duplica_corso_modal").modal("show");
    }

    function corsiDuplicaConfirm() {
        var corsoId = parseInt($("#hidden_corso_id").val(), 10) || 0;
        if (corsoId <= 0) return;

        $.post("../didattica/corsiDuplica.php",
            { corsi_id: corsoId },
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
