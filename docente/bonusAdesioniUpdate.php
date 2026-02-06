<?php
/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('segreteria-docenti', 'dirigente', 'docente');

header('Content-Type: application/json; charset=utf-8');

function jsonOut(bool $ok, array $extra = []): void {
    echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

// anno selezionato dal client (fallback: corrente)
$anno = isset($_POST['anno_scolastico_id'])
    ? (int)$_POST['anno_scolastico_id']
    : (int)$__anno_scolastico_corrente_id;

// 🔒 Regola: il docente può modificare SOLO anno corrente e solo se adesioni aperte
if (!$__config->getBonus_adesione_aperto()) {
    http_response_code(403);
    jsonOut(false, ['error' => 'Adesioni chiuse']);
}
if ($anno !== (int)$__anno_scolastico_corrente_id) {
    http_response_code(403);
    jsonOut(false, ['error' => 'Anno non corrente']);
}

// Parametri minimi
if (!isset($_POST['bonus_id'])) {
    http_response_code(400);
    jsonOut(false, ['error' => 'bonus_id mancante']);
}

$bonus_id = (int)$_POST['bonus_id'];
if ($bonus_id <= 0) {
    http_response_code(400);
    jsonOut(false, ['error' => 'bonus_id non valido']);
}

$adesione_id = isset($_POST['adesione_id']) ? (int)$_POST['adesione_id'] : -1; // legacy
$checked = isset($_POST['checked']) ? (int)$_POST['checked'] : null;          // nuova modalità (0/1)

// ===============================
// Helper: trova id adesione esistente
// ===============================
function getExistingId(int $docente_id, int $anno, int $bonus_id): int {
    $id = dbGetValue("
        SELECT id
        FROM bonus_docente
        WHERE docente_id = $docente_id
          AND anno_scolastico_id = $anno
          AND bonus_id = $bonus_id
        LIMIT 1
    ");
    return (int)$id;
}

try {

    // ===============================
    // MODALITÀ NUOVA: decide con checked
    // ===============================
    if ($checked === 1) {

        // se esiste già, ritorna l'id esistente
        $existingId = getExistingId((int)$__docente_id, $anno, $bonus_id);
        if ($existingId > 0) {
            jsonOut(true, ['action' => 'exists', 'id' => $existingId]);
        }

        // INSERT
        dbExec("
            INSERT INTO bonus_docente (approvato, docente_id, anno_scolastico_id, bonus_id)
            VALUES (NULL, $__docente_id, $anno, $bonus_id)
        ");
        $newId = (int)dblastId();
        if ($newId <= 0) {
            jsonOut(false, ['error' => 'Inserimento non riuscito']);
        }
        jsonOut(true, ['action' => 'insert', 'id' => $newId]);
    }

    if ($checked === 0) {

        // DELETE sicuro: docente+anno+bonus (non mi fido dell'id passato dal client)
        dbExec("
            DELETE FROM bonus_docente
            WHERE docente_id = $__docente_id
              AND anno_scolastico_id = $anno
              AND bonus_id = $bonus_id
            LIMIT 1
        ");
        jsonOut(true, ['action' => 'delete']);
    }

    // ===============================
    // MODALITÀ LEGACY: decide con adesione_id
    // (compatibilità col JS attuale se non invia checked)
    // ===============================
    if ($adesione_id < 0) {

        $existingId = getExistingId((int)$__docente_id, $anno, $bonus_id);
        if ($existingId > 0) {
            jsonOut(true, ['action' => 'exists', 'id' => $existingId]);
        }

        dbExec("
            INSERT INTO bonus_docente (approvato, docente_id, anno_scolastico_id, bonus_id)
            VALUES (NULL, $__docente_id, $anno, $bonus_id)
        ");
        $newId = (int)dblastId();
        if ($newId <= 0) {
            jsonOut(false, ['error' => 'Inserimento non riuscito']);
        }

        jsonOut(true, ['action' => 'insert', 'id' => $newId]);
    }

    // Legacy DELETE: anche qui faccio sicuro (docente+anno+bonus)
    dbExec("
        DELETE FROM bonus_docente
        WHERE docente_id = $__docente_id
          AND anno_scolastico_id = $anno
          AND bonus_id = $bonus_id
        LIMIT 1
    ");
    jsonOut(true, ['action' => 'delete']);

} catch (Throwable $e) {
    warning("bonusAdesioniUpdate.php ERROR: " . $e->getMessage());
    jsonOut(false, ['error' => $e->getMessage()]);
}
