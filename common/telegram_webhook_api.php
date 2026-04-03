<?php 

/**
 * =============================
 * TELEGRAM API
 * =============================
 * 
 * Questo blocco contiene le funzioni base per interagire con le API di Telegram:
 * - invio messaggi
 * - modifica messaggi
 * - risposta ai callback dei pulsanti inline
 */

/**
 * ------------------------------------------------------------
 * FUNZIONE: tgSendMessage
 * ------------------------------------------------------------
 * Invia un messaggio ad una chat Telegram (utente o gruppo)
 * 
 * Parametri:
 * - $botToken: token del bot Telegram
 * - $chatId: ID della chat destinataria
 * - $text: testo del messaggio
 * - $extra: array opzionale con parametri aggiuntivi (es. reply_markup, parse_mode, ecc.)
 */
function tgSendMessage($botToken, $chatId, $text, array $extra = []) {

    // Costruzione URL API Telegram per invio messaggio
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

    // Payload base + eventuali parametri extra
    $payload = array_merge([
        'chat_id' => $chatId,
        'text' => $text
    ], $extra);

    // Inizializzazione chiamata cURL
    $ch = curl_init($url);

    // Configurazione richiesta POST
    curl_setopt($ch, CURLOPT_POST, true);

    // Restituisce la risposta come stringa invece di stamparla
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Invio dati POST (form-urlencoded)
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));

    // Timeout massimo della richiesta (secondi)
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    // Esecuzione chiamata
    $response = curl_exec($ch);

    // Verifica eventuali errori cURL
    $errno = curl_errno($ch);
    $error = curl_error($ch);

    // Chiusura connessione
    curl_close($ch);

    // Se errore cURL → log e ritorno errore
    if ($errno) {
        errorTelegram("tgSendMessage: curl error=$error");
        return ['ok'=>false,'error'=>$error];
    }

    // Decodifica JSON risposta Telegram
    $json = json_decode($response,true);

    // Log risposta completa
    infoTelegram("tgSendMessage: chatId=$chatId response=" . json_encode($json));

    // Se risposta valida → ritorna OK con dati utili
    return is_array($json) && !empty($json['ok'])
        ? [
            'ok'=>true,
            'json'=>$json,
            'message_id'=>$json['result']['message_id']??0 // utile per reply o tracking
          ]
        : [
            'ok'=>false,
            'error'=>$response
          ];
}


/**
 * ------------------------------------------------------------
 * FUNZIONE: tgEditMessage
 * ------------------------------------------------------------
 * Modifica un messaggio già inviato dal bot
 * 
 * Parametri:
 * - $botToken: token del bot
 * - $chatId: chat dove si trova il messaggio
 * - $messageId: ID del messaggio da modificare
 * - $text: nuovo testo
 * - $extra: parametri extra (es. reply_markup per aggiornare bottoni)
 */
function tgEditMessage($botToken, $chatId, $messageId, $text, array $extra = []) {

    // Endpoint API per modifica messaggi
    $url = "https://api.telegram.org/bot{$botToken}/editMessageText";

    // Payload base + parametri extra
    $payload = array_merge([
        'chat_id'=>$chatId,
        'message_id'=>$messageId,
        'text'=>$text
    ],$extra);

    // Inizializzazione cURL
    $ch = curl_init($url);

    // Configurazione POST
    curl_setopt($ch,CURLOPT_POST,true);

    // Restituisce risposta
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);

    // Invio payload
    curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($payload));

    // Timeout
    curl_setopt($ch,CURLOPT_TIMEOUT,20);

    // Esecuzione richiesta
    $response = curl_exec($ch);

    // Chiusura
    curl_close($ch);

    // Decodifica risposta
    $json = json_decode($response,true);

    // Log
    infoTelegram("tgEditMessage: chatId=$chatId messageId=$messageId response=" . json_encode($json));

    // Ritorno risultato
    return is_array($json) && !empty($json['ok'])
        ? ['ok'=>true,'json'=>$json]
        : ['ok'=>false,'error'=>$response];
}


/**
 * ------------------------------------------------------------
 * FUNZIONE: tgAnswerCallbackQuery
 * ------------------------------------------------------------
 * Risponde ad un callback di un pulsante inline (IMPORTANTISSIMO)
 * 
 * Serve per:
 * - evitare il "loading infinito" su Telegram
 * - mostrare un messaggio popup opzionale
 * 
 * Parametri:
 * - $botToken: token bot
 * - $callbackQueryId: ID del callback ricevuto
 * - $text: messaggio opzionale da mostrare all'utente
 */
function tgAnswerCallbackQuery($botToken, $callbackQueryId, $text='') {

    // Se mancano dati essenziali → esco
    if ($botToken===''||$callbackQueryId==='') return;

    // Endpoint Telegram
    $url = "https://api.telegram.org/bot{$botToken}/answerCallbackQuery";

    // Payload base
    $payload = [
        'callback_query_id'=>$callbackQueryId
    ];

    // Se c'è testo → aggiungo al payload
    if ($text!=='') $payload['text'] = $text;

    // cURL init
    $ch = curl_init($url);

    // POST
    curl_setopt($ch,CURLOPT_POST,true);

    // risposta in stringa
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);

    // invio dati
    curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($payload));

    // timeout
    curl_setopt($ch,CURLOPT_TIMEOUT,20);

    // esecuzione (nota: non serve leggere risposta)
    curl_exec($ch);

    // chiusura
    curl_close($ch);

    // log debug
    infoTelegram("tgAnswerCallbackQuery: callbackQueryId=$callbackQueryId text=[".$text."]");
}

?>