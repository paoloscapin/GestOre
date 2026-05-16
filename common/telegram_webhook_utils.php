<?php
 
/**
 * ===========================
 * FUNZIONI UTILI
 * ===========================
 */

// Funzione per normalizzare un valore
function tgNorm($s) {
    // Se il valore è null restituisce stringa vuota, altrimenti lo converte in stringa e fa trim
    return $s === null ? '' : trim((string)$s);
}

// Funzione per troncare un testo ad una lunghezza massima
function tgCut($text, $maxLen = 3000) {
    // Converte il valore in stringa
    $text = (string)$text;

    // Se la lunghezza del testo è minore o uguale al limite lo restituisce intero,
    // altrimenti lo taglia e aggiunge "…"
    return mb_strlen($text, 'UTF-8') <= $maxLen ? $text : mb_substr($text, 0, $maxLen, 'UTF-8') . '…';
}

function tgAppendTicketUserText($previousText, $newText) {
    $previousText = tgNorm($previousText);
    $newText = tgNorm($newText);

    if ($newText === '') {
        return $previousText;
    }
    if ($previousText === '') {
        return $newText;
    }
    if ($previousText === $newText) {
        return $previousText;
    }

    return $previousText . "\n\n--- Messaggio successivo ---\n" . $newText;
}

function tgTicketUserTextSeparator() {
    return "\n\n--- Messaggio successivo ---\n";
}

function tgSplitTicketUserText($text) {
    $text = tgNorm($text);
    if ($text === '') {
        return [];
    }

    $parts = explode(tgTicketUserTextSeparator(), $text);
    return array_values(array_filter(array_map('tgNorm', $parts), function ($part) {
        return $part !== '';
    }));
}

function tgGetLatestTicketUserText($text) {
    $parts = tgSplitTicketUserText($text);
    if (!$parts) {
        return '';
    }

    return $parts[count($parts) - 1];
}

function tgRemoveLatestTicketUserText($text) {
    $parts = tgSplitTicketUserText($text);
    if (count($parts) <= 1) {
        return tgNorm($text);
    }

    array_pop($parts);
    return implode(tgTicketUserTextSeparator(), $parts);
}

// Funzione per costruire il nome visualizzato di un utente Telegram
function tgUserDisplayName(array $from) {
    // Costruisce il nome concatenando first_name e last_name (se presenti)
    $nome = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));

    // Se il nome non è vuoto lo restituisce, altrimenti usa username o fallback "Utente Telegram"
    return $nome !== '' ? $nome : trim($from['username'] ?? 'Utente Telegram');
}

// Funzione per ottenere la label leggibile dello stato del ticket
function tgBuildStatoLabel($stato) {
    // Normalizza lo stato e lo converte in maiuscolo
    $stato = strtoupper(tgNorm($stato));

    // Se lo stato è IN_GESTIONE restituisce etichetta con emoji gialla
    if ($stato === 'IN_GESTIONE') return '🟡 IN GESTIONE';

    // Se lo stato è CHIUSA restituisce etichetta con emoji verde
    if ($stato === 'CHIUSA') return '✅ CHIUSA';

    // In tutti gli altri casi restituisce stato APERTA con emoji blu
    return '🔵 APERTA';
}

// Funzione per costruire il codice ticket leggibile
function tgBuildTicketCode($idRelay) {
    // Converte l'id in intero
    $idRelay = (int)$idRelay;

    // Costruisce il codice nel formato TCK-AAAAMMGG-ID
    return 'TCK-' . date('dmY') . '-' . $idRelay;
}

// Funzione per aggiornare il ticket_code nel database
function tgUpdateTicketCode($idRelay) {
    // Converte l'id in intero
    $idRelay = (int)$idRelay;

    // Se l'id non è valido (<= 0) restituisce stringa vuota
    if ($idRelay <= 0) return '';

    // Genera il codice ticket
    $ticketCode = tgBuildTicketCode($idRelay);

    // Costruisce la query SQL di aggiornamento
    $q = "UPDATE docente_telegram_relay SET ticket_code = " . dbQ($ticketCode) . " WHERE id = " . dbI($idRelay);

    // Log della query per debug
    infoTelegram("tgUpdateTicketCode: query=$q");

    // Esegue la query sul database
    dbExec($q);

    // Restituisce il codice ticket generato
    return $ticketCode;
}

?>
