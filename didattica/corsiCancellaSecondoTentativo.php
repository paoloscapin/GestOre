<?php
/**
 *  Questo file rimuove l'iscrizione di uno studente dal secondo tentativo d'esame.
 *  Cancella la riga corrispondente nella tabella corso_esiti collegata al tentativo 2.
 * 
 *  @author     Massimo Saiani
 *  @copyright  (C) 2025
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

header('Content-Type: application/json; charset=utf-8');

// 🔹 Controllo parametri
if (!isset($_POST['id_corso']) || !isset($_POST['id_studente'])) {
    echo json_encode(['success' => false, 'error' => 'Parametri mancanti']);
    exit;
}

$id_corso    = intval($_POST['id_corso']);
$id_studente = intval($_POST['id_studente']);

try {
    // --- 🔹 Log diagnostico (facoltativo, se hai una funzione logToFile) ---
    if (function_exists('logToFile')) {
        logToFile("corsiCancellaSecondoTentativo.php", "Tentativo di cancellare secondo tentativo per studente $id_studente, corso $id_corso", 'debug');
    }

    // --- 🔹 Recupero l'id_esame_data del secondo tentativo ---
    $queryEsame = "
        SELECT id 
        FROM corso_esami_date 
        WHERE id_corso = ? 
          AND tentativo = 2
        LIMIT 1
    ";
    $id_esame_data = dbGetValue($queryEsame, [$id_corso]);

    if (!$id_esame_data) {
        echo json_encode(['success' => false, 'error' => 'Nessun secondo tentativo trovato per questo corso']);
        exit;
    }

    // --- 🔹 Cancellazione riga corso_esiti associata ---
    $queryDelete = "
        DELETE FROM corso_esiti 
        WHERE id_corso = ? 
          AND id_studente = ? 
          AND id_esame_data = ?
    ";

    dbExec($queryDelete, [$id_corso, $id_studente, $id_esame_data]);

    // --- 🔹 Verifica se la cancellazione è avvenuta ---
    $controllo = dbGetValue("
        SELECT COUNT(*) 
        FROM corso_esiti 
        WHERE id_corso = ? 
          AND id_studente = ? 
          AND id_esame_data = ?
    ", [$id_corso, $id_studente, $id_esame_data]);

    if ($controllo == 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Cancellazione non riuscita']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
