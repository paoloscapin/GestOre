<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// favicon ref
echo '<link rel="icon" href="' . $__application_base_path . '/ore-32.png" />';
echo '<link rel="stylesheet" href="' . $__application_base_path . '/css/releaseversion.css?' . time() . '" />';
?>
<script src="<?php echo $__application_base_path; ?>/common/jquery-3.3.1-dist/jquery-3.3.1.min.js"></script>

<script>

  (function () {

  // intercetta tutte le chiamate ajax
  $(document).ajaxSend(function (e, xhr, options) {
    console.log("[GLOBAL ajaxSend]", options.type, options.url, options.data);
  });

  $(document).ajaxSuccess(function (e, xhr, options) {
    console.log("[GLOBAL ajaxSuccess]", options.url, xhr.status, xhr.getResponseHeader("content-type"));
  });

  $(document).ajaxError(function (e, xhr, options, err) {
    console.error("[GLOBAL ajaxError]", options.url, xhr.status, err, (xhr.responseText || "").substring(0, 200));
  });

  // intercetta cambi pagina "strani"
  const _assign = window.location.assign.bind(window.location);
  window.location.assign = function (url) {
    console.warn("[location.assign]", url, new Error().stack);
    return _assign(url);
  };

  const _replace = window.location.replace.bind(window.location);
  window.location.replace = function (url) {
    console.warn("[location.replace]", url, new Error().stack);
    return _replace(url);
  };

  // se qualcuno fa window.location.href = ...
  // non si può patchare direttamente href, ma logghiamo unload
  window.addEventListener("beforeunload", function () {
    console.warn("[beforeunload] leaving page to:", window.location.href);
  });

})();

(function () {
  function redirectToLogin(url) {
    // fallback: index generale dell’app
    window.location.href = url || ("<?php echo $__application_base_path; ?>/index.php");
  }

  function onReady(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn);
    } else {
      fn();
    }
  }

  onReady(function () {
    if (!window.jQuery) return;

    $(document).ajaxComplete(function (event, xhr, settings) {
      try {
        // 1) Caso corretto: sessione scaduta -> 401
        if (xhr && xhr.status === 401) {
          // se è JSON, prova a leggere redirect
          var ct = (xhr.getResponseHeader && xhr.getResponseHeader("Content-Type")) ? xhr.getResponseHeader("Content-Type") : "";
          if (ct.toLowerCase().indexOf("application/json") !== -1) {
            try {
              var r = JSON.parse(xhr.responseText || "{}");
              if (r && r.redirect) return redirectToLogin(r.redirect);
            } catch(e) {}
          }
          return redirectToLogin();
        }

        // 2) Secondo caso: backend risponde 200 ma JSON indica session expired (difesa extra)
        var ct2 = (xhr.getResponseHeader && xhr.getResponseHeader("Content-Type")) ? xhr.getResponseHeader("Content-Type") : "";
        if (ct2.toLowerCase().indexOf("application/json") !== -1) {
          try {
            var r2 = (typeof xhr.responseJSON === "object" && xhr.responseJSON) ? xhr.responseJSON : JSON.parse(xhr.responseText || "{}");
            if (r2 && r2.reason === "SESSION_EXPIRED") {
              return redirectToLogin(r2.redirect);
            }
          } catch(e) {}
        }

        // ❌ STOP: niente euristiche su "login/google/auth" perché generano falsi positivi
      } catch (err) {
        // non fare redirect in caso di errori di parsing: meglio non interferire
      }
    });

    $(document).ajaxError(function (event, xhr) {
      if (xhr && xhr.status === 401) redirectToLogin();
    });
  });
})();
</script>
<script>
(function () {

  function updateHeaderOffset() {
    var header = document.querySelector('.navbar-fixed-top');
    if (!header) return;

    var h = header.getBoundingClientRect().height;

    // Variabile CSS globale
    document.documentElement.style.setProperty(
      '--header-offset',
      (h + 10) + 'px' // +10px di respiro
    );
  }

  // iniziale
  window.addEventListener('load', updateHeaderOffset);

  // resize (rotazione, ridimensionamento)
  window.addEventListener('resize', updateHeaderOffset);

  // bootstrap: menu che collassa/si espande
  document.addEventListener('shown.bs.collapse', updateHeaderOffset);
  document.addEventListener('hidden.bs.collapse', updateHeaderOffset);

})();
</script>

