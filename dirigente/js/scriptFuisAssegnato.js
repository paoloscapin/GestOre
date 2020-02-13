/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function reloadTable(fuis_assegnato_tipo_id) {
	$.post("fuisAssegnatoReadList.php", {
		fuis_assegnato_tipo_id: fuis_assegnato_tipo_id
		},
		function (data, status) {
			//console.log(data);
			var fuisArray = JSON.parse(data);
			// console.log(fuisArray);
			var tableId = 'table_' + fuis_assegnato_tipo_id;
			var totaleId = 'totale_' + fuis_assegnato_tipo_id; 
			// svuota il tbody della tabella
			$('#' + tableId + ' tbody').empty();
			var markup = '';
			var totale = 0;
			fuisArray.forEach(function(fuis) {
				totale = totale + parseFloat(fuis.fuis_assegnato_importo);
				// console.log("importo=" + fuis.fuis_assegnato_importo + " totale=" + totale);
				markup = markup + 
					"<tr>" +
					"<td style=\"display:none;\">" + fuis.fuis_assegnato_id + "</td>" +
					"<td>" + fuis.docente_cognome + " " + fuis.docente_nome + "</td>" +
					"<td class=\"col-md-2 text-right\">" + parseFloat(fuis.fuis_assegnato_importo).toFixed(2) + "</td>" +
					"<td class=\"col-md-2 text-center\">" +
					"<div onclick=\"editFuisAssegnato(" + fuis.fuis_assegnato_id + ","+ fuis.fuis_assegnato_fuis_assegnato_tipo_id + "," + 1 + ","+ fuis.fuis_assegnato_importo + "," + fuis.docente_id + ")\" class=\"btn btn-success btn-xs\"><span class=\"glyphicon glyphicon-pencil\"></div>&nbsp;" +
					"<div onclick=\"deleteFuisAssegnato(" + fuis.fuis_assegnato_id + ","+ fuis.fuis_assegnato_fuis_assegnato_tipo_id +")\" class=\"btn btn-danger btn-xs\"><span class=\"glyphicon glyphicon-trash\"></div>&nbsp;" +
					"</td>" +
					"</tr>";
			});
			$('#' + tableId + ' > tbody:last-child').append(markup);
			// console.log("totale=" + totale);
			$('#' + totaleId).text('Totale ' + Math.round(totale));
			$('#' + totaleId).css("font-weight","Bold");
		}
	);
}

function editFuisAssegnato(fuis_assegnato_id, fuis_assegnato_tipo_id, nome, importo, docente_id) {
	$("#hidden_fuis_assegnato_id").val(fuis_assegnato_id);
	$("#hidden_fuis_assegnato_tipo_id").val(fuis_assegnato_tipo_id);
	$("#myModalLabel").text(nome);
	$("#importo").val(importo);
	$('#docente_incaricato').selectpicker('val', docente_id);
	$("#add_new_record_modal").modal("show");
}

function fuisAssegnatoSaveRecord() {
    $.post("fuisAssegnatoUpdateDetails.php", {
    		fuis_assegnato_id: $("#hidden_fuis_assegnato_id").val(),
    		importo: $("#importo").val(),
    		fuis_assegnato_tipo_id: $("#hidden_fuis_assegnato_tipo_id").val(),
			docente_id: $("#docente_incaricato").val()
	    },
	    function (data, status) {
	        $("#add_new_record_modal").modal("hide");
	        reloadTable($("#hidden_fuis_assegnato_tipo_id").val());
	    }
	);
}

function deleteFuisAssegnato(fuis_assegnato_id, fuis_assegnato_tipo_id) {
    var conf = confirm("Sei sicuro di volere cancellare questo fuis assegnato ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
				id: fuis_assegnato_id,
				table: 'fuis_assegnato',
				name: "fuis assegnato importo=" + importo
            },
            function (data, status) {
                reloadTable($("#hidden_fuis_assegnato_tipo_id").val());
            }
        );
    }
}

$(document).ready(function () {
	$.post("fuisAssegnatoGetIdList.php", {
		},
		function (data, status) {
		// console.log(data);
			var idArray = JSON.parse(data);
			idArray.forEach(function(id) {
				reloadTable(id.id);
			});
		});
});
