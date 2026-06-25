<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/__Util.php';
require_once __DIR__ . '/path.php';
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/__MasterCom.php';

if ($__settings->config->MBApp !== true) {
    debug("checkSession: MBApp feature disabled, skipping connectMBApp.php ");
} else {
    require_once __DIR__ . '/connectMBApp.php';
}

// =====================================================
// DEBUG HELPERS (dump completo stato sessione, con masking)
// =====================================================
function __dbg_mask($v, $maxLen = 180)
{
    if ($v === null) return 'NULL';
    if (is_bool($v)) return $v ? 'true' : 'false';
    if (is_int($v) || is_float($v)) return (string)$v;
    if (is_array($v)) return 'ARRAY(' . count($v) . ')';
    if (is_object($v)) return 'OBJECT(' . get_class($v) . ')';

    $s = (string)$v;

    // maschera token lunghi / jwt / google token
    if (strlen($s) > 60) {
        return substr($s, 0, 10) . '...' . substr($s, -8) . ' (len=' . strlen($s) . ')';
    }
    if (strlen($s) > $maxLen) {
        return substr($s, 0, $maxLen) . '... (len=' . strlen($s) . ')';
    }
    return $s;
}

function __dbg_session_state(string $stage): void
{
    $sessStatus = session_status();
    $sid = ($sessStatus === PHP_SESSION_ACTIVE) ? session_id() : '(no-session)';

    // elenco chiavi "gestite" (quelle che nel file vengono lette/scritte)
    $keys = [
        // timer scadenza
        'LAST_ACTIVITY',
        'EXPIRE_AFTER',

        // google auth
        'token',

        // identità utente
        '__useremail',
        '__username',
        'username',
        'utente_id',
        'utente_nome',
        'utente_cognome',
        'utente_ruolo',

        // docente
        'docente_id',
        'docente_nome',
        'docente_cognome',
        'docente_email',

        // esterno
        'esterno_id',
        'esterno_nome',
        'esterno_cognome',
        'esterno_email',

        // portineria
        'portineria_id',
        'portineria_nome',
        'portineria_cognome',
        'portineria_email',

        // studente
        'studente_id',
        'studente_nome',
        'studente_cognome',
        'studente_email',
        'studente_codice_fiscale',

        // genitore
        'genitore_id',
        'genitore_nome',
        'genitore_cognome',
        'genitore_email',
        'genitore_codice_fiscale',

        // personale ATA
        'personale_ata_id',
        'personale_ata_nome',
        'personale_ata_cognome',
        'personale_ata_email',

        // anno scolastico
        'anno_scolastico_corrente_id',
        'anno_scolastico_corrente_anno',
        'anno_scolastico_scorso_id',

        // eventuali chiavi vecchie
        'ruolo',
    ];

    $allKeys = [];
    if (isset($_SESSION) && is_array($_SESSION)) {
        $allKeys = array_keys($_SESSION);
        sort($allKeys);
    }

    $dump = [
        'stage' => $stage,
        'session_status' => $sessStatus,
        'session_id' => $sid,
        'session_count' => (isset($_SESSION) && is_array($_SESSION)) ? count($_SESSION) : -1,
        'all_session_keys' => !empty($allKeys) ? implode(',', $allKeys) : '(empty)',
        'req_uri' => $_SERVER['REQUEST_URI'] ?? '',
        'req_method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'query' => $_SERVER['QUERY_STRING'] ?? '',
        'referer' => $_SERVER['HTTP_REFERER'] ?? '',
        'accept' => $_SERVER['HTTP_ACCEPT'] ?? '',
        'xrw' => $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '',
        'cookie_header_present' => isset($_SERVER['HTTP_COOKIE']) ? 'YES' : 'NO',
        'cookie_has_PHPSESSID' => (isset($_COOKIE[session_name()]) ? 'YES' : 'NO'),
        'php_session_name' => session_name(),
    ];

    foreach ($keys as $k) {
        $dump[$k] = __dbg_mask($_SESSION[$k] ?? null);
    }

    debug('checkSession: SESSION_DUMP ' . json_encode($dump, JSON_UNESCAPED_SLASHES));
}

// =====================================================
// AJAX / JSON helpers
// =====================================================
function __is_ajax_request(): bool
{
    return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
}

function __wants_json(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return (stripos($accept, 'application/json') !== false);
}

function __current_internal_redirect_after_login(): ?string
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if (!is_string($requestUri) || $requestUri === '') {
        return null;
    }

    // Accetta solo percorsi interni a GestOre: evita open redirect verso domini esterni.
    if (strpos($requestUri, '/GestOre/') !== 0) {
        return null;
    }

    // Non ha senso ricordare la pagina di login come destinazione finale.
    if (preg_match('~/GestOre/index\.php(?:\?|$)~', $requestUri) === 1) {
        return null;
    }

    return $requestUri;
}

/**
 * Se la sessione è scaduta e la richiesta è AJAX/JSON:
 * - risponde 401 + JSON (così il JS può redirigere)
 * Altrimenti:
 * - redirect a error.php come prima
 */
function __session_expired_exit(string $message): void
{
    global $__application_base_path;

    debug("checkSession: __session_expired_exit() ENTER msg=" . $message);
    __dbg_session_state('EXPIRY: at __session_expired_exit ENTER');

    debug("checkSession: __session_expired_exit() is_ajax=" . (__is_ajax_request() ? "YES" : "NO")
        . " wants_json=" . (__wants_json() ? "YES" : "NO"));

    if (headers_sent($file, $line)) {
        debug("checkSession: WARNING headers already sent at $file:$line");
    }

    if (__is_ajax_request() || __wants_json()) {
        debug("checkSession: replying 401 JSON session-expired");
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'reason' => 'SESSION_EXPIRED',
            'msg' => $message,
            'redirect' => ($__application_base_path ?? '') . '/index.php'
        ]);
        exit();
    }

    debug("checkSession: redirecting to ../error/error.php (session-expired)");
    header("Location: ../error/error.php?message=" . urlencode($message));
    exit();
}

