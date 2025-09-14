<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/Log.php';
require_once '../common/checkSession.php';
ruoloRichiesto('segreteria-didattica', 'dirigente');

$username = $__settings->MasterComAuth->clientId;
$password = $__settings->MasterComAuth->clientSecret;

if (empty($username) || empty($password)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "auth" => false,
        "message" => "Username o password mancanti"
    ]);
    exit;
}

// Costruisco l'URL esterno
$baseUrl = "https://buonarroti-tn.registroelettronico.com/mastercom/register_manager.php";
$url = $baseUrl . "?form_user=" . urlencode($username) . "&form_password=" . urlencode($password);

// Eseguo la richiesta GET
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$response = curl_exec($ch);

if ($response === false) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "auth" => false,
        "message" => "Errore nella connessione: " . curl_error($ch)
    ]);
    curl_close($ch);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Decodifica la risposta JSON remota
$data = json_decode($response, true);

// Se la risposta non è JSON valido
if ($data === null) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "auth" => false,
        "message" => "Risposta remota non valida",
        "raw" => $response
    ]);
    exit;
}

$__mastercom_token = $data['result']['current_key'] ?? '';
$__mastercom_user = $data['result']['current_user'] ?? '';

// Se il server remoto conferma auth:true
if ($httpCode === 200 && isset($data['auth']) && $data['auth'] === true) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "auth" => true,
        "utente" => $data['result']['full_name'] ?? '',
        "tipo_utente" => $data['result']['tipo_utente'] ?? '',
    ]);
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "auth" => false,
        "message" => "Autenticazione fallita",
        "error" => $data['error_code'] ?? 'UNKNOWN'
    ]);
}
