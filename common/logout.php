<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/path.php';
require_once __DIR__ . '/__Log.php';
require_once __DIR__ . '/__Util.php';
require_once __DIR__ . '/checkSession.php';

$base = $_GET['base'] ?? '';

// assicuro che la sessione sia attiva
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

debug("Ruolo attuale: " . ($__utente_ruolo ?? 'n/d'));
debug("Username attuale: " . ($__username ?? 'n/d'));
debug("Docente ID attuale: " . ($__docente_id ?? 'n/d'));
debug("Studente ID attuale: " . ($__studente_id ?? 'n/d'));
debug("Genitore ID attuale: " . ($__genitore_id ?? 'n/d'));

function close_or_redirect($fallbackPath) {
    // Prova a chiudere la finestra (se aperta da popup), altrimenti redirect
    // NB: niente HTML complicato, ma mettiamo anche un link cliccabile.
    $fallbackPath = $fallbackPath ?: '/index.php';
    echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
    echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($fallbackPath, ENT_QUOTES) . '">';
    echo '</head><body>';
    echo '<script>';
    echo 'try { window.close(); } catch(e) {}';
    echo 'window.location.href = ' . json_encode($fallbackPath) . ';';
    echo '</script>';
    echo '<p>Reindirizzamento in corso... Se non accade, <a href="' . htmlspecialchars($fallbackPath, ENT_QUOTES) . '">clicca qui</a>.</p>';
    echo '</body></html>';
    exit;
}

// -------------------------
// Gestione impersonamenti
// -------------------------
if (haRuolo('admin')) {
    if (impersonaRuolo('docente')) {
        info("Torno dalla sessione docente ad admin");
        unset($_SESSION['docente_id'], $_SESSION['docente_nome'], $_SESSION['docente_cognome'], $_SESSION['docente_email']);

        // ✅ IMPORTANTISSIMO: se hai flag impersona_* (come quelli che ti ho fatto aggiungere)
        unset($_SESSION['impersona_attiva'], $_SESSION['impersona_ruolo'], $_SESSION['impersona_docente_id']);

        // fallback: torna in admin (o dove preferisci)
        close_or_redirect('/index.php');
    }

    if (impersonaRuolo('studente')) {
        info("Torno dalla sessione studente ad admin");
        unset($_SESSION['studente_id'], $_SESSION['studente_nome'], $_SESSION['studente_cognome'], $_SESSION['studente_email'], $_SESSION['studente_codice_fiscale']);

        unset($_SESSION['impersona_attiva'], $_SESSION['impersona_ruolo'], $_SESSION['impersona_studente_id']);

        close_or_redirect('/index.php');
    }

    if (impersonaRuolo('genitore')) {
        info("Torno dalla sessione genitore ad admin");
        unset($_SESSION['genitore_id'], $_SESSION['genitore_nome'], $_SESSION['genitore_cognome'], $_SESSION['genitore_email'], $_SESSION['genitore_codice_fiscale']);

        unset($_SESSION['impersona_attiva'], $_SESSION['impersona_ruolo'], $_SESSION['impersona_genitore_id']);

        close_or_redirect('/index.php');
    }
}

// -------------------------
// Logout completo
// -------------------------
$__username = $session->get('username');
info("Logout avviato per utente [$__username]");

// Pulisce TUTTO (variabili + cookie + distrugge sessione)
$session->logout();

// Redirect finale
redirect('/index.php');
exit;
