<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/permessi_uscita_lib.php';

ruoloRichiesto('segreteria-didattica', 'dirigente', 'genitore');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect("/error/unauthorized.php");
    exit;
}

function permessiFailUnauthorized()
{
    redirect("/error/unauthorized.php");
    exit;
}

function canGenitoreAccessStudente($idStudente, $idGenitore)
{
    $idStudente = (int)$idStudente;
    $idGenitore = (int)$idGenitore;

    if ($idStudente <= 0 || $idGenitore <= 0) {
        return false;
    }

    $q = "
        SELECT id_studente
        FROM genitori_studenti
        WHERE id_genitore = " . dbI($idGenitore) . "
          AND id_studente = " . dbI($idStudente) . "
        LIMIT 1
    ";

    $row = dbGetFirst($q);
    return is_array($row) && !empty($row['id_studente']);
}

function getPermessoAutorizzato($idPermesso, $idGenitore, $isGenitore)
{
    $idPermesso = (int)$idPermesso;
    $idGenitore = (int)$idGenitore;

    if ($idPermesso <= 0) {
        return null;
    }

    $whereExtra = "";
    if ($isGenitore) {
        $whereExtra = "
            AND EXISTS (
                SELECT 1
                FROM genitori_studenti gs
                WHERE gs.id_genitore = " . dbI($idGenitore) . "
                  AND gs.id_studente = permessi_uscita.id_studente
            )
        ";
    }

    $q = "
        SELECT *
        FROM permessi_uscita
        WHERE id = " . dbI($idPermesso) . "
        $whereExtra
        LIMIT 1
    ";

    return dbGetFirst($q);
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$data = escapePost('data');
$ora_uscita = escapePost('ora_uscita');
$motivo = escapePost('motivo');
$ora_rientro = trim((string)escapePost('ora_rientro'));
$rientro = isset($_POST['rientro']) ? (int)$_POST['rientro'] : 0;

if ($rientro === 1) {
    if ($ora_rientro === '') {
        permessiFailUnauthorized();
    }
} else {
    $ora_rientro = '00:00:00';
}
$id_studente = isset($_POST['id_studente']) ? (int)$_POST['id_studente'] : 0;

if ($id_studente <= 0 || $data === '' || $ora_uscita === '' || $motivo === '') {
    permessiFailUnauthorized();
}

$isGenitore = impersonaRuolo('genitore');

if ($isGenitore && !canGenitoreAccessStudente($id_studente, (int)$__genitore_id)) {
    permessiFailUnauthorized();
}

if ($id > 0) {
    $permesso = getPermessoAutorizzato($id, (int)$__genitore_id, $isGenitore);
    if (!$permesso) {
        permessiFailUnauthorized();
    }

    if ($isGenitore) {
        if ((int)$permesso['stato'] !== 1) {
            permessiFailUnauthorized();
        }
        if ((int)$permesso['id_studente'] !== $id_studente) {
            permessiFailUnauthorized();
        }
    }

    $query = "
        UPDATE permessi_uscita
        SET data = " . dbQ($data) . ",
            ora_uscita = " . dbQ($ora_uscita) . ",
            ora_rientro = " . dbQ($ora_rientro) . ",
            motivo = " . dbQ($motivo) . ",
            rientro = " . dbI($rientro) . "
        WHERE id = " . dbI($id);

    dbExec($query);
    info("aggiornato permesso id=$id");
} else {
    $query = "
        INSERT INTO permessi_uscita (
            id_genitore,
            id_studente,
            data,
            ora_uscita,
            ora_rientro,
            rientro,
            motivo,
            stato
        ) VALUES (
            " . dbI((int)$__genitore_id) . ",
            " . dbI($id_studente) . ",
            " . dbQ($data) . ",
            " . dbQ($ora_uscita) . ",
            " . dbQ($ora_rientro) . ",
            " . dbI($rientro) . ",
            " . dbQ($motivo) . ",
            1
        )
    ";

    dbExec($query);
    $id = dbLastId();
    info("inserito nuovo permesso id=$id");
    permessiUscitaSendParentMail((int)$id, 'creazione');
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'id' => $id
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
