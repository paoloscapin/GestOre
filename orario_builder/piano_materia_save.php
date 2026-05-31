<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

$idPiano = ob_int($_POST['id_piano'] ?? 0);
$idMateria = ob_int($_POST['id_materia'] ?? 0);
$idPianoMateria = ob_int($_POST['id_piano_materia'] ?? 0);
$azione = trim((string)($_POST['azione'] ?? 'salva'));

if ($idPiano <= 0) {
    die('Dati non validi: piano mancante');
}

if ($azione === 'elimina') {
    if ($idPianoMateria <= 0) {
        die('Dati non validi: materia piano mancante');
    }

    dbExec("
        DELETE FROM orario_piano_orario_materia
        WHERE id = $idPianoMateria
          AND id_piano_orario = $idPiano
    ");

    ob_redirect("piano_materie.php?id_piano=$idPiano");
}

if ($idMateria <= 0) {
    die('Dati non validi: materia mancante');
}

$oreTeoria = ob_float($_POST['ore_teoria'] ?? 0);
$oreLaboratorio = ob_float($_POST['ore_laboratorio'] ?? 0);
if ($oreLaboratorio > $oreTeoria) {
    die('Le ore di laboratorio non possono essere superiori alle ore totali della materia.');
}
$sequenzaTeoria = trim((string)($_POST['sequenza_teoria'] ?? ''));
$sequenzaLaboratorio = trim((string)($_POST['sequenza_laboratorio'] ?? ''));

dbExec("
    INSERT INTO orario_piano_orario_materia (
        id_piano_orario,
        id_materia,
        ore_teoria,
        ore_laboratorio
    ) VALUES (
        $idPiano,
        $idMateria,
        $oreTeoria,
        $oreLaboratorio
    )
    ON DUPLICATE KEY UPDATE
        ore_teoria = VALUES(ore_teoria),
        ore_laboratorio = VALUES(ore_laboratorio)
");

$idPianoMateria = dbGetValue("
    SELECT id
    FROM orario_piano_orario_materia
    WHERE id_piano_orario = $idPiano
      AND id_materia = $idMateria
    LIMIT 1
");

$idPianoMateria = intval($idPianoMateria);

if ($idPianoMateria <= 0) {
    die('Errore: impossibile recuperare id piano/materia');
}

salvaSequenzaBlocco($idPianoMateria, 'TEORIA', $sequenzaTeoria);
salvaSequenzaBlocco($idPianoMateria, 'LABORATORIO', $sequenzaLaboratorio);

ob_redirect("piano_materie.php?id_piano=$idPiano");


function salvaSequenzaBlocco($idPianoMateria, $tipoOra, $sequenza) {
    $idPianoMateria = intval($idPianoMateria);
    $tipoOraSql = dbQNotNull($tipoOra);
    $sequenza = trim((string)$sequenza);

    if ($sequenza === '') {
        dbExec("
            DELETE FROM orario_piano_orario_materia_blocco
            WHERE id_piano_orario_materia = $idPianoMateria
              AND tipo_ora = $tipoOraSql
        ");
        return;
    }

    $sequenzaSql = dbQNotNull($sequenza);

    dbExec("
        INSERT INTO orario_piano_orario_materia_blocco (
            id_piano_orario_materia,
            tipo_ora,
            sequenza,
            preferita,
            peso
        ) VALUES (
            $idPianoMateria,
            $tipoOraSql,
            $sequenzaSql,
            1,
            100
        )
        ON DUPLICATE KEY UPDATE
            sequenza = VALUES(sequenza),
            preferita = 1,
            peso = 100
    ");
}