<?php

/**
 * =============================
 * RELAY / TICKET
 * =============================
 */

// Funzione per trovare un ticket (relay) tramite ID
function tgFindRelayById($idRelay){

    // Converte l'id in intero
    $idRelay = (int)$idRelay;

    // Se l'id non è valido restituisce null
    if ($idRelay <= 0) return null;

    // Esegue query per recuperare il record dalla tabella docente_telegram_relay
    $relay = dbGetFirst("SELECT * FROM docente_telegram_relay WHERE id=".dbI($idRelay)." LIMIT 1");

    // Log del risultato per debug
    infoTelegram("tgFindRelayById: idRelay=$idRelay relay=".json_encode($relay));

    // Restituisce il record trovato (o null se non esiste)
    return $relay;
}

// Funzione per trovare un ticket tramite chat e message_id nel gruppo service
function tgFindRelayByServiceMessage($serviceChatId,$serviceMessageId){

    // Normalizza chatId (stringa)
    $serviceChatId = tgNorm($serviceChatId);

    // Converte messageId in intero
    $serviceMessageId = (int)$serviceMessageId;
    
    // Se dati non validi restituisce null
    if ($serviceChatId===''||$serviceMessageId<=0) return null;

    // Query per cercare il ticket associato a quel messaggio nel gruppo
    $query = "SELECT * FROM docente_telegram_relay WHERE service_chat_id=" . dbQ($serviceChatId) . " AND service_message_id=" . dbI($serviceMessageId) . " LIMIT 1";
    infoTelegram("tgFindRelayByServiceMessage: query=$query");
    $relay = dbGetFirst($query);

    // Log del risultato
    infoTelegram("tgFindRelayByServiceMessage: serviceChatId=$serviceChatId serviceMessageId=$serviceMessageId relay=".json_encode($relay));

    // Restituisce il ticket trovato
    return $relay;
}

// Funzione per trovare un ticket tramite chat e message_id nel gruppo service
function tgFindRelayByServiceThread($serviceChatId,$serviceThreadId){

    // Normalizza chatId (stringa)
    $serviceChatId = tgNorm($serviceChatId);

    // Converte messageId in intero
    $serviceThreadId = (int)$serviceThreadId;
    
    // Se dati non validi restituisce null
    if ($serviceChatId===''||$serviceThreadId<=0) return null;

    // Query per cercare il ticket associato a quel messaggio nel gruppo
    $query = "SELECT * FROM docente_telegram_relay WHERE service_chat_id=" . dbQ($serviceChatId) . " AND service_thread_id=" . dbI($serviceThreadId) . " LIMIT 1";
    infoTelegram("tgFindRelayByServiceThread: query=$query");
    $relay = dbGetFirst($query);

    // Log del risultato
    infoTelegram("tgFindRelayByServiceThread: serviceChatId=$serviceChatId serviceThreadId=$serviceThreadId relay=".json_encode($relay));

    // Restituisce il ticket trovato
    return $relay;
}

