/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function notifyOk(msg) {
    $.notify(
        { icon: 'glyphicon glyphicon-ok', message: msg },
        { type: 'success', delay: 2000, placement: { from: "top", align: "center" } }
    );
}

function notifyErr(msg) {
    $.notify(
        { icon: 'glyphicon glyphicon-warning-sign', message: msg },
        { type: 'danger', delay: 4000, placement: { from: "top", align: "center" } }
    );
}

function getAnno() {
    return $("#anno_scolastico_select").val();
}

function loadTree() {
    $.get("bonusCriteriReadTree.php", { anno_scolastico_id: getAnno() }, function (html) {
        $("#bonus_criteri_content").html(html);
    });
}

function exportCsv() {
    const anno = getAnno();
    window.location.href = "bonusCriteriExportCsv.php?anno_scolastico_id=" + encodeURIComponent(anno);
}

function printPdf() {
	const anno = getAnno();
	window.open(
		"bonusCriteriExportPdf.php?anno_scolastico_id=" + encodeURIComponent(anno),
		"_blank"
	);
}


/* =======================
   COPY FROM PREV YEAR
   ======================= */

function copyFromPreviousYear(forceOverwrite) {
    $.ajax({
        url: "bonusCriteriCopyFromPrevYear.php",
        method: "POST",
        dataType: "json",
        data: {
            anno_scolastico_id: getAnno(),
            force: forceOverwrite ? 1 : 0
        },
        success: function (r) {
            if (r && r.success) {
                notifyOk(r.message || "Copia completata");
                loadTree();
            } else {
                if (r && r.code === "already_exists") {
                    if (confirm((r.message || "Esistono già criteri per questo anno.") +
                        "\nVuoi sovrascrivere (valido=0 + nuova copia)?")) {
                        copyFromPreviousYear(true);
                    }
                } else {
                    notifyErr((r && r.message) ? r.message : "Errore copia");
                }
            }
        },
        error: function (xhr) {
            console.error("copyFromPreviousYear error:", xhr.status, xhr.responseText);
            notifyErr("Risposta non valida dal server. Controlla console (F12) > Network/Console.");
        }
    });
}

/* =======================
   INDICATORE MODAL
   ======================= */

function openIndicatoreModal(areaId, indicatore) {
    $("#indicatore_area_id").val(areaId);

    if (!indicatore) {
        $("#modalIndicatoreLabel").text("Nuovo indicatore");
        $("#indicatore_id").val(0);
        $("#indicatore_codice").val("");
        $("#indicatore_descrizione").val("");
        $("#indicatore_valore_massimo").val("");
        $("#indicatore_valido").prop("checked", true);
    } else {
        $("#modalIndicatoreLabel").text("Modifica indicatore");
        $("#indicatore_id").val(indicatore.id);
        $("#indicatore_codice").val(indicatore.codice || "");
        $("#indicatore_descrizione").val(indicatore.descrizione || "");
        $("#indicatore_valore_massimo").val(indicatore.valore_massimo || "");
        $("#indicatore_valido").prop("checked",
            indicatore.valido === null || indicatore.valido === "1" || indicatore.valido === 1
        );
    }

    $("#modal_indicatore").modal("show");
}

function saveIndicatore() {
    $.ajax({
        url: "bonusIndicatoreSave.php",
        method: "POST",
        dataType: "json",
        data: {
            id: $("#indicatore_id").val(),
            anno_scolastico_id: getAnno(),
            bonus_area_id: $("#indicatore_area_id").val(),
            codice: $("#indicatore_codice").val(),
            descrizione: $("#indicatore_descrizione").val(),
            valore_massimo: $("#indicatore_valore_massimo").val(),
            valido: $("#indicatore_valido").is(":checked") ? 1 : 0
        },
        success: function (r) {
            if (r && r.success) {
                $("#modal_indicatore").modal("hide");
                notifyOk(r.message || "Salvato");
                loadTree();
            } else {
                notifyErr((r && r.message) ? r.message : "Errore salvataggio indicatore");
            }
        },
        error: function (xhr) {
            console.error("saveIndicatore error:", xhr.status, xhr.responseText);
            notifyErr("Errore salvataggio indicatore (server). Vedi console.");
        }
    });
}

function deleteIndicatore(indicatoreId) {
    if (!confirm("Vuoi disattivare questo indicatore? (valido=0)")) return;

    $.ajax({
        url: "bonusIndicatoreDelete.php",
        method: "POST",
        dataType: "json",
        data: {
            id: indicatoreId,
            anno_scolastico_id: getAnno()
        },
        success: function (r) {
            if (r && r.success) {
                notifyOk(r.message || "Eliminato");
                loadTree();
            } else {
                notifyErr((r && r.message) ? r.message : "Errore eliminazione indicatore");
            }
        },
        error: function (xhr) {
            console.error("deleteIndicatore error:", xhr.status, xhr.responseText);
            notifyErr("Errore eliminazione indicatore (server). Vedi console.");
        }
    });
}

