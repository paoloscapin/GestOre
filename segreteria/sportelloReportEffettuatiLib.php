<?php

function sportelloReportEffettuatiFilters()
{
    $periodo = trim((string)($_GET['periodo_filtro'] ?? ''));
    if (!in_array($periodo, ['tutti', 'passati', 'futuri'], true)) {
        $periodo = isset($_GET['passati']) ? (intval($_GET['passati']) ? 'passati' : 'futuri') : 'tutti';
    }

    return [
        'passati' => intval($_GET['passati'] ?? 1),
        'periodo_filtro' => $periodo,
        'docente_filtro_id' => intval($_GET['docente_filtro_id'] ?? 0),
        'materia_filtro_id' => intval($_GET['materia_filtro_id'] ?? 0),
        'iscritti_filtro' => trim((string)($_GET['iscritti_filtro'] ?? 'tutti')),
        'firmato_filtro' => trim((string)($_GET['firmato_filtro'] ?? 'tutti')),
    ];
}

function sportelloReportEffettuatiTitle(array $filters)
{
    $materiaNome = $filters['materia_filtro_id'] > 0
        ? dbGetValue("SELECT nome FROM materia WHERE id=" . dbI($filters['materia_filtro_id']) . " LIMIT 1")
        : 'Tutte le materie';

    if (($filters['periodo_filtro'] ?? 'tutti') === 'passati') {
        $suffix = ' - Sportelli effettuati fino al: ';
    } elseif (($filters['periodo_filtro'] ?? 'tutti') === 'futuri') {
        $suffix = ' - Sportelli ancora da effettuare dopo il: ';
    } else {
        $suffix = ' - Tutti gli sportelli al: ';
    }

    return $materiaNome . $suffix . date('d/m/Y');
}

function sportelloReportEffettuatiRows(array $filters)
{
    global $__anno_scolastico_corrente_id;

    $query = "
        SELECT
            sportello.id AS sportello_id,
            sportello.data AS sportello_data,
            sportello.ora AS sportello_ora,
            sportello.numero_ore AS sportello_numero_ore,
            sportello.luogo AS sportello_luogo,
            sportello.classe AS sportello_classe,
            sportello.firmato AS sportello_firmato,
            sportello.cancellato AS sportello_cancellato,
            materia.nome AS materia_nome,
            docente.cognome AS docente_cognome,
            docente.nome AS docente_nome,
            docente.email AS docente_email,
            (SELECT COUNT(id) FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.presente) AS numero_presenti,
            (SELECT COUNT(id) FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id AND sportello_studente.iscritto) AS numero_iscritti
        FROM sportello sportello
        INNER JOIN docente docente ON sportello.docente_id = docente.id
        INNER JOIN materia materia ON sportello.materia_id = materia.id
        WHERE sportello.anno_scolastico_id = " . dbI($__anno_scolastico_corrente_id) . "
          AND NOT sportello.cancellato
    ";

    if ($filters['docente_filtro_id'] > 0) {
        $query .= " AND sportello.docente_id = " . dbI($filters['docente_filtro_id']);
    }
    if ($filters['materia_filtro_id'] > 0) {
        $query .= " AND sportello.materia_id = " . dbI($filters['materia_filtro_id']);
    }
    if (($filters['periodo_filtro'] ?? 'tutti') === 'passati') {
        $query .= " AND sportello.data <= CURDATE()";
    } elseif (($filters['periodo_filtro'] ?? 'tutti') === 'futuri') {
        $query .= " AND sportello.data > CURDATE()";
    }
    if ($filters['firmato_filtro'] === 'firmati') {
        $query .= " AND sportello.firmato = 1";
    } elseif ($filters['firmato_filtro'] === 'non_firmati') {
        $query .= " AND COALESCE(sportello.firmato, 0) = 0";
    }

    $query .= " ORDER BY sportello.data ASC, docente_cognome ASC, docente_nome ASC";

    $rows = dbGetAll($query) ?: [];
    if ($filters['iscritti_filtro'] === 'con_iscritti') {
        $rows = array_values(array_filter($rows, function ($row) {
            return intval($row['numero_iscritti'] ?? 0) > 0;
        }));
    } elseif ($filters['iscritti_filtro'] === 'senza_iscritti') {
        $rows = array_values(array_filter($rows, function ($row) {
            return intval($row['numero_iscritti'] ?? 0) <= 0;
        }));
    }

    return $rows;
}

function sportelloReportEffettuatiStudenti($sportello_id)
{
    global $__anno_scolastico_corrente_id;

    return dbGetAll("
        SELECT
            sportello_studente.iscritto AS sportello_studente_iscritto,
            sportello_studente.presente AS sportello_studente_presente,
            sportello_studente.note AS sportello_studente_note,
            studente.cognome AS studente_cognome,
            studente.nome AS studente_nome,
            c.classe AS studente_classe
        FROM sportello_studente
        INNER JOIN studente ON sportello_studente.studente_id = studente.id
        INNER JOIN studente_frequenta sf
            ON sf.id_studente = studente.id
           AND sf.id_anno_scolastico = " . dbI($__anno_scolastico_corrente_id) . "
        INNER JOIN classi c ON sf.id_classe = c.id
        WHERE sportello_studente.sportello_id = " . dbI($sportello_id) . "
        ORDER BY c.classe ASC, studente.cognome ASC, studente.nome ASC
    ") ?: [];
}

function sportelloReportEffettuatiRowState(array $row)
{
    if (!empty($row['sportello_cancellato'])) return 'cancellato';
    if (!empty($row['sportello_firmato'])) return 'firmato';
    return 'non firmato';
}

function sportelloReportEffettuatiDateIt($value)
{
    if (!$value) return '';
    try {
        return (new DateTime((string)$value))->format('d/m/Y');
    } catch (Exception $e) {
        return (string)$value;
    }
}

function sportelloReportEffettuatiTotals(array $rows)
{
    $totals = [
        'sportelli_fatti' => 0,
        'ore_fatte' => 0,
        'sportelli_saltati' => 0,
        'ore_saltate' => 0,
    ];
    foreach ($rows as $row) {
        if (intval($row['numero_presenti'] ?? 0) > 0) {
            $totals['sportelli_fatti']++;
            $totals['ore_fatte'] += floatval($row['sportello_numero_ore'] ?? 0);
        } else {
            $totals['sportelli_saltati']++;
            $totals['ore_saltate'] += floatval($row['sportello_numero_ore'] ?? 0);
        }
    }
    return $totals;
}
