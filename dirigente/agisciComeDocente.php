<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

if (isset($_POST)) {
    require_once '../common/checkSession.php';
    ruoloRichiesto('dirigente');

    // get values
    $docente_id = $_POST['docente_id'];
    debug('docente_id=' . $docente_id);
    require_once '../common/connect.php';
    $query = "SELECT * FROM docente WHERE docente.id = '$docente_id'";
    if (!$result = mysqli_query($con, $query)) {
        error('query fallita' . $query);
        exit(mysqli_error($con));
    }
    $response = array();
    if (mysqli_num_rows($result) > 0) {
        // prende solo la prima ed unica riga
        $row = mysqli_fetch_assoc($result);

        $session->set('docente_id', $row['id']);
        $session->set('docente_nome', $row['nome']);
        $session->set('docente_cognome', $row['cognome']);
    } else {
        warning("docente id $docente_id not found!");
        $response['status'] = 200;
        $response['message'] = "docente id $docente_id not found!";
    }

    $__docente_id = $session->get('docente_id');
    $__docente_nome = $session->get('docente_nome');
    $__docente_cognome = $session->get('docente_cognome');
    debug('redirect con docente_id=' . $docente_id);
    redirect('/docente/index.php');
}
