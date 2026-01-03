/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

/* =========================
   Parsing numeri (US/IT)
   ========================= */

function parseNumberSmart(text) {
	if (!text) return 0;

	let t = text.toString().trim();
	if (t === '') return 0;

	// rimuovi euro e spazi
	t = t.replace(/€/g, '').replace(/\s+/g, '');

	// tieni solo cifre, punto, virgola e segno
	t = t.replace(/[^0-9.,\-]/g, '');

	const hasComma = t.indexOf(',') !== -1;
	const hasDot = t.indexOf('.') !== -1;

	if (hasComma && hasDot) {
		const lastComma = t.lastIndexOf(',');
		const lastDot = t.lastIndexOf('.');

		if (lastDot > lastComma) {
			// US: 1,158.00
			t = t.replace(/,/g, '');
		} else {
			// IT: 1.158,00
			t = t.replace(/\./g, '').replace(',', '.');
		}
	} else if (hasComma) {
		if (/,\d{1,2}$/.test(t)) {
			t = t.replace(',', '.');
		} else {
			t = t.replace(/,/g, '');
		}
	} else if (hasDot) {
		if (!/\.\d{1,2}$/.test(t)) {
			t = t.replace(/\./g, '');
		}
	}

	const n = parseFloat(t);
	return isNaN(n) ? 0 : n;
}

function formatIT(n, decimals) {
	return n.toLocaleString('it-IT', {
		minimumFractionDigits: decimals,
		maximumFractionDigits: decimals
	});
}

function formatEuroIT(n) {
	return formatIT(n, 2) + ' €';
}

/* =========================
   Conversione visuale numeri + €
   ========================= */

function convertTableNumbersToITWithEuro() {
	$("#bonus_docenti_table tbody td.funzionale, #bonus_docenti_table tbody td.totale").each(function () {
		const raw = $(this).text();
		const trimmed = raw ? raw.toString().trim() : '';
		if (trimmed === '') return;

		const num = parseNumberSmart(trimmed);
		$(this).text(formatEuroIT(num));
	});
}

/* =========================
   Totale + massimo docente (con link)
   ========================= */

function aggiornaTotaleERecord() {
	let totale = 0;

	let maxVal = -Infinity;
	let maxDocenteText = '';
	let maxDocenteHref = '';

	$("#bonus_docenti_table tbody tr").each(function () {
		// link docente
		const $a = $(this).find("td:nth-child(2) a");
		let docenteText = '';
		let docenteHref = '';

		if ($a.length) {
			docenteText = $a.text();
			docenteHref = $a.attr("href") || '';
		} else {
			docenteText = $(this).find("td:nth-child(2)").text();
		}

		docenteText = (docenteText || '').replace(/\s+/g, ' ').trim();

		// valore "da Pagare"
		const valText = $(this).find("td.totale").text();
		const val = parseNumberSmart(valText);

		totale += val;

		if (val > maxVal) {
			maxVal = val;
			maxDocenteText = docenteText;
			maxDocenteHref = docenteHref;
		}
	});

	if (!isFinite(maxVal)) {
		maxVal = 0;
		maxDocenteText = '';
		maxDocenteHref = '';
	}

	let html = '<strong>Totale da pagare:</strong> ' + formatEuroIT(totale);

	if (maxDocenteText !== '') {
		let docenteHtml = maxDocenteText;
		if (maxDocenteHref !== '') {
			// stesso comportamento del resto: usa la modalità apertura tab impostata dalla pagina (target)
			// se vuoi forzare _blank, metti target="_blank"
			docenteHtml = '<a href="' + maxDocenteHref + '">' + maxDocenteText + '</a>';
		}

		html += '&emsp;|&emsp;<strong>Più alto:</strong> ' + docenteHtml + ' (' + formatEuroIT(maxVal) + ')';
	}

	$("#totale_bonus_docenti").html(html);
}

/* =========================
   Lettura tabella docenti
   ========================= */

function bonusDocentiReadRecords() {
	let anno = $("#anno_scolastico_select").val();

	$.get("bonusDocentiReadRecords.php", {
		anno_scolastico_id: anno
	}, function (data, status) {
		$(".bonus_docenti_records_content").html(data);

		// nasconde la prima colonna con l'id
		$('#bonus_docenti_table td:nth-child(1),th:nth-child(1)').hide();

		// formatta numeri nella tabella (IT + €)
		convertTableNumbersToITWithEuro();

		// aggiorna totale + docente massimo (con link)
		aggiornaTotaleERecord();
	});
}

/* =========================
   Init pagina
   ========================= */

$(document).ready(function () {
	bonusDocentiReadRecords();

	$("#anno_scolastico_select").change(function () {
		bonusDocentiReadRecords();
	});
});
