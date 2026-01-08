<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// favicon ref
echo '<link rel="icon" href="../ore-32.png" />';
echo '<link rel="stylesheet" href="../css/releaseversion.css">';
?>

<script>
(function () {
  function redirectToLogin() {
    // porta alla home di login (da te è index.php)
    window.location.href = "<?php echo $__application_base_path; ?>/index.php";
  }

  function onReady(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn);
    } else {
      fn();
    }
  }

  onReady(function () {
    // Se jQuery non c'è, non facciamo nulla (ma nel tuo progetto c'è)
    if (!window.jQuery) return;

    // intercetta TUTTE le chiamate ajax
    $(document).ajaxComplete(function (event, xhr) {
      try {
        // 1) Caso migliore: backend risponde 401 JSON
        if (xhr && xhr.status === 401) {
          // se è JSON con redirect
          try {
            var r = JSON.parse(xhr.responseText || "{}");
            if (r && r.redirect) {
              window.location.href = r.redirect;
              return;
            }
          } catch (e) {}
          redirectToLogin();
          return;
        }

        // 2) Fallback: se torna HTML e contiene form/login (vecchi endpoint / redirect “trasparente”)
        var ct = (xhr.getResponseHeader && xhr.getResponseHeader("Content-Type")) ? xhr.getResponseHeader("Content-Type") : "";
        if (ct.indexOf("text/html") !== -1) {
          var t = (xhr.responseText || "").toLowerCase();
          if (t.includes("glogin") || t.includes("google") && t.includes("auth") || t.includes("login") || t.includes("password")) {
            redirectToLogin();
            return;
          }
        }
      } catch (err) {
        // in caso di errori strani, fallback soft
      }
    });

    // opzionale: intercetta errori ajax (es. network)
    $(document).ajaxError(function (event, xhr) {
      if (xhr && xhr.status === 401) {
        redirectToLogin();
      }
    });
  });
})();
</script>