// Funzione per aggiornare lo stato di un ticket
function tgUpdateRelayStatus($idRelay, $newStatus, $adminUserId=null, $adminName=''){

    // Converte id in intero
    $idRelay = (int)$idRelay;

    // Normalizza e porta lo stato in maiuscolo
    $newStatus = strtoupper(tgNorm($newStatus));

    // Normalizza nome admin
    $adminName = tgNorm($adminName);

    // Log iniziale
    infoTelegram("tgUpdateRelayStatus: idRelay=$idRelay newStatus=$newStatus adminUserId=$adminUserId adminName=$adminName");

    // Controllo validità parametri
    if($idRelay<=0 || !in_array($newStatus,['APERTA','IN_GESTIONE','CHIUSA'],true)) {
        warningTelegram("tgUpdateRelayStatus: parametri invalidi");
        return false;
    }

    // Array campi da aggiornare
    $fields=["stato=".dbQ($newStatus)];

    // Se lo stato diventa IN_GESTIONE
    if($newStatus==='IN_GESTIONE'){

        // Il ticket non è chiuso
        $fields[]="chiusa=0";

        // Salva chi ha preso in carico (id admin)
        if($adminUserId!==null) $fields[]="preso_in_carico_da=".dbQ($adminUserId);

        // Salva nome admin
        if($adminName!=='') $fields[]="preso_in_carico_nome=".dbQ($adminName);

        // Timestamp presa in carico
        $fields[]="data_presa_in_carico=NOW()";

        // Reset eventuale data chiusura
        $fields[]="data_chiusura=NULL";
    }

    // Se lo stato diventa CHIUSA
    if($newStatus==='CHIUSA'){

        // Segna come chiuso
        $fields[]="chiusa=1";

        // Salva admin che ha chiuso
        if($adminUserId!==null) $fields[]="preso_in_carico_da=".dbQ($adminUserId);

        // Salva nome admin
        if($adminName!=='') $fields[]="preso_in_carico_nome=".dbQ($adminName);

        // Timestamp chiusura
        $fields[]="data_chiusura=NOW()";
    }

    // Se lo stato torna APERTA
    if($newStatus==='APERTA'){

        // Il ticket non è chiuso
        $fields[]="chiusa=0";

        // Reset data chiusura
        $fields[]="data_chiusura=NULL";
    }

    // Costruisce query UPDATE dinamica
    $q = "UPDATE docente_telegram_relay SET ".implode(", ",$fields)." WHERE id=".dbI($idRelay);

    // Log query
    infoTelegram("tgUpdateRelayStatus: query=$q");

    // Esegue aggiornamento
    dbExec($q);

    // Restituisce successo
    return true;
}

// Funzione per trovare un ticket aperto associato a un docente
function tgFindOpenRelayByDocente($idDocente){

    // Converte id docente in intero
    $idDocente=(int)$idDocente;

    // Se non valido → log e null
    if($idDocente<=0){
        infoTelegram("tgFindOpenRelayByDocente: idDocente non valido");
        return null;
    }

    // Query per trovare ticket aperto o in gestione
    $q="SELECT * FROM docente_telegram_relay WHERE idDocente=".dbI($idDocente)." AND stato IN ('APERTA','IN_GESTIONE') AND (chiusa=0 OR chiusa IS NULL) ORDER BY id DESC LIMIT 1";

    // Esegue query
    $relay=dbGetFirst($q);

    // Log risultato
    infoTelegram("tgFindOpenRelayByDocente: query=$q relay=".json_encode($relay));

    // Restituisce ticket trovato
    return $relay;
}

function tgGetDashboardKeyboard()
{
    $keyboard = [];

    $keyboard[] = [
        ['text' => '📋 Ticket aperti', 'callback_data' => 'lista_aperte'],
        ['text' => '🟡 In lavorazione', 'callback_data' => 'lista_lavorazione']
    ];

    $keyboard[] = [
        ['text' => '✅ Chiusi oggi', 'callback_data' => 'lista_chiusi_oggi'],
        ['text' => '👤 I miei ticket', 'callback_data' => 'lista_miei_ticket']
    ];

    infoTelegram("tgGetDashboardKeyboard: keyboard=" . json_encode($keyboard));

    return ['inline_keyboard' => $keyboard];
}

// Funzione per costruire la keyboard minimale del ticket
function tgGetTicketKeyboardMinimal(array $relay){

    // Inizializza array keyboard
    $keyboard = [];

    // Recupera id ticket
    $idRelay = (int)($relay['id'] ?? 0);

    // Recupera stato ticket normalizzato
    $stato = strtoupper(tgNorm($relay['stato'] ?? 'APERTA'));

    // Se id non valido → ritorna keyboard vuota
    if ($idRelay <= 0) return ['inline_keyboard' => $keyboard];

    // Costruisce bottoni in base allo stato
    switch ($stato) {

        // Se aperto → mostra "prendi in carico"
        case 'APERTA':
            $keyboard[] = [
                ['text' => '🟡 Prendi in carico', 'callback_data' => "presa_relay_{$idRelay}"]
            ];
            break;

        // Se in gestione → mostra "chiudi"
        case 'IN_GESTIONE':
            $keyboard[] = [
                ['text' => '✅ Chiudi', 'callback_data' => "chiudi_relay_{$idRelay}"]
            ];
            break;

        // Se chiuso → mostra "riapri" + "chiudi topic"
        case 'CHIUSA':
            $keyboard[] = [
                ['text' => '🔵 Riapri', 'callback_data' => "riapri_relay_{$idRelay}"]
            ];
            break;
    }

    // Log keyboard generata
    infoTelegram("tgGetTicketKeyboardMinimal: idRelay=$idRelay stato=$stato keyboard=" . json_encode($keyboard));

    // Restituisce struttura inline keyboard per Telegram
    return ['inline_keyboard' => $keyboard];
}

