/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */


function attribuiteReadRecords() {
	$.get("oreFatteReadAttribuite.php", {}, function (data, status) {
		$(".attribuite_records_content").html(data);
	});
}

function attribuiteGetDetails(id) {
    $("#hidden_attribuite_id").val(id);
    if (id > 0) {
        $.post("../common/readRecordDetails.php", {
                id: id,
                table: 'ore_previste_attivita'
            },
            function (data, status) {
                var record = JSON.parse(data);
                console.log(record);
                $('#attribuite_tipo_attivita').selectpicker('val', record.ore_previste_tipo_attivita_id);
                $("#attribuite_dettaglio").val(record.dettaglio);
                $("#attribuite_ore").val(record.ore);
            }
        );
    } else {
        $("#attribuite_tipo_attivita").selectpicker('val', 0);
        $("#attribuite_dettaglio").val("");
        $("#attribuite_ore").val("");
    }
	$("#attribuite_modal").modal("show");
}

function attribuiteSave() {
    $.post("attribuiteSave.php", {
        id: $("#hidden_attribuite_id").val(),
        tipo_attivita_id: $("#attribuite_tipo_attivita").val(),
        dettaglio: $("#attribuite_dettaglio").val(),
        ore: $("#attribuite_ore").val(),
    },
    function (data, status) {
        $("#attribuite_modal").modal("hide");
        attribuiteReadRecords();
        $("#attribuite_tipo_attivita").selectpicker('val', 0);
        $("#attribuite_dettaglio").val("");
        $("#attribuite_ore").val("");
        fuisAggiornaDocente();
    });
}
