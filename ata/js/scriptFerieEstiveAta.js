(function () {
    "use strict";

    const BOOT = window.__FERIE_ESTIVE_BOOTSTRAP || {};
    const FERIE_TIPO_ID = parseInt(BOOT.ferie_tipo_id || 0, 10);
    const FINESTRA = BOOT.finestra || {};
    const DATA_INIZIO = (FINESTRA.data_inizio || "").toString();
    const DATA_FINE = (FINESTRA.data_fine || "").toString();
    const PATRONO_MMDD = (BOOT.patrono_mmdd || "06-26").toString();
    const EDIT_ID = parseInt(BOOT.edit_id || 0, 10);

    const MONTH_NAMES = [
        "Gennaio", "Febbraio", "Marzo", "Aprile", "Maggio", "Giugno",
        "Luglio", "Agosto", "Settembre", "Ottobre", "Novembre", "Dicembre"
    ];

    const DOW_NAMES = ["Lun", "Mar", "Mer", "Gio", "Ven", "Sab", "Dom"];

    let selectedDays = new Set();
    let currentState = "BOZZA";
    let isReadOnly = false;

    function pad2(n) {
        n = parseInt(n, 10);
        return (n < 10 ? "0" : "") + n;
    }

    function formatDateIT(ymd) {
        if (!ymd || !/^\d{4}-\d{2}-\d{2}$/.test(ymd)) return ymd || "";
        const p = ymd.split("-");
        return p[2] + "/" + p[1] + "/" + p[0];
    }

    function parseYmdLocal(ymd) {
        const p = (ymd || "").split("-");
        if (p.length !== 3) return null;
        return new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10), 12, 0, 0, 0);
    }

    function toYmd(date) {
        return date.getFullYear() + "-" + pad2(date.getMonth() + 1) + "-" + pad2(date.getDate());
    }

    function addDays(date, days) {
        const d = new Date(date.getTime());
        d.setDate(d.getDate() + days);
        return d;
    }

    function sameMonth(a, b) {
        return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth();
    }

    function monthStart(date) {
        return new Date(date.getFullYear(), date.getMonth(), 1, 12, 0, 0, 0);
    }

    function monthEnd(date) {
        return new Date(date.getFullYear(), date.getMonth() + 1, 0, 12, 0, 0, 0);
    }

    function firstOfNextMonth(date) {
        return new Date(date.getFullYear(), date.getMonth() + 1, 1, 12, 0, 0, 0);
    }

    function isWeekend(ymd) {
        const d = parseYmdLocal(ymd);
        if (!d) return false;
        const jsDow = d.getDay(); // 0 dom, 6 sab
        return jsDow === 0 || jsDow === 6;
    }

    function isPatrono(ymd) {
        return (ymd || "").slice(5) === PATRONO_MMDD;
    }

    function inWindow(ymd) {
        return ymd >= DATA_INIZIO && ymd <= DATA_FINE;
    }

    function isSelectable(ymd) {
        return inWindow(ymd) && !isWeekend(ymd) && !isPatrono(ymd);
    }

    function lockReason(ymd) {
        if (!inWindow(ymd)) return "Fuori periodo";
        if (isPatrono(ymd)) return "Patrono";
        if (isWeekend(ymd)) return "Weekend";
        return "";
    }

    function showError(msg) {
        $("#ferie_alert").text(msg).show();
        $("html, body").animate({ scrollTop: 0 }, 150);
    }

    function hideError() {
        $("#ferie_alert").hide().text("");
    }

    function notifyCentered(type, title, msg, delay) {
        $.notify(
            {
                icon: type === "danger" ? "glyphicon glyphicon-warning-sign" : "glyphicon glyphicon-info-sign",
                title: "<strong>" + title + "</strong><br>",
                message: msg
            },
            {
                placement: { from: "top", align: "center" },
                offset: { x: 0, y: 70 },
                delay: delay || 3500,
                timer: 100,
                mouse_over: "pause",
                type: type,
                z_index: 9999
            }
        );
    }

    function updateHeaderInfo() {
        $("#periodo_testo").text(formatDateIT(DATA_INIZIO) + " – " + formatDateIT(DATA_FINE));
        $("#count_selected").text(selectedDays.size);
        $("#badge_stato").text(currentState || "BOZZA");
    }

    function setReadonly(readonly) {
        isReadOnly = !!readonly;
        $("#ferie_note").prop("readonly", isReadOnly);
        $("#btn_save_bozza_estive").prop("disabled", isReadOnly);
        $("#btn_invia_estive").prop("disabled", isReadOnly);
        $("#btn_clear_selection").prop("disabled", isReadOnly);
        renderCalendar();
    }

    function clearSelection() {
        if (isReadOnly) return;
        selectedDays = new Set();
        renderCalendar();
    }

    function resetEditorToNew() {
    $("#richiesta_id").val("");
    $("#ferie_note").val("");
    currentState = "BOZZA";
    selectedDays = new Set();
    $("#editor_title").text("Nuova richiesta ferie estive");
    setReadonly(false);
    updateHeaderInfo();
}

