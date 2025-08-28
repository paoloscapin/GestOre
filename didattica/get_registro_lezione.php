<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

$corso_id = intval($_POST['corso_id']);

$date = dbGetAll("
    SELECT id, DATE_FORMAT(data, '%d-%m-%Y %H:%i') as data_format, aula 
    FROM corso_date 
    WHERE id_corso = $corso_id
    ORDER BY data ASC
");

echo json_encode([
    "success" => true,
    "date" => $date
]);
