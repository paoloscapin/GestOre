/**
 *  This file is part of GestOre
 */

function getAnnoScolasticoId() {
	const $sel = $("#anno_scolastico_select");
	if ($sel.length) return $sel.val();

	const params = new URLSearchParams(window.location.search);
	return params.get("anno_scolastico_id");
}

function gotoSelection() {
	const anno = getAnnoScolasticoId();
	window.location.href = "bonusSelection.php?anno_scolastico_id=" + encodeURIComponent(anno);
}

function loadAllegati(bonus_docente_id) {
	const anno = getAnnoScolasticoId();
	$("#allegati_list").load("bonusAllegatiList.php?bonus_docente_id=" + encodeURIComponent(bonus_docente_id) +
		"&anno_scolastico_id=" + encodeURIComponent(anno));
}

function uploadAllegati() {
	const bonus_docente_id = $("#hidden_bonus_docente_id").val();
	const anno = getAnnoScolasticoId();
	const files = $("#allegati_files")[0].files;

	if (!files || files.length === 0) return;

	const fd = new FormData();
	fd.append("bonus_docente_id", bonus_docente_id);
	fd.append("anno_scolastico_id", anno);
	for (let i = 0; i < files.length; i++) {
		fd.append("files[]", files[i]);
	}

	$.ajax({
		url: "bonusAllegatiUpload.php",
		method: "POST",
		data: fd,
		processData: false,
		contentType: false,
		dataType: "json",
		success: function (r) {
			if (r.success) {
				$("#allegati_files").val("");
				loadAllegati(bonus_docente_id);
			} else {
				alert(r.message || "Errore upload");
			}
		},
		error: function (xhr) {
			console.error(xhr.responseText);
			alert("Errore upload (controlla console)");
		}
	});
}

$(document).on("click", ".btn-del-allegato", function () {
	const $btn = $(this);
	const id = $btn.data("id");
	const anno = getAnnoScolasticoId();
	const bonus_docente_id = $("#hidden_bonus_docente_id").val();

	if (!confirm("Eliminare questo allegato?")) return;

	$btn.prop("disabled", true);

	$.ajax({
		url: "bonusAllegatoDelete.php",
		method: "POST",
		data: { id: id, anno_scolastico_id: anno },
		dataType: "text", // <-- NON json
		success: function (txt) {

			let r = null;
			try { r = JSON.parse(txt); } catch (e) { }

			if (!r || r.success !== true) {
				alert((r && r.message) ? r.message : "Risposta non valida dal server:\n" + txt);
				return;
			}

			// rimuove subito dalla UI e ricarica lista
			$btn.closest("li").remove();
			loadAllegati(bonus_docente_id);
		},
		error: function (xhr, textStatus, errorThrown) {
			console.error("bonusAllegatoDelete.php error:", xhr.status, textStatus, errorThrown, xhr.responseText);
			alert("Errore cancellazione (" + xhr.status + ")\n" + (xhr.responseText || "").slice(0, 300));
		}
	});

});


function bonusRendiconto(bonus_docente_id, bonus_codice, bonus_descrittori, bonus_evidenze) {
	$("#hidden_bonus_docente_id").val(bonus_docente_id);

	$.post("bonusDocenteReadDetails.php", { bonus_docente_id: bonus_docente_id }, function (dati) {
		// console.log(dati);
		var bonus = JSON.parse(dati);
		$("#rendiconto_rendiconto").val(bonus.rendiconto_evidenze);
	});

	$("#myModalLabel").text(bonus_codice + ": " + bonus_descrittori);
	$("#evidenze_text").text(bonus_evidenze);
	$("#bonus_docente_rendiconto_modal").modal("show");
	loadAllegati(bonus_docente_id);

}

