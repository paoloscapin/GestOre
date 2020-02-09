/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function setDbDateToPickr(pickr, data_str) {
	var data = Date.parseExact(data_str, 'yyyy-MM-dd');
	pickr.setDate(data);
}

function getDbDateFromPickrId(pickrId) {
	var data_str = $(pickrId).val();
	var data_date = Date.parseExact(data_str, 'd/M/yyyy');
	return data_date.toString('yyyy-MM-dd');
}

function sportelloReadRecords() {
	$.get("../docente/sportelloReadRecords.php?ancheCancellati=true", {}, function (data, status) {
		$(".records_content").html(data);
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

function sportelloGetDetails(sportello_id) {
    $("#hidden_sportello_id").val(sportello_id);
    $.post("../docente/sportelloReadDetails.php", {
        sportello_id: sportello_id
    }, function (data, status) {
        // console.log(data);
        var sportello = JSON.parse(data);
        $("#data").val(sportello.sportello_data);
        $("#ora").val(sportello.sportello_ora);
        $("#docente").val(sportello.docente_nome + ' ' + sportello.docente_nome);
        $("#materia").val(sportello.materia_nome);
        $("#numero_ore").val(sportello.sportello_numero_ore);
        $("#argomento").val(sportello.sportello_argomento);
        $("#luogo").val(sportello.sportello_luogo);
        $("#classe").val(sportello.sportello_classe);
        $("#cancellato").prop('checked', sportello.sportello_cancellato != 0 && sportello.sportello_cancellato != null);
        $("#firmato").prop('checked', sportello.sportello_firmato != 0 && sportello.sportello_firmato != null);

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
                    "<td>" + studenti.studente_cognome + " " + studenti.studente_nome + "</td>" +
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
