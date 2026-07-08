<?php

require_once '../common/checkSession.php';
require_once '../common/studentiMovimentiLib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

studentiMovimentiEnsureTables();
iscrizioniPrimeEnsureSchema();

$canOpenColloqui = function_exists('haRuolo') ? haRuolo('admin') : ((string)($__utente_ruolo ?? '') === 'admin');
$message = '';
$error = '';
$syncResult = null;
$autoSyncResult = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string)($_POST['action'] ?? 'save'));
        if ($action === 'sync_bocciati') {
            $syncResult = studentiMovimentiSyncBocciatiFromTabelloni(studentiMovimentiCurrentYearId());
            $message = 'Bocciati aggiornati dai tabelloni.';
        } elseif ($action === 'sync_iscrizioni') {
            $syncResult = studentiMovimentiSyncCambioScuolaDaIscrizioni();
            $message = 'Cambi scuola aggiornati dalle iscrizioni.';
        } elseif ($action === 'delete') {
            $deleted = studentiMovimentiDeletePractice((int)($_POST['id'] ?? 0));
            $message = $deleted ? 'Pratica eliminata.' : 'Pratica non trovata.';
        } elseif ($action === 'delete_event') {
            $deleted = studentiMovimentiDeleteEvent((int)($_POST['event_id'] ?? 0));
            $message = $deleted ? 'Riga storico eliminata.' : 'Riga storico non trovata.';
        } elseif ($action === 'update_event') {
            $updated = studentiMovimentiUpdateEvent(
                (int)($_POST['event_id'] ?? 0),
                (string)($_POST['event_descrizione'] ?? ''),
                (string)($_POST['event_note'] ?? '')
            );
            $message = $updated ? 'Riga storico aggiornata.' : 'Riga storico non trovata.';
        } elseif ($action === 'add_event_attachment') {
            $uploaded = studentiMovimentiAttachFileToEvent(
                (int)($_POST['event_id'] ?? 0),
                $_FILES['event_allegato'] ?? [],
                (string)($_POST['tipo_allegato'] ?? 'documento')
            );
            $message = $uploaded ? 'Allegato aggiunto alla riga storico.' : 'Allegato non caricato.';
        } elseif ($action === 'add_event') {
            $practiceId = (int)($_POST['id'] ?? 0);
            $description = trim((string)($_POST['event_descrizione'] ?? ''));
            $note = trim((string)($_POST['event_note'] ?? ''));
            if ($practiceId <= 0 || $description === '') {
                throw new RuntimeException('Scrivi almeno una descrizione per aggiungere un evento.');
            }
            $eventId = studentiMovimentiAddEvent($practiceId, 'nota_segreteria', $description, [
                'note' => $note,
                'tipo_pratica' => (string)($_POST['tipo_pratica'] ?? ''),
                'stato_pratica' => (string)($_POST['stato_pratica'] ?? ''),
            ], studentiMovimentiCurrentActor());
            if ($eventId > 0 && !empty($_FILES['event_allegato'])) {
                studentiMovimentiAttachFileToEvent($eventId, $_FILES['event_allegato'], (string)($_POST['tipo_allegato'] ?? 'altro'));
            }
            $message = 'Evento aggiunto allo storico.';
        } elseif ($action === 'add_practice_attachment') {
            $uploaded = studentiMovimentiAttachFiles(
                (int)($_POST['id'] ?? 0),
                $_FILES['allegato'] ?? [],
                trim((string)($_POST['tipo_allegato'] ?? 'altro'))
            );
            $message = $uploaded > 0 ? 'Documento caricato.' : 'Documento non caricato.';
        } elseif ($action === 'delete_attachment') {
            $deleted = studentiMovimentiDeleteAttachment((int)($_POST['attachment_id'] ?? 0));
            $message = $deleted ? 'Allegato eliminato.' : 'Allegato non trovato.';
        } else {
            $practiceId = studentiMovimentiSavePractice($_POST);
            if (!empty($_FILES['allegato'])) {
                studentiMovimentiAttachFiles($practiceId, $_FILES['allegato'], trim((string)($_POST['tipo_allegato'] ?? 'documento')));
            }
            $message = 'Pratica salvata.';
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && ($_GET['autosync'] ?? '1') !== '0') {
    try {
        $autoSyncResult = studentiMovimentiSyncBocciatiFromTabelloni(studentiMovimentiCurrentYearId());
        $syncResult = $autoSyncResult;
        $iscrizioniSync = studentiMovimentiSyncCambioScuolaDaIscrizioni();
        if (intval($autoSyncResult['created'] ?? 0) > 0) {
            $message = 'Bocciati aggiornati automaticamente dai tabelloni: creati ' . intval($autoSyncResult['created']) . '.';
        } elseif (intval($iscrizioniSync['created'] ?? 0) > 0 || intval($iscrizioniSync['updated'] ?? 0) > 0) {
            $message = 'Cambi scuola iscrizioni sincronizzati: creati ' . intval($iscrizioniSync['created'] ?? 0) . ', aggiornati ' . intval($iscrizioniSync['updated'] ?? 0) . '.';
        }
    } catch (Throwable $e) {
        if ($error === '') {
            $error = 'Aggiornamento automatico bocciati non riuscito: ' . $e->getMessage();
        }
    }
}

