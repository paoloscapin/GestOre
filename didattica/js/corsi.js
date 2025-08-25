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

function salvaModificaData() {
    // Prendi i valori dai campi
    const idCorso = $('#hidden_data_id').val(); // se serve
    const dataOra = $('#mod_data').val();
    const aula = $('#mod_aula').val();

    if (!dataOra || !aula) {
        $('#error-modifica-data').text('Compila tutti i campi').show();
        return;
    }

    // Invia i dati al PHP via AJAX
    $.ajax({
        url: 'aggiornaDataCorso.php', // PHP che inserisce la data nel DB
        type: 'POST',
        data: {
            corso_id: idCorso,
            data_ora: dataOra,
            aula: aula
        },
        success: function(response) {
            // Se vuoi, puoi controllare response per errori
            // Chiudi il secondo modale
            $('#modificaDataModal').modal('hide');

            // Richiama la funzione per aggiornare il corso nel primo modale
            // Assumendo che idCorso contenga l'ID del corso aperto
            corsoGetDetails(idCorso);
        },
        error: function() {
            $('#error-modifica-data').text('Errore durante il salvataggio').show();
        }
    });
}

function aggiungiDate() {
    // Reset dei campi del modale
    $('#hidden_data_id').val('');
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
function modificaData(data_id) {
    var row = res.date.find(d => d.data_id == data_id);
    if(!row) return;

    $('#hidden_data_id').val(row.data_id);

    var d = new Date(row.corso_data);
    var formatted = d.getFullYear() + '-' +
                    String(d.getMonth()+1).padStart(2,'0') + '-' +
                    String(d.getDate()).padStart(2,'0') + 'T' +
                    String(d.getHours()).padStart(2,'0') + ':' +
                    String(d.getMinutes()).padStart(2,'0');
    $('#mod_data').val(formatted);
    $('#mod_aula').val(row.corso_aula);

    $("#modificaDataModal").modal('show');
    $('body').addClass('modal-open');  // mantiene la prima modale attiva
}

$('#modificaDataModal').on('hidden.bs.modal', function () {
    $('.modal-backdrop').not(':first').remove(); // rimuove backdrop extra
});


function salvaModificaData() {
    var data_id = $('#hidden_data_id').val();
    var nuova_data = $('#mod_data').val();
    var nuova_aula = $('#mod_aula').val();

    if(!nuova_data || !nuova_aula) {
        $('#error-modifica-data').text("Compila tutti i campi").show();
        return;
    }
    $('#error-modifica-data').hide();

 $.post("../didattica/aggiornaDataCorso.php", {
                        data_id: data_id,
            corso_data: nuova_data,
            corso_aula: nuova_aula
        }, function (data, status) {
            // chiudi la modal
            $('#modificaDataModal').modal('hide');
            // aggiorna la tabella date con le nuove info
            corsiGetDetails(); // supponendo che ricarichi il JSON del corso
        },
        function() {
            $('#error-modifica-data').text("Errore durante il salvataggio").show();
        });
}


function corsiGetDetails(corsi_id) {
    $("#hidden_corso_id").val(corsi_id);

    if (corsi_id > 0) {
        $.post("../didattica/corsiReadDetails.php", {
            corsi_id: corsi_id
        }, function (data, status) {
            console.log(data);
            var corsi = data;
            $('#titolo').val(corsi.corso.titolo);
            $('#materia').selectpicker('val', corsi.corso.materia_id);
            $('#docente').selectpicker('val', corsi.corso.doc_id);

            // pulisco le tabelle prima di riempirle
            $('#date_table tbody').empty();
            $('#iscritti_table tbody').empty();

            // costruzione righe date

            var markupDate = '';
            corsi.date.forEach(function (d) {
                markupDate += "<tr>" +
                    "<td style='text-align:center; vertical-align:middle;'>" + formatDateTime(d.corso_data) + "</td>" +
                    "<td style='text-align:center; vertical-align:middle;'>" + d.corso_aula + "</td>" +
                    "<td style='text-align:center; vertical-align:middle;'>" +
                    "<button class='btn btn-sm btn-warning' onclick='modificaData(" + d.data_id + ")' title='Modifica'>" +
                    "<span class='glyphicon glyphicon-pencil'></span>" +
                    "</button> " +
                    "<button class='btn btn-sm btn-danger' onclick='cancellaData(" + d.data_id + ")' title='Elimina'>" +
                    "<span class='glyphicon glyphicon-trash'></span>" +
                    "</button>" +
                    "</td>" +
                    "</tr>";
            });
            $('#date_table > tbody:last-child').append(markupDate);

            // costruzione righe studenti iscritti
            var markupStud = '';
            corsi.studenti.forEach(function (s) {
                var nominativo = s.stud_cognome + " " + s.stud_nome;
                markupStud += "<tr>" +
                    "<td style='text-align:center; vertical-align:middle;'>" + nominativo + "</td>" +
                    "<td style='text-align:center; vertical-align:middle;'>" + s.classe + "</td>" +
                    "<td style='text-align:center; vertical-align:middle;'>" +
                    "<button class='btn btn-sm btn-danger' onclick='cancellaIscritto(" + s.iscrizione_id + ")' title='Elimina'>" +
                    "<span class='glyphicon glyphicon-trash'></span>" +
                    "</button>" +
                    "</td>" +
                    "</tr>";
            });
            $('#iscritti_table > tbody:last-child').append(markupStud);


        });
    }
    else {
        $('#titolo').val("");
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
