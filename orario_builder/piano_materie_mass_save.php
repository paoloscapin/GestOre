<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

$idPiano = ob_int($_POST['id_piano'] ?? 0);

if ($idPiano <= 0) {
    die('Piano non valido');
}

$ids = $_POST['id_piano_materia'] ?? [];
$oreTeoriaArr = $_POST['ore_teoria'] ?? [];
$oreLabArr = $_POST['ore_laboratorio'] ?? [];
$seqTeoriaArr = $_POST['sequenza_teoria'] ?? [];
$seqLabArr = $_POST['sequenza_laboratorio'] ?? [];

foreach ($ids as $i => $idRaw) {
    $idPianoMateria = intval($idRaw);
    if ($idPianoMateria <= 0) continue;

    $oreTeoria = ob_float($oreTeoriaArr[$i] ?? 0);
    $oreLab = ob_float($oreLabArr[$i] ?? 0);

    if ($oreLab > $oreTeoria) {
        die('Le ore laboratorio non possono superare le ore totali.');
    }

    $seqTeoria = trim((string)($seqTeoriaArr[$i] ?? ''));
    $seqLab = trim((string)($seqLabArr[$i] ?? ''));

    dbExec("
        UPDATE orario_piano_orario_materia
        SET
            ore_teoria = $oreTeoria,
            ore_laboratorio = $oreLab
        WHERE id = $idPianoMateria
          AND id_piano_orario = $idPiano
    ");

    dbExec("
        DELETE FROM orario_piano_orario_materia_blocco
        WHERE id_piano_orario_materia = $idPianoMateria
    ");

    if ($seqTeoria !== '') {
        dbExec("
            INSERT INTO orario_piano_orario_materia_blocco
            (id_piano_orario_materia, tipo_ora, sequenza, preferita, peso)
            VALUES
            ($idPianoMateria, 'TEORIA', " . dbQNotNull($seqTeoria) . ", 1, 100)
        ");
    }

    if ($seqLab !== '') {
        dbExec("
            INSERT INTO orario_piano_orario_materia_blocco
            (id_piano_orario_materia, tipo_ora, sequenza, preferita, peso)
            VALUES
            ($idPianoMateria, 'LABORATORIO', " . dbQNotNull($seqLab) . ", 1, 100)
        ");
    }
}

ob_redirect("piano_materie.php?id_piano=$idPiano");