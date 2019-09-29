/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function ricalcolaTutti() {
	$.get("fuisDocentiCalcola.php", {
	},
	function (data, status) {
		// console.log(data);
		fuisDocentiReadRecords();
	});
}

function number_format (number, decimals, decPoint, thousandsSep) {
	  number = (number + '').replace(/[^0-9+\-Ee.]/g, '')
	  var n = !isFinite(+number) ? 0 : +number
	  var prec = !isFinite(+decimals) ? 0 : Math.abs(decimals)
	  var sep = (typeof thousandsSep === 'undefined') ? ',' : thousandsSep
	  var dec = (typeof decPoint === 'undefined') ? '.' : decPoint
	  var s = ''

	  var toFixedFix = function (n, prec) {
	    if (('' + n).indexOf('e') === -1) {
	      return +(Math.round(n + 'e+' + prec) + 'e-' + prec)
	    } else {
	      var arr = ('' + n).split('e')
	      var sig = ''
	      if (+arr[1] + prec > 0) {
	        sig = '+'
	      }
	      return (+(Math.round(+arr[0] + 'e' + sig + (+arr[1] + prec)) + 'e-' + prec)).toFixed(prec)
	    }
	  }

	  // @todo: for IE parseFloat(0.55).toFixed(0) = 0;
	  s = (prec ? toFixedFix(n, prec).toString() : '' + Math.round(n)).split('.')
	  if (s[0].length > 3) {
	    s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep)
	  }
	  if ((s[1] || '').length < prec) {
	    s[1] = s[1] || ''
	    s[1] += new Array(prec - s[1].length + 1).join('0')
	  }

	  return s.join(dec)
}

function getFromTable(table, item) {
	var content = table.find("." + item).text().trim().replace(/,/g, '');
	if (isNaN(content) || content.length == 0) {
		return 0;
	}
	return parseFloat(content);
}

function fuisDocentiReadRecords() {
	$.get("fuisDocentiReadRecords.php", {}, function (data, status) {
		// console.log(data);
		$(".fuis_docenti_records_content").html(data);
		$('#fuis_docenti_table td:nth-child(1),th:nth-child(1)').hide(); // nasconde la prima colonna con l'id
		// calcola il totale
		var totale_non_clil = 0;
		var totale_clil = 0;
		
		$("#fuis_docenti_table tr").each(function() {
			var viaggi = getFromTable($(this),"viaggi");
			var assegnato = getFromTable($(this),"assegnato");
			var sostituzioni = getFromTable($(this),"sostituzioni");
			var funzionale = getFromTable($(this),"funzionale");
			var con_studenti = getFromTable($(this),"con_studenti");
			var clil_funzionale = getFromTable($(this),"clil_funzionale");
			var clil_con_studenti = getFromTable($(this),"clil_con_studenti");
			// le ore non possono essere negative quando sommo gli importi: se lo sono le azzero
			var parziale_ore = sostituzioni + funzionale + con_studenti;
			if (parziale_ore < 0) {
				parziale_ore = 0;
			}
			var parziale_non_clil = viaggi + assegnato + parziale_ore;
			// console.log('parziale_ore=' + parziale_ore + ' parziale_non_clil=' + parziale_non_clil);
			var parziale_clil = clil_funzionale + clil_con_studenti;

			totale_non_clil += parziale_non_clil;
			totale_clil += parziale_clil;
		});
		
		$('#totale_fuis_docenti').text('Totale senza CLIL: ' + number_format(totale_non_clil,2));
		$('#totale_fuis_docenti').css("font-weight","Bold");
		$('#totale_fuis_docenti_clil').text('Totale CLIL: ' + number_format(totale_clil,2));
		$('#totale_fuis_docenti_clil').css("font-weight","Bold");
	});
}

$(document).ready(function () {
	ricalcolaTutti();
});
