<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

$azione = trim((string)($_POST['azione'] ?? ''));
$idScenario = ob_int($_POST['id_scenario'] ?? 0);

if ($idScenario <= 0) {
    die('Scenario non valido');
}

function backArt($idScenario) {
    ob_redirect("classi_articolate.php?id_scenario=" . intval($idScenario));
}

if ($azione === 'crea_gruppo') {
    $nome = dbQNotNull($_POST['nome'] ?? '');
    $descrizione = dbQ($_POST['descrizione'] ?? '');

    dbExec("
        INSERT INTO orario_classe_articolata_gruppo (
            id_scenario, nome, descrizione, attivo
        ) VALUES (
            $idScenario, $nome, $descrizione, 1
        )
    ");

    backArt($idScenario);
}

if ($azione === 'salva_gruppo') {
    $idGruppo = ob_int($_POST['id_gruppo'] ?? 0);
    $nome = dbQNotNull($_POST['nome'] ?? '');
    $descrizione = dbQ($_POST['descrizione'] ?? '');

    dbExec("
        UPDATE orario_classe_articolata_gruppo
        SET nome = $nome,
            descrizione = $descrizione,
            updated_at = NOW()
        WHERE id = $idGruppo
          AND id_scenario = $idScenario
    ");

    backArt($idScenario);
}

if ($azione === 'elimina_gruppo') {
    $idGruppo = ob_int($_POST['id_gruppo'] ?? 0);

    dbExec("
        UPDATE orario_classe_articolata_gruppo
        SET attivo = 0,
            updated_at = NOW()
        WHERE id = $idGruppo
          AND id_scenario = $idScenario
    ");

    backArt($idScenario);
}

if ($azione === 'aggiungi_classe') {
    $idGruppo = ob_int($_POST['id_gruppo'] ?? 0);
    $idClasse = ob_int($_POST['id_classe'] ?? 0);

    dbExec("
        INSERT INTO orario_classe_articolata_classe (id_gruppo, id_classe)
        VALUES ($idGruppo, $idClasse)
        ON DUPLICATE KEY UPDATE id_classe = VALUES(id_classe)
    ");

    backArt($idScenario);
}

if ($azione === 'rimuovi_classe') {
    $idRiga = ob_int($_POST['id_riga'] ?? 0);

    dbExec("
        DELETE FROM orario_classe_articolata_classe
        WHERE id = $idRiga
    ");

    backArt($idScenario);
}

if ($azione === 'aggiungi_materia_comune') {
    $idGruppo = ob_int($_POST['id_gruppo'] ?? 0);
    $idMateria = ob_int($_POST['id_materia'] ?? 0);

    dbExec("
        INSERT INTO orario_classe_articolata_materia (
            id_gruppo, id_materia, tipo
        ) VALUES (
            $idGruppo, $idMateria, 'COMUNE'
        )
        ON DUPLICATE KEY UPDATE tipo = 'COMUNE'
    ");

    backArt($idScenario);
}

if ($azione === 'rimuovi_materia_comune') {
    $idRiga = ob_int($_POST['id_riga'] ?? 0);

    dbExec("
        DELETE FROM orario_classe_articolata_materia
        WHERE id = $idRiga
    ");

    backArt($idScenario);
}

if ($azione === 'crea_gruppo_materie') {
    $idGruppo = ob_int($_POST['id_gruppo'] ?? 0);
    $idClasse = ob_int($_POST['id_classe'] ?? 0);
    $nome = dbQNotNull($_POST['nome'] ?? '');

    dbExec("
        INSERT INTO orario_classe_articolata_gruppo_materie (
            id_gruppo_articolato,
            id_classe,
            nome,
            attivo
        ) VALUES (
            $idGruppo,
            $idClasse,
            $nome,
            1
        )
    ");

    backArt($idScenario);
}

if ($azione === 'elimina_gruppo_materie') {
    $idGruppoMaterie = ob_int($_POST['id_gruppo_materie'] ?? 0);

    dbExec("
        UPDATE orario_classe_articolata_gruppo_materie
        SET attivo = 0,
            updated_at = NOW()
        WHERE id = $idGruppoMaterie
    ");

    backArt($idScenario);
}

if ($azione === 'aggiungi_materia_gruppo') {
    $idGruppoMaterie = ob_int($_POST['id_gruppo_materie'] ?? 0);
    $idMateria = ob_int($_POST['id_materia'] ?? 0);

    dbExec("
        INSERT INTO orario_classe_articolata_gruppo_materie_riga (
            id_gruppo_materie,
            id_materia
        ) VALUES (
            $idGruppoMaterie,
            $idMateria
        )
        ON DUPLICATE KEY UPDATE id_materia = VALUES(id_materia)
    ");

    backArt($idScenario);
}

if ($azione === 'rimuovi_materia_gruppo') {
    $idRiga = ob_int($_POST['id_riga'] ?? 0);

    dbExec("
        DELETE FROM orario_classe_articolata_gruppo_materie_riga
        WHERE id = $idRiga
    ");

    backArt($idScenario);
}

if ($azione === 'crea_sincronizzazione') {
    $idGruppo = ob_int($_POST['id_gruppo'] ?? 0);
    $nome = dbQNotNull($_POST['nome'] ?? '');
    $idA = ob_int($_POST['id_gruppo_materie_a'] ?? 0);
    $idB = ob_int($_POST['id_gruppo_materie_b'] ?? 0);
    $ore = dbF($_POST['ore_settimanali'] ?? null);

    if ($idA <= 0 || $idB <= 0 || $idA === $idB) {
        die('Gruppi materie non validi');
    }

    dbExec("
        INSERT INTO orario_classe_articolata_sincronizzazione (
            id_gruppo_articolato,
            nome,
            id_gruppo_materie_a,
            id_gruppo_materie_b,
            ore_settimanali,
            attivo
        ) VALUES (
            $idGruppo,
            $nome,
            $idA,
            $idB,
            $ore,
            1
        )
    ");

    backArt($idScenario);
}

if ($azione === 'elimina_sincronizzazione') {
    $idSync = ob_int($_POST['id_sync'] ?? 0);

    dbExec("
        UPDATE orario_classe_articolata_sincronizzazione
        SET attivo = 0,
            updated_at = NOW()
        WHERE id = $idSync
    ");

    backArt($idScenario);
}

die('Azione non valida');