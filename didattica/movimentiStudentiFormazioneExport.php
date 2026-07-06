<?php

require_once '../common/checkSession.php';
require_once '../common/studentiMovimentiLib.php';
require_once '../common/formazioneClassiLib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

studentiMovimentiEnsureTables();
iscrizioniPrimeEnsureSchema();

$format = strtolower(trim((string)($_GET['format'] ?? 'xls')));
if (!in_array($format, ['xls', 'pdf'], true)) {
    $format = 'xls';
}

function msfe_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function msfe_practice_name(array $row): string
{
    $name = trim((string)($row['cognome'] ?: $row['studente_cognome']) . ' ' . (string)($row['nome'] ?: $row['studente_nome']));
    return $name !== '' ? $name : 'Studente provvisorio';
}

function msfe_year_label(int $year): string
{
    $labels = [1 => 'Prime', 2 => 'Seconde', 3 => 'Terze', 4 => 'Quarte', 5 => 'Quinte'];
    return $labels[$year] ?? 'Anno ' . $year;
}

function msfe_address_label(array $row): string
{
    $year = intval($row['anno_corso'] ?? 0);
    if ($year <= 2) {
        if ($year === 2) {
            foreach ([$row['preiscrizione_terze_indirizzo_nome'] ?? '', $row['preiscrizione_terze_corso'] ?? ''] as $value) {
                $value = trim((string)$value);
                if ($value !== '') {
                    return $value . msfe_cat_curvature_suffix($row, $value);
                }
            }
        }
        return 'BIENNIO SETTORE TECNOLOGICO';
    }

    $values = [
        $row['indirizzo_gestore_nome'] ?? '',
        $row['indirizzo_destinazione'] ?? '',
    ];
    foreach ($values as $value) {
        $value = trim((string)$value);
        if ($value !== '') {
            return $value . msfe_cat_curvature_suffix($row, $value);
        }
    }

    if (intval($row['classe_corrente_id_primo_indirizzo'] ?? 0) > 0) {
        $name = trim((string)dbGetValue("SELECT nome FROM indirizzo WHERE id = " . dbI($row['classe_corrente_id_primo_indirizzo']) . " LIMIT 1"));
        if ($name !== '') {
            return $name . msfe_cat_curvature_suffix($row, $name);
        }
    }

    foreach ([$row['classe_origine'] ?? '', $row['classe_corrente'] ?? ''] as $classLabel) {
        $classLabel = trim((string)$classLabel);
        if ($classLabel === '') {
            continue;
        }
        $localClass = dbGetFirst("SELECT id_primo_indirizzo FROM classi WHERE classe = " . dbQ($classLabel) . " LIMIT 1");
        if ($localClass && intval($localClass['id_primo_indirizzo'] ?? 0) > 0) {
            $name = trim((string)dbGetValue("SELECT nome FROM indirizzo WHERE id = " . dbI($localClass['id_primo_indirizzo']) . " LIMIT 1"));
            if ($name !== '') {
                return $name . msfe_cat_curvature_suffix($row, $name);
            }
        }
        $address = formazioneClassiAddressKeyFromClass($classLabel);
        if ($address !== '') {
            $label = formazioneClassiAddressLabel($address);
            return $label . msfe_cat_curvature_suffix($row, $label);
        }
    }

    return 'Indirizzo da verificare';
}

function msfe_cat_curvature_suffix(array $row, string $baseLabel): string
{
    $text = strtoupper(trim($baseLabel
        . ' ' . (string)($row['preiscrizione_terze_corso'] ?? '')
        . ' ' . (string)($row['preiscrizione_terze_scelta_formativa'] ?? '')
        . ' ' . (string)($row['classe_origine'] ?? '')
        . ' ' . (string)($row['classe_corrente'] ?? '')));
    if (!msfe_is_cat_text($text)) {
        return '';
    }
    $curvature = strtolower(trim((string)($row['preiscrizione_terze_curvatura_design'] ?? '')));
    if ($curvature === '') {
        if (preg_match('/\b[3-5]CT[CD]\b/u', $text) || strpos($text, 'DESIGN') !== false) {
            $curvature = 'design';
        } elseif (preg_match('/\b[3-5]CT[AB]\b/u', $text) || strpos($text, 'NORMAL') !== false) {
            $curvature = 'normale';
        }
    }
    if ($curvature === 'design') {
        return ' - DESIGN';
    }
    if ($curvature === 'normale') {
        return ' - NORMALE';
    }
    return ' - CURVATURA DA VERIFICARE';
}

function msfe_is_cat_text(string $text): bool
{
    return strpos($text, 'COSTRUZ') !== false
        || preg_match('/\bCAT\b/u', $text) === 1
        || preg_match('/\b[3-5]CT[ABCD]\b/u', $text) === 1;
}

