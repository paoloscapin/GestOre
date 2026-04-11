(function () {
    "use strict";

    const BOOT = window.__FERIE_BOOTSTRAP || {};
    const FERIE_TIPO_ID = parseInt(BOOT.ferie_tipo_id || 0, 10);
    const FINESTRA = BOOT.finestra || {};
    const DATA_INIZIO = (FINESTRA.data_inizio || "").toString();
    const DATA_FINE = (FINESTRA.data_fine || "").toString();
    const EDIT_ID = parseInt(BOOT.edit_id || 0, 10);
    const SOTTOTIPO = (BOOT.sottotipo || "ESTIVE").toString();
    const TITOLO = (BOOT.titolo || "Ferie").toString();
    const GIORNI_SPECIALI = Array.isArray(BOOT.giorni_speciali) ? BOOT.giorni_speciali : [];
    const CALENDAR_STATE_URL = (BOOT.calendar_state_url || "ferieRichiestaReadCalendarState.php").toString();

    const specialExcludeMap = {};
    const specialIncludeMap = {};

    GIORNI_SPECIALI.forEach(function (g) {
        const data = (g.data || "").toString();
        const tipo = (g.tipo || "").toString().toUpperCase();
        if (!data) return;

        if (tipo === "ESCLUDI") specialExcludeMap[data] = g;
        if (tipo === "INCLUDI") specialIncludeMap[data] = g;
    });

    const MONTH_NAMES = [
        "Gennaio", "Febbraio", "Marzo", "Aprile", "Maggio", "Giugno",
        "Luglio", "Agosto", "Settembre", "Ottobre", "Novembre", "Dicembre"
    ];

    const DOW_NAMES = ["Lun", "Mar", "Mer", "Gio", "Ven", "Sab", "Dom"];

    let selectedDays = new Set();
    let currentState = "BOZZA";
    let isReadOnly = false;

    // nuovo: stato storico globale utente
    let historicalDaysMap = {};
    let currentDraftDaysMap = {};

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
        const jsDow = d.getDay();
        return jsDow === 0 || jsDow === 6;
    }

    function isSpecialExcluded(ymd) {
        return !!specialExcludeMap[ymd];
    }

    function isSpecialIncluded(ymd) {
        return !!specialIncludeMap[ymd];
    }

    function inWindow(ymd) {
        return ymd >= DATA_INIZIO && ymd <= DATA_FINE;
    }

    function getHistoricalInfo(ymd) {
        return historicalDaysMap[ymd] || null;
    }

    function isEditableCurrentDraftDay(ymd) {
        return !!currentDraftDaysMap[ymd];
    }

    function isSelectable(ymd) {
        if (!inWindow(ymd)) return false;

        // giorni già presenti nello storico (altre richieste) NON selezionabili
        if (getHistoricalInfo(ymd)) return false;

        // giorni speciali inclusi restano selezionabili
        if (isSpecialIncluded(ymd)) return true;

        if (isWeekend(ymd)) return false;
        if (isSpecialExcluded(ymd)) return false;

        return true;
    }

    function lockReason(ymd) {
        if (!inWindow(ymd)) return "Fuori periodo";

        const hist = getHistoricalInfo(ymd);
        if (hist) {
            if (hist.stato === "APPROVATO") return hist.motivo || "Già approvato";
            if (hist.stato === "RESPINTO") return hist.motivo || "Già respinto";
            if (hist.stato === "BOZZA") return hist.motivo || "Già presente in altra bozza";
            return hist.motivo || "Già richiesto";
        }

        if (isWeekend(ymd) && !isSpecialIncluded(ymd)) return "Weekend";
        if (isSpecialExcluded(ymd)) {
            return specialExcludeMap[ymd].descrizione || "Non disponibile";
        }

        return "";
    }

    function historicalCssClass(ymd) {
        const hist = getHistoricalInfo(ymd);
        if (!hist) return "";

        const stato = (hist.stato || "").toUpperCase();
        if (stato === "APPROVATO") return "historical-approved";
        if (stato === "RESPINTO") return "historical-rejected";
        if (stato === "BOZZA") return "historical-draft";
        return "historical-requested";
    }

    function historicalMetaText(ymd) {
        const hist = getHistoricalInfo(ymd);
        if (!hist) return "";

        const stato = (hist.stato || "").toUpperCase();

        if (stato === "APPROVATO") return "✓";
        if (stato === "RESPINTO") return "✕";
        if (stato === "BOZZA") return "•";
        return "!";
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

        const stato = String(currentState || "").toUpperCase();
        const currentId = parseInt($("#richiesta_id").val() || "0", 10);

        const isBozza = (stato === "BOZZA");
        const isInviato = (stato === "INVIATO" || stato === "INVIATA");
        const isApprovato = (
            stato === "APPROVATO" ||
            stato === "APPROVATA" ||
            stato === "APPROVATO_PARZIALE"
        );

        $("#ferie_note").prop("readonly", isReadOnly);

        if (isBozza) {
            $("#btn_delete_bozza_ferie").toggle(currentId > 0);
            $("#btn_cancel_ferie").show();
            $("#btn_save_bozza_ferie").show().prop("disabled", false);
            $("#btn_invia_ferie").show().prop("disabled", false);
            $("#btn_rimetti_bozza_ferie").hide();
        } else if (isInviato) {
            $("#btn_delete_bozza_ferie").hide();
            $("#btn_cancel_ferie").show();
            $("#btn_save_bozza_ferie").hide();
            $("#btn_invia_ferie").hide();
            $("#btn_rimetti_bozza_ferie").show().prop("disabled", false);
        } else if (isApprovato) {
            $("#btn_delete_bozza_ferie").hide();
            $("#btn_cancel_ferie").show();
            $("#btn_save_bozza_ferie").hide();
            $("#btn_invia_ferie").hide();
            $("#btn_rimetti_bozza_ferie").hide();
        } else {
            $("#btn_delete_bozza_ferie").hide();
            $("#btn_cancel_ferie").show();
            $("#btn_save_bozza_ferie").hide();
            $("#btn_invia_ferie").hide();
            $("#btn_rimetti_bozza_ferie").hide();
        }

        renderCalendar();
    }

    function renderCalendar() {
        const $wrap = $("#months_wrap");
        $wrap.empty();

        if (!DATA_INIZIO || !DATA_FINE) {
            $wrap.html('<div class="alert alert-danger ferie-alert">Finestra ferie non configurata.</div>');
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

            let startOffset = first.getDay();
            startOffset = (startOffset === 0 ? 7 : startOffset);
            const emptyBefore = startOffset - 1;

            for (let i = 0; i < emptyBefore; i++) {
                html += '<div class="day-cell empty"></div>';
            }

            for (let day = 1; day <= last.getDate(); day++) {
                const d = new Date(first.getFullYear(), first.getMonth(), day, 12, 0, 0, 0);
                const ymd = toYmd(d);

                const selectable = isSelectable(ymd);
                const selected = selectedDays.has(ymd);
                const hist = getHistoricalInfo(ymd);
                const classes = ["day-cell"];

                if (!selectable && !selected) classes.push("locked");
                if (hist) classes.push(historicalCssClass(ymd));
                if (selected && !isReadOnly) classes.push("selected");
                if (selected && isReadOnly) classes.push("readonly-selected");
                if (selected && isEditableCurrentDraftDay(ymd)) classes.push("current-draft");

                let metaText = "";
                if (selected) {
                    metaText = "+";
                } else if (hist) {
                    metaText = historicalMetaText(ymd);
                }

                const meta = metaText
                    ? '<div class="day-meta status-meta">' + metaText + '</div>'
                    : '<div class="day-meta"></div>';

                const reason = !selectable && !selected
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

    function loadCalendarState(callback) {
        $.ajax({
            url: CALENDAR_STATE_URL,
            method: "GET",
            dataType: "json",
            data: {
                sottotipo: SOTTOTIPO,
                edit_id: $("#richiesta_id").val() || EDIT_ID || 0
            },
            success: function (r) {
                if (!r || r.ok !== true) {
                    historicalDaysMap = {};
                    currentDraftDaysMap = {};
                    if (callback) callback();
                    return;
                }

                historicalDaysMap = r.giorni_storici || {};
                currentDraftDaysMap = r.giorni_edit_corrente || {};
                if (callback) callback();
            },
            error: function () {
                historicalDaysMap = {};
                currentDraftDaysMap = {};
                if (callback) callback();
            }
        });
    }

    function loadRequest(id) {
        hideError();

        $.ajax({
            url: "ferieRichiestaReadDetails.php",
            method: "POST",
            dataType: "json",
            data: {
                id: id,
                sottotipo: SOTTOTIPO
            },
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

                $("#editor_title").text(TITOLO + " #" + req.id);

                loadCalendarState(function () {
                    setReadonly(currentState !== "BOZZA");
                    updateHeaderInfo();
                });
            },
            error: function (xhr) {
                showError("Errore server: " + xhr.status);
            }
        });
    }

    function deleteRequest(id) {
        if (!confirm("Vuoi eliminare questa bozza?")) return;

        $.ajax({
            url: "ferieRichiestaDelete.php",
            method: "POST",
            dataType: "json",
            data: {
                id: id,
                sottotipo: SOTTOTIPO
            },
            success: function (r) {
                if (!r || r.ok !== true) {
                    notifyCentered("danger", TITOLO, (r && r.error) ? r.error : "Errore eliminazione.", 5000);
                    return;
                }

                notifyCentered("info", TITOLO, "Bozza eliminata.", 2200);
                window.location.href = "ferieRichiesta.php?sottotipo=" + encodeURIComponent(SOTTOTIPO);
            },
            error: function (xhr) {
                notifyCentered("danger", TITOLO, "Errore server: " + xhr.status, 5000);
            }
        });
    }

    function rimettiInBozza(id) {
        if (!confirm("Vuoi rimettere questa richiesta in bozza?")) return;

        $.ajax({
            url: "ferieRichiestaRimettiBozza.php",
            method: "POST",
            dataType: "json",
            data: {
                id: id,
                sottotipo: SOTTOTIPO
            },
            success: function (r) {
                if (!r || r.ok !== true) {
                    notifyCentered("danger", TITOLO, (r && r.error) ? r.error : "Errore aggiornamento stato.", 5000);
                    return;
                }

                notifyCentered("info", TITOLO, "Richiesta rimessa in bozza.", 2200);
                window.location.href = "ferieRichiesta.php?sottotipo=" + encodeURIComponent(SOTTOTIPO) + "&id=" + encodeURIComponent(id);
            },
            error: function (xhr) {
                notifyCentered("danger", TITOLO, "Errore server: " + xhr.status, 5000);
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
            url: "ferieRichiestaSave.php",
            method: "POST",
            dataType: "json",
            data: {
                richiesta_id: $("#richiesta_id").val() || "",
                permesso_tipo_id: FERIE_TIPO_ID,
                ferie_sottotipo: SOTTOTIPO,
                note: $("#ferie_note").val() || "",
                azione: azione,
                giorni_json: JSON.stringify(giorni)
            },
            success: function (r) {
                if (!r || r.ok !== true) {
                    showError((r && r.error) ? r.error : "Errore salvataggio.");
                    notifyCentered("danger", TITOLO, (r && r.error) ? r.error : "Errore salvataggio.", 5000);
                    return;
                }

                notifyCentered(
                    "info",
                    TITOLO,
                    (azione === "INVIA") ? "Richiesta inviata." : "Bozza salvata.",
                    1800
                );

                setTimeout(function () {
                    window.location.href = "permessi.php";
                }, 900);
            },
            error: function (xhr) {
                const msg = "Errore server: " + xhr.status;
                showError(msg);
                notifyCentered("danger", TITOLO, msg, 5000);
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

    $(document).on("click", "#btn_delete_bozza_ferie", function () {
        const id = parseInt($("#richiesta_id").val() || "0", 10);
        if (id > 0) deleteRequest(id);
    });

    $(document).on("click", "#btn_rimetti_bozza_ferie", function () {
        const id = parseInt($("#richiesta_id").val() || "0", 10);
        if (id > 0) rimettiInBozza(id);
    });

    $(document).on("click", "#btn_cancel_ferie", function () {
        window.location.href = "permessi.php";
    });

    $(document).on("click", "#btn_save_bozza_ferie", function () {
        saveRequest("BOZZA");
    });

    $(document).on("click", "#btn_invia_ferie", function () {
        saveRequest("INVIA");
    });

    $(function () {
        updateHeaderInfo();

        if (EDIT_ID > 0) {
            loadRequest(EDIT_ID);
        } else {
            loadCalendarState(function () {
                renderCalendar();
                setReadonly(false);
            });
        }
    });
})();