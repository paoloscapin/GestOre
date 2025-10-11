/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var soloNuovi = 1;
var ancheCancellati = 0;

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

$('#ancheCancellatiCheckBox').change(function () {
    // this si riferisce al checkbox
    if (this.checked) {
        ancheCancellati = 1;
    } else {
        ancheCancellati = 0;
    }
    sportelloReadRecords();
});

function sportelloReadRecords() {
    $.get("sportelloReadRecords.php?ancheCancellati=" + ancheCancellati + "&soloNuovi=" + soloNuovi, {}, function (data, status) {
        $(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
    });
}

function sportelloDelete(id, materia) {
    var conf = confirm("Sei sicuro di volere cancellare lo sportello di " + materia + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
            id: id,
            table: 'sportello',
            name: "materia" + materia
        },
            function (data, status) {
                if (data == 'Application Error') {
                    errorNotify('Impossibile cancellare lo sportello', 'Lo sportello della materia <Strong>' + materia + '</Strong> contiene probabilmente degli studenti iscritti o altri riferimenti');
                } else {
                    infoNotify('Cancellazione effettuata', 'Lo sportello della materia <Strong>' + materia + '</Strong> è stato cancellato regolarmente');
                }
                sportelloReadRecords();
            }
        );
    }
}

function sportelloSave() {
    // controlla che ci siano la materia ed il numero di ore

    if ($("#materia").val() <= 0) {
        $("#_error-materia").text("Devi selezionare una materia");
        $("#_error-materia-part").show();
        return;
    }
    if ($("#categoria").val() <= 0) {
        $("#_error-materia").text("Devi selezionare una categoria");
        $("#_error-materia-part").show();
        return;
    }
    if ($("#classe").val() <= 0) {
        $("#_error-materia").text("Devi selezionare una classe");
        $("#_error-materia-part").show();
        return;
    }
    if ($("#numero_ore").val() <= 0) {
        $("#_error-materia").text("Il numero di ore non può essere 0");
        $("#_error-materia-part").show();
        return;
    }

    // se tutto bene nasconde il messaggio di errore e prosegue nel save
    $("#_error-materia-part").hide();

    // controlla la lista di studenti segnati presenti
    var studentiDaModificareIdList = [];
    $('#studenti_table tbody tr').each(function () {
        var row = $(this);
        var presenteCheckbox = row.find('input[type="checkbox"]');
        var presenteOriginal = presenteCheckbox.prop('defaultChecked');
        var presenteCorrente = presenteCheckbox.prop('checked');
        var id = row.children().eq(0).text();
        if (presenteCorrente != presenteOriginal) {
            studentiDaModificareIdList.push(id);
        }
    });

    if ($("#hidden_lista_classi").val() == "testo") // se la classe è una casella di testo
    {
        if ($('#hidden_sezione_online_clil').val() == 'true') {
            $.post("sportelloAggiorna.php", {
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
                classe: $("#classe").val(),
                classe_id: 0,
                cancellato: $("#cancellato").is(':checked') ? 1 : 0,
                firmato: $("#firmato").is(':checked') ? 1 : 0,
                online: $("#online").is(':checked') ? 1 : 0,
                clil: $("#clil").is(':checked') ? 1 : 0,
                orientamento: $("#orientamento").is(':checked') ? 1 : 0,
                studentiDaModificareIdList: JSON.stringify(studentiDaModificareIdList),
            }, function (data, status) {
                $("#sportello_modal").modal("hide");
                sportelloReadRecords();
            });
        }
        else {
            $.post("sportelloAggiorna.php", {
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
                classe: $("#classe").val(),
                classe_id: 0,
                cancellato: $("#cancellato").is(':checked') ? 1 : 0,
                firmato: $("#firmato").is(':checked') ? 1 : 0,
                online: 0,
                clil: 0,
                orientamento: 0,
                studentiDaModificareIdList: JSON.stringify(studentiDaModificareIdList),
            }, function (data, status) {
                $("#sportello_modal").modal("hide");
                sportelloReadRecords();
            });
        }
    }
    else {
        if ($('#hidden_sezione_online_clil').val() == 'true') {
            $.post("sportelloAggiorna.php", {
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
                classe: "",
                classe_id: $("#classe").val(),
                cancellato: $("#cancellato").is(':checked') ? 1 : 0,
                firmato: $("#firmato").is(':checked') ? 1 : 0,
                online: $("#online").is(':checked') ? 1 : 0,
                clil: $("#clil").is(':checked') ? 1 : 0,
                orientamento: $("#orientamento").is(':checked') ? 1 : 0,
                studentiDaModificareIdList: JSON.stringify(studentiDaModificareIdList),
            }, function (data, status) {
                $("#sportello_modal").modal("hide");
                sportelloReadRecords();
            });
        }
        else {
            $.post("sportelloAggiorna.php", {
                id: $("#hidden_sportello_id").val(),
                data: getDbDateFromPickrId("#data"),
                ora: $("#ora").val(),
                docente_id: $("docente").val(),
                materia_id: $("#materia").val(),
                categoria_id: $("#categoria").val(),
                numero_ore: $("#numero_ore").val(),
                argomento: $("#argomento").val(),
                luogo: $("#luogo").val(),
                max_iscrizioni: $("#max_iscrizioni").val(),
                classe: "",
                classe_id: $("#classe").val(),
                cancellato: $("#cancellato").is(':checked') ? 1 : 0,
                firmato: $("#firmato").is(':checked') ? 1 : 0,
                online: 0,
                clil: 0,
                orientamento: 0,
                studentiDaModificareIdList: JSON.stringify(studentiDaModificareIdList),
            }, function (data, status) {
                $("#sportello_modal").modal("hide");
                sportelloReadRecords();
            });
        }
    }
}