function msfe_address_source(array $row): string
{
    $year = intval($row['anno_corso'] ?? 0);
    if ($year <= 2) {
        if ($year === 2 && intval($row['preiscrizione_terze_id'] ?? 0) > 0) {
            return 'futuro da pre-iscrizione terze';
        }
        return 'biennio tecnologico';
    }
    if (trim((string)($row['indirizzo_gestore_nome'] ?? '')) !== '') {
        return 'attuale da indirizzo GestOre pratica';
    }
    if (trim((string)($row['indirizzo_destinazione'] ?? '')) !== '') {
        return 'attuale da indirizzo pratica';
    }
    if (trim((string)($row['classe_origine'] ?? '')) !== '' || trim((string)($row['classe_corrente'] ?? '')) !== '') {
        return 'attuale dedotto dalla classe interna';
    }
    return 'da verificare';
}

function msfe_is_confirmed_exit(array $row): bool
{
    if ((string)($row['tipo_pratica'] ?? '') === 'bocciato_reiscrizione') {
        return false;
    }
    return in_array((string)($row['stato_pratica'] ?? ''), ['nulla_osta_inviato', 'chiusa'], true);
}

function msfe_is_open_exit(array $row): bool
{
    if ((string)($row['tipo_pratica'] ?? '') === 'bocciato_reiscrizione') {
        return false;
    }
    return !msfe_is_confirmed_exit($row);
}

function msfe_type_label(array $row, array $tipi, array $stati): string
{
    $type = (string)($row['tipo_pratica'] ?? '');
    return ($tipi[$type] ?? $type) . ' - ' . ($stati[$row['stato_pratica']] ?? $row['stato_pratica']);
}

function msfe_count_bucket(array $row): string
{
    if ((string)($row['tipo_pratica'] ?? '') === 'bocciato_reiscrizione') {
        return 'Bocciati / reiscrizioni';
    }
    return msfe_is_confirmed_exit($row) ? 'Uscite / ritiri confermati' : 'Uscite / ritiri aperti';
}

$tipi = studentiMovimentiTipi();
$stati = studentiMovimentiStati();

