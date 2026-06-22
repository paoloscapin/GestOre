<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$rdir = str_replace("\\", "/", dirname(__FILE__));
require $rdir . '/PHPMailer-master/src/Exception.php';
require $rdir . '/PHPMailer-master/src/PHPMailer.php';
require $rdir . '/PHPMailer-master/src/SMTP.php';
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/__Settings.php';

function sendMailBase64Url(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function sendMailOAuthConfig()
{
    global $__settings;
    return $__settings->local->mailOAuth ?? null;
}

function sendMailOAuthEnabledForSender(string $senderEmail): bool
{
    $cfg = sendMailOAuthConfig();
    if (!$cfg || empty($cfg->enabled)) {
        return false;
    }

    $senderEmail = strtolower(trim($senderEmail));
    $allowed = $cfg->allowedSenders ?? [];
    if (empty($allowed)) {
        return true;
    }

    foreach ($allowed as $email) {
        if (strtolower(trim((string)$email)) === $senderEmail) {
            return true;
        }
    }

    return false;
}

function sendMailOAuthFallbackEnabled(): bool
{
    $cfg = sendMailOAuthConfig();
    return !$cfg || !isset($cfg->fallbackSmtp) || !empty($cfg->fallbackSmtp);
}

function sendMailServiceAccountPath(): string
{
    $cfg = sendMailOAuthConfig();
    $path = trim((string)($cfg->serviceAccountFile ?? ''));
    if ($path === '') {
        throw new Exception('serviceAccountFile mancante in local.mailOAuth');
    }

    if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) || substr($path, 0, 1) === '/' || substr($path, 0, 1) === '\\') {
        return $path;
    }

    return realpath(__DIR__ . '/../' . $path) ?: (__DIR__ . '/../' . $path);
}

function sendMailLoadServiceAccount(): array
{
    $path = sendMailServiceAccountPath();
    if (!is_file($path)) {
        throw new Exception('File service account mail non trovato: ' . $path);
    }

    $json = json_decode(file_get_contents($path), true);
    if (!is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
        throw new Exception('File service account mail non valido');
    }

    return $json;
}

function sendMailOAuthAccessToken(string $subjectEmail, string $scope = 'https://www.googleapis.com/auth/gmail.send'): string
{
    static $cache = [];

    $subjectEmail = strtolower(trim($subjectEmail));
    $scope = trim($scope) !== '' ? trim($scope) : 'https://www.googleapis.com/auth/gmail.send';
    $cacheKey = $subjectEmail . '|' . $scope;
    if (isset($cache[$cacheKey]) && $cache[$cacheKey]['expires_at'] > time() + 60) {
        return $cache[$cacheKey]['access_token'];
    }

    $sa = sendMailLoadServiceAccount();
    $now = time();

    $header = [
        'alg' => 'RS256',
        'typ' => 'JWT',
    ];
    $claim = [
        'iss' => $sa['client_email'],
        'scope' => $scope,
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now,
        'sub' => $subjectEmail,
    ];

    $unsigned = sendMailBase64Url(json_encode($header)) . '.' . sendMailBase64Url(json_encode($claim));
    $privateKey = openssl_pkey_get_private($sa['private_key']);
    if (!$privateKey) {
        throw new Exception('Private key service account mail non caricabile');
    }

    $signature = '';
    if (!openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        throw new Exception('Firma JWT service account mail non riuscita');
    }
    if (function_exists('openssl_pkey_free')) {
        openssl_pkey_free($privateKey);
    }

    $jwt = $unsigned . '.' . sendMailBase64Url($signature);
    $tokenUri = trim((string)($sa['token_uri'] ?? 'https://oauth2.googleapis.com/token')) ?: 'https://oauth2.googleapis.com/token';

    $ch = curl_init($tokenUri);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ]));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception('Errore CURL token Gmail API: ' . $curlError);
    }

    $decoded = json_decode((string)$response, true);
    if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded) || empty($decoded['access_token'])) {
        $clientId = trim((string)($sa['client_id'] ?? ''));
        throw new Exception('Errore token Gmail API HTTP ' . $httpCode . ': ' . $response . ' Verifica delega dominio client ID ' . $clientId . ' con scope ' . $scope . ' e subject ' . $subjectEmail . '.');
    }

    $cache[$cacheKey] = [
        'access_token' => $decoded['access_token'],
        'expires_at' => time() + intval($decoded['expires_in'] ?? 3600),
    ];

    return $cache[$cacheKey]['access_token'];
}

