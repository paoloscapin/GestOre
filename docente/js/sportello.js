/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var soloNuovi=1;
function setDbDateToPickr(pickr, data_str) {
	var data = Date.parseExact(data_str, 'yyyy-MM-dd');
	pickr.setDate(data);
}

function getDbDateFromPickrId(pickrId) {
	var data_str = $(pickrId).val();
	var data_date = Date.parseExact(data_str, 'd/M/yyyy');
	return data_date.toString('yyyy-MM-dd');
}

$('#soloNuoviCheckBox').change(function() {
    // this si riferisce al checkbox
    if (this.checked) {
		soloNuovi = 1;
    } else {
		soloNuovi = 0;
    }
    sportelloReadRecords();
});

function sportelloReadRecords() {
	$.get("../docente/sportelloReadRecords.php?ancheCancellati=true&soloNuovi=" + soloNuovi, {}, function (data, status) {
        $(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
	});
}

function sportelloSave() {
    var studentiDaModificareIdList = [];
    $('#studenti_table tbody tr').each(function() {
        var row = $(this);
        var presenteCheckbox = row.find('input[type="checkbox"]');
        var presenteOriginal = presenteCheckbox.prop('defaultChecked');
        var presenteCorrente = presenteCheckbox.prop('checked');
        var id = row.children().eq(0).text();
        if (presenteCorrente != presenteOriginal) {
            studentiDaModificareIdList.push(id);
        }
    });

    $.post("sportelloAggiorna.php", {
        id: $("#hidden_sportello_id").val(),
        firmato: $("#firmato").is(':checked')? 1: 0,
        studentiDaModificareIdList: JSON.stringify(studentiDaModificareIdList),
    }, function (data, status) {
        $("#sportello_modal").modal("hide");
        sportelloReadRecords();
    });
}

function sportelloFirma() {
    if ($("#firmato").is(':checked')) {
        // gia' firmato, non dovrebbe succedere0
        return;
    }
    // setta che è firmato
    $("#firmato").prop('checked', true);

    // lo salva
    $.post("sportelloAggiorna.php", {
        id: $("#hidden_sportello_id").val(),
        firmato: $("#firmato").is(':checked')? 1: 0,
        studentiDaModificareIdList: JSON.stringify([]),
    }, function (data, status) {
    });
}

function sportelloGetDetails(sportello_id) {
    $("#hidden_sportello_id").val(sportello_id);
    $.post("../docente/sportelloReadDetails.php", {
        sportello_id: sportello_id
    }, function (data, status) {
        // console.log(data);
        var sportello = JSON.parse(data);
        var cancellato = sportello.sportello_cancellato != 0 && sportello.sportello_cancellato != null;
        var firmato = sportello.sportello_firmato != 0 && sportello.sportello_firmato != null;
    
        $("#data").val(sportello.sportello_data);
        $("#ora").val(sportello.sportello_ora);
        $("#docente").val(sportello.docente_nome + ' ' + sportello.docente_nome);
        $("#materia").val(sportello.materia_nome);
        $("#numero_ore").val(sportello.sportello_numero_ore);
        $("#argomento").val(sportello.sportello_argomento);
        $("#luogo").val(sportello.sportello_luogo);
        $("#classe").val(sportello.sportello_classe);
        $("#cancellato").prop('checked', cancellato);
        $("#firmato").prop('checked', firmato);
        // abilita la firma se non firmato
        if (! firmato && ! cancellato) {
            console.log('show firmato='+firmato+ ' cancellato='+cancellato);
            $("#firma_sportello_button_id").show();
        } else {
            console.log('hide firmato='+firmato+ ' cancellato='+cancellato);
            $("#firma_sportello_button_id").hide();
        }

        $('#studenti_table tbody').empty();
        var markup = '';
        // cicla su tutti gli studenti
        console.log(sportello.studenti);
        sportello.studenti.forEach(function(studenti) {
            console.log(studenti);
            markup = markup + 
                    "<tr>" +
                    "<td>" + studenti.sportello_studente_id + "</td>" +
                    "<td>" + studenti.sportello_studente_presente + "</td>" +
                    "<td style=\"text-align: left; vertical-align: middle;\">" + studenti.studente_cognome + " " + studenti.studente_nome + "</td>" +
                    "<td style=\"text-align: left; vertical-align: middle;\">" + studenti.sportello_studente_argomento + "</td>" +
                    "<td style=\"text-align: center; vertical-align: middle;\">" +
                        "<input type=\"checkbox\" name=\"query_myTextEditBox\"" +
                        ((studenti.sportello_studente_presente == 0 || studenti.sportello_studente_presente == null) ? "" : " checked" ) +
                    "></td>" +
            "</tr>";
        });
        $('#studenti_table > tbody:last-child').append(markup);
        $('#studenti_table td:nth-child(1),#studenti_table th:nth-child(1),#studenti_table td:nth-child(2),#studenti_table th:nth-child(2)').hide(); // nasconde la prima colonna con l'id
});
    $("#sportello_modal").modal("show");
}

$(document).ready(function () {
	sportelloReadRecords();
});
