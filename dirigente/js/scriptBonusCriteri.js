/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function notifyOk(msg) {
	$.notify({ icon: 'glyphicon glyphicon-ok', message: msg }, { type: 'success', delay: 2000, placement: { from: "top", align: "center" } });
}

function notifyErr(msg) {
	$.notify({ icon: 'glyphicon glyphicon-warning-sign', message: msg }, { type: 'danger', delay: 4000, placement: { from: "top", align: "center" } });
}

function getAnno() {
	return $("#anno_scolastico_select").val();
}

function loadTree() {
	$.get("bonusCriteriReadTree.php", { anno_scolastico_id: getAnno() }, function (html) {
		$("#bonus_criteri_content").html(html);
	});
}

/* =======================
   COPY FROM PREV YEAR
   ======================= */

function copyFromPreviousYear(forceOverwrite) {
	$.ajax({
		url: "bonusCriteriCopyFromPrevYear.php",
		method: "POST",
		dataType: "json", // ✅ jQuery fa parse JSON
		data: {
			anno_scolastico_id: getAnno(),
			force: forceOverwrite ? 1 : 0
		},
		success: function (r) {
			if (r.success) {
				nottifyOk(r.message || "Copia completata");
				loadTree();
			} else {
				if (r.code === "already_exists") {
					if (confirm((r.message || "Esistono già criteri per questo anno.") +
						"\nVuoi sovrascrivere (valido=0 + nuova copia)?")) {
						copyFromPreviousYear(true);
					}
				} else {
					notifyErr(r.message || "Errore copia");
				}
			}
		},
		error: function (xhr) {
			// ✅ qui vedi proprio cosa ha risposto il server
			console.error("Risposta server non JSON:", xhr.responseText);
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
		$("#indicatore_valido").prop("checked", indicatore.valido === null || indicatore.valido === "1" || indicatore.valido === 1);
	}

	$("#modal_indicatore").modal("show");
}

function saveIndicatore() {
	$.post("bonusIndicatoreSave.php", {
		id: $("#indicatore_id").val(),
		anno_scolastico_id: getAnno(),
		bonus_area_id: $("#indicatore_area_id").val(),
		codice: $("#indicatore_codice").val(),
		descrizione: $("#indicatore_descrizione").val(),
		valore_massimo: $("#indicatore_valore_massimo").val(),
		valido: $("#indicatore_valido").is(":checked") ? 1 : 0
	}, function (resp) {
		let r;
		try { r = JSON.parse(resp); } catch(e) { notifyErr("Risposta non valida dal server"); return; }

		if (r.success) {
			$("#modal_indicatore").modal("hide");
			notifyOk(r.message || "Salvato");
			loadTree();
		} else {
			notifyErr(r.message || "Errore salvataggio indicatore");
		}
	});
}

function deleteIndicatore(indicatoreId) {
	if (!confirm("Vuoi disattivare questo indicatore? (valido=0)")) return;

	$.post("bonusIndicatoreDelete.php", {
		id: indicatoreId,
		anno_scolastico_id: getAnno()
	}, function (resp) {
		let r;
		try { r = JSON.parse(resp); } catch(e) { notifyErr("Risposta non valida dal server"); return; }

		if (r.success) {
			notifyOk(r.message || "Eliminato");
			loadTree();
		} else {
			notifyErr(r.message || "Errore eliminazione indicatore");
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
		$("#bonus_valido").prop("checked", bonus.valido === null || bonus.valido === "1" || bonus.valido === 1);
	}

	$("#modal_bonus").modal("show");
}

function saveBonus() {
	$.post("bonusVoceSave.php", {
		id: $("#bonus_id").val(),
		anno_scolastico_id: getAnno(),
		bonus_indicatore_id: $("#bonus_indicatore_id").val(),
		codice: $("#bonus_codice").val(),
		descrittori: $("#bonus_descrittori").val(),
		evidenze: $("#bonus_evidenze").val(),
		valore_previsto: $("#bonus_valore_previsto").val(),
		valido: $("#bonus_valido").is(":checked") ? 1 : 0
	}, function (resp) {
		let r;
		try { r = JSON.parse(resp); } catch(e) { notifyErr("Risposta non valida dal server"); return; }

		if (r.success) {
			$("#modal_bonus").modal("hide");
			notifyOk(r.message || "Salvato");
			loadTree();
		} else {
			notifyErr(r.message || "Errore salvataggio bonus");
		}
	});
}

function deleteBonus(bonusId) {
	if (!confirm("Vuoi disattivare questo bonus? (valido=0)")) return;

	$.post("bonusVoceDelete.php", {
		id: bonusId,
		anno_scolastico_id: getAnno()
	}, function (resp) {
		let r;
		try { r = JSON.parse(resp); } catch(e) { notifyErr("Risposta non valida dal server"); return; }

		if (r.success) {
			notifyOk(r.message || "Eliminato");
			loadTree();
		} else {
			notifyErr(r.message || "Errore eliminazione bonus");
		}
	});
}

/* =======================
   EVENTS (delegati)
   ======================= */

$(document).ready(function () {

	loadTree();

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

	$("#btn_save_indicatore").click(saveIndicatore);
	$("#btn_save_bonus").click(saveBonus);

	// Deleghe click dentro tree (creato dinamicamente)
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