function sendMailGmailApiSendRaw(string $senderEmail, string $rawMime): array
{
    $accessToken = sendMailOAuthAccessToken($senderEmail);
    $body = json_encode(['raw' => sendMailBase64Url($rawMime)]);

    $ch = curl_init('https://gmail.googleapis.com/gmail/v1/users/me/messages/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception('Errore CURL invio Gmail API: ' . $curlError);
    }

    $decoded = json_decode((string)$response, true);
    if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded)) {
        throw new Exception('Errore invio Gmail API HTTP ' . $httpCode . ': ' . $response);
    }

    return $decoded;
}

function sendMailDispatch(PHPMailer $mail, string $senderEmail, string $logLabel, string $to, string $subject): bool
{
    $senderEmail = strtolower(trim($senderEmail));
    $GLOBALS['__sendMailLastDispatchResult'] = [
        'ok' => false,
        'transport' => '',
        'sender' => $senderEmail,
        'gmail_message_id' => '',
        'error' => '',
    ];

    if (sendMailOAuthEnabledForSender($senderEmail)) {
        try {
            if (!$mail->preSend()) {
                throw new Exception('PHPMailer preSend non riuscito');
            }
            $res = sendMailGmailApiSendRaw($senderEmail, $mail->getSentMIMEMessage());
            info("[send-mail] $logLabel gmail_api ok=1 id=" . trim((string)($res['id'] ?? '')) . " from=$senderEmail to=$to subj=$subject");
            $GLOBALS['__sendMailLastDispatchResult'] = [
                'ok' => true,
                'transport' => 'gmail_api',
                'sender' => $senderEmail,
                'gmail_message_id' => trim((string)($res['id'] ?? '')),
                'error' => '',
            ];
            return true;
        } catch (Throwable $e) {
            error("[send-mail] $logLabel GMAIL API EXCEPTION: " . $e->getMessage());
            $GLOBALS['__sendMailLastDispatchResult']['error'] = $e->getMessage();
            if (!sendMailOAuthFallbackEnabled()) {
                return false;
            }
            info("[send-mail] $logLabel fallback SMTP from=$senderEmail to=$to subj=$subject");
        }
    }

    try {
        $ok = $mail->send();
        info("[send-mail] $logLabel smtp ok=" . ($ok ? "1" : "0") . " to=$to subj=$subject");
        $GLOBALS['__sendMailLastDispatchResult'] = [
            'ok' => (bool)$ok,
            'transport' => 'smtp',
            'sender' => $senderEmail,
            'gmail_message_id' => '',
            'error' => '',
        ];
        try {
            $mail->smtpClose();
        } catch (Throwable $e2) {
        }
        return (bool)$ok;
    } catch (Throwable $e) {
        error("[send-mail] $logLabel SMTP EXCEPTION: " . $e->getMessage());
        $GLOBALS['__sendMailLastDispatchResult'] = [
            'ok' => false,
            'transport' => 'smtp',
            'sender' => $senderEmail,
            'gmail_message_id' => '',
            'error' => $e->getMessage(),
        ];
        try {
            $mail->smtpClose();
        } catch (Throwable $e2) {
        }
        return false;
    }
}

function sendMailLastDispatchResult(): array
{
    return $GLOBALS['__sendMailLastDispatchResult'] ?? [
        'ok' => false,
        'transport' => '',
        'sender' => '',
        'gmail_message_id' => '',
        'error' => '',
    ];
}

function sendMail($to, $toName, $subject, $Content): bool
{
    global $__settings;

    $mail = new PHPMailer(true);

    try {
        $mail->CharSet = "utf-8";
        $mail->Encoding = "base64";
        $mail->isSMTP();
        $mail->Mailer = "smtp";
        $mail->SMTPDebug = 0;
        $mail->Host = $__settings->local->smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $__settings->local->smtpMail;
        $mail->Password = $__settings->local->AppPassword;
        $mail->SMTPSecure = $__settings->local->SMTPSecure;
        $mail->SMTPAutoTLS = false;
        $mail->CharSet = 'UTF-8';
        $mail->Port = $__settings->local->Port;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->IsHTML(true);
        $mail->addAddress($to, $toName);
        $mail->setFrom($__settings->local->emailNoReplyFrom, "GestOre " . $__settings->local->nomeIstituto, true);
        $mail->addReplyTo($__settings->local->emailNoReplyFrom, "GestOre " . $__settings->local->nomeIstituto);
        $mail->addBCC($__settings->local->emailSportelli, "Gestione attività GestOre");

        $mail->Subject = $subject;
        $mail->msgHTML($Content);

        return sendMailDispatch($mail, (string)$__settings->local->emailNoReplyFrom, 'send', (string)$to, (string)$subject);
    } catch (Throwable $e) {
        error("[send-mail] EXCEPTION: " . $e->getMessage());

        try {
            $mail->smtpClose();
        } catch (Throwable $e2) {
        }

        return false;
    }
}



