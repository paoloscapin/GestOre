<?php
/**
 * Logout GestOre compatibile con MBApp
 * Pulisce SOLO le variabili di GestOre e il token Google senza toccare MBApp
 * Sessione separata tramite session_name("GESTORESESSID")
 */

@session_name("GESTORESESSID"); // nome della sessione GestOre separato da MBApp
session_start();

require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/__Log.php';

$__username = $_SESSION['username'] ?? '(non definito)';
info("Logout GestOre avviato per utente [$__username]");

// =============================
// 1️⃣ Pulizia variabili GestOre e token Google
// =============================
$keysToUnset = [
    // utente applicativo
    'utente_id', 'utente_nome', 'utente_cognome', 'utente_ruolo',
    // docente
    'docente_id', 'docente_nome', 'docente_cognome', 'docente_email',
    // studente
    'studente_id', 'studente_nome', 'studente_cognome', 'studente_email', 'studente_codice_fiscale',
    // genitore
    'genitore_id', 'genitore_nome', 'genitore_cognome', 'genitore_email', 'genitore_codice_fiscale',
    // esterno
    'esterno_id', 'esterno_nome', 'esterno_cognome', 'esterno_email',
    // portineria
    'portineria_id', 'portineria_nome', 'portineria_cognome', 'portineria_email',
    // personale ATA
    'personale_ata_id', 'personale_ata_nome', 'personale_ata_cognome', 'personale_ata_email',
    // anno scolastico
    'anno_scolastico_corrente_id', 'anno_scolastico_corrente_anno', 'anno_scolastico_scorso_id',
    // impersonamenti
    'impersona_attiva', 'impersona_ruolo', 'impersona_docente_id', 'impersona_studente_id', 'impersona_genitore_id',
    // Google OAuth lato GestOre
    'token', 'access_token', 'refresh_token', 'google_user',
    // variabili di sessione generali di GestOre
    'username', '__username', '__useremail', 'LAST_ACTIVITY', 'EXPIRE_AFTER'
];

// rimuove tutte le variabili indicate
foreach ($keysToUnset as $k) {
    unset($_SESSION[$k]);
}

// =============================
// 2️⃣ NON distruggere la sessione
// MBApp continua a funzionare perché usa un cookie diverso
// session_destroy(); // ❌ NON FARLO
// =============================

// =============================
// 3️⃣ Redirect finale al login GestOre
// =============================
info("Logout GestOre completato per utente [$__username]");
header('Location: /GestOre/index.php');
exit;