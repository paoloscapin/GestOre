<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('segreteria-didattica', 'dirigente');

$id_genitore  = isset($_POST['id_genitore']) ? (int)$_POST['id_genitore'] : 0;
$id_studente  = isset($_POST['id_studente']) ? (int)$_POST['id_studente'] : 0;
$id_relazione = isset($_POST['id_relazione']) ? (int)$_POST['id_relazione'] : 0;

if ($id_genitore <= 0 || $id_studente <= 0 || $id_relazione <= 0) {
    echo json_encode(['error' => 'Dati non validi']);
    exit;
}

// evita duplicati: se già collegato aggiorna relazione
$query = "
    INSERT INTO genitori_studenti (id_genitore, id_studente, id_relazione)
    VALUES ($id_genitore, $id_studente, $id_relazione)
    ON DUPLICATE KEY UPDATE id_relazione = VALUES(id_relazione)
";
dbExec($query);

echo json_encode(['ok' => true]);
