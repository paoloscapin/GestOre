/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function viaggioDiariaReadRecords() {
	$.get("../segreteria/viaggioDiariaReadRecords.php", {}, function (data, status) {
		$(".viaggioDiaria_records_content").html(data);
	});
}

$(document).ready(function () {
	viaggioDiariaReadRecords();
});
