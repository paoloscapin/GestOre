/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function attribuiteReadRecords() {
	$.get("oreFatteReadAttribuite.php", {}, function (data, status) {
		// console.log(data);
		data = JSON.parse(data);
		attribuiteOreFunzionali = data.attribuiteOreFunzionali;
		attribuiteOreConStudenti = data.attribuiteOreConStudenti;
		attribuiteClilOreFunzionali = data.attribuiteClilOreFunzionali;
		attribuiteClilOreConStudenti = data.attribuiteClilOreConStudenti;
		attribuiteOrientamentoOreFunzionali = data.attribuiteOrientamentoOreFunzionali;
		attribuiteOrientamentoOreConStudenti = data.attribuiteOrientamentoOreConStudenti;
		$(".attribuite_records_content").html(data.data);
	});
}

function attribuiteGetDetails(id) {
    $("#hidden_attribuite_id").val(id);
    if (id > 0) {
		$.post("../docente/previsteReadDetails.php", {
            attivita_id: id
        },
        function (dati, status) {
            // console.log(dati);
            var attivita = JSON.parse(dati);
            $('#attribuite_tipo_attivita').selectpicker('val', attivita.ore_previste_tipo_attivita_id);
            setOre('#attribuite_ore', attivita.ore);
            $("#attribuite_dettaglio").val(attivita.dettaglio);
            $("#attribuite_commento").val(attivita.commento);
        });
    } else {
        $("#attribuite_tipo_attivita").val('');
        $("#attribuite_tipo_attivita").selectpicker('val', 0);
        $("#attribuite_ore").val("");
        $("#attribuite_dettaglio").val("");
		if ($("#hidden_operatore").val() == 'dirigente') {
			var d = new Date();
			var strDate = d.getDate() + "/" + (d.getMonth()+1) + "/" + d.getFullYear();
			$("#attribuite_commento").val('Inserito da dirigente (' + strDate + ')');
		} else {
			$("#attribuite_commento").val('');
		}
    }
	if ($("#hidden_operatore").val() == 'dirigente') {
		$("#attribuite_commento-part").show();
	} else {
		$("#attribuite_commento-part").hide();
	}

	$("#_error-attribuite-part").hide();
	$("#attribuite_modal").modal("show");
}

function attribuiteSave() {
	if ($("#attribuite_tipo_attivita").val() <= 0) {
		$("#_error-attribuite").text("Devi selezionare un tipo di attività");
		$("#_error-attribuite-part").show();
		return;
	}
	$("#_error-attribuite-part").hide();

    $.post("attribuiteSave.php", {
        attribuite_attivita_id: $("#hidden_attribuite_id").val(),
        tipo_attivita_id: $("#attribuite_tipo_attivita").val(),
        dettaglio: $("#attribuite_dettaglio").val(),
        ore: $("#attribuite_ore").val(),
        commento: $("#attribuite_commento").val(),
		docente_id: $("#hidden_docente_id").val()
    },
    function (data, status) {
        $("#attribuite_modal").modal("hide");
        $("#attribuite_tipo_attivita").selectpicker('val', 0);
        $("#attribuite_ore").val("");
        $("#attribuite_dettaglio").val("");
        $("#attribuite_commento").val("");
        attribuiteReadRecords();
		oreDovuteReadRecords();
        fuisAggiornaDocente();
    });
}
