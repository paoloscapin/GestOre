/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var soloNuovi=1;
var materia_filtro_id=0;

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
	$.get("sportelloReadRecords.php?ancheCancellati=true&soloNuovi=" + soloNuovi + "&materia_filtro_id=" + materia_filtro_id, {}, function (data, status) {
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
                sportelloReadRecords();
            }
        );
    }
}

function sportelloSave() {
    $.post("sportelloSave.php", {
        id: $("#hidden_sportello_id").val(),
		data: getDbDateFromPickrId("#data"),
        ora: $("#ora").val(),
        docente_id: $("#docente").val(),
		materia_id: $("#materia").val(),
        numero_ore: $("#numero_ore").val(),
		argomento: $("#argomento").val(),
		luogo: $("#commento").val(),
        classe: $("#classe").val(),
        cancellato: $("#cancellato").prop('checked'),
        firmato: $("#firmato").prop('checked')
    }, function (data, status) {
        $("#sportello_modal").modal("hide");
        sportelloReadRecords();
    });
}

function sportelloGetDetails(sportello_id) {
    $("#hidden_sportello_id").val(sportello_id);

    if (sportello_id > 0) {
        $.post("../docente/sportelloReadDetails.php", {
            sportello_id: sportello_id
        }, function (data, status) {
            var sportello = JSON.parse(data);
            setDbDateToPickr(data_pickr, sportello.sportello_data);
            $("#ora").val(sportello.sportello_ora);
            $('#docente').selectpicker('val', sportello.docente_id);
            $('#materia').selectpicker('val', sportello.materia_id);
            $("#numero_ore").val(sportello.sportello_numero_ore);
            $("#argomento").val(sportello.sportello_argomento);
            $("#luogo").val(sportello.sportello_luogo);
            $("#classe").val(sportello.sportello_classe);
            $("#cancellato").prop('checked', sportello.sportello_cancellato != 0 && sportello.sportello_cancellato != null);
            $("#firmato").prop('checked', sportello.sportello_firmato != 0 && sportello.sportello_firmato != null);
        });
    } else {
        data_pickr.setDate(Date.today().toString('d/M/yyyy'));
        $("#ora").val("14");
        $('#docente').val("0");
        $('#docente').selectpicker('refresh');
        $('#materia').val("0");
        $('#materia').selectpicker('refresh');
        $("#numero_ore").val("0");
        $("#argomento").val("");
        $("#luogo").val("");
        $("#classe").val("");
        $("#cancellato").prop('checked', false);
        $("#firmato").prop('checked', false);
}

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
    
    $("#materia_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        materia_filtro_id = this.value;
        sportelloReadRecords();
    });

});
