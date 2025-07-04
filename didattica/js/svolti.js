/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var $classi_filtro_id = 0;
var $materia_filtro_id = 0;
var $docenti_filtro_id = 0;
var $da_completare_filtro_id = 0;


$('#daCompletareCheckBox').change(function () {
    // this si riferisce al checkbox
    if (this.checked) {
        $da_completare_filtro_id = 1;
        $('#send_btn').show();
    } else {
        $da_completare_filtro_id = 0;
        $('#send_btn').hide();
    }
    programmiSvoltiReadRecords();
});

function programmiSvoltiReadRecords() {
    if ($("#hidden_docente_id").val() > 0)
        $docenti_filtro_id = $("#hidden_docente_id").val();
    $.get("programmiSvoltiReadRecords.php?classi_id=" + $classi_filtro_id + "&materia_id=" + $materia_filtro_id + "&docenti_id=" + $docenti_filtro_id + "&da_completare_id=" + $da_completare_filtro_id, {}, function (data, status) {
        $(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
    });
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
            alert("Tutte le email sono state inviate!");
        }, 500);
    }
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function inviaSollecito(single_id) {

    if (single_id > 0) {
        totale = 1;
        completati = 0;
        await $.post("invioSollecito.php", {
            id: single_id
        }).then(response => {
            if (response.trim() !== 'sent') {
                console.error(`Errore per programma ID ${single_id}: ${response}`);
            }
        }).catch(err => {
            console.error(`Errore AJAX per studente ID ${single_id}:`, err);
        });
        aggiornaProgressBar();
        await sleep(Math.floor(Math.random() * 5000) + 1000); // tra 1 e 2 secondi    
    }
    else {
        const sollecito = $('#hidden_sollecito').val();
        const sollecito_array = sollecito.split(',');
        totale = sollecito_array.length;
        completati = 0;

        if (totale > 0) {
            mostraOverlay();

            for (const soll of sollecito_array) {
                await $.post("invioSollecito.php", {
                    id: soll
                }).then(response => {
                    if (response.trim() !== 'sent') {
                        console.error(`Errore per programma ID ${soll}: ${response}`);
                    }
                }).catch(err => {
                    console.error(`Errore AJAX per studente ID ${soll}:`, err);
                });

                aggiornaProgressBar();
                await sleep(Math.floor(Math.random() * 5000) + 1000); // tra 1 e 2 secondi
            }
        } else {
            alert("Nessun sollecito da inviare!");
        }
    }
}

function moduliSvoltiReadRecords(programma_id) {
    $.get("../didattica/moduliSvoltiReadRecords.php", {
        programma_id: programma_id
    }, function (data, status) {
        $(".moduli_content").val("");
        $(".moduli_content")
        $(".moduli_content").html(data);
    });

}

function programmiSvoltiGetDetails(programma_id, duplica, share) {
    $("#hidden_programma_id").val(programma_id);
    $("#hidden_duplica").val(duplica);
    $("#hidden_share").val(share);
    id_docente = $('#docente').val();
    if (duplica == 'true') {
        $("#myModalLabel1").html("Duplica il programma per un altra classe");
    }
    else
        if (share == 'true') {
            $("#myModalLabel1").html("Invia una copia del programma al codocente della classe");
        }
        else {
            $("#myModalLabel1").html("Programma svolto");
        }
    if (programma_id > 0) {
        $.post("../didattica/programmiSvoltiReadDetails.php", {
            programma_id: programma_id
        }, function (data, status) {
            var programma = JSON.parse(data);
            if (duplica == 'true') {
                $('#classe').selectpicker('val', 0);
            }
            else {
                $('#classe').selectpicker('val', programma.programma_classe);
            }
            if (share == 'true') {
                $('#docente').selectpicker('val', 0);
            }
            else {
                $('#docente').selectpicker('val', programma.programma_iddocente);
            }

            $('#materia').selectpicker('val', programma.programma_idmateria);

            if (duplica == 'false') {
                $('#classe').attr('disabled', true);
            }
            else {
                $('#classe').attr('disabled', false);
            }
            if (share == 'false') {
                $('#docente').attr('disabled', true);
            }
            else {
                $('#docente').attr('disabled', false);
            }
            $('#materia').attr('disabled', true);
            $('#classe').selectpicker('refresh');
            $('#materia').selectpicker('refresh');
            $('#docente').selectpicker('refresh');
        });
        moduliSvoltiReadRecords(programma_id);
    }
    else {
        $('#classe').attr('disabled', false);
        if (id_docente != 0) {
            $('#docente').attr('disabled', true);
        }
        else {
            $('#docente').attr('disabled', false);
        }
        $('#materia').attr('disabled', false);
        $('#classe').val("0");
        $('#classe').selectpicker('refresh');
        $('#classe').disabled = true;
        $('#docente').val(id_docente);
        $('#docente').selectpicker('refresh');
        $('#materia').val("0");
        $('#materia').selectpicker('refresh');
        $(".moduli_content").html("");

    }
    $("#_error-programma-part").hide();
    $("#programma_modal").modal("show");
}