<script>
(function () {

  const APP_BASE = "<?php echo $__application_base_path; ?>";
  const VAPID_PUBLIC_KEY = "<?php echo htmlspecialchars((string)($__settings->notifiche->vapid->publicKey ?? ''), ENT_QUOTES, 'UTF-8'); ?>";
  const PUSH_USER_LOGGED = <?php echo isset($_SESSION['username']) && $_SESSION['username'] !== '' ? 'true' : 'false'; ?>;

  function isPushSupported() {
    return (
      "serviceWorker" in navigator &&
      "PushManager" in window &&
      "Notification" in window
    );
  }

  function isIOS() {
    return /iphone|ipad|ipod/i.test(navigator.userAgent);
  }

  function isStandalonePWA() {
    return window.matchMedia("(display-mode: standalone)").matches ||
           window.navigator.standalone === true;
  }

  function urlBase64ToUint8Array(base64String) {
    const padding = "=".repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/");
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }

    return outputArray;
  }

  function createPushBanner() {
    if (document.getElementById("gestorePushBanner")) return;

    const box = document.createElement("div");
    box.id = "gestorePushBanner";
    box.className = "alert alert-info";
    box.style.cssText = `
      display:none;
      position:fixed;
      top:70px;
      left:15px;
      right:15px;
      z-index:99999;
      box-shadow:0 4px 12px rgba(0,0,0,.18);
    `;

    let extraText = "";
    if (isIOS() && !isStandalonePWA()) {
      extraText = `
        <br><small>
          Su iPhone/iPad le notifiche funzionano solo dopo aver aggiunto GestOre alla schermata Home.
        </small>
      `;
    }

    box.innerHTML = `
      <strong>Vuoi attivare le notifiche di GestOre?</strong><br>
      Riceverai avvisi importanti anche su PC o telefono.
      ${extraText}
      <div style="margin-top:10px;">
        <button type="button" id="gestoreBtnEnablePush" class="btn btn-primary btn-sm">
          Attiva notifiche
        </button>
        <button type="button" id="gestoreBtnDismissPush" class="btn btn-default btn-sm">
          Non ora
        </button>
      </div>
    `;

    document.body.appendChild(box);
  }

  async function enablePush() {
    try {
      const permission = await Notification.requestPermission();

      if (permission !== "granted") {
        alert("Notifiche non attivate.");
        return;
      }

      const registration = await navigator.serviceWorker.register(
        APP_BASE + "/service-worker.js"
      );

      const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
      });

      const response = await fetch(APP_BASE + "/common/save_push_subscription.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json"
        },
        body: JSON.stringify(subscription)
      });

      if (!response.ok) {
        let errorText = "";
        try {
          const errorJson = await response.json();
          errorText = errorJson.error || JSON.stringify(errorJson);
        } catch (ignore) {
          errorText = await response.text();
        }
        throw new Error("Errore salvataggio subscription: " + errorText);
      }

      localStorage.setItem("gestore_push_choice", "enabled");
      $("#gestorePushBanner").hide();

      alert("Notifiche attivate correttamente.");

    } catch (e) {
      console.error("[GestOre Push]", e);
      alert("Errore durante l'attivazione delle notifiche.");
    }
  }

  async function hasActivePushSubscription() {
    if (Notification.permission !== "granted") return false;

    const registration = await navigator.serviceWorker.getRegistration(APP_BASE + "/service-worker.js")
      || await navigator.serviceWorker.getRegistration(APP_BASE + "/");

    if (!registration) return false;

    const subscription = await registration.pushManager.getSubscription();
    return !!subscription;
  }

  $(document).ready(async function () {

    if (!isPushSupported()) return;

    if (!PUSH_USER_LOGGED) return;

    if (!VAPID_PUBLIC_KEY) {
      console.warn("[GestOre Push] VAPID public key non configurata.");
      return;
    }

    if (await hasActivePushSubscription()) {
      localStorage.setItem("gestore_push_choice", "enabled");
      return;
    }

    if (Notification.permission === "denied") return;

    if (localStorage.getItem("gestore_push_choice") === "dismissed") return;

    createPushBanner();

    $("#gestorePushBanner").show();

    $("#gestoreBtnDismissPush").on("click", function () {
      localStorage.setItem("gestore_push_choice", "dismissed");
      $("#gestorePushBanner").hide();
    });

    $("#gestoreBtnEnablePush").on("click", function () {
      enablePush();
    });

  });

})();
</script>
