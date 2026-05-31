<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$azione = trim((string)($_POST['azione'] ?? ''));
$idScenario = ob_int($_POST['id_scenario'] ?? 0);

if ($idScenario <= 0) {
    die('Scenario non valido');
}

function backDocentiMaterie($idScenario)
{
    ob_redirect("docenti_materie.php?id_scenario=" . intval($idScenario));
}

if ($azione === 'salva') {
    $idClasse = ob_int($_POST['id_classe'] ?? 0);
    $idMateria = ob_int($_POST['id_materia'] ?? 0);
    $idDocente = ob_int($_POST['id_docente'] ?? 0);
    $temporaneo = trim((string)($_POST['docente_temporaneo'] ?? ''));

    if ($idClasse <= 0 || $idMateria <= 0) {
        die('Classe o materia non valida');
    }

    if ($idDocente > 0) {
        $docenteKey = 'DOCENTE_' . $idDocente;
        $idDocenteSql = (string)$idDocente;
        $temporaneoSql = "NULL";
        $daNominare = 0;
    } else {
        if ($temporaneo === '') {
            die('Indicare un docente reale oppure un docente temporaneo');
        }

        $docenteKey = 'TEMP_' . strtoupper(preg_replace('/\s+/', '', $temporaneo));
        $idDocenteSql = "NULL";
        $temporaneoSql = dbQNotNull($temporaneo);
        $daNominare = 1;
    }

    $docenteKeySql = dbQNotNull($docenteKey);

    dbExec("
        INSERT INTO orario_docente_insegna_scenario (
            id_scenario,
            id_docente,
            docente_temporaneo,
            docente_da_nominare,
            docente_key,
            id_classe,
            id_materia,
            origine
        ) VALUES (
            $idScenario,
            $idDocenteSql,
            $temporaneoSql,
            $daNominare,
            $docenteKeySql,
            $idClasse,
            $idMateria,
            'MANUALE'
        )
        ON DUPLICATE KEY UPDATE
            id_docente = VALUES(id_docente),
            docente_temporaneo = VALUES(docente_temporaneo),
            docente_da_nominare = VALUES(docente_da_nominare),
            docente_key = VALUES(docente_key),
            origine = VALUES(origine),
            updated_at = NOW()
    ");

    backDocentiMaterie($idScenario);
}

if ($azione === 'elimina') {
    $id = ob_int($_POST['id'] ?? 0);

    dbExec("
        DELETE FROM orario_docente_insegna_scenario
        WHERE id = $id
          AND id_scenario = $idScenario
    ");

    backDocentiMaterie($idScenario);
}

if ($azione === 'sostituisci_temporaneo') {
    $vecchiaDocenteKey = trim((string)($_POST['docente_key'] ?? ''));
    $idDocente = ob_int($_POST['id_docente'] ?? 0);

    if ($vecchiaDocenteKey === '' || $idDocente <= 0) {
        die('Dati sostituzione non validi');
    }

    $vecchiaDocenteKeySql = dbQNotNull($vecchiaDocenteKey);
    $nuovaDocenteKeySql = dbQNotNull('DOCENTE_' . $idDocente);

    $tmpKey = $vecchiaDocenteKey;
    if (strpos($tmpKey, 'TEMP_') === 0) {
        $tmpKey = substr($tmpKey, 5);
    }
    $tmpKeySql = dbQNotNull($tmpKey);

    dbExec("
        INSERT INTO orario_import_docente_temporaneo_alias (
            docente_temporaneo,
            id_docente,
            note
        ) VALUES (
            $tmpKeySql,
            $idDocente,
            'Creato da sostituzione docente temporaneo'
        )
        ON DUPLICATE KEY UPDATE
            id_docente = VALUES(id_docente),
            note = VALUES(note),
            updated_at = NOW()
    ");

    dbExec("
        DELETE FROM orario_docente_insegna_scenario
        WHERE id_scenario = $idScenario
          AND docente_key = $nuovaDocenteKeySql
          AND EXISTS (
              SELECT 1
              FROM (
                  SELECT id_classe, id_materia
                  FROM orario_docente_insegna_scenario
                  WHERE id_scenario = $idScenario
                    AND docente_key = $vecchiaDocenteKeySql
              ) tmp
              WHERE tmp.id_classe = orario_docente_insegna_scenario.id_classe
                AND tmp.id_materia = orario_docente_insegna_scenario.id_materia
          )
    ");

    dbExec("
        UPDATE orario_docente_insegna_scenario
        SET
            id_docente = $idDocente,
            docente_temporaneo = NULL,
            docente_da_nominare = 0,
            docente_key = $nuovaDocenteKeySql,
            updated_at = NOW()
        WHERE id_scenario = $idScenario
          AND docente_key = $vecchiaDocenteKeySql
    ");

    backDocentiMaterie($idScenario);
}

if ($azione === 'salva_alias') {
    $alias = strtoupper(trim((string)($_POST['alias_classe'] ?? '')));
    $note = dbQ($_POST['note'] ?? '');

    $idClasse = ob_int($_POST['id_classe'] ?? 0);
    $idClasseSql = $idClasse > 0 ? (string)$idClasse : "NULL";

    if ($alias === '') {
        die('Alias non valido');
    }

    $aliasSql = dbQNotNull($alias);

    dbExec("
        INSERT INTO orario_import_classe_alias (
            alias_classe,
            id_classe,
            note
        ) VALUES (
            $aliasSql,
            $idClasseSql,
            $note
        )
        ON DUPLICATE KEY UPDATE
            id_classe = VALUES(id_classe),
            note = VALUES(note)
    ");

    backDocentiMaterie($idScenario);
}

if ($azione === 'sostituisci_temporanei_massivo') {
    $docenteKeys = $_POST['docente_key'] ?? [];
    $idDocenti = $_POST['id_docente'] ?? [];

    foreach ($docenteKeys as $i => $vecchiaKeyRaw) {
        $vecchiaDocenteKey = trim((string)$vecchiaKeyRaw);
        $idDocente = ob_int($idDocenti[$i] ?? 0);

        if ($vecchiaDocenteKey === '' || $idDocente <= 0) {
            continue;
        }

        $vecchiaDocenteKeySql = dbQNotNull($vecchiaDocenteKey);
        $nuovaDocenteKeySql = dbQNotNull('DOCENTE_' . $idDocente);

        $tmpKey = $vecchiaDocenteKey;
        if (strpos($tmpKey, 'TEMP_') === 0) {
            $tmpKey = substr($tmpKey, 5);
        }
        $tmpKeySql = dbQNotNull($tmpKey);

        dbExec("
            INSERT INTO orario_import_docente_temporaneo_alias (
                docente_temporaneo,
                id_docente,
                note
            ) VALUES (
                $tmpKeySql,
                $idDocente,
                'Creato da sostituzione massiva docenti temporanei'
            )
            ON DUPLICATE KEY UPDATE
                id_docente = VALUES(id_docente),
                note = VALUES(note),
                updated_at = NOW()
        ");

        dbExec("
            DELETE FROM orario_docente_insegna_scenario
            WHERE id_scenario = $idScenario
              AND docente_key = $nuovaDocenteKeySql
              AND EXISTS (
                  SELECT 1
                  FROM (
                      SELECT id_classe, id_materia
                      FROM orario_docente_insegna_scenario
                      WHERE id_scenario = $idScenario
                        AND docente_key = $vecchiaDocenteKeySql
                  ) tmp
                  WHERE tmp.id_classe = orario_docente_insegna_scenario.id_classe
                    AND tmp.id_materia = orario_docente_insegna_scenario.id_materia
              )
        ");

        dbExec("
            UPDATE orario_docente_insegna_scenario
            SET
                id_docente = $idDocente,
                docente_temporaneo = NULL,
                docente_da_nominare = 0,
                docente_key = $nuovaDocenteKeySql,
                updated_at = NOW()
            WHERE id_scenario = $idScenario
              AND docente_key = $vecchiaDocenteKeySql
        ");
    }

    backDocentiMaterie($idScenario);
}

if ($azione === 'elimina_alias') {
    $idAlias = ob_int($_POST['id_alias'] ?? 0);

    dbExec("
        DELETE FROM orario_import_classe_alias
        WHERE id = $idAlias
    ");

    backDocentiMaterie($idScenario);
}

if ($azione === 'salva_alias_massivo') {
    $aliasIds = $_POST['alias_id'] ?? [];
    $aliasClassi = $_POST['alias_classe'] ?? [];
    $idClassi = $_POST['id_classe'] ?? [];
    $noteArr = $_POST['note'] ?? [];
    $elimina = $_POST['elimina_alias'] ?? [];

    $eliminaMap = [];
    foreach ($elimina as $idElimina) {
        $eliminaMap[intval($idElimina)] = true;
    }

    foreach ($aliasIds as $i => $idAliasRaw) {
        $idAlias = intval($idAliasRaw);

        if ($idAlias <= 0) {
            continue;
        }

        if (isset($eliminaMap[$idAlias])) {
            dbExec("
                DELETE FROM orario_import_classe_alias
                WHERE id = $idAlias
            ");
            continue;
        }

        $alias = strtoupper(trim((string)($aliasClassi[$i] ?? '')));
        $idClasse = ob_int($idClassi[$i] ?? 0);
        $note = dbQ($noteArr[$i] ?? '');

        if ($alias === '') {
            continue;
        }

        $aliasSql = dbQNotNull($alias);
        $idClasseSql = $idClasse > 0 ? (string)$idClasse : "NULL";

        dbExec("
            UPDATE orario_import_classe_alias
            SET
                alias_classe = $aliasSql,
                id_classe = $idClasseSql,
                note = $note
            WHERE id = $idAlias
        ");
    }

    backDocentiMaterie($idScenario);
}

die('Azione non valida');