$rows = dbGetAll("
    SELECT p.*,
           s.cognome AS studente_cognome,
           s.nome AS studente_nome,
           s.codice_fiscale AS studente_cf,
           c.classe AS classe_corrente,
           c.id_primo_indirizzo AS classe_corrente_id_primo_indirizzo,
           ind_gestore.nome AS indirizzo_gestore_nome,
           pre.id AS preiscrizione_terze_id,
           pre.corso_studi AS preiscrizione_terze_corso,
           pre.scelta_formativa AS preiscrizione_terze_scelta_formativa,
           pre.curvatura_design AS preiscrizione_terze_curvatura_design,
           pre.id_indirizzo_gestore AS preiscrizione_terze_indirizzo_id,
           ind_pre.nome AS preiscrizione_terze_indirizzo_nome
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
    LEFT JOIN iscrizioni_prime_pratiche pre ON pre.id = (
        SELECT pre2.id
        FROM iscrizioni_prime_pratiche pre2
        WHERE pre2.tipo_iscrizione = 'terze'
          AND pre2.codice_fiscale IS NOT NULL
          AND pre2.codice_fiscale <> ''
          AND UPPER(TRIM(pre2.codice_fiscale)) = UPPER(TRIM(COALESCE(NULLIF(p.codice_fiscale, ''), s.codice_fiscale, '')))
        ORDER BY
          CASE WHEN pre2.stato = 'annullata' THEN 1 ELSE 0 END ASC,
          pre2.anno_scolastico DESC,
          pre2.updated_at DESC,
          pre2.id DESC
        LIMIT 1
    )
    LEFT JOIN indirizzo ind_pre ON ind_pre.id = pre.id_indirizzo_gestore
    WHERE p.tipo_pratica IN ('bocciato_reiscrizione','uscita','ritiro')
      AND p.stato_pratica <> 'annullata'
      AND p.anno_corso BETWEEN 1 AND 5
    ORDER BY COALESCE(ind_pre.nome, ind_gestore.nome, p.indirizzo_destinazione, '') ASC,
             p.anno_corso ASC,
             COALESCE(p.cognome, s.cognome, '') ASC,
             COALESCE(p.nome, s.nome, '') ASC
") ?: [];

$summary = [];
foreach ($rows as $row) {
    $address = msfe_address_label($row);
    $year = intval($row['anno_corso'] ?? 0);
    if (!isset($summary[$address])) {
        $summary[$address] = [
            'totale_non_annullate' => 0,
            'bocciati_reiscrizioni' => 0,
            'uscite_ritiri_confermati' => 0,
            'uscite_ritiri_aperti' => 0,
            'anni' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
        ];
    }
    $summary[$address]['totale_non_annullate']++;
    if ((string)($row['tipo_pratica'] ?? '') === 'bocciato_reiscrizione') {
        $summary[$address]['bocciati_reiscrizioni']++;
    } elseif (msfe_is_confirmed_exit($row)) {
        $summary[$address]['uscite_ritiri_confermati']++;
    } else {
        $summary[$address]['uscite_ritiri_aperti']++;
    }
    if ($year >= 1 && $year <= 5) {
        $summary[$address]['anni'][$year]++;
    }
}
ksort($summary, SORT_NATURAL | SORT_FLAG_CASE);

$title = 'Riepilogo bocciati e uscite per formazione classi';

ob_start();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #17202f; }
        h2 { margin: 0 0 8px; }
        h3 { margin: 18px 0 8px; }
        .meta { margin-bottom: 12px; color: #526173; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th { background: #1f4e79; color: #fff; font-weight: bold; }
        th, td { border: 1px solid #9eacb8; padding: 5px; vertical-align: top; }
        .number { text-align: right; }
        .muted { color: #526173; }
    </style>
</head>
<body>
<h2><?php echo msfe_h($title); ?></h2>
<div class="meta">
    Generato il <?php echo date('d/m/Y H:i'); ?>.
    Righe dettaglio: <?php echo count($rows); ?>.
    Prime e seconde senza indirizzo futuro vengono attribuite a BIENNIO SETTORE TECNOLOGICO.
    Le seconde usano l'indirizzo futuro della pre-iscrizione alle terze quando presente; terze, quarte e quinte usano l'indirizzo della pratica o quello dedotto dalla classe interna.
    Uscite/ritiri confermati = stati Nulla osta inviato o Chiusa.
</div>

<h3>Totali per indirizzo</h3>
<table>
    <thead>
    <tr>
        <th>Indirizzo</th>
        <th>Bocciati / reiscrizioni</th>
        <th>Uscite / ritiri confermati</th>
        <th>Uscite / ritiri aperti</th>
        <th>Prime</th>
        <th>Seconde</th>
        <th>Terze</th>
        <th>Quarte</th>
        <th>Quinte</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($summary as $address => $data): ?>
        <tr>
            <td><?php echo msfe_h($address); ?></td>
            <td class="number"><?php echo intval($data['bocciati_reiscrizioni']); ?></td>
            <td class="number"><?php echo intval($data['uscite_ritiri_confermati']); ?></td>
            <td class="number"><?php echo intval($data['uscite_ritiri_aperti']); ?></td>
            <?php foreach ([1, 2, 3, 4, 5] as $year): ?>
                <td class="number"><?php echo intval($data['anni'][$year] ?? 0); ?></td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($summary)): ?>
        <tr><td colspan="9">Nessun movimento trovato.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<h3>Dettaglio studenti</h3>
<table>
    <thead>
    <tr>
        <th>Indirizzo riepilogo</th>
        <th>Fonte indirizzo</th>
        <th>Anno</th>
        <th>Studente</th>
        <th>Codice fiscale</th>
        <th>Classe</th>
        <th>Tipo</th>
        <th>Conteggio riepilogo</th>
        <th>Stato</th>
        <th>Scuola destinazione</th>
        <th>Note</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
        <?php
        $classe = trim((string)($row['classe_origine'] ?: $row['classe_corrente'] ?: ''));
        ?>
        <tr>
            <td><?php echo msfe_h(msfe_address_label($row)); ?></td>
            <td><?php echo msfe_h(msfe_address_source($row)); ?></td>
            <td><?php echo msfe_h(msfe_year_label(intval($row['anno_corso'] ?? 0))); ?></td>
            <td><?php echo msfe_h(studentiMovimentiUpperName(msfe_practice_name($row))); ?></td>
            <td><?php echo msfe_h($row['codice_fiscale'] ?: $row['studente_cf'] ?: ''); ?></td>
            <td><?php echo msfe_h($classe !== '' ? $classe : '-'); ?></td>
            <td><?php echo msfe_h($tipi[$row['tipo_pratica']] ?? $row['tipo_pratica']); ?></td>
            <td><?php echo msfe_h(msfe_count_bucket($row)); ?></td>
            <td><?php echo msfe_h($stati[$row['stato_pratica']] ?? $row['stato_pratica']); ?></td>
            <td>
                <?php echo msfe_h(trim((string)($row['scuola_destinazione'] ?? '')) !== '' ? $row['scuola_destinazione'] : '-'); ?>
                <?php if (trim((string)($row['indirizzo_destinazione'] ?? '')) !== ''): ?>
                    <br><span class="muted"><?php echo msfe_h($row['indirizzo_destinazione']); ?></span>
                <?php endif; ?>
            </td>
            <td><?php echo nl2br(msfe_h($row['note'] ?? '')); ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
        <tr><td colspan="11">Nessun movimento trovato.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</body>
</html>
<?php
$html = ob_get_clean();

$basename = 'movimenti_formazione_classi_' . date('Ymd_His');

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
