<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// include Database connection file
require_once '../common/checkSession.php';
require_once '../common/connect.php';

$programma_modulo_id = $_POST['id'];


// cancello se esistono i moduli (da cancellare)
$query = "DELETE from programmi_svolti_moduli WHERE ID_PROGRAMMA=$programma_modulo_id";
dbExec($query);

?>