/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function agisciComeDocente(docente_id) {
  $.post("agisciComeDocente.php", { docente_id: docente_id }, null, "json")
    .done(function (res) {
      if (res && res.ok) {
        window.location.href = '../docente/index.php';
      } else {
        alert((res && res.msg) ? res.msg : "Impossibile impersonare il docente");
      }
    })
    .fail(function (xhr) {
      alert("Errore impersonificazione (" + xhr.status + ")");
    });
}

function agisciComeDocenteSelezionato() {
  agisciComeDocente($("#docente").val());
}	

$(document).ready(function () {
//	$('#docente').data('selectpicker').$button.focus();
	$('#docente').data('selectpicker').$searchbox.focus();
	$("#docente").on("changed.bs.select", 
			function(e, clickedIndex, newValue, oldValue) {
				var docente_id = this.value;
				agisciComeDocente(docente_id);
	});
});
