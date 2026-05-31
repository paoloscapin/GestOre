<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

$azione = trim((string)($_POST['azione'] ?? ''));

if ($azione === 'crea_gruppo' || $azione === 'salva_gruppo') {
    $idGruppo = ob_int($_POST['id_gruppo'] ?? 0);
    $nome = dbQNotNull($_POST['nome'] ?? '');
    $descrizione = dbQ($_POST['descrizione'] ?? '');
    $ordine = ob_int($_POST['ordine'] ?? 100, 100);

    if ($azione === 'crea_gruppo') {
        dbExec("
            INSERT INTO orario_aula_gruppo (nome, descrizione, ordine, attivo)
            VALUES ($nome, $descrizione, $ordine, 1)
            ON DUPLICATE KEY UPDATE
                descrizione = VALUES(descrizione),
                ordine = VALUES(ordine),
                attivo = 1
        ");
    } else {
        dbExec("
            UPDATE orario_aula_gruppo
            SET nome = $nome,
                descrizione = $descrizione,
                ordine = $ordine,
                updated_at = NOW()
            WHERE id = $idGruppo
        ");
    }

    ob_redirect('aula_gruppi.php');
}

if ($azione === 'elimina_gruppo') {
    $idGruppo = ob_int($_POST['id_gruppo'] ?? 0);

    dbExec("
        UPDATE orario_aula_gruppo
        SET attivo = 0,
            updated_at = NOW()
        WHERE id = $idGruppo
    ");

    ob_redirect('aula_gruppi.php');
}

if ($azione === 'aggiungi_aula') {
    $idGruppo = ob_int($_POST['id_gruppo'] ?? 0);
    $idAula = ob_int($_POST['id_aula'] ?? 0);

    dbExec("
        INSERT INTO orario_aula_gruppo_aula (id_gruppo, id_aula)
        VALUES ($idGruppo, $idAula)
        ON DUPLICATE KEY UPDATE id_aula = VALUES(id_aula)
    ");

    ob_redirect('aula_gruppi.php');
}

if ($azione === 'rimuovi_aula') {
    $idRiga = ob_int($_POST['id_riga'] ?? 0);

    dbExec("
        DELETE FROM orario_aula_gruppo_aula
        WHERE id = $idRiga
    ");

    ob_redirect('aula_gruppi.php');
}

die('Azione non valida');