$tipi = studentiMovimentiTipi();
$stati = studentiMovimentiStati();
$istitutiScuole = scuoleIstitutiAll();
$materieGestore = dbGetAll("
    SELECT id, nome
    FROM materia
    ORDER BY nome ASC
") ?: [];
$indirizziGestore = iscrizioniPrimeGestoreAddressOptions();
try {
    $entrateStudentiSync = studentiMovimentiEnsureStudentsForEntrate();
    if (intval($entrateStudentiSync['linked'] ?? 0) > 0 && $message === '') {
        $message = 'Studenti GestOre collegati/create per entrate: ' . intval($entrateStudentiSync['linked']) . '.';
    }
    if (intval($entrateStudentiSync['activated'] ?? 0) > 0 && $message === '') {
        $message = 'Studenti GestOre riattivati per entrate: ' . intval($entrateStudentiSync['activated']) . '.';
    }
    if (!empty($entrateStudentiSync['errors']) && $error === '') {
        $error = 'Alcune entrate non hanno ancora lo studente GestOre: ' . implode(' | ', $entrateStudentiSync['errors']);
    }
    $entrateIscrizioniSync = studentiMovimentiEnsureIscrizioniForEntrate();
    if (intval($entrateIscrizioniSync['linked'] ?? 0) > 0 && $message === '') {
        $message = 'Pratiche iscrizione collegate/create per entrate prime-terze: ' . intval($entrateIscrizioniSync['linked']) . '.';
    }
    if (!empty($entrateIscrizioniSync['errors']) && $error === '') {
        $error = 'Alcune entrate prime-terze non hanno ancora la pratica iscrizione: ' . implode(' | ', $entrateIscrizioniSync['errors']);
    }
} catch (Throwable $e) {
    if ($error === '') {
        $error = 'Controllo pratiche iscrizione entrate non riuscito: ' . $e->getMessage();
    }
}
$activeSection = trim((string)($_GET['sezione'] ?? 'entrate'));
if (!in_array($activeSection, ['uscite', 'entrate'], true)) {
    $activeSection = 'entrate';
}
$activeYear = intval($_GET['anno'] ?? 0);
if ($activeYear < 1 || $activeYear > 5) {
    $activeYear = 1;
}
$filterText = trim((string)($_GET['q'] ?? ''));
$filterState = trim((string)($_GET['stato'] ?? ''));
if ($filterState !== '' && !array_key_exists($filterState, $stati)) {
    $filterState = '';
}
$openMovementId = intval($_GET['open_movimento_id'] ?? 0);
$openMovementFound = false;
if ($openMovementId > 0) {
    $openMovement = dbGetFirst("
        SELECT tipo_pratica, anno_corso
        FROM studenti_movimenti_pratiche
        WHERE id = " . dbI($openMovementId) . "
        LIMIT 1
    ");
    if ($openMovement) {
        $openMovementFound = true;
        $activeSection = (($openMovement['tipo_pratica'] ?? '') === 'entrata') ? 'entrate' : 'uscite';
        $movementYear = intval($openMovement['anno_corso'] ?? 0);
        $activeYear = ($movementYear >= 1 && $movementYear <= 5) ? $movementYear : 1;
    }
}

$pratiche = dbGetAll("
    SELECT p.*,
           s.cognome AS studente_cognome,
           s.nome AS studente_nome,
           s.codice_fiscale AS studente_cf,
           s.id AS studente_id,
           c.classe AS classe_corrente,
           c.id_primo_indirizzo AS classe_corrente_id_primo_indirizzo,
           ind_gestore.nome AS indirizzo_gestore_nome,
           pre.id AS preiscrizione_terze_id,
           pre.corso_studi AS preiscrizione_terze_corso,
           pre.scelta_formativa AS preiscrizione_terze_scelta_formativa,
           pre.curvatura_design AS preiscrizione_terze_curvatura_design,
           pre.id_indirizzo_gestore AS preiscrizione_terze_indirizzo_id,
           ind_pre.nome AS preiscrizione_terze_indirizzo_nome,
           linked_pratica.id AS linked_pratica_id,
           linked_pratica.tipo_iscrizione AS linked_pratica_tipo_iscrizione,
           linked_pratica.stato AS linked_pratica_stato,
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
    LEFT JOIN iscrizioni_prime_pratiche linked_pratica ON linked_pratica.id = p.id_pratica_iscrizione
    LEFT JOIN studenti_movimenti_allegati a ON a.id_pratica = p.id
    GROUP BY p.id
    ORDER BY COALESCE(p.anno_corso, 99) ASC,
             COALESCE(p.cognome, s.cognome, '') ASC,
             COALESCE(p.nome, s.nome, '') ASC,
             p.updated_at DESC
") ?: [];

$allegati = [];
if (!empty($pratiche)) {
    $ids = array_map(static function ($row) { return intval($row['id']); }, $pratiche);
    $rows = dbGetAll("
        SELECT *
        FROM studenti_movimenti_allegati
        WHERE id_pratica IN (" . implode(',', $ids) . ")
        ORDER BY created_at DESC, id DESC
    ") ?: [];
    foreach ($rows as $row) {
        $allegati[intval($row['id_pratica'] ?? 0)][] = $row;
    }
}

$storico = [];
if (!empty($pratiche)) {
    $storico = studentiMovimentiHistoryForPractices(array_map(static function ($row) { return intval($row['id']); }, $pratiche));
}

$colloquiCounts = [];
if (!empty($pratiche) && dbGetValue("SHOW TABLES LIKE 'genitori_colloqui'")) {
    $ids = array_map(static function ($row) { return intval($row['id']); }, $pratiche);
    $rows = dbGetAll("
        SELECT id_movimento, COUNT(*) AS totale
        FROM genitori_colloqui
        WHERE id_movimento IN (" . implode(',', $ids) . ")
        GROUP BY id_movimento
    ") ?: [];
    foreach ($rows as $row) {
        $colloquiCounts[intval($row['id_movimento'] ?? 0)] = intval($row['totale'] ?? 0);
    }
}

$currentStudents = dbGetAll("
    SELECT s.id, s.cognome, s.nome, s.codice_fiscale, c.classe
    FROM studente s
    INNER JOIN studente_frequenta sf ON sf.id_studente = s.id
      AND sf.id_anno_scolastico = " . dbI(studentiMovimentiCurrentYearId()) . "
    INNER JOIN classi c ON c.id = sf.id_classe
    WHERE COALESCE(s.attivo, 1) = 1
    ORDER BY c.classe ASC, s.cognome ASC, s.nome ASC
") ?: [];
$realEnrollmentSummary = [];

$grouped = [
    'uscite' => [1 => [], 2 => [], 3 => [], 4 => [], 5 => []],
    'entrate' => [1 => [], 2 => [], 3 => [], 4 => [], 5 => []],
];
foreach ($pratiche as $pratica) {
    $section = ($pratica['tipo_pratica'] ?? '') === 'entrata' ? 'entrate' : 'uscite';
    $year = intval($pratica['anno_corso'] ?? 0);
    if ($year < 1 || $year > 5) {
        continue;
    }
    $grouped[$section][$year][] = $pratica;
}
if ($activeYear === 0 && !$openMovementFound) {
    foreach ([1, 2, 3, 4, 5] as $year) {
        if (!empty($grouped[$activeSection][$year])) {
            $activeYear = $year;
            break;
        }
    }
    if ($activeYear === 0) {
        $activeYear = 1;
    }
}

function ms_selected($a, $b): string
{
    return (string)$a === (string)$b ? 'selected' : '';
}

function ms_active($a, $b): string
{
    return (string)$a === (string)$b ? 'active' : '';
}

function ms_label_class(string $state): string
{
    if (in_array($state, ['reiscrizione_confermata', 'nulla_osta_inviato', 'idoneo_iscrizione', 'chiusa'], true)) {
        return 'success';
    }
    if (in_array($state, ['cambia_scuola', 'si_ritira', 'esami_integrativi', 'documenti_in_verifica'], true)) {
        return 'warning';
    }
    if (in_array($state, ['non_idoneo', 'annullata'], true)) {
        return 'danger';
    }
    return 'default';
}

function ms_year_label(int $year): string
{
    $labels = [1 => 'Prime', 2 => 'Seconde', 3 => 'Terze', 4 => 'Quarte', 5 => 'Quinte'];
    return $labels[$year] ?? 'Prime';
}

function ms_practice_name(array $row): string
{
    $name = trim((string)($row['cognome'] ?: $row['studente_cognome']) . ' ' . (string)($row['nome'] ?: $row['studente_nome']));
    return $name !== '' ? $name : 'Studente provvisorio';
}

function ms_data_attr($value): string
{
    return studentiMovimentiH($value);
}

function ms_iscrizione_pratica_url(array $row): string
{
    $praticaId = intval($row['id_pratica_iscrizione'] ?? 0);
    if ($praticaId <= 0) {
        return '';
    }
    if (intval($row['linked_pratica_id'] ?? 0) !== $praticaId || trim((string)($row['linked_pratica_stato'] ?? '')) === 'annullata') {
        return '';
    }
    $tipoIscrizione = intval($row['anno_corso'] ?? 0) === 3 ? 'terze' : 'prime';
    if ((string)($row['linked_pratica_tipo_iscrizione'] ?? '') !== $tipoIscrizione) {
        return '';
    }
    return 'iscrizioniPrimeDomande.php?tipo_iscrizione=' . rawurlencode($tipoIscrizione)
        . '&stato=tutte&open_pratica_id=' . $praticaId
        . '#pratica-' . $praticaId;
}

function ms_lower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function ms_filter_blob(array $row, array $tipi, array $stati): string
{
    $sectionWords = (string)($row['tipo_pratica'] ?? '') === 'entrata'
        ? ['entrata', 'entrate']
        : ['uscita', 'uscite'];
    $parts = array_merge($sectionWords, [
        ms_practice_name($row),
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
        $row['preiscrizione_terze_indirizzo_nome'] ?? '',
        $row['preiscrizione_terze_corso'] ?? '',
        $row['note'] ?? '',
        $row['esami_integrativi_note'] ?? '',
        $row['carenze_note'] ?? '',
        $row['tipo_pratica'] ?? '',
        $tipi[$row['tipo_pratica'] ?? ''] ?? '',
        $row['stato_pratica'] ?? '',
        $stati[$row['stato_pratica'] ?? ''] ?? '',
    ]);
    if (!empty($row['doppio_bocciato'])) {
        $parts[] = 'doppio bocciato';
    }
    if (!empty($row['doppio_bocciato_non_consecutivo'])) {
        $parts[] = 'doppio non consecutivo';
    }
    if (!empty($row['bocciato_altra_scuola'])) {
        $parts[] = 'bocciato altra scuola';
    }
    return ms_lower(implode(' ', array_map('strval', $parts)));
}

function ms_uscita_has_address_column(string $section, int $year): bool
{
    return $section === 'uscite' && $year >= 2 && $year <= 5;
}

function ms_row_address_label(array $row, int $year, string $section): string
{
    if (!ms_uscita_has_address_column($section, $year)) {
        return '';
    }
    $values = $year === 2
        ? [
            $row['preiscrizione_terze_indirizzo_nome'] ?? '',
            $row['preiscrizione_terze_corso'] ?? '',
            $row['indirizzo_gestore_nome'] ?? '',
            $row['indirizzo_destinazione'] ?? '',
        ]
        : [
            $row['indirizzo_gestore_nome'] ?? '',
            $row['indirizzo_destinazione'] ?? '',
        ];
    foreach ($values as $value) {
        $value = trim((string)$value);
        if ($value !== '') {
            return $value . ms_cat_curvature_suffix($row, $value);
        }
    }
    if ($year <= 2) {
        return 'BIENNIO SETTORE TECNOLOGICO';
    }

    if (intval($row['classe_corrente_id_primo_indirizzo'] ?? 0) > 0) {
        $name = trim((string)dbGetValue("SELECT nome FROM indirizzo WHERE id = " . dbI($row['classe_corrente_id_primo_indirizzo']) . " LIMIT 1"));
        if ($name !== '') {
            return $name . ms_cat_curvature_suffix($row, $name);
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
                return $name . ms_cat_curvature_suffix($row, $name);
            }
        }
        $classAddress = ms_address_label_from_class($classLabel);
        if ($classAddress !== '') {
            return $classAddress . ms_cat_curvature_suffix($row, $classAddress);
        }
    }
    return '';
}

function ms_address_label_from_class(string $classLabel): string
{
    $classLabel = trim($classLabel);
    $norm = strtoupper(trim($classLabel));
    if ($norm === '') {
        return '';
    }
    if (preg_match('/^[1-2]/', $norm)) {
        return 'BIENNIO SETTORE TECNOLOGICO';
    }
    if (preg_match('/^[3-5]DS\b/u', $norm)) {
        return 'DIGITAL SCIENCE';
    }
    if (preg_match('/^[3-5]([A-Z0-9]{2,})\b/u', $norm, $matches)) {
        $code = (string)$matches[1];
        $labels = [
            'MEA' => 'MECCANICA ED ENERGIA',
            'AUA' => 'AUTOMAZIONE',
            'ELA' => 'ELETTRONICA / ELETTROTECNICA',
            'INF' => 'INFORMATICA',
            'TEL' => 'TELECOMUNICAZIONI',
            'TLC' => 'TELECOMUNICAZIONI',
            'BTS' => 'BIOTECNOLOGIE SANITARIE',
            'BTA' => 'BIOTECNOLOGIE AMBIENTALI',
            'CHI' => 'CHIMICA E MATERIALI',
            'GRA' => 'GRAFICA E COMUNICAZIONE',
            'CTA' => 'COSTRUZIONI AMBIENTE E TERRITORIO',
            'CTB' => 'COSTRUZIONI AMBIENTE E TERRITORIO',
            'CTC' => 'COSTRUZIONI AMBIENTE E TERRITORIO',
            'CTD' => 'COSTRUZIONI AMBIENTE E TERRITORIO',
            'CAT' => 'COSTRUZIONI AMBIENTE E TERRITORIO',
        ];
        return $labels[$code] ?? '';
    }
    return '';
}

function ms_cat_curvature_suffix(array $row, string $baseLabel): string
{
    $text = strtoupper(trim($baseLabel
        . ' ' . (string)($row['preiscrizione_terze_corso'] ?? '')
        . ' ' . (string)($row['preiscrizione_terze_scelta_formativa'] ?? '')
        . ' ' . (string)($row['classe_origine'] ?? '')
        . ' ' . (string)($row['classe_corrente'] ?? '')));
    if (!ms_is_cat_text($text)) {
        return '';
    }
    $curvature = strtolower(trim((string)($row['preiscrizione_terze_curvatura_design'] ?? '')));
    if ($curvature === '') {
        $choice = strtoupper(trim((string)($row['preiscrizione_terze_scelta_formativa'] ?? '')));
        if (preg_match('/\b[3-5]CT[CD]\b/u', $text) || strpos($choice, 'DESIGN') !== false) {
            $curvature = 'design';
        } elseif (preg_match('/\b[3-5]CT[AB]\b/u', $text) || strpos($choice, 'NORMAL') !== false) {
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

function ms_is_cat_text(string $text): bool
{
    return strpos($text, 'COSTRUZ') !== false
        || preg_match('/\bCAT\b/u', $text) === 1
        || preg_match('/\b[3-5]CT[ABCD]\b/u', $text) === 1;
}

function ms_row_summary_address(array $row, int $year, string $section): string
{
    $label = ms_row_address_label($row, $year, $section);
    return $label !== '' ? $label : 'Senza indirizzo';
}

function ms_row_counts_in_address_summary(array $row, int $year, string $section): bool
{
    if (!ms_uscita_has_address_column($section, $year)) {
        return false;
    }
    if ((string)($row['stato_pratica'] ?? '') === 'annullata') {
        return false;
    }
    if ($year === 2) {
        return (string)($row['tipo_pratica'] ?? '') === 'bocciato_reiscrizione';
    }
    return true;
}

function ms_address_summary(array $rows, int $year, string $section): array
{
    $summary = [];
    foreach ($rows as $row) {
        if (!ms_row_counts_in_address_summary($row, $year, $section)) {
            continue;
        }
        $address = ms_row_summary_address($row, $year, $section);
        if (!isset($summary[$address])) {
            $summary[$address] = 0;
        }
        $summary[$address]++;
    }
    ksort($summary, SORT_NATURAL | SORT_FLAG_CASE);
    return $summary;
}

function ms_matches_filters(array $row, string $filterText, string $filterState, array $tipi, array $stati): bool
{
    if ($filterState !== '' && (string)($row['stato_pratica'] ?? '') !== $filterState) {
        return false;
    }
    if ($filterText === '') {
        return true;
    }
    $needle = ms_lower($filterText);
    return strpos(ms_filter_blob($row, $tipi, $stati), $needle) !== false;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Entrate e uscite studenti</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <style>
        .ms-topbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 10px;
            align-items: center;
            margin-bottom: 12px;
        }
        .ms-tabs {
            margin-bottom: 10px;
        }
        .ms-year-tabs {
            margin-bottom: 12px;
        }
        .ms-filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-end;
            padding: 12px;
            margin-bottom: 12px;
            border: 1px solid #d8dee8;
            border-radius: 4px;
            background: #f8fafc;
        }
        .ms-filter-field {
            min-width: 210px;
            flex: 1 1 220px;
        }
        .ms-filter-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }
        .ms-filter-count {
            color: #60718a;
            font-size: 12px;
            margin-left: auto;
        }
        .ms-address-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 12px;
            align-items: center;
            padding: 10px 12px;
            margin-bottom: 12px;
            border: 1px solid #cfe2f3;
            border-radius: 4px;
            background: #f4f8fc;
        }
        .ms-address-summary-title {
            font-weight: 700;
            color: #17202f;
        }
        .ms-address-summary-items {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }
        .ms-address-chip {
            display: inline-flex;
            gap: 7px;
            align-items: center;
            padding: 4px 8px;
            border: 1px solid #b9d6ee;
            border-radius: 4px;
            background: #fff;
            color: #17202f;
        }
        .ms-address-chip strong {
            color: #0f4c81;
        }
        .ms-address-empty {
            color: #60718a;
            font-size: 12px;
        }
        .ms-real-summary {
            margin: 10px 0 14px;
            border: 1px solid #c7d8ea;
            border-radius: 4px;
            background: #f8fbff;
            overflow: hidden;
        }
        .ms-real-summary-head {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            justify-content: space-between;
            padding: 10px 12px;
            border-bottom: 1px solid #dbe7f3;
            background: #eef6ff;
        }
        .ms-real-summary-title {
            color: #102a43;
            font-weight: 800;
        }
        .ms-real-summary-note {
            color: #60718a;
            font-size: 12px;
        }
        .ms-real-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(170px, 1fr));
            gap: 0;
        }
        .ms-real-card {
            padding: 12px;
            border-right: 1px solid #dbe7f3;
            background: #fff;
        }
        .ms-real-card:last-child {
            border-right: 0;
        }
        .ms-real-card-title {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            color: #102a43;
            font-weight: 800;
            margin-bottom: 8px;
        }
        .ms-real-total {
            color: #0f4c81;
            font-size: 24px;
            font-weight: 900;
            line-height: 1;
        }
        .ms-real-line {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            padding: 3px 0;
            color: #42536a;
            font-size: 12px;
            border-top: 1px solid #edf2f7;
        }
        .ms-real-line strong {
            color: #17202f;
        }
        .ms-real-negative strong {
            color: #b91c1c;
        }
        .ms-table-wrap {
            border: 1px solid #d8dee8;
            border-radius: 4px;
            background: #fff;
            overflow-x: auto;
        }
        .ms-table {
            margin-bottom: 0;
            min-width: 980px;
        }
        .ms-table th {
            background: #f8fafc;
            color: #17202f;
            white-space: nowrap;
        }
        .ms-name {
            font-weight: 700;
            color: #102a43;
        }
        .ms-muted {
            color: #60718a;
            font-size: 12px;
        }
        .ms-attachment-link {
            display: inline-block;
            margin-right: 7px;
            margin-bottom: 3px;
        }
        .ms-modal-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .ms-modal-main {
            border: 1px solid #dbe4ef;
            border-radius: 8px;
            background: #fff;
            padding: 12px;
        }
        #msPracticeModal .modal-dialog {
            width: calc(100vw - 28px);
            max-width: none;
            margin: 14px auto;
        }
        #msPracticeModal .modal-content {
            border: 0;
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            height: calc(100vh - 28px);
            overflow: hidden;
        }
        #msPracticeModal .modal-header {
            background: #f8fafc;
            border-bottom-color: #dbe4ef;
        }
        #msPracticeModal .modal-title {
            font-weight: 750;
            color: #17202f;
        }
        #msPracticeModal .modal-body {
            background: #f3f6fa;
            flex: 1 1 auto;
            overflow: auto;
            padding: 12px;
        }
        #msPracticeModal .modal-footer {
            background: #fff;
        }
        .ms-modal-layout {
            display: grid;
            grid-template-columns: minmax(680px, 1.18fr) minmax(720px, .82fr);
            gap: 14px;
            align-items: start;
        }
        .ms-history {
            border: 1px solid #d8dee8;
            border-radius: 8px;
            background: #fff;
            padding: 12px;
            max-height: 320px;
            overflow: auto;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }
        .ms-side-panel {
            display: grid;
            gap: 10px;
            position: sticky;
            top: 8px;
        }
        .ms-docs-panel {
            border: 1px solid #d8dee8;
            border-radius: 8px;
            background: #fff;
            padding: 12px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }
        .ms-panel-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 10px;
            color: #17202f;
            font-size: 16px;
            font-weight: 750;
        }
        .ms-panel-title .glyphicon {
            color: #2f80ed;
        }
        .ms-panel-title .btn {
            margin-left: auto;
        }
        .ms-history-add-box {
            border: 1px solid #dbe4ef;
            border-radius: 8px;
            background: #f8fafc;
            display: none;
            margin-bottom: 10px;
            padding: 10px;
        }
        .ms-history-add-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto;
            gap: 6px;
            align-items: end;
        }
        .ms-history-add-box textarea {
            margin-top: 6px;
        }
        .ms-doc-list {
            max-height: none;
            overflow: auto;
            margin-bottom: 10px;
        }
        .ms-doc-table {
            width: 100%;
            margin-bottom: 0;
            table-layout: fixed;
        }
        .ms-doc-table th {
            background: #f8fafc;
            border-bottom: 1px solid #dbe4ef;
            color: #17202f;
            font-size: 12px;
            font-weight: 750;
            padding: 6px;
        }
        .ms-doc-table td {
            border-top: 1px solid #edf2f7;
            padding: 6px;
            vertical-align: middle;
        }
        .ms-doc-table .ms-doc-name {
            color: #17202f;
            font-weight: 600;
        }
        .ms-doc-table .ms-doc-file {
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .ms-doc-table .ms-doc-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 3px;
        }
        .ms-doc-inline-upload {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 4px;
        }
        .ms-doc-inline-upload input[type="file"] {
            flex: 1 1 190px;
            min-width: 0;
        }
        .ms-doc-upload-tools {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin: 0 0 10px;
        }
        .ms-doc-upload-status {
            color: #475569;
            font-size: 12px;
            font-weight: 700;
        }
        .ms-doc-extra-title {
            color: #60718a;
            font-size: 12px;
            font-weight: 700;
            margin: 8px 0 4px;
            text-transform: uppercase;
        }
        .ms-doc-empty {
            color: #9aa6b2;
            font-size: 12px;
        }
        .ms-wait-overlay {
            position: fixed;
            z-index: 3000;
            inset: 0;
            background: rgba(15, 23, 42, .28);
            display: none;
            align-items: center;
            justify-content: center;
        }
        .ms-wait-overlay.is-visible {
            display: flex;
        }
        .ms-wait-box {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, .22);
            min-width: 280px;
            padding: 22px 26px;
            text-align: center;
        }
        .ms-wait-spinner {
            animation: msSpin .8s linear infinite;
            border: 4px solid #dbeafe;
            border-top-color: #2f80ed;
            border-radius: 999px;
            height: 34px;
            margin: 0 auto 12px;
            width: 34px;
        }
        @keyframes msSpin {
            to { transform: rotate(360deg); }
        }
        .ms-history-event {
            border: 1px solid #dbe4ef;
            border-left: 5px solid #2f80ed;
            border-radius: 4px;
            background: #fff;
            padding: 8px 10px;
            margin-bottom: 8px;
        }
        .ms-history-head {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            flex-wrap: wrap;
            font-weight: 700;
        }
        .ms-history-meta { color: #60718a; font-size: 12px; margin-top: 3px; }
        .ms-history-note { margin-top: 6px; white-space: pre-wrap; }
        .ms-history-attachment-link {
            max-width: 100%;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
            text-align: left;
        }
        .ms-history-attachment-row {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            align-items: flex-start;
            margin-top: 4px;
        }
        .ms-history-attachment-row .ms-history-attachment-link {
            flex: 1 1 220px;
        }
        .ms-modal-wide {
            grid-column: span 2;
        }
        .ms-section-title {
            grid-column: span 2;
            margin: 6px 0 -2px;
            padding: 7px 9px;
            border-left: 4px solid #2f80ed;
            background: #f4f8fc;
            color: #17202f;
            font-weight: 700;
        }
        .ms-section-title .ms-section-toggle {
            float: right;
            margin-top: -2px;
        }
        .ms-section-title.ms-nullosta {
            border-left-color: #b7791f;
            background: #fff8ec;
        }
        .ms-subject-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 8px;
        }
        .ms-subject-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #f8fafc;
            color: #17202f;
        }
        .ms-subject-chip button {
            border: 0;
            background: transparent;
            padding: 0;
            color: #8a1f1f;
            font-weight: 700;
            line-height: 1;
        }
        .ms-subject-add {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 8px;
        }
        .ms-empty {
            padding: 28px;
            text-align: center;
            color: #60718a;
        }
        .ms-auto-dismiss {
            transition: opacity .35s ease, max-height .35s ease, margin .35s ease, padding .35s ease;
            overflow: hidden;
            max-height: 180px;
        }
        .ms-auto-dismiss.is-hiding {
            opacity: 0;
            max-height: 0;
            margin-top: 0;
            margin-bottom: 0;
            padding-top: 0;
            padding-bottom: 0;
            border-width: 0;
        }
        @media (max-width: 800px) {
            #msPracticeModal .modal-dialog {
                width: auto;
                max-width: none;
            }
            .ms-modal-grid {
                grid-template-columns: 1fr;
            }
            .ms-modal-layout {
                grid-template-columns: 1fr;
            }
            .ms-history {
                max-height: none;
            }
            .ms-history-add-grid {
                grid-template-columns: 1fr;
            }
            .ms-real-grid {
                grid-template-columns: 1fr;
            }
            .ms-real-card {
                border-right: 0;
                border-bottom: 1px solid #dbe7f3;
            }
            .ms-side-panel {
                position: static;
            }
            .ms-modal-wide {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>
<?php require_once '../common/header-didattica.php'; ?>
<div class="container-fluid">
    <div class="panel panel-lightblue4">
        <div class="panel-heading"><span class="glyphicon glyphicon-transfer"></span>&emsp;Entrate e uscite studenti</div>
        <div class="panel-body">
            <?php if ($message !== ''): ?>
                <div class="alert alert-success ms-auto-dismiss">
                    <?php echo studentiMovimentiH($message); ?>
                    <?php if (is_array($syncResult)): ?>
                        Letti <?php echo intval($syncResult['read'] ?? 0); ?> record,
                        creati <?php echo intval($syncResult['created'] ?? 0); ?>,
                        gia presenti <?php echo intval($syncResult['existing'] ?? 0); ?>,
                        aggiornati <?php echo intval(($syncResult['updated'] ?? 0) + ($syncResult['updated_existing'] ?? 0)); ?>.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo studentiMovimentiH($error); ?></div><?php endif; ?>
            <?php if (is_array($syncResult)): ?>
                <div class="alert alert-warning ms-auto-dismiss">
                    <strong>Controllo tabelloni:</strong>
                    trovati <?php echo intval($syncResult['read'] ?? 0); ?> bocciati/non ammessi.
                    <?php $byYear = (array)($syncResult['by_year'] ?? []); ?>
                    Prime <?php echo intval($byYear[1] ?? 0); ?>,
                    Seconde <?php echo intval($byYear[2] ?? 0); ?>,
                    Terze <?php echo intval($byYear[3] ?? 0); ?>,
                    Quarte <?php echo intval($byYear[4] ?? 0); ?>,
                    Quinte <?php echo intval($byYear[5] ?? 0); ?>.
                    <?php if (intval($syncResult['without_gestore_id'] ?? 0) > 0): ?>
                        <br>
                        <strong><?php echo intval($syncResult['without_gestore_id']); ?> righe non importate</strong>
                        perche non hanno l'aggancio allo studente GestOre.
                        <?php if (!empty($syncResult['without_gestore_examples'])): ?>
                            Esempi: <?php echo studentiMovimentiH(implode(', ', (array)$syncResult['without_gestore_examples'])); ?>.
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="ms-topbar">
                <div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="msOpenNew('uscita')">
                        <span class="glyphicon glyphicon-plus"></span>&ensp;Aggiungi uscita
                    </button>
                    <button type="button" class="btn btn-success btn-sm" onclick="msOpenNew('entrata')">
                        <span class="glyphicon glyphicon-plus"></span>&ensp;Aggiungi entrata
                    </button>
                </div>
                <form method="post" style="margin:0;" onsubmit="return confirm('Importare nelle uscite tutti i bocciati presenti nei tabelloni finali?');">
                    <input type="hidden" name="action" value="sync_bocciati">
                    <button type="submit" class="btn btn-warning btn-sm">
                        <span class="glyphicon glyphicon-refresh"></span>&ensp;Aggiorna bocciati da tabelloni
                    </button>
                </form>
                <form method="post" style="margin:0;" onsubmit="return confirm('Sincronizzare nelle uscite i cambi scuola registrati nelle iscrizioni prime/terze?');">
                    <input type="hidden" name="action" value="sync_iscrizioni">
                    <button type="submit" class="btn btn-info btn-sm">
                        <span class="glyphicon glyphicon-transfer"></span>&ensp;Aggiorna cambi scuola da iscrizioni
                    </button>
                </form>
                <div class="btn-group">
                    <a class="btn btn-success btn-sm" href="movimentiStudentiFormazioneExport.php?format=xls">
                        <span class="glyphicon glyphicon-list-alt"></span>&ensp;Export formazione XLS
                    </a>
                    <a class="btn btn-danger btn-sm" href="movimentiStudentiFormazioneExport.php?format=pdf">
                        <span class="glyphicon glyphicon-file"></span>&ensp;PDF
                    </a>
                </div>
            </div>

            <?php if (!empty($realEnrollmentSummary)): ?>
            <div class="ms-real-summary">
                <div class="ms-real-summary-head">
                    <div class="ms-real-summary-title">
                        <span class="glyphicon glyphicon-education"></span>
                        Iscritti reali ad oggi
                    </div>
                    <div class="ms-real-summary-note">
                        Prime/terze: pratiche iniziali + entrate nuove - uscite/cambi scuola. Seconde/quarte/quinte: iscritti attuali anno precedente + entrate - uscite. I doppi bocciati sono conteggiati tra gli usciti anche se il cambio scuola non e ancora concluso.
                    </div>
                </div>
                <div class="ms-real-grid">
                    <?php foreach ([1, 2, 3, 4, 5] as $summaryYear): ?>
                        <?php $summaryRow = $realEnrollmentSummary[$summaryYear] ?? []; ?>
                        <div class="ms-real-card">
                            <div class="ms-real-card-title">
                                <span><?php echo studentiMovimentiH($summaryRow['label'] ?? ms_year_label($summaryYear)); ?></span>
                                <span class="ms-real-total"><?php echo intval($summaryRow['totale_reale'] ?? 0); ?></span>
                            </div>
                            <div class="ms-real-line">
                                <span><?php echo studentiMovimentiH($summaryRow['base_label'] ?? 'Base'); ?></span>
                                <strong><?php echo intval($summaryRow['base'] ?? 0); ?></strong>
                            </div>
                            <div class="ms-real-line">
                                <span>In arrivo</span>
                                <strong>+<?php echo intval($summaryRow['in_arrivo'] ?? 0); ?></strong>
                            </div>
                            <div class="ms-real-line ms-real-negative">
                                <span>Usciti / cambio scuola</span>
                                <strong>-<?php echo intval($summaryRow['usciti'] ?? 0); ?></strong>
                            </div>
                            <div class="ms-real-line">
                                <span>di cui doppi bocciati</span>
                                <strong><?php echo intval($summaryRow['doppi_bocciati'] ?? 0); ?></strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <ul class="nav nav-tabs ms-tabs">
                <li class="<?php echo ms_active($activeSection, 'uscite'); ?>">
                    <a href="movimentiStudenti.php?sezione=uscite">Uscite</a>
                </li>
                <li class="<?php echo ms_active($activeSection, 'entrate'); ?>">
                    <a href="movimentiStudenti.php?sezione=entrate">Entrate</a>
                </li>
            </ul>

            <ul class="nav nav-pills ms-year-tabs">
                <?php foreach ([1, 2, 3, 4, 5] as $year): ?>
                    <li class="<?php echo ms_active($activeYear, $year); ?>">
                        <a href="movimentiStudenti.php?sezione=<?php echo studentiMovimentiH($activeSection); ?>&anno=<?php echo $year; ?>">
                            <?php echo studentiMovimentiH(ms_year_label($year)); ?>
                            <span class="badge"><?php echo count($grouped[$activeSection][$year] ?? []); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php
            $rowsAll = $grouped[$activeSection][$activeYear] ?? [];
            $isGlobalFilter = $filterText !== '' || $filterState !== '';
            $rows = $pratiche;
            $showAddressColumn = !$isGlobalFilter && ms_uscita_has_address_column($activeSection, $activeYear);
            $addressSummary = ms_address_summary($rowsAll, $activeYear, $activeSection);
            $exportBase = 'movimentiStudentiExport.php?sezione=' . rawurlencode($activeSection)
                . '&anno=' . intval($activeYear)
                . '&q=' . rawurlencode($filterText)
                . '&stato=' . rawurlencode($filterState);
            ?>
            <form method="get" class="ms-filter-bar" id="msInstantFilterForm" onsubmit="return false;">
                <input type="hidden" name="sezione" value="<?php echo studentiMovimentiH($activeSection); ?>">
                <input type="hidden" name="anno" value="<?php echo intval($activeYear); ?>">
                <div class="ms-filter-field">
                    <label for="ms_filter_q">Filtro testo</label>
                    <input type="text" class="form-control" id="ms_filter_q" name="q" value="<?php echo studentiMovimentiH($filterText); ?>" placeholder="Cerca in tutti gli anni, entrate e uscite...">
                </div>
                <div class="ms-filter-field">
                    <label for="ms_filter_stato">Stato</label>
                    <select class="form-control" id="ms_filter_stato" name="stato">
                        <option value="">Tutti gli stati</option>
                        <?php foreach ($stati as $stateKey => $stateLabel): ?>
                            <option value="<?php echo studentiMovimentiH($stateKey); ?>" <?php echo ms_selected($filterState, $stateKey); ?>>
                                <?php echo studentiMovimentiH($stateLabel); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ms-filter-actions">
                    <button type="button" class="btn btn-default" id="ms_filter_clear">Pulisci</button>
                    <a class="btn btn-success" id="ms_export_xls" href="<?php echo studentiMovimentiH($exportBase . '&format=xls'); ?>">
                        <span class="glyphicon glyphicon-list-alt"></span> XLS
                    </a>
                    <a class="btn btn-danger" id="ms_export_pdf" href="<?php echo studentiMovimentiH($exportBase . '&format=pdf'); ?>">
                        <span class="glyphicon glyphicon-file"></span> PDF
                    </a>
                </div>
                <div class="ms-filter-count" id="ms_filter_count">
                    <?php echo count($rows); ?> righe<?php echo $isGlobalFilter ? ' trovate in tutte le pratiche' : ''; ?>
                </div>
            </form>

            <?php if ($isGlobalFilter): ?>
                <div class="alert alert-info" style="margin-top:-6px;">
                    Ricerca estesa a tutti gli anni, entrate e uscite. Pulisci il filtro per tornare alla vista per anno.
                </div>
            <?php endif; ?>

            <?php if ($showAddressColumn): ?>
                <div class="ms-address-summary" id="ms_address_summary">
                    <div class="ms-address-summary-title">
                        <?php echo $activeYear === 2 ? 'Bocciati / mancati iscritti per indirizzo' : 'Totali uscite per indirizzo'; ?>
                    </div>
                    <div class="ms-address-summary-items" id="ms_address_summary_items">
                        <?php if (empty($addressSummary)): ?>
                            <span class="ms-address-empty">Nessun dato da riepilogare</span>
                        <?php else: ?>
                            <?php foreach ($addressSummary as $address => $total): ?>
                                <span class="ms-address-chip">
                                    <?php echo studentiMovimentiH($address); ?>
                                    <strong><?php echo intval($total); ?></strong>
                                </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="ms-table-wrap">
                <table class="table table-striped table-hover ms-table">
                    <thead>
                    <tr>
                        <th>Studente</th>
                        <th>Classe</th>
                        <th>Anno / sezione</th>
                        <th>Tipo</th>
                        <th>Stato</th>
                        <?php if ($showAddressColumn): ?>
                            <th><?php echo $activeYear === 2 ? 'Indirizzo pre-iscrizione' : 'Indirizzo'; ?></th>
                        <?php endif; ?>
                        <th>Scuola</th>
                        <th>Esami integrativi</th>
                        <th>Carenze</th>
                        <th>Allegati</th>
                        <th>Aggiornata</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $id = intval($row['id'] ?? 0); ?>
                        <tr class="ms-data-row"
                            data-filter-text="<?php echo ms_data_attr(ms_filter_blob($row, $tipi, $stati)); ?>"
                            data-filter-state="<?php echo ms_data_attr($row['stato_pratica'] ?? ''); ?>"
                            data-filter-section="<?php echo ms_data_attr(($row['tipo_pratica'] ?? '') === 'entrata' ? 'entrate' : 'uscite'); ?>"
                            data-filter-year="<?php echo intval($row['anno_corso'] ?? 0); ?>"
                            data-summary-address="<?php echo ms_data_attr(ms_row_summary_address($row, $activeYear, $activeSection)); ?>"
                            data-summary-include="<?php echo ms_row_counts_in_address_summary($row, $activeYear, $activeSection) ? 1 : 0; ?>">
                            <td>
                                <div class="ms-name"><?php echo studentiMovimentiH(studentiMovimentiUpperName(ms_practice_name($row))); ?></div>
                                <div class="ms-muted"><?php echo studentiMovimentiH($row['codice_fiscale'] ?: $row['studente_cf'] ?: ''); ?></div>
                            </td>
                            <td>
                                <?php $classeBase = trim((string)($row['classe_origine'] ?: $row['classe_corrente'] ?: '')); ?>
                                <?php if ($classeBase !== ''): ?>
                                    <strong><?php echo studentiMovimentiH($classeBase); ?></strong>
                                <?php endif; ?>
                                <?php if (($row['classe_richiesta'] ?? '') !== ''): ?>
                                    <div class="ms-muted">richiesta <?php echo studentiMovimentiH($row['classe_richiesta']); ?></div>
                                <?php elseif ($classeBase === ''): ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo studentiMovimentiH(ms_year_label(intval($row['anno_corso'] ?? 0))); ?></strong>
                                <div class="ms-muted"><?php echo (($row['tipo_pratica'] ?? '') === 'entrata') ? 'Entrata' : 'Uscita'; ?></div>
                            </td>
                            <td><?php echo studentiMovimentiH($tipi[$row['tipo_pratica']] ?? $row['tipo_pratica']); ?></td>
                            <td>
                                <span class="label label-<?php echo ms_label_class((string)$row['stato_pratica']); ?>">
                                    <?php echo studentiMovimentiH($stati[$row['stato_pratica']] ?? $row['stato_pratica']); ?>
                                </span>
                                <?php if (!empty($row['doppio_bocciato'])): ?>
                                    <div><span class="label label-danger">Doppio bocciato</span></div>
                                <?php endif; ?>
                                <?php if (!empty($row['doppio_bocciato_non_consecutivo'])): ?>
                                    <div><span class="label label-info">Doppio non consecutivo</span></div>
                                <?php endif; ?>
                                <?php if (!empty($row['bocciato_altra_scuola'])): ?>
                                    <div><span class="label label-warning">Bocciato altra scuola</span></div>
                                <?php endif; ?>
                            </td>
                            <?php if ($showAddressColumn): ?>
                                <td>
                                    <?php $addressLabel = ms_row_address_label($row, $activeYear, $activeSection); ?>
                                    <?php if ($addressLabel !== ''): ?>
                                        <strong><?php echo studentiMovimentiH($addressLabel); ?></strong>
                                        <?php if ($activeYear === 2 && intval($row['preiscrizione_terze_id'] ?? 0) > 0): ?>
                                            <div class="ms-muted">Pre-iscrizione terze #<?php echo intval($row['preiscrizione_terze_id']); ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td>
                                <?php $rowSection = ($row['tipo_pratica'] ?? '') === 'entrata' ? 'entrate' : 'uscite'; ?>
                                <?php if ($rowSection === 'entrate'): ?>
                                    <?php echo studentiMovimentiH($row['scuola_provenienza'] ?: '-'); ?>
                                <?php else: ?>
                                    <?php echo studentiMovimentiH($row['scuola_destinazione'] ?: '-'); ?>
                                    <?php if (($row['indirizzo_destinazione'] ?? '') !== ''): ?>
                                        <div class="ms-muted"><?php echo studentiMovimentiH($row['indirizzo_destinazione']); ?></div>
                                    <?php endif; ?>
                                    <?php if (intval($row['id_indirizzo_gestore'] ?? 0) > 0 && trim((string)($row['indirizzo_gestore_nome'] ?? '')) !== ''): ?>
                                        <div class="ms-muted">GestOre: <?php echo studentiMovimentiH($row['indirizzo_gestore_nome']); ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo intval($row['esami_integrativi'] ?? 0) ? '<span class="label label-warning">Si</span>' : '<span class="text-muted">No</span>'; ?></td>
                            <td><?php echo intval($row['carenze_presenti'] ?? 0) ? '<span class="label label-warning">Si</span>' : '<span class="text-muted">No</span>'; ?></td>
                            <td>
                                <?php if (empty($allegati[$id])): ?>
                                    <span class="text-muted">nessuno</span>
                                <?php else: ?>
                                    <?php foreach ($allegati[$id] as $allegato): ?>
                                        <a class="ms-attachment-link" target="_blank" href="movimentiStudentiAllegato.php?id=<?php echo intval($allegato['id'] ?? 0); ?>">
                                            <span class="glyphicon glyphicon-file"></span>
                                            <?php echo studentiMovimentiH($allegato['nome_file']); ?>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo studentiMovimentiH(studentiMovimentiFormatDateTimeIt((string)($row['updated_at'] ?? ''))); ?></td>
                            <td class="text-right">
                                <button type="button"
                                        class="btn btn-default btn-xs ms-edit"
                                        data-id="<?php echo $id; ?>"
                                        data-fonte="<?php echo ms_data_attr($row['fonte'] ?? 'manuale'); ?>"
                                        data-id_pratica_iscrizione="<?php echo intval($row['id_pratica_iscrizione'] ?? 0); ?>"
                                        data-id_cambio_scuola_iscrizione="<?php echo intval($row['id_cambio_scuola_iscrizione'] ?? 0); ?>"
                                        data-tipo_pratica="<?php echo ms_data_attr($row['tipo_pratica'] ?? ''); ?>"
                                        data-stato_pratica="<?php echo ms_data_attr($row['stato_pratica'] ?? ''); ?>"
                                        data-id_studente="<?php echo intval($row['id_studente'] ?? 0); ?>"
                                        data-cognome="<?php echo ms_data_attr($row['cognome'] ?: $row['studente_cognome']); ?>"
                                        data-nome="<?php echo ms_data_attr($row['nome'] ?: $row['studente_nome']); ?>"
                                        data-codice_fiscale="<?php echo ms_data_attr($row['codice_fiscale'] ?: $row['studente_cf']); ?>"
                                        data-anno_corso="<?php echo intval($row['anno_corso'] ?? 0); ?>"
                                        data-classe_origine="<?php echo ms_data_attr($row['classe_origine'] ?: $row['classe_corrente']); ?>"
                                        data-classe_richiesta="<?php echo ms_data_attr($row['classe_richiesta'] ?? ''); ?>"
                                        data-id_istituto_provenienza="<?php echo intval($row['id_istituto_provenienza'] ?? 0); ?>"
                                        data-scuola_provenienza="<?php echo ms_data_attr($row['scuola_provenienza'] ?? ''); ?>"
                                        data-indirizzo_provenienza="<?php echo ms_data_attr($row['indirizzo_provenienza'] ?? ''); ?>"
                                        data-bocciato_altra_scuola="<?php echo intval($row['bocciato_altra_scuola'] ?? 0); ?>"
                                        data-responsabile_1_tipo="<?php echo ms_data_attr($row['responsabile_1_tipo'] ?? ''); ?>"
                                        data-responsabile_1_cognome="<?php echo ms_data_attr($row['responsabile_1_cognome'] ?? ''); ?>"
                                        data-responsabile_1_nome="<?php echo ms_data_attr($row['responsabile_1_nome'] ?? ''); ?>"
                                        data-responsabile_1_codice_fiscale="<?php echo ms_data_attr($row['responsabile_1_codice_fiscale'] ?? ''); ?>"
                                        data-email_genitore_1="<?php echo ms_data_attr($row['email_genitore_1'] ?? ''); ?>"
                                        data-telefono_genitore_1="<?php echo ms_data_attr($row['telefono_genitore_1'] ?? ''); ?>"
                                        data-responsabile_2_tipo="<?php echo ms_data_attr($row['responsabile_2_tipo'] ?? ''); ?>"
                                        data-responsabile_2_cognome="<?php echo ms_data_attr($row['responsabile_2_cognome'] ?? ''); ?>"
                                        data-responsabile_2_nome="<?php echo ms_data_attr($row['responsabile_2_nome'] ?? ''); ?>"
                                        data-responsabile_2_codice_fiscale="<?php echo ms_data_attr($row['responsabile_2_codice_fiscale'] ?? ''); ?>"
                                        data-email_genitore_2="<?php echo ms_data_attr($row['email_genitore_2'] ?? ''); ?>"
                                        data-telefono_genitore_2="<?php echo ms_data_attr($row['telefono_genitore_2'] ?? ''); ?>"
                                        data-id_istituto_destinazione="<?php echo intval($row['id_istituto_destinazione'] ?? 0); ?>"
                                        data-scuola_destinazione="<?php echo ms_data_attr($row['scuola_destinazione'] ?? ''); ?>"
                                        data-indirizzo_destinazione="<?php echo ms_data_attr($row['indirizzo_destinazione'] ?? ''); ?>"
                                        data-id_indirizzo_gestore="<?php echo intval($row['id_indirizzo_gestore'] ?? 0); ?>"
                                        data-doppio_bocciato="<?php echo intval($row['doppio_bocciato'] ?? 0); ?>"
                                        data-doppio_bocciato_non_consecutivo="<?php echo intval($row['doppio_bocciato_non_consecutivo'] ?? 0); ?>"
                                        data-esami_integrativi="<?php echo intval($row['esami_integrativi'] ?? 0); ?>"
                                        data-esami_integrativi_note="<?php echo ms_data_attr($row['esami_integrativi_note'] ?? ''); ?>"
                                        data-carenze_presenti="<?php echo intval($row['carenze_presenti'] ?? 0); ?>"
                                        data-carenze_note="<?php echo ms_data_attr($row['carenze_note'] ?? ''); ?>"
                                        data-note="<?php echo ms_data_attr($row['note'] ?? ''); ?>">
                                    Dettaglio
                                </button>
                                <?php $iscrizioneUrl = ms_iscrizione_pratica_url($row); ?>
                                <?php if ($rowSection === 'entrate' && $iscrizioneUrl !== ''): ?>
                                    <a class="btn btn-success btn-xs" href="<?php echo studentiMovimentiH($iscrizioneUrl); ?>">
                                        <span class="glyphicon glyphicon-folder-open"></span> Pratica iscrizione
                                    </a>
                                <?php elseif ($rowSection === 'entrate' && in_array(intval($row['anno_corso'] ?? 0), [1, 3], true)): ?>
                                    <span class="label label-warning">Pratica iscrizione mancante</span>
                                <?php endif; ?>
                                <?php if ($canOpenColloqui && !empty($colloquiCounts[$id])): ?>
                                    <a class="btn btn-info btn-xs" href="colloquiGenitori.php?movimento=<?php echo $id; ?>">Colloqui <?php echo intval($colloquiCounts[$id]); ?></a>
                                <?php endif; ?>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Eliminare definitivamente questa pratica?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                                    <button type="submit" class="btn btn-danger btn-xs">Elimina</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php $emptyColspan = $showAddressColumn ? 12 : 11; ?>
                    <?php if (empty($rows)): ?>
                        <tr id="ms_empty_row"><td colspan="<?php echo $emptyColspan; ?>"><div class="ms-empty">Nessuna pratica in questa sezione.</div></td></tr>
                    <?php else: ?>
                        <tr id="ms_empty_row" style="display:none;"><td colspan="<?php echo $emptyColspan; ?>"><div class="ms-empty">Nessuna pratica trovata con questi filtri.</div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="msPracticeModal" tabindex="-1" role="dialog" aria-labelledby="msPracticeTitle">
    <div class="modal-dialog modal-lg" role="document">
        <form method="post" enctype="multipart/form-data" class="modal-content" id="msPracticeForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="ms_id" value="">
            <input type="hidden" name="fonte" id="ms_fonte" value="manuale">
            <input type="hidden" name="id_pratica_iscrizione" id="ms_id_pratica_iscrizione" value="">
            <input type="hidden" name="id_cambio_scuola_iscrizione" id="ms_id_cambio_scuola_iscrizione" value="">
            <input type="hidden" name="note" id="ms_note" value="">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="msPracticeTitle">Pratica studente</h4>
            </div>
            <div class="modal-body">
                <div class="ms-modal-layout">
                    <div class="ms-modal-main">
                <div class="ms-modal-grid">
                    <div class="form-group">
                        <label>Tipo pratica</label>
                        <select name="tipo_pratica" id="ms_tipo_pratica" class="form-control input-sm">
                            <?php foreach ($tipi as $key => $label): ?>
                                <option value="<?php echo studentiMovimentiH($key); ?>"><?php echo studentiMovimentiH($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Stato</label>
                        <select name="stato_pratica" id="ms_stato_pratica" class="form-control input-sm">
                            <?php foreach ($stati as $key => $label): ?>
                                <option value="<?php echo studentiMovimentiH($key); ?>" data-state-key="<?php echo studentiMovimentiH($key); ?>"><?php echo studentiMovimentiH($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group ms-only-uscita">
                        <label style="display:block;">Doppio bocciato</label>
                        <label class="checkbox-inline">
                            <input type="checkbox" name="doppio_bocciato" id="ms_doppio_bocciato" value="1">
                            deve cambiare scuola o ritirarsi
                        </label>
                        <label class="checkbox-inline">
                            <input type="checkbox" name="doppio_bocciato_non_consecutivo" id="ms_doppio_bocciato_non_consecutivo" value="1">
                            non consecutivo, puo reiscriversi
                        </label>
                    </div>
                    <div class="form-group ms-modal-wide">
                        <label>Studente gia presente in GestOre</label>
                        <select name="id_studente" id="ms_id_studente" class="form-control input-sm">
                            <option value="">Studente provvisorio / non ancora presente</option>
                            <?php foreach ($currentStudents as $student): ?>
                                <option value="<?php echo intval($student['id']); ?>"
                                        data-cognome="<?php echo ms_data_attr($student['cognome'] ?? ''); ?>"
                                        data-nome="<?php echo ms_data_attr($student['nome'] ?? ''); ?>"
                                        data-cf="<?php echo ms_data_attr($student['codice_fiscale'] ?? ''); ?>"
                                        data-classe="<?php echo ms_data_attr($student['classe'] ?? ''); ?>"
                                        data-anno="<?php echo intval(studentiMovimentiClassYear((string)($student['classe'] ?? '')) ?? 0); ?>">
                                    <?php echo studentiMovimentiH(($student['classe'] ?? '') . ' - ' . ($student['cognome'] ?? '') . ' ' . ($student['nome'] ?? '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Cognome</label>
                        <input type="text" name="cognome" id="ms_cognome" class="form-control input-sm">
                    </div>
                    <div class="form-group">
                        <label>Nome</label>
                        <input type="text" name="nome" id="ms_nome" class="form-control input-sm">
                    </div>
                    <div class="form-group">
                        <label>Codice fiscale</label>
                        <input type="text" name="codice_fiscale" id="ms_codice_fiscale" class="form-control input-sm">
                    </div>
                    <div class="form-group ms-field-year">
                        <label>Anno</label>
                        <select name="anno_corso" id="ms_anno_corso" class="form-control input-sm">
                            <option value="">Seleziona anno</option>
                            <?php foreach ([1, 2, 3, 4, 5] as $year): ?>
                                <option value="<?php echo $year; ?>"><?php echo studentiMovimentiH(ms_year_label($year)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group ms-field-origin-class">
                        <label>Classe origine</label>
                        <input type="text" name="classe_origine" id="ms_classe_origine" class="form-control input-sm">
                    </div>
                    <div class="form-group ms-field-requested-class">
                        <label>Classe richiesta</label>
                        <input type="text" name="classe_richiesta" id="ms_classe_richiesta" class="form-control input-sm">
                    </div>
                    <div class="form-group ms-address-gestore"></div>
                    <div class="form-group ms-address-gestore">
                        <label>Indirizzo GestOre</label>
                        <select name="id_indirizzo_gestore" id="ms_id_indirizzo_gestore" class="form-control input-sm">
                            <option value="">Da ricavare dal testo</option>
                            <?php foreach ($indirizziGestore as $indirizzoRow): ?>
                                <option value="<?php echo intval($indirizzoRow['id']); ?>"><?php echo studentiMovimentiH($indirizzoRow['nome'] ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="help-block">Usato nella formazione classi per filtrare bocciati, entrate e reiscrizioni.</span>
                    </div>
                    <div class="form-group ms-field-source-school">
                        <label>Scuola provenienza</label>
                        <input type="hidden" name="scuola_provenienza" id="ms_scuola_provenienza">
                        <select name="id_istituto_provenienza" id="ms_id_istituto_provenienza" class="form-control input-sm">
                            <option value="">Seleziona istituto</option>
                            <option value="__altro__">ALTRO - scuola non presente</option>
                            <?php foreach ($istitutiScuole as $istituto): ?>
                                <option value="<?php echo intval($istituto['id']); ?>"><?php echo studentiMovimentiH($istituto['nome'] ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" id="ms_scuola_provenienza_altro" class="form-control input-sm" style="display:none;margin-top:6px;" placeholder="Scrivi scuola provenienza">
                        <div id="ms_scuola_provenienza_libera" class="help-block" style="display:none;"></div>
                    </div>
                    <input type="hidden" name="indirizzo_provenienza" id="ms_indirizzo_provenienza" value="">
                    <div class="form-group ms-only-entrata">
                        <label style="display:block;">Esito anno precedente</label>
                        <label class="checkbox-inline">
                            <input type="checkbox" name="bocciato_altra_scuola" id="ms_bocciato_altra_scuola" value="1">
                            bocciato in altra scuola
                        </label>
                        <span class="help-block">Valore sincronizzato con domanda di iscrizione e colloquio di entrata.</span>
                    </div>
                    <div class="ms-section-title ms-only-entrata ms-parent-title">
                        Dati genitori
                        <button type="button" class="btn btn-default btn-xs ms-section-toggle" id="ms_toggle_parents" onclick="msToggleParents()">
                            Mostra dati genitori
                        </button>
                    </div>
                    <div class="form-group ms-only-entrata ms-parent-field">
                        <label>Responsabile 1 - tipo</label>
                        <input type="text" name="responsabile_1_tipo" id="ms_responsabile_1_tipo" class="form-control input-sm" placeholder="madre, padre, tutore...">
                    </div>
                    <div class="form-group ms-only-entrata ms-parent-field">
                        <label>Responsabile 1 - cognome</label>
                        <input type="text" name="responsabile_1_cognome" id="ms_responsabile_1_cognome" class="form-control input-sm">
                    </div>
                    <div class="form-group ms-only-entrata ms-parent-field">
                        <label>Responsabile 1 - nome</label>
                        <input type="text" name="responsabile_1_nome" id="ms_responsabile_1_nome" class="form-control input-sm">
                    </div>
                    <div class="form-group ms-only-entrata ms-parent-field">
                        <label>Responsabile 1 - codice fiscale</label>
                        <input type="text" name="responsabile_1_codice_fiscale" id="ms_responsabile_1_codice_fiscale" class="form-control input-sm">
                    </div>
                    <div class="form-group ms-only-entrata ms-parent-field">
                        <label>Email genitore 1</label>
                        <input type="email" name="email_genitore_1" id="ms_email_genitore_1" class="form-control input-sm">
                    </div>
                    <div class="form-group ms-only-entrata ms-parent-field">
                        <label>Telefono genitore 1</label>
                        <input type="text" name="telefono_genitore_1" id="ms_telefono_genitore_1" class="form-control input-sm">
                    </div>
                    <div class="form-group ms-only-entrata ms-parent-field">
                        <label>Responsabile 2 - tipo</label>
                        <input type="text" name="responsabile_2_tipo" id="ms_responsabile_2_tipo" class="form-control input-sm" placeholder="madre, padre, tutore...">
                    </div>
                    <div class="form-group ms-only-entrata ms-parent-field">
                        <label>Responsabile 2 - cognome</label>
                        <input type="text" name="responsabile_2_cognome" id="ms_responsabile_2_cognome" class="form-control input-sm">
                    </div>
                    <div class="form-group ms-only-entrata ms-parent-field">
                        <label>Responsabile 2 - nome</label>
                        <input type="text" name="responsabile_2_nome" id="ms_responsabile_2_nome" class="form-control input-sm">
                    </div>
                    <div class="form-group ms-only-entrata ms-parent-field">
                        <label>Responsabile 2 - codice fiscale</label>
                        <input type="text" name="responsabile_2_codice_fiscale" id="ms_responsabile_2_codice_fiscale" class="form-control input-sm">
                    </div>
                    <div class="form-group ms-only-entrata ms-parent-field">
                        <label>Email genitore 2</label>
                        <input type="email" name="email_genitore_2" id="ms_email_genitore_2" class="form-control input-sm">
                    </div>
                    <div class="form-group ms-only-entrata ms-parent-field">
                        <label>Telefono genitore 2</label>
                        <input type="text" name="telefono_genitore_2" id="ms_telefono_genitore_2" class="form-control input-sm">
                    </div>
                    <div class="form-group ms-field-destination-school">
                        <label>Scuola destinazione</label>
                        <input type="hidden" name="scuola_destinazione" id="ms_scuola_destinazione">
                        <select name="id_istituto_destinazione" id="ms_id_istituto_destinazione" class="form-control input-sm">
                            <option value="">Seleziona istituto</option>
                            <option value="__altro__">ALTRO - scuola non presente</option>
                            <?php foreach ($istitutiScuole as $istituto): ?>
                                <option value="<?php echo intval($istituto['id']); ?>"><?php echo studentiMovimentiH($istituto['nome'] ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" id="ms_scuola_destinazione_altro" class="form-control input-sm" style="display:none;margin-top:6px;" placeholder="Scrivi scuola destinazione">
                        <div id="ms_scuola_destinazione_libera" class="help-block" style="display:none;"></div>
                    </div>
                    <div class="form-group ms-field-destination-address">
                        <label>Indirizzo di studio di destinazione</label>
                        <input type="text" name="indirizzo_destinazione" id="ms_indirizzo_destinazione" class="form-control input-sm" placeholder="Es. informatica, liceo scientifico...">
                    </div>
                    <div class="ms-section-title ms-only-uscita">Colloqui</div>
                    <div class="form-group ms-only-uscita ms-modal-wide">
                        <label>Note colloqui / comunicazioni</label>
                        <textarea id="ms_note_uscita" class="form-control input-sm ms-note-field" rows="4"></textarea>
                    </div>
                    <div class="ms-section-title ms-nullosta ms-needs-nullosta">Nulla osta</div>
                    <div class="form-group ms-only-entrata">
                        <label>Esami integrativi</label>
                        <select name="esami_integrativi" id="ms_esami_integrativi" class="form-control input-sm">
                            <option value="0">No</option>
                            <option value="1">Si</option>
                        </select>
                    </div>
                    <div class="form-group ms-only-entrata ms-modal-wide" id="ms_esami_materie_box" style="display:none;">
                        <label>Materie esami integrativi</label>
                        <div id="ms_esami_materie_list" class="ms-subject-list"></div>
                        <div class="ms-subject-add">
                            <select id="ms_esami_materie" class="form-control input-sm">
                                <option value="">Aggiungi materia...</option>
                                <?php foreach ($materieGestore as $materia): ?>
                                    <option value="<?php echo studentiMovimentiH($materia['nome'] ?? ''); ?>"><?php echo studentiMovimentiH($materia['nome'] ?? ''); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-default btn-sm" id="ms_add_esame_materia">Aggiungi</button>
                        </div>
                        <input type="hidden" name="esami_integrativi_note" id="ms_esami_integrativi_note" value="">
                    </div>
                    <div class="form-group ms-only-entrata">
                        <label>Carenze da recuperare</label>
                        <select name="carenze_presenti" id="ms_carenze_presenti" class="form-control input-sm">
                            <option value="0">No</option>
                            <option value="1">Si</option>
                        </select>
                    </div>
                    <div class="form-group ms-only-entrata ms-modal-wide" id="ms_carenze_materie_box" style="display:none;">
                        <label>Materie carenze da recuperare</label>
                        <div id="ms_carenze_materie_list" class="ms-subject-list"></div>
                        <div class="ms-subject-add">
                            <select id="ms_carenze_materie" class="form-control input-sm">
                                <option value="">Aggiungi materia...</option>
                                <?php foreach ($materieGestore as $materia): ?>
                                    <option value="<?php echo studentiMovimentiH($materia['nome'] ?? ''); ?>"><?php echo studentiMovimentiH($materia['nome'] ?? ''); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-default btn-sm" id="ms_add_carenza_materia">Aggiungi</button>
                        </div>
                        <input type="hidden" name="carenze_note" id="ms_carenze_note" value="">
                    </div>
                    <div class="form-group ms-modal-wide ms-only-entrata">
                        <label>Note colloqui / comunicazioni</label>
                        <textarea id="ms_note_entrata" class="form-control input-sm ms-note-field" rows="5"></textarea>
                    </div>
                </div>
                    </div>
                    <div class="ms-side-panel">
                        <div class="ms-docs-panel" id="ms_docs_panel">
                            <h4 class="ms-panel-title"><span class="glyphicon glyphicon-folder-open"></span> Documenti pratica</h4>
                            <div id="ms_docs_content" class="ms-doc-list ms-muted">Nessun documento caricato.</div>
                        </div>
                        <div class="ms-history">
                            <h4 class="ms-panel-title">
                                <span class="glyphicon glyphicon-time"></span> Storico pratica
                                <button type="button" class="btn btn-success btn-xs" onclick="msToggleAddHistoryEvent(true)">
                                    <span class="glyphicon glyphicon-plus"></span> Evento
                                </button>
                            </h4>
                            <div class="ms-history-add-box" id="ms_history_add_box">
                                <div class="ms-history-add-grid">
                                    <div>
                                        <label>Descrizione evento</label>
                                        <input type="text" class="form-control input-sm" id="ms_new_history_desc" placeholder="Es. Contatto telefonico, nota segreteria...">
                                    </div>
                                    <div>
                                        <label>Allegato eventuale</label>
                                        <input type="file" class="form-control input-sm" id="ms_new_history_file" accept="application/pdf,image/jpeg,image/png">
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-primary btn-sm" onclick="msSubmitNewHistoryEvent()">Salva evento</button>
                                        <button type="button" class="btn btn-default btn-sm" onclick="msToggleAddHistoryEvent(false)">Annulla</button>
                                    </div>
                                </div>
                                <textarea class="form-control input-sm" rows="3" id="ms_new_history_note" placeholder="Note evento..."></textarea>
                            </div>
                            <div id="ms_history_content" class="ms-muted">Nessuno storico disponibile.</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
                <button type="submit" class="btn btn-primary"><span class="glyphicon glyphicon-floppy-disk"></span>&ensp;Salva</button>
            </div>
        </form>
    </div>
</div>

<div class="ms-wait-overlay" id="msWaitOverlay">
    <div class="ms-wait-box">
        <div class="ms-wait-spinner"></div>
        <strong id="msWaitTitle">Caricamento in corso</strong>
        <div class="ms-muted" id="msWaitText">Attendere qualche secondo...</div>
    </div>
</div>

<script>
const msHistory = <?php echo json_encode($storico, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const msAttachments = <?php echo json_encode($allegati, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const msOpenMovementId = <?php echo intval($openMovementId); ?>;
const msStatesByType = <?php echo json_encode(studentiMovimentiStatiPerTipo(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const msStateLabels = <?php echo json_encode($stati, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const msPracticeDocumentTypesBase = [
    {key: 'pagella', label: 'Pagella', aliases: ['pagella_precedente']},
    {key: 'documento_identita_studente', label: 'Documento di identita dello studente'},
    {key: 'codice_fiscale_studente', label: 'Codice fiscale dello studente'},
    {key: 'documento_identita_genitore_1', label: 'Documento di identita del responsabile 1'},
    {key: 'codice_fiscale_genitore_1', label: 'Codice fiscale del responsabile 1'},
    {key: 'documento_identita_genitore_2', label: 'Documento di identita del responsabile 2'},
    {key: 'codice_fiscale_genitore_2', label: 'Codice fiscale del responsabile 2'},
    {key: 'nulla_osta_entrata', label: 'Nulla osta', aliases: ['richiesta_nulla_osta']},
    {key: 'altro', label: 'Altri documenti', optional: true}
];
const msPracticeDocumentTypesByType = {
    entrata: msPracticeDocumentTypesBase.concat([
        {key: 'ok_colloqui', label: 'OK dai colloqui', checkOnly: true}
    ]),
    uscita: [
        {key: 'domanda_nulla_osta', label: 'Domanda di nulla osta', aliases: ['richiesta_nulla_osta']},
        {key: 'ricevuta_papiro', label: 'Ricevuta libreria Il Papiro restituzione libri', optional: true},
        {key: 'ok_colloqui', label: 'OK dai colloqui', checkOnly: true},
        {key: 'altro', label: 'Altri documenti', optional: true}
    ],
    ritiro: [
        {key: 'domanda_ritiro_firmata', label: 'Domanda/mail di ritiro firmata'},
        {key: 'altro', label: 'Altri documenti', optional: true}
    ],
    bocciato_reiscrizione: []
};
const msFilterSection = <?php echo json_encode($activeSection, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const msFilterYear = <?php echo intval($activeYear); ?>;
let msParentsExpanded = false;

window.setTimeout(function () {
    document.querySelectorAll('.ms-auto-dismiss').forEach(function (element) {
        element.classList.add('is-hiding');
        window.setTimeout(function () {
            if (element && element.parentNode) {
                element.parentNode.removeChild(element);
            }
        }, 450);
    });
}, 7000);

function msTextLower(value) {
    return String(value || '').toLocaleLowerCase('it-IT');
}

function msExportUrl(format, q, stato) {
    const params = new URLSearchParams();
    const global = String(q || '').trim() !== '' || String(stato || '') !== '';
    params.set('sezione', global ? 'tutte' : msFilterSection);
    params.set('anno', global ? '0' : String(msFilterYear));
    params.set('q', q || '');
    params.set('stato', stato || '');
    params.set('format', format);
    return 'movimentiStudentiExport.php?' + params.toString();
}

function msRenderAddressSummary(summary, hidden) {
    const box = document.getElementById('ms_address_summary');
    const container = document.getElementById('ms_address_summary_items');
    if (!container) return;
    if (box) {
        box.style.display = hidden ? 'none' : '';
    }
    if (hidden) {
        return;
    }
    container.innerHTML = '';
    const addresses = Object.keys(summary).sort(function (a, b) {
        return a.localeCompare(b, 'it-IT', { sensitivity: 'base', numeric: true });
    });
    if (addresses.length === 0) {
        const empty = document.createElement('span');
        empty.className = 'ms-address-empty';
        empty.textContent = 'Nessun dato da riepilogare';
        container.appendChild(empty);
        return;
    }
    addresses.forEach(function (address) {
        const chip = document.createElement('span');
        chip.className = 'ms-address-chip';
        chip.appendChild(document.createTextNode(address + ' '));
        const total = document.createElement('strong');
        total.textContent = String(summary[address]);
        chip.appendChild(total);
        container.appendChild(chip);
    });
}

function msApplyInstantFilters() {
    const qInput = document.getElementById('ms_filter_q');
    const stateInput = document.getElementById('ms_filter_stato');
    const rows = Array.from(document.querySelectorAll('.ms-data-row'));
    const emptyRow = document.getElementById('ms_empty_row');
    const countBox = document.getElementById('ms_filter_count');
    const q = msTextLower(qInput ? qInput.value : '').trim();
    const state = stateInput ? String(stateInput.value || '') : '';
    let visible = 0;
    let visibleEntrate = 0;
    let visibleUscite = 0;
    const addressSummary = {};
    const globalSearch = q !== '' || state !== '';
    rows.forEach(function (row) {
        const rowSection = String(row.dataset.filterSection || '');
        const rowYear = parseInt(row.dataset.filterYear || '0', 10);
        const scopeOk = globalSearch
            ? (rowYear >= 1 && rowYear <= 5)
            : (rowSection === msFilterSection && rowYear === msFilterYear);
        const textOk = q === '' || String(row.dataset.filterText || '').indexOf(q) !== -1;
        const stateOk = state === '' || String(row.dataset.filterState || '') === state;
        const show = scopeOk && textOk && stateOk;
        row.style.display = show ? '' : 'none';
        if (show) {
            visible++;
            if (rowSection === 'entrate') {
                visibleEntrate++;
            } else if (rowSection === 'uscite') {
                visibleUscite++;
            }
            if (!globalSearch && String(row.dataset.summaryInclude || '') === '1') {
                const address = String(row.dataset.summaryAddress || 'Senza indirizzo');
                addressSummary[address] = (addressSummary[address] || 0) + 1;
            }
        }
    });
    if (emptyRow) {
        emptyRow.style.display = visible === 0 ? '' : 'none';
    }
    if (countBox) {
        countBox.textContent = visible + ' righe' + (globalSearch ? ' trovate in tutte le pratiche (' + visibleEntrate + ' entrate, ' + visibleUscite + ' uscite)' : '');
    }
    const xls = document.getElementById('ms_export_xls');
    const pdf = document.getElementById('ms_export_pdf');
    if (xls) xls.href = msExportUrl('xls', qInput ? qInput.value.trim() : '', state);
    if (pdf) pdf.href = msExportUrl('pdf', qInput ? qInput.value.trim() : '', state);
    msRenderAddressSummary(addressSummary, globalSearch);
}

const msFilterQ = document.getElementById('ms_filter_q');
const msFilterState = document.getElementById('ms_filter_stato');
if (msFilterQ) {
    msFilterQ.addEventListener('input', msApplyInstantFilters);
}
if (msFilterState) {
    msFilterState.addEventListener('change', msApplyInstantFilters);
}
const msFilterClear = document.getElementById('ms_filter_clear');
if (msFilterClear) {
    msFilterClear.addEventListener('click', function () {
        if (msFilterQ) msFilterQ.value = '';
        if (msFilterState) msFilterState.value = '';
        msApplyInstantFilters();
    });
}
msApplyInstantFilters();

function msSetField(id, value) {
    const element = document.getElementById(id);
    if (element) element.value = value || '';
}

function msSetChecked(id, value) {
    const element = document.getElementById(id);
    if (element) element.checked = String(value || '') === '1';
}

function msSetParentsVisible(expanded) {
    msParentsExpanded = !!expanded;
    const kind = document.getElementById('ms_tipo_pratica') && document.getElementById('ms_tipo_pratica').value === 'entrata' ? 'entrata' : 'uscita';
    document.querySelectorAll('.ms-parent-field').forEach(function (element) {
        element.style.display = kind === 'entrata' && msParentsExpanded ? '' : 'none';
    });
    document.querySelectorAll('.ms-parent-title').forEach(function (element) {
        element.style.display = kind === 'entrata' ? '' : 'none';
    });
    const button = document.getElementById('ms_toggle_parents');
    if (button) {
        button.textContent = msParentsExpanded ? 'Nascondi dati genitori' : 'Mostra dati genitori';
    }
}

function msToggleParents() {
    msSetParentsVisible(!msParentsExpanded);
}

function msSplitList(value) {
    const text = String(value || '').replace(/\r?\n/g, ' ').trim();
    if (!text) return [];
    const parts = text.indexOf('|') !== -1 ? text.split('|') : text.split(';');
    return parts.map(function (item) {
        return item.replace(/\s+/g, ' ').trim();
    }).filter(Boolean);
}

function msSubjectsHiddenId(kind) {
    return kind === 'esami' ? 'ms_esami_integrativi_note' : 'ms_carenze_note';
}

function msSubjectsListId(kind) {
    return kind === 'esami' ? 'ms_esami_materie_list' : 'ms_carenze_materie_list';
}

function msSubjectsSelectId(kind) {
    return kind === 'esami' ? 'ms_esami_materie' : 'ms_carenze_materie';
}

function msSubjectValues(kind) {
    return msSplitList(document.getElementById(msSubjectsHiddenId(kind)).value);
}

function msSetSubjectValues(kind, values) {
    const normalized = [];
    values.forEach(function (value) {
        const text = String(value || '').replace(/\s+/g, ' ').trim();
        if (!text) return;
        if (!normalized.some(function (item) { return item.toUpperCase() === text.toUpperCase(); })) {
            normalized.push(text);
        }
    });
    msSetField(msSubjectsHiddenId(kind), normalized.join(' | '));
    msRenderSubjectList(kind);
}

function msRenderSubjectList(kind) {
    const box = document.getElementById(msSubjectsListId(kind));
    if (!box) return;
    const values = msSubjectValues(kind);
    if (!values.length) {
        box.innerHTML = '<span class="ms-muted">Nessuna materia selezionata.</span>';
        return;
    }
    box.innerHTML = '';
    values.forEach(function (value, index) {
        const chip = document.createElement('span');
        chip.className = 'ms-subject-chip';
        const text = document.createElement('span');
        text.textContent = value;
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.textContent = 'x';
        remove.title = 'Rimuovi materia';
        remove.addEventListener('click', function () {
            const next = msSubjectValues(kind);
            next.splice(index, 1);
            msSetSubjectValues(kind, next);
        });
        chip.appendChild(text);
        chip.appendChild(remove);
        box.appendChild(chip);
    });
}

function msAddSubject(kind) {
    const select = document.getElementById(msSubjectsSelectId(kind));
    if (!select || !select.value) return;
    const values = msSubjectValues(kind);
    values.push(select.value);
    msSetSubjectValues(kind, values);
    select.value = '';
}

function msUpdateSubjectBoxes() {
    const esamiActive = document.getElementById('ms_esami_integrativi').value === '1';
    const carenzeActive = document.getElementById('ms_carenze_presenti').value === '1';
    document.getElementById('ms_esami_materie_box').style.display = esamiActive ? '' : 'none';
    document.getElementById('ms_carenze_materie_box').style.display = carenzeActive ? '' : 'none';
    if (!esamiActive) {
        msSetSubjectValues('esami', []);
    }
    if (!carenzeActive) {
        msSetSubjectValues('carenze', []);
    }
    msRenderSubjectList('esami');
    msRenderSubjectList('carenze');
}

function msSyncSubjectNotes() {
    msSetSubjectValues('esami', document.getElementById('ms_esami_integrativi').value === '1' ? msSubjectValues('esami') : []);
    msSetSubjectValues('carenze', document.getElementById('ms_carenze_presenti').value === '1' ? msSubjectValues('carenze') : []);
}

function msSetNote(value) {
    msSetField('ms_note', value || '');
    msSetField('ms_note_entrata', value || '');
    msSetField('ms_note_uscita', value || '');
}

function msSyncVisibleNote() {
    const kind = document.getElementById('ms_tipo_pratica').value === 'entrata' ? 'entrata' : 'uscita';
    const source = document.getElementById(kind === 'entrata' ? 'ms_note_entrata' : 'ms_note_uscita');
    msSetField('ms_note', source ? source.value : '');
}

function msFilterStateOptions(preferredValue) {
    const typeSelect = document.getElementById('ms_tipo_pratica');
    const stateSelect = document.getElementById('ms_stato_pratica');
    if (!typeSelect || !stateSelect) return;
    const type = typeSelect.value || 'uscita';
    const allowed = msStatesByType[type] || msStatesByType.uscita || [];
    const current = preferredValue || stateSelect.value || '';
    stateSelect.innerHTML = '';
    allowed.forEach(function (key) {
        const option = document.createElement('option');
        option.value = key;
        option.textContent = msStateLabels[key] || key;
        option.setAttribute('data-state-key', key);
        stateSelect.appendChild(option);
    });
    if (allowed.includes(current)) {
        stateSelect.value = current;
    } else if (allowed.length) {
        stateSelect.value = allowed[0];
    }
}

function msEscape(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (char) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
    });
}

function msFormatDateTimeIt(value) {
    const text = String(value || '').trim();
    const match = text.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
    if (!match) return text;
    return match[3] + '/' + match[2] + '/' + match[1] + (match[4] ? ' ' + match[4] + ':' + match[5] : '');
}

function msAttachmentHref(path, id) {
    if (id) {
        return 'movimentiStudentiAllegato.php?id=' + encodeURIComponent(id);
    }
    const normalized = String(path || '').replace(/\\/g, '/');
    if (!normalized) return '';
    const dataIndex = normalized.indexOf('/data/');
    if (dataIndex >= 0) {
        return '../' + normalized.substring(dataIndex + 1);
    }
    if (normalized.indexOf('data/') === 0) {
        return '../' + normalized;
    }
    return normalized;
}

function msNormalizeAttachmentName(value) {
    return String(value || '').replace(/\s+/g, ' ').trim().toUpperCase();
}

function msEventAttachments(practiceId, row) {
    const attachments = [];
    const seen = {};
    function addAttachment(path, name, type, id, linkedEventId) {
        path = String(path || '').trim();
        name = String(name || '').trim();
        if (!path) return;
        const key = path + '|' + name;
        if (seen[key]) {
            if (id && !seen[key].id) {
                seen[key].id = id;
            }
            if (linkedEventId && !seen[key].id_evento) {
                seen[key].id_evento = linkedEventId;
            }
            return;
        }
        const item = {path: path, name: name || 'Allegato', type: type || '', id: id || '', id_evento: linkedEventId || ''};
        seen[key] = item;
        attachments.push(item);
    }

    const eventId = Number(row.id || 0);
    addAttachment(row.allegato_path, row.allegato_original_name, row.tipo_allegato, '', eventId);

    const practiceAttachments = msAttachments[String(practiceId)] || msAttachments[Number(practiceId)] || [];
    if (!practiceAttachments.length) {
        return attachments;
    }

    const description = msNormalizeAttachmentName(row.descrizione || '');
    const rowName = msNormalizeAttachmentName(row.allegato_original_name || '');
    practiceAttachments.forEach(function (attachment) {
        const name = String(attachment.nome_file || attachment.allegato_original_name || '').trim();
        const normalizedName = msNormalizeAttachmentName(name);
        const linkedEventId = Number(attachment.id_evento || 0);
        const matchesCurrentEvent = normalizedName && (
            linkedEventId === eventId ||
            normalizedName === rowName ||
            description.indexOf(normalizedName) !== -1
        );
        if (matchesCurrentEvent) {
            addAttachment(attachment.path_file || attachment.allegato_path, name, attachment.tipo_allegato || '', attachment.id || '', linkedEventId);
        }
    });

    if (!attachments.length && String(row.tipo_evento || '') === 'allegato') {
        practiceAttachments.forEach(function (attachment) {
            addAttachment(
                attachment.path_file || attachment.allegato_path,
                attachment.nome_file || attachment.allegato_original_name,
                attachment.tipo_allegato || '',
                attachment.id || '',
                attachment.id_evento || ''
            );
        });
    }

    return attachments;
}

function msAttachmentLinks(attachments, allowDelete) {
    if (!attachments.length) return '';
    return '<div style="margin-top:6px;">' + attachments.map(function (attachment) {
        const label = attachment.type ? attachment.type + ': ' + attachment.name : attachment.name;
        const deleteButton = allowDelete && attachment.id
            ? ' <button type="button" class="btn btn-xs btn-danger" onclick="msDeleteAttachment(' + Number(attachment.id) + ')">Elimina</button>'
            : '';
        return '<div class="ms-history-attachment-row"><a class="btn btn-xs btn-default ms-history-attachment-link" target="_blank" download href="' + msEscape(msAttachmentHref(attachment.path, attachment.id)) + '"><span class="glyphicon glyphicon-paperclip"></span> Scarica allegato: ' + msEscape(label) + '</a>' + deleteButton + '</div>';
    }).join('') + '</div>';
}

function msCurrentPracticeType() {
    const select = document.getElementById('ms_tipo_pratica');
    return select ? String(select.value || 'uscita') : 'uscita';
}

function msPracticeDocumentsForType(type) {
    return msPracticeDocumentTypesByType[type] || msPracticeDocumentTypesByType.uscita;
}

function msHistoryHasColloquiOk(practiceId) {
    const rows = msHistory[String(practiceId)] || msHistory[Number(practiceId)] || [];
    return rows.some(function (row) {
        const text = [
            row.tipo_evento || '',
            row.descrizione || '',
            row.note || '',
            row.stato_pratica || ''
        ].join(' ').toLocaleLowerCase('it-IT');
        return text.indexOf('colloqu') !== -1 && (
            text.indexOf('positivo') !== -1 ||
            text.indexOf('approv') !== -1 ||
            text.indexOf('idone') !== -1 ||
            text.indexOf('ok') !== -1 ||
            text.indexOf('svolto') !== -1
        );
    });
}

function msRenderPracticeDocs(practiceId) {
    const box = document.getElementById('ms_docs_content');
    if (!box) return;
    const docsPanel = document.getElementById('ms_docs_panel');
    const practiceType = msCurrentPracticeType();
    const documents = msPracticeDocumentsForType(practiceType);
    if (docsPanel) {
        docsPanel.style.display = documents.length ? '' : 'none';
    }
    const attachments = msAttachments[String(practiceId)] || msAttachments[Number(practiceId)] || [];
    if (!documents.length) {
        box.innerHTML = '<span class="ms-muted">Per questa pratica bastano lo storico e gli eventuali allegati collegati agli eventi.</span>';
        return;
    }
    if (!practiceId) {
        box.innerHTML = '<span class="ms-muted">Salva prima la pratica, poi potrai caricare documenti.</span>';
        return;
    }
    const attachmentsByType = {};
    attachments.forEach(function (attachment) {
        const type = String(attachment.tipo_allegato || '').trim();
        if (!type) return;
        if (!attachmentsByType[type]) attachmentsByType[type] = [];
        attachmentsByType[type].push(attachment);
    });
    const typeLabels = {
        documenti_entrata: 'Documenti genitori e studente',
        pagella: 'Pagella',
        pagella_precedente: 'Pagella',
        documento_identita_studente: 'Documento di identita dello studente',
        codice_fiscale_studente: 'Codice fiscale dello studente',
        documento_identita_genitore_1: 'Documento di identita del responsabile 1',
        codice_fiscale_genitore_1: 'Codice fiscale del responsabile 1',
        documento_identita_genitore_2: 'Documento di identita del responsabile 2',
        codice_fiscale_genitore_2: 'Codice fiscale del responsabile 2',
        documenti_identita: 'Documenti genitori e studente',
        domanda_ritiro_firmata: 'Domanda/mail di ritiro firmata',
        domanda_nulla_osta: 'Domanda di nulla osta',
        ricevuta_papiro: 'Ricevuta libreria Il Papiro restituzione libri',
        richiesta_nulla_osta: 'Nulla osta',
        nulla_osta_entrata: 'Nulla osta',
        altro: 'Altri documenti'
    };
    const usedAttachmentIds = {};
    function attachmentId(attachment) {
        return String(attachment.id || '') || String(attachment.path_file || attachment.allegato_path || '') + '|' + String(attachment.nome_file || attachment.allegato_original_name || '');
    }
    function attachmentsForDocument(document) {
        let matches = [];
        [document.key].concat(document.aliases || []).forEach(function (type) {
            if (attachmentsByType[type]) {
                matches = matches.concat(attachmentsByType[type]);
            }
        });
        return matches;
    }
    function renderAttachmentActions(attachment) {
        const name = String(attachment.nome_file || attachment.allegato_original_name || 'Documento').trim();
        const href = msAttachmentHref(attachment.path_file || attachment.allegato_path || '', attachment.id || '');
        const id = Number(attachment.id || 0);
        return '<div class="ms-doc-file">' + msEscape(name) + '</div>' +
            '<div class="ms-doc-actions">' +
            (href ? '<a class="btn btn-xs btn-primary" target="_blank" href="' + msEscape(href) + '"><span class="glyphicon glyphicon-file"></span> Apri</a>' : '') +
            (id ? '<button type="button" class="btn btn-xs btn-danger" onclick="msDeleteAttachment(' + id + ')">Elimina</button>' : '') +
            '</div>';
    }
    function renderInlineUpload(document, matches) {
        const inputId = 'ms_doc_file_' + document.key;
        const buttonText = matches.length ? 'Aggiungi' : 'Carica';
        return '<div class="ms-doc-inline-upload">' +
            '<input type="file" id="' + msEscape(inputId) + '" class="form-control input-sm ms-doc-upload-input" data-tipo-documento="' + msEscape(document.key) + '" accept="application/pdf,image/jpeg,image/png" multiple>' +
            '<button type="button" class="btn btn-xs btn-success" onclick="msUploadPracticeAttachments(\'' + msEscape(document.key) + '\', \'' + msEscape(inputId) + '\')">' +
            '<span class="glyphicon glyphicon-plus"></span> ' + buttonText +
            '</button>' +
            '</div>';
    }
    let html = '<div class="ms-doc-upload-tools">' +
        '<button type="button" class="btn btn-sm btn-success" onclick="msUploadSelectedPracticeAttachments(event)">' +
        '<span class="glyphicon glyphicon-upload"></span> Carica tutti i file selezionati</button>' +
        '<span class="ms-doc-upload-status" id="ms_doc_upload_all_status"></span>' +
        '</div>' +
        '<table class="ms-doc-table"><colgroup><col style="width:30%"><col style="width:12%"><col style="width:58%"></colgroup>' +
        '<thead><tr><th>Documento</th><th>Stato</th><th>File</th></tr></thead><tbody>';
    documents.forEach(function (document) {
        const matches = document.checkOnly ? [] : attachmentsForDocument(document);
        matches.forEach(function (attachment) {
            usedAttachmentIds[attachmentId(attachment)] = true;
        });
        const checkOk = document.key === 'ok_colloqui' ? msHistoryHasColloquiOk(practiceId) : false;
        const ok = matches.length || checkOk;
        const statusText = ok ? (document.checkOnly ? 'ok' : 'caricato') : (document.optional ? 'facoltativo' : 'da avere');
        const statusClass = ok ? 'success' : (document.optional ? 'default' : 'warning');
        const fileCell = document.checkOnly
            ? (checkOk ? '<span class="ms-doc-empty">OK presente nello storico colloqui</span>' : '<span class="ms-doc-empty">Da registrare nello storico/colloqui</span>')
            : (matches.length ? matches.map(renderAttachmentActions).join('') : '<span class="ms-doc-empty">Nessun file caricato</span>') + renderInlineUpload(document, matches);
        html += '<tr>' +
            '<td class="ms-doc-name">' + msEscape(document.label) + '</td>' +
            '<td><span class="label label-' + statusClass + '">' + statusText + '</span></td>' +
            '<td>' + fileCell + '</td>' +
            '</tr>';
    });
    html += '</tbody></table>';

    const extras = attachments.filter(function (attachment) {
        return !usedAttachmentIds[attachmentId(attachment)];
    });
    if (extras.length) {
        html += '<div class="ms-doc-extra-title">Altri allegati caricati</div>' +
            '<table class="ms-doc-table"><colgroup><col style="width:30%"><col style="width:12%"><col style="width:58%"></colgroup><tbody>' +
            extras.map(function (attachment) {
                const type = String(attachment.tipo_allegato || '').trim();
                const typeLabel = typeLabels[type] || type || 'Documento';
                return '<tr>' +
                    '<td class="ms-doc-name">' + msEscape(typeLabel) + '</td>' +
                    '<td><span class="label label-default">extra</span></td>' +
                    '<td>' + renderAttachmentActions(attachment) + '</td>' +
                    '</tr>';
            }).join('') +
            '</tbody></table>';
    }
    box.innerHTML = html;
}

function msShowWait(title, text) {
    const overlay = document.getElementById('msWaitOverlay');
    if (!overlay) return;
    document.getElementById('msWaitTitle').textContent = title || 'Caricamento in corso';
    document.getElementById('msWaitText').textContent = text || 'Attendere qualche secondo...';
    overlay.classList.add('is-visible');
}

function msUploadPracticeAttachments(type, inputId) {
    const practiceId = Number(document.getElementById('ms_id').value || 0);
    if (!practiceId) {
        window.alert('Salva prima la pratica, poi potrai caricare documenti.');
        return;
    }
    const fileInput = document.getElementById(inputId || '');
    if (!fileInput || !fileInput.files || !fileInput.files.length) {
        window.alert('Seleziona almeno un documento da caricare.');
        return;
    }
    msShowWait('Caricamento documento', 'Sto caricando il documento nella pratica...');
    msUploadPracticeAttachmentFiles(practiceId, type || 'altro', fileInput.files).then(function () {
        window.location.href = 'movimentiStudenti.php?open_movimento_id=' + practiceId;
    }).catch(function () {
        const overlay = document.getElementById('msWaitOverlay');
        if (overlay) overlay.classList.remove('is-visible');
        window.alert('Caricamento documento non riuscito.');
    });
}

function msUploadPracticeAttachmentFiles(practiceId, type, files) {
    const data = new FormData();
    data.append('action', 'add_practice_attachment');
    data.append('id', practiceId);
    data.append('tipo_allegato', type || 'altro');
    Array.prototype.forEach.call(files, function (file) {
        data.append('allegato[]', file);
    });
    return fetch(window.location.href, {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    });
}

async function msUploadSelectedPracticeAttachments(event) {
    if (event) event.preventDefault();
    const practiceId = Number(document.getElementById('ms_id').value || 0);
    if (!practiceId) {
        window.alert('Salva prima la pratica, poi potrai caricare documenti.');
        return false;
    }
    const inputs = Array.from(document.querySelectorAll('#ms_docs_content .ms-doc-upload-input')).filter(function (input) {
        return input.files && input.files.length;
    });
    const status = document.getElementById('ms_doc_upload_all_status');
    const trigger = event ? event.currentTarget : null;
    if (!inputs.length) {
        if (status) {
            status.textContent = 'Seleziona almeno un file in una riga documento.';
            status.style.color = '#b91c1c';
        }
        return false;
    }
    if (trigger) {
        trigger.disabled = true;
    }
    document.querySelectorAll('#ms_docs_content .ms-doc-inline-upload button').forEach(function (button) {
        button.disabled = true;
    });
    msShowWait('Caricamento allegati', 'Sto salvando tutti i file selezionati...');

    let uploaded = 0;
    let errors = 0;
    for (const input of inputs) {
        if (status) {
            status.textContent = 'Caricamento ' + (uploaded + errors + 1) + ' di ' + inputs.length + '...';
            status.style.color = '#475569';
        }
        try {
            const response = await msUploadPracticeAttachmentFiles(practiceId, input.dataset.tipoDocumento || 'altro', input.files);
            if (!response.ok) {
                errors++;
            } else {
                uploaded++;
            }
        } catch (e) {
            errors++;
        }
    }

    if (uploaded > 0) {
        if (errors > 0) {
            window.alert(uploaded + ' righe caricate, ' + errors + ' con errore.');
        }
        window.location.href = 'movimentiStudenti.php?open_movimento_id=' + practiceId;
        return false;
    }

    const overlay = document.getElementById('msWaitOverlay');
    if (overlay) overlay.classList.remove('is-visible');
    if (status) {
        status.textContent = 'Nessun documento caricato.';
        status.style.color = '#b91c1c';
    }
    if (trigger) {
        trigger.disabled = false;
    }
    document.querySelectorAll('#ms_docs_content .ms-doc-inline-upload button').forEach(function (button) {
        button.disabled = false;
    });
    return false;
}

function msSubmitHiddenPost(fields) {
    const form = document.createElement('form');
    form.method = 'post';
    form.style.display = 'none';
    Object.keys(fields).forEach(function (name) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = fields[name];
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
}

function msToggleAddHistoryEvent(show) {
    const box = document.getElementById('ms_history_add_box');
    if (!box) return;
    const practiceId = Number(document.getElementById('ms_id').value || 0);
    if (show && !practiceId) {
        window.alert('Salva prima la pratica, poi potrai aggiungere eventi allo storico.');
        return;
    }
    box.style.display = show ? 'block' : 'none';
    if (!show) {
        const desc = document.getElementById('ms_new_history_desc');
        const note = document.getElementById('ms_new_history_note');
        const file = document.getElementById('ms_new_history_file');
        if (desc) desc.value = '';
        if (note) note.value = '';
        if (file) file.value = '';
    }
}

function msSubmitNewHistoryEvent() {
    const practiceId = Number(document.getElementById('ms_id').value || 0);
    const desc = document.getElementById('ms_new_history_desc');
    const note = document.getElementById('ms_new_history_note');
    const file = document.getElementById('ms_new_history_file');
    const description = desc ? desc.value.trim() : '';
    if (!practiceId) {
        window.alert('Salva prima la pratica, poi potrai aggiungere eventi allo storico.');
        return;
    }
    if (!description) {
        window.alert('Scrivi una descrizione per l\'evento.');
        return;
    }
    const data = new FormData();
    data.append('action', 'add_event');
    data.append('id', practiceId);
    data.append('event_descrizione', description);
    data.append('event_note', note ? note.value.trim() : '');
    data.append('tipo_pratica', document.getElementById('ms_tipo_pratica') ? document.getElementById('ms_tipo_pratica').value : '');
    data.append('stato_pratica', document.getElementById('ms_stato_pratica') ? document.getElementById('ms_stato_pratica').value : '');
    data.append('tipo_allegato', 'altro');
    if (file && file.files && file.files.length) {
        data.append('event_allegato', file.files[0]);
    }
    msShowWait('Salvataggio evento', 'Sto aggiungendo la riga allo storico...');
    fetch(window.location.href, {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    }).then(function () {
        window.location.href = 'movimentiStudenti.php?open_movimento_id=' + practiceId;
    }).catch(function () {
        const overlay = document.getElementById('msWaitOverlay');
        if (overlay) overlay.classList.remove('is-visible');
        window.alert('Salvataggio evento non riuscito.');
    });
}

function msToggleHistoryEdit(id, show) {
    const box = document.getElementById('ms_history_edit_' + id);
    if (box) box.style.display = show ? 'block' : 'none';
}

function msDeleteHistoryEvent(id) {
    if (!window.confirm('Eliminare questa riga dello storico?')) return;
    msSubmitHiddenPost({action: 'delete_event', event_id: id});
}

function msDeleteAttachment(id) {
    if (!window.confirm('Eliminare questo allegato?')) return;
    msShowWait('Eliminazione documento', 'Sto aggiornando la pratica...');
    msSubmitHiddenPost({action: 'delete_attachment', attachment_id: id});
}

function msAttachmentTypeOptions() {
    const docs = msPracticeDocumentsForType(msCurrentPracticeType()).filter(function (document) {
        return !document.checkOnly;
    });
    const source = docs.length ? docs : [{key: 'altro', label: 'Altri documenti'}];
    return source.map(function (document) {
        return '<option value="' + msEscape(document.key) + '">' + msEscape(document.label) + '</option>';
    }).join('');
}

function msUploadHistoryAttachment(eventId) {
    const fileInput = document.getElementById('ms_history_file_' + eventId);
    if (!fileInput || !fileInput.files || !fileInput.files.length) {
        window.alert('Seleziona un allegato da caricare.');
        return;
    }
    const typeSelect = document.getElementById('ms_history_tipo_allegato_' + eventId);
    const data = new FormData();
    data.append('action', 'add_event_attachment');
    data.append('event_id', eventId);
    data.append('tipo_allegato', typeSelect ? typeSelect.value : 'documento');
    data.append('event_allegato', fileInput.files[0]);
    fetch(window.location.href, {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    }).then(function () {
        window.location.reload();
    }).catch(function () {
        window.alert('Caricamento allegato non riuscito.');
    });
}

function msSubmitHistoryUpdate(id) {
    msSubmitHiddenPost({
        action: 'update_event',
        event_id: id,
        event_descrizione: document.getElementById('ms_history_desc_' + id).value || '',
        event_note: document.getElementById('ms_history_note_' + id).value || ''
    });
}

function msRenderHistory(practiceId) {
    const box = document.getElementById('ms_history_content');
    if (!box) return;
    const rows = msHistory[String(practiceId)] || msHistory[Number(practiceId)] || [];
    if (!practiceId || !rows.length) {
        box.innerHTML = '<span class="ms-muted">Nessuno storico disponibile.</span>';
        return;
    }
    box.innerHTML = rows.map(row => {
        const eventId = Number(row.id || 0);
        const attachments = msEventAttachments(practiceId, row);
        const meta = [
            row.tipo_pratica ? 'Tipo: ' + row.tipo_pratica : '',
            row.stato_pratica ? 'Stato: ' + row.stato_pratica : '',
            row.scuola_destinazione ? 'Destinazione: ' + row.scuola_destinazione : '',
            row.indirizzo_destinazione ? 'Indirizzo/GestOre: ' + row.indirizzo_destinazione : '',
            row.scuola_provenienza ? 'Provenienza: ' + row.scuola_provenienza : '',
            row.tipo_allegato ? 'Tipo allegato: ' + row.tipo_allegato : '',
        ].filter(Boolean).join(' - ');
        const attachment = msAttachmentLinks(attachments, false);
        const editableAttachments = msAttachmentLinks(attachments, true);
        const uploadBox = '<div style="margin-top:8px;">' +
            '<label>Aggiungi allegato a questa riga</label>' +
            '<div class="row" style="margin-left:-4px;margin-right:-4px;">' +
                '<div class="col-sm-5" style="padding-left:4px;padding-right:4px;"><select class="form-control input-sm" id="ms_history_tipo_allegato_' + eventId + '">' + msAttachmentTypeOptions() + '</select></div>' +
                '<div class="col-sm-5" style="padding-left:4px;padding-right:4px;"><input type="file" class="form-control input-sm" id="ms_history_file_' + eventId + '" accept="application/pdf,image/jpeg,image/png"></div>' +
                '<div class="col-sm-2" style="padding-left:4px;padding-right:4px;"><button type="button" class="btn btn-default btn-xs" onclick="msUploadHistoryAttachment(' + eventId + ')">Aggiungi</button></div>' +
            '</div>' +
        '</div>';
        const editBox = '<div id="ms_history_edit_' + eventId + '" style="display:none;margin-top:8px;">' +
            '<label>Descrizione</label><input class="form-control input-sm" id="ms_history_desc_' + eventId + '" value="' + msEscape(row.descrizione || row.tipo_evento || 'Aggiornamento') + '">' +
            '<label style="margin-top:6px;">Note</label><textarea class="form-control input-sm" rows="3" id="ms_history_note_' + eventId + '">' + msEscape(row.note || '') + '</textarea>' +
            '<label style="margin-top:6px;">Allegati</label>' +
            (editableAttachments || '<div class="ms-muted">Nessun allegato collegato a questa riga.</div>') +
            uploadBox +
            '<div style="margin-top:6px;"><button type="button" class="btn btn-primary btn-xs" onclick="msSubmitHistoryUpdate(' + eventId + ')">Salva correzione</button> ' +
            '<button type="button" class="btn btn-default btn-xs" onclick="msToggleHistoryEdit(' + eventId + ', false)">Annulla</button></div>' +
        '</div>';
        return '<div class="ms-history-event">' +
            '<div class="ms-history-head">' +
                '<span>' + msEscape(row.descrizione || row.tipo_evento || 'Aggiornamento') + '</span>' +
                '<span>' + msEscape(msFormatDateTimeIt(row.created_at || '')) + '</span>' +
            '</div>' +
            (row.created_by ? '<div class="ms-history-meta">' + msEscape(row.created_by) + '</div>' : '') +
            (meta ? '<div class="ms-history-meta">' + msEscape(meta) + '</div>' : '') +
            (row.note ? '<div class="ms-history-note">' + msEscape(row.note) + '</div>' : '') +
            attachment +
            '<div style="margin-top:6px;"><button type="button" class="btn btn-default btn-xs" onclick="msToggleHistoryEdit(' + eventId + ', true)">Modifica</button> ' +
            '<button type="button" class="btn btn-danger btn-xs" onclick="msDeleteHistoryEvent(' + eventId + ')">Elimina</button></div>' +
            editBox +
        '</div>';
    }).join('');
}

function msSyncSchoolHidden(selectId, hiddenId, otherInputId) {
    const select = document.getElementById(selectId);
    const hidden = document.getElementById(hiddenId);
    if (!select || !hidden) return;
    const otherInput = document.getElementById(otherInputId);
    if (select.value === '__altro__') {
        hidden.value = otherInput ? otherInput.value.trim() : '';
        return;
    }
    const option = select.options[select.selectedIndex];
    if (select.value && option) {
        hidden.value = (option.textContent || '').trim();
    } else {
        hidden.value = '';
    }
}

function msUpdateSchoolOther(selectId, hiddenId, otherInputId, boxId, value) {
    const select = document.getElementById(selectId);
    const hidden = document.getElementById(hiddenId);
    const otherInput = document.getElementById(otherInputId);
    const box = document.getElementById(boxId);
    if (!select || !hidden || !otherInput) return;
    const text = String(value || '').trim();
    let matched = false;
    if (text !== '') {
        for (let i = 0; i < select.options.length; i++) {
            const option = select.options[i];
            if (option.value && option.value !== '__altro__' && (option.textContent || '').trim().toUpperCase() === text.toUpperCase()) {
                select.value = option.value;
                matched = true;
                break;
            }
        }
    }
    if (text !== '' && !matched && !select.value) {
        select.value = '__altro__';
        otherInput.value = text;
    } else if (select.value !== '__altro__') {
        otherInput.value = '';
    }
    otherInput.style.display = select.value === '__altro__' ? 'block' : 'none';
    if (box) {
        box.style.display = 'none';
        box.textContent = '';
    }
    msSyncSchoolHidden(selectId, hiddenId, otherInputId);
}

function msOpenNew(kind) {
    msSetField('ms_id', '');
    msSetField('ms_fonte', 'manuale');
    msSetField('ms_id_pratica_iscrizione', '');
    msSetField('ms_id_cambio_scuola_iscrizione', '');
    msSetField('ms_tipo_pratica', kind === 'entrata' ? 'entrata' : 'uscita');
    msSetField('ms_stato_pratica', kind === 'entrata' ? 'contatto_ricevuto' : 'da_verificare');
    msSetField('ms_id_studente', '');
    msSetField('ms_cognome', '');
    msSetField('ms_nome', '');
    msSetField('ms_codice_fiscale', '');
    msSetField('ms_anno_corso', '');
    msSetField('ms_classe_origine', '');
    msSetField('ms_classe_richiesta', '');
    msSetField('ms_id_istituto_provenienza', '');
    msSetField('ms_scuola_provenienza', '');
    msSetField('ms_indirizzo_provenienza', '');
    msSetField('ms_id_istituto_destinazione', '');
    msSetField('ms_scuola_destinazione', '');
    msSetField('ms_indirizzo_destinazione', '');
    msSetField('ms_id_indirizzo_gestore', '');
    msSetChecked('ms_doppio_bocciato', '0');
    msSetChecked('ms_doppio_bocciato_non_consecutivo', '0');
    msSetChecked('ms_bocciato_altra_scuola', '0');
    ['responsabile_1_tipo','responsabile_1_cognome','responsabile_1_nome','responsabile_1_codice_fiscale','email_genitore_1','telefono_genitore_1','responsabile_2_tipo','responsabile_2_cognome','responsabile_2_nome','responsabile_2_codice_fiscale','email_genitore_2','telefono_genitore_2'].forEach(function (field) {
        msSetField('ms_' + field, '');
    });
    msUpdateSchoolOther('ms_id_istituto_provenienza', 'ms_scuola_provenienza', 'ms_scuola_provenienza_altro', 'ms_scuola_provenienza_libera', '');
    msUpdateSchoolOther('ms_id_istituto_destinazione', 'ms_scuola_destinazione', 'ms_scuola_destinazione_altro', 'ms_scuola_destinazione_libera', '');
    msSetField('ms_esami_integrativi', '0');
    msSetSubjectValues('esami', []);
    msSetField('ms_carenze_presenti', '0');
    msSetSubjectValues('carenze', []);
    msSetNote('');
    msRenderHistory(0);
    msToggleAddHistoryEvent(false);
    document.getElementById('msPracticeTitle').textContent = kind === 'entrata' ? 'Nuova entrata' : 'Nuova uscita';
    msParentsExpanded = false;
    msUpdatePracticeKindFields();
    msRenderPracticeDocs(0);
    $('#msPracticeModal').modal('show');
}

function msOpenPracticeFromButton(button) {
    if (!button) return;
    msSetField('ms_id', button.dataset.id || '');
    msSetField('ms_fonte', button.dataset.fonte || 'manuale');
    msSetField('ms_id_pratica_iscrizione', button.dataset.id_pratica_iscrizione || '');
    msSetField('ms_id_cambio_scuola_iscrizione', button.dataset.id_cambio_scuola_iscrizione || '');
    msSetField('ms_tipo_pratica', button.dataset.tipo_pratica || 'uscita');
    msSetField('ms_stato_pratica', button.dataset.stato_pratica || 'da_verificare');
    msSetField('ms_id_studente', button.dataset.id_studente || '');
    msSetField('ms_cognome', button.dataset.cognome || '');
    msSetField('ms_nome', button.dataset.nome || '');
    msSetField('ms_codice_fiscale', button.dataset.codice_fiscale || '');
    msSetField('ms_anno_corso', button.dataset.anno_corso || '');
    msSetField('ms_classe_origine', button.dataset.classe_origine || '');
    msSetField('ms_classe_richiesta', button.dataset.classe_richiesta || '');
    msSetField('ms_id_istituto_provenienza', button.dataset.id_istituto_provenienza || '');
    msSetField('ms_scuola_provenienza', button.dataset.scuola_provenienza || '');
    msSetField('ms_indirizzo_provenienza', '');
    msSetField('ms_id_istituto_destinazione', button.dataset.id_istituto_destinazione || '');
    msSetField('ms_scuola_destinazione', button.dataset.scuola_destinazione || '');
    msSetField('ms_indirizzo_destinazione', button.dataset.indirizzo_destinazione || '');
    msSetField('ms_id_indirizzo_gestore', button.dataset.id_indirizzo_gestore || '');
    msSetChecked('ms_doppio_bocciato', button.dataset.doppio_bocciato || '0');
    msSetChecked('ms_doppio_bocciato_non_consecutivo', button.dataset.doppio_bocciato_non_consecutivo || '0');
    msSetChecked('ms_bocciato_altra_scuola', button.dataset.bocciato_altra_scuola || '0');
    ['responsabile_1_tipo','responsabile_1_cognome','responsabile_1_nome','responsabile_1_codice_fiscale','email_genitore_1','telefono_genitore_1','responsabile_2_tipo','responsabile_2_cognome','responsabile_2_nome','responsabile_2_codice_fiscale','email_genitore_2','telefono_genitore_2'].forEach(function (field) {
        msSetField('ms_' + field, button.dataset[field] || '');
    });
    msUpdateSchoolOther('ms_id_istituto_provenienza', 'ms_scuola_provenienza', 'ms_scuola_provenienza_altro', 'ms_scuola_provenienza_libera', button.dataset.scuola_provenienza || '');
    msUpdateSchoolOther('ms_id_istituto_destinazione', 'ms_scuola_destinazione', 'ms_scuola_destinazione_altro', 'ms_scuola_destinazione_libera', button.dataset.scuola_destinazione || '');
    msSetField('ms_esami_integrativi', button.dataset.esami_integrativi || '0');
    msSetSubjectValues('esami', msSplitList(button.dataset.esami_integrativi_note || ''));
    msSetField('ms_carenze_presenti', button.dataset.carenze_presenti || '0');
    msSetSubjectValues('carenze', msSplitList(button.dataset.carenze_note || ''));
    msSetNote(button.dataset.note || '');
    msRenderHistory(button.dataset.id || 0);
    msToggleAddHistoryEvent(false);
    document.getElementById('msPracticeTitle').textContent = 'Dettaglio pratica';
    msParentsExpanded = false;
    msUpdatePracticeKindFields();
    msRenderPracticeDocs(button.dataset.id || 0);
    $('#msPracticeModal').modal('show');
}

document.querySelectorAll('.ms-edit').forEach(function (button) {
    button.addEventListener('click', function () {
        msOpenPracticeFromButton(button);
    });
});

if (msOpenMovementId > 0) {
    window.setTimeout(function () {
        const button = document.querySelector('.ms-edit[data-id="' + msOpenMovementId + '"]');
        if (!button) return;
        const row = button.closest('tr');
        if (row) {
            row.scrollIntoView({block: 'center'});
        }
        msOpenPracticeFromButton(button);
    }, 100);
}

function msUpdatePracticeKindFields() {
    const type = document.getElementById('ms_tipo_pratica').value || '';
    const kind = type === 'entrata' ? 'entrata' : 'uscita';
    const isEntry = type === 'entrata';
    const isExit = type === 'uscita';
    const isRepeat = type === 'bocciato_reiscrizione';
    const isWithdrawal = type === 'ritiro';
    msFilterStateOptions();
    document.querySelectorAll('.ms-only-entrata').forEach(function (element) {
        element.style.display = isEntry ? '' : 'none';
    });
    document.querySelectorAll('.ms-only-uscita').forEach(function (element) {
        element.style.display = isExit ? '' : 'none';
    });
    document.querySelectorAll('.ms-field-source-school').forEach(function (element) {
        element.style.display = isEntry ? '' : 'none';
    });
    document.querySelectorAll('.ms-field-destination-school, .ms-field-destination-address').forEach(function (element) {
        element.style.display = isExit ? '' : 'none';
    });
    document.querySelectorAll('.ms-field-year, .ms-field-requested-class').forEach(function (element) {
        element.style.display = isEntry ? '' : 'none';
    });
    document.querySelectorAll('.ms-address-gestore').forEach(function (element) {
        element.style.display = isEntry ? '' : 'none';
    });
    document.querySelectorAll('.ms-needs-nullosta').forEach(function (element) {
        element.style.display = (isEntry || isExit) ? '' : 'none';
    });
    msSetParentsVisible(msParentsExpanded);
    if (isEntry) {
        msSetField('ms_id_istituto_destinazione', '');
        msSetField('ms_scuola_destinazione', '');
        msSetField('ms_indirizzo_destinazione', '');
        msUpdateSchoolOther('ms_id_istituto_destinazione', 'ms_scuola_destinazione', 'ms_scuola_destinazione_altro', 'ms_scuola_destinazione_libera', '');
    } else {
        msSetField('ms_id_istituto_provenienza', '');
        msSetField('ms_scuola_provenienza', '');
        msSetField('ms_indirizzo_provenienza', '');
        msSetField('ms_esami_integrativi', '0');
        msSetSubjectValues('esami', []);
        msSetField('ms_carenze_presenti', '0');
        msSetSubjectValues('carenze', []);
        msUpdateSchoolOther('ms_id_istituto_provenienza', 'ms_scuola_provenienza', 'ms_scuola_provenienza_altro', 'ms_scuola_provenienza_libera', '');
    }
    if (isExit || isRepeat || isWithdrawal) {
        msSetField('ms_classe_richiesta', '');
        msSetField('ms_id_indirizzo_gestore', '');
    }
    if (isRepeat || isWithdrawal) {
        msSetField('ms_anno_corso', '');
        msSetField('ms_id_istituto_destinazione', '');
        msSetField('ms_scuola_destinazione', '');
        msSetField('ms_indirizzo_destinazione', '');
        msUpdateSchoolOther('ms_id_istituto_destinazione', 'ms_scuola_destinazione', 'ms_scuola_destinazione_altro', 'ms_scuola_destinazione_libera', '');
    }
    msUpdateSubjectBoxes();
}

document.getElementById('ms_tipo_pratica').addEventListener('change', function () {
    msFilterStateOptions();
    msUpdatePracticeKindFields();
    msRenderPracticeDocs(document.getElementById('ms_id').value || 0);
});

document.getElementById('ms_stato_pratica').addEventListener('change', function () {
    msRenderPracticeDocs(document.getElementById('ms_id').value || 0);
});

document.getElementById('ms_doppio_bocciato').addEventListener('change', function () {
    if (!this.checked) return;
    if (document.getElementById('ms_tipo_pratica').value === 'bocciato_reiscrizione') {
        msSetField('ms_tipo_pratica', 'uscita');
    }
    if (['da_verificare', 'reiscrizione_confermata', 'chiusa'].includes(document.getElementById('ms_stato_pratica').value)) {
        msSetField('ms_stato_pratica', 'cambia_scuola');
    }
    msUpdatePracticeKindFields();
    msRenderPracticeDocs(document.getElementById('ms_id').value || 0);
});

document.getElementById('ms_id_istituto_provenienza').addEventListener('change', function () {
    msUpdateSchoolOther('ms_id_istituto_provenienza', 'ms_scuola_provenienza', 'ms_scuola_provenienza_altro', 'ms_scuola_provenienza_libera', document.getElementById('ms_scuola_provenienza').value);
});

document.getElementById('ms_id_istituto_destinazione').addEventListener('change', function () {
    msUpdateSchoolOther('ms_id_istituto_destinazione', 'ms_scuola_destinazione', 'ms_scuola_destinazione_altro', 'ms_scuola_destinazione_libera', document.getElementById('ms_scuola_destinazione').value);
});

document.getElementById('ms_esami_integrativi').addEventListener('change', function () {
    msUpdateSubjectBoxes();
    msRenderPracticeDocs(document.getElementById('ms_id').value || 0);
});
document.getElementById('ms_carenze_presenti').addEventListener('change', function () {
    msUpdateSubjectBoxes();
    msRenderPracticeDocs(document.getElementById('ms_id').value || 0);
});
document.getElementById('ms_add_esame_materia').addEventListener('click', function () {
    msAddSubject('esami');
});
document.getElementById('ms_add_carenza_materia').addEventListener('click', function () {
    msAddSubject('carenze');
});

document.getElementById('ms_scuola_provenienza_altro').addEventListener('input', function () {
    msSyncSchoolHidden('ms_id_istituto_provenienza', 'ms_scuola_provenienza', 'ms_scuola_provenienza_altro');
});

document.getElementById('ms_scuola_destinazione_altro').addEventListener('input', function () {
    msSyncSchoolHidden('ms_id_istituto_destinazione', 'ms_scuola_destinazione', 'ms_scuola_destinazione_altro');
});

document.querySelectorAll('.ms-note-field').forEach(function (element) {
    element.addEventListener('input', msSyncVisibleNote);
});

document.getElementById('msPracticeForm').addEventListener('submit', function () {
    msShowWait('Salvataggio pratica', 'Sto salvando i dati della pratica...');
    const url = new URL(window.location.href);
    if (url.searchParams.has('open_movimento_id')) {
        url.searchParams.delete('open_movimento_id');
        window.history.replaceState({}, '', url.toString());
    }
    msSyncVisibleNote();
    msSyncSubjectNotes();
    msSyncSchoolHidden('ms_id_istituto_provenienza', 'ms_scuola_provenienza', 'ms_scuola_provenienza_altro');
    msSyncSchoolHidden('ms_id_istituto_destinazione', 'ms_scuola_destinazione', 'ms_scuola_destinazione_altro');
});

document.querySelectorAll('.ms-tabs a, .pagination a, a[href*="movimentiStudenti.php?sezione="]').forEach(function (link) {
    link.addEventListener('click', function () {
        msShowWait('Caricamento movimenti', 'Sto caricando la nuova vista...');
    });
});

document.querySelectorAll('form').forEach(function (form) {
    if (form.id === 'msInstantFilterForm') return;
    form.addEventListener('submit', function () {
        msShowWait('Operazione in corso', 'Attendere qualche secondo...');
    });
});

document.getElementById('ms_id_studente').addEventListener('change', function () {
    const option = this.options[this.selectedIndex];
    if (!option || !this.value) return;
    msSetField('ms_cognome', option.dataset.cognome || '');
    msSetField('ms_nome', option.dataset.nome || '');
    msSetField('ms_codice_fiscale', option.dataset.cf || '');
    msSetField('ms_classe_origine', option.dataset.classe || '');
    msSetField('ms_anno_corso', option.dataset.anno || '');
});
</script>
</body>
</html>
