<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// favicon ref
echo '<link rel="icon" href="../ore-32.png" />';
echo '<link rel="stylesheet" href="../css/releaseversion.css">';
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
