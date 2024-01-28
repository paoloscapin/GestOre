/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function oreDovuteReadRecords() {
	oreFatteReloadTables(true);
}

//Read records on page load
$(document).ready(function () {
    oreDovuteReadRecords();
});
