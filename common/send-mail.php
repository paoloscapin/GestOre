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

        $ok = $mail->send();
        info("[send-mail] send ok=" . ($ok ? "1" : "0") . " to=$to subj=$subject");

        try {
            $mail->smtpClose();
        } catch (Throwable $e2) {
        }

        return (bool)$ok;
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
    try {
        $ok = $mail->send();
        info("[send-mail] send ok=" . ($ok ? "1" : "0") . " to=$to subj=$subject");
        $mail->smtpClose();
        return (bool)$ok;
    } catch (Throwable $e) {
        error("[send-mail] EXCEPTION: " . $e->getMessage());
        try {
            $mail->smtpClose();
        } catch (Throwable $e2) {
        }
        return false;
    }
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
    try {
        $ok = $mail->send();
        info("[send-mail] send ok=" . ($ok ? "1" : "0") . " to=$to subj=$subject");
        $mail->smtpClose();
        return (bool)$ok;
    } catch (Throwable $e) {
        error("[send-mail] EXCEPTION: " . $e->getMessage());
        try {
            $mail->smtpClose();
        } catch (Throwable $e2) {
        }
        return false;
    }
  
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
    $attachments = $options['attachments'] ?? [];
    $embeddedImages = $options['embedded_images'] ?? [];
    $addBcc = array_key_exists('add_bcc_default', $options) ? (bool)$options['add_bcc_default'] : false;

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
        $mail->setFrom($fromEmail, $fromName, true);
        $mail->addReplyTo($replyToEmail, $replyToName);
        if ($senderEmail !== '') {
            $mail->Sender = $senderEmail;
            $mail->addCustomHeader('Sender', $senderName !== '' ? sprintf('"%s" <%s>', addslashes($senderName), $senderEmail) : $senderEmail);
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

        $ok = $mail->send();
        info("[send-mail] send custom ok=" . ($ok ? "1" : "0") . " to=$to subj=$subject");

        try {
            $mail->smtpClose();
        } catch (Throwable $e2) {
        }

        return (bool)$ok;
    } catch (Throwable $e) {
        error("[send-mail] CUSTOM EXCEPTION: " . $e->getMessage());

        try {
            $mail->smtpClose();
        } catch (Throwable $e2) {
        }

        return false;
    }
}
