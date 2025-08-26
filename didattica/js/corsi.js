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
// function modificaData(data_id) {
//     var row = res.date.find(d => d.data_id == data_id);
//     if(!row) return;
//     $('#hidden_data_id').val(row.data_id);
//     var d = new Date(row.corso_data);
//     var formatted = d.getFullYear() + '-' +
//                     String(d.getMonth()+1).padStart(2,'0') + '-' +
//                     String(d.getDate()).padStart(2,'0') + 'T' +
//                     String(d.getHours()).padStart(2,'0') + ':' +
//                     String(d.getMinutes()).padStart(2,'0');
//     $('#mod_data').val(formatted);
//     $('#mod_aula').val(row.corso_aula);

//     $("#modificaDataModal").modal('show');
//     $('body').addClass('modal-open');  // mantiene la prima modale attiva
// }

// $('#modificaDataModal').on('hidden.bs.modal', function () {
//     $('.modal-backdrop').not(':first').remove(); // rimuove backdrop extra
// });


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

    $.post("../didattica/corsiAggiornaData.php", {
        data_id: data_id,
        corso_id: corso_id,
        corso_data: nuova_data,
        corso_aula: nuova_aula
    }, function (data, status) {
        // chiudi la modal
        if (data.status = 'ok') {
            corsiGetDetails(data.id); // supponendo che ricarichi il JSON del corso
            $('#modificaDataModal').modal('hide');
            // aggiorna la tabella date con le nuove info
        }
        else {

            $('#error-modifica-data').text("Errore durante il salvataggio").show();
        };
    });
}

function corsiGetDetails(corsi_id) {
    $("#hidden_corso_id").val(corsi_id);
    carenze = $("#carenze").prop('checked');

    if (corsi_id > 0) {
        $.post("../didattica/corsiReadDetails.php", { corsi_id: corsi_id }, function (data, status) {
            var corsi = data;

            // Aggiorna campi del corso
            $('#titolo').val(corsi.corso.titolo);
            $('#titolo').prop('disabled', false);

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

        }, 'json'); // importante specificare json per evitare parsing manuale
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

function cancellaIscritto(iscrizione_id) {
    corsi_id = $("#hidden_corso_id").val();
    if (!window.confirm("Sei sicuro di volere cancellare questo studente?")) return;

    $.post("../didattica/corsoCancellaIscritto.php", { id: iscrizione_id, corso_id: corsi_id }, function (data) {
        $('#row_stud_' + iscrizione_id).remove();
    }, 'json');
    corsiGetDetails(corsi_id); // supponendo che ricarichi il JSON del corso
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

    $.post("corsiSave.php", {
        id: $("#hidden_corso_id").val(),
        docente_id: $("#docente").val(),
        materia_id: $("#materia").val(),
        titolo: $("#titolo").val()
    }, function (data, status) {
        $("#corsi_modal").modal("hide");
        corsiReadRecords();
    });

}

var studentiDisponibili = []; // Popolato via AJAX

function iscriviStudenti() {
    carenze = $("#carenze").prop('checked');
    corso_id = $("#hidden_corso_id").val();
    $('#container_studenti').empty();
    $('#error-aggiungi-studenti').hide();
    console.log(carenze);
    // Recupera elenco studenti disponibili
    $.getJSON('../didattica/elencoStudentiDisponibili.php', { corso_id: corso_id, carenze: carenze }, function (data) {
        
        studentiDisponibili = data.stud; // salviamo globalmente
        studentiDisponibili = studentiDisponibili.filter((v,i,a)=>a.findIndex(t=>t.studente_id===v.studente_id)===i);

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
        $('#error-aggiungi-studenti').text("Seleziona almeno uno studente").show();
        return;
    }
    $('#error-aggiungi-studenti').hide();

    $.post('../didattica/corsoAggiungiStudenti.php',
        { id_corso: corso_id, id_studente: studenti_id },
        function (data) {
            if (data.status == 'ok') {
                aggiornaTabellaStudenti(corso_id);
                $('#aggiungiStudentiModal').modal('hide');
            } else {
                $('#error-aggiungi-studenti').text(data.message || "Errore durante l'aggiunta").show();
            }
        }, 'json'
    );

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