function cancelEditing() {
    if (EDIT_ID > 0 || ($("#richiesta_id").val() || "") !== "") {
        const id = parseInt($("#richiesta_id").val() || EDIT_ID || "0", 10);
        if (id > 0) {
            loadRequest(id);
            return;
        }
    }
    resetEditorToNew();
}
    function renderCalendar() {
        const $wrap = $("#months_wrap");
        $wrap.empty();

        if (!DATA_INIZIO || !DATA_FINE) {
            $wrap.html('<div class="alert alert-danger ferie-alert">Finestra ferie estive non configurata.</div>');
            return;
        }

        let cursor = monthStart(parseYmdLocal(DATA_INIZIO));
        const endDate = parseYmdLocal(DATA_FINE);

        while (cursor <= endDate) {
            const first = monthStart(cursor);
            const last = monthEnd(cursor);

            let html = '';
            html += '<div class="month-card">';
            html += '  <div class="month-head">' + MONTH_NAMES[first.getMonth()] + ' ' + first.getFullYear() + '</div>';
            html += '  <div class="month-grid">';

            //            for (let i = 0; i < DOW_NAMES.length; i++) {
            //                html += '<div class="dow-cell">' + DOW_NAMES[i] + '</div>';
            //            }

            let startOffset = first.getDay(); // 0 dom
            startOffset = (startOffset === 0 ? 7 : startOffset); // 1..7 lun..dom
            const emptyBefore = startOffset - 1;

            for (let i = 0; i < emptyBefore; i++) {
                html += '<div class="day-cell empty"></div>';
            }

            for (let day = 1; day <= last.getDate(); day++) {
                const d = new Date(first.getFullYear(), first.getMonth(), day, 12, 0, 0, 0);
                const ymd = toYmd(d);

                const selectable = isSelectable(ymd);
                const selected = selectedDays.has(ymd);
                const classes = ["day-cell"];
                if (!selectable) classes.push("locked");
                if (selected && !isReadOnly) classes.push("selected");
                if (selected && isReadOnly) classes.push("readonly-selected");

                const meta = selected
                    ? '<div class="day-meta"><span class="glyphicon glyphicon-ok"></span> Selezionato</div>'
                    : '<div class="day-meta"></div>';

                const reason = !selectable
                    ? '<div class="day-lock-reason">' + lockReason(ymd) + '</div>'
                    : '';

                const dowIndex = (d.getDay() === 0 ? 6 : d.getDay() - 1);
                const dowLabel = DOW_NAMES[dowIndex];

                html += ''
                    + '<div class="' + classes.join(" ") + '" data-date="' + ymd + '">'
                    + '  <div class="day-num">'
                    + '    <span class="day-dow">' + dowLabel + '</span>'
                    + '    <span class="day-day">' + day + '</span>'
                    + '  </div>'
                    + '  ' + meta
                    + '  ' + reason
                    + '</div>';
            }

            html += '  </div>';
            html += '</div>';

            $wrap.append(html);
            cursor = firstOfNextMonth(cursor);
        }

        updateHeaderInfo();
    }

