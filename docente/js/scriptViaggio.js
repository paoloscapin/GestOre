/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var ancheChiusi = 0;

$('#ancheChiusiCheckBox').change(function() {
    // this si riferisce al checkbox
    if (this.checked) {
		ancheChiusi = 0;
    } else {
		ancheChiusi = 1;
    }
	viaggioReadRecords();
});

// Read records
function viaggioReadRecords() {
	$.get("viaggioReadRecords.php?ancheChiusi=" + ancheChiusi, {}, function (data, status) {
		$(".records_content").html(data);
	});
}

// Get details for update
function viaggioGetDetails(id) {
	// console.log('viaggioGetDetails id='+id);
	$("#hidden_viaggio_id").val(id);

    $.post("../common/readRecordDetails.php", {
        id: id,
        table: 'viaggio'
    },
    function (data, status) {
        var viaggio = JSON.parse(data);

		$("#update_viaggio_destinazione").val(viaggio.destinazione);
		$("#update_viaggio_classe").val(viaggio.classe);
		$("#update_viaggio_data_partenza").val(new Date(viaggio.data_partenza).toLocaleDateString('it-IT', {day: 'numeric',month: 'short',year: 'numeric'}));
		$("#update_viaggio_data_rientro").val(new Date(viaggio.data_rientro).toLocaleDateString('it-IT', {day: 'numeric',month: 'short',year: 'numeric'}));
    });

	$("#update_viaggio_modal").modal("show");
}

function viaggioAccetta(viaggio_id) {
    $.post("viaggioCambiaStato.php", {
    		viaggio_id: viaggio_id,
    		nuovo_stato: "accettato"
        },
        function (data, status) {
        	viaggioReadRecords();
        }
    );
}

function viaggioInoltra() {
	var viaggio_id = $("#hidden_viaggio_id").val();
	var ore = $("#update_viaggio_ore_richieste").val();
	var diaria = $("#update_viaggio_diaria").prop('checked');
	if (diaria == null) {
		diaria = false;
	}

	$.post("viaggioInoltra.php", {
		viaggio_id: viaggio_id,
		ore: ore,
		diaria: diaria
	},
	function (data, status) {
		$("#update_viaggio_modal").modal("hide");
		viaggioReadRecords();
	});
}

$(document).ready(function () {
    viaggioReadRecords();
});
