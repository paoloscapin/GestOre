<?php
/**
 * Questo file iscrive uno studente al secondo tentativo d'esame.
 * Se non esiste ancora una data d'esame per il secondo tentativo,
 * la crea automaticamente in `corso_esami_date`.
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['id_corso']) || !isset($_POST['id_studente'])) {
    echo json_encode(['success' => false, 'error' => 'Parametri mancanti']);
    exit;
}

$id_corso    = intval($_POST['id_corso']);
$id_studente = intval($_POST['id_studente']);

try {
    // 🔹 1. Controlla se lo studente è già iscritto al secondo tentativo
    $queryCheck = "
        SELECT COUNT(*) AS cnt
        FROM corso_esiti
        WHERE id_corso = $id_corso
          AND id_studente = $id_studente
          AND id_esame_data IN (
              SELECT id FROM corso_esami_date WHERE id_corso = $id_corso AND tentativo = 2
          )
    ";
    $exists = dbGetValue($queryCheck);

    if ($exists > 0) {
        echo json_encode(['success' => false, 'error' => 'Studente già iscritto al secondo tentativo']);
        exit;
    }

    // 🔹 2. Controlla se esiste già una data per il secondo tentativo
    $queryEsame2 = "
        SELECT id
        FROM corso_esami_date
        WHERE id_corso = $id_corso AND tentativo = 2
        LIMIT 1
    ";
    $esame2_id = dbGetValue($queryEsame2);

    // 🔹 3. Se non esiste, la crea
    if (!$esame2_id) {
        $queryInsertEsame2 = "
            INSERT INTO corso_esami_date (id_corso, tentativo, firmato)
            VALUES ($id_corso, 2, 0)
        ";
        $ok = mysqli_query($__con, $queryInsertEsame2);
        if (!$ok) {
            throw new Exception("Errore creazione secondo tentativo: " . mysqli_error($__con));
        }
        $esame2_id = mysqli_insert_id($__con);
    }

    // 🔹 4. Inserisce l'iscrizione dello studente nel secondo tentativo
    $queryInsertEsito = "
        INSERT INTO corso_esiti (id_corso, id_studente, id_esame_data, presente, recuperato)
        VALUES ($id_corso, $id_studente, $esame2_id, 0, 0)
    ";
    $ok = mysqli_query($__con, $queryInsertEsito);
    if (!$ok) {
        throw new Exception("Errore inserimento corso_esiti: " . mysqli_error($__con));
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