function readRecords() {
    // Non mostriamo più la lista richieste in questa pagina
}

    function loadRequest(id) {
        hideError();

        $.ajax({
            url: "ferieEstiveReadDetails.php",
            method: "POST",
            dataType: "json",
            data: { id: id },
            success: function (r) {
                if (!r || r.ok !== true) {
                    showError((r && r.error) ? r.error : "Errore lettura richiesta.");
                    return;
                }

                const req = r.richiesta || {};
                const giorni = Array.isArray(r.giorni) ? r.giorni : [];

                $("#richiesta_id").val(req.id || "");
                $("#ferie_note").val(req.note || "");
                currentState = (req.stato || "BOZZA").toString();

                selectedDays = new Set();
                giorni.forEach(function (g) {
                    if (g && g.data) selectedDays.add(g.data);
                });

                $("#editor_title").text("Richiesta ferie estive #" + req.id);
                setReadonly(currentState !== "BOZZA");
                updateHeaderInfo();
            },
            error: function (xhr) {
                showError("Errore server: " + xhr.status);
            }
        });
    }

    function collectSelectedDays() {
        return Array.from(selectedDays).sort();
    }

    function saveRequest(azione) {
        hideError();

        if (isReadOnly) {
            showError("Questa richiesta non è modificabile.");
            return;
        }

        const giorni = collectSelectedDays();
        if (!giorni.length) {
            showError("Seleziona almeno un giorno di ferie.");
            return;
        }

        $.ajax({
            url: "ferieEstiveSave.php",
            method: "POST",
            dataType: "json",
            data: {
                richiesta_id: $("#richiesta_id").val() || "",
                permesso_tipo_id: FERIE_TIPO_ID,
                note: $("#ferie_note").val() || "",
                azione: azione,
                giorni_json: JSON.stringify(giorni)
            },
            success: function (r) {
                if (!r || r.ok !== true) {
                    showError((r && r.error) ? r.error : "Errore salvataggio.");
                    notifyCentered("danger", "Ferie estive", (r && r.error) ? r.error : "Errore salvataggio.", 5000);
                    return;
                }

                $("#richiesta_id").val(r.id || "");
                currentState = r.stato || currentState;
                $("#editor_title").text("Richiesta ferie estive #" + (r.id || ""));
                updateHeaderInfo();

                if (azione === "INVIA") {
                    setReadonly(true);
                }

                notifyCentered(
                    "info",
                    "Ferie estive",
                    (azione === "INVIA")
                        ? "Richiesta ferie estive inviata."
                        : "Bozza ferie estive salvata.",
                    2600
                );
            },
            error: function (xhr) {
                const msg = "Errore server: " + xhr.status;
                showError(msg);
                notifyCentered("danger", "Ferie estive", msg, 5000);
            }
        });
    }

    function deleteRequest(id) {
        if (!confirm("Vuoi eliminare questa bozza di ferie estive?")) return;

        $.ajax({
            url: "ferieEstiveDelete.php",
            method: "POST",
            dataType: "json",
            data: { id: id },
            success: function (r) {
                if (!r || r.ok !== true) {
                    notifyCentered("danger", "Ferie estive", (r && r.error) ? r.error : "Errore eliminazione.", 5000);
                    return;
                }

                if (parseInt($("#richiesta_id").val() || "0", 10) === parseInt(id, 10)) {
                    $("#richiesta_id").val("");
                    $("#ferie_note").val("");
                    currentState = "BOZZA";
                    $("#editor_title").text("Nuova richiesta ferie estive");
                    selectedDays = new Set();
                    setReadonly(false);
                    updateHeaderInfo();
                }

                notifyCentered("info", "Ferie estive", "Bozza eliminata.", 2200);
            },
            error: function (xhr) {
                notifyCentered("danger", "Ferie estive", "Errore server: " + xhr.status, 5000);
            }
        });
    }

    $(document).on("click", ".day-cell", function () {
        if (isReadOnly) return;

        const $cell = $(this);
        const ymd = ($cell.data("date") || "").toString();

        if (!ymd || !isSelectable(ymd)) return;

        if (selectedDays.has(ymd)) {
            selectedDays.delete(ymd);
        } else {
            selectedDays.add(ymd);
        }

        renderCalendar();
    });

$(document).on("click", "#btn_cancel_estive", function () {
    cancelEditing();
});

    $(document).on("click", "#btn_new_estive", function () {
        window.location.href = "ferieEstive.php";
    });

    $(document).on("click", "#btn_save_bozza_estive", function () {
        saveRequest("BOZZA");
    });

    $(document).on("click", "#btn_invia_estive", function () {
        saveRequest("INVIA");
    });

    $(document).on("click", ".btn-delete-estive", function () {
        deleteRequest($(this).data("id"));
    });

    $(function () {
        updateHeaderInfo();
        renderCalendar();

        if (EDIT_ID > 0) {
            loadRequest(EDIT_ID);
        } else {
            setReadonly(false);
        }
    });
})();