/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function parseNumberSmart(text) {
	if (!text) return 0;

	let t = text.toString().trim();
	if (t === '') return 0;

	t = t.replace(/€/g, '').replace(/\s+/g, '');
	t = t.replace(/[^0-9.,\-]/g, '');

	const hasComma = t.indexOf(',') !== -1;
	const hasDot = t.indexOf('.') !== -1;

	if (hasComma && hasDot) {
		const lastComma = t.lastIndexOf(',');
		const lastDot = t.lastIndexOf('.');
		if (lastDot > lastComma) {
			t = t.replace(/,/g, ''); // US
		} else {
			t = t.replace(/\./g, '').replace(',', '.'); // IT
		}
	} else if (hasComma) {
		if (/,\d{1,2}$/.test(t)) t = t.replace(',', '.');
		else t = t.replace(/,/g, '');
	} else if (hasDot) {
		if (!/\.\d{1,2}$/.test(t)) t = t.replace(/\./g, '');
	}

	const n = parseFloat(t);
	return isNaN(n) ? 0 : n;
}

function formatEuroIT(n) {
	return n.toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
}

/**
 * FIX: evita il raddoppio /GestOre/dirigente/GestOre/dirigente/...
 * Restituisce sempre un pathname assoluto (inizia con /GestOre/dirigente/...)
 * quando l'href originale è relativo.
 */
function addOrReplaceQueryParam(href, key, value) {
	if (!href) return href;

	try {
		// Base = URL corrente (con directory), così un href relativo viene risolto correttamente
		const u = new URL(href, window.location.href);
		u.searchParams.set(key, value);

		// Se href originale è assoluto, restituisci assoluto
		if (/^https?:\/\//i.test(href)) return u.toString();

		// Se href originale è relativo, restituisci un path ASSOLUTO (con slash iniziale)
		return u.pathname + u.search + u.hash;
	} catch (e) {
		return href;
	}
}

function convertTableNumbersToITWithEuro() {
	$("#bonus_docenti_table tbody td.funzionale, #bonus_docenti_table tbody td.totale").each(function () {
		const raw = $(this).text();
		const trimmed = raw ? raw.toString().trim() : '';
		if (trimmed === '') return;
		const num = parseNumberSmart(trimmed);
		$(this).text(formatEuroIT(num));
	});
}

function aggiornaTotaleERecord() {
	let totale = 0;

	let maxVal = -Infinity;
	let maxDocenteText = '';
	let maxDocenteHref = '';

	$("#bonus_docenti_table tbody tr").each(function () {
		const $a = $(this).find("td:nth-child(2) a");
		let docenteText = $a.length ? $a.text() : $(this).find("td:nth-child(2)").text();
		let docenteHref = $a.length ? ($a.attr("href") || '') : '';

		docenteText = (docenteText || '').replace(/\s+/g, ' ').trim();

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
		if (maxDocenteHref) {
			docenteHtml = '<a href="' + maxDocenteHref + '">' + maxDocenteText + '</a>';
		}
		html += '&emsp;|&emsp;<strong>Più alto:</strong> ' + docenteHtml + ' (' + formatEuroIT(maxVal) + ')';
	}

	$("#totale_bonus_docenti").html(html);
}

function aggiornaLinkDocentiConAnno() {
	const anno = $("#anno_scolastico_select").val();

	$("#bonus_docenti_table tbody td:nth-child(2) a").each(function () {
		const href = $(this).attr("href");
		$(this).attr("href", addOrReplaceQueryParam(href, "anno_scolastico_id", anno));
	});
}

function aggiornaLinkGestisciCriteri() {
	const anno = $("#anno_scolastico_select").val();
	const $btn = $("#btn_gestisci_criteri");
	if ($btn.length) {
		const href = $btn.attr("href");
		$btn.attr("href", addOrReplaceQueryParam(href, "anno_scolastico_id", anno));
	}
}

function bonusDocentiReadRecords() {
	let anno = $("#anno_scolastico_select").val();

	$.get("bonusDocentiReadRecords.php", { anno_scolastico_id: anno }, function (data, status) {
		$(".bonus_docenti_records_content").html(data);

		$('#bonus_docenti_table td:nth-child(1),th:nth-child(1)').hide();

		aggiornaLinkDocentiConAnno();
		aggiornaLinkGestisciCriteri();   // ✅ aggiungi questa riga

		convertTableNumbersToITWithEuro();
		aggiornaTotaleERecord();
	});
}

$(document).ready(function () {
	bonusDocentiReadRecords();

	$("#anno_scolastico_select").change(function () {
		aggiornaLinkGestisciCriteri();   // ✅ aggiorna subito il link
		bonusDocentiReadRecords();
	});
});


$(document).ready(function () {
	bonusDocentiReadRecords();

	$("#anno_scolastico_select").change(function () {
		bonusDocentiReadRecords();
	});
});
