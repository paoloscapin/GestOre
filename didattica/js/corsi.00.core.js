/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// 🔽 Recupero parametro "a" passato nello <script src=...>
// Robusto: non deve mai rompere l'esecuzione del file
var $anni_filtro_id = "1"; // default
try {
    var myScript =
        (document.currentScript) ||
        (function () {
            var scripts = document.getElementsByTagName('script');
            return scripts[scripts.length - 1];
        })();

    if (myScript && myScript.src) {
        // base URL = location.href permette di gestire src relativi
        var url = new URL(myScript.src, window.location.href);
        var params = new URLSearchParams(url.search);
        $anni_filtro_id = params.get("a") || "1";
    }
} catch (e) {
    // fallback: resta "1"
    if (window.console) console.warn("GestOre: impossibile leggere parametro 'a' dallo script src", e);
}

var $docente_filtro_id = 0;
var $materia_filtro_id = 0;
var $futuri = 0;
var $carenze_toggle = 0;
var $in_itinere_toggle = 0;
var $firma_esame = 0; // filtro esame firmato (se usato)
var $carenza_sessione = 0; // 0=tutte, 1=S1, 2=S2

// ================================
// DEBUG helper (abilita/disabilita qui)
// ================================
window.GESTORE_DEBUG_FIRME = true;

// ================================
// Read Records
// ================================
function corsiReadRecords() {
    $.get(
        "corsiReadRecords.php?anni_id=" + $anni_filtro_id +
        "&docente_id=" + $docente_filtro_id +
        "&materia_id=" + $materia_filtro_id +
        "&futuri=" + $futuri +
        "&carenze=" + $carenze_toggle +
        "&itinere=" + $in_itinere_toggle +
        "&carenza_sessione=" + $carenza_sessione,
        {},
        function (data, status) {
            $(".records_content").html(data);
            $('[data-toggle="tooltip"]').tooltip({ container: 'body' });
        }
    );
}

// ================================
// Utils
// ================================
function showToast(message, isError = false, duration = 3000) {
    var toast = $('#toastMessage');
    toast.css('background', isError ? '#dc3545' : '#28a745');
    toast.text(message).fadeIn(400).delay(duration).fadeOut(400);
}

// funzione per formattare le date
function formatDateTime(dateTimeStr) {
    var d = new Date(dateTimeStr);
    var giorno = String(d.getDate()).padStart(2, '0');
    var mese = String(d.getMonth() + 1).padStart(2, '0');
    var anno = d.getFullYear();
    var ore = String(d.getHours()).padStart(2, '0');
    var minuti = String(d.getMinutes()).padStart(2, '0');
    return giorno + "-" + mese + "-" + anno + " alle ore " + ore + ":" + minuti;
}

// Helper HTML escape (per note assenza)
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function hideTooltip(el) {
    $(el).tooltip('hide');
}

/**
 * IMPORTANTISSIMO (richiesta): NON disabilitare mai nulla.
 * Qui facciamo solo "pulizia" dei campi incoerenti.
 */
function syncAssenzaUI($tr) {
    var presente = $tr.find(".chk-presente").is(":checked");

    var $chkG = $tr.find(".chk-assenza-giust");
    var $note = $tr.find(".input-assenza-note");

    var $tipo = $tr.find(".tipo-prova");
    var $voto = $tr.find(".voto-studente");
    var $rec = $tr.find(".recuperata");

    if (presente) {
        $chkG.prop("checked", false);
        $note.val("");
        var vv = parseFloat($voto.val());
        if (!isNaN(vv)) $rec.text(vv >= 6 ? "✅" : "❌");
        else $rec.text("-");
    } else {
        $tipo.val("");
        $voto.val("");
        $rec.text("-");
        if (!$chkG.is(":checked")) $note.val("");
    }
}

// ================================
// Import
// ================================
function importFile(file) {
    var contenuto = "";
    const reader = new FileReader();
    reader.addEventListener('load', (event) => {
        contenuto = event.target.result;
        $.post("corsiImport.php", { contenuto: contenuto },
            function (data, status) {
                $('#result_text').html(data);
                corsiReadRecords();
                setTimeout(function () { $('#result_text').html(""); }, 5000);
            });
    });
    reader.readAsText(file);
}
