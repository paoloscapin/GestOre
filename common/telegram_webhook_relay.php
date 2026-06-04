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

function tgFindOpenRelayByActor($actorType, $actorId)
{
    $actorType = strtolower(trim((string)$actorType));
    $actorId = (int)$actorId;

    if ($actorId <= 0) {
        infoTelegram("tgFindOpenRelayByActor: actorId non valido type=$actorType");
        return null;
    }

    if ($actorType === 'docente') {
        return tgFindOpenRelayByDocente($actorId);
    }

    $column = $actorType === 'genitore' ? 'idGenitore' : ($actorType === 'studente' ? 'idStudente' : '');
    if ($column === '') {
        infoTelegram("tgFindOpenRelayByActor: actorType non supportato type=$actorType");
        return null;
    }

    if (function_exists('ticketMailColumnExists') && !ticketMailColumnExists('docente_telegram_relay', $column)) {
        infoTelegram("tgFindOpenRelayByActor: colonna $column assente nel relay");
        return null;
    }

    $q = "SELECT * FROM docente_telegram_relay WHERE {$column}=" . dbI($actorId) . " AND stato IN ('APERTA','IN_GESTIONE') AND (chiusa=0 OR chiusa IS NULL) ORDER BY id DESC LIMIT 1";
    $relay = dbGetFirst($q);
    infoTelegram("tgFindOpenRelayByActor: query=$q relay=" . json_encode($relay));
    return $relay;
}

function tgFindLatestClosedRelayByActor($actorType, $actorId)
{
    $actorType = strtolower(trim((string)$actorType));
    $actorId = (int)$actorId;

    if ($actorId <= 0) {
        infoTelegram("tgFindLatestClosedRelayByActor: actorId non valido type=$actorType");
        return null;
    }

    if ($actorType === 'docente') {
        $column = 'idDocente';
    } elseif ($actorType === 'genitore') {
        $column = 'idGenitore';
    } elseif ($actorType === 'studente') {
        $column = 'idStudente';
    } else {
        infoTelegram("tgFindLatestClosedRelayByActor: actorType non supportato type=$actorType");
        return null;
    }

    if ($column !== 'idDocente' && function_exists('ticketMailColumnExists') && !ticketMailColumnExists('docente_telegram_relay', $column)) {
        infoTelegram("tgFindLatestClosedRelayByActor: colonna $column assente nel relay");
        return null;
    }

    $q = "SELECT * FROM docente_telegram_relay WHERE {$column}=" . dbI($actorId) . " AND stato = 'CHIUSA' AND chiusa = 1 ORDER BY data_chiusura DESC, id DESC LIMIT 1";
    $relay = dbGetFirst($q);
    infoTelegram("tgFindLatestClosedRelayByActor: query=$q relay=" . json_encode($relay));
    return $relay;
}

function tgGetClosedTicketFollowupKeyboard(array $relay)
{
    $idRelay = (int)($relay['id'] ?? 0);
    if ($idRelay <= 0) {
        return ['inline_keyboard' => []];
    }

    return [
        'inline_keyboard' => [
            [
                ['text' => '🔵 Riapri precedente', 'callback_data' => "riapri_relay_{$idRelay}"],
                ['text' => '🆕 Apri nuovo ticket', 'callback_data' => "nuovo_relay_{$idRelay}"]
            ],
            [
                ['text' => '📌 Stato', 'callback_data' => "stato_relay_{$idRelay}"]
            ]
        ]
    ];
}

function tgGetOpenTicketFollowupKeyboard(array $relay)
{
    $idRelay = (int)($relay['id'] ?? 0);
    if ($idRelay <= 0) {
        return ['inline_keyboard' => []];
    }

    return [
        'inline_keyboard' => [
            [
                ['text' => 'Unisci al precedente', 'callback_data' => "unisci_relay_{$idRelay}"],
                ['text' => 'Apri nuovo ticket', 'callback_data' => "nuovo_relay_{$idRelay}"]
            ],
            [
                ['text' => 'Stato', 'callback_data' => "stato_relay_{$idRelay}"]
            ]
        ]
    ];
}

