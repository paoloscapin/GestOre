/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function bonusDocentiReadRecords() {
	$.get("bonusDocentiReadRecords.php", {}, function (data, status) {
		console.log(data);
		$(".bonus_docenti_records_content").html(data);
		$('#bonus_docenti_table td:nth-child(1),th:nth-child(1)').hide(); // nasconde la prima colonna con l'id
	});
}

$(document).ready(function () {
	bonusDocentiReadRecords();
});
