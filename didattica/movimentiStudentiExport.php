<?php

require_once '../common/checkSession.php';
require_once '../common/studentiMovimentiLib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

studentiMovimentiEnsureTables();

$format = strtolower(trim((string)($_GET['format'] ?? 'xls')));
if (!in_array($format, ['xls', 'pdf'], true)) {
    $format = 'xls';
}
$activeSection = trim((string)($_GET['sezione'] ?? 'uscite'));
if (!in_array($activeSection, ['uscite', 'entrate', 'tutte'], true)) {
    $activeSection = 'uscite';
}
$activeYear = intval($_GET['anno'] ?? 1);
if ($activeYear < 1 || $activeYear > 5) {
    $activeYear = 1;
}
$filterText = trim((string)($_GET['q'] ?? ''));
$filterState = trim((string)($_GET['stato'] ?? ''));
$tipi = studentiMovimentiTipi();
$stati = studentiMovimentiStati();
if ($filterState !== '' && !array_key_exists($filterState, $stati)) {
    $filterState = '';
}
$canSeeColloqui = function_exists('haRuolo') ? haRuolo('admin') : ((string)($__utente_ruolo ?? '') === 'admin');

function mse_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mse_practice_name(array $row): string
{
    $name = trim((string)($row['cognome'] ?: $row['studente_cognome']) . ' ' . (string)($row['nome'] ?: $row['studente_nome']));
    return $name !== '' ? $name : 'Studente provvisorio';
}

function mse_year_label(int $year): string
{
    $labels = [1 => 'prime', 2 => 'seconde', 3 => 'terze', 4 => 'quarte', 5 => 'quinte'];
    return $labels[$year] ?? 'prime';
}

function mse_lower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function mse_filter_blob(array $row, array $tipi, array $stati): string
{
    $sectionWords = (string)($row['tipo_pratica'] ?? '') === 'entrata'
        ? ['entrata', 'entrate']
        : ['uscita', 'uscite'];
    $parts = [
        ...$sectionWords,
        mse_practice_name($row),
        $row['codice_fiscale'] ?? '',
        $row['studente_cf'] ?? '',
        $row['classe_origine'] ?? '',
        $row['classe_corrente'] ?? '',
        $row['classe_richiesta'] ?? '',
        $row['scuola_provenienza'] ?? '',
        $row['indirizzo_provenienza'] ?? '',
        $row['scuola_destinazione'] ?? '',
        $row['indirizzo_destinazione'] ?? '',
        $row['indirizzo_gestore_nome'] ?? '',
        $row['note'] ?? '',
        $row['esami_integrativi_note'] ?? '',
        $row['carenze_note'] ?? '',
        $row['tipo_pratica'] ?? '',
        $tipi[$row['tipo_pratica'] ?? ''] ?? '',
        $row['stato_pratica'] ?? '',
        $stati[$row['stato_pratica'] ?? ''] ?? '',
    ];
    if (!empty($row['doppio_bocciato'])) {
        $parts[] = 'doppio bocciato';
    }
    if (!empty($row['doppio_bocciato_non_consecutivo'])) {
        $parts[] = 'doppio non consecutivo';
    }
    if (!empty($row['bocciato_altra_scuola'])) {
        $parts[] = 'bocciato altra scuola';
    }
    return mse_lower(implode(' ', array_map('strval', $parts)));
}

function mse_matches_filters(array $row, string $filterText, string $filterState, array $tipi, array $stati): bool
{
    if ($filterState !== '' && (string)($row['stato_pratica'] ?? '') !== $filterState) {
        return false;
    }
    if ($filterText === '') {
        return true;
    }
    return strpos(mse_filter_blob($row, $tipi, $stati), mse_lower($filterText)) !== false;
}