function tgExtractFollowupTextFromServiceMessage(array $message): string
{
    $text = tgNorm($message['text'] ?? $message['caption'] ?? '');
    if ($text === '') {
        return '';
    }

    if (preg_match('/Messaggio:\s*(.+)\z/us', $text, $matches)) {
        return tgNorm($matches[1] ?? '');
    }

    return '';
}

function tgRelayActorLabel(array $relay)
{
    $type = strtolower(trim((string)($relay['tipo_utente'] ?? 'docente')));
    if ($type === 'studente') {
        return 'Studente';
    }
    if ($type === 'genitore') {
        return 'Genitore';
    }
    return 'Docente';
}

function tgRelayActorName(array $relay)
{
    $name = trim((string)(($relay['utente_cognome'] ?? '') . ' ' . ($relay['utente_nome'] ?? '')));
    if ($name !== '') {
        return $name;
    }

    if (!empty($relay['idDocente'])) {
        $doc = dbGetFirst("SELECT cognome, nome FROM docente WHERE id = " . dbI((int)$relay['idDocente']) . " LIMIT 1");
        if ($doc) {
            return trim((string)(($doc['cognome'] ?? '') . ' ' . ($doc['nome'] ?? '')));
        }
    }

    return trim((string)($relay['utente_email'] ?? $relay['email_riferimento'] ?? 'Utente'));
}

