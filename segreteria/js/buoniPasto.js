/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function apriProspettoDocente() {
    var idDocente = document.getElementById('docente_select').value;
    if (idDocente > 0) {
        window.location.href = '../docente/attivita.php?docente_id=' + idDocente;
    }
}

function ricalcola() {
    // trova docente id, mese da e mese a
    mese_da = $("#mese_da").val();
    mese_a = $("#mese_a").val();
    docente_id = $("#docente_select-2").val();

    if (mese_da == '' || mese_a == '' || docente_id == '') {
		$(".records_content").html('');
    } else {
        $.get("buoniPastoReadRecords.php?docente_id=" + docente_id + "&mese_da=" + mese_da + "&mese_a=" + mese_a, {}, function (data, status) {
            $(".records_content").html(data);
        });
    }
}

function scarica() {
    // trova docente id, mese da e mese a
    mese_da = $("#mese_da").val();
    mese_a = $("#mese_a").val();
    docente_id = 0;

    if (mese_da == '' || mese_a == '') {
		$(".records_content").html('');
    } else {
    	window.open("buoniPastoExport.php?docente_id=" + docente_id + "&mese_da=" + mese_da + "&mese_a=" + mese_a, '_blank');
    }
}