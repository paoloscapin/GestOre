<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/programmiSvoltiCopertineLib.php';

ruoloRichiesto('segreteria-didattica', 'dirigente');

if (!programmiSvoltiCopertineTableExists()) {
    echo '<div class="alert alert-warning">Tabella copertine non configurata.</div>';
    exit;
}
programmiSvoltiCopertineEnsureConsegnaColumns();

$q = trim((string)($_GET['q'] ?? ''));
$consegna = trim((string)($_GET['consegna'] ?? ''));
$generazione = trim((string)($_GET['generazione'] ?? ''));
$sort = trim((string)($_GET['sort'] ?? 'stato'));
$order = strtolower(trim((string)($_GET['order'] ?? 'asc'))) === 'desc' ? 'DESC' : 'ASC';
$hasPrintedAt = programmiSvoltiCopertinaColumnExists('printed_at');
$hasPrintedBy = programmiSvoltiCopertinaColumnExists('printed_by_user_id');

$where = [];
if ($q !== '') {
    $like = "'%" . dbEscape($q) . "%'";
    $where[] = "(
        c.stato LIKE $like
        OR c.fascicolo_codice LIKE $like
        OR c.file_name LIKE $like
        OR materia.nome LIKE $like
        OR docente.cognome LIKE $like
        OR docente.nome LIKE $like
        OR CONCAT(docente.cognome, ' ', docente.nome) LIKE $like
        OR classi.classe LIKE $like
        OR anno_scolastico.anno LIKE $like
        OR EXISTS (
            SELECT 1
            FROM programmi_svolti_classi psc_search
            INNER JOIN classi c_search ON c_search.id = psc_search.id_classe
            WHERE psc_search.id_programma_svolto = ps.id
              AND c_search.classe LIKE $like
        )
    )";
}
if ($consegna === 'consegnate') {
    $where[] = "c.verifiche_consegnate = 1";
} elseif ($consegna === 'non_consegnate') {
    $where[] = "COALESCE(c.verifiche_consegnate, 0) = 0";
}
if ($generazione === 'da_generare') {
    $where[] = "c.stato IN ('RICHIESTA', 'ERRORE')";
} elseif ($generazione === 'generate') {
    $where[] = "c.stato IN ('GENERATA', 'STAMPATA')";
}
$whereSql = empty($where) ? '' : ' WHERE ' . implode(' AND ', $where);

