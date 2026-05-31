<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

$idOrigine = ob_int($_POST['id_piano_origine'] ?? 0);
$idDestinazione = ob_int($_POST['id_piano_destinazione'] ?? 0);

if ($idOrigine <= 0 || $idDestinazione <= 0 || $idOrigine === $idDestinazione) {
    die('Dati non validi');
}

$righeOrigine = dbGetAll("
    SELECT *
    FROM orario_piano_orario_materia
    WHERE id_piano_orario = $idOrigine
") ?: [];

foreach ($righeOrigine as $r) {
    $idMateria = intval($r['id_materia']);
    $oreTeoria = floatval($r['ore_teoria']);
    $oreLaboratorio = floatval($r['ore_laboratorio']);
    $noteSql = dbQ($r['note'] ?? '');

    dbExec("
        INSERT INTO orario_piano_orario_materia (
            id_piano_orario,
            id_materia,
            ore_teoria,
            ore_laboratorio,
            note
        ) VALUES (
            $idDestinazione,
            $idMateria,
            $oreTeoria,
            $oreLaboratorio,
            $noteSql
        )
        ON DUPLICATE KEY UPDATE
            ore_teoria = VALUES(ore_teoria),
            ore_laboratorio = VALUES(ore_laboratorio),
            note = VALUES(note)
    ");

    $idOriginePianoMateria = intval($r['id']);

    $idDestPianoMateria = intval(dbGetValue("
        SELECT id
        FROM orario_piano_orario_materia
        WHERE id_piano_orario = $idDestinazione
          AND id_materia = $idMateria
        LIMIT 1
    "));

    if ($idDestPianoMateria <= 0) {
        continue;
    }

    dbExec("
        DELETE FROM orario_piano_orario_materia_blocco
        WHERE id_piano_orario_materia = $idDestPianoMateria
    ");

    dbExec("
        INSERT INTO orario_piano_orario_materia_blocco (
            id_piano_orario_materia,
            tipo_ora,
            sequenza,
            preferita,
            peso,
            note
        )
        SELECT
            $idDestPianoMateria,
            tipo_ora,
            sequenza,
            preferita,
            peso,
            note
        FROM orario_piano_orario_materia_blocco
        WHERE id_piano_orario_materia = $idOriginePianoMateria
    ");

    dbExec("
        DELETE FROM orario_piano_materia_aula_richiesta
        WHERE id_piano_orario_materia = $idDestPianoMateria
    ");

    dbExec("
        INSERT INTO orario_piano_materia_aula_richiesta (
            id_piano_orario_materia,
            tipo_ora,
            progressivo,
            modalita,
            id_aula,
            id_gruppo_aula,
            obbligatoria,
            note
        )
        SELECT
            $idDestPianoMateria,
            tipo_ora,
            progressivo,
            modalita,
            id_aula,
            id_gruppo_aula,
            obbligatoria,
            note
        FROM orario_piano_materia_aula_richiesta
        WHERE id_piano_orario_materia = $idOriginePianoMateria
    ");
}

ob_redirect("piano_materie.php?id_piano=$idDestinazione");