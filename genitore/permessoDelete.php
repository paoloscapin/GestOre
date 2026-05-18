<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

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
if ($id <= 0) {
    permessiFailUnauthorized();
}

$isGenitore = impersonaRuolo('genitore');

$permesso = getPermessoAutorizzato($id, (int)$__genitore_id, $isGenitore);
if (!$permesso) {
    permessiFailUnauthorized();
}

if ($isGenitore) {
    // Il genitore può cancellare solo richieste ancora in stato "Richiesto"
    if ((int)$permesso['stato'] !== 1) {
        permessiFailUnauthorized();
    }
}

$mailPermesso = permessiUscitaLoad($id);
if ($mailPermesso) {
    permessiUscitaSendParentMailFromRow($mailPermesso, 'cancellazione');
}

$qDelete = "
    DELETE FROM permessi_uscita
    WHERE id = " . dbI($id) . "
    LIMIT 1
";

dbExec($qDelete);

info("cancellato permesso id=$id");

header('Content-Type: application/json; charset=utf-8');
echo json_encode(
    array(
        'ok' => true,
        'id' => $id
    ),
    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
);
exit;