function bonusDocenteRendicontoUpdateDetails() {
	$.post("bonusDocenteUpdate.php", {
		bonus_docente_id: $("#hidden_bonus_docente_id").val(),
		rendiconto: $("#rendiconto_rendiconto").val()
	}, function (data) {
		// se il backend torna JSON, possiamo gestirlo
		try {
			var r = (typeof data === "string") ? JSON.parse(data) : data;
			if (r && r.success === false) {
				$.notify({
					icon: 'glyphicon glyphicon-warning-sign',
					title: '<Strong>Bonus</Strong></br>',
					message: r.message || 'Operazione non consentita'
				}, {
					placement: { from: "top", align: "center" },
					delay: 5000,
					timer: 100,
					mouse_over: "pause",
					type: 'danger'
				});
			}
		} catch (e) {
			// ignore
		}
	});

	$("#bonus_docente_rendiconto_modal").modal("hide");
}

$(document).ready(function () {

	$("#btn_upload_allegati").on("click", function () {
		uploadAllegati();
	});

	// BONUS.PHP: bottone adesioni (se presente)
	$("#btn_adesioni").on("click", function () {
		gotoSelection();
	});

	// Cambio anno: ricarica pagina mantenendo querystring
	$("#anno_scolastico_select").change(function () {
		const anno = $(this).val();
		const url = new URL(window.location.href);
		url.searchParams.set("anno_scolastico_id", anno);
		window.location.href = url.pathname + url.search;
	});

	// BONUSSELECTION.PHP: nascondi colonne id (ora tabella ripetuta => usiamo class)
	$(".bonus_selection_table td:nth-child(1), .bonus_selection_table th:nth-child(1)").hide();
	$(".bonus_selection_table td:nth-child(2), .bonus_selection_table th:nth-child(2)").hide();

	// BONUSSELECTION.PHP: toggle selezione (delegato, perché bootstrap-toggle può rigenerare DOM)
	$(document).on("change", ".bonus_selection_table input:checkbox", function () {

		// se disabilitato (anno non corrente o adesioni chiuse), non fare nulla
		if ($(this).is(":disabled")) return;

		const anno = getAnnoScolasticoId();
		const row = $(this).closest("tr");

		const idBonus = row.children().eq(0).text();
		const idAdesione = row.children().eq(1).text();
		const checked = $(this).is(":checked");

		$.post("bonusAdesioniUpdate.php", {
			adesione_id: idAdesione,
			bonus_id: idBonus,
			anno_scolastico_id: anno
		}, function (data) {

			// se ho spuntato, il server ritorna l'id della nuova adesione
			if (checked) {
				row.children().eq(1).html(data);

				$.notify({
					icon: 'glyphicon glyphicon-info-sign',
					title: '<Strong>Selezione Bonus</Strong></br>',
					message: 'Criterio aggiunto. Selezione aggiornata!'
				}, {
					placement: { from: "top", align: "center" },
					delay: 3500,
					timer: 100,
					mouse_over: "pause",
					type: 'info'
				});
			} else {
				// se ho deselezionato, adesione torna a -1
				row.children().eq(1).html(-1);

				$.notify({
					icon: 'glyphicon glyphicon-info-sign',
					title: '<Strong>Selezione Bonus</Strong></br>',
					message: 'Criterio rimosso. Selezione aggiornata!'
				}, {
					placement: { from: "top", align: "center" },
					delay: 3500,
					timer: 100,
					mouse_over: "pause",
					type: 'info'
				});
			}

		}).fail(function (xhr) {
			// se backend blocca (403), ripristina lo stato del toggle
			// (per evitare UI incoerente)
			if (checked) {
				$(this).prop("checked", false);
				row.children().eq(1).html(-1);
			} else {
				$(this).prop("checked", true);
			}

			$.notify({
				icon: 'glyphicon glyphicon-warning-sign',
				title: '<Strong>Selezione Bonus</Strong></br>',
				message: 'Modifica non consentita (anno non corrente o adesioni chiuse).'
			}, {
				placement: { from: "top", align: "center" },
				delay: 5000,
				timer: 100,
				mouse_over: "pause",
				type: 'danger'
			});
		}.bind(this));
	});
});
