/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function ricalcolaTutti() {
	$.get("oreAssegnateRicalcolaTutti.php", {
	},
	function (data, status) {
		// console.log(data);
	});
}

function reloadTable(ore_previste_tipo_attivita_id) {
	$.post("oreAssegnateReadList.php", {
			ore_previste_tipo_attivita_id: ore_previste_tipo_attivita_id
		},
		function (data, status) {
			// console.log(data);
			var oreArray = JSON.parse(data);
			// console.log(oreArray);
			var tableId = 'table_' + ore_previste_tipo_attivita_id;
			// svuota il tbody della tabella
			$('#' + tableId + ' tbody').empty();
			var markup = '';
			oreArray.forEach(function(ore) {
				if (ore.ore_previste_attivita_id !== null) {
					markup = markup + 
					"<tr>" +
					"<td style=\"display:none;\">" + ore.ore_previste_attivita_id + "</td>" +
					"<td>" + ore.docente_cognome + " " + ore.docente_nome + "</td>" +
					"<td>" + ore.ore_previste_attivita_dettaglio + "</td>" +
					"<td class=\"col-md-1 text-center\">" + ore.ore_previste_attivita_ore + "</td>" +
					"<td class=\"col-md-2 text-center\">" +
					"<div onclick=\"deleteOreAttivita(" + ore.ore_previste_attivita_id + ","+ ore.ore_previste_attivita_ore_previste_tipo_attivita_id + ",'" + ore.docente_cognome + "','" + ore.docente_nome + "')\" class=\"btn btn-danger btn-xs\"><span class=\"glyphicon glyphicon-trash\"></div>&nbsp;" +
					"</td>" +
					"</tr>";
				}
			});
			$('#' + tableId + ' > tbody:last-child').append(markup);
		}
	);
}

function addAttivita(tipo_attivita_id, nome, ore, ore_max) {
	$("#hidden_ore_previste_attivita_id").val(0);
	$("#hidden_ore_previste_tipo_attivita_id").val(tipo_attivita_id);
	$("#myModalLabel").text(nome);
	$("#add_new_record_modal").modal("show");
	
	// se sono specificate le ore, le inserisce e le rende read only
	if (ore !== 0) {
		$("#oreLabel").text("Ore");
		$("#ore").val(ore);
		$('#ore').attr('readonly', false);
		
	} else {
		// non e' certo readonly
		$('#ore').attr('readonly', false);
		
		// di default ci mette ore_max
		$("#ore").val(ore_max);
		
		// se specificato il max, lo scrive nella label
		if (ore === 0 && ore_max > 0) {
			$("#oreLabel").text("Ore (max " + ore_max + ")");
		} else {
			$("#oreLabel").text("Ore");
		}
	}
}

function oreAssegnateAddRecord() {
    $.post("oreAssegnateAddDetails.php", {
    		ore_previste_attivita_id: $("#hidden_ore_previste_attivita_id").val(),
    		dettaglio: $("#dettaglio").val(),
			ore: $("#ore").val(),
			ore_previste_tipo_attivita_id: $("#hidden_ore_previste_tipo_attivita_id").val(),
			docente_id: $("#docente_incaricato").val()
	    },
	    function (data, status) {
	        $("#add_new_record_modal").modal("hide");
	        reloadTable($("#hidden_ore_previste_tipo_attivita_id").val());
	    }
	);
}

function deleteOreAttivita(ore_previste_attivita_id, ore_previste_tipo_attivita_id, cognome, nome) {
    var conf = confirm("Sei sicuro di volere cancellare le ore del docente " + cognome + " " + nome + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
				id: ore_previste_attivita_id,
				table: 'ore_previste_attivita',
				name: "docente " + cognome + " " + nome + " ore_previste_attivita_id=" + ore_previste_attivita_id + " ore_previste_tipo_attivita_id=" + ore_previste_tipo_attivita_id
            },
            function (data, status) {
                reloadTable(ore_previste_tipo_attivita_id);
            }
        );
    }
}
