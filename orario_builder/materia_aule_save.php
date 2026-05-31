<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

$azione = trim((string)($_POST['azione'] ?? ''));
$idPianoMateria = ob_int($_POST['id_piano_materia'] ?? 0);

if ($idPianoMateria <= 0) {
    die('Materia piano non valida');
}

if ($azione === 'elimina') {
    $idRichiesta = ob_int($_POST['id_richiesta'] ?? 0);

    dbExec("
        DELETE FROM orario_piano_materia_aula_richiesta
        WHERE id = $idRichiesta
          AND id_piano_orario_materia = $idPianoMateria
    ");

    ob_redirect("materia_aule.php?id_piano_materia=$idPianoMateria");
}

if ($azione === 'salva') {
    $idRichiesta = ob_int($_POST['id_richiesta'] ?? 0);
    $tipoOra = dbQNotNull($_POST['tipo_ora'] ?? 'TEORIA');
    $progressivo = ob_int($_POST['progressivo'] ?? 1, 1);
    $modalita = trim((string)($_POST['modalita'] ?? 'NESSUNA'));
    $obbligatoria = ob_int($_POST['obbligatoria'] ?? 1, 1);

    $idAula = ob_int($_POST['id_aula'] ?? 0);
    $idGruppo = ob_int($_POST['id_gruppo_aula'] ?? 0);

    if ($modalita === 'AULA_FISSA') {
        $idAulaSql = $idAula > 0 ? (string)$idAula : "NULL";
        $idGruppoSql = "NULL";
    } elseif ($modalita === 'GRUPPO_AULE') {
        $idAulaSql = "NULL";
        $idGruppoSql = $idGruppo > 0 ? (string)$idGruppo : "NULL";
    } else {
        $modalita = 'NESSUNA';
        $idAulaSql = "NULL";
        $idGruppoSql = "NULL";
    }

    $modalitaSql = dbQNotNull($modalita);

    if ($idRichiesta > 0) {
        dbExec("
            UPDATE orario_piano_materia_aula_richiesta
            SET
                tipo_ora = $tipoOra,
                progressivo = $progressivo,
                modalita = $modalitaSql,
                id_aula = $idAulaSql,
                id_gruppo_aula = $idGruppoSql,
                obbligatoria = $obbligatoria,
                updated_at = NOW()
            WHERE id = $idRichiesta
              AND id_piano_orario_materia = $idPianoMateria
        ");
    } else {
        dbExec("
            INSERT INTO orario_piano_materia_aula_richiesta (
                id_piano_orario_materia,
                tipo_ora,
                progressivo,
                modalita,
                id_aula,
                id_gruppo_aula,
                obbligatoria
            ) VALUES (
                $idPianoMateria,
                $tipoOra,
                $progressivo,
                $modalitaSql,
                $idAulaSql,
                $idGruppoSql,
                $obbligatoria
            )
        ");
    }

    ob_redirect("materia_aule.php?id_piano_materia=$idPianoMateria");
}

die('Azione non valida');