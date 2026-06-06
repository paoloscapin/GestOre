(function () {
    "use strict";

    function escapeHtml(s) {
        s = s == null ? "" : String(s);
        return s.replace(/[&<>"']/g, function (c) {
            return {
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                "\"": "&quot;",
                "'": "&#39;"
            }[c];
        });
    }

    function boxEl() {
        return document.getElementById("genitore-telegram-status-box");
    }

    function showBox(html) {
        var el = boxEl();
        if (!el) return;
        el.innerHTML = html;
    }

    function showError(msg) {
        showBox('<div class="alert alert-danger">' + escapeHtml(msg) + '</div>');
    }

    function parseJsonSafe(text) {
        try {
            return JSON.parse(text);
        } catch (e) {
            return null;
        }
    }

    function requestJson(url, options) {
        options = options || {};
        var fetchOptions = {
            method: options.method || "GET",
            headers: Object.assign({
                "Accept": "application/json",
                "X-Requested-With": "XMLHttpRequest"
            }, options.headers || {}),
            credentials: "same-origin"
        };

        if (options.body) {
            fetchOptions.body = options.body;
        }

        return fetch(url, fetchOptions).then(function (response) {
            return response.text().then(function (text) {
                var data = parseJsonSafe(text);
                if (!response.ok) {
                    var errorMessage = (data && data.error) ? data.error : ("Errore HTTP " + response.status);
                    throw new Error(errorMessage);
                }
                if (!data) {
                    throw new Error("Risposta non valida dal server");
                }
                return data;
            });
        });
    }

    function renderStatus(status) {
        if (!status || status.ok !== true) {
            showError((status && status.error) ? status.error : "Errore caricamento stato Telegram");
            return;
        }

        if (!status.telegramReady) {
            showBox(
                '<div class="alert alert-warning">' +
                '<strong>Configurazione incompleta.</strong> Le tabelle Telegram genitori non sono ancora presenti nel database.' +
                '</div>'
            );
            return;
        }

        var hasProfile = !!status.hasTelegramProfile;
        var enabled = !!status.enabled;
        var email = escapeHtml(status.email || "");
        var stateBadge = enabled
            ? '<span class="label label-success">Attive</span>'
            : '<span class="label label-default">Disattive</span>';

        if (!hasProfile) {
            showBox(
                '<div class="panel panel-default">' +
                '<div class="panel-body">' +
                '<div style="font-weight:700;font-size:16px;margin-bottom:8px;">Collegamento Telegram non ancora attivo</div>' +
                '<p>La mail attuale del profilo è <strong>' + email + '</strong>.</p>' +
                '<p>Premi il pulsante qui sotto: riceverai una mail con il link personale per avviare il bot Telegram e completare il collegamento.</p>' +
                '<button type="button" id="btn-send-genitore-telegram-link" class="btn btn-primary">' +
                '<span class="glyphicon glyphicon-envelope"></span> Inviami il link di collegamento' +
                '</button>' +
                '</div></div>'
            );
            return;
        }

        var toggleText = enabled ? 'Disabilita notifiche Telegram' : 'Abilita notifiche Telegram';
        var toggleClass = enabled ? 'btn-danger' : 'btn-success';

        showBox(
            '<div class="panel panel-default">' +
            '<div class="panel-body">' +
            '<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">' +
            '<div>' +
            '<div style="font-weight:700;font-size:16px;">Profilo Telegram collegato</div>' +
            '<div style="margin-top:8px;">' + stateBadge + '</div>' +
            '<div style="margin-top:8px;color:#666;">Le notifiche generali Telegram del genitore sono attualmente ' + (enabled ? 'attive' : 'disattive') + '.</div>' +
            '</div>' +
            '<div>' +
            '<button type="button" id="btn-toggle-genitore-telegram" class="btn ' + toggleClass + '" data-enabled="' + (enabled ? '1' : '0') + '">' +
            '<span class="glyphicon glyphicon-send"></span> ' + escapeHtml(toggleText) +
            '</button>' +
            '</div>' +
            '</div>' +
            '</div></div>'
        );
    }

    function loadStatus() {
        showBox('<div class="alert alert-default">Caricamento stato Telegram in corso...</div>');
        requestJson("genitoreTelegramStatus.php")
            .then(renderStatus)
            .catch(function (err) {
                console.error("[genitore telegram] loadStatus", err);
                showError(err && err.message ? err.message : "Errore caricamento stato Telegram");
            });
    }

    function postForm(url, formData) {
        return requestJson(url, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
            },
            body: formData.toString()
        });
    }

    document.addEventListener("click", function (event) {
        var sendBtn = event.target.closest("#btn-send-genitore-telegram-link");
        if (sendBtn) {
            sendBtn.disabled = true;
            sendBtn.textContent = "Invio in corso...";

            postForm("genitoreTelegramSendLink.php", new URLSearchParams())
                .then(function (r) {
                    window.alert(r.message || "Mail inviata correttamente");
                    loadStatus();
                })
                .catch(function (err) {
                    console.error("[genitore telegram] sendLink", err);
                    window.alert(err && err.message ? err.message : "Errore invio link Telegram");
                    loadStatus();
                });
            return;
        }

        var toggleBtn = event.target.closest("#btn-toggle-genitore-telegram");
        if (toggleBtn) {
            var enabledNow = parseInt(toggleBtn.getAttribute("data-enabled"), 10) === 1;
            var enabledNext = enabledNow ? 0 : 1;

            toggleBtn.disabled = true;
            toggleBtn.textContent = "Salvataggio...";

            postForm("genitoreTelegramToggle.php", new URLSearchParams({ enabled: String(enabledNext) }))
                .then(function (r) {
                    window.alert(r.message || "Preferenza aggiornata");
                    loadStatus();
                })
                .catch(function (err) {
                    console.error("[genitore telegram] toggle", err);
                    window.alert(err && err.message ? err.message : "Errore aggiornamento notifiche Telegram");
                    loadStatus();
                });
        }
    });

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", loadStatus);
    } else {
        loadStatus();
    }
})();
