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

// -------------------------
// Gestione impersonamenti
// -------------------------
if (haRuolo('admin')) {
    if (impersonaRuolo('docente')) {
        info("Torno dalla sessione docente ad admin");
        unset($_SESSION['docente_id'], $_SESSION['docente_nome'], $_SESSION['docente_cognome'], $_SESSION['docente_email']);
        redirect('/index.php');
    }
    if (impersonaRuolo('studente')) {
        info("Torno dalla sessione studente ad admin");
        unset($_SESSION['studente_id'], $_SESSION['studente_nome'], $_SESSION['studente_cognome'], $_SESSION['studente_email'], $_SESSION['studente_codice_fiscale']);
        redirect('/index.php');
    }
    if (impersonaRuolo('genitore')) {
        info("Torno dalla sessione genitore ad admin");
        unset($_SESSION['genitore_id'], $_SESSION['genitore_nome'], $_SESSION['genitore_cognome'], $_SESSION['genitore_email'], $_SESSION['genitore_codice_fiscale']);
        redirect('/index.php');
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