function tgCreateTopic($botToken, $chatId, $name)
{
    // Costruisce l'URL dell'endpoint Telegram per creare un topic forum
    $url = "https://api.telegram.org/bot{$botToken}/createForumTopic";

    // Prepara il payload con chat_id del gruppo/forum e nome del topic da creare
    $payload = ['chat_id' => $chatId, 'name' => $name];

    // Inizializza una sessione cURL verso l'URL Telegram
    $ch = curl_init($url);

    // Imposta la richiesta HTTP come POST
    curl_setopt($ch, CURLOPT_POST, true);

    // Fa restituire la risposta come stringa invece di stamparla direttamente
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Invia i parametri POST codificati come query string
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));

    // Imposta un timeout massimo di 20 secondi per la chiamata
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    // Esegue la chiamata HTTP verso Telegram e salva la risposta
    $response = curl_exec($ch);

    // Chiude la sessione cURL liberando le risorse
    curl_close($ch);

    // Decodifica la risposta JSON di Telegram in array associativo
    $json = json_decode($response, true);

    // Se Telegram ha risposto con ok=true, restituisce il message_thread_id del topic creato; altrimenti restituisce 0
    return $json['ok'] ? ($json['result']['message_thread_id'] ?? 0) : 0;
}

function tgCreateOrAppendTicketFromDocente(array $doc, string $text, string $serviceChatId, string $botToken)
{
    $text = tgNorm($text);
    $idDocente = (int)($doc['id'] ?? 0);
    $teacherChatId = tgNorm($doc['telegram_chat_id'] ?? '');
    $docenteNome = trim(($doc['cognome'] ?? '') . ' ' . ($doc['nome'] ?? ''));

    if ($idDocente <= 0 || $text === '') {
        return ['ok' => false, 'error' => 'Dati ticket non validi'];
    }

    infoTelegram("tgCreateOrAppendTicketFromDocente: idDocente=$idDocente text=[" . tgCut($text, 200) . "]");

    $openRelay = tgFindOpenRelayByDocente($idDocente);

    // =========================================================
    // TICKET ESISTENTE
    // =========================================================
    if ($openRelay) {
        $idRelay = (int)($openRelay['id'] ?? 0);
        if ($idRelay <= 0) {
            return ['ok' => false, 'error' => 'Relay esistente non valido'];
        }

        $ticketCode = tgNorm($openRelay['ticket_code'] ?? '');
        if ($ticketCode === '') {
            $ticketCode = tgUpdateTicketCode($idRelay);
        }

        $statoLabel = tgBuildStatoLabel($openRelay['stato'] ?? 'APERTA');
        $threadId = (int)($openRelay['service_thread_id'] ?? 0);

        dbExec("
            UPDATE docente_telegram_relay
            SET ultimo_testo_docente = " . dbQ($text) . ",
                data_aggiornamento = NOW()
            WHERE id = " . dbI($idRelay) . "
        ");

        $openRelay = tgFindRelayById($idRelay);

        $serviceText =
            "➕ Aggiornamento ticket {$ticketCode}\n\n" .
            "👤 Docente: {$docenteNome}\n" .
            "📌 Stato attuale: {$statoLabel}\n\n" .
            "✉️ Nuovo messaggio:\n" . tgCut($text, 3000);

        $sendRes = tgSendMessage(
            $botToken,
            $serviceChatId,
            $serviceText,
            [
                'message_thread_id' => $threadId,
                'reply_markup' => json_encode(
                    tgGetTicketKeyboardMinimal($openRelay),
                    JSON_UNESCAPED_UNICODE
                )
            ]
        );

        if (!$sendRes['ok']) {
            errorTelegram("tgCreateOrAppendTicketFromDocente: errore invio gruppo append " . ($sendRes['error'] ?? ''));
            return ['ok' => false, 'error' => 'Errore invio messaggio al gruppo di servizio'];
        }

        // Mostra il testo anche nella chat privata docente
        if ($teacherChatId !== '') {
            tgSendMessage(
                $botToken,
                $teacherChatId,
                "📝 Il tuo messaggio:\n" . tgCut($text, 3000)
            );

            tgSendMessage(
                $botToken,
                $teacherChatId,
                "✅ Il tuo messaggio è stato aggiunto al ticket {$ticketCode}.\nStato corrente: {$statoLabel}."
            );
        }

        return [
            'ok' => true,
            'mode' => 'append',
            'idRelay' => $idRelay,
            'ticket_code' => $ticketCode
        ];
    }

    // =========================================================
    // NUOVO TICKET
    // =========================================================
    dbExec("START TRANSACTION");

    try {
        $q = "
            INSERT INTO docente_telegram_relay (
                idDocente,
                docente_chat_id,
                docente_message_id,
                service_chat_id,
                service_message_id,
                service_thread_root_message_id,
                stato,
                chiusa,
                ultimo_testo_docente,
                data_creazione,
                data_aggiornamento
            ) VALUES (
                " . dbI($idDocente) . ",
                " . dbQ($teacherChatId) . ",
                0,
                " . dbQ($serviceChatId) . ",
                0,
                0,
                'APERTA',
                0,
                " . dbQ($text) . ",
                NOW(),
                NOW()
            )
        ";

        dbExec($q);

        $idRelay = (int)dblastId();
        if ($idRelay <= 0) {
            throw new Exception("Inserimento relay fallito");
        }

        $ticketCode = tgUpdateTicketCode($idRelay);

        $serviceThreadId = tgCreateTopic($botToken, $serviceChatId, "Ticket " . $ticketCode);

        dbExec("
            UPDATE docente_telegram_relay
            SET service_thread_id = " . dbI($serviceThreadId) . ",
                thread_topic_name = " . dbQ("Ticket $ticketCode") . "
            WHERE id = " . dbI($idRelay) . "
        ");

        $relay = tgFindRelayById($idRelay);

        $sendRes = tgSendMessage(
            $botToken,
            $serviceChatId,
            "📩 Nuovo messaggio da docente\n\n" .
            "🏷 Ticket: {$ticketCode}\n" .
            "👤 Docente: {$docenteNome}\n" .
            "✉️ Messaggio:\n" . tgCut($text, 3000),
            [
                'message_thread_id' => $serviceThreadId,
                'reply_markup' => json_encode(
                    tgGetTicketKeyboardMinimal($relay),
                    JSON_UNESCAPED_UNICODE
                )
            ]
        );

        if (!$sendRes['ok']) {
            throw new Exception("Invio messaggio Telegram al gruppo fallito: " . ($sendRes['error'] ?? ''));
        }

        $serviceMessageId = (int)($sendRes['message_id'] ?? 0);

        dbExec("
            UPDATE docente_telegram_relay
            SET service_message_id = " . dbI($serviceMessageId) . ",
                service_thread_root_message_id = " . dbI($serviceMessageId) . "
            WHERE id = " . dbI($idRelay) . "
        ");

        dbExec("COMMIT");

        // Mostra il testo anche nella chat privata docente
        if ($teacherChatId !== '') {
            tgSendMessage(
                $botToken,
                $teacherChatId,
                "📝 La tua richiesta:\n" . tgCut($text, 3000)
            );

            tgSendMessage(
                $botToken,
                $teacherChatId,
                "✅ Messaggio inviato al gruppo di servizio GestOre.\nTicket: {$ticketCode}\nStato richiesta: APERTA."
            );
        }

        return [
            'ok' => true,
            'mode' => 'create',
            'idRelay' => $idRelay,
            'ticket_code' => $ticketCode
        ];

    } catch (Throwable $e) {
        dbExec("ROLLBACK");
        errorTelegram("tgCreateOrAppendTicketFromDocente: eccezione " . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}
?>