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
    $mail->CharSet = "UTF-8";
    $mail->Encoding = "base64";
    //Configure an SMTP
    $mail->isSMTP();
    $mail->Mailer = "smtp";
    $mail->SMTPDebug = 0;
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $__settings->GoogleAuth->GoogleAppMail;
    $mail->Password = $__settings->GoogleAuth->GoogleAppPassword;
    $mail->SMTPSecure = "tls";
    $mail->Port = 587;
    $mail->IsHTML(true);
    $mail->AddAddress($to, $toName);
    $mail->SetFrom($__settings->local->emailNoReplyFrom, "GestOre " . $__settings->local->nomeIstituto, true);
    $mail->AddReplyTo($__settings->local->emailNoReplyFrom, "GestOre " . $__settings->local->nomeIstituto);

    $mail->addBCC($__settings->local->emailSportelli, "Gestione attività GestOre");
    $mail->Subject = $subject;
    $content = $Content;

    // Attempt to send the email
    $mail->Body = $content;
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