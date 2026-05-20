<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/programmiSvoltiCopertineLib.php';

ruoloRichiesto('segreteria-didattica', 'dirigente');

if (!programmiSvoltiCopertineTableExists()) {
    echo '<div class="alert alert-warning">Tabella copertine non configurata.</div>';
    exit;
}

$rows = dbGetAll("SELECT
        c.*,
        ps.id AS programma_id,
        materia.nome AS materia_nome,
        docente.cognome AS docente_cognome,
        docente.nome AS docente_nome,
        classi.classe AS classe_nome,
        anno_scolastico.anno AS anno_scolastico_label,
        u_req.cognome AS requested_cognome,
        u_req.nome AS requested_nome,
        u_gen.cognome AS generated_cognome,
        u_gen.nome AS generated_nome" .
        (programmiSvoltiCopertinaColumnExists('printed_at') ? ",
        c.printed_at AS printed_at" : ",
        NULL AS printed_at") .
        (programmiSvoltiCopertinaColumnExists('printed_by_user_id') ? ",
        u_print.cognome AS printed_cognome,
        u_print.nome AS printed_nome" : ",
        NULL AS printed_cognome,
        NULL AS printed_nome") . ",
        (
            SELECT GROUP_CONCAT(c2.classe ORDER BY c2.classe SEPARATOR ' / ')
            FROM programmi_svolti_classi psc2
            INNER JOIN classi c2 ON c2.id = psc2.id_classe
            WHERE psc2.id_programma_svolto = ps.id
        ) AS classi_collegate_nome
    FROM programmi_svolti_copertine c
    INNER JOIN programmi_svolti ps ON ps.id = c.id_programma_svolto
    INNER JOIN materia ON materia.id = ps.id_materia
    INNER JOIN docente ON docente.id = ps.id_docente
    INNER JOIN classi ON classi.id = ps.id_classe
    LEFT JOIN anno_scolastico ON anno_scolastico.id = ps.id_anno_scolastico
    LEFT JOIN utente u_req ON u_req.id = c.requested_by_user_id
    LEFT JOIN utente u_gen ON u_gen.id = c.generated_by_user_id" .
    (programmiSvoltiCopertinaColumnExists('printed_by_user_id') ? "
    LEFT JOIN utente u_print ON u_print.id = c.printed_by_user_id" : "") . "
    ORDER BY
        FIELD(c.stato, 'RICHIESTA', 'ERRORE', 'GENERATA', 'STAMPATA', 'ANNULLATA'),
        c.requested_at ASC,
        classi.classe ASC,
        materia.nome ASC");

if (!$rows) {
    echo '<div class="alert alert-info">Nessuna copertina richiesta.</div>';
    exit;
}

function copertineBadge(string $stato): string
{
    $stato = strtoupper(trim($stato));
    if ($stato === 'RICHIESTA') {
        return '<span class="badge" style="background:#f0ad4e;color:white;">Richiesta</span>';
    }
    if ($stato === 'GENERATA') {
        return '<span class="badge" style="background:green;color:white;">Generata</span>';
    }
    if ($stato === 'STAMPATA') {
        return '<span class="badge" style="background:#2e6da4;color:white;">Stampata</span>';
    }
    if ($stato === 'ERRORE') {
        return '<span class="badge" style="background:red;color:white;">Errore</span>';
    }
    return '<span class="badge" style="background:#777;color:white;">' . htmlspecialchars($stato, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
}

echo '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">';
echo '<thead><tr>';
echo '<th>Stato</th><th>Fascicolo</th><th>Classe</th><th>Materia</th><th>Docente</th><th>Richiesta</th><th>Generazione</th><th>Stampa</th><th>File</th><th>Azioni</th><th>Errore</th>';
echo '</tr></thead><tbody>';
foreach ($rows as $row) {
    $classe = trim((string)($row['classi_collegate_nome'] ?? '')) !== '' ? (string)$row['classi_collegate_nome'] : (string)$row['classe_nome'];
    $requested = trim((string)($row['requested_cognome'] ?? '') . ' ' . (string)($row['requested_nome'] ?? ''));
    $generated = trim((string)($row['generated_cognome'] ?? '') . ' ' . (string)($row['generated_nome'] ?? ''));
    $printed = trim((string)($row['printed_cognome'] ?? '') . ' ' . (string)($row['printed_nome'] ?? ''));
    $fileLink = '-';
    if (trim((string)($row['drive_web_view_link'] ?? '')) !== '') {
        $fileLink = '<a href="' . htmlspecialchars((string)$row['drive_web_view_link'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" target="_blank">'
            . htmlspecialchars((string)($row['file_name'] ?? 'Apri PDF'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</a>';
    } elseif (trim((string)($row['file_name'] ?? '')) !== '') {
        $fileLink = htmlspecialchars((string)$row['file_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    echo '<tr>';
    echo '<td class="text-center">' . copertineBadge((string)$row['stato']) . '</td>';
    echo '<td>' . htmlspecialchars((string)($row['fascicolo_codice'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars($classe, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)$row['materia_nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(trim((string)$row['docente_cognome'] . ' ' . (string)$row['docente_nome']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(date('d/m/Y H:i', strtotime((string)$row['requested_at'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '<br><small>' . htmlspecialchars($requested, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</small></td>';
    echo '<td>' . (!empty($row['generated_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string)$row['generated_at'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '<br><small>' . htmlspecialchars($generated, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</small>' : '-') . '</td>';
    echo '<td>' . (!empty($row['printed_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string)$row['printed_at'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '<br><small>' . htmlspecialchars($printed, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</small>' : '-') . '</td>';
    echo '<td>' . $fileLink . '</td>';
    echo '<td class="copertine-actions text-center">';
    if (in_array(strtoupper((string)$row['stato']), ['GENERATA', 'STAMPATA'], true)) {
        $title = strtoupper((string)$row['stato']) === 'STAMPATA' ? 'Ristampa copertina' : 'Stampa copertina';
        echo '<a class="btn btn-primary btn-xs" target="_blank" href="programmiSvoltiCopertinePrint.php?id=' . intval($row['id']) . '" data-toggle="tooltip" title="' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"><span class="glyphicon glyphicon-print"></span></a>';
    } else {
        echo '-';
    }
    echo '</td>';
    echo '<td>' . htmlspecialchars((string)($row['error_message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '</tr>';
}
echo '</tbody></table></div>';