async function moduliSvoltiImport() {
    let programma_id = $("#hidden_programma_id").val();

    // Se il programma id è negativo, salviamo prima
    if (programma_id < 0) {

        programma_id = await new Promise((resolve, reject) => {
            $.post("programmiSvoltiSave.php", {
                id: '-1',
                docente_id: $("#docente").val(),
                classe_id: $("#classe").val(),
                materia_id: $("#materia").val(),
                duplica: 'false',
                share: 'false',
                overwrite: 'false'
            }, function (data, status) {
                $("#hidden_programma_id").val(data);
                resolve(data);
            }).fail(function (jqXHR, textStatus, errorThrown) {
                console.error("Errore nel salvataggio:", textStatus, errorThrown);
                reject(errorThrown);
            });
        });
    }

    console.log("programma ID after " + programma_id);

    // Se abbiamo un ID valido, proseguiamo con l'importazione
    if (programma_id > 0) {
        var conf = confirm("Sei sicuro di volere importare il programma di dipartimento ? Eventuali moduli già presenti saranno sovrascritti.");

        if (conf == true) {
            await new Promise((resolve, reject) => {
                $.post("../didattica/moduliSvoltiImport.php", {
                    programma_modulo_id: programma_id,
                    classe_id: $('#classe').val(),
                    materia_id: $('#materia').val()
                },
                    function (data, status) {
                        console.log("Importazione completata");
                        moduliSvoltiReadRecords($("#hidden_programma_id").val());
                        resolve();
                    }).fail(function (jqXHR, textStatus, errorThrown) {
                        console.error("Errore nell'importazione:", textStatus, errorThrown);
                        reject(errorThrown);
                    });
            });
        }
    }
}

async function moduloSvoltiGetDetails(modulo_id) {
    let programma_id = $("#hidden_programma_id").val();

    // Se il programma id è negativo, salviamo prima
    if (programma_id < 0) {

        programma_id = await new Promise((resolve, reject) => {
            $.post("programmiSvoltiSave.php", {
                id: '-1',
                docente_id: $("#docente").val(),
                classe_id: $("#classe").val(),
                materia_id: $("#materia").val(),
                duplica: 'false',
                share: 'false',
                overwrite: 'false'
            }, function (data, status) {
                console.log('data save ' + data);
                $("#hidden_programma_id").val(data);
                resolve(data);
            }).fail(function (jqXHR, textStatus, errorThrown) {
                console.error("Errore nel salvataggio:", textStatus, errorThrown);
                reject(errorThrown);
            });
        });
    }
    programma_id = $("#hidden_programma_id").val();
    $("#hidden_modulo_id").val(modulo_id);
    let nmoduli = $("#hidden_nmoduli").val();

    if (modulo_id > 0) {
        const data = await new Promise((resolve, reject) => {
            $.post("../didattica/moduloSvoltiReadDetails.php", {
                modulo_id: modulo_id
            }, function (data, status) {
                resolve(data);
            }).fail(function (jqXHR, textStatus, errorThrown) {
                console.error("Errore nel recupero dettagli modulo:", textStatus, errorThrown);
                reject(errorThrown);
            });
        });
        const programma = JSON.parse(data);
        $('#titolo').val(programma.modulo_nome);
        $('#ordine').val(programma.modulo_ordine);
        $('#contenuto').val(programma.modulo_contenuto);
    }
    else {
        console.log("Nmoduli " + nmoduli);
        console.log("Nmoduli bis " + parseInt(nmoduli));
        $('#titolo').val("");
        $('#ordine').val(parseInt(nmoduli) + 1);
        $('#contenuto').val("");
        $("#moduli_content").html("");
    }
    $("#_error-modulo-part").hide();
    $("#modulo_modal").modal("show");
}


