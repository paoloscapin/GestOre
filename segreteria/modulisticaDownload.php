<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('dirigente','segreteria-docenti', 'modulistica');

if (isset($_GET['documento'])) {
    $documento = $_GET['documento'];

    // la root directory in cui si trova l'applicazione
    $applicationRoot = $_SERVER['DOCUMENT_ROOT'] . '/' . APPLICATION_NAME;

    // la directory di base in cui vengono caricati i files sotto le loro directory
    $uploadBaseDirectory = $applicationRoot . '/uploads' . '/';

    $filePath = $uploadBaseDirectory . $documento;

    header("Content-type: application/octet-stream");
    header('Content-Disposition: attachment; filename="' . basename($documento) . '"');
    header("Content-Length: " . filesize($filePath));

    readfile($filePath);
}
?>