<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$rdir = str_replace("\\", "/", dirname(__FILE__));          
require $rdir . '/PHPMailer-master/src/Exception.php';
require $rdir . '/PHPMailer-master/src/PHPMailer.php';
require $rdir . '/PHPMailer-master/src/SMTP.php';
require_once '../common/connect.php';
require_once __DIR__ . '/__Settings.php';

function sendMail($to, $toName, $subject, $Content)
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
    $mail->Password = $__settings->local->smtpPassword;
    $mail->SMTPSecure = "ssl";
    $mail->SMTPAutoTLS = false;
    $mail->Port = '465';
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
    if (!$mail->Send()) {
        info("[send-mail] Error while sending Email");
        var_dump($mail);
    } else {
        info("[send-mail] Email sent successfully");
    }
    info("[send-mail] invio concluso");
    $mail->smtpClose();
}

function sendMailwithAttachment($to, $toName, $subject, $Content,$AttachmentFilePath)
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
    $mail->Password = $__settings->local->smtpPassword;
    $mail->SMTPSecure = "ssl";
    $mail->SMTPAutoTLS = false;
    $mail->Port = '465';
    $mail->addAttachment($AttachmentFilePath);
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
    if (!$mail->Send()) {
        info("[send-mail] Error while sending Email");
        var_dump($mail);
    } else {
        info("[send-mail] Email sent successfully");
    }
    info("[send-mail] invio concluso");
    $mail->smtpClose();
}
?>