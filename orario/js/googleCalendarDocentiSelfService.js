(function () {
  if (!window.ORARIO_GOOGLE_CALENDAR_DOCENTI_SELF_SERVICE) return;

  const $box = $("#google_calendar_docenti_box");
  const $status = $("#google_calendar_docenti_status");
  const $enable = $("#btn_google_calendar_docenti_enable");
  const $disable = $("#btn_google_calendar_docenti_disable");
  const $force = $("#btn_google_calendar_docenti_force");
  let progressTimer = null;

  function setBusy(isBusy) {
    $enable.prop("disabled", isBusy);
    $disable.prop("disabled", isBusy);
    $force.prop("disabled", isBusy);
    $box.toggleClass("is-busy", isBusy);
  }

  function ensureOverlay() {
    let $overlay = $("#google_calendar_docenti_sync_overlay");
    if ($overlay.length) return $overlay;

    $overlay = $(
      '<div id="google_calendar_docenti_sync_overlay" class="calendar-sync-overlay" style="display:none;">' +
        '<div class="calendar-sync-dialog">' +
          '<div class="calendar-sync-title">Sincronizzazione Google Calendar</div>' +
          '<div id="google_calendar_docenti_sync_message" class="calendar-sync-message"></div>' +
          '<div class="calendar-sync-progress">' +
            '<div id="google_calendar_docenti_sync_bar" class="calendar-sync-progress-bar">0%</div>' +
          '</div>' +
          '<div id="google_calendar_docenti_sync_detail" class="calendar-sync-detail"></div>' +
          '<button type="button" id="google_calendar_docenti_sync_close" class="btn btn-primary btn-sm calendar-sync-close" style="display:none;">Chiudi</button>' +
        '</div>' +
      '</div>'
    );

    $("body").append($overlay);
    $("#google_calendar_docenti_sync_close").on("click", function () {
      $overlay.fadeOut(120);
    });
    return $overlay;
  }

  function fmtDateTime(value) {
    if (!value) return "";
    const str = String(value);
    if (/^\d{4}-\d{2}-\d{2}$/.test(str)) {
      return str.replace(/^(\d{4})-(\d{2})-(\d{2})$/, "$3/$2/$1");
    }
    return str.replace(/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}:\d{2}).*$/, "$3/$2/$1 $4");
  }

  function syncPeriodText(data) {
    if (!data || !data.lastSyncFrom || !data.lastSyncTo) return "";
    return "Periodo sincronizzato: " + fmtDateTime(data.lastSyncFrom) + " - " + fmtDateTime(data.lastSyncTo);
  }

  function setOverlayProgress(percent, message, detail) {
    const pct = Math.max(0, Math.min(100, Math.round(percent)));
    $("#google_calendar_docenti_sync_bar").css("width", pct + "%").text(pct + "%");
    if (message != null) $("#google_calendar_docenti_sync_message").text(message);
    if (detail != null) $("#google_calendar_docenti_sync_detail").text(detail);
  }

  function showSyncOverlay(message, detail) {
    const $overlay = ensureOverlay();
    $("#google_calendar_docenti_sync_close").hide();
    $overlay.find(".calendar-sync-dialog").removeClass("is-error is-done");
    setOverlayProgress(3, message || "Sincronizzazione in corso, attendere...", detail || "Preparazione...");
    $overlay.stop(true, true).css("display", "flex").hide().fadeIn(120);

    let percent = 3;
    clearInterval(progressTimer);
    progressTimer = setInterval(function () {
      percent += percent < 55 ? 4 : (percent < 82 ? 2 : 1);
      if (percent > 92) percent = 92;
      setOverlayProgress(percent);
    }, 650);
  }

  function finishSyncOverlay(ok, message, detail) {
    clearInterval(progressTimer);
    progressTimer = null;
    const $dialog = ensureOverlay().find(".calendar-sync-dialog");
    $dialog.toggleClass("is-error", !ok).toggleClass("is-done", ok);
    setOverlayProgress(100, message, detail || "");
    $("#google_calendar_docenti_sync_close").show();
  }

  function renderStatus(data) {
    if (!data || data.disabled) {
      $box.hide();
      return;
    }

    $box.show();
    const enabled = !!data.enabled;
    $box.toggleClass("is-enabled", enabled);
    $enable.toggle(!enabled);
    $disable.toggle(enabled);
    $force.toggle(enabled);

    let text = enabled ? "attivo" : "non attivo";
    if (enabled && data.lastManualSyncAt) {
      text += " - ultimo sync manuale " + fmtDateTime(data.lastManualSyncAt);
    } else if (enabled && data.lastCronSyncAt) {
      text += " - ultimo sync automatico " + fmtDateTime(data.lastCronSyncAt);
    }
    if (enabled && data.lastSyncFrom && data.lastSyncTo) {
      text += " - periodo " + fmtDateTime(data.lastSyncFrom) + " / " + fmtDateTime(data.lastSyncTo);
    }
    if (data.lastError) {
      text += " - errore: " + data.lastError;
    }

    const pastDays = data.manualPastDays || 15;
    const futureDays = data.manualFutureDays || 120;
    $status.text(text);
    $force.html(
      '<span class="glyphicon glyphicon-refresh"></span>&ensp;Sync -' +
      pastDays +
      '/+' +
      futureDays +
      ' giorni'
    );
  }

  function notify(type, message) {
    if ($.notify) {
      $.notify({ message }, { type, delay: 3500, placement: { from: "top", align: "center" } });
    } else {
      $status.text(message);
    }
  }

  function request(action, busyText, overlayText, overlayDetail) {
    setBusy(true);
    if (busyText) $status.text(busyText);
    if (overlayText) showSyncOverlay(overlayText, overlayDetail);

    return $.ajax({
      url: "googleCalendarDocentiSelfService.php",
      method: "POST",
      dataType: "json",
      data: { action }
    }).done(function (data) {
      if (!data || data.ok === false) {
        notify("danger", (data && data.error) ? data.error : "Operazione non riuscita.");
        if (overlayText) finishSyncOverlay(false, "Sincronizzazione non riuscita.", (data && data.error) ? data.error : "");
        return;
      }
      renderStatus(data);
      if (overlayText) {
        finishSyncOverlay(
          true,
          action === "enable" ? "Attivazione completata." : "Sincronizzazione completata.",
          syncPeriodText(data)
        );
      }
    }).fail(function (xhr) {
      const data = xhr.responseJSON || {};
      notify("danger", data.error || "Errore Google Calendar.");
      if (overlayText) finishSyncOverlay(false, "Sincronizzazione non riuscita.", data.error || "Errore Google Calendar.");
    }).always(function () {
      setBusy(false);
    });
  }

  $enable.on("click", function () {
    request(
      "enable",
      "Attivazione e primo sync in corso...",
      "Attendere: sto sincronizzando tutto l'anno scolastico sul tuo Google Calendar.",
      "Questa operazione puo richiedere un po' di tempo."
    );
  });
  $disable.on("click", function () {
    request("disable", "Disattivazione in corso...");
  });
  $force.on("click", function () {
    request(
      "force_sync",
      "Sync manuale in corso...",
      "Attendere: sto sincronizzando gli impegni recenti e futuri.",
      "Periodo manuale: 15 giorni indietro e 120 giorni avanti."
    );
  });

  request("status", "Verifica stato...");
})();
