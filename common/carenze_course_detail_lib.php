<?php

/**
 * Helpers per mostrare a studenti e genitori il dettaglio del corso di recupero
 * collegato ad una carenza.
 */

function carenzeCourseHtml($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function carenzeCourseFormatDateTime($dt)
{
    if (empty($dt)) {
        return '';
    }
    try {
        return (new DateTime($dt))->format('d-m-Y H:i');
    } catch (Exception $e) {
        return '';
    }
}

function carenzeCourseFormatDateOnly($dt)
{
    if (empty($dt)) {
        return '';
    }
    try {
        return (new DateTime($dt))->format('d-m-Y');
    } catch (Exception $e) {
        return '';
    }
}

function carenzeCourseFormatTimeOnly($dt)
{
    if (empty($dt)) {
        return '';
    }
    try {
        return (new DateTime($dt))->format('H:i');
    } catch (Exception $e) {
        return '';
    }
}

function carenzeCourseFormatFirstDate($idCorso)
{
    $idCorso = intval($idCorso);
    if ($idCorso <= 0) {
        return '';
    }

    $row = dbGetFirst("
        SELECT data_inizio, data_fine, aula
        FROM corso_date
        WHERE id_corso = {$idCorso}
        ORDER BY data_inizio ASC
        LIMIT 1
    ");
    if (!$row) {
        return '';
    }

    $data = carenzeCourseFormatDateOnly($row['data_inizio'] ?? '');
    $oraInizio = carenzeCourseFormatTimeOnly($row['data_inizio'] ?? '');
    $oraFine = carenzeCourseFormatTimeOnly($row['data_fine'] ?? '');
    $aula = trim((string)($row['aula'] ?? ''));
    $out = 'Prima lezione';
    if ($data !== '') {
        $out .= ': ' . $data;
    }
    if ($oraInizio !== '') {
        $out .= ' ore ' . $oraInizio;
        if ($oraFine !== '') {
            $out .= '-' . $oraFine;
        }
    }
    if ($aula !== '') {
        $out .= ' - Aula ' . $aula;
    }
    return $out;
}

function carenzeCourseGetInfo($idCorso)
{
    $idCorso = intval($idCorso);
    if ($idCorso <= 0) {
        return null;
    }

    return dbGetFirst("
        SELECT
            co.id,
            co.titolo,
            m.nome AS materia,
            CONCAT(dmain.cognome, ' ', dmain.nome) AS docente_main,
            GROUP_CONCAT(
                CONCAT(d.cognome, ' ', d.nome)
                ORDER BY cdn.principale DESC, d.cognome ASC, d.nome ASC
                SEPARATOR ', '
            ) AS docenti
        FROM corso co
        LEFT JOIN materia m ON m.id = co.id_materia
        LEFT JOIN docente dmain ON dmain.id = co.id_docente
        LEFT JOIN corso_docenti cdn ON cdn.id_corso = co.id
        LEFT JOIN docente d ON d.id = cdn.id_docente
        WHERE co.id = {$idCorso}
        GROUP BY co.id, co.titolo, m.nome, dmain.cognome, dmain.nome
        LIMIT 1
    ");
}

function carenzeCourseDocentiLabel($info)
{
    if (!is_array($info)) {
        return '';
    }

    $docenti = trim((string)($info['docenti'] ?? ''));
    if ($docenti === '') {
        $docenti = trim((string)($info['docente_main'] ?? ''));
    }
    return $docenti;
}

function carenzeCoursePublicTitle($info)
{
    if (!is_array($info)) {
        return 'Dettaglio corso';
    }

    $materia = trim((string)($info['materia'] ?? ''));
    $titolo = trim((string)($info['titolo'] ?? ''));

    if ($materia !== '' && stripos($titolo, 'recupero carenze') !== false) {
        return 'Recupero carenze - ' . $materia;
    }

    if ($titolo !== '') {
        return $titolo;
    }

    return $materia !== '' ? $materia : 'Dettaglio corso';
}

function carenzeCourseBuildDetailHtml($idCorso, $idStudente)
{
    $idCorso = intval($idCorso);
    $idStudente = intval($idStudente);

    $info = carenzeCourseGetInfo($idCorso);
    if (!$info) {
        return [
            'success' => false,
            'error' => 'Corso non trovato'
        ];
    }

    $dates = dbGetAll("
        SELECT
            cd.id,
            cd.data_inizio,
            cd.data_fine,
            cd.aula,
            COALESCE(cd.firmato, 0) AS firmato,
            ca.argomento,
            cp.id_studente AS presente_id
        FROM corso_date cd
        LEFT JOIN corso_argomenti ca ON ca.id_data_corso = cd.id
        LEFT JOIN corso_presenti cp
               ON cp.id_data_corso = cd.id
              AND cp.id_studente = {$idStudente}
        WHERE cd.id_corso = {$idCorso}
        ORDER BY cd.data_inizio ASC
    ") ?: [];

    $title = carenzeCoursePublicTitle($info);

    $html = '<div class="carenze-course-detail text-left">';
    $html .= '<div class="carenze-course-meta">';
    if (!empty($info['materia'])) {
        $html .= '<div><span class="carenze-course-meta-label">Materia</span><span class="carenze-course-meta-value">' . carenzeCourseHtml($info['materia']) . '</span></div>';
    }
    $docenti = carenzeCourseDocentiLabel($info);
    if ($docenti !== '') {
        $html .= '<div><span class="carenze-course-meta-label">Docente</span><span class="carenze-course-meta-value">' . carenzeCourseHtml($docenti) . '</span></div>';
    }
    $html .= '</div>';

    if (!$dates) {
        $html .= '<div class="alert alert-warning" style="margin:0;">Nessuna data inserita per il corso.</div>';
        $html .= '</div>';
        return [
            'success' => true,
            'title' => $title,
            'html' => $html
        ];
    }

    $html .= '<div class="table-responsive carenze-course-schedule"><table class="table carenze-course-detail-table">';
    $html .= '<thead><tr>';
    $html .= '<th>Data e ora</th><th>Aula</th><th>Presenza</th><th>Argomenti</th>';
    $html .= '</tr></thead><tbody>';

    $now = new DateTime();
    foreach ($dates as $date) {
        $inizioRaw = (string)($date['data_inizio'] ?? '');
        $fineRaw = (string)($date['data_fine'] ?? '');
        $inizio = carenzeCourseFormatDateTime($inizioRaw);
        $fine = carenzeCourseFormatDateTime($fineRaw);
        $quando = $inizio;
        if ($fine !== '') {
            $quando .= ' - ' . substr($fine, 11);
        }

        $isPast = false;
        try {
            $endCompare = $fineRaw !== '' ? new DateTime($fineRaw) : new DateTime($inizioRaw);
            $isPast = $endCompare < $now;
        } catch (Exception $e) {
            $isPast = false;
        }

        $firmato = intval($date['firmato'] ?? 0) === 1;
        if (!$isPast) {
            $presenza = '<span class="label label-info carenze-status">Da svolgere</span>';
        } elseif (!$firmato) {
            $presenza = '<span class="label label-default carenze-status">Registro non firmato</span>';
        } elseif (!empty($date['presente_id'])) {
            $presenza = '<span class="label label-success carenze-status">Presente</span>';
        } else {
            $presenza = '<span class="label label-danger carenze-status">Assente</span>';
        }

        $argomento = trim((string)($date['argomento'] ?? ''));
        if ($argomento === '') {
            $argomento = $isPast ? 'Non inseriti' : '-';
        }

        $html .= '<tr>';
        $dataSolo = carenzeCourseFormatDateOnly($inizioRaw);
        $oraInizio = carenzeCourseFormatTimeOnly($inizioRaw);
        $oraFine = carenzeCourseFormatTimeOnly($fineRaw);
        $orario = $oraInizio;
        if ($oraFine !== '') {
            $orario .= ' - ' . $oraFine;
        }

        $html .= '<td class="course-date-cell"><div class="date-main">' . carenzeCourseHtml($dataSolo !== '' ? $dataSolo : $quando) . '</div>';
        if ($orario !== '') {
            $html .= '<div class="date-time">' . carenzeCourseHtml($orario) . '</div>';
        }
        $html .= '</td>';
        $html .= '<td class="course-room-cell"><span class="course-room-pill">' . carenzeCourseHtml($date['aula'] ?? '') . '</span></td>';
        $html .= '<td class="text-center course-presence-cell">' . $presenza . '</td>';
        $html .= '<td class="course-topic-cell">' . nl2br(carenzeCourseHtml($argomento)) . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table></div></div>';

    return [
        'success' => true,
        'title' => $title,
        'html' => $html
    ];
}

function carenzeCourseStudentIsEnrolled($idCorso, $idStudente)
{
    $idCorso = intval($idCorso);
    $idStudente = intval($idStudente);
    if ($idCorso <= 0 || $idStudente <= 0) {
        return false;
    }

    $count = dbGetValue("
        SELECT COUNT(*)
        FROM corso_iscritti
        WHERE id_corso = {$idCorso}
          AND id_studente = {$idStudente}
    ");

    return intval($count) > 0;
}
