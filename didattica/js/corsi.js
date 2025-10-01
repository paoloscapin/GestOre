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
var $carenze_toggle = 1;
var $in_itinere_toggle = 0;
var $firma_esame = 0; // filtro esame firmato

$('#futuri').change(function () {
    // this si riferisce al checkbox
    if (this.checked) {
        $futuri = 1;
    } else {
        $futuri = 0;
    }
    corsiReadRecords();
});

$('#esameFirmato').change(function () {
    // this si riferisce al checkbox
    if (this.checked) {
        $firma_esame = 1;
    } else {
        $firma_esame = 0;
    }
    corsiReadRecords();
});

$('#filtro_itinere').change(function () {
    // this si riferisce al checkbox
    if (this.checked) {
        $in_itinere_toggle = 1;
    } else {
        $in_itinere_toggle = 0;
    }
    corsiReadRecords();
});

$('#carenze').change(function () {
    // this si riferisce al checkbox
    if (this.checked) {
        $carenze_toggle = 1;
    } else {
        $carenze_toggle = 0;
    }
    corsiReadRecords();
});

function corsiReadRecords() {
    $.get("corsiReadRecords.php?anni_id=" + $anni_filtro_id + "&docente_id=" + $docente_filtro_id + "&materia_id=" + $materia_filtro_id + "&futuri=" + $futuri + "&carenze=" + $carenze_toggle + "&itinere=" + $in_itinere_toggle, {}, function (data, status) {
        $(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
    });
}

// funzione per formattare le date
function formatDateTime(dateTimeStr) {
    // creo oggetto Date a partire dalla stringa
    var d = new Date(dateTimeStr);
    // estraggo giorno, mese, anno, ora, minuti
    var giorno = String(d.getDate()).padStart(2, '0');
    var mese = String(d.getMonth() + 1).padStart(2, '0'); // mesi partono da 0
    var anno = d.getFullYear();
    var ore = String(d.getHours()).padStart(2, '0');
    var minuti = String(d.getMinutes()).padStart(2, '0');
    return giorno + "-" + mese + "-" + anno + " alle ore " + ore + ":" + minuti;
}

function aggiungiDate() {
    // Reset dei campi del modale
    $('#hidden_data_id').val(-1);
    $('#mod_aula').val('');
    $('#error-modifica-data').hide();
    // Imposta data e ora corrente
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const datetimeLocal = `${year}-${month}-${day}T${hours}:${minutes}`;
    $('#mod_data').val(datetimeLocal);
    // Apri il modale
    $('#modificaDataModal').modal('show');
}

function salvaModificaData() {
    var corso_id = $('#hidden_corso_id').val();
    var data_id = $('#hidden_data_id').val();
    var nuova_data = $('#mod_data').val();
    var nuova_aula = $('#mod_aula').val();
    if (!nuova_data || !nuova_aula) {
        $('#error-modifica-data').text("Compila tutti i campi").show();
        return;
    }
    $('#error-modifica-data').hide();
    // invia dati via AJAX
    $.post("corsiAggiornaData.php", {
        data_id: data_id,
        corso_id: corso_id,
        corso_data: nuova_data,
        corso_aula: nuova_aula
    }, function (data, status) {
        // verifica risposta JSON correttamente
        if (data && (data.status === 'ok' || data.success === true)) {
            corsiGetDetails(corso_id); // ricarica i dettagli del corso
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
    // svuota il contenuto precedente
    $('#registroLezioneModal #select_data_corso').empty();
    $('#registroLezioneModal #tabellaStudenti tbody').empty();
    $('#registroLezioneModal #argomentiLezione').val('');

    // Mostra il modal
    $('#registroLezioneModal').modal('show');

    // Carica le date del corso
    $.post("../didattica/get_date_corso.php", { corso_id: corso_id }, function (data) {
        if (data.success) {
            var $selectData = $('#select_data_corso');
            if (data.date.length === 0) {
                $selectData.append('<option value="">Nessuna data disponibile</option>').selectpicker('refresh');
                $('#tabellaStudenti tbody').html('<tr><td colspan="4" class="text-center text-danger">Nessuna data disponibile</td></tr>');
            } else {
                data.date.forEach(function (d) {
                    var dt = new Date(d.data);
                    var giorno = String(dt.getDate()).padStart(2, '0');
                    var mese = String(dt.getMonth() + 1).padStart(2, '0');
                    var anno = dt.getFullYear();
                    var ore = String(dt.getHours()).padStart(2, '0');
                    var minuti = String(dt.getMinutes()).padStart(2, '0');
                    var formatted = `${giorno}-${mese}-${anno} alle ore ${ore}:${minuti}`;
                    $selectData.append('<option value="' + d.id + '">' + formatted + ' - Aula: ' + d.aula + '</option>');
                });
                $selectData.selectpicker('refresh');

                // carica studenti e argomenti per la prima data
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

    // svuota contenuti precedenti
    $('#tabellaStudenti tbody').empty();
    $('#argomentiLezione').val('');
    $('#lezioneFirmata').prop('checked', false); // reset checkbox firmato

    $.post("corsoGetStudentiArgomenti.php", { data_id: data_id }, function (data) {
        if (data.success) {
            // Popola studenti
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

            // Popola argomenti
            if (data.argomento) {
                $('#argomentiLezione').val(data.argomento);
            }
            // Imposta checkbox FIRMATO se presente nei dati
            if (data.firmato !== undefined) {
                $('#lezioneFirmata').prop('checked', data.firmato == 1);
            }
        } else {
            showToast('Errore nel caricamento studenti/argomenti', true);
        }
    }, 'json');
}

function showToast(message, isError = false, duration = 3000) {
    var toast = $('#toastMessage');
    toast.css('background', isError ? '#dc3545' : '#28a745'); // rosso se errore, verde se ok
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
    // raccolta presenze
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
            corsiReadRecords(); // aggiorna tabella corsi
        } else {
            showToast('Errore nel salvataggio: ' + (data.error || ''), true);
        }
    }, 'json');
}

// ================================
// Evento cambio data (registro lezione)
// ================================
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
            if (corsi.corso.in_itinere == 1) {
                $('#in_itinere').prop('checked', true).change();
            } else {
                $('#in_itinere').prop('checked', false).change();
            }
            // Aggiorna campi del corso
            $('#titolo').val(corsi.corso.titolo);
            if (carenze) {
                $('#titolo').prop('disabled', true);
            }
            else {
                $('#titolo').prop('disabled', false);
            }

            $('#materia').selectpicker('val', corsi.corso.materia_id);
            $('#docente').selectpicker('val', corsi.corso.doc_id);

            // Pulizia tabelle
            var tbodyDate = $('#date_table tbody');
            var tbodyStud = $('#iscritti_table tbody');
            tbodyDate.empty();
            tbodyStud.empty();

            // Tabella date
            corsi.date.forEach(function (d) {
                var tr = $('<tr>').attr('id', 'row_' + d.data_id);

                tr.append($('<td>').css({ textAlign: 'center', verticalAlign: 'middle' })
                    .text(formatDateTime(d.corso_data)));
                tr.append($('<td>').css({ textAlign: 'center', verticalAlign: 'middle' })
                    .text(d.corso_aula));

                var tdBtn = $('<td>').css({ textAlign: 'center', verticalAlign: 'middle' });

                // Bottone Modifica
                var btnMod = $('<button>')
                    .attr('type', 'button')
                    .addClass('btn btn-sm btn-warning')
                    .html('<span class="glyphicon glyphicon-pencil"></span>')
                    .on('click', function () { modificaData(d.data_id, corsi_id); });

                // Bottone Elimina
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

            // Tabella studenti
            corsi.studenti.forEach(function (s) {
                var tr = $('<tr row_stud_>' + s.iscrizione_id);

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
                tr.append(tdBtn);
                tbodyStud.append(tr);
            });

        }, 'json'); // importante specificare json
    }
    else {
        // Reset campi se corso_id non valido
        if (carenze) {
            $('#titolo').val("Corso recupero carenze");
            $('#titolo').prop('disabled', true);
        }
        else {
            $('#titolo').val("");
            $('#titolo').prop('disabled', false);
        }
        $('#materia').val("0");
        $('#materia').selectpicker('refresh');
        $('#docente').val("0");
        $('#docente').selectpicker('refresh');
        $('#date_table tbody').empty();
        $('#iscritti_table tbody').empty();
    }

    $("#_error-corsi-part").hide();
    $("#corsi_modal").modal("show");
}

function corsiDelete(id, materia, docente, nstudenti, stato) {
    var conf = confirm("Sei sicuro di volere cancellare il corso di " + materia + " a " + docente + " ?");
    if (!conf) return;

    // Se ci sono studenti iscritti chiedi conferma aggiuntiva
    if (nstudenti > 0) {
        var conf2 = confirm("Ci sono studenti iscritti al corso! Cancellare comunque il corso?");
        if (!conf2) return;
    }

    // Esegui cancellazione
    $.post("../didattica/corsoCancella.php", {
        id: id
    }, function (data, status) {
        corsiReadRecords();
    });
}

function aggiornaTabellaDate(corso_id) {
    $.post("../didattica/corsiReadDetails.php", { corsi_id: corso_id }, function (data) {
        var tbody = $('#date_table tbody');
        tbody.empty();

        data.date.forEach(function (d) {
            var tr = $('<tr>');
            tr.append($('<td>').text(formatDateTime(d.corso_data)));
            tr.append($('<td>').text(d.corso_aula));

            var tdBtn = $('<td>');

            // Bottone Modifica
            var btnMod = $('<button>')
                .addClass('btn btn-sm btn-warning')
                .html('<span class="glyphicon glyphicon-pencil"></span>')
                .on('click', function () { modificaData(d.data_id, corso_id); });

            // Bottone Elimina
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
    // conferma
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

            // Bottone Elimina
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

    // salva il corso corrente (utile per il salvataggio)
    $('#hidden_corso_id').val(corso_id);

    // richiedi le date del corso (stesso endpoint usato in apriRegistroLezione)
    $.post("../didattica/get_date_corso.php", { corso_id: corso_id }, function (resp) {
        if (!resp || !resp.success) {
            showToast("Errore nel recupero delle date del corso", true);
            return;
        }

        // trova la data corrispondente
        var found = resp.date.find(function (d) {
            return String(d.id) === String(data_id) || String(d.id) === String(data_id);
        });

        if (!found) {
            showToast("Data non trovata", true);
            return;
        }

        // assegna hidden id
        $('#hidden_data_id').val(found.id);

        // costruisci valore per input datetime-local (YYYY-MM-DDTHH:MM)
        var raw = String(found.data || found.corso_data || found.data_corrente || "");
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

        $('#mod_data').val(datetimeLocal);
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
    console.log("in_itinere: " + in_itinere);
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

var studentiDisponibili = []; // Popolato via AJAX

function iscriviStudenti() {
    var carenze = $("#carenze").prop('checked');
    var corso_id = $("#hidden_corso_id").val();
    $('#container_studenti').empty();
    $('#error-aggiungi-studenti').hide();

    // Recupera elenco studenti disponibili
    $.getJSON('../didattica/elencoStudentiDisponibili.php', { corso_id: corso_id, carenze: carenze }, function (data) {

        studentiDisponibili = data.stud; // salviamo globalmente
        studentiDisponibili = studentiDisponibili.filter((v, i, a) => a.findIndex(t => t.studente_id === v.studente_id) === i);

        aggiungiSelectStudente();   // aggiungiamo il primo select
    });

    $('#aggiungiStudentiModal').modal('show');
}

// Funzione per creare un nuovo select
function aggiungiSelectStudente() {
    var container = $('#container_studenti');
    var select = $('<select>').addClass('form-control mb-2').css({ maxWidth: '250px', margin: '0 auto' });
    select.append('<option value="">-- Seleziona uno studente --</option>');

    // Popola con studenti ancora disponibili
    studentiDisponibili.forEach(function (s) {
        select.append('<option value="' + s.studente_id + '">' + s.cognome + ' ' + s.nome + ' (' + s.classe + ')</option>');
    });

    // Al cambiamento del select
    select.on('change', function () {
        var val = $(this).val();
        if (val) {
            // Rimuovo lo studente selezionato dai select futuri
            studentiDisponibili = studentiDisponibili.filter(s => s.studente_id != val);
            // Aggiungo un nuovo select solo se l'ultimo select non è vuoto
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
        $.post("corsiImport.php", {
            contenuto: contenuto
        },
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

    // reset campi (non tocco readonly: lo gestisce il PHP in base al ruolo)
    $('#tabellaEsameStudenti tbody').empty();
    $('#argomentiEsame').val('');
    $('#esame_data').val('');
    $('#esame_ora').val('');
    $('#esame_aula').val('');

    $('#esameModal').modal('show');

    $.post("../didattica/corsoEsamiReadDetails.php", { corso_id: corso_id }, function (data) {
        if (data.success) {
            // Se esiste già una data esame → compila i campi
            if (data.esami && data.esami.length > 0) {
                let esame = data.esami[0];
                if (esame.data_esame) {
                    // parsing robusto
                    let raw = String(esame.data_esame);
                    let dt = new Date(raw.replace(' ', 'T'));
                    if (!isNaN(dt.getTime())) {
                        $('#esame_data').val(dt.toISOString().slice(0, 10));  // YYYY-MM-DD
                        $('#esame_ora').val(
                            String(dt.getHours()).padStart(2, '0') + ':' +
                            String(dt.getMinutes()).padStart(2, '0')
                        ); // HH:MM
                    }
                }
                if (esame.aula) {
                    $('#esame_aula').val(esame.aula);
                }
                if (esame.firmato !== undefined) {
                    $('#esameFirmato').prop('checked', esame.firmato == 1);
                }
            }

            // Popola argomenti se esistono 
            if (data.studenti && data.studenti.length > 0) {
                let primoConArg = data.studenti.find(s => s.argomenti && String(s.argomenti).trim() !== "");
                if (primoConArg) {
                    $('#argomentiEsame').val(primoConArg.argomenti);
                }
            }

            // Popola tabella studenti
            if (data.studenti && data.studenti.length > 0) {
                data.studenti.forEach(function (s) {
                    let row = `
                        <tr>
                            <td>${s.cognome} ${s.nome}</td>
                            <td>${s.classe}</td>
                            <td class="text-center">
                                <input type="checkbox" class="chk-presente" data-id="${s.stud_id}" ${s.presente == 1 ? 'checked' : ''}>
                            </td>
                            <td>
                                <select class="form-control tipo-prova" data-id="${s.stud_id}">
                                    <option value="scritto" ${s.tipo_prova === 'scritto' ? 'selected' : ''}>Scritto</option>
                                    <option value="orale" ${s.tipo_prova === 'orale' ? 'selected' : ''}>Orale</option>
                                    <option value="pratico" ${s.tipo_prova === 'pratico' ? 'selected' : ''}>Pratico</option>
                                </select>
                            </td>
                            <td>
                                <input type="number" class="form-control voto-studente" data-id="${s.stud_id}" min="0" max="10" step="0.5" value="${s.voto || ''}">
                            </td>
                            <td class="recuperata text-center">
                                ${s.voto >= 6 ? '✅' : (s.voto ? '❌' : '-')}
                            </td>
                        </tr>`;
                    $('#tabellaEsameStudenti tbody').append(row);
                });

                // aggiorna colonna "carenza recuperata" al variare del voto
                $(".voto-studente").on("input", function () {
                    let voto = parseFloat($(this).val());
                    let cell = $(this).closest("tr").find(".recuperata");
                    if (!isNaN(voto)) {
                        cell.text(voto >= 6 ? "✅" : "❌");
                    } else {
                        cell.text("-");
                    }
                });
            } else {
                $('#tabellaEsameStudenti tbody').html('<tr><td colspan="6" class="text-center text-danger">Nessuno studente iscritto</td></tr>');
            }

        } else {
            showToast("Errore nel caricamento dati esame", true);
        }
    }, 'json').fail(function () {
        showToast("Errore di comunicazione col server", true);
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
    var corso_id = $("#hidden_corso_id").val();
    var argomenti = $('#argomentiEsame').val().trim();
    var firmato = $('#esameFirmato').is(':checked') ? 1 : 0;

    var studenti = [];
    $('#tabellaEsameStudenti tbody tr').each(function () {
        let id = $(this).find('.chk-presente').data('id');
        if (!id) return;

        let presente = $(this).find('.chk-presente').is(':checked') ? 1 : 0;
        let tipo = $(this).find('.tipo-prova').val();
        let voto = $(this).find('.voto-studente').val();

        studenti.push({
            id_studente: id,
            presente: presente,
            tipo: tipo,
            voto: voto
        });
    });

    // recupera anche i campi data/ora/aula
    var data_esame = $('#esame_data').val();
    var ora_esame = $('#esame_ora').val();
    var aula_esame = $('#esame_aula').val().trim();
    var datetime_esame = null;

    if (data_esame && ora_esame) {
        datetime_esame = data_esame + " " + ora_esame + ":00";
    }

    // 🔎 VALIDAZIONI
    if (!argomenti) {
        showToast("Inserisci gli argomenti della prova", true);
        return;
    }
    if (!firmato) {
        showToast("Devi firmare l'esame per poterlo salvare", true);
        return;
    }
    if (!data_esame || !ora_esame) {
        showToast("Inserisci data e ora dell'esame", true);
        return;
    }
    if (!aula_esame) {
        showToast("Inserisci l'aula dell'esame", true);
        return;
    }

    // se tutti i controlli superati → salvo
    $.post("../didattica/corsoEsamiSave.php", {
        corso_id: corso_id,
        argomenti: argomenti,
        data: datetime_esame,
        aula: aula_esame,
        firmato: firmato,
        studenti: studenti
    }, function (data) {
        if (data.success) {
            showToast('Esame salvato con successo');
            $('#esameModal').modal('hide');
            corsiReadRecords();
        } else {
            showToast('Errore nel salvataggio esame: ' + (data.error || ''), true);
        }
    }, 'json').fail(function () {
        showToast("Errore di comunicazione col server", true);
    });
}


$(document).ready(function () {
    corsiReadRecords();

    $("#btn-invia-esiti").on("click", function () {
        showToast("Invio in corso...", false, 10000); // toast iniziale

        $.post("inviaEsitiCoordinatori.php", {}, function (res) {
            let risposta = JSON.parse(res);
            if (risposta.success) {
                showToast("Invio completato!");
            } else {
                showToast("Errore: " + risposta.msg);
            }
        }).fail(function () {
            showToast("Errore nella comunicazione col server");
        });
    });

    $('#in_itinere').change(function () {
        if ($(this).prop('checked')) {
            $('#date_section').hide();
        } else {
            $('#date_section').show();
        }
    });

    $("#incompleti").on("click", function (e) {
        e.preventDefault();

        // Avvio direttamente il download
        window.location.href = "corsiIncompleti.php";
    });

    $("#export_btn").on("click", function (e) {
        e.preventDefault();

        // Avvio direttamente il download
        window.location.href = "exportEsami.php";
    });

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
