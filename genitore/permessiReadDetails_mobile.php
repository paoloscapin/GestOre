<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

/**
 *  Versione MOBILE di permessiReadDetails.php
 *  Restituisce JSON con dettagli permesso
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    permessiFailUnauthorized();
}

ruoloRichiesto('genitore', 'segreteria-didattica', 'dirigente');

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
        SELECT
            permessi_uscita.id as permesso_id,
            permessi_uscita.id_genitore as permesso_id_genitore,
            permessi_uscita.id_studente as permesso_id_studente,
            permessi_uscita.data as permesso_data,
            permessi_uscita.ora_uscita as permesso_ora_uscita,
            permessi_uscita.ora_rientro as permesso_ora_rientro,
            permessi_uscita.rientro as permesso_rientro,
            permessi_uscita.motivo as permesso_motivo,
            permessi_uscita.stato as permesso_stato,
            genitori.id as genitore_id,
            genitori.nome AS genitore_nome,
            genitori.cognome AS genitore_cognome,
            studente.id AS studente_id,
            studente.nome AS studente_nome,
            studente.cognome AS studente_cognome
        FROM permessi_uscita
        INNER JOIN genitori
            ON permessi_uscita.id_genitore = genitori.id
        INNER JOIN studente
            ON permessi_uscita.id_studente = studente.id
        WHERE permessi_uscita.id = " . dbI($idPermesso) . "
        $whereExtra
        LIMIT 1
    ";

    return dbGetFirst($q);
}

$permesso_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($permesso_id <= 0) {
    permessiFailUnauthorized();
}

$isGenitore = impersonaRuolo('genitore');
$permesso = getPermessoAutorizzato($permesso_id, (int)$__genitore_id, $isGenitore);

if (!$permesso) {
    permessiFailUnauthorized();
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($permesso, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);