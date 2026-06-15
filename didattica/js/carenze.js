/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
// 🔽 Recupero parametro "d" passato nello <script src=...>
var scripts = document.getElementsByTagName('script');
var myScript = scripts[scripts.length - 1];
var url = new URL(myScript.src);
var params = new URLSearchParams(url.search);
var $anni_filtro_id = params.get("a") || "1"; // default 

var $docente_filtro_id = 0;
var $classe_filtro_id = 0;
var $materia_filtro_id = 0;
var $studente_filtro_id = 0;
var $anno_filtro_id = 0;
var $da_validare_filtro = 0;
var $docente_scope_filtro = 1;
let completati = 0;
let totale = 0;

$('#daValidareCheckBox').change(function () {
    // this si riferisce al checkbox
    if (this.checked) {
        $da_validare_filtro = 1;
    } else {
        $da_validare_filtro = 0;
    }
    carenzeReadRecords();
});

function carenzeReadRecords() {
    var hiddenDocenteId = parseInt($("#hidden_docente_id").val() || "0", 10) || 0;
    var vistaDocente = hiddenDocenteId > 0 ? 1 : 0;
    var docenteId = vistaDocente ? hiddenDocenteId : $docente_filtro_id;
    $.get("carenzeReadRecords.php?anno=" + $anno_filtro_id + "&docente_id=" + docenteId + "&vista_docente=" + vistaDocente + "&docente_scope_filtro=" + $docente_scope_filtro + "&classe_id=" + $classe_filtro_id + "&materia_id=" + $materia_filtro_id + "&studente_id=" + $studente_filtro_id + "&da_validare_filtro=" + $da_validare_filtro + "&anni_id=" + $anni_filtro_id, {}, function (data, status) {
        $(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
    });
}

function carenzeCoordinatoreReadRecords() {
    if ($(".coordinatore_records_content").length === 0) return;

    var docenteId = parseInt($("#hidden_docente_id").val() || "0", 10) || 0;
    var vistaDocente = docenteId > 0 ? 1 : 0;
    $.get("carenzeCoordinatoreReadRecords.php?docente_id=" + encodeURIComponent(docenteId) + "&vista_docente=" + vistaDocente + "&classe_id=" + encodeURIComponent($classe_filtro_id) + "&materia_id=" + encodeURIComponent($materia_filtro_id) + "&studente_id=" + encodeURIComponent($studente_filtro_id) + "&anno=" + encodeURIComponent($anno_filtro_id) + "&anni_id=" + encodeURIComponent($anni_filtro_id), {}, function (data, status) {
        $(".coordinatore_records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
    });
}

function carenzeGetDetails(carenza_id) {
    $("#hidden_carenza_id").val(carenza_id);
    $("#hidden_anno_carenza_id").val($anni_filtro_id);
    

    if (carenza_id > 0) {
        $.post("../didattica/carenzeReadDetails.php", {
            carenza_id: carenza_id
        }, function (data, status) {
            var carenza = JSON.parse(data);
            $('#classe').selectpicker('val', carenza.carenza_id_classe);
            $('#materia').selectpicker('val', carenza.carenza_id_materia);
            $('#studente').selectpicker('val', carenza.carenza_id_studente);
        });
        carenzeReadRecords(carenza_id);
    }
    else {
        $('#classe').val("0");
        $('#classe').selectpicker('refresh');
        $('#materia').val("0");
        $('#materia').selectpicker('refresh');
        $('#studente').val("0");
        $('#studente').selectpicker('refresh');
    }
    $("#_error-carenza-part").hide();
    $("#carenza_modal").modal("show");
}

function carenzaDelete(id, materia, studente) {
    var conf = confirm("Sei sicuro di volere cancellare la carenza di " + materia + " a " + studente + " ?");
    if (conf == true) {
        $.post("../didattica/carenzeDelete.php", {
            id: id
        },
            function (data, status) {
                carenzeReadRecords();
            }
        );
    }
}

function carenzaPrint(id_carenza,id_anno_carenza) {
    // creo form nascosto
    console.log($anni_filtro_id)
    var form = $('<form>', {
        action: 'stampaCarenza.php',
        method: 'POST',
        target: '_black'    // apre in un nuovo tab
    });
    // aggiungo i campi
    form.append($('<input>', { type: 'hidden', name: 'id', value: id_carenza }));
    form.append($('<input>', { type: 'hidden', name: 'print', value: 0 }));
    form.append($('<input>', { type: 'hidden', name: 'mail', value: 0 }));
    form.append($('<input>', { type: 'hidden', name: 'genera', value: 0 }));
    form.append($('<input>', { type: 'hidden', name: 'view', value: 1 }));
    form.append($('<input>', { type: 'hidden', name: 'anno', value: id_anno_carenza }));
    form.append($('<input>', { type: 'hidden', name: 'titolo', value: 'Programma carenza formativa' }));
    // lo “submitto” e lo rimuovo
    form.appendTo('body').submit().remove();

}

function carenzaSend(id_carenza) {
        // creo form nascosto
    console.log($anni_filtro_id)
    var form = $('<form>', {
        action: 'stampaCarenza.php',
        method: 'POST',
        target: '_black'    // apre in un nuovo tab
    });
    // aggiungo i campi
    form.append($('<input>', { type: 'hidden', name: 'id', value: id_carenza }));
    form.append($('<input>', { type: 'hidden', name: 'print', value: 0 }));
    form.append($('<input>', { type: 'hidden', name: 'mail', value: 1 }));
    form.append($('<input>', { type: 'hidden', name: 'genera', value: 0 }));
    form.append($('<input>', { type: 'hidden', name: 'view', value: 0 }));
    form.append($('<input>', { type: 'hidden', name: 'anno', value: id_anno_carenza }));
    form.append($('<input>', { type: 'hidden', name: 'titolo', value: 'Programma carenza formativa' }));
    // lo “submitto” e lo rimuovo
    form.appendTo('body').submit().remove();
    $.post("stampaCarenza.php", {
        id: id_carenza,
        print: 0,
        mail: 1,
        genera: 0,
        view: 0,
        anno: id_anno_carenza,
        titolo: 'Programma carenza formativa'
    },
        function (data, status) {
            if (data == 'sent') {
                alert("Carenza spedita alla mail dello studente!");
            }
            else {
                alert("Carenza NON spedita! " + data);
            }
            carenzeReadRecords();
        }
    );
}

function carenzaGenera(id_carenza) {
    $.post("stampaCarenza.php", {
        id: id_carenza,
        print: 0,
        mail: 0,
        genera: 1,
        view: 0,
        titolo: 'Programma carenza formativa',
        anno: id_anno_carenza
    },
        function (data, status) {
            if (data == 'generato') {
                alert("Carenza generata correttamente!");
            }
            else if (data == 'aggiornato') {
                alert("Carenza aggiornata correttamente!");
            }

            else 
            {
                alert("Carenza NON generata! " + data);
            }
            carenzeReadRecords();
        }
    );
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

async function generaCarenze() {
    const arrayc = $('#hidden_arraycarenzegenera').val();
if (!arrayc || arrayc.trim() === '' || arrayc.split(',').filter(e => e.trim() !== '').length === 0) {
    alert("Nessuna carenza da generare!");
    return;
}
    const arraycarenze = arrayc.split(',').map(id => id.trim()).filter(id => id !== '');
      totale = arraycarenze.length;
      completati = 0;
      if (arraycarenze.length > 0)
      {
        mostraOverlay();

        for (const id of arraycarenze) {
            try {
                const response = await $.post("stampaCarenza.php", {
                    id: id,
                    print: 0,
                    mail: 0,
                    genera: 1,
                    view: 0,
                    titolo: 'Programma carenze formative'
                });

                if ((response.trim() !== 'generato')&&(response.trim() !== 'aggiornato')) {
                    console.error(`Errore per studente ID ${id}: ${response}`);
                }

            } catch (err) {
                console.error(`Errore AJAX per studente ID ${id}:`, err);
            }
            aggiornaProgressBar(completati, totale);

            // Delay tra 1 e 2 secondi
            await sleep(Math.floor(Math.random() * 1000) + 1000);
        }

        nascondiOverlay(); // opzionale: chiudi overlay al termine
    }
    carenzeReadRecords();
}

async function invioMassivoCarenze() {
   const carenze = $('#hidden_arraycarenzemail').val();
    const carenze_array = carenze.split(',').map(id => id.trim()).filter(id => id !== '');

    totale = carenze_array.length;
    completati = 0;

    if (totale > 0) {
        mostraOverlay();

        for (const carenza of carenze_array) {
            await $.post("stampaCarenza.php", {
                id: carenza,
                print: 0,
                mail: 1,
                genera: 0,
                view: 0,
                titolo: 'Programma carenze formative'
            }).then(response => {
                if (response.trim() !== 'sent') {
                    console.error(`Errore per studente ID ${carenza}: ${response}`);
                }
            }).catch(err => {
                console.error(`Errore AJAX per studente ID ${carenza}:`, err);
            });

            aggiornaProgressBar();
            await sleep(Math.floor(Math.random() * 5000) + 5000); // tra 5 e 10 secondi
        }
    } else {
        alert("Nessuna carenza da inviare!");
    }
    carenzeReadRecords();
}

function carenzaValida(id, id_utente, stato) {
    if (stato == 0) {
        carenzaApriNotaModal({
            mode: "validate",
            id: id,
            id_utente: id_utente,
            title: "Conferma carenza",
            help: "Puoi aggiungere indicazioni per lo studente: materiali da studiare, riferimenti a Classroom, esercizi o altre note utili.",
            note: "Nessuna nota aggiuntiva dal docente"
        });
        return;
    }

    if (stato == 1 && confirm("Confermi che vuoi togliere la validazione a questa carenza?")) {
        carenzaInviaValidazione(id, id_utente, stato, "");
    }
}

function carenzaInviaValidazione(id, id_utente, stato, nota_docente) {
    $.post("../didattica/carenzaValida.php", {
        id: id,
        id_utente: id_utente,
        stato: stato,
        nota: nota_docente
    },
        function (data, status) {
            if (data && $.trim(data) !== '') {
                alert(data);
            }
            carenzeReadRecords();
        }
    ).fail(function (xhr) {
        alert(xhr.responseText || "Validazione non riuscita.");
        carenzeReadRecords();
    });
}

function carenzaModificaNota(id, nota_attuale) {
    carenzaApriNotaModal({
        mode: "edit",
        id: id,
        title: "Modifica nota",
        help: "Aggiorna le indicazioni che compariranno nella carenza dello studente.",
        note: nota_attuale || ""
    });
}

function carenzaApriNotaModal(options) {
    var $modal = $("#carenza_nota_modal");
    if ($modal.length === 0) {
        alert("Modale nota non disponibile.");
        return;
    }

    $("#carenza_nota_mode").val(options.mode || "");
    $("#carenza_nota_id").val(options.id || "");
    $("#carenza_nota_id_utente").val(options.id_utente || "");
    $("#carenza_nota_title").text(options.title || "Nota carenza");
    $("#carenza_nota_help").text(options.help || "");
    $("#carenza_nota_testo").val(options.note || "");
    $("#carenza_nota_save_btn").text(options.mode === "validate" ? "Conferma carenza" : "Salva nota");

    $modal.one("shown.bs.modal", function () {
        $("#carenza_nota_testo").focus().select();
    });
    $modal.modal("show");
}

function carenzaNotaModalSalva() {
    var mode = $("#carenza_nota_mode").val();
    var id = $("#carenza_nota_id").val();
    var id_utente = $("#carenza_nota_id_utente").val();
    var nota = $("#carenza_nota_testo").val();

    $("#carenza_nota_modal").modal("hide");

    if (mode === "validate") {
        carenzaInviaValidazione(id, id_utente, 0, nota);
        return;
    }

    $.post("../didattica/carenzaNotaSave.php", {
        id: id,
        nota: nota
    }, function (data) {
        if (data) {
            alert(data);
        }
        carenzeReadRecords();
    }).fail(function (xhr) {
        alert(xhr.responseText || "Errore durante il salvataggio della nota.");
    });
}

function carenzaSave() {

    if ($("#studente").val() <= 0) {
        $("#_error-carenza").text("Devi selezionare uno studente");
        $("#_error-carenza-part").show();
        return;
    }
    if ($("#classe").val() <= 0) {
        $("#_error-carenza").text("Devi selezionare una classe");
        $("#_error-carenza-part").show();
        return;
    }
    if ($("#materia").val() <= 0) {
        $("#_error-carenza").text("Devi selezionare una materia");
        $("#_error-carenza-part").show();
        return;
    }

    $("#_error-carenza-part").hide();
    console.log("anno id " + $anni_filtro_id);
    $.post("carenzaSave.php", {
        id: $("#hidden_carenza_id").val(),
        studente_id: $("#studente").val(),
        classe_id: $("#classe").val(),
        materia_id: $("#materia").val(),
        anno_id: $anni_filtro_id
    }, function (data, status) {
        $("#carenza_modal").modal("hide");
        carenzeReadRecords();
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

function exportFile() {
    var hiddenDocenteId = parseInt($("#hidden_docente_id").val() || "0", 10) || 0;
    var vistaDocente = hiddenDocenteId > 0 ? 1 : 0;
    var docenteId = vistaDocente ? hiddenDocenteId : $docente_filtro_id;
    const url = "carenzeExport.php"
        + "?id_docente=" + encodeURIComponent(docenteId)
        + "&vista_docente=" + encodeURIComponent(vistaDocente)
        + "&docente_scope_filtro=" + encodeURIComponent($docente_scope_filtro)
        + "&id_classe=" + encodeURIComponent($classe_filtro_id)
        + "&id_materia=" + encodeURIComponent($materia_filtro_id)
        + "&id_studente=" + encodeURIComponent($studente_filtro_id)
        + "&id_anno=" + encodeURIComponent($anno_filtro_id)
        + "&da_validare=" + encodeURIComponent($da_validare_filtro);
    // Forza il browser a scaricare il file
    console.log(url);
    window.open(url, '_blank');
};

function exportCoordinatore(format) {
    var docenteId = parseInt($("#hidden_docente_id").val() || "0", 10) || 0;
    var vistaDocente = docenteId > 0 ? 1 : 0;
    const url = "carenzeCoordinatoreExport.php"
        + "?format=" + encodeURIComponent(format)
        + "&docente_id=" + encodeURIComponent(docenteId)
        + "&vista_docente=" + encodeURIComponent(vistaDocente)
        + "&classe_id=" + encodeURIComponent($classe_filtro_id)
        + "&materia_id=" + encodeURIComponent($materia_filtro_id)
        + "&studente_id=" + encodeURIComponent($studente_filtro_id)
        + "&anno=" + encodeURIComponent($anno_filtro_id)
        + "&anni_id=" + encodeURIComponent($anni_filtro_id);
    window.open(url, '_blank');
}

$(document).ready(function () {

    const docentePreselezionato = parseInt($("#hidden_docente_id").val() || "0", 10);
    if (docentePreselezionato > 0) {
        $docente_filtro_id = docentePreselezionato;
        $('#docente_filtro').selectpicker('val', String(docentePreselezionato));
    }
    $docente_scope_filtro = $("#docenteScopeCheckBox").length > 0 && $("#docenteScopeCheckBox").prop("checked") ? 1 : 0;

    carenzeReadRecords();
    carenzeCoordinatoreReadRecords();

    $("#docenteScopeCheckBox").change(function () {
        $docente_scope_filtro = this.checked ? 1 : 0;
        carenzeReadRecords();
    });

    $("#docente_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $docente_filtro_id = this.value;
            carenzeReadRecords();
        });

    $("#classe_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $classe_filtro_id = this.value;
            carenzeReadRecords();
            carenzeCoordinatoreReadRecords();
        });

    $("#materia_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $materia_filtro_id = this.value;
            carenzeReadRecords();
            carenzeCoordinatoreReadRecords();
        });

    $("#studente_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $studente_filtro_id = this.value;
            carenzeReadRecords();
            carenzeCoordinatoreReadRecords();
        });
        
    $("#anni_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $anni_filtro_id = this.value;
            carenzeReadRecords();
            carenzeCoordinatoreReadRecords();
        });

    $("#anno_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $anno_filtro_id = this.value;
            carenzeReadRecords();
            carenzeCoordinatoreReadRecords();
        });

    $('#export_btn').on('click', function (e) {
        exportFile();
    });

    $('#coord_export_pdf_btn').on('click', function (e) {
        exportCoordinatore('pdf');
    });

    $('#coord_export_xlsx_btn').on('click', function (e) {
        exportCoordinatore('xlsx');
    });

    $('#send_btn').on('click', function (e) {
        invioMassivoCarenze();
    });

    $('#genera_btn').on('click', function (e) {
        generaCarenze();
    });

    $('#file_select_id').change(function (e) {
        importFile(e.target.files[0]);
    });

    //$('#docente_filtro').val("0");
    //$('#docente_filtro').selectpicker('refresh');
});     
