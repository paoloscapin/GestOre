<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

header('Content-Type: application/json; charset=utf-8');

$idScenario = ob_int($_POST['id_scenario'] ?? 0);
$idSlot = ob_int($_POST['id_slot'] ?? 0);
$stato = trim((string)($_POST['stato'] ?? ''));
$classi = $_POST['classi'] ?? [];

if ($idScenario <= 0 || $idSlot <= 0 || empty($classi)) {
    echo json_encode([
        'ok' => false,
        'error' => 'Dati non validi'
    ]);
    exit;
}

$statiValidi = ['DISPONIBILE', 'NON_DISPONIBILE', 'OBBLIGATORIO', 'PREFERITO', 'CANCELLA'];

if (!in_array($stato, $statiValidi, true)) {
    echo json_encode([
        'ok' => false,
        'error' => 'Stato non valido'
    ]);
    exit;
}

foreach ($classi as $idClasse) {
    $idClasse = intval($idClasse);

    if ($idClasse <= 0) {
        continue;
    }

    if ($stato === 'CANCELLA') {
        dbExec("
            DELETE FROM orario_classe_slot_vincolo
            WHERE id_scenario = $idScenario
              AND id_classe = $idClasse
              AND id_slot = $idSlot
        ");
    } else {
        $statoSql = dbQNotNull($stato);

        dbExec("
            INSERT INTO orario_classe_slot_vincolo (
                id_scenario,
                id_classe,
                id_slot,
                stato
            ) VALUES (
                $idScenario,
                $idClasse,
                $idSlot,
                $statoSql
            )
            ON DUPLICATE KEY UPDATE
                stato = VALUES(stato),
                updated_at = NOW()
        ");
    }
}

echo json_encode(['ok' => true]);