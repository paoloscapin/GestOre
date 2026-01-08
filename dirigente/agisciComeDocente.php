<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

if (isset($_POST)) {
    require_once '../common/checkSession.php';

    // ⚠️ prima era dirigente: se la pagina è admin e vuoi che funzioni da admin, cambia qui
    ruoloRichiesto('admin', 'dirigente');

    // get values
    $docente_id = intval($_POST['docente_id'] ?? 0);
    debug('docente_id=' . $docente_id);

    require_once '../common/connect.php';
    $query = "SELECT * FROM docente WHERE docente.id = '$docente_id'";

    if (!$result = mysqli_query($con, $query)) {
        error('query fallita' . $query);
        // se ajax, torna json
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'msg' => 'Query fallita']);
            exit;
        }
        exit(mysqli_error($con));
    }

    $response = array();
    if (mysqli_num_rows($result) > 0) {
        // prende solo la prima ed unica riga
        $row = mysqli_fetch_assoc($result);

        // ✅ come prima
        $session->set('docente_id', $row['id']);
        $session->set('docente_nome', $row['nome']);
        $session->set('docente_cognome', $row['cognome']);

        // ✅ AGGIUNTA: flag impersonificazione (nomi “generici”)
        // Se nel tuo progetto i nomi sono diversi, li adeguiamo dopo con impersonaRuolo()
        $session->set('impersona_attiva', 1);
        $session->set('impersona_ruolo', 'docente');
        $session->set('impersona_docente_id', intval($row['id']));

        $response['ok'] = true;
    } else {
        warning("docente id $docente_id not found!");
        $response['ok'] = false;
        $response['msg'] = "docente id $docente_id not found!";
    }

    $__docente_id = $session->get('docente_id');
    $__docente_nome = $session->get('docente_nome');
    $__docente_cognome = $session->get('docente_cognome');
    debug('redirect con docente_id=' . $docente_id);

    // ✅ Se è AJAX: rispondi JSON e NON redirectare
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response);
        exit;
    }

    // altrimenti comportamento legacy
    redirect('/docente/index.php');
}
