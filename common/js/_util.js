/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var warning = '<span class="glyphicon glyphicon-warning-sign text-error"></span>';
var okSymbol = '&ensp;<span class="glyphicon glyphicon-ok text-success"></span>';

function getHtmlNum(value) {
	return '&emsp;' + ((value >= 10) ? trasformaFloatInStringa(value) : '&ensp;' + trasformaFloatInStringa(value));
}

function getHtmlNumAndPrevisteVisual(value, total) {
	var numString = (value >= 10) ? trasformaFloatInStringa(value) : '&ensp;' + trasformaFloatInStringa(value);
	var diff = total - value;
	if (diff > 0) {
		numString += '&ensp;<span class="label label-warning">- '+ trasformaFloatInStringa(diff) +'</span>';
	} else if (diff < 0) {
			numString += '&ensp;<span class="label label-danger">+ '+ trasformaFloatInStringa(-diff) +'</span>';
	} else {
		numString += okSymbol;
	}
	return '&emsp;' + numString;
}

function getHtmlNumAndFatteVisual(value, total) {
	var numString = (value >= 10) ? trasformaFloatInStringa(value) : '&ensp;' + trasformaFloatInStringa(value);
	var diff = total - value;
	if (diff > 0) {
		numString += '&ensp;<span class="label label-warning">- '+ trasformaFloatInStringa(diff) +'</span>';
	} else if (diff < 0) {
			numString += '&ensp;<span class="label label-danger">+ '+ trasformaFloatInStringa(-diff) +'</span>';
	} else {
		numString += okSymbol;
	}
	return '&emsp;' + numString;
}

function getHtmlNumAndFacoltativeVisual(value, total) {
	return '&emsp;' + ((value >= 10) ? trasformaFloatInStringa(value) : '&ensp;' + trasformaFloatInStringa(value));
}

function getHtmlNumAndFatte80Visual(value, total) {
	return '&emsp;' + ((value >= 10) ? trasformaFloatInStringa(value) : '&ensp;' + trasformaFloatInStringa(value));
}

function number_format (number, decimals, decPoint, thousandsSep) {
	number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
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
	s = (prec ? toFixedFix(n, prec).toString() : '' + Math.round(n)).split('.');
	if (s[0].length > 3) {
	  s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep)
	}
	if ((s[1] || '').length < prec) {
	  s[1] = s[1] || ''
	  s[1] += new Array(prec - s[1].length + 1).join('0')
	}
	return s.join(dec)
}

// questa versione non mette il separatore delle migliaia
function number_format_simple (number, decimals, decPoint, thousandsSep) {
	return parseFloat(number).toFixed(decimals);
}