function programmiSvoltiDelete(id, materia) {
    var conf = confirm("Sei sicuro di volere cancellare il programma di " + materia + " ?");
    if (conf == true) {
        $.post("../didattica/moduliElimina.php", {
            id: id
        });
        $.post("../common/deleteRecord.php", {
            id: id,
            table: 'programmi_svolti',
            name: "materia" + materia
        },
            function (data, status) {
                programmiSvoltiReadRecords();
            }
        );
    }
}

function programmiSvoltiPrint(id_programma) {
    // creo form nascosto
    var form = $('<form>', {
        action: 'stampaProgrammiSvolti.php',
        method: 'POST',
        target: '_black'    // apre in un nuovo tab
    });
    // aggiungo i campi
    form.append($('<input>', { type: 'hidden', name: 'id', value: id_programma }));
    form.append($('<input>', { type: 'hidden', name: 'print', value: 0 }));
    form.append($('<input>', { type: 'hidden', name: 'titolo', value: 'Programma svolto' }));
    // lo “submitto” e lo rimuovo
    form.appendTo('body').submit().remove();
}

function moduloSvoltiDelete(id, id_programma, titolo) {
    var conf = confirm("Sei sicuro di volere cancellare il modulo  " + titolo + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
            id: id,
            table: 'programmi_svolti_moduli',
            name: "nome" + titolo
        },
            function (data, status) {
                moduliSvoltiReadRecords(id_programma);
                //$("#programma_modal").modal("hide");
            }
        );
    }
}

function programmiSvoltiSave() {

    if ($("#docente").val() <= 0) {
        $("#_error-programma").text("Devi selezionare un docente");
        $("#_error-programma-part").show();
        return;
    }
    if ($("#classe").val() <= 0) {
        $("#_error-programma").text("Devi selezionare una classe");
        $("#_error-programma-part").show();
        return;
    }
    if ($("#materia").val() <= 0) {
        $("#_error-programma").text("Devi selezionare una materia");
        $("#_error-programma-part").show();
        return;
    }

    $("#_error-programma-part").hide();

    $.post("programmiSvoltiSave.php", {
        id: $("#hidden_programma_id").val(),
        docente_id: $("#docente").val(),
        classe_id: $("#classe").val(),
        materia_id: $("#materia").val(),
        duplica: $("#hidden_duplica").val(),
        share: $("#hidden_share").val()
    }, function (data, status) {
        if (data == 'Programma già esistente') {
            if ($("#hidden_share").val() == 'true') {
                alert("Non puoi condividere il programma con il docente, perchè ha già un programma presente!")
            }
            else {
                alert("Esiste già il programma nella classe di destinazione!");
            }
        }
        else {
            $("#programma_modal").modal("hide");
            programmiSvoltiReadRecords();
        }

    });
}

function moduloSvoltiSave() {

    if ($.trim($("#ordine").val()).length <= 0) {
        $("#_error-modulo").text("Devi indicare l'ordine del modulo, ad es. 1");
        $("#_error-modulo-part").show();
        return;
    }
    if ($.trim($("#titolo").val()).length <= 0) {
        $("#_error-modulo").text("Devi indicare il titolo del modulo");
        $("#_error-modulo-part").show();
        return;
    }
    if ($.trim($("#contenuto").val()).length <= 0) {
        $("#_error-modulo").text("Devi indicare il contenuto");
        $("#_error-modulo-part").show();
        return;
    }
    $("#_error-modulo-part").hide();
    console.log("salvataggio in corso");
    $.post("moduloSvoltiSave.php", {
        id: $("#hidden_modulo_id").val(),
        id_programma: $("#hidden_programma_id").val(),
        ordine: $("#ordine").val(),
        titolo: $("#titolo").val(),
        contenuto: $("#contenuto").val(),
    }, function (data, status) {
        $("#modulo_modal").modal("hide");
        moduliSvoltiReadRecords($("#hidden_programma_id").val());
    });

}


$(document).ready(function () {


    programmiSvoltiReadRecords();

    $("#classi_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $classi_filtro_id = this.value;
            programmiSvoltiReadRecords();
        });

    $('#send_btn').on('click', function (e) {
        inviaSollecito(-1);
    });

    $("#materia_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $materia_filtro_id = this.value;
            programmiSvoltiReadRecords();
        });

    $("#docente_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $docenti_filtro_id = this.value;
            programmiSvoltiReadRecords();
        });
    $('#send_btn').hide();
});     