// =====================================================
// DEBUG BOOT (prima ancora di session_start)
// =====================================================
debug("checkSession: ENTER");
debug("checkSession: request_uri=" . ($_SERVER['REQUEST_URI'] ?? 'n/d')
    . " method=" . ($_SERVER['REQUEST_METHOD'] ?? 'n/d')
    . " query=" . ($_SERVER['QUERY_STRING'] ?? ''));
debug("checkSession: referer=" . ($_SERVER['HTTP_REFERER'] ?? ''));
debug("checkSession: user_agent=" . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
debug("checkSession: accept=" . ($_SERVER['HTTP_ACCEPT'] ?? ''));
debug("checkSession: xrw=" . ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
debug("checkSession: cookie_header_present=" . (isset($_SERVER['HTTP_COOKIE']) ? "YES" : "NO"));
debug("checkSession: PHPSESSID cookie present=" . (isset($_COOKIE[session_name()]) ? "YES" : "NO"));
debug("checkSession: session_name=" . session_name());
debug("checkSession: session_status(before)=" . session_status());
debug("checkSession: settings durata_sessione=" . intval($__settings->system->durata_sessione));

// =====================================================
// helper: siamo su index.php?
// =====================================================
$__script = $_SERVER['SCRIPT_NAME'] ?? '';
$__is_login_page = (preg_match('~/index\.php$~', $__script) === 1);

// =====================================================
// SESSION START + INI CHECKS
// =====================================================

debug("checkSession: ini before gc_maxlifetime=" . ini_get('session.gc_maxlifetime')
    . " cookie_lifetime=" . ini_get('session.cookie_lifetime')
    . " save_handler=" . ini_get('session.save_handler')
    . " save_path=" . ini_get('session.save_path')
    . " cookie_path=" . ini_get('session.cookie_path')
    . " cookie_domain=" . ini_get('session.cookie_domain')
    . " cookie_secure=" . ini_get('session.cookie_secure')
    . " cookie_samesite=" . (ini_get('session.cookie_samesite') ?: ''));

if (session_status() == PHP_SESSION_NONE) {
    $dur = intval($__settings->system->durata_sessione);

    debug("checkSession: session not started -> applying ini_set + session_set_cookie_params, durata=$dur");

    ini_set('session.gc_maxlifetime', $dur);
    ini_set('session.cookie_lifetime', $dur);
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    // forza cookie path "/" (evita cookie “vincolati” a sottocartelle)
    $cookieParams = session_get_cookie_params();
    $path = !empty($__application_base_path) ? $__application_base_path : '/';
    $domain = $cookieParams['domain'] ?? '';
    $secure = true;
    $httponly = true;

    session_set_cookie_params([
        'lifetime' => $dur,
        'path' => $path,
        'domain' => $domain,
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => 'Lax',
    ]);

    debug("checkSession: ini after ini_set gc_maxlifetime=" . ini_get('session.gc_maxlifetime')
        . " cookie_lifetime=" . ini_get('session.cookie_lifetime')
        . " cookie_secure=" . ini_get('session.cookie_secure')
        . " cookie_httponly=" . ini_get('session.cookie_httponly')
        . " cookie_samesite=" . (ini_get('session.cookie_samesite') ?: ''));

    @session_name("GESTORESESSID");
    $ok = @session_start();
    debug("checkSession: session_start() -> " . ($ok ? "OK" : "FAIL"));
} else {
    debug("checkSession: session already active (status=" . session_status() . ")");
}

debug("checkSession: session_status(after)=" . session_status());
debug("checkSession: session_id=" . session_id());
debug("checkSession: cookie_params=" . json_encode(session_get_cookie_params()));
debug("checkSession: PHPSESSID cookie NOW present=" . (isset($_COOKIE[session_name()]) ? "YES" : "NO"));

__dbg_session_state('ENTRY (post session_start / active)');

// Se il cookie esiste ma la sessione è vuota -> spesso significa che il server ha già ripulito i file di sessione
if (isset($_COOKIE[session_name()]) && isset($_SESSION) && is_array($_SESSION) && count($_SESSION) === 0) {
    debug("checkSession: WARNING cookie present but session is EMPTY -> server likely deleted session file (GC/hosting)");
}

// =====================================================
// EXPIRE_AFTER / LAST_ACTIVITY CHECKS
// =====================================================
if (!isset($_SESSION['EXPIRE_AFTER']) || intval($_SESSION['EXPIRE_AFTER']) <= 0) {
    $_SESSION['EXPIRE_AFTER'] = intval($__settings->system->durata_sessione);
    debug("checkSession: EXPIRE_AFTER missing -> set to " . intval($_SESSION['EXPIRE_AFTER']));
} else {
    debug("checkSession: EXPIRE_AFTER exists -> " . intval($_SESSION['EXPIRE_AFTER']));
}

if (isset($_SESSION['LAST_ACTIVITY'])) {
    $delta = time() - intval($_SESSION['LAST_ACTIVITY']);
    debug("checkSession: LAST_ACTIVITY exists -> " . intval($_SESSION['LAST_ACTIVITY']) . " delta=" . $delta . "s");

    if ($delta > intval($_SESSION['EXPIRE_AFTER'])) {
        debug("checkSession: SESSION EXPIRED by app logic (delta=$delta > expire_after=" . intval($_SESSION['EXPIRE_AFTER']) . ")");
        __dbg_session_state('EXPIRY: before destroy');

        $redirectAfterLogin = (!__is_ajax_request() && !__wants_json()) ? __current_internal_redirect_after_login() : null;

        session_unset();
        session_destroy();

        __dbg_session_state('EXPIRY: after destroy (should be empty)');
        $message = "Sessione scaduta, effettuare nuovamente il login";

        if ($redirectAfterLogin !== null) {
            debug("checkSession: expired normal request -> preserve redirect_after_login=" . $redirectAfterLogin);
            @session_start();
            $_SESSION['redirect_after_login'] = $redirectAfterLogin;
            $_SESSION['LAST_ACTIVITY'] = time();
            $_SESSION['EXPIRE_AFTER'] = intval($__settings->system->durata_sessione);
            redirect('/index.php');
            exit();
        }

        __session_expired_exit($message);
    } else {
        debug("checkSession: session still valid (delta=$delta <= expire_after=" . intval($_SESSION['EXPIRE_AFTER']) . ")");
    }
} else {
    debug("checkSession: LAST_ACTIVITY missing (first request or lost session?)");
}

// refresh activity
$_SESSION['LAST_ACTIVITY'] = time();
debug("checkSession: LAST_ACTIVITY refreshed to " . intval($_SESSION['LAST_ACTIVITY']));

// configurazione globale
require_once __DIR__ . '/Config.php';
debug("checkSession: Config.php loaded");

// =====================================================
// Utility: IP client
// =====================================================
function get_client_ip()
{
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if (isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if (isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if (isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if (isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

function get_client_ip_single(): string
{
    // prende il primo IP valido tra gli header (gestisce X_FORWARDED_FOR con lista)
    $candidates = [];

    if (!empty($_SERVER['HTTP_CLIENT_IP'])) $candidates[] = $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) $candidates[] = $_SERVER['HTTP_X_FORWARDED_FOR'];
    if (!empty($_SERVER['HTTP_X_FORWARDED'])) $candidates[] = $_SERVER['HTTP_X_FORWARDED'];
    if (!empty($_SERVER['HTTP_FORWARDED_FOR'])) $candidates[] = $_SERVER['HTTP_FORWARDED_FOR'];
    if (!empty($_SERVER['HTTP_FORWARDED'])) $candidates[] = $_SERVER['HTTP_FORWARDED'];
    if (!empty($_SERVER['REMOTE_ADDR'])) $candidates[] = $_SERVER['REMOTE_ADDR'];

    foreach ($candidates as $raw) {
        // X_FORWARDED_FOR può essere "ip1, ip2, ip3"
        $parts = preg_split('/\s*,\s*/', (string)$raw);
        foreach ($parts as $ip) {
            $ip = trim($ip);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return 'UNKNOWN';
}

/**
 * Aggiorna last_login e last_IP per un genitore.
 * Usa query parametrica (consigliato).
 */
function aggiornaLoginGenitore(int $genitore_id): void
{
    if ($genitore_id <= 0) {
        return;
    }

    $ip = get_client_ip_single();

    // escaping minimo (dbExec NON usa prepared)
    $ip = addslashes($ip);
    $genitore_id = intval($genitore_id);

    $query = "
        UPDATE genitori
        SET
            last_login = NOW(),
            last_IP = '$ip'
        WHERE id = $genitore_id
        LIMIT 1
    ";

    dbExec($query);
}

function contaStudentiAttiviGenitore(int $genitore_id): int
{
    if ($genitore_id <= 0) {
        return 0;
    }

    $count = dbGetValue("
        SELECT COUNT(*)
        FROM genitori_studenti gs
        INNER JOIN studente s ON s.id = gs.id_studente
        WHERE gs.id_genitore = " . dbI($genitore_id) . "
          AND s.attivo = 1
    ");

    return intval($count);
}

function segnalaLoginMastercomGenitoreFallito(array $genitore, string $username, int $studentiAttivi, int $mastercomHttp, string $mastercomError): void
{
    static $sent = [];

    $genitoreId = intval($genitore['id'] ?? 0);
    if ($genitoreId <= 0 || isset($sent[$genitoreId])) {
        return;
    }
    $sent[$genitoreId] = true;

    global $__settings;

    try {
        $_SESSION['login_mastercom_assistenza'] = [
            'token' => bin2hex(random_bytes(24)),
            'expires_at' => time() + 1800,
            'genitore_id' => $genitoreId,
            'username' => $username,
            'studenti_attivi' => $studentiAttivi,
            'mastercom_http' => $mastercomHttp,
            'mastercom_error' => $mastercomError,
            'created_at' => time(),
        ];
    } catch (Throwable $e) {
        errorTelegram("Token assistenza login MasterCom non creato: " . $e->getMessage());
    }

    $botToken = trim((string)($__settings->telegram->bot_token ?? ''));
    $serviceChatId = trim((string)($__settings->telegram->chat_id ?? ''));
    if ($botToken === '' || $serviceChatId === '') {
        warningTelegram("Notifica login MasterCom genitore non inviata: configurazione Telegram mancante");
        return;
    }

    require_once __DIR__ . '/telegram_webhook_utils.php';
    require_once __DIR__ . '/telegram_webhook_api.php';

    $nome = trim((string)($genitore['nome'] ?? ''));
    $cognome = trim((string)($genitore['cognome'] ?? ''));
    $email = trim((string)($genitore['email'] ?? ''));
    $codiceFiscale = trim((string)($genitore['codice_fiscale'] ?? ''));
    $ip = get_client_ip_single();
    $userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $requestUri = trim((string)($_SERVER['REQUEST_URI'] ?? ''));

    $ticketText =
        "LOGIN_MASTERCOM\n" .
        "Problema login genitore: MasterCom non autentica le credenziali.\n\n" .
        "Genitore: " . trim($cognome . ' ' . $nome) . "\n" .
        "ID GestOre: " . $genitoreId . "\n" .
        "Username MasterCom: " . $username . "\n" .
        "Email: " . ($email !== '' ? $email : '-') . "\n" .
        "Codice fiscale: " . ($codiceFiscale !== '' ? $codiceFiscale : '-') . "\n" .
        "Studenti attivi collegati: " . $studentiAttivi . "\n" .
        "MasterCom HTTP: " . $mastercomHttp . "\n" .
        "MasterCom errore: " . ($mastercomError !== '' ? $mastercomError : '-') . "\n" .
        "IP: " . $ip . "\n" .
        "Pagina: " . ($requestUri !== '' ? $requestUri : '-') . "\n" .
        "User agent: " . tgCut($userAgent, 240);

    $sendRes = tgSendMessage(
        $botToken,
        $serviceChatId,
        "Avviso login MasterCom genitore\n\n" . tgCut($ticketText, 3300) . "\n\nIl genitore può aprire un ticket dalla pagina di errore.",
        []
    );
    if (empty($sendRes['ok'])) {
        errorTelegram("Notifica login MasterCom genitore fallita: " . ($sendRes['error'] ?? 'errore sconosciuto'));
    }
}

function mastercomGenitoreLoginMirrorAvailable(): bool
{
    require_once __DIR__ . '/mastercom/admin_lib.php';
    return mastercomAdminTableExists('mastercom_genitori')
        && mastercomAdminTableColumnExists('mastercom_genitori', 'mastercom_id_parente')
        && mastercomAdminTableColumnExists('mastercom_genitori', 'id_genitore_gestore');
}

function mastercomGenitoreMirrorByUsername(string $username): ?array
{
    $username = trim($username);
    if ($username === '' || !mastercomGenitoreLoginMirrorAvailable()) {
        return null;
    }

    $conditions = [];
    if (ctype_digit($username)) {
        $conditions[] = 'mastercom_id_parente = ' . dbI($username);
    }
    if (mastercomAdminTableColumnExists('mastercom_genitori', 'username')) {
        $conditions[] = 'LOWER(TRIM(username)) = ' . dbQ(strtolower($username));
    }
    if (!$conditions) {
        return null;
    }

    $activeWhere = mastercomAdminTableColumnExists('mastercom_genitori', 'attivo_mastercom')
        ? ' AND COALESCE(attivo_mastercom, 1) = 1'
        : '';

    $row = dbGetFirst("
        SELECT *
        FROM mastercom_genitori
        WHERE (" . implode(' OR ', $conditions) . ")
          " . $activeWhere . "
        LIMIT 1
    ");

    return $row ?: null;
}

$newlogin_genitore = false;

debug("checkSession: entering login/genitore+google block");
__dbg_session_state('BEFORE genitore/google auth block');

// =====================================================
// LOGIN GENITORI (username/password) - solo se NON già autenticato
// =====================================================
if (isset($_POST['username']) && isset($_POST['password']) && !isset($_SESSION['utente_ruolo'])) {
    debug("checkSession: login genitore con username/password POST");
    __dbg_session_state('GENITORE LOGIN: BEFORE handling POST credentials');

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    unset($_SESSION['login_mastercom_assistenza']);

    debug("checkSession: genitore username=" . $username);

    $mirrorAvailable = mastercomGenitoreLoginMirrorAvailable();
    $mirror = $mirrorAvailable ? mastercomGenitoreMirrorByUsername($username) : null;
    if ($mirrorAvailable && !$mirror) {
        $__message = 'Accesso MasterCom riservato ai genitori: username non censito fra i genitori della scuola. Se sei un docente usa Accesso Google.';
        debug("checkSession: username MasterCom non censito fra genitori -> fail");
        infoLogin("Username MasterCom non censito fra genitori: " . $username);
        warning($__message);
        redirect('/error/error.php?message=' . urlencode($__message));
        exit();
    }

    if ($mirror && intval($mirror['id_genitore_gestore'] ?? 0) > 0) {
        $query = "SELECT * FROM genitori WHERE id = " . dbI($mirror['id_genitore_gestore']) . " AND attivo = 1";
    } else {
        $query = "SELECT * FROM genitori WHERE username = " . dbQ($username) . " AND attivo = 1";
    }
    debug("checkSession: query genitori=" . $query);
    $genitore = dbGetFirst($query);
    $esiste_login = ($genitore != null);

    debug("checkSession: esiste_login=" . ($esiste_login ? "YES" : "NO"));

    if ($esiste_login) {
        debug("checkSession: trovato genitore con username=$username -> calling mastercom");
        $studentiAttiviGenitore = contaStudentiAttiviGenitore((int)$genitore['id']);
        $authResult = mastercomAuthenticate($username, $password, ['method' => 'GET']);

        if (!$authResult['ok'] && !empty($authResult['error']) && $authResult['response'] === null && intval($authResult['http_code']) === 0) {
            $__message = 'Impossibile collegarsi a MasterCom';
            debug("checkSession: mastercom ERROR=" . $authResult['error']);
            infoLogin("Impossibile collegarsi a MasterCom: " . $authResult['error']);
            warning($__message);
            redirect('/error/error.php?message=' . urlencode($__message));
            exit();
        } else {
            debug("checkSession: mastercom response=" . __dbg_mask($authResult['raw'] ?? null));
            $array = is_array($authResult['response'] ?? null) ? $authResult['response'] : [];

            if (empty($array["auth"])) {
                $__message = 'Credenziali MasterCom non valide. Controlla username e password del registro elettronico.';
                $mastercomHttp = intval($authResult['http_code'] ?? 0);
                $mastercomError = (string)($authResult['error'] ?? '');
                debug("checkSession: mastercom auth EMPTY -> fail http=$mastercomHttp error=" . $mastercomError . " studenti_attivi=" . $studentiAttiviGenitore);
                infoLogin(
                    "Credenziali MasterCom non valide: " . $username
                    . " | genitore_locale_id=" . intval($genitore['id'])
                    . " | locale_attivo=1"
                    . " | studenti_attivi=" . $studentiAttiviGenitore
                    . " | mastercom_http=" . $mastercomHttp
                    . " | mastercom_error=" . $mastercomError
                );
                segnalaLoginMastercomGenitoreFallito($genitore, $username, $studentiAttiviGenitore, $mastercomHttp, $mastercomError);
                warning($__message);
                redirect('/error/error.php?message=' . urlencode($__message));
                exit();
            } else {
                debug("checkSession: mastercom auth OK -> set session genitore");
                $newlogin_genitore = true;

                $session->set('utente_id', -1);
                $session->set('genitore_id', $genitore['id']);
                $session->set('genitore_nome', $genitore['nome']);
                $session->set('genitore_cognome', $genitore['cognome']);
                $session->set('genitore_email', $genitore['email']);
                $session->set('genitore_codice_fiscale', $genitore['codice_fiscale']);
                aggiornaLoginGenitore((int)$genitore['id']);

                $session->set('username', $genitore['nome'] . "." . $genitore['cognome']);
                $session->set('utente_nome', $genitore['nome']);
                $session->set('utente_cognome', $genitore['cognome']);
                $session->set('utente_ruolo', 'genitore');
                $session->set('__useremail', $genitore['email']);

                $__useremail = $session->get('__useremail');
                $__username = $session->get('username');
                $session->set('__username', $__username);

                $_SESSION['LAST_ACTIVITY'] = time();
                $_SESSION['EXPIRE_AFTER'] = intval($__settings->system->durata_sessione);

                debug("checkSession: genitore logged -> __username=$__username __useremail=$__useremail expire_after=" . intval($_SESSION['EXPIRE_AFTER']));
                __dbg_session_state('AFTER genitore login success');

                info("utente [" . $genitore['nome'] . $genitore['cognome'] . "]: logged in");
            }
        }
    } else {
        $__message = $mirror
            ? 'Credenziali MasterCom riconosciute ma non collegate a un genitore GestOre. Scrivi a gestore@buonarroti.tn.it.'
            : 'Genitore non trovato fra gli utenti della scuola: [' . $username . ']';
        debug("checkSession: genitore NOT found locally -> fail");
        infoLogin("Genitore non trovato fra gli utenti della scuola: " . $username);
        warning($__message);
        redirect('/error/error.php?message=' . urlencode($__message));
        exit();
    }
}

// =====================================================
// Se già autenticato come genitore, salta Google
// =====================================================
if (isset($_SESSION['utente_ruolo']) && $_SESSION['utente_ruolo'] === 'genitore') {
    debug("checkSession: ruolo genitore già in sessione -> skip Google Auth");
    __dbg_session_state('SKIP Google (already genitore)');
} else {

    // =====================================================
    // Google Auth Flow - solo se manca __username
    // =====================================================
    $__username = $__username ?? ($_SESSION['__username'] ?? null);

    if (empty($__username)) {
        __dbg_session_state('GOOGLE: missing __username -> start auth flow');

        debug("checkSession: __username missing and __username in session missing -> starting Google Auth flow");

        if (!isset($__gClient)) {
            $__redirectURL = $__http_base_link . '/index.php';

            // Include Google client library
            require_once __DIR__ . '/google-client-library/src/Google_Client.php';
            require_once __DIR__ . '/google-client-library/src/contrib/Google_Oauth2Service.php';

            // Call Google API
            $gClient = new Google_Client();
            $gClient->setApplicationName($__settings->GoogleAuth->applicationName);
            $gClient->setClientId($__settings->GoogleAuth->clientId);
            $gClient->setClientSecret($__settings->GoogleAuth->clientSecret);
            $gClient->setRedirectUri($__redirectURL);

            $google_oauthV2 = new Google_Oauth2Service($gClient);

            debug("checkSession: Google client initialized redirectURL=$__redirectURL");
        }

        // callback Google
        if (isset($_GET['code'])) {
            __dbg_session_state('GOOGLE CALLBACK: before authenticate');

            debug("checkSession: Google callback code present -> authenticate()");
            $gClient->authenticate($_GET['code']);
            $_SESSION['token'] = $gClient->getAccessToken();

            __dbg_session_state('GOOGLE CALLBACK: after token set');

            // log the new access
            $gpUserProfile = $google_oauthV2->userinfo->get();
            $useremail = $gpUserProfile['email'] ?? null;
            debug("checkSession: Google profile email=" . ($useremail ?? 'NULL'));
            $redirectAfterLogin = $_SESSION['redirect_after_login'] ?? $__redirectURL;
            unset($_SESSION['redirect_after_login']);
            infoLogin("utente [" . ($useremail ?? 'NULL') . "]: logging in with Google from IP " . get_client_ip());
            if (!is_string($redirectAfterLogin) || strpos($redirectAfterLogin, '/GestOre/') !== 0) {
                $redirectAfterLogin = $__redirectURL;
            }
            header('Location: ' . filter_var($redirectAfterLogin, FILTER_SANITIZE_URL));
            exit();
        }

        if (isset($_SESSION['token'])) {
            debug("checkSession: token exists in session -> setAccessToken");
            $gClient->setAccessToken($_SESSION['token']);
        }

        if ($gClient->getAccessToken()) {
            debug("checkSession: accessToken OK -> fetch userinfo");
            $gpUserProfile = $google_oauthV2->userinfo->get();
            $useremail = $gpUserProfile['email'] ?? null;

            if (empty($useremail)) {
                debug("checkSession: useremail empty after google -> output error");
                $output = '<h3 style="color:red">Some problem occurred, please try again.</h3>';
            }
        } else {
            $authUrl = $gClient->createAuthUrl();
            debug("checkSession: no accessToken -> authUrl=" . __dbg_mask($authUrl, 400));

            // ✅ PATCH: su index.php NON fare redirect automatico a Google,
            // ma lascia che index mostri il bottone/link di login.
            if ($__is_login_page) {
                debug("checkSession: index.php detected -> show login link (NO auto-redirect)");
                $output = '<a href="' . filter_var($authUrl, FILTER_SANITIZE_URL) . '"><img src="' . $__application_base_path . '/img/glogin.png" alt=""/></a>';
                return;
            }

            // pagine interne: se non c'e' una sessione applicativa valida,
            // rientra dalla pagina di login invece di lasciare una pagina bianca
            // in caso di problemi nel passaggio diretto verso Google.
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/index.php';
            debug("checkSession: internal page without app session -> redirect /index.php");
            redirect('/index.php');
            exit();
        }
    }

    // =====================================================
    // Ricava email utente (da sessione o da google var)
    // =====================================================
    if (!isset($__useremail)) {
        $__useremail = $session->get('__useremail');
        debug("checkSession: __useremail loaded from session -> " . ($__useremail ?? 'NULL'));
    }

    if (!isset($__useremail)) {
        $__useremail = $useremail ?? null;
        debug("checkSession: __useremail loaded from google var -> " . ($__useremail ?? 'NULL'));
    }

    // deve esserci un utente collegato
    if (empty($__useremail)) {
        warning('nessun utente collegato!');
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/index.php';
        debug("checkSession: EMPTY __useremail -> redirect /index.php");
        redirect('/index.php');
        exit();
    }

    // =====================================================
    // Se manca utente_id: lookup DB utente / studente
    // =====================================================
    if (!$session->has('utente_id')) {
        __dbg_session_state('BEFORE lookup utente/studente');
        debug('checkSession: manca in sessione utente_id -> lookup utente/studente by email=' . $__useremail);

        $utente = dbGetFirst("SELECT * FROM utente WHERE utente.email = '$__useremail'");
        if ($utente != null) {
            debug("checkSession: found utente id=" . $utente['id'] . " ruolo=" . $utente['ruolo']);

            infoLogin("utente [$utente[username]]: logged in - role=[" . $utente['ruolo'] . "]");

            $session->set('utente_id', $utente['id']);
            $session->set('username', $utente['username']);
            $session->set('utente_nome', $utente['nome']);
            $session->set('utente_cognome', $utente['cognome']);
            $session->set('utente_ruolo', $utente['ruolo']);
            $session->set('__useremail', $__useremail);

            $_SESSION['LAST_ACTIVITY'] = time();
            $_SESSION['EXPIRE_AFTER'] = intval($__settings->system->durata_sessione);

            if (!empty($utente['username'])) {
                $__username = $session->get('username');
                $session->set('__username', $__username);
                info('utente [' . $utente['username'] . ']: logged in');
            }

            __dbg_session_state('AFTER lookup+set utente');
        } else {

            debug("checkSession: utente NOT found -> lookup email genitore");

            $genitore = dbGetFirst("SELECT * FROM genitori WHERE genitori.email = '$__useremail' AND genitori.attivo = 1");
            if ($genitore != null) {
                debug("checkSession: found genitore id=" . $genitore['id']);

                infoLogin("utente [" . $genitore['nome'] . $genitore['cognome'] . "]: logged in - role=[genitore]");
                $newlogin_genitore = true;
                $session->set('utente_id', -1);
                $session->set('genitore_id', $genitore['id']);
                $session->set('genitore_nome', $genitore['nome']);
                $session->set('genitore_cognome', $genitore['cognome']);
                $session->set('genitore_email', $__useremail);
                $session->set('genitore_codice_fiscale', $genitore['codice_fiscale']);
                aggiornaLoginGenitore((int)$genitore['id']);

                $session->set('username', $genitore['nome'] . "." . $genitore['cognome']);
                $session->set('utente_nome', $genitore['nome']);
                $session->set('utente_cognome', $genitore['cognome']);
                $session->set('utente_ruolo', 'genitore');
                $session->set('__useremail', $__useremail);

                $__username = $session->get('username');
                $session->set('__username', $__username);

                $_SESSION['LAST_ACTIVITY'] = time();
                $_SESSION['EXPIRE_AFTER'] = intval($__settings->system->durata_sessione);

                info("utente [" . $genitore['nome'] . "]: logged in");

                __dbg_session_state('AFTER lookup+set genitore');
            } else {
                debug("checkSession: utente NOT found -> lookup studente");

                $studente = dbGetFirst("SELECT * FROM studente WHERE studente.email = '$__useremail' AND studente.attivo = 1");
                if ($studente != null) {
                    debug("checkSession: found studente id=" . $studente['id']);

                    infoLogin("utente [" . $studente['nome'] . $studente['cognome'] . "]: logged in - role=[studente]");

                    $session->set('utente_id', -1);
                    $session->set('studente_id', $studente['id']);
                    $session->set('studente_nome', $studente['nome']);
                    $session->set('studente_cognome', $studente['cognome']);
                    $session->set('studente_email', $__useremail);
                    $session->set('studente_codice_fiscale', $studente['codice_fiscale']);

                    $session->set('username', $studente['nome'] . "." . $studente['cognome']);
                    $session->set('utente_nome', $studente['nome']);
                    $session->set('utente_cognome', $studente['cognome']);
                    $session->set('utente_ruolo', 'studente');
                    $session->set('__useremail', $__useremail);

                    $__username = $session->get('username');
                    $session->set('__username', $__username);

                    $_SESSION['LAST_ACTIVITY'] = time();
                    $_SESSION['EXPIRE_AFTER'] = intval($__settings->system->durata_sessione);

                    info("utente [" . $studente['nome'] . $studente['cognome'] . "]: logged in");

                    __dbg_session_state('AFTER lookup+set studente');
                } else {

                    // ============================
                    // TENTATIVO LOGIN PERSONALE ATA
                    // ============================
                    // (MODIFICHE SOLO QUI: riallineo $__useremail / $__username e setto personale_ata_*)
                    $emailEsc = addslashes($__useremail);
                    $userEsc  = addslashes((string)($session->get('__username') ?? $session->get('username') ?? ''));

                    $ata = dbGetFirst("
                        SELECT id, nome, cognome, email, username, attivo
                        FROM personale_ata
                        WHERE attivo = 1
                          AND (
                                email = '$emailEsc'
                                OR username = '$userEsc'
                              )
                        LIMIT 1
                    ");

                    if ($ata != null) {

                        // Utente applicativo "virtuale"
                        $session->set('utente_id', -1);
                        $session->set('utente_nome', $ata['nome']);
                        $session->set('utente_cognome', $ata['cognome']);
                        $session->set('utente_ruolo', 'personale-ata');

                        // Username: usa quello in tabella, altrimenti fallback "nome.cognome"
                        $uname = !empty($ata['username']) ? $ata['username'] : ($ata['nome'] . "." . $ata['cognome']);
                        $session->set('username', $uname);
                        $session->set('__username', $uname);

                        // Email coerente
                        $session->set('__useremail', $ata['email']);

                        // >>> MOD: riallineo anche le variabili PHP locali (non solo sessione)
                        $__useremail = $ata['email'];
                        $__username  = $uname;

                        // Session vars ATA dedicate
                        $session->set('personale_ata_id', (int)$ata['id']);
                        $session->set('personale_ata_nome', $ata['nome']);
                        $session->set('personale_ata_cognome', $ata['cognome']);
                        $session->set('personale_ata_email', $ata['email']);

                        $_SESSION['LAST_ACTIVITY'] = time();
                        $_SESSION['EXPIRE_AFTER']  = intval($__settings->system->durata_sessione);

                        debug("checkSession: login PERSONALE_ATA OK -> id=" . $ata['id'] . " " . $ata['cognome'] . " " . $ata['nome']);
                        __dbg_session_state('AFTER lookup+set personale_ata');

                        // ok: non redirect qui (lo gestisce index.php)
                    } else {
                        // Nessuna anagrafica trovata
                        $__message = 'La mail utilizzata non è presente in anagrafica: [' . $__useremail . ']';
                        debug("checkSession: neither utente nor genitore nor studente nor personale_ata found -> redirect error");
                        infoLogin("utente non trovato: " . $__useremail);
                        warning($__message);
                        redirect('/error/error.php?message=' . $__message);
                        exit();
                    }
                }
            }
        }
    } else {
        debug("checkSession: utente_id exists in session -> " . __dbg_mask($session->get('utente_id')));
    }
}

// =====================================================
// Variabili globali “risolte” dalla sessione
// =====================================================
$__utente_id = $session->get('utente_id');
$__username = $session->get('username');
$__utente_nome = $session->get('utente_nome');
$__utente_cognome = $session->get('utente_cognome');
$__utente_ruolo = $session->get('utente_ruolo');

if ($__utente_ruolo === "esterno") {
    $session->set('esterno_id', $__utente_id);
    $session->set('esterno_nome', $__utente_nome);
    $session->set('esterno_cognome', $__utente_cognome);
    $session->set('esterno_email', $__useremail);
}

if ($__utente_ruolo === "portineria") {
    $session->set('portineria_id', $__utente_id);
    $session->set('portineria_nome', $__utente_nome);
    $session->set('portineria_cognome', $__utente_cognome);
    $session->set('portineria_email', $__useremail);
}

debug("checkSession: resolved user -> id=" . ($__utente_id ?? 'NULL')
    . " username=" . ($__username ?? 'NULL')
    . " ruolo=" . ($__utente_ruolo ?? 'NULL'));

__dbg_session_state('AFTER resolved user globals');

// =====================================================
// Personale ATA mapping (se NON già ruolo "forte")
// =====================================================
// Nota: qui "promuoviamo" a personale-ata solo se l'utente non è già:
// docente / studente / genitore / esterno / segreterie / dirigente / admin
if (!empty($__useremail)) {

    $ruoloAttuale = (string)($session->get('utente_ruolo') ?? '');

    $ruoliForti = [
        'docente',
        'studente',
        'genitore',
        'esterno',
        'portineria',
        'segreteria-docenti',
        'segreteria-ata',
        'dirigente',
        'admin'
    ];

    if (!in_array($ruoloAttuale, $ruoliForti, true)) {

        // >>> MOD: se ho già personale_ata_id in sessione (perché ho già fatto login ATA sopra),
        //          NON rifaccio query e NON riassegno campi.
        if (!$session->has('personale_ata_id') || intval($session->get('personale_ata_id')) <= 0) {

            // escape minimale (dbExec/dbGetFirst non usano prepared)
            $emailEsc = addslashes($__useremail);
            $userEsc  = addslashes((string)$__username);

            $ata = dbGetFirst("
                SELECT id, nome, cognome, email, username, attivo
                FROM personale_ata
                WHERE attivo = 1
                  AND (
                        email = '$emailEsc'
                        OR username = '$userEsc'
                      )
                LIMIT 1
            ");

            if ($ata != null) {

                // set session ATA
                $session->set('personale_ata_id', (int)$ata['id']);
                $session->set('personale_ata_nome', $ata['nome']);
                $session->set('personale_ata_cognome', $ata['cognome']);
                $session->set('personale_ata_email', $ata['email']);

                // imposta il ruolo applicativo richiesto
                $session->set('utente_ruolo', 'personale-ata');

                // riallineo i globals già calcolati
                $__utente_ruolo = 'personale-ata';

                debug("checkSession: personale_ata recognized -> id=" . $ata['id'] . " " . $ata['cognome'] . " " . $ata['nome']);
                __dbg_session_state('ATA: after set personale_ata');
            }
        } else {
            // già in sessione -> garantisco ruolo coerente
            if ($ruoloAttuale !== 'personale-ata') {
                $session->set('utente_ruolo', 'personale-ata');
                $__utente_ruolo = 'personale-ata';
            }
        }
    }
}

// =====================================================
// Globals Personale ATA
// =====================================================
$__ata_id      = $session->get('personale_ata_id');
$__ata_nome    = $session->get('personale_ata_nome');
$__ata_cognome = $session->get('personale_ata_cognome');
$__ata_email   = $session->get('personale_ata_email');

// =====================================================
// Docente mapping (se ruolo docente)
// =====================================================
if (!$session->has('docente_id') && $session->has('utente_ruolo') && ($session->get('utente_ruolo') === "docente")) {
    debug('checkSession: manca in sessione docente_id (ruolo docente) -> lookup docente');
    __dbg_session_state('DOCENTE: before lookup docente');

    $docente = dbGetFirst("SELECT * FROM docente WHERE docente.username = '$__username' AND docente.attivo = 1");
    if ($docente == null) {
        debug("checkSession: docente NOT found -> unauthorized");
        redirect("/error/unauthorized.php");
        exit();
    }
    $session->set('docente_id', $docente['id']);
    $session->set('docente_nome', $docente['nome']);
    $session->set('docente_cognome', $docente['cognome']);
    $session->set('docente_email', $__useremail);

    __dbg_session_state('DOCENTE: after set docente_id');
} else {
    debug("checkSession: docente_id=" . ($session->get('docente_id') ?? 'NULL'));
}

// =====================================================
// Altri globals (docente/studente/genitore)
// =====================================================
$__docente_id = $session->get('docente_id');
$__docente_nome = $session->get('docente_nome');
$__docente_cognome = $session->get('docente_cognome');
$__docente_email = $session->get('docente_email');

$__studente_id = $session->get('studente_id');
$__studente_nome = $session->get('studente_nome');
$__studente_cognome = $session->get('studente_cognome');
$__studente_email = $session->get('studente_email');

$__genitore_id = $session->get('genitore_id');
$__genitore_nome = $session->get('genitore_nome');
$__genitore_cognome = $session->get('genitore_cognome');
$__genitore_email = $session->get('genitore_email');
$__genitore_codice_fiscale = $session->get('genitore_codice_fiscale');

$__esterno_id = $session->get('esterno_id');
$__esterno_nome = $session->get('esterno_nome');
$__esterno_cognome = $session->get('esterno_cognome');
$__esterno_email = $session->get('esterno_email');

$__portineria_id = $session->get('portineria_id');
$__portineria_nome = $session->get('portineria_nome');
$__portineria_cognome = $session->get('portineria_cognome');
$__portineria_email = $session->get('portineria_email');

// =====================================================
// Anno scolastico
// =====================================================
if (!$session->has('anno_scolastico_corrente_anno')) {
    debug('checkSession: manca in sessione anno_scolastico_corrente_anno -> load from DB');
    __dbg_session_state('ANNO: before load anno_scolastico_corrente');

    $anno = dbGetFirst("SELECT * FROM anno_scolastico_corrente");
    $session->set('anno_scolastico_corrente_id', $anno['anno_scolastico_id']);
    $session->set('anno_scolastico_corrente_anno', $anno['anno']);
    $session->set('anno_scolastico_scorso_id', $anno['anno_scorso_id']);

    __dbg_session_state('ANNO: after set anno_scolastico_corrente');
} else {
    debug("checkSession: anno_scolastico_corrente_anno exists -> " . $session->get('anno_scolastico_corrente_anno'));
}

$__anno_scolastico_corrente_id = $session->get('anno_scolastico_corrente_id');
$__anno_scolastico_corrente_anno = $session->get('anno_scolastico_corrente_anno');
$__anno_scolastico_scorso_id = $session->get('anno_scolastico_scorso_id');

// =====================================================
// Redirect post-login genitore
// =====================================================
if ($newlogin_genitore) {
    debug("checkSession: newlogin_genitore=true -> redirect /genitore/index.php");
    __dbg_session_state('GENITORE: before redirect /genitore/index.php');
    redirect("/genitore/index.php");
}

__dbg_session_state('EXIT OK');
debug("checkSession: EXIT OK");
