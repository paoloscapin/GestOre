/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function storicoBonus() {
	var anno_id = $("#anno_select").val();
	window.open("storicoBonus.php" + "?anno_id=" + anno_id, '_blank');
}

function storicoFuis() {
	var anno_id = $("#anno_select").val();
	window.open("storicoFuis.php" + "?anno_id=" + anno_id, '_blank');
}


 $(document).ready(function () {
});