function sendMailCC($to, $toName, $toCC, $toCCName, $subject, $Content)
{

    global $__settings;
    $mail = new PHPMailer(true);
    $mail->CharSet = "utf-8";
    $mail->Encoding = "base64";
    //Configure an SMTP
    $mail->isSMTP();
    $mail->Mailer = "smtp";
    $mail->SMTPDebug = 0;
    $mail->Host = $__settings->local->smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $__settings->local->smtpMail;
    $mail->Password = $__settings->local->AppPassword;
    $mail->SMTPSecure = $__settings->local->SMTPSecure;
    $mail->SMTPAutoTLS = false;
    $mail->CharSet = 'UTF-8';
    $mail->Port = $__settings->local->Port;
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    $mail->IsHTML(true);
    $mail->AddAddress($to, $toName);
    $mail->SetFrom($__settings->local->emailNoReplyFrom, "GestOre " . $__settings->local->nomeIstituto, true);
    $mail->AddReplyTo($__settings->local->emailNoReplyFrom, "GestOre " . $__settings->local->nomeIstituto);

    $mail->addBCC($__settings->local->emailSportelli, "Gestione attività GestOre");

    // Dividiamo i valori per la virgola e rimuoviamo eventuali spazi
    $ccEmails = array_map('trim', explode(',', $toCC));
    $ccNames  = array_map('trim', explode(',', $toCCName));

    // Cicliamo su tutti gli indirizzi
    foreach ($ccEmails as $index => $ccEmail) {
        // Prende il nome corrispondente se esiste, altrimenti stringa vuota
        $ccName = isset($ccNames[$index]) ? $ccNames[$index] : '';
        $mail->addCC($ccEmail, $ccName);
    }

    $mail->Subject = $subject;
    $content = $Content;

    // Attempt to send the email
    $mail->msgHTML($content);
    return sendMailDispatch($mail, (string)$__settings->local->emailNoReplyFrom, 'sendCC', (string)$to, (string)$subject);
}

function sendMailwithAttachment($to, $toName, $subject, $Content, $AttachmentFilePath)
{

    global $__settings;
    $mail = new PHPMailer(true);
    $mail->CharSet = "utf-8";
    $mail->Encoding = "base64";
    //Configure an SMTP
    $mail->isSMTP();
    $mail->Mailer = "smtp";
    $mail->SMTPDebug = 0;
    $mail->Host = $__settings->local->smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $__settings->local->smtpMail;
    $mail->Password = $__settings->local->AppPassword;
    $mail->SMTPSecure = $__settings->local->SMTPSecure;
    $mail->SMTPAutoTLS = false;
    $mail->CharSet = 'UTF-8';
    $mail->Port = $__settings->local->Port;
    // Allegato
    if (!empty($AttachmentFilePath)) {
        if (is_array($AttachmentFilePath)) {
            foreach ($AttachmentFilePath as $file) {
                if (is_file($file)) {
                    $mail->addAttachment($file);
                }
            }
        } else {
            if (is_file($AttachmentFilePath)) {
                $mail->addAttachment($AttachmentFilePath);
            }
        }
    }
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    $mail->IsHTML(true);
    $mail->AddAddress($to, $toName);
    $mail->SetFrom($__settings->local->emailNoReplyFrom, "GestOre " . $__settings->local->nomeIstituto, true);
    $mail->AddReplyTo($__settings->local->emailNoReplyFrom, "GestOre " . $__settings->local->nomeIstituto);

    $mail->addBCC($__settings->local->emailSportelli, "Gestione attività GestOre");
    $mail->Subject = $subject;
    $content = $Content;

    // Attempt to send the email
    $mail->msgHTML($content);
    return sendMailDispatch($mail, (string)$__settings->local->emailNoReplyFrom, 'sendAttachment', (string)$to, (string)$subject);
  
}

