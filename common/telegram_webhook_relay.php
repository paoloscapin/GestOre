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
    infoimportsost("tgFindRelayById: idRelay=$idRelay relay=".json_encode($relay));

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
    infoimportsost("tgFindRelayByServiceMessage: query=$query");
    $relay = dbGetFirst($query);

    // Log del risultato
    infoimportsost("tgFindRelayByServiceMessage: serviceChatId=$serviceChatId serviceMessageId=$serviceMessageId relay=".json_encode($relay));

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
    infoimportsost("tgFindRelayByServiceThread: query=$query");
    $relay = dbGetFirst($query);

    // Log del risultato
    infoimportsost("tgFindRelayByServiceThread: serviceChatId=$serviceChatId serviceThreadId=$serviceThreadId relay=".json_encode($relay));

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
    infoimportsost("tgUpdateRelayStatus: idRelay=$idRelay newStatus=$newStatus adminUserId=$adminUserId adminName=$adminName");

    // Controllo validità parametri
    if($idRelay<=0 || !in_array($newStatus,['APERTA','IN_GESTIONE','CHIUSA'],true)) {
        warningimportsost("tgUpdateRelayStatus: parametri invalidi");
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
    infoimportsost("tgUpdateRelayStatus: query=$q");

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
        infoimportsost("tgFindOpenRelayByDocente: idDocente non valido");
        return null;
    }

    // Query per trovare ticket aperto o in gestione
    $q="SELECT * FROM docente_telegram_relay WHERE idDocente=".dbI($idDocente)." AND stato IN ('APERTA','IN_GESTIONE') AND (chiusa=0 OR chiusa IS NULL) ORDER BY id DESC LIMIT 1";

    // Esegue query
    $relay=dbGetFirst($q);

    // Log risultato
    infoimportsost("tgFindOpenRelayByDocente: query=$q relay=".json_encode($relay));

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

    infoimportsost("tgGetDashboardKeyboard: keyboard=" . json_encode($keyboard));

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
    infoimportsost("tgGetTicketKeyboardMinimal: idRelay=$idRelay stato=$stato keyboard=" . json_encode($keyboard));

    // Restituisce struttura inline keyboard per Telegram
    return ['inline_keyboard' => $keyboard];
}


?>