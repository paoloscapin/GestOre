<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/mastercom/admin_lib.php';
require_once '../common/student_photo.php';

if (isset($_POST['id']) && $_POST['id'] != "") {
    $studente_id = $_POST['id'];

    // Recupero studente
    $query = "SELECT * FROM studente WHERE id = '$studente_id'";
    $studente = dbGetFirst($query);

    if (!$studente) {
        echo json_encode(['error' => 'Studente non trovato']);
        exit;
    }

    // Recupero genitori (JOIN genitori_studenti -> genitori)
    $query = "
        SELECT
            g.id,
            g.cognome,
            g.nome
        FROM genitori_studenti gs
        JOIN genitori g ON g.id = gs.id_genitore
        WHERE gs.id_studente = '$studente_id'
          AND g.attivo = 1
        ORDER BY g.cognome, g.nome
    ";
    $genitori = dbGetAll($query);

    // aggiungo i genitori alla struttura JSON
    $studente['genitori'] = $genitori ?: [];

    $studente['gestore_foto_url'] = gestoreStudentPhotoUrl(intval($studente_id));
    $studente['mastercom_foto'] = '';
    if (mastercomAdminTableExists('mastercom_studenti')
        && mastercomAdminTableColumnExists('mastercom_studenti', 'foto')
        && mastercomAdminTableColumnExists('mastercom_studenti', 'id_studente_gestore')) {
        $photoWhere = ["id_studente_gestore = " . intval($studente_id)];
        if (mastercomAdminTableColumnExists('mastercom_studenti', 'codice_fiscale') && trim((string)($studente['codice_fiscale'] ?? '')) !== '') {
            $photoWhere[] = "LOWER(codice_fiscale) = LOWER(" . dbQ(trim((string)$studente['codice_fiscale'])) . ")";
        }
        if (mastercomAdminTableColumnExists('mastercom_studenti', 'mastercom_id_studente') && ctype_digit(trim((string)($studente['username'] ?? '')))) {
            $photoWhere[] = "mastercom_id_studente = " . dbI(intval($studente['username']));
        }
        $studente['mastercom_foto'] = trim((string)(dbGetValue("
            SELECT foto
            FROM mastercom_studenti
            WHERE (" . implode(' OR ', $photoWhere) . ")
              AND foto IS NOT NULL
              AND foto <> ''
            ORDER BY last_seen_at DESC, last_sync_at DESC
            LIMIT 1
        ") ?? ''));
    }

    // Recupero frequenze
    $query = "SELECT * FROM studente_frequenta WHERE id_studente = '$studente_id' ORDER BY id_anno_scolastico DESC";
    $frequenze_raw = dbGetAll($query);

    $frequenze = [];
    $first = true; // Flag per il primo ciclo

    if (!empty($frequenze_raw)) {
        foreach ($frequenze_raw as $frequenza) {
            // Recupera nome classe
            $query = "SELECT classe FROM classi WHERE id = " . intval($frequenza['id_classe']);
            $classe = dbGetValue($query);

            // Recupera anno scolastico
            $query = "SELECT anno FROM anno_scolastico WHERE id = " . intval($frequenza['id_anno_scolastico']);
            $anno = dbGetValue($query);

            // Recupera nome classe
            $id_classe = intval($frequenza['id_classe']);
            $id_anno_scolastico = intval($frequenza['id_anno_scolastico']);

            if ($first) {
                // Se l'anno scolastico è quello corrente, aggiungo anche il nome dell'anno
                $studente['id_anno_scolastico'] = $id_anno_scolastico;
                $studente['id_classe'] = $id_classe;
                $first = false; // Dopo il primo ciclo, non entra più
            }

            // Aggiungi i dati
            $frequenza['classe'] = $classe;
            $frequenza['anno'] = $anno;

            $frequenze[] = $frequenza; // <-- salva nel nuovo array
        }
    }

    // Aggiungi array frequenze allo studente
    $studente['frequenze'] = $frequenze;

    // Output
    echo json_encode($studente);
}