function tgCreateNewTicketFromClosedRelay(array $sourceRelay, string $serviceChatId, string $botToken)
{
    $sourceIdRelay = (int)($sourceRelay['id'] ?? 0);
    $sourceText = tgNorm($sourceRelay['ultimo_testo_docente'] ?? '');
    $text = function_exists('tgGetLatestTicketUserText') ? tgGetLatestTicketUserText($sourceText) : $sourceText;
    $remainingText = function_exists('tgRemoveLatestTicketUserText') ? tgRemoveLatestTicketUserText($sourceText) : $sourceText;
    $serviceChatId = tgNorm($serviceChatId !== '' ? $serviceChatId : ($sourceRelay['service_chat_id'] ?? ''));
    $botToken = tgNorm($botToken);

    if ($sourceIdRelay <= 0 || $text === '' || $serviceChatId === '' || $botToken === '') {
        return ['ok' => false, 'error' => 'Dati insufficienti per aprire nuovo ticket'];
    }

    dbExec("START TRANSACTION");

    try {
        $insertColumns = [
            'docente_chat_id',
            'docente_message_id',
            'service_chat_id',
            'service_message_id',
            'service_thread_root_message_id',
            'stato',
            'chiusa',
            'ultimo_testo_docente',
            'data_creazione',
            'data_aggiornamento'
        ];
        $insertValues = [
            dbQ((string)($sourceRelay['docente_chat_id'] ?? '')),
            dbI((int)($sourceRelay['docente_message_id'] ?? 0)),
            dbQ($serviceChatId),
            '0',
            '0',
            "'APERTA'",
            '0',
            dbQ($text),
            'NOW()',
            'NOW()'
        ];

        foreach (['idDocente', 'idStudente', 'idGenitore'] as $column) {
            if (!array_key_exists($column, $sourceRelay) || (int)$sourceRelay[$column] <= 0) {
                continue;
            }
            if ($column !== 'idDocente' && function_exists('ticketMailColumnExists') && !ticketMailColumnExists('docente_telegram_relay', $column)) {
                continue;
            }
            $insertColumns[] = $column;
            $insertValues[] = dbI((int)$sourceRelay[$column]);
        }

        foreach (['tipo_utente', 'canale_apertura', 'email_riferimento', 'utente_nome', 'utente_cognome', 'utente_email'] as $column) {
            if (!array_key_exists($column, $sourceRelay)) {
                continue;
            }
            if (function_exists('ticketMailColumnExists') && !ticketMailColumnExists('docente_telegram_relay', $column)) {
                continue;
            }
            $insertColumns[] = $column;
            $insertValues[] = dbQ((string)$sourceRelay[$column]);
        }

        dbExec("
            INSERT INTO docente_telegram_relay (
                " . implode(",\n                ", $insertColumns) . "
            ) VALUES (
                " . implode(",\n                ", $insertValues) . "
            )
        ");

        $idRelay = (int)dblastId();
        if ($idRelay <= 0) {
            throw new Exception("Inserimento nuovo ticket da chiuso fallito");
        }

        $ticketCode = tgUpdateTicketCode($idRelay);
        $serviceThreadId = tgCreateTopic($botToken, $serviceChatId, "Ticket " . $ticketCode);

        if ($remainingText !== $sourceText) {
            dbExec("
                UPDATE docente_telegram_relay
                SET ultimo_testo_docente = " . dbQ($remainingText) . ",
                    data_aggiornamento = NOW()
                WHERE id = " . dbI($sourceIdRelay) . "
            ");
        }

        dbExec("
            UPDATE docente_telegram_relay
            SET service_thread_id = " . dbI($serviceThreadId) . ",
                thread_topic_name = " . dbQ("Ticket $ticketCode") . "
            WHERE id = " . dbI($idRelay) . "
        ");

        $relay = tgFindRelayById($idRelay);
        $actorLabel = tgRelayActorLabel($relay ?: $sourceRelay);
        $actorName = tgRelayActorName($relay ?: $sourceRelay);
        $oldTicketCode = tgNorm($sourceRelay['ticket_code'] ?? '');
        $sourceWasClosed = strtoupper(tgNorm($sourceRelay['stato'] ?? '')) === 'CHIUSA'
            || (int)($sourceRelay['chiusa'] ?? 0) === 1;
        $newTicketReason = $sourceWasClosed
            ? 'messaggio successivo a ticket chiuso'
            : 'messaggio distinto da ticket aperto';

        $serviceText =
            "🆕 Nuovo ticket aperto da {$newTicketReason}\n\n" .
            "🏷 Ticket: {$ticketCode}\n" .
            ($oldTicketCode !== '' ? "🔁 Ticket precedente: {$oldTicketCode}\n" : '') .
            "👤 {$actorLabel}: {$actorName}\n\n" .
            "✉️ Messaggio:\n" . tgCut($text, 3000);

        $sendRes = tgSendMessage(
            $botToken,
            $serviceChatId,
            $serviceText,
            [
                'message_thread_id' => $serviceThreadId,
                'reply_markup' => json_encode(tgGetTicketKeyboardMinimal($relay ?: []), JSON_UNESCAPED_UNICODE)
            ]
        );

        if (!$sendRes['ok']) {
            throw new Exception("Invio messaggio nuovo ticket fallito: " . ($sendRes['error'] ?? ''));
        }

        $serviceMessageId = (int)($sendRes['message_id'] ?? 0);
        dbExec("
            UPDATE docente_telegram_relay
            SET service_message_id = " . dbI($serviceMessageId) . ",
                service_thread_root_message_id = " . dbI($serviceMessageId) . "
            WHERE id = " . dbI($idRelay) . "
        ");

        dbExec("COMMIT");

        $newRelay = tgFindRelayById($idRelay);
        $recipientChatId = tgNorm(is_array($newRelay) ? ($newRelay['docente_chat_id'] ?? '') : ($sourceRelay['docente_chat_id'] ?? ''));
        if ($recipientChatId !== '') {
            tgSendMessage(
                $botToken,
                $recipientChatId,
                "🆕 Il tuo nuovo messaggio è stato registrato come nuovo ticket {$ticketCode}."
            );
        } elseif (function_exists('ticketMailSendRelayNotification') && function_exists('ticketMailRelayIsMailOrigin') && ticketMailRelayIsMailOrigin($newRelay ?: $sourceRelay)) {
            ticketMailSendRelayNotification(
                $newRelay ?: $sourceRelay,
                "Ricezione ticket {$ticketCode}",
                "Il tuo nuovo messaggio è stato registrato come nuovo ticket {$ticketCode}."
            );
        }

        return [
            'ok' => true,
            'idRelay' => $idRelay,
            'ticket_code' => $ticketCode
        ];
    } catch (Throwable $e) {
        dbExec("ROLLBACK");
        errorTelegram("tgCreateNewTicketFromClosedRelay: eccezione " . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
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
            if (trim((string)($relay['preso_in_carico_da'] ?? '')) !== '') {
                $keyboard[] = [
                    ['text' => '🔁 Override', 'callback_data' => "override_relay_{$idRelay}"]
                ];
            }
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
        $needsMergeChoice = strtoupper(tgNorm($openRelay['stato'] ?? 'APERTA')) === 'APERTA';
        $threadId = (int)($openRelay['service_thread_id'] ?? 0);
        $ticketText = tgAppendTicketUserText($openRelay['ultimo_testo_docente'] ?? '', $text);

        dbExec("
            UPDATE docente_telegram_relay
            SET ultimo_testo_docente = " . dbQ($ticketText) . ",
                data_aggiornamento = NOW()
            WHERE id = " . dbI($idRelay) . "
        ");

        $openRelay = tgFindRelayById($idRelay);

        if ($needsMergeChoice) {
            $mergeChoiceNotice = "\n\nQuesto messaggio arriva mentre il ticket precedente non e' ancora in gestione.\nScegli se unirlo al ticket precedente oppure aprire un nuovo ticket.";
        } else {
            $mergeChoiceNotice = '';
        }

        $serviceText =
            "➕ Aggiornamento ticket {$ticketCode}\n\n" .
            "👤 Docente: {$docenteNome}\n" .
            "📌 Stato attuale: {$statoLabel}\n\n" .
            "✉️ Nuovo messaggio:\n" . tgCut($text, 3000);

        $serviceText .= $mergeChoiceNotice;

        $sendRes = tgSendMessage(
            $botToken,
            $serviceChatId,
            $serviceText,
            [
                'message_thread_id' => $threadId,
                'reply_markup' => json_encode(
                    $needsMergeChoice ? tgGetOpenTicketFollowupKeyboard($openRelay) : tgGetTicketKeyboardMinimal($openRelay),
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
            'mode' => $needsMergeChoice ? 'open_followup' : 'append',
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
                "✅ Il tuo messaggio è stato ricevuto.\nTicket: {$ticketCode}\nIl tuo caso sarà preso in carico appena possibile."
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

function tgCreateOrAppendTicketFromDocenteMail(
    array $doc,
    string $subject,
    string $text,
    string $fromEmail,
    string $serviceChatId,
    string $botToken,
    ?array $relayByCode = null
)
{
    $subject = tgNorm($subject);
    $text = tgNorm($text);
    $fromEmail = tgNorm($fromEmail);
    $serviceChatId = tgNorm($serviceChatId);
    $botToken = tgNorm($botToken);
    $actorType = strtolower(trim((string)($doc['tipo_utente'] ?? 'docente')));
    $actorId = (int)($doc['id'] ?? 0);
    $actorLabel = trim((string)($doc['display_label'] ?? ($actorType === 'studente' ? 'Studente' : ($actorType === 'genitore' ? 'Genitore' : 'Docente'))));
    $actorName = trim((string)($doc['display_name'] ?? (trim(($doc['cognome'] ?? '') . ' ' . ($doc['nome'] ?? '')))));
    $actorChatId = tgNorm($doc['telegram_chat_id'] ?? '');
    $actorIdColumn = $actorType === 'studente' ? 'idStudente' : ($actorType === 'genitore' ? 'idGenitore' : 'idDocente');

    if ($actorId <= 0 || $text === '' || $serviceChatId === '' || $botToken === '') {
        return ['ok' => false, 'error' => 'Dati ticket mail non validi'];
    }

    infoTelegram("tgCreateOrAppendTicketFromDocenteMail: actorType=$actorType actorId=$actorId fromEmail=[$fromEmail] subject=[" . tgCut($subject, 120) . "]");

    $targetRelay = null;
    if (is_array($relayByCode) && !empty($relayByCode['id'])) {
        $targetRelay = $relayByCode;
    } else {
        if (function_exists('ticketMailFindOpenMailRelayByActor')) {
            $targetRelay = ticketMailFindOpenMailRelayByActor($doc);
        } elseif ($actorType === 'docente' && function_exists('ticketMailFindOpenMailRelayByDocente')) {
            $targetRelay = ticketMailFindOpenMailRelayByDocente($actorId);
        } elseif ($actorType === 'docente') {
            $targetRelay = tgFindOpenRelayByDocente($actorId);
        }
        if (!$targetRelay && function_exists('tgFindLatestClosedRelayByActor')) {
            $targetRelay = tgFindLatestClosedRelayByActor($actorType, $actorId);
        }
    }

    if ($targetRelay) {
        $idRelay = (int)($targetRelay['id'] ?? 0);
        if ($idRelay <= 0) {
            return ['ok' => false, 'error' => 'Relay mail esistente non valido'];
        }

        $ticketCode = tgNorm($targetRelay['ticket_code'] ?? '');
        if ($ticketCode === '') {
            $ticketCode = tgUpdateTicketCode($idRelay);
        }

        $statoLabel = tgBuildStatoLabel($targetRelay['stato'] ?? 'APERTA');
        $isClosedRelay = strtoupper(tgNorm($targetRelay['stato'] ?? '')) === 'CHIUSA' || (int)($targetRelay['chiusa'] ?? 0) === 1;
        $needsMergeChoice = !$relayByCode && !$isClosedRelay && strtoupper(tgNorm($targetRelay['stato'] ?? 'APERTA')) === 'APERTA';
        $threadId = (int)($targetRelay['service_thread_id'] ?? 0);
        if (!$needsMergeChoice) {
            $ticketText = tgAppendTicketUserText($targetRelay['ultimo_testo_docente'] ?? '', $text);

            dbExec("
                UPDATE docente_telegram_relay
                SET ultimo_testo_docente = " . dbQ($ticketText) . ",
                    data_aggiornamento = NOW()
                WHERE id = " . dbI($idRelay) . "
            ");
        }

        $targetRelay = tgFindRelayById($idRelay);
        if (!$targetRelay) {
            return ['ok' => false, 'error' => 'Relay mail non ricaricato'];
        }

        $serviceText =
            "📧 Aggiornamento ticket via email\n\n" .
            "🏷 Ticket: {$ticketCode}\n" .
            "👤 {$actorLabel}: {$actorName}\n" .
            "✉️ Mittente: {$fromEmail}\n" .
            "📌 Stato attuale: {$statoLabel}\n";

        if ($isClosedRelay) {
            $serviceText .= "\n⚠️ Questo messaggio è arrivato su un ticket già chiuso.\nScegli se riaprire il ticket precedente oppure aprire un nuovo ticket.\n";
        }

        if ($needsMergeChoice) {
            $serviceText .= "\nQuesto messaggio arriva mentre il ticket precedente non e' ancora in gestione.\nScegli se unirlo al ticket precedente oppure aprire un nuovo ticket.\n";
        }

        if ($subject !== '') {
            $serviceText .= "📝 Oggetto: {$subject}\n";
        }

        $serviceText .= "\n📨 Messaggio:\n" . tgCut($text, 3000);

        $sendOptions = [
            'reply_markup' => json_encode(
                $isClosedRelay ? tgGetClosedTicketFollowupKeyboard($targetRelay) : ($needsMergeChoice ? tgGetOpenTicketFollowupKeyboard($targetRelay) : tgGetTicketKeyboardMinimal($targetRelay)),
                JSON_UNESCAPED_UNICODE
            )
        ];
        if ($threadId > 0) {
            $sendOptions['message_thread_id'] = $threadId;
        }

        $sendRes = tgSendMessage(
            $botToken,
            $serviceChatId,
            $serviceText,
            $sendOptions
        );

        if (!$sendRes['ok']) {
            errorTelegram("tgCreateOrAppendTicketFromDocenteMail: errore invio gruppo append " . ($sendRes['error'] ?? ''));
            return ['ok' => false, 'error' => 'Errore invio messaggio email al gruppo di servizio'];
        }

        return [
            'ok' => true,
            'mode' => $isClosedRelay ? 'closed_followup' : ($needsMergeChoice ? 'open_followup' : 'append'),
            'idRelay' => $idRelay,
            'ticket_code' => $ticketCode
        ];
    }

    dbExec("START TRANSACTION");

    try {
        $insertColumns = [
            'docente_chat_id',
            'docente_message_id',
            'service_chat_id',
            'service_message_id',
            'service_thread_root_message_id',
            'stato',
            'chiusa',
            'ultimo_testo_docente',
            'data_creazione',
            'data_aggiornamento'
        ];
        $insertValues = [
            dbQNotNull($actorChatId, ''),
            '0',
            dbQ($serviceChatId),
            '0',
            '0',
            "'APERTA'",
            '0',
            dbQ($text),
            'NOW()',
            'NOW()'
        ];

        if ($actorIdColumn === 'idDocente' || (function_exists('ticketMailColumnExists') && ticketMailColumnExists('docente_telegram_relay', $actorIdColumn))) {
            array_unshift($insertColumns, $actorIdColumn);
            array_unshift($insertValues, dbI($actorId));
        }
        if (function_exists('ticketMailColumnExists') && ticketMailColumnExists('docente_telegram_relay', 'tipo_utente')) {
            $insertColumns[] = 'tipo_utente';
            $insertValues[] = dbQ($actorType);
        }
        if (function_exists('ticketMailColumnExists') && ticketMailColumnExists('docente_telegram_relay', 'canale_apertura')) {
            $insertColumns[] = 'canale_apertura';
            $insertValues[] = dbQ('mail');
        }
        if (function_exists('ticketMailColumnExists') && ticketMailColumnExists('docente_telegram_relay', 'email_riferimento')) {
            $insertColumns[] = 'email_riferimento';
            $insertValues[] = dbQ((string)$fromEmail);
        }
        if (function_exists('ticketMailColumnExists') && ticketMailColumnExists('docente_telegram_relay', 'utente_nome')) {
            $insertColumns[] = 'utente_nome';
            $insertValues[] = dbQ((string)($doc['nome'] ?? ''));
        }
        if (function_exists('ticketMailColumnExists') && ticketMailColumnExists('docente_telegram_relay', 'utente_cognome')) {
            $insertColumns[] = 'utente_cognome';
            $insertValues[] = dbQ((string)($doc['cognome'] ?? ''));
        }
        if (function_exists('ticketMailColumnExists') && ticketMailColumnExists('docente_telegram_relay', 'utente_email')) {
            $insertColumns[] = 'utente_email';
            $insertValues[] = dbQ((string)($doc['email'] ?? $fromEmail));
        }

        dbExec("
            INSERT INTO docente_telegram_relay (
                " . implode(",\n                ", $insertColumns) . "
            ) VALUES (
                " . implode(",\n                ", $insertValues) . "
            )
        ");

        $idRelay = (int)dblastId();
        if ($idRelay <= 0) {
            throw new Exception("Inserimento relay mail fallito");
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

        $serviceText =
            "📧 Nuovo ticket via email\n\n" .
            "🏷 Ticket: {$ticketCode}\n" .
            "👤 {$actorLabel}: {$actorName}\n" .
            "✉️ Mittente: {$fromEmail}\n";

        if ($subject !== '') {
            $serviceText .= "📝 Oggetto: {$subject}\n";
        }

        $serviceText .= "\n📨 Messaggio:\n" . tgCut($text, 3000);

        $sendRes = tgSendMessage(
            $botToken,
            $serviceChatId,
            $serviceText,
            [
                'message_thread_id' => $serviceThreadId,
                'reply_markup' => json_encode(
                    tgGetTicketKeyboardMinimal($relay),
                    JSON_UNESCAPED_UNICODE
                )
            ]
        );

        if (!$sendRes['ok']) {
            throw new Exception("Invio messaggio Telegram mail al gruppo fallito: " . ($sendRes['error'] ?? ''));
        }

        $serviceMessageId = (int)($sendRes['message_id'] ?? 0);

        dbExec("
            UPDATE docente_telegram_relay
            SET service_message_id = " . dbI($serviceMessageId) . ",
                service_thread_root_message_id = " . dbI($serviceMessageId) . "
            WHERE id = " . dbI($idRelay) . "
        ");

        dbExec("COMMIT");

        return [
            'ok' => true,
            'mode' => 'create',
            'idRelay' => $idRelay,
            'ticket_code' => $ticketCode
        ];
    } catch (Throwable $e) {
        dbExec("ROLLBACK");
        errorTelegram("tgCreateOrAppendTicketFromDocenteMail: eccezione " . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}
?>
