(function () {
  if (!window.ORARIO_GOOGLE_CALENDAR_DOCENTI_SELF_SERVICE) return;

  const $box = $("#google_calendar_docenti_box");
  const $status = $("#google_calendar_docenti_status");
  const $enable = $("#btn_google_calendar_docenti_enable");
  const $disable = $("#btn_google_calendar_docenti_disable");
  const $force = $("#btn_google_calendar_docenti_force");

  function setBusy(isBusy) {
    $enable.prop("disabled", isBusy);
    $disable.prop("disabled", isBusy);
    $force.prop("disabled", isBusy);
    $box.toggleClass("is-busy", isBusy);
  }

  function fmtDateTime(value) {
    if (!value) return "";
    return String(value).replace(/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}:\d{2}).*$/, "$3/$2/$1 $4");
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
      text += " · ultimo sync manuale " + fmtDateTime(data.lastManualSyncAt);
    } else if (enabled && data.lastCronSyncAt) {
      text += " · ultimo sync automatico " + fmtDateTime(data.lastCronSyncAt);
    }
    if (enabled && data.lastSyncFrom && data.lastSyncTo) {
      text += " · periodo " + data.lastSyncFrom + " / " + data.lastSyncTo;
    }
    if (data.lastError) {
      text += " · errore: " + data.lastError;
    }

    $status.text(text);
    $force.html(
      '<span class="glyphicon glyphicon-refresh"></span>&ensp;Sync ultimi ' +
      (data.manualPastDays || 15) +
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

  function request(action, busyText) {
    setBusy(true);
    if (busyText) $status.text(busyText);

    return $.ajax({
      url: "googleCalendarDocentiSelfService.php",
      method: "POST",
      dataType: "json",
      data: { action }
    }).done(function (data) {
      if (!data || data.ok === false) {
        notify("danger", (data && data.error) ? data.error : "Operazione non riuscita.");
        return;
      }
      renderStatus(data);
    }).fail(function (xhr) {
      const data = xhr.responseJSON || {};
      notify("danger", data.error || "Errore Google Calendar.");
    }).always(function () {
      setBusy(false);
    });
  }

  $enable.on("click", function () {
    request("enable", "Attivazione e primo sync in corso...");
  });
  $disable.on("click", function () {
    request("disable", "Disattivazione in corso...");
  });
  $force.on("click", function () {
    request("force_sync", "Sync manuale in corso...");
  });

  request("status", "Verifica stato...");
})();