function sendMailCustom($to, $toName, $subject, $Content, array $options = []): bool
{
    global $__settings;

    $fromEmail = trim((string)($options['from_email'] ?? $__settings->local->emailNoReplyFrom));
    $fromName = trim((string)($options['from_name'] ?? ("GestOre " . $__settings->local->nomeIstituto)));
    $replyToEmail = trim((string)($options['reply_to_email'] ?? $fromEmail));
    $replyToName = trim((string)($options['reply_to_name'] ?? $fromName));
    $senderEmail = trim((string)($options['sender_email'] ?? $__settings->local->smtpMail));
    $senderName = trim((string)($options['sender_name'] ?? $fromName));
    $dispatchSenderEmail = trim((string)($options['dispatch_sender_email'] ?? ''));
    $smtpHost = trim((string)($options['smtp_host'] ?? $__settings->local->smtpHost));
    $smtpUsername = trim((string)($options['smtp_username'] ?? $__settings->local->smtpMail));
    $smtpPassword = (string)($options['smtp_password'] ?? $__settings->local->AppPassword);
    $smtpSecure = (string)($options['smtp_secure'] ?? $__settings->local->SMTPSecure);
    $smtpPort = intval($options['smtp_port'] ?? $__settings->local->Port);
    $ccRecipients = $options['cc'] ?? [];
    $attachments = $options['attachments'] ?? [];
    $embeddedImages = $options['embedded_images'] ?? [];
    $customHeaders = $options['custom_headers'] ?? [];
    $addBcc = array_key_exists('add_bcc_default', $options) ? (bool)$options['add_bcc_default'] : false;

    $mail = new PHPMailer(true);

    try {
        $mail->CharSet = "utf-8";
        $mail->Encoding = "base64";
        $mail->isSMTP();
        $mail->Mailer = "smtp";
        $mail->SMTPDebug = 0;
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUsername;
        $mail->Password = $smtpPassword;
        $mail->SMTPSecure = $smtpSecure;
        $mail->SMTPAutoTLS = false;
        $mail->CharSet = 'UTF-8';
        $mail->Port = $smtpPort;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->IsHTML(true);
        $mail->addAddress($to, $toName);
        if (!is_array($ccRecipients)) {
            $ccRecipients = [$ccRecipients];
        }
        foreach ($ccRecipients as $ccEmail => $ccName) {
            if (is_int($ccEmail)) {
                $ccEmail = is_array($ccName) ? (string)($ccName['email'] ?? '') : (string)$ccName;
                $ccName = is_array($ccName) ? (string)($ccName['name'] ?? '') : '';
            }
            $ccEmail = trim((string)$ccEmail);
            if ($ccEmail !== '') {
                $mail->addCC($ccEmail, trim((string)$ccName));
            }
        }
        $mail->setFrom($fromEmail, $fromName, true);
        $mail->addReplyTo($replyToEmail, $replyToName);
        if ($senderEmail !== '') {
            $mail->Sender = $senderEmail;
            $mail->addCustomHeader('Sender', $senderName !== '' ? sprintf('"%s" <%s>', addslashes($senderName), $senderEmail) : $senderEmail);
        }
        if (is_array($customHeaders)) {
            foreach ($customHeaders as $headerName => $headerValue) {
                $headerName = trim((string)$headerName);
                $headerValue = trim((string)$headerValue);
                if ($headerName !== '' && $headerValue !== '') {
                    $mail->addCustomHeader($headerName, $headerValue);
                }
            }
        }

        if ($addBcc && trim((string)$__settings->local->emailSportelli) !== '') {
            $mail->addBCC($__settings->local->emailSportelli, "Gestione attività GestOre");
        }

        if (!is_array($attachments)) {
            $attachments = [$attachments];
        }
        foreach ($attachments as $file) {
            if (is_string($file) && is_file($file)) {
                $mail->addAttachment($file);
            }
        }

        if (is_array($embeddedImages)) {
            foreach ($embeddedImages as $cid => $file) {
                if (is_string($cid) && is_string($file) && is_file($file)) {
                    $mail->addEmbeddedImage($file, $cid);
                }
            }
        }

        $mail->Subject = $subject;
        $mail->msgHTML($Content);

        if ($dispatchSenderEmail === '') {
            $dispatchSenderEmail = $fromEmail !== '' ? $fromEmail : $smtpUsername;
        }
        return sendMailDispatch($mail, $dispatchSenderEmail, 'sendCustom', (string)$to, (string)$subject);
    } catch (Throwable $e) {
        error("[send-mail] CUSTOM EXCEPTION: " . $e->getMessage());

        try {
            $mail->smtpClose();
        } catch (Throwable $e2) {
        }

        return false;
    }
}
