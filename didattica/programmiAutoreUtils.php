<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function programmiUtenteAutoreDaDocente(int $docenteId, int $fallbackUtenteId): int
{
    if ($docenteId <= 0) {
        return $fallbackUtenteId;
    }

    $docenteId = intval($docenteId);

    $utente = dbGetFirst("
        SELECT utente.id
        FROM docente
        INNER JOIN utente
        ON utente.username = docente.username
        WHERE docente.id = $docenteId
        LIMIT 1
    ");

    if ($utente != null && intval($utente['id']) > 0) {
        return intval($utente['id']);
    }

    $utente = dbGetFirst("
        SELECT utente.id
        FROM docente
        INNER JOIN utente
        ON utente.cognome = docente.cognome
            AND utente.nome = docente.nome
        WHERE docente.id = $docenteId
        LIMIT 1
    ");

    if ($utente != null && intval($utente['id']) > 0) {
        return intval($utente['id']);
    }

    return $fallbackUtenteId;
}

function programmiUtenteAutoreDaProgrammaSvolto(int $programmaId, int $fallbackUtenteId): int
{
    if ($programmaId <= 0) {
        return $fallbackUtenteId;
    }

    $programma = dbGetFirst("
        SELECT id_docente
        FROM programmi_svolti
        WHERE id = " . intval($programmaId) . "
        LIMIT 1
    ");

    if ($programma == null) {
        return $fallbackUtenteId;
    }

    return programmiUtenteAutoreDaDocente(intval($programma['id_docente']), $fallbackUtenteId);
}

?>