$where = [];
if ($activeSection !== 'tutte') {
    $where[] = "p.tipo_pratica = " . dbQ($activeSection === 'entrate' ? 'entrata' : 'uscita');
    $where[] = "p.anno_corso = " . dbI($activeYear);
} else {
    $where[] = "COALESCE(p.anno_corso, 0) BETWEEN 1 AND 5";
}
$pratiche = dbGetAll("
    SELECT p.*,
           s.cognome AS studente_cognome,
           s.nome AS studente_nome,
           s.codice_fiscale AS studente_cf,
           c.classe AS classe_corrente,
           ind_gestore.nome AS indirizzo_gestore_nome,
           COUNT(a.id) AS allegati_count
    FROM studenti_movimenti_pratiche p
    LEFT JOIN studente s ON s.id = p.id_studente
    LEFT JOIN studente_frequenta sf ON sf.id = (
        SELECT sf2.id
        FROM studente_frequenta sf2
        WHERE sf2.id_studente = s.id
          AND sf2.id_anno_scolastico = " . dbI(studentiMovimentiCurrentYearId()) . "
        ORDER BY sf2.id DESC
        LIMIT 1
    )
    LEFT JOIN classi c ON c.id = sf.id_classe
    LEFT JOIN indirizzo ind_gestore ON ind_gestore.id = p.id_indirizzo_gestore
    LEFT JOIN studenti_movimenti_allegati a ON a.id_pratica = p.id
    WHERE " . implode("\n      AND ", $where) . "
    GROUP BY p.id
    ORDER BY COALESCE(p.cognome, s.cognome, '') ASC,
             COALESCE(p.nome, s.nome, '') ASC,
             p.updated_at DESC
") ?: [];

$rows = array_values(array_filter($pratiche, static function ($row) use ($filterText, $filterState, $tipi, $stati) {
    return mse_matches_filters($row, $filterText, $filterState, $tipi, $stati);
}));

$colloquiCounts = [];
if ($canSeeColloqui && !empty($rows) && dbGetValue("SHOW TABLES LIKE 'genitori_colloqui'")) {
    $ids = array_map(static function ($row) { return intval($row['id']); }, $rows);
    $counts = dbGetAll("
        SELECT id_movimento, COUNT(*) AS totale
        FROM genitori_colloqui
        WHERE id_movimento IN (" . implode(',', $ids) . ")
        GROUP BY id_movimento
    ") ?: [];
    foreach ($counts as $countRow) {
        $colloquiCounts[intval($countRow['id_movimento'] ?? 0)] = intval($countRow['totale'] ?? 0);
    }
}

$title = $activeSection === 'tutte'
    ? 'Movimenti studenti - tutte le pratiche'
    : 'Movimenti studenti - ' . ($activeSection === 'entrate' ? 'entrate' : 'uscite') . ' future ' . mse_year_label($activeYear);

ob_start();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #17202f; }
        h2 { margin: 0 0 8px; }
        .meta { margin-bottom: 12px; color: #526173; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1f4e79; color: #fff; font-weight: bold; }
        th, td { border: 1px solid #9eacb8; padding: 5px; vertical-align: top; }
        .muted { color: #526173; }
    </style>
</head>
<body>
<h2><?php echo mse_h($title); ?></h2>
<div class="meta">
    Generato il <?php echo date('d/m/Y H:i'); ?>.
    Righe: <?php echo count($rows); ?>.
    <?php if ($filterText !== ''): ?>Filtro testo: <?php echo mse_h($filterText); ?>.<?php endif; ?>
    <?php if ($filterState !== ''): ?>Stato: <?php echo mse_h($stati[$filterState] ?? $filterState); ?>.<?php endif; ?>
</div>
<table>
    <thead>
    <tr>
        <th>Studente</th>
        <th>Codice fiscale</th>
        <th>Classe</th>
        <th>Tipo</th>
        <th>Stato</th>
        <th>Scuola</th>
        <th>Indirizzo GestOre</th>
        <th>Esami</th>
        <th>Carenze</th>
        <th>Segnalazioni</th>
        <th>Allegati</th>
        <?php if ($canSeeColloqui): ?><th>Colloqui</th><?php endif; ?>
        <th>Aggiornata</th>
        <th>Note</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
        <?php
        $classe = trim((string)($row['classe_origine'] ?: $row['classe_corrente'] ?: ''));
        $rowSection = (string)($row['tipo_pratica'] ?? '') === 'entrata' ? 'entrate' : 'uscite';
        $scuola = $rowSection === 'entrate'
            ? trim((string)($row['scuola_provenienza'] ?: ''))
            : trim((string)($row['scuola_destinazione'] ?: ''));
        $segnalazioni = [];
        if (!empty($row['doppio_bocciato'])) {
            $segnalazioni[] = 'Doppio bocciato';
        }
        if (!empty($row['doppio_bocciato_non_consecutivo'])) {
            $segnalazioni[] = 'Doppio non consecutivo';
        }
        if (!empty($row['bocciato_altra_scuola'])) {
            $segnalazioni[] = 'Bocciato altra scuola';
        }
        if (!empty($row['id_pratica_iscrizione'])) {
            $segnalazioni[] = 'Pratica iscrizione #' . intval($row['id_pratica_iscrizione']);
        }
        ?>
        <tr>
            <td><?php echo mse_h(studentiMovimentiUpperName(mse_practice_name($row))); ?></td>
            <td><?php echo mse_h($row['codice_fiscale'] ?: $row['studente_cf'] ?: ''); ?></td>
            <td>
                <?php echo mse_h($classe !== '' ? $classe : '-'); ?>
                <?php if (trim((string)($row['classe_richiesta'] ?? '')) !== ''): ?>
                    <br><span class="muted">richiesta <?php echo mse_h($row['classe_richiesta']); ?></span>
                <?php endif; ?>
            </td>
            <td><?php echo mse_h($tipi[$row['tipo_pratica']] ?? $row['tipo_pratica']); ?></td>
            <td><?php echo mse_h($stati[$row['stato_pratica']] ?? $row['stato_pratica']); ?></td>
            <td>
                <?php echo mse_h($scuola !== '' ? $scuola : '-'); ?>
                <?php if ($rowSection === 'uscite' && trim((string)($row['indirizzo_destinazione'] ?? '')) !== ''): ?>
                    <br><span class="muted"><?php echo mse_h($row['indirizzo_destinazione']); ?></span>
                <?php elseif ($rowSection === 'entrate' && trim((string)($row['indirizzo_provenienza'] ?? '')) !== ''): ?>
                    <br><span class="muted"><?php echo mse_h($row['indirizzo_provenienza']); ?></span>
                <?php endif; ?>
            </td>
            <td><?php echo mse_h($row['indirizzo_gestore_nome'] ?? ''); ?></td>
            <td>
                <?php echo !empty($row['esami_integrativi']) ? 'Si' : 'No'; ?>
                <?php if (trim((string)($row['esami_integrativi_note'] ?? '')) !== ''): ?>
                    <br><span class="muted"><?php echo mse_h($row['esami_integrativi_note']); ?></span>
                <?php endif; ?>
            </td>
            <td>
                <?php echo !empty($row['carenze_presenti']) ? 'Si' : 'No'; ?>
                <?php if (trim((string)($row['carenze_note'] ?? '')) !== ''): ?>
                    <br><span class="muted"><?php echo mse_h($row['carenze_note']); ?></span>
                <?php endif; ?>
            </td>
            <td><?php echo mse_h(implode(', ', $segnalazioni)); ?></td>
            <td><?php echo intval($row['allegati_count'] ?? 0); ?></td>
            <?php if ($canSeeColloqui): ?><td><?php echo intval($colloquiCounts[intval($row['id'] ?? 0)] ?? 0); ?></td><?php endif; ?>
            <td><?php echo mse_h(studentiMovimentiFormatDateTimeIt((string)($row['updated_at'] ?? ''))); ?></td>
            <td><?php echo nl2br(mse_h($row['note'] ?? '')); ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
        <tr><td colspan="<?php echo $canSeeColloqui ? 14 : 13; ?>">Nessuna pratica trovata.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</body>
</html>
<?php
$html = ob_get_clean();

$basename = 'movimenti_' . $activeSection . '_' . mse_year_label($activeYear) . '_' . date('Ymd_His');

if ($format === 'pdf') {
    require_once '../common/dompdf/vendor/autoload.php';
    $dompdf = new Dompdf\Dompdf(['isRemoteEnabled' => true]);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->render();
    $dompdf->stream($basename . '.pdf', ['Attachment' => true]);
    exit;
}

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $basename . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');
echo "\xEF\xBB\xBF";
echo $html;