function confermaCancellato() {
    if ($("#cancellato").is(':checked')) {
        if ($("#hidden_numero_studenti_iscritti").val() == 0) {
            let result = confirm("Non ci sono studenti iscritti, sei sicuro di voler cancellare lo sportello?\nPuoi eventualmente riprogrammare lo sportello ad un'altra data con una richiesta all'amministratore.");
            if (result === false) {
                $("#cancellato").prop('checked', false);
            }
        }
        else {
            let result = confirm("Sei sicuro di voler cancellare lo sportello? Agli studenti eventualmente iscritti arriverà un avviso di annullamento dello sportello.");
            if (result === false) {
                $("#cancellato").prop('checked', false);
            }
        }
    }
}

function confermaFirmato() {
    if ($("#firmato").is(':checked')) {
        if ($("#hidden_numero_studenti_iscritti").val() == 0) {
            alert("Non puoi firmare uno sportello a cui non ci sono studenti iscritti!");
            $("#firmato").prop('checked', false);
        }
        else {
            var conferma = confirm("Una volta firmato lo sportello non potrai più modificare nulla. Sei sicuro?");
            if (!conferma) {
                $("#firmato").prop('checked', false);
            }
        }

    }
}

function sportelloGetDetails(sportello_id, modificabile, sportello_n_studenti, categoria) {
    $("#hidden_sportello_id").val(sportello_id);
    //    $("#hidden_numero_studenti_iscritti").val(10);
    $("#hidden_numero_studenti_iscritti").val(sportello_n_studenti);

    if (sportello_id > 0) {
        $.post("../docente/sportelloReadDetails.php", {
            sportello_id: sportello_id
        }, function (data, status) {
            //console.log(data);
            var sportello = data;
            var cancellato = sportello.sportello_cancellato != 0 && sportello.sportello_cancellato != null;
            var firmato = sportello.sportello_firmato != 0 && sportello.sportello_firmato != null;
            setDbDateToPickr(data_pickr, sportello.sportello_data);
            $("#ora").val(sportello.sportello_ora);
            $("#docente").val(sportello.docente_cognome + ' ' + sportello.docente_nome);
            $('#materia').selectpicker('val', sportello.materia_id);
            $('#categoria').selectpicker('val', sportello.categoria_id);
            $("#numero_ore").val(sportello.sportello_numero_ore);
            $("#argomento").val(sportello.sportello_argomento);
            $("#luogo").val(sportello.sportello_luogo);
            if ($('#hidden_lista_classi').val() == 'testo') {
                $("#classe").val(sportello.classe);
            }
            else {
                $('#classe').selectpicker('val', sportello.classe_id);
            }
            $("#max_iscrizioni").val(sportello.sportello_max_iscrizioni);
            $("#cancellato").prop('checked', sportello.sportello_cancellato != 0 && sportello.sportello_cancellato != null);
            $("#firmato").prop('checked', sportello.sportello_firmato != 0 && sportello.sportello_firmato != null);

            if ($('#hidden_sezione_online_clil').val() == 'true') {
                $("#online").prop('checked', sportello.sportello_online != 0 && sportello.sportello_online != null);
                $("#clil").prop('checked', sportello.sportello_clil != 0 && sportello.sportello_clil != null);
                $("#orientamento").prop('checked', sportello.sportello_orientamento != 0 && sportello.sportello_orientamento != null);
                $("#online").prop('disabled', $('#hidden_modifica_sportelli').val());
                $("#clil").prop('disabled', $('#hidden_modifica_sportelli').val());
                $("#orientamento").prop('disabled', $('#hidden_modifica_sportelli').val());
            }

            if (!modificabile) {
                $("#ora").prop('disabled', true);
                $("#data").prop('disabled', true);
                $("#max_iscrizioni").prop('disabled', true);
                $("#docente").prop('disabled', true);
                $("#materia").prop('disabled', true);
                $("#categoria").prop('disabled', true);
                $("#numero_ore").prop('disabled', true);
                $("#argomento").prop('disabled', true);
                $("#luogo").prop('disabled', true);
                $("#classe").prop('disabled', true);
                $("#firmato").prop('disabled', true);
                $("#cancellato").prop('disabled', true);
                $("#button_save").hide();
            }
            else {
                $("#ora").prop('disabled', $('#hidden_modifica_sportelli').val());
                $("#data").prop('disabled', $('#hidden_modifica_sportelli').val());
                $("#max_iscrizioni").prop('disabled', $('#hidden_modifica_sportelli').val());
                $("#docente").prop('disabled', $('#hidden_modifica_sportelli').val());
                $("#materia").prop('disabled', $('#hidden_modifica_sportelli').val());
                $("#categoria").prop('disabled', $('#hidden_modifica_sportelli').val());
                $("#numero_ore").prop('disabled', $('#hidden_modifica_sportelli').val());
                $("#argomento").prop('disabled', $('#hidden_modifica_sportelli').val());
                $("#luogo").prop('disabled', $('#hidden_modifica_sportelli').val());
                $("#classe").prop('disabled', $('#hidden_modifica_sportelli').val());
                $("#firmato").prop('disabled', false);
                $("#cancellato").prop('disabled', false);
                $("#button_save").show();
            }

            // abilita la firma se non firmato
            if (!firmato && !cancellato) {
                $("#firma_sportello_button_id").show();
            } else {
                $("#firma_sportello_button_id").hide();
            }

            $('#studenti_table tbody').empty();
            var markup = '';
            // cicla su tutti gli studenti
            // console.log(sportello.studenti);
            sportello.studenti.forEach(function (studenti) {
                // console.log(studenti);
                markup = markup +
                    "<tr>" +
                    "<td>" + studenti.sportello_studente_id + "</td>" +
                    "<td>" + studenti.sportello_studente_presente + "</td>" +
                    "<td style=\"text-align: left; vertical-align: middle;\">" + studenti.studente_cognome + " " + studenti.studente_nome + "</td>" +
                    "<td style=\"text-align: left; vertical-align: middle;\">" + studenti.sportello_studente_argomento + "</td>" +
                    "<td style=\"text-align: center; vertical-align: middle;\">" +
                    "<input type=\"checkbox\" name=\"query_myTextEditBox\"" +
                    ((studenti.sportello_studente_presente == 0 || studenti.sportello_studente_presente == null) ? "" : " checked") +
                    "></td>" +
                    "</tr>";
            });
            $('#studenti_table > tbody:last-child').append(markup);
            $('#studenti_table td:nth-child(1),#studenti_table th:nth-child(1),#studenti_table td:nth-child(2),#studenti_table th:nth-child(2)').hide(); // nasconde la prima colonna con l'id
        });
    } else {
        data_pickr.setDate(Date.today().toString('d/M/yyyy'));
        $("#ora").val("14");
        $('#docente').val($("#hidden_docente_cognome_nome").val());
        $('#docente').selectpicker('refresh');
        $('#materia').val("0");
        $('#materia').selectpicker('refresh');
        $('#categoria').val("0");
        $('#categoria').selectpicker('refresh');
        $("#numero_ore").val("0");
        $("#argomento").val("");
        $("#luogo").val("");
        $('#classe').val("0");
        $("#classe").selectpicker('refresh');
        $("#max_iscrizioni").val($("#hidden_max_iscrizioni_default").val());
        $("#cancellato").prop('checked', false);
        $("#firmato").prop('checked', false);

        if ($('#hidden_sezione_online_clil').val() == 'true') {
            $("#onine").prop('checked', false);
            $("#clil").prop('checked', false);
            $("#orientamento").prop('checked', false);
        }
    }
    $("#_error-materia-part").hide();
    $("#sportello_modal").modal("show");
}

$(document).ready(function () {
    data_pickr = flatpickr("#data", {
        locale: {
            firstDayOfWeek: 1
        },
        dateFormat: 'j/n/Y'
    });

    sportelloReadRecords();
});
