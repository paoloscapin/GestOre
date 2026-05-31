<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

$azione = trim((string)($_POST['azione'] ?? ''));
$idScenario = ob_int($_POST['id_scenario'] ?? 0);

if ($azione === 'crea_gruppo') {
    $nome = dbQNotNull($_POST['nome'] ?? '');
    $descrizione = dbQ($_POST['descrizione'] ?? '');

    if ($idScenario <= 0) {
        die('Scenario non valido');
    }

    $scenario = dbGetFirst("
        SELECT *
        FROM orario_scenario
        WHERE id = $idScenario
        LIMIT 1
    ");

    if (!$scenario) {
        die('Scenario non trovato');
    }

    $idAnno = intval($scenario['id_anno_scolastico']);

    dbExec("
        INSERT INTO orario_alternanza_gruppo (
            id_scenario,
            id_anno_scolastico,
            nome,
            descrizione,
            attivo
        ) VALUES (
            $idScenario,
            $idAnno,
            $nome,
            $descrizione,
            1
        )
    ");

    ob_redirect("alternanze.php?id_scenario=$idScenario");
}

if ($azione === 'salva_gruppo') {
    $idGruppo = ob_int($_POST['id_gruppo'] ?? 0);
    $nome = dbQNotNull($_POST['nome'] ?? '');
    $descrizione = dbQ($_POST['descrizione'] ?? '');

    if ($idGruppo <= 0) {
        die('Gruppo non valido');
    }

    dbExec("
        UPDATE orario_alternanza_gruppo
        SET
            nome = $nome,
            descrizione = $descrizione,
            updated_at = NOW()
        WHERE id = $idGruppo
    ");

    ob_redirect("alternanze.php?id_scenario=$idScenario");
}

if ($azione === 'elimina_gruppo') {
    $idGruppo = ob_int($_POST['id_gruppo'] ?? 0);

    if ($idGruppo <= 0) {
        die('Gruppo non valido');
    }

    dbExec("
        DELETE FROM orario_alternanza_gruppo
        WHERE id = $idGruppo
    ");

    ob_redirect("alternanze.php?id_scenario=$idScenario");
}

if ($azione === 'salva_riga') {
    $idGruppo = ob_int($_POST['id_gruppo'] ?? 0);
    $idRiga = ob_int($_POST['id_riga'] ?? 0);
    $idClasse = ob_int($_POST['id_classe'] ?? 0);
    $idMateriaP1 = ob_int($_POST['id_materia_periodo_1'] ?? 0);
    $idMateriaP2 = ob_int($_POST['id_materia_periodo_2'] ?? 0);

    if ($idGruppo <= 0 || $idClasse <= 0 || $idMateriaP1 <= 0 || $idMateriaP2 <= 0) {
        die('Dati riga non validi');
    }

    if ($idMateriaP1 === $idMateriaP2) {
        die('Le due materie devono essere diverse');
    }

    if ($idRiga > 0) {
        dbExec("
            UPDATE orario_alternanza_riga
            SET
                id_classe = $idClasse,
                id_materia_periodo_1 = $idMateriaP1,
                id_materia_periodo_2 = $idMateriaP2
            WHERE id = $idRiga
              AND id_gruppo = $idGruppo
        ");
    } else {
        dbExec("
            INSERT INTO orario_alternanza_riga (
                id_gruppo,
                id_classe,
                id_materia_periodo_1,
                id_materia_periodo_2
            ) VALUES (
                $idGruppo,
                $idClasse,
                $idMateriaP1,
                $idMateriaP2
            )
        ");
    }

    ob_redirect("alternanze.php?id_scenario=$idScenario");
}

if ($azione === 'elimina_riga') {
    $idGruppo = ob_int($_POST['id_gruppo'] ?? 0);
    $idRiga = ob_int($_POST['id_riga'] ?? 0);

    if ($idGruppo <= 0 || $idRiga <= 0) {
        die('Dati non validi');
    }

    dbExec("
        DELETE FROM orario_alternanza_riga
        WHERE id = $idRiga
          AND id_gruppo = $idGruppo
    ");

    ob_redirect("alternanze.php?id_scenario=$idScenario");
}

die('Azione non valida');