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
    $docente = dbGetFirst("SELECT * FROM docente WHERE docente.id = '$docente_id' ;");
    $session->set('docente_id', $docente['id']);
    $session->set('docente_nome', $docente['nome']);
    $session->set('docente_cognome', $docente['cognome']);
    $session->set('docente_email', $docente['email']);

    $__docente_id = $session->get('docente_id');
    $__docente_nome = $session->get('docente_nome');
    $__docente_cognome = $session->get('docente_cognome');
    $__docente_email = $session->get('docente_email');
    debug('redirect con docente_id=' . $docente_id);
    redirect('/docente/index.php');
}