/* =======================
   BONUS MODAL
   ======================= */

function openBonusModal(indicatoreId, bonus) {
    $("#bonus_indicatore_id").val(indicatoreId);

    if (!bonus) {
        $("#modalBonusLabel").text("Nuovo bonus");
        $("#bonus_id").val(0);
        $("#bonus_codice").val("");
        $("#bonus_descrittori").val("");
        $("#bonus_evidenze").val("");
        $("#bonus_valore_previsto").val("");
        $("#bonus_valido").prop("checked", true);
    } else {
        $("#modalBonusLabel").text("Modifica bonus");
        $("#bonus_id").val(bonus.id);
        $("#bonus_codice").val(bonus.codice || "");
        $("#bonus_descrittori").val(bonus.descrittori || "");
        $("#bonus_evidenze").val(bonus.evidenze || "");
        $("#bonus_valore_previsto").val(bonus.valore_previsto || "");
        $("#bonus_valido").prop("checked",
            bonus.valido === null || bonus.valido === "1" || bonus.valido === 1
        );
    }

    $("#modal_bonus").modal("show");
}

function saveBonus() {
    $.ajax({
        url: "bonusVoceSave.php",
        method: "POST",
        dataType: "json",
        data: {
            id: $("#bonus_id").val(),
            anno_scolastico_id: getAnno(),
            bonus_indicatore_id: $("#bonus_indicatore_id").val(),
            codice: $("#bonus_codice").val(),
            descrittori: $("#bonus_descrittori").val(),
            evidenze: $("#bonus_evidenze").val(),
            valore_previsto: $("#bonus_valore_previsto").val(),
            valido: $("#bonus_valido").is(":checked") ? 1 : 0
        },
        success: function (r) {
            if (r && r.success) {
                $("#modal_bonus").modal("hide");
                notifyOk(r.message || "Salvato");
                loadTree();
            } else {
                notifyErr((r && r.message) ? r.message : "Errore salvataggio bonus");
            }
        },
        error: function (xhr) {
            console.error("saveBonus error:", xhr.status, xhr.responseText);
            notifyErr("Errore salvataggio bonus (server). Vedi console.");
        }
    });
}

function deleteBonus(bonusId) {
    if (!confirm("Vuoi disattivare questo bonus? (valido=0)")) return;

    $.ajax({
        url: "bonusVoceDelete.php",
        method: "POST",
        dataType: "json",
        data: {
            id: bonusId,
            anno_scolastico_id: getAnno()
        },
        success: function (r) {
            if (r && r.success) {
                notifyOk(r.message || "Eliminato");
                loadTree();
            } else {
                notifyErr((r && r.message) ? r.message : "Errore eliminazione bonus");
            }
        },
        error: function (xhr) {
            console.error("deleteBonus error:", xhr.status, xhr.responseText);
            notifyErr("Errore eliminazione bonus (server). Vedi console.");
        }
    });
}

/* =======================
   EVENTS (delegati)
   ======================= */

$(document).ready(function () {

    loadTree();

    $("#btn_export_csv").click(function () {
        exportCsv();
    });

    $("#btn_print_pdf").click(function () {
        printPdf();
    });

    $("#anno_scolastico_select").change(function () {
        loadTree();
    });

    $("#btn_reload").click(function () {
        loadTree();
    });

    $("#btn_copy_prev").click(function () {
        if (!confirm("Vuoi copiare indicatori e bonus dall'anno precedente a quello selezionato?")) return;
        copyFromPreviousYear(false);
    });

    $(document).on("click", "#btn_save_indicatore", saveIndicatore);
    $(document).on("click", "#btn_save_bonus", saveBonus);

    $(document).on("click", ".btn-add-indicatore", function () {
        openIndicatoreModal($(this).data("area-id"), null);
    });

    $(document).on("click", ".btn-edit-indicatore", function () {
        openIndicatoreModal($(this).data("area-id"), $(this).data("indicatore"));
    });

    $(document).on("click", ".btn-del-indicatore", function () {
        deleteIndicatore($(this).data("id"));
    });

    $(document).on("click", ".btn-add-bonus", function () {
        openBonusModal($(this).data("indicatore-id"), null);
    });

    $(document).on("click", ".btn-edit-bonus", function () {
        openBonusModal($(this).data("indicatore-id"), $(this).data("bonus"));
    });

    $(document).on("click", ".btn-del-bonus", function () {
        deleteBonus($(this).data("id"));
    });
});