$sortMap = [
    'stato' => ["FIELD(c.stato, 'RICHIESTA', 'ERRORE', 'GENERATA', 'STAMPATA', 'ANNULLATA')"],
    'fascicolo' => ['c.fascicolo_codice'],
    'classe' => ['classi.classe'],
    'materia' => ['materia.nome'],
    'docente' => ['docente.cognome', 'docente.nome'],
    'richiesta' => ['c.requested_at'],
    'generazione' => ['c.generated_at'],
    'stampa' => [$hasPrintedAt ? 'c.printed_at' : 'c.updated_at'],
    'consegna' => ['c.verifiche_consegnate', 'c.verifiche_consegnate_at'],
    'file' => ['c.file_name'],
];
$orderExprs = $sortMap[$sort] ?? $sortMap['stato'];
$orderParts = array_map(function ($expr) use ($order) {
    return $expr . ' ' . $order;
}, $orderExprs);
$orderParts[] = 'classi.classe ASC';
$orderParts[] = 'materia.nome ASC';
$orderParts[] = 'c.requested_at ASC';
$orderParts[] = 'c.id ASC';
$orderSql = ' ORDER BY ' . implode(', ', $orderParts);

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
        u_gen.nome AS generated_nome,
        u_cons.cognome AS consegna_cognome,
        u_cons.nome AS consegna_nome" .
        ($hasPrintedAt ? ",
        c.printed_at AS printed_at" : ",
        NULL AS printed_at") .
        ($hasPrintedBy ? ",
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
    LEFT JOIN utente u_gen ON u_gen.id = c.generated_by_user_id
    LEFT JOIN utente u_cons ON u_cons.id = c.verifiche_consegnate_by_user_id" .
    ($hasPrintedBy ? "
    LEFT JOIN utente u_print ON u_print.id = c.printed_by_user_id" : "") . "
    $whereSql
    $orderSql");

if (!$rows) {
    echo '<div class="alert alert-info">Nessuna copertina trovata con i filtri selezionati.</div>';
    exit;
}

function copertineSortLink(string $key, string $label, string $currentSort, string $currentOrder): string
{
    $icon = '';
    if ($key === $currentSort) {
        $icon = $currentOrder === 'DESC' ? ' <span class="glyphicon glyphicon-triangle-bottom"></span>' : ' <span class="glyphicon glyphicon-triangle-top"></span>';
    }
    return '<a href="#" class="copertine-sort" onclick="programmiSvoltiCopertineSort(\'' . htmlspecialchars($key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '\'); return false;">' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . $icon . '</a>';
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
echo '<th class="text-center" style="width:6%;">' . copertineSortLink('stato', 'Stato', $sort, $order) . '</th>';
echo '<th class="text-center" style="width:8%;">' . copertineSortLink('fascicolo', 'Fascicolo', $sort, $order) . '</th>';
echo '<th class="text-center" style="width:7%;">' . copertineSortLink('classe', 'Classe', $sort, $order) . '</th>';
echo '<th style="width:15%;">' . copertineSortLink('materia', 'Materia', $sort, $order) . '</th>';
echo '<th class="text-center" style="width:11%;">' . copertineSortLink('docente', 'Docente', $sort, $order) . '</th>';
echo '<th class="text-center" style="width:11%;">' . copertineSortLink('richiesta', 'Richiesta', $sort, $order) . '</th>';
echo '<th class="text-center" style="width:12%;">' . copertineSortLink('generazione', 'Generazione', $sort, $order) . '</th>';
echo '<th class="text-center" style="width:12%;">' . copertineSortLink('stampa', 'Stampa', $sort, $order) . '</th>';
echo '<th class="text-center" style="width:7%;">' . copertineSortLink('consegna', 'Consegna verifiche', $sort, $order) . '</th>';
echo '<th style="width:7%;">' . copertineSortLink('file', 'File', $sort, $order) . '</th>';
echo '<th class="text-center" style="width:4%;">Azioni</th>';
echo '<th class="text-center" style="width:4%;">Errore</th>';
echo '</tr></thead><tbody>';
foreach ($rows as $row) {
    $classe = trim((string)($row['classi_collegate_nome'] ?? '')) !== '' ? (string)$row['classi_collegate_nome'] : (string)$row['classe_nome'];
    $requested = trim((string)($row['requested_cognome'] ?? '') . ' ' . (string)($row['requested_nome'] ?? ''));
    $generated = trim((string)($row['generated_cognome'] ?? '') . ' ' . (string)($row['generated_nome'] ?? ''));
    $printed = trim((string)($row['printed_cognome'] ?? '') . ' ' . (string)($row['printed_nome'] ?? ''));
    $consegnaUtente = trim((string)($row['consegna_cognome'] ?? '') . ' ' . (string)($row['consegna_nome'] ?? ''));
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
    echo '<td class="text-center">' . htmlspecialchars((string)($row['fascicolo_codice'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td class="text-center">' . htmlspecialchars($classe, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string)$row['materia_nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td class="text-center">' . htmlspecialchars(trim((string)$row['docente_cognome'] . ' ' . (string)$row['docente_nome']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td class="text-center">' . htmlspecialchars(date('d/m/Y H:i', strtotime((string)$row['requested_at'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '<br><small>' . htmlspecialchars($requested, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</small></td>';
    echo '<td class="text-center">' . (!empty($row['generated_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string)$row['generated_at'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '<br><small>' . htmlspecialchars($generated, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</small>' : '-') . '</td>';
    echo '<td class="text-center">' . (!empty($row['printed_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string)$row['printed_at'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '<br><small>' . htmlspecialchars($printed, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</small>' : '-') . '</td>';
    echo '<td class="text-center">';
    if (intval($row['verifiche_consegnate'] ?? 0) === 1) {
        $consegnaTitle = !empty($row['verifiche_consegnate_at'])
            ? 'Consegnate il ' . date('d/m/Y H:i', strtotime((string)$row['verifiche_consegnate_at'])) . ($consegnaUtente !== '' ? ' da ' . $consegnaUtente : '')
            : 'Verifiche consegnate';
        echo '<button type="button" class="btn btn-success btn-xs" onclick="programmiSvoltiCopertineConsegna(' . intval($row['id']) . ',0)" data-toggle="tooltip" title="' . htmlspecialchars($consegnaTitle . ' - clicca per rimuovere la spunta', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"><span class="glyphicon glyphicon-check"></span></button>';
        echo '<br><small>' . (!empty($row['verifiche_consegnate_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string)$row['verifiche_consegnate_at'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '') . '</small>';
    } else {
        echo '<button type="button" class="btn btn-default btn-xs" onclick="programmiSvoltiCopertineConsegna(' . intval($row['id']) . ',1)" data-toggle="tooltip" title="Segna verifiche consegnate"><span class="glyphicon glyphicon-unchecked"></span></button>';
    }
    echo '</td>';
    echo '<td>' . $fileLink . '</td>';
    echo '<td class="copertine-actions text-center">';
    if (in_array(strtoupper((string)$row['stato']), ['GENERATA', 'STAMPATA'], true)) {
        $title = strtoupper((string)$row['stato']) === 'STAMPATA' ? 'Ristampa copertina' : 'Stampa copertina';
        echo '<a class="btn btn-primary btn-xs" target="_blank" href="programmiSvoltiCopertinePrint.php?id=' . intval($row['id']) . '" data-toggle="tooltip" title="' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"><span class="glyphicon glyphicon-print"></span></a>';
        echo ' <button type="button" class="btn btn-warning btn-xs" onclick="programmiSvoltiCopertineRegenerate(' . intval($row['id']) . ')" data-toggle="tooltip" title="Rigenera e sostituisci il PDF su Drive"><span class="glyphicon glyphicon-repeat"></span></button>';
    }
    echo ' <button type="button" class="btn btn-danger btn-xs" onclick="programmiSvoltiCopertineDelete(' . intval($row['id']) . ')" data-toggle="tooltip" title="Annulla richiesta e riporta allo stato iniziale"><span class="glyphicon glyphicon-trash"></span></button>';
    echo '</td>';
    echo '<td class="text-center">' . htmlspecialchars((string)($row['error_message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '</tr>';
}
echo '</tbody></table></div>';
