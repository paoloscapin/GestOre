<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
require_once '../common/studentiMovimentiLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

iscrizioniPrimeEnsureSchema();
studentiMovimentiEnsureTables();
$tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($_GET['tipo_iscrizione'] ?? 'prime');
$pageTitle = $tipoIscrizione === 'terze' ? 'Domande iscrizioni terze' : 'Domande iscrizioni prime';
$returnPage = $tipoIscrizione === 'terze' ? 'iscrizioniTerze.php' : 'iscrizioniPrime.php';
$istitutiScuole = scuoleIstitutiAll();
$movimentiStati = studentiMovimentiStati();

function ipd_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ipd_confirmed(array $pratica): array
{
    $confirmed = [];
    if (!empty($pratica['dati_confermati_json'])) {
        $decoded = json_decode((string)$pratica['dati_confermati_json'], true);
        if (is_array($decoded)) {
            $confirmed = $decoded;
        }
    }
    return $confirmed;
}

function ipd_value(array $pratica, array $confirmed, string $field): string
{
    if (in_array($field, ['email_studente', 'telefono_studente', 'email_genitore_1', 'telefono_genitore_1', 'email_genitore_2', 'telefono_genitore_2'], true)) {
        return trim((string)($pratica[$field] ?? ''));
    }

    return trim((string)($confirmed[$field] ?? $pratica[$field] ?? ''));
}

function ipd_decode_json_field(array $pratica, string $field): array
{
    if (empty($pratica[$field])) {
        return [];
    }

    $decoded = json_decode((string)$pratica[$field], true);
    return is_array($decoded) ? $decoded : [];
}

function ipd_raw_value(array $sources, array $exactKeys, array $keywordSets = []): string
{
    foreach ($sources as $source) {
        foreach ($exactKeys as $key) {
            if (isset($source[$key]) && trim((string)$source[$key]) !== '') {
                return trim((string)$source[$key]);
            }
        }
    }

    foreach ($sources as $source) {
        foreach ($source as $key => $value) {
            $value = trim((string)$value);
            if ($value === '') {
                continue;
            }
            $normalizedKey = strtolower((string)$key);
            foreach ($keywordSets as $keywords) {
                $matches = true;
                foreach ($keywords as $keyword) {
                    if (strpos($normalizedKey, strtolower($keyword)) === false) {
                        $matches = false;
                        break;
                    }
                }
                if ($matches) {
                    return $value;
                }
            }
        }
    }

    return '';
}

function ipd_extra_info(array $pratica): array
{
    $sources = [
        ipd_decode_json_field($pratica, 'raw_anagrafica_json'),
        ipd_decode_json_field($pratica, 'raw_prime_json'),
        ipd_decode_json_field($pratica, 'raw_dsa_json'),
    ];

    $indirizzo = ipd_raw_value($sources, [
        'INDIRIZZO RESIDENZA',
        'INDIRIZZO_RESIDENZA',
        'RESIDENZA_INDIRIZZO',
        'VIA RESIDENZA',
        'INDIRIZZO',
    ], [['indirizzo', 'residenza'], ['via', 'residenza']]);
    $comune = ipd_raw_value($sources, [
        'COMUNE RESIDENZA',
        'COMUNE_RESIDENZA',
        'RESIDENZA_COMUNE',
        'LOCALITA RESIDENZA',
        'CITTA RESIDENZA',
        'COMUNE',
    ], [['comune', 'residenza'], ['citta', 'residenza'], ['localita', 'residenza']]);
    $provincia = ipd_raw_value($sources, [
        'PROVINCIA RESIDENZA',
        'PROVINCIA_RESIDENZA',
        'RESIDENZA_PROVINCIA',
        'PROV',
    ], [['provincia', 'residenza'], ['prov', 'residenza']]);
    $cap = ipd_raw_value($sources, [
        'CAP RESIDENZA',
        'CAP_RESIDENZA',
        'RESIDENZA_CAP',
        'CAP',
    ], [['cap', 'residenza']]);
    $scuola = ipd_raw_value($sources, [
        'SCUOLA DI PROVENIENZA',
        'SCUOLA PROVENIENZA',
        'DENOMINAZIONE SCUOLA PROVENIENZA',
        'SCUOLA MEDIA DI PROVENIENZA',
        'ISTITUTO DI PROVENIENZA',
        'SCUOLA UTENZA',
        'SCUOLA',
    ], [['scuola', 'provenienza'], ['istituto', 'provenienza'], ['scuola', 'media'], ['scuola', 'utenza']]);

    $residenzaParts = array_filter([$indirizzo, trim($cap . ' ' . $comune), $provincia], fn($value) => trim((string)$value) !== '');

    return [
        'residenza' => implode(' - ', $residenzaParts),
        'scuola_provenienza' => $scuola,
    ];
}

function ipd_badge_class(string $stato): string
{
    if ($stato === 'verificata') return 'label-success';
    if ($stato === 'verifica_iniziale_ok') return 'label-info';
    if ($stato === 'da_integrare') return 'label-warning';
    if ($stato === 'inviata') return 'label-primary';
    if ($stato === 'annullata') return 'label-danger';
    return 'label-default';
}

function ipd_stato_label(string $stato): string
{
    $labels = [
        'bozza' => 'Compilabile',
        'inviata' => 'Inviata',
        'verifica_iniziale_ok' => 'Verifica iniziale OK',
        'verificata' => 'Pratica completata',
        'da_integrare' => 'Da integrare',
        'annullata' => 'Cambio scuola',
    ];
    return $labels[$stato] ?? $stato;
}

function ipd_event_key(array $evento): string
{
    return implode('|', [
        (string)($evento['pratica_id'] ?? ''),
        (string)($evento['created_at'] ?? ''),
        (string)($evento['stato_precedente'] ?? ''),
        (string)($evento['stato_nuovo'] ?? ''),
        trim((string)($evento['messaggio'] ?? '')),
    ]);
}

function ipd_filter_duplicate_integration_events(array $eventi): array
{
    $integrationKeys = [];
    foreach ($eventi as $evento) {
        if (($evento['tipo_evento'] ?? '') === 'richiesta_integrazione') {
            $integrationKeys[ipd_event_key($evento)] = true;
        }
    }
    if (empty($integrationKeys)) {
        return $eventi;
    }

    return array_values(array_filter($eventi, static function (array $evento) use ($integrationKeys): bool {
        if (($evento['tipo_evento'] ?? '') !== 'cambio_stato') {
            return true;
        }
        if (($evento['stato_nuovo'] ?? '') !== 'da_integrare') {
            return true;
        }
        return !isset($integrationKeys[ipd_event_key($evento)]);
    }));
}

$filtroStato = trim((string)($_GET['stato'] ?? 'tutte'));
$openPraticaId = intval($_GET['open_pratica_id'] ?? 0);
$mostraCompletate = intval($_GET['mostra_completate'] ?? 0) === 1;
$allowedFilters = ['tutte', 'inviata', 'verifica_iniziale_ok', 'verificata', 'da_integrare', 'annullata'];
if (!in_array($filtroStato, $allowedFilters, true)) {
    $filtroStato = 'tutte';
}
if ($filtroStato === 'verificata') {
    $mostraCompletate = true;
}

$visibleStates = "'inviata', 'verifica_iniziale_ok', 'verificata', 'da_integrare', 'annullata'";
$where = "p.tipo_iscrizione = " . dbQ($tipoIscrizione) . " AND p.stato IN ($visibleStates)";
if ($filtroStato !== 'tutte') {
    $where = "p.tipo_iscrizione = " . dbQ($tipoIscrizione) . " AND p.stato = " . dbQ($filtroStato);
}
if ($openPraticaId > 0) {
    $where = "(" . $where . " OR (p.tipo_iscrizione = " . dbQ($tipoIscrizione) . " AND p.id = " . intval($openPraticaId) . "))";
}

$pratiche = dbGetAll("
    SELECT p.*,
           movimento_reiscrizione.id AS movimento_reiscrizione_id,
           movimento_reiscrizione.stato_pratica AS movimento_reiscrizione_stato,
           movimento_reiscrizione.classe_origine AS movimento_reiscrizione_classe_origine,
           movimento_reiscrizione.classe_richiesta AS movimento_reiscrizione_classe_richiesta,
           movimento_reiscrizione.updated_at AS movimento_reiscrizione_updated_at,
           COALESCE(documenti_caricati.totale_caricati, 0) AS totale_documenti_caricati
    FROM iscrizioni_prime_pratiche p
    LEFT JOIN (
        SELECT
            pratica_id,
            COUNT(*) AS totale_caricati
        FROM iscrizioni_prime_documenti
        WHERE (
            stato IN ('caricato', 'estratto', 'verificato')
            OR COALESCE(file_path, '') <> ''
            OR COALESCE(drive_file_id, '') <> ''
        )
        GROUP BY pratica_id
    ) documenti_caricati ON documenti_caricati.pratica_id = p.id
    LEFT JOIN studenti_movimenti_pratiche movimento_reiscrizione
      ON movimento_reiscrizione.id = (
          SELECT m2.id
          FROM studenti_movimenti_pratiche m2
          WHERE m2.tipo_pratica = 'bocciato_reiscrizione'
            AND m2.stato_pratica <> 'annullata'
            AND m2.codice_fiscale IS NOT NULL
            AND m2.codice_fiscale <> ''
            AND UPPER(TRIM(m2.codice_fiscale)) = UPPER(TRIM(p.codice_fiscale))
          ORDER BY m2.updated_at DESC, m2.id DESC
          LIMIT 1
      )
    WHERE $where
    ORDER BY
        CASE p.stato
            WHEN 'inviata' THEN 0
            WHEN 'da_integrare' THEN 1
            WHEN 'verifica_iniziale_ok' THEN 2
            WHEN 'annullata' THEN 3
            WHEN 'verificata' THEN 4
            ELSE 9
        END,
        CASE WHEN p.stato = 'inviata' THEN COALESCE(documenti_caricati.totale_caricati, 0) ELSE 0 END DESC,
        p.updated_at DESC,
        p.cognome ASC,
        p.nome ASC
");

$stats = dbGetFirst("
    SELECT
        SUM(stato = 'inviata') AS inviate,
        SUM(stato = 'verifica_iniziale_ok') AS verifica_iniziale_ok,
        SUM(stato = 'verificata') AS verificate,
        SUM(stato = 'da_integrare') AS da_integrare,
        SUM(stato = 'annullata') AS annullate
    FROM iscrizioni_prime_pratiche
    WHERE tipo_iscrizione = " . dbQ($tipoIscrizione) . "
");
$statsTotale = intval($stats['inviate'] ?? 0)
    + intval($stats['verifica_iniziale_ok'] ?? 0)
    + intval($stats['verificate'] ?? 0)
    + intval($stats['da_integrare'] ?? 0)
    + intval($stats['annullate'] ?? 0);

$labels = array_merge(iscrizioniPrimeDocumentTypes($tipoIscrizione), iscrizioniPrimeSecretaryDocumentTypes($tipoIscrizione), [
    'nulla_osta' => 'Nulla osta',
]);
$eventiPratiche = [];
foreach ($pratiche as $praticaEvento) {
    $eventiPratiche[intval($praticaEvento['id'] ?? 0)] = ipd_filter_duplicate_integration_events(iscrizioniPrimeEventsForPratica($praticaEvento));
}

function ipd_filter_url(string $tipoIscrizione, string $stato, bool $mostraCompletate): string
{
    $params = [
        'tipo_iscrizione' => $tipoIscrizione,
        'stato' => $stato,
    ];
    if ($mostraCompletate || $stato === 'verificata') {
        $params['mostra_completate'] = '1';
    }
    return '?' . http_build_query($params);
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <title><?php echo ipd_h($pageTitle); ?></title>
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    ?>
    <style>
        .ipd-top {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 14px;
            align-items: start;
        }
        .ipd-title-sub {
            color: #475569;
            font-weight: 650;
            margin-top: 2px;
        }
        .ipd-top-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: flex-end;
        }
        .ipd-stats {
            display: grid;
            grid-template-columns: repeat(6, minmax(110px, 1fr));
            gap: 8px;
            margin: 12px 0 10px;
        }
        .ipd-stat {
            display: block;
            border: 1px solid #dbe4ef;
            border-left: 5px solid #94a3b8;
            border-radius: 8px;
            background: #fff;
            padding: 7px 10px;
            color: #0f172a;
            text-decoration: none;
            min-height: 56px;
        }
        .ipd-stat:hover,
        .ipd-stat:focus {
            text-decoration: none;
            background: #f8fafc;
        }
        .ipd-stat.active {
            border-color: #2563eb;
            border-left-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
        }
        .ipd-stat .num {
            display: block;
            font-size: 21px;
            line-height: 1;
            font-weight: 850;
        }
        .ipd-stat .label {
            display: block;
            padding: 0;
            margin-top: 5px;
            background: transparent;
            color: #475569;
            font-size: 12px;
            text-align: left;
            white-space: normal;
        }
        .ipd-stat.inviata { border-left-color: #2563eb; }
        .ipd-stat.verifica { border-left-color: #0891b2; }
        .ipd-stat.completata { border-left-color: #16a34a; }
        .ipd-stat.integrazione { border-left-color: #f59e0b; }
        .ipd-stat.cambio { border-left-color: #dc2626; }
        .ipd-stat.tutte { border-left-color: #64748b; }
        .ipd-toolbar {
            display: grid;
            grid-template-columns: minmax(260px, 1fr) auto;
            gap: 8px 12px;
            align-items: center;
            margin-bottom: 8px;
            padding: 10px;
            border: 1px solid #dbe4ef;
            border-radius: 8px;
            background: #f8fafc;
        }
        .ipd-toolbar label { display: block; margin: 0 0 7px; }
        .ipd-card { border: 1px solid #d9e0ea; border-radius: 6px; margin-bottom: 14px; background: #fff; box-shadow: 0 3px 14px rgba(0,0,0,.06); }
        .ipd-card.ipd-target { border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37, 99, 235, .16), 0 8px 24px rgba(15, 23, 42, .12); }
        .ipd-filter-box input { width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 9px 12px; font-size: 15px; background: #fff; }
        .ipd-filter-count { display: inline-block; margin-top: 6px; color: #64748b; font-weight: 700; }
        .ipd-bulk-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: flex-end;
            align-items: center;
            align-self: end;
            padding-bottom: 1px;
        }
        .ipd-bulk-actions .btn { height: 38px; }
        .ipd-progress { width: 100%; height: 14px; border-radius: 999px; background: #e2e8f0; overflow: hidden; margin-top: 8px; }
        .ipd-progress > span { display: block; height: 100%; width: 0; background: #1d4ed8; transition: width .25s ease; }
        .ipd-card-head { padding: 12px 14px; border-bottom: 1px solid #e8edf4; background: #f8fafc; display: flex; flex-direction: column; gap: 10px; align-items: stretch; }
        .ipd-card-body { padding: 14px; }
        .ipd-name { font-size: 18px; font-weight: 700; }
        .ipd-meta { color: #64748b; margin-top: 3px; }
        .ipd-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px 14px; margin-bottom: 12px; }
        .ipd-field { border-bottom: 1px solid #edf1f6; padding-bottom: 6px; }
        .ipd-label { color: #64748b; font-size: 12px; }
        .ipd-value { font-weight: 650; overflow-wrap: anywhere; }
        .ipd-doc-status { font-weight: 700; }
        .ipd-doc-status.ok { color: #166534; }
        .ipd-doc-status.paper { color: #92400e; }
        .ipd-doc-status.optional { color: #64748b; }
        .ipd-doc-status.missing { color: #b91c1c; }
        .ipd-empty { padding: 18px; color: #64748b; }
        .ipd-help-tip {
            position: relative;
            display: inline-block;
        }
        .ipd-help-tip > button {
            font-weight: 700;
        }
        .ipd-help-tip .ipd-help-popup {
            display: none;
            position: absolute;
            right: 0;
            bottom: calc(100% + 8px);
            z-index: 50;
            width: min(560px, calc(100vw - 40px));
            padding: 12px 14px;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            background: #fff;
            color: #1e293b;
            box-shadow: 0 14px 38px rgba(15, 23, 42, .20);
            line-height: 1.45;
            text-align: left;
            font-weight: 500;
        }
        .ipd-help-tip .ipd-help-popup strong {
            display: block;
            margin-bottom: 5px;
            color: #0f172a;
        }
        .ipd-help-tip:hover .ipd-help-popup,
        .ipd-help-tip:focus-within .ipd-help-popup {
            display: block;
        }
        .ipd-help-tip .ipd-help-popup:after {
            content: "";
            position: absolute;
            right: 18px;
            top: 100%;
            border: 8px solid transparent;
            border-top-color: #fff;
        }
        .ipd-status-actions { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; justify-content: flex-start; }
        .ipd-status-actions .btn-group { display: flex; flex-wrap: wrap; gap: 0; }
        .ipd-card-body { display: none; }
        .ipd-card.open .ipd-card-body { display: block; }
        .ipd-summary-line { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 5px; color: #64748b; }
        .ipd-pill { display: inline-block; border-radius: 999px; background: #e2e8f0; color: #334155; padding: 2px 8px; font-size: 12px; font-weight: 700; }
        .ipd-pill.ok { background: #dcfce7; color: #166534; }
        .ipd-pill.paper { background: #fef3c7; color: #92400e; }
        .ipd-pill.missing { background: #fee2e2; color: #991b1b; }
        .ipd-pill.news { background: #f97316; color: #fff; box-shadow: 0 0 0 2px rgba(249,115,22,.18); }
        .ipd-pill.reiscrizione { background: #dcfce7; color: #166534; }
        .ipd-news-box { border: 2px solid #fb923c; border-left-width: 7px; background: #fff7ed; color: #7c2d12; border-radius: 8px; padding: 10px 12px; margin-bottom: 12px; font-weight: 750; }
        .ipd-news-box small { display: block; margin-top: 4px; color: #9a3412; font-weight: 650; }
        .ipd-movement-box { border: 1px solid #bbf7d0; border-left: 5px solid #16a34a; background: #f0fdf4; border-radius: 6px; padding: 10px 12px; margin-bottom: 12px; color: #14532d; }
        .ipd-movement-box.pending { border-color: #fde68a; border-left-color: #f59e0b; background: #fffbeb; color: #78350f; }
        .ipd-movement-box a { font-weight: 700; }
        .ipd-terze-values { border: 1px solid #c7d2fe; border-left: 5px solid #4f46e5; background: #eef2ff; border-radius: 8px; padding: 12px; margin: 14px 0 18px; }
        .ipd-terze-values h4 { margin: 0 0 8px; color: #312e81; }
        .ipd-terze-values-grid { display: grid; grid-template-columns: repeat(4, minmax(110px, 1fr)); gap: 10px; align-items: end; }
        .ipd-terze-values label { display: block; color: #475569; font-size: 12px; margin-bottom: 4px; }
        .ipd-terze-values input { width: 100%; border: 1px solid #b7c4e8; border-radius: 6px; padding: 8px 9px; background: #fff; }
        .ipd-terze-values-status { margin-top: 8px; color: #475569; font-weight: 650; }
        .ipd-toggle { min-width: 92px; }
        .ipd-secretary-docs { margin-top: 18px; padding: 12px; border: 1px solid #bfdbfe; border-radius: 6px; background: #eff6ff; }
        .ipd-secretary-docs h4 { margin-top: 0; }
        .ipd-file-line { display: flex; align-items: center; gap: 6px; flex-wrap: nowrap; min-width: 0; }
        .ipd-file-name { color: #64748b; min-width: 80px; max-width: 360px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ipd-secretary-upload { display: inline-flex; gap: 6px; align-items: center; flex-wrap: nowrap; margin: 0; }
        .ipd-secretary-upload input[type="file"] { width: 230px; max-width: 24vw; }
        .ipd-secretary-upload-tools { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 4px 0 10px; }
        .ipd-secretary-upload-status { color: #475569; font-weight: 700; }
        .ipd-modal-backdrop { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(15,23,42,.62); z-index: 4000; padding: 16px; }
        .ipd-modal-backdrop.open { display: flex; }
        .ipd-modal-box { width: min(620px, 100%); background: #fff; border-radius: 8px; box-shadow: 0 22px 56px rgba(0,0,0,.28); overflow: hidden; }
        .ipd-modal-head { padding: 14px 16px; background: #92400e; color: #fff; font-weight: 800; font-size: 18px; }
        .ipd-modal-body { padding: 16px; }
        .ipd-modal-body input[type="text"] { width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 9px 10px; }
        .ipd-modal-body select { width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 9px 10px; background: #fff; }
        .ipd-modal-body textarea { width: 100%; min-height: 150px; resize: vertical; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; }
        .ipd-rich-tools { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 6px; }
        .ipd-rich-tools .btn { font-weight: 700; }
        .ipd-modal-field { margin-bottom: 12px; }
        .ipd-modal-field label { display: block; margin-bottom: 5px; font-weight: 700; }
        .ipd-modal-field .help-block { margin: 4px 0 0; }
        .ipd-modal-actions { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 16px; border-top: 1px solid #e5e7eb; background: #f8fafc; }
        .ipd-busy-box { width: min(420px, 100%); background: #fff; border-radius: 8px; box-shadow: 0 20px 55px rgba(15,23,42,.35); padding: 22px; text-align: center; }
        .ipd-busy-spinner { width: 42px; height: 42px; border: 4px solid #dbeafe; border-top-color: #2563eb; border-radius: 50%; margin: 0 auto 14px; animation: ipdSpin .8s linear infinite; }
        @keyframes ipdSpin { to { transform: rotate(360deg); } }
        .ipd-cambio-layout { display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(360px, .95fr); gap: 16px; align-items: start; }
        .ipd-cambio-history { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; background: #fff; max-height: calc(100vh - 210px); overflow: auto; }
        .ipd-cambio-event { border: 1px solid #dbe4ef; border-left: 5px solid #7f1d1d; border-radius: 6px; padding: 10px 12px; margin-bottom: 8px; background: #f8fafc; }
        .ipd-cambio-event-head { display: flex; justify-content: space-between; gap: 8px; flex-wrap: wrap; font-weight: 800; }
        .ipd-cambio-event-meta { color: #64748b; margin-top: 4px; }
        .ipd-cambio-event-note { margin-top: 6px; white-space: pre-wrap; }
        @media (max-width: 900px) {
            .ipd-top { grid-template-columns: 1fr; }
            .ipd-top-actions { justify-content: flex-start; }
            .ipd-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .ipd-toolbar { grid-template-columns: 1fr; }
            .ipd-bulk-actions { justify-content: flex-start; }
            .ipd-grid { grid-template-columns: 1fr; }
            .ipd-terze-values-grid { grid-template-columns: 1fr 1fr; }
            .ipd-status-actions { justify-content: flex-start; }
            .ipd-cambio-layout { grid-template-columns: 1fr; }
            .ipd-cambio-history { max-height: none; }
        }
    </style>
</head>
<body>
<?php require_once '../common/header-didattica.php'; ?>

<div class="container-fluid">
    <div class="panel panel-lightblue4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-inbox"></span>&ensp;<?php echo ipd_h($pageTitle); ?>
        </div>
        <div class="panel-body">
            <div class="ipd-top">
                <div>
                    <div class="ipd-title-sub">
                        <?php echo $tipoIscrizione === 'terze' ? 'Pratiche esterne future terze' : 'Pratiche future prime'; ?>
                    </div>
                </div>
                <div class="ipd-top-actions">
                    <a class="btn btn-default btn-sm" href="iscrizioniContattiVariazioni.php?tipo_iscrizione=<?php echo urlencode($tipoIscrizione); ?>">
                        <span class="glyphicon glyphicon-transfer"></span> Variazioni contatti
                    </a>
                    <a class="btn btn-default btn-sm" href="<?php echo ipd_h($returnPage); ?>">
                        <span class="glyphicon glyphicon-arrow-left"></span> Torna a import/invio link
                    </a>
                </div>
            </div>

            <div class="ipd-stats">
                <?php
                $statCards = [
                    ['key' => 'inviata', 'class' => 'inviata', 'label' => 'Inviate', 'value' => intval($stats['inviate'] ?? 0)],
                    ['key' => 'verifica_iniziale_ok', 'class' => 'verifica', 'label' => 'Verifica iniziale OK', 'value' => intval($stats['verifica_iniziale_ok'] ?? 0)],
                    ['key' => 'verificata', 'class' => 'completata', 'label' => 'Completate', 'value' => intval($stats['verificate'] ?? 0)],
                    ['key' => 'da_integrare', 'class' => 'integrazione', 'label' => 'Da integrare', 'value' => intval($stats['da_integrare'] ?? 0)],
                    ['key' => 'annullata', 'class' => 'cambio', 'label' => 'Cambio scuola', 'value' => intval($stats['annullate'] ?? 0)],
                    ['key' => 'tutte', 'class' => 'tutte', 'label' => 'Tutte', 'value' => $statsTotale],
                ];
                ?>
                <?php foreach ($statCards as $card) : ?>
                    <a class="ipd-stat <?php echo ipd_h($card['class']); ?> <?php echo $filtroStato === $card['key'] ? 'active' : ''; ?>"
                       href="<?php echo ipd_h(ipd_filter_url($tipoIscrizione, (string)$card['key'], $mostraCompletate)); ?>">
                        <span class="num"><?php echo intval($card['value']); ?></span>
                        <span class="label"><?php echo ipd_h($card['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="ipd-toolbar">
                <div class="ipd-filter-box">
                    <label for="ipdLiveFilter">Cerca pratica</label><br>
                    <input type="search" id="ipdLiveFilter" placeholder="Scrivi nome, cognome, codice fiscale, corso, email, scuola...">
                    <span id="ipdFilterCount" class="ipd-filter-count"></span>
                </div>
                <div class="ipd-bulk-actions">
                    <button type="button" class="btn btn-primary" onclick="ipdOpenBulkMailModal()">
                        <span class="glyphicon glyphicon-envelope"></span> Scrivi a tutti i genitori
                    </button>
                    <button type="button" class="btn btn-default" onclick="document.getElementById('ipdLiveFilter').value=''; ipdApplyLiveFilter();">
                        <span class="glyphicon glyphicon-remove"></span> Pulisci ricerca
                    </button>
                    <button type="button" id="ipdToggleCompletedButton" class="btn <?php echo $mostraCompletate ? 'btn-warning' : 'btn-default'; ?>" onclick="ipdToggleCompleted()">
                        <span class="glyphicon glyphicon-<?php echo $mostraCompletate ? 'eye-close' : 'eye-open'; ?>"></span>
                        <span class="ipd-toggle-completed-label"><?php echo $mostraCompletate ? 'Nascondi completate' : 'Mostra completate'; ?></span>
                    </button>
                    <span class="ipd-help-tip">
                        <button type="button" class="btn btn-default">
                            <span class="glyphicon glyphicon-question-sign"></span> Uso stati
                        </button>
                        <span class="ipd-help-popup" role="tooltip">
                            <strong>Uso degli stati</strong>
                            "Verifica iniziale OK" registra che quanto caricato e' corretto e che eventuali documenti cartacei sono attesi.
                            "Pratica completata" chiude definitivamente la pratica.
                            "Richiedi integrazione" riapre la pratica, invia una mail ai genitori con le indicazioni della segreteria e permette di correggere/reinviare.
                            "Rimetti in inviata" riporta una domanda allo stato ricevuto senza inviare mail.
                            "Rendi compilabile" riapre il link genitore senza inviare mail.
                            "Cambio scuola" mantiene la pratica archiviata ma la esclude dagli invii massivi.
                        </span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$pratiche) : ?>
        <div class="panel panel-default"><div class="ipd-empty">Nessuna domanda nel filtro selezionato.</div></div>
    <?php endif; ?>

    <?php foreach ($pratiche as $pratica) :
        $confirmed = ipd_confirmed($pratica);
        $documents = iscrizioniPrimeDocumentsForPratica((int)$pratica['id']);
        $secretaryDocuments = $tipoIscrizione === 'terze' ? iscrizioniPrimeSecretaryDocumentsForPratica((int)$pratica['id']) : [];
        $nome = trim((string)(($pratica['cognome'] ?? '') . ' ' . ($pratica['nome'] ?? '')));
        $extraInfo = ipd_extra_info($pratica);
        $docCounts = ['ok' => 0, 'paper' => 0, 'missing' => 0];
        $optionalDocumentTypes = iscrizioniPrimeOptionalDocumentTypes();
        foreach ($documents as $documentCountRow) {
            $tipoCount = (string)$documentCountRow['tipo_documento'];
            if (in_array($tipoCount, $optionalDocumentTypes, true) && (string)$documentCountRow['stato'] === 'mancante') {
                continue;
            }
            if (in_array($tipoCount, ['documento_identita_genitore_2', 'codice_fiscale_genitore_2', 'documento_cf_genitore_2'], true) && !hasSecondResponsibleForIscrizioniPrime($pratica, $confirmed)) {
                continue;
            }
            $statoCount = (string)$documentCountRow['stato'];
            if ($statoCount === 'consegna_cartacea') {
                $docCounts['paper']++;
            } elseif (in_array($statoCount, ['caricato', 'estratto', 'verificato'], true)) {
                $docCounts['ok']++;
            } else {
                $docCounts['missing']++;
            }
        }
        $movementId = intval($pratica['movimento_reiscrizione_id'] ?? 0);
        $movementState = (string)($pratica['movimento_reiscrizione_stato'] ?? '');
        $movementLabel = $movimentiStati[$movementState] ?? $movementState;
        $movementDone = $movementId > 0 && in_array($movementState, ['reiscrizione_confermata', 'chiusa'], true);
        $movementUrl = 'movimentiStudenti.php?sezione=uscite&open_movimento_id=' . $movementId;
    ?>
        <div class="ipd-card" id="pratica-<?php echo intval($pratica['id']); ?>" data-stato="<?php echo ipd_h((string)$pratica['stato']); ?>">
            <div class="ipd-card-head">
                <div>
                    <div class="ipd-name"><?php echo ipd_h($nome); ?></div>
                    <div class="ipd-meta">
                        <?php echo ipd_h($pratica['codice_fiscale'] ?? ''); ?> ·
                        <?php echo ipd_h($pratica['corso_studi'] ?? ''); ?> ·
                        aggiornata <?php echo ipd_h(iscrizioniPrimeFormatDateIt($pratica['updated_at'] ?? '')); ?>
                    </div>
                    <div class="ipd-summary-line">
                        <span class="ipd-pill ok"><?php echo intval($docCounts['ok']); ?> caricati</span>
                        <span class="ipd-pill paper"><?php echo intval($docCounts['paper']); ?> cartacei</span>
                        <?php if ($docCounts['missing'] > 0) : ?>
                            <span class="ipd-pill missing"><?php echo intval($docCounts['missing']); ?> mancanti</span>
                        <?php endif; ?>
                        <?php if (!empty($pratica['novita_segreteria_at'])) : ?>
                            <span class="ipd-pill news">Novita' per segreteria</span>
                        <?php endif; ?>
                        <?php if (trim((string)($pratica['note_genitori_iscrizione'] ?? '')) !== '') : ?>
                            <span class="ipd-pill news" title="<?php echo ipd_h($pratica['note_genitori_iscrizione']); ?>">Note genitori</span>
                        <?php endif; ?>
                        <?php if ($tipoIscrizione === 'terze' && trim((string)($pratica['curvatura_design'] ?? '')) !== '') : ?>
                            <span class="ipd-pill <?php echo $pratica['curvatura_design'] === 'design' ? 'ok' : 'paper'; ?>">
                                Design: <?php echo $pratica['curvatura_design'] === 'design' ? 'si' : 'no'; ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($movementId > 0) : ?>
                            <span class="ipd-pill reiscrizione"><?php echo $movementDone ? 'Reiscrizione gia sistemata' : 'Reiscrizione in gestione'; ?></span>
                        <?php endif; ?>
                        <?php if ($extraInfo['scuola_provenienza'] !== '') : ?>
                            <span class="ipd-pill">Scuola: <?php echo ipd_h($extraInfo['scuola_provenienza']); ?></span>
                        <?php endif; ?>
                        <?php if (intval($pratica['tablet_scelto'] ?? 0) === 1) : ?>
                            <?php
                            $tabletStato = (string)($pratica['tablet_stato'] ?? '');
                            $tabletGroup = (string)($pratica['tablet_gruppo'] ?? '');
                            $tabletText = iscrizioniPrimeTabletStatusLabel($tabletStato);
                            if ($tabletGroup !== '') {
                                $tabletText .= ' - ' . iscrizioniPrimeTabletGroupLabel($tabletGroup);
                            }
                            if (!empty($pratica['tablet_posizione'])) {
                                $tabletText .= ' pos. ' . intval($pratica['tablet_posizione']);
                            }
                            if ($tabletStato === 'confermato') {
                                $tabletText .= intval($pratica['tablet_acquistato'] ?? 0) === 1 ? ' - acquistato' : ' - da acquistare';
                            }
                            ?>
                            <span class="ipd-pill <?php echo $tabletStato === 'rinuncia' ? 'missing' : ($tabletStato === 'confermato' ? 'ok' : 'paper'); ?>">Tablet: <?php echo ipd_h($tabletText); ?></span>
                        <?php endif; ?>
                        <?php if ($extraInfo['residenza'] !== '') : ?>
                            <span class="ipd-pill">Residenza: <?php echo ipd_h($extraInfo['residenza']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ipd-status-actions">
                    <span class="label <?php echo ipd_badge_class((string)$pratica['stato']); ?>"><?php echo ipd_h(ipd_stato_label((string)$pratica['stato'])); ?></span>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-info ipd-toggle" onclick="ipdToggleDettagli(<?php echo intval($pratica['id']); ?>, this)">Dettagli</button>
                        <button type="button" class="btn btn-primary" title="Invia una comunicazione personalizzata ai genitori collegati alla pratica." onclick="ipdOpenCustomMailModal(<?php echo intval($pratica['id']); ?>, <?php echo ipd_h(json_encode($nome, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>, <?php echo ipd_h(json_encode([
                            'genitore1' => strtolower(trim((string)($pratica['email_genitore_1'] ?? ''))),
                            'genitore2' => strtolower(trim((string)($pratica['email_genitore_2'] ?? ''))),
                        ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)">Scrivi ai genitori</button>
                        <button type="button" class="btn btn-info" title="Quanto caricato e' stato controllato; restano eventuali documenti cartacei attesi." onclick="ipdSetStato(<?php echo intval($pratica['id']); ?>, 'verifica_iniziale_ok')">Verifica iniziale OK</button>
                        <button type="button" class="btn btn-success" title="La pratica e' completa e viene chiusa definitivamente." onclick="ipdSetStato(<?php echo intval($pratica['id']); ?>, 'verificata')">Pratica completata</button>
                        <button type="button" class="btn btn-warning" title="Riapre la pratica e invia una mail ai genitori." onclick="ipdSetStato(<?php echo intval($pratica['id']); ?>, 'da_integrare')">Richiedi integrazione</button>
                        <button type="button" class="btn btn-default" title="Riporta la pratica allo stato ricevuto/inviata." onclick="ipdSetStato(<?php echo intval($pratica['id']); ?>, 'inviata')">Rimetti in inviata</button>
                        <button type="button" class="btn btn-default" title="Rende di nuovo apribile il link genitore senza inviare automaticamente una mail." onclick="ipdSetStato(<?php echo intval($pratica['id']); ?>, 'bozza')">Rendi compilabile</button>
                        <button type="button" class="btn btn-danger" title="La famiglia ha cambiato scuola: la pratica resta archiviata ma non riceve piu comunicazioni automatiche." onclick="ipdOpenCambioScuolaModal(<?php echo intval($pratica['id']); ?>)">Cambio scuola</button>
                    </div>
                </div>
            </div>
            <div class="ipd-card-body">
                <?php if (!empty($pratica['novita_segreteria_at'])) : ?>
                    <div class="ipd-news-box">
                        Ci sono novita' da controllare: <?php echo ipd_h($pratica['novita_segreteria_messaggio'] ?: 'la pratica e stata aggiornata dalla famiglia.'); ?>
                        <small>Aggiornamento registrato il <?php echo ipd_h(iscrizioniPrimeFormatDateTimeIt($pratica['novita_segreteria_at'])); ?>. Il flag viene tolto quando registri la verifica iniziale o completi la pratica.</small>
                    </div>
                <?php endif; ?>
                <?php if ($movementId > 0) : ?>
                    <div class="ipd-movement-box <?php echo $movementDone ? '' : 'pending'; ?>">
                        <strong><?php echo $movementDone ? 'Reiscrizione gia sistemata in Entrate / uscite.' : 'Reiscrizione presente in Entrate / uscite.'; ?></strong>
                        Stato movimento: <?php echo ipd_h($movementLabel); ?>.
                        <?php if (!empty($pratica['movimento_reiscrizione_classe_origine']) || !empty($pratica['movimento_reiscrizione_classe_richiesta'])) : ?>
                            <br>Classe: <?php echo ipd_h(trim((string)($pratica['movimento_reiscrizione_classe_origine'] ?? ''))); ?>
                            <?php if (!empty($pratica['movimento_reiscrizione_classe_richiesta'])) : ?>
                                &rarr; <?php echo ipd_h((string)$pratica['movimento_reiscrizione_classe_richiesta']); ?>
                            <?php endif; ?>
                        <?php endif; ?>
                        <br><a href="<?php echo ipd_h($movementUrl); ?>" target="_blank" rel="noopener">Apri pratica movimenti</a>
                    </div>
                <?php endif; ?>
                <div class="ipd-grid">
                    <div class="ipd-field"><div class="ipd-label">Data nascita</div><div class="ipd-value"><?php echo ipd_h(iscrizioniPrimeFormatDateIt($pratica['data_nascita'] ?? '')); ?></div></div>
                    <div class="ipd-field"><div class="ipd-label">Email studente</div><div class="ipd-value"><?php echo ipd_h(ipd_value($pratica, $confirmed, 'email_studente')); ?></div></div>
                    <div class="ipd-field"><div class="ipd-label">Telefono studente</div><div class="ipd-value"><?php echo ipd_h(ipd_value($pratica, $confirmed, 'telefono_studente')); ?></div></div>
                    <div class="ipd-field"><div class="ipd-label"><?php echo ipd_h($pratica['responsabile_1_tipo'] ?: 'Responsabile 1'); ?></div><div class="ipd-value"><?php echo ipd_h(trim(($pratica['responsabile_1_cognome'] ?? '') . ' ' . ($pratica['responsabile_1_nome'] ?? ''))); ?></div></div>
                    <div class="ipd-field"><div class="ipd-label">Email responsabile 1</div><div class="ipd-value"><?php echo ipd_h(ipd_value($pratica, $confirmed, 'email_genitore_1')); ?></div></div>
                    <div class="ipd-field"><div class="ipd-label">Telefono responsabile 1</div><div class="ipd-value"><?php echo ipd_h(ipd_value($pratica, $confirmed, 'telefono_genitore_1')); ?></div></div>
                    <div class="ipd-field"><div class="ipd-label"><?php echo ipd_h($pratica['responsabile_2_tipo'] ?: 'Responsabile 2'); ?></div><div class="ipd-value"><?php echo ipd_h(trim(($pratica['responsabile_2_cognome'] ?? '') . ' ' . ($pratica['responsabile_2_nome'] ?? ''))); ?></div></div>
                    <div class="ipd-field"><div class="ipd-label">Email responsabile 2</div><div class="ipd-value"><?php echo ipd_h(ipd_value($pratica, $confirmed, 'email_genitore_2')); ?></div></div>
                    <div class="ipd-field"><div class="ipd-label">Telefono responsabile 2</div><div class="ipd-value"><?php echo ipd_h(ipd_value($pratica, $confirmed, 'telefono_genitore_2')); ?></div></div>
                    <div class="ipd-field"><div class="ipd-label">Residenza</div><div class="ipd-value"><?php echo ipd_h($extraInfo['residenza'] ?: 'Non disponibile nei dati importati'); ?></div></div>
                    <div class="ipd-field"><div class="ipd-label">Scuola di provenienza</div><div class="ipd-value"><?php echo ipd_h($extraInfo['scuola_provenienza'] ?: 'Non disponibile nei dati importati'); ?></div></div>
                    <?php if ($tipoIscrizione === 'prime') : ?>
                        <div class="ipd-field"><div class="ipd-label">Esame scuola media</div><div class="ipd-value"><?php echo ipd_h(trim((string)($pratica['voto_esame_licenza'] ?? '')) !== '' ? ('Voto ' . $pratica['voto_esame_licenza']) : 'Non disponibile'); ?><?php if (trim((string)($pratica['esito_esame_licenza'] ?? '')) !== '') : ?><br><span class="text-muted"><?php echo ipd_h($pratica['esito_esame_licenza']); ?></span><?php endif; ?></div></div>
                    <?php endif; ?>
                    <div class="ipd-field">
                        <div class="ipd-label">Note genitori per iscrizione/formazione</div>
                        <div class="ipd-value" id="ipd_note_formazione_value_<?php echo intval($pratica['id']); ?>">
                            <?php echo trim((string)($pratica['note_genitori_iscrizione'] ?? '')) !== '' ? nl2br(ipd_h($pratica['note_genitori_iscrizione'])) : '<span class="text-muted">Nessuna nota inserita.</span>'; ?>
                        </div>
                        <button type="button" class="btn btn-xs btn-default" id="ipd_note_formazione_button_<?php echo intval($pratica['id']); ?>" style="margin-top:6px;" onclick='ipdOpenFormationNoteModal(<?php echo intval($pratica['id']); ?>, <?php echo json_encode(trim(($pratica['cognome'] ?? '') . ' ' . ($pratica['nome'] ?? '')), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, <?php echo json_encode((string)($pratica['note_genitori_iscrizione'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                            <span class="glyphicon glyphicon-pencil"></span> Modifica note
                        </button>
                    </div>
                    <?php if ($tipoIscrizione === 'terze' && trim((string)($pratica['curvatura_design'] ?? '')) !== '') : ?>
                        <div class="ipd-field"><div class="ipd-label">Curvatura CAT</div><div class="ipd-value"><?php echo ipd_h($pratica['curvatura_design'] === 'design' ? 'Design e riqualificazione ambientale' : 'Normale'); ?></div></div>
                    <?php endif; ?>
                </div>

                <?php
                $carenzeDichiarate = (string)($confirmed['carenze_formative_dichiarate'] ?? ($pratica['carenze_formative_dichiarate'] ?? ''));
                $carenzeMaterie = $confirmed['carenze_formative_materie'] ?? null;
                if (!is_array($carenzeMaterie)) {
                    $decodedCarenzeMaterie = json_decode((string)($pratica['carenze_formative_materie'] ?? '[]'), true);
                    $carenzeMaterie = is_array($decodedCarenzeMaterie) ? $decodedCarenzeMaterie : [];
                }
                $carenzeAltro = trim((string)($confirmed['carenze_formative_altro'] ?? ($pratica['carenze_formative_altro'] ?? '')));
                if ($carenzeAltro !== '' && !in_array($carenzeAltro, $carenzeMaterie, true)) {
                    $carenzeMaterie[] = $carenzeAltro;
                }
                $carenzeLabel = $carenzeDichiarate === 'si' ? 'Si' : ($carenzeDichiarate === 'no' ? 'No' : 'Non indicato');
                ?>
                <h4>Carenze formative</h4>
                <div class="ipd-grid">
                    <div class="ipd-field"><div class="ipd-label">Carenze dichiarate dal genitore</div><div class="ipd-value"><?php echo ipd_h($carenzeLabel); ?></div></div>
                    <div class="ipd-field"><div class="ipd-label">Materie indicate</div><div class="ipd-value"><?php echo ipd_h($carenzeDichiarate === 'si' ? (implode(', ', array_values(array_filter($carenzeMaterie, 'strlen'))) ?: 'Non specificate') : '-'); ?></div></div>
                </div>

                <?php if ($tipoIscrizione === 'terze') : ?>
                    <form class="ipd-terze-values" onsubmit="return ipdSaveTerzeValues(event, <?php echo intval($pratica['id']); ?>);">
                        <h4>Valori pagella seconda per formazione classi</h4>
                        <div class="text-muted" style="margin-bottom:10px;">Da compilare per studenti esterni quando i valori non sono disponibili dai tabelloni.</div>
                        <div class="ipd-terze-values-grid">
                            <div>
                                <label>Media pagella</label>
                                <input type="text" name="terza_media_pagella" inputmode="decimal" placeholder="es. 7,50" value="<?php echo ipd_h($pratica['terza_media_pagella'] ?? ''); ?>">
                            </div>
                            <div>
                                <label>Matematica</label>
                                <input type="text" name="terza_voto_matematica" inputmode="decimal" placeholder="es. 8" value="<?php echo ipd_h($pratica['terza_voto_matematica'] ?? ''); ?>">
                            </div>
                            <div>
                                <label>Italiano</label>
                                <input type="text" name="terza_voto_italiano" inputmode="decimal" placeholder="es. 7" value="<?php echo ipd_h($pratica['terza_voto_italiano'] ?? ''); ?>">
                            </div>
                            <div>
                                <label>Capacita relazionale</label>
                                <input type="text" name="terza_voto_capacita_relazionale" inputmode="decimal" placeholder="es. 9" value="<?php echo ipd_h($pratica['terza_voto_capacita_relazionale'] ?? ''); ?>">
                            </div>
                        </div>
                        <div style="margin-top:10px;">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <span class="glyphicon glyphicon-floppy-disk"></span> Salva valori
                            </button>
                            <span class="ipd-terze-values-status" id="ipdTerzeValuesStatus-<?php echo intval($pratica['id']); ?>"></span>
                        </div>
                    </form>
                <?php endif; ?>

                <h4>Documenti</h4>
                <div class="ipd-secretary-upload-tools">
                    <button type="button" class="btn btn-sm btn-success" onclick="return ipdUploadSegreteriaDocumentiSelezionati(event, <?php echo intval($pratica['id']); ?>);">
                        <span class="glyphicon glyphicon-upload"></span> Carica tutti i PDF selezionati
                    </button>
                    <span class="ipd-secretary-upload-status" id="ipdUploadAllStatus-<?php echo intval($pratica['id']); ?>"></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-condensed table-bordered">
                        <thead>
                            <tr>
                                <th>Documento</th>
                                <th>Stato</th>
                                <th>File</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $document) :
                                $tipo = (string)$document['tipo_documento'];
                                if (in_array($tipo, ['documento_identita_genitore_2', 'codice_fiscale_genitore_2', 'documento_cf_genitore_2'], true) && !hasSecondResponsibleForIscrizioniPrime($pratica, $confirmed)) {
                                    continue;
                                }
                                $statoDoc = (string)$document['stato'];
                                $hasFile = !empty($document['file_path']) || !empty($document['drive_file_id']);
                                $isOptionalMissing = in_array($tipo, $optionalDocumentTypes, true) && !$hasFile && $statoDoc === 'mancante';
                                $statusClass = $isOptionalMissing ? 'optional' : ($statoDoc === 'consegna_cartacea' ? 'paper' : ($hasFile || in_array($statoDoc, ['caricato', 'estratto', 'verificato'], true) ? 'ok' : 'missing'));
                                $statusLabel = $isOptionalMissing ? 'facoltativo' : ($statoDoc === 'consegna_cartacea' ? 'consegna cartacea' : $statoDoc);
                            ?>
                                <tr>
                                    <td><?php echo ipd_h($labels[$tipo] ?? $tipo); ?></td>
                                    <td class="ipd-doc-status <?php echo $statusClass; ?>"><?php echo ipd_h($statusLabel); ?></td>
                                    <td>
                                        <?php if ($hasFile) : ?>
                                            <div class="ipd-file-line">
                                                <a class="btn btn-xs btn-primary" target="_blank" rel="noopener" href="iscrizioniPrimeDocumento.php?pratica_id=<?php echo intval($pratica['id']); ?>&tipo=<?php echo rawurlencode($tipo); ?>">
                                                    <span class="glyphicon glyphicon-file"></span> Apri PDF
                                                </a>
                                                <span class="ipd-file-name" title="<?php echo ipd_h($document['original_name'] ?? ''); ?>"><?php echo ipd_h($document['original_name'] ?? ''); ?></span>
                                                <button type="button" class="btn btn-xs btn-danger" onclick="return ipdDeleteSegreteriaDocumento(<?php echo intval($pratica['id']); ?>, '<?php echo ipd_h($tipo); ?>');">
                                                    <span class="glyphicon glyphicon-trash"></span> Cancella allegato
                                                </button>
                                                <form class="ipd-secretary-upload" data-pratica-id="<?php echo intval($pratica['id']); ?>" data-tipo-documento="<?php echo ipd_h($tipo); ?>" onsubmit="return ipdUploadSegreteriaDocumento(event, <?php echo intval($pratica['id']); ?>, '<?php echo ipd_h($tipo); ?>');" enctype="multipart/form-data">
                                                    <input type="hidden" name="upload_mode" value="append">
                                                    <input type="file" name="pdf[]" accept="application/pdf,.pdf" multiple required>
                                                    <button type="submit" class="btn btn-xs btn-success">
                                                        <span class="glyphicon glyphicon-plus"></span> Aggiungi al PDF
                                                    </button>
                                                </form>
                                            </div>
                                        <?php elseif ($statoDoc === 'consegna_cartacea') : ?>
                                            <div class="ipd-file-line">
                                                <span class="text-muted">Consegna in segreteria didattica</span>
                                                <form class="ipd-secretary-upload" data-pratica-id="<?php echo intval($pratica['id']); ?>" data-tipo-documento="<?php echo ipd_h($tipo); ?>" onsubmit="return ipdUploadSegreteriaDocumento(event, <?php echo intval($pratica['id']); ?>, '<?php echo ipd_h($tipo); ?>');" enctype="multipart/form-data">
                                                    <input type="hidden" name="upload_mode" value="replace">
                                                    <input type="file" name="pdf[]" accept="application/pdf,.pdf" multiple required>
                                                    <button type="submit" class="btn btn-xs btn-success">
                                                        <span class="glyphicon glyphicon-upload"></span> Carica scansione PDF
                                                    </button>
                                                </form>
                                            </div>
                                        <?php else : ?>
                                            <div class="ipd-file-line">
                                                <span class="text-danger">Mancante</span>
                                                <form class="ipd-secretary-upload" data-pratica-id="<?php echo intval($pratica['id']); ?>" data-tipo-documento="<?php echo ipd_h($tipo); ?>" onsubmit="return ipdUploadSegreteriaDocumento(event, <?php echo intval($pratica['id']); ?>, '<?php echo ipd_h($tipo); ?>');" enctype="multipart/form-data">
                                                    <input type="hidden" name="upload_mode" value="replace">
                                                    <input type="file" name="pdf[]" accept="application/pdf,.pdf" multiple required>
                                                    <button type="submit" class="btn btn-xs btn-default">
                                                        <span class="glyphicon glyphicon-upload"></span> Carica PDF
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php $eventi = $eventiPratiche[intval($pratica['id'])] ?? []; ?>
                <h4>
                    Storico pratica
                    <button type="button" class="btn btn-xs btn-primary pull-right" data-student="<?php echo ipd_h(trim((string)($pratica['cognome'] ?? '') . ' ' . (string)($pratica['nome'] ?? ''))); ?>" onclick="return ipdOpenManualEventModal(<?php echo intval($pratica['id']); ?>, this.getAttribute('data-student') || '');">
                        <span class="glyphicon glyphicon-plus"></span> Aggiungi evento
                    </button>
                </h4>
                <?php if (!$eventi) : ?>
                    <div class="ipd-empty">Nessun evento registrato.</div>
                <?php else : ?>
                    <div class="ipd-cambio-history" style="max-height:none;">
                        <?php foreach ($eventi as $evento) :
                            $dettagli = [];
                            if (!empty($evento['dettagli_json'])) {
                                $decodedDetails = json_decode((string)$evento['dettagli_json'], true);
                                if (is_array($decodedDetails)) {
                                    $dettagli = $decodedDetails;
                                }
                            }
                        ?>
                            <div class="ipd-cambio-event">
                                <div class="ipd-cambio-event-head">
                                    <span><?php echo ipd_h($evento['titolo'] ?? $evento['tipo_evento'] ?? 'Evento'); ?></span>
                                    <span><?php echo ipd_h(iscrizioniPrimeFormatDateTimeIt($evento['created_at'] ?? '')); ?></span>
                                </div>
                                <div class="ipd-cambio-event-meta">
                                    <?php if (!empty($evento['created_by'])) : ?>
                                        Operatore: <?php echo ipd_h($evento['created_by']); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($evento['stato_precedente']) || !empty($evento['stato_nuovo'])) : ?>
                                        &middot; Stato: <?php echo ipd_h(ipd_stato_label((string)($evento['stato_precedente'] ?? '-')) . ' -> ' . ipd_stato_label((string)($evento['stato_nuovo'] ?? '-'))); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($evento['oggetto'])) : ?>
                                        &middot; Oggetto: <?php echo ipd_h($evento['oggetto']); ?>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($evento['messaggio'])) : ?>
                                    <div class="ipd-cambio-event-note"><?php echo ipd_h($evento['messaggio']); ?></div>
                                <?php endif; ?>
                                <?php if ((!empty($evento['allegato_path']) || !empty($evento['allegato_drive_file_id'])) && intval($evento['id'] ?? 0) > 0) : ?>
                                    <div class="ipd-cambio-event-meta">
                                        <a class="btn btn-xs btn-default" target="_blank" rel="noopener" href="iscrizioniPrimeEventoAllegato.php?evento_id=<?php echo intval($evento['id']); ?>">
                                            <span class="glyphicon glyphicon-paperclip"></span>
                                            <?php echo ipd_h($evento['allegato_original_name'] ?: 'Apri allegato'); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if ($dettagli) : ?>
                                    <div class="ipd-cambio-event-meta">
                                        <?php foreach ($dettagli as $key => $value) :
                                            if (in_array((string)$key, ['ok', 'message'], true)) {
                                                continue;
                                            }
                                            if (is_array($value)) {
                                                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                            }
                                            if (trim((string)$value) === '') {
                                                continue;
                                            }
                                        ?>
                                            <span><?php echo ipd_h(str_replace('_', ' ', (string)$key)); ?>: <?php echo ipd_h($value); ?></span>&nbsp;
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($secretaryDocuments) : ?>
                    <div class="ipd-secretary-docs">
                        <h4>Documenti ricevuti da altra segreteria</h4>
                        <div class="text-muted" style="margin-bottom: 8px;">
                            Questi PDF non sono richiesti al genitore: arrivano via mail dalla scuola di provenienza e vengono archiviati qui dalla segreteria didattica.
                        </div>
                        <div class="table-responsive">
                            <table class="table table-condensed table-bordered">
                                <thead>
                                    <tr>
                                        <th>Documento</th>
                                        <th>Stato</th>
                                        <th>File</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($secretaryDocuments as $document) :
                                        $tipo = (string)$document['tipo_documento'];
                                        $statoDoc = (string)($document['stato'] ?? 'mancante');
                                        $isUploaded = !empty($document['file_path']) || !empty($document['drive_file_id']) || in_array($statoDoc, ['caricato', 'estratto', 'verificato'], true);
                                        $statusClass = $isUploaded ? 'ok' : 'missing';
                                    ?>
                                        <tr>
                                            <td><?php echo ipd_h($labels[$tipo] ?? $tipo); ?></td>
                                            <td class="ipd-doc-status <?php echo $statusClass; ?>"><?php echo $isUploaded ? 'caricato' : 'mancante'; ?></td>
                                            <td>
                                                <?php if ($isUploaded) : ?>
                                                    <div class="ipd-file-line">
                                                        <a class="btn btn-xs btn-primary" target="_blank" rel="noopener" href="iscrizioniPrimeDocumento.php?pratica_id=<?php echo intval($pratica['id']); ?>&tipo=<?php echo rawurlencode($tipo); ?>">
                                                            <span class="glyphicon glyphicon-file"></span> Apri PDF
                                                        </a>
                                                        <span class="ipd-file-name" title="<?php echo ipd_h($document['original_name'] ?? ''); ?>"><?php echo ipd_h($document['original_name'] ?? ''); ?></span>
                                                        <button type="button" class="btn btn-xs btn-danger" onclick="return ipdDeleteSegreteriaDocumento(<?php echo intval($pratica['id']); ?>, '<?php echo ipd_h($tipo); ?>');">
                                                            <span class="glyphicon glyphicon-trash"></span> Cancella allegato
                                                        </button>
                                                        <form class="ipd-secretary-upload" data-pratica-id="<?php echo intval($pratica['id']); ?>" data-tipo-documento="<?php echo ipd_h($tipo); ?>" onsubmit="return ipdUploadSegreteriaDocumento(event, <?php echo intval($pratica['id']); ?>, '<?php echo ipd_h($tipo); ?>');" enctype="multipart/form-data">
                                                            <input type="hidden" name="upload_mode" value="append">
                                                            <input type="file" name="pdf[]" accept="application/pdf,.pdf" multiple required>
                                                            <button type="submit" class="btn btn-xs btn-default">
                                                                <span class="glyphicon glyphicon-plus"></span> Aggiungi al PDF
                                                            </button>
                                                        </form>
                                                    </div>
                                                <?php else : ?>
                                                    <div class="ipd-file-line">
                                                        <span class="text-danger">Mancante</span>
                                                        <form class="ipd-secretary-upload" data-pratica-id="<?php echo intval($pratica['id']); ?>" data-tipo-documento="<?php echo ipd_h($tipo); ?>" onsubmit="return ipdUploadSegreteriaDocumento(event, <?php echo intval($pratica['id']); ?>, '<?php echo ipd_h($tipo); ?>');" enctype="multipart/form-data">
                                                            <input type="hidden" name="upload_mode" value="replace">
                                                            <input type="file" name="pdf[]" accept="application/pdf,.pdf" multiple required>
                                                            <button type="submit" class="btn btn-xs btn-success">
                                                                <span class="glyphicon glyphicon-upload"></span> Carica PDF
                                                            </button>
                                                        </form>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div id="ipdCambioScuolaModal" class="ipd-modal-backdrop" aria-hidden="true">
    <div class="ipd-modal-box" role="dialog" aria-modal="true" aria-labelledby="ipdCambioScuolaTitle" style="width:min(1180px,100%);">
        <div id="ipdCambioScuolaTitle" class="ipd-modal-head" style="background:#7f1d1d;">Cambio scuola</div>
        <form id="ipdCambioScuolaForm" enctype="multipart/form-data">
            <div class="ipd-modal-body">
                <input type="hidden" name="id" id="ipdCambioScuolaId">
                <p id="ipdCambioScuolaStudent" class="text-muted"></p>
                <div class="alert alert-warning">
                    Questa pratica verra' segnata come cambio scuola e non ricevera' piu comunicazioni automatiche per completare l'iscrizione.
                </div>
                <div class="ipd-cambio-layout">
                <div>
                    <h4>Nuovo aggiornamento</h4>
                    <div class="row">
                    <div class="col-sm-6 ipd-modal-field">
                        <label for="ipdCambioScuolaData">Data richiesta</label>
                        <input type="date" name="richiesta_data" id="ipdCambioScuolaData" class="form-control">
                    </div>
                    <div class="col-sm-6 ipd-modal-field">
                        <label for="ipdCambioScuolaCanale">Richiesta arrivata via</label>
                        <select name="canale" id="ipdCambioScuolaCanale" class="form-control">
                            <option value="mail">Mail</option>
                            <option value="telefono">Telefono</option>
                            <option value="presenza">Di persona</option>
                            <option value="altro">Altro</option>
                        </select>
                    </div>
                    <div class="col-sm-12 ipd-modal-field">
                        <label for="ipdCambioScuolaScuolaDestinazione">Scuola di destinazione</label>
                        <input type="hidden" name="scuola_destinazione" id="ipdCambioScuolaScuolaDestinazione">
                        <select name="id_istituto_destinazione" id="ipdCambioScuolaIstitutoDestinazione" class="form-control">
                            <?php echo scuoleIstitutiSelectOptionsHtml(null); ?>
                        </select>
                        <input type="text" id="ipdCambioScuolaScuolaDestinazioneManuale" class="form-control" style="margin-top:8px;" placeholder="Se non trovi la scuola nell'elenco, scrivila qui">
                        <div id="ipdCambioScuolaScuolaDestinazioneLibera" class="help-block" style="display:none;"></div>
                    </div>
                    <div class="col-sm-12 ipd-modal-field">
                        <label for="ipdCambioScuolaIndirizzoDestinazione">Indirizzo di studio nella scuola di destinazione</label>
                        <input type="text" name="indirizzo_destinazione" id="ipdCambioScuolaIndirizzoDestinazione" class="form-control" placeholder="Es. informatica, liceo scientifico, meccanica...">
                    </div>
                    <div class="col-sm-6 ipd-modal-field">
                        <label for="ipdCambioScuolaColloquio">Colloquio uscita</label>
                        <select name="colloquio_stato" id="ipdCambioScuolaColloquio" class="form-control">
                            <option value="da_valutare">Da valutare</option>
                            <option value="da_fare">Da fare</option>
                            <option value="fatto">Fatto</option>
                            <option value="non_necessario">Non necessario</option>
                        </select>
                    </div>
                    <div class="col-sm-6 ipd-modal-field">
                        <label for="ipdCambioScuolaNullaOsta">Nulla osta</label>
                        <select name="nulla_osta_stato" id="ipdCambioScuolaNullaOsta" class="form-control">
                            <option value="da_richiedere">Da richiedere</option>
                            <option value="richiesto">Richiesto dalla famiglia</option>
                            <option value="ricevuto">Ricevuto/in lavorazione</option>
                            <option value="evaso_inviato">Evaso / inviato</option>
                            <option value="non_necessario">Non necessario</option>
                        </select>
                    </div>
                    <div class="col-sm-6 ipd-modal-field">
                        <label for="ipdCambioScuolaDocumenti">Documenti pratica</label>
                        <select name="documenti_stato" id="ipdCambioScuolaDocumenti" class="form-control">
                            <option value="da_verificare">Da verificare</option>
                            <option value="manca_qualcosa">Manca qualcosa</option>
                            <option value="completi">Completi</option>
                        </select>
                    </div>
                    <div class="col-sm-6 ipd-modal-field">
                        <label for="ipdCambioScuolaPraticaStato">Stato pratica cambio scuola</label>
                        <select name="pratica_stato" id="ipdCambioScuolaPraticaStato" class="form-control">
                            <option value="aperta">Aperta</option>
                            <option value="in_attesa">In attesa</option>
                            <option value="completata">Completata</option>
                        </select>
                    </div>
                    <div class="col-sm-12 ipd-modal-field">
                        <label for="ipdCambioScuolaAllegato">PDF collegato a questo aggiornamento</label>
                        <input type="file" name="allegato" id="ipdCambioScuolaAllegato" accept="application/pdf,.pdf" class="form-control">
                        <div class="help-block">Puoi allegare, per esempio, la stampa PDF della mail ricevuta o inviata. Ogni salvataggio resta nello storico.</div>
                    </div>
                    <div class="col-sm-12 ipd-modal-field">
                        <label for="ipdCambioScuolaNote">Note segreteria</label>
                        <textarea name="note" id="ipdCambioScuolaNote" placeholder="Annota cosa e' stato comunicato, eventuali documenti mancanti, contatti con la famiglia o con la scuola di destinazione."></textarea>
                    </div>
                    </div>
                </div>
                    <div class="ipd-cambio-history">
                        <h4>Storico aggiornamenti</h4>
                        <div id="ipdCambioScuolaStorico" class="text-muted">Nessun aggiornamento registrato.</div>
                    </div>
                </div>
                <div id="ipdCambioScuolaError" class="text-danger" style="margin-top:8px;" hidden></div>
            </div>
            <div class="ipd-modal-actions">
                <button type="button" class="btn btn-default" onclick="ipdCloseCambioScuolaModal()">Annulla</button>
                <button type="submit" class="btn btn-danger">Salva cambio scuola</button>
            </div>
        </form>
    </div>
</div>

<div id="ipdBulkMailModal" class="ipd-modal-backdrop" aria-hidden="true">
    <div class="ipd-modal-box" role="dialog" aria-modal="true" aria-labelledby="ipdBulkMailTitle">
        <div id="ipdBulkMailTitle" class="ipd-modal-head" style="background:#1d4ed8;">Scrivi a tutti i genitori</div>
        <div class="ipd-modal-body">
            <p class="text-muted">
                La comunicazione verra' inviata a lotti. Le pratiche segnate come cambio scuola non ricevono questa mail.
            </p>
            <div class="ipd-modal-field">
                <label for="ipdBulkMailAudience">Destinatari</label>
                <select id="ipdBulkMailAudience">
                    <option value="esterni">Famiglie esterne da seguire</option>
                    <option value="interni">Studenti gia nostri / ripetenti</option>
                    <option value="interni_bocciati">Solo interni bocciati da tabellone</option>
                    <option value="tutte">Tutte le pratiche attive</option>
                </select>
                <div class="help-block text-muted">Usa "Solo interni bocciati da tabellone" per rettifiche mirate agli studenti gia nostri che risultano non ammessi.</div>
            </div>
            <div class="ipd-modal-field">
                <label for="ipdBulkMailSubject">Oggetto</label>
                <input type="text" id="ipdBulkMailSubject" value="Comunicazione iscrizione">
            </div>
            <div class="ipd-modal-field">
                <label for="ipdBulkMailMessage">Messaggio</label>
                <div class="ipd-rich-tools">
                    <button type="button" class="btn btn-default btn-xs" onclick="ipdFormatTextarea('ipdBulkMailMessage', 'bold')"><strong>B</strong></button>
                    <button type="button" class="btn btn-default btn-xs" onclick="ipdFormatTextarea('ipdBulkMailMessage', 'ul')">Elenco puntato</button>
                    <button type="button" class="btn btn-default btn-xs" onclick="ipdFormatTextarea('ipdBulkMailMessage', 'ol')">Elenco numerato</button>
                </div>
                <textarea id="ipdBulkMailMessage" placeholder="Scrivi qui il testo da inviare a tutte le famiglie."></textarea>
                <div class="help-block text-muted">Puoi usare **testo** per il grassetto, righe che iniziano con "- " per elenco puntato e "1. " per elenco numerato.</div>
            </div>
            <div class="ipd-modal-field">
                <label for="ipdBulkMailSignature">Firma</label>
                <textarea id="ipdBulkMailSignature" style="min-height:90px;">Segreteria didattica
ITT Buonarroti - Trento</textarea>
            </div>
            <div id="ipdBulkMailStatus" class="text-muted" aria-live="polite"></div>
            <div class="ipd-progress" id="ipdBulkMailProgress" hidden><span></span></div>
            <div id="ipdBulkMailError" class="text-danger" style="margin-top:8px;" hidden></div>
        </div>
        <div class="ipd-modal-actions">
            <button type="button" class="btn btn-default" id="ipdBulkMailCancelButton" onclick="ipdCloseBulkMailModal()">Annulla</button>
            <button type="button" class="btn btn-info" id="ipdBulkMailStatusButton" onclick="ipdCheckBulkMailStatus()">Verifica stato invio</button>
            <button type="button" class="btn btn-primary" id="ipdBulkMailSendButton" onclick="ipdStartBulkMail()">Invia a tutti</button>
        </div>
    </div>
</div>

<div id="ipdCustomMailModal" class="ipd-modal-backdrop" aria-hidden="true">
    <div class="ipd-modal-box" role="dialog" aria-modal="true" aria-labelledby="ipdCustomMailTitle">
        <div id="ipdCustomMailTitle" class="ipd-modal-head" style="background:#1d4ed8;">Scrivi ai genitori</div>
        <div class="ipd-modal-body">
            <p id="ipdCustomMailStudent" class="text-muted"></p>
            <div class="ipd-modal-field">
                <label>Destinatari</label>
                <div id="ipdCustomMailRecipients" class="well well-sm" style="margin-bottom:0;"></div>
            </div>
            <div class="ipd-modal-field">
                <label for="ipdCustomMailSubject">Oggetto</label>
                <input type="text" id="ipdCustomMailSubject" value="Comunicazione pratica iscrizione">
            </div>
            <div class="ipd-modal-field">
                <label for="ipdCustomMailMessage">Messaggio</label>
                <div class="ipd-rich-tools">
                    <button type="button" class="btn btn-default btn-xs" onclick="ipdFormatTextarea('ipdCustomMailMessage', 'bold')"><strong>B</strong></button>
                    <button type="button" class="btn btn-default btn-xs" onclick="ipdFormatTextarea('ipdCustomMailMessage', 'ul')">Elenco puntato</button>
                    <button type="button" class="btn btn-default btn-xs" onclick="ipdFormatTextarea('ipdCustomMailMessage', 'ol')">Elenco numerato</button>
                </div>
                <textarea id="ipdCustomMailMessage" placeholder="Scrivi qui il testo da inviare ai genitori."></textarea>
                <div class="help-block text-muted">Puoi usare **testo** per il grassetto, righe che iniziano con "- " per elenco puntato e "1. " per elenco numerato.</div>
            </div>
            <div class="ipd-modal-field">
                <label for="ipdCustomMailSignature">Firma</label>
                <textarea id="ipdCustomMailSignature" style="min-height:90px;">Segreteria didattica
ITT Buonarroti - Trento</textarea>
                <div class="help-block text-muted">La firma viene inserita in fondo alla mail e puo' essere personalizzata per questa comunicazione.</div>
            </div>
            <div id="ipdCustomMailError" class="text-danger" style="margin-top:8px;" hidden></div>
        </div>
        <div class="ipd-modal-actions">
            <button type="button" class="btn btn-default" onclick="ipdCloseCustomMailModal()">Annulla</button>
            <button type="button" class="btn btn-primary" onclick="ipdSendCustomMail()">Invia mail</button>
        </div>
    </div>
</div>

<div id="ipdFormationNoteModal" class="ipd-modal-backdrop" aria-hidden="true">
    <div class="ipd-modal-box" role="dialog" aria-modal="true" aria-labelledby="ipdFormationNoteTitle">
        <div id="ipdFormationNoteTitle" class="ipd-modal-head" style="background:#4f46e5;">Richieste / note genitori</div>
        <div class="ipd-modal-body">
            <input type="hidden" id="ipdFormationNoteId">
            <p id="ipdFormationNoteStudent" class="text-muted"></p>
            <div class="ipd-modal-field">
                <label for="ipdFormationNoteText">Note per iscrizione e formazione classi</label>
                <textarea id="ipdFormationNoteText" placeholder="Inserisci richieste dei genitori, abbinamenti, incompatibilita o note utili per la futura classe."></textarea>
                <div class="help-block">Queste note sono le stesse mostrate nella pagina formazione classi sullo studente.</div>
            </div>
            <div id="ipdFormationNoteError" class="text-danger" style="margin-top:8px;" hidden></div>
        </div>
        <div class="ipd-modal-actions">
            <button type="button" class="btn btn-default" onclick="ipdCloseFormationNoteModal()">Annulla</button>
            <button type="button" class="btn btn-primary" id="ipdFormationNoteSaveButton" onclick="ipdSaveFormationNote()">Salva note</button>
        </div>
    </div>
</div>

<div id="ipdIntegrationModal" class="ipd-modal-backdrop" aria-hidden="true">
    <div class="ipd-modal-box" role="dialog" aria-modal="true" aria-labelledby="ipdIntegrationTitle">
        <div id="ipdIntegrationTitle" class="ipd-modal-head">Richiedi integrazione ai genitori</div>
        <div class="ipd-modal-body">
            <p class="text-muted">
                Scrivi cosa deve correggere la famiglia. La pratica verra' riaperta e il testo verra' inserito nella mail inviata ai genitori.
            </p>
            <textarea id="ipdIntegrationNote" placeholder="Esempio: Sono stati caricati gli stessi PDF per tutti i documenti. Cancellare gli allegati non corretti e caricare ogni documento nella voce corrispondente."></textarea>
            <div id="ipdIntegrationError" class="text-danger" style="margin-top:8px;" hidden></div>
        </div>
        <div class="ipd-modal-actions">
            <button type="button" class="btn btn-default" onclick="ipdCloseIntegrationModal()">Annulla</button>
            <button type="button" class="btn btn-warning" onclick="ipdSubmitIntegrationRequest()">Riapri pratica e invia mail</button>
        </div>
    </div>
</div>

<div id="ipdMessageModal" class="ipd-modal-backdrop" aria-hidden="true">
    <div class="ipd-modal-box" role="dialog" aria-modal="true" aria-labelledby="ipdMessageTitle" style="max-width:560px;text-align:center;">
        <div class="ipd-modal-body" style="padding:28px 24px;">
            <div id="ipdMessageIcon" style="width:68px;height:68px;border-radius:50%;background:#1d4ed8;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:30px;margin-bottom:12px;">
                <span class="glyphicon glyphicon-envelope"></span>
            </div>
            <h3 id="ipdMessageTitle" style="font-size:26px;font-weight:800;color:#0f172a;margin:0 0 8px;">Conferma invio</h3>
            <p id="ipdMessageText" class="text-muted" style="font-size:16px;line-height:1.45;margin-bottom:12px;"></p>
            <div id="ipdMessageDetails" class="text-muted"></div>
        </div>
        <div class="ipd-modal-actions" id="ipdMessageActions">
            <button type="button" class="btn btn-default" id="ipdMessageCancel">Annulla</button>
            <button type="button" class="btn btn-primary" id="ipdMessageConfirm">Conferma invio</button>
            <button type="button" class="btn btn-primary" id="ipdMessageClose" style="display:none;">Chiudi</button>
        </div>
    </div>
</div>

<div id="ipdStatusNoteModal" class="ipd-modal-backdrop" aria-hidden="true">
    <div class="ipd-modal-box" role="dialog" aria-modal="true" aria-labelledby="ipdStatusNoteTitle">
        <div id="ipdStatusNoteTitle" class="ipd-modal-head" style="background:#334155;">Aggiorna stato pratica</div>
        <div class="ipd-modal-body">
            <p id="ipdStatusNoteHelp" class="text-muted"></p>
            <div class="ipd-modal-field">
                <label for="ipdStatusNoteText">Motivo / nota interna</label>
                <textarea id="ipdStatusNoteText" placeholder="Scrivi il motivo dell'aggiornamento dello stato."></textarea>
            </div>
            <div id="ipdStatusNoteError" class="text-danger" style="margin-top:8px;" hidden></div>
        </div>
        <div class="ipd-modal-actions">
            <button type="button" class="btn btn-default" onclick="ipdCloseStatusNoteModal()">Annulla</button>
            <button type="button" class="btn btn-primary" onclick="ipdSubmitStatusNote()">Salva stato</button>
        </div>
    </div>
</div>

<div id="ipdManualEventModal" class="ipd-modal-backdrop" aria-hidden="true">
    <div class="ipd-modal-box" role="dialog" aria-modal="true" aria-labelledby="ipdManualEventTitle">
        <form id="ipdManualEventForm" enctype="multipart/form-data">
            <div id="ipdManualEventTitle" class="ipd-modal-head" style="background:#1d4ed8;">Aggiungi evento allo storico</div>
            <div class="ipd-modal-body">
                <input type="hidden" name="pratica_id" id="ipdManualEventPraticaId">
                <p id="ipdManualEventStudent" class="text-muted"></p>
                <div class="ipd-modal-field">
                    <label for="ipdManualEventTitolo">Titolo evento</label>
                    <input type="text" name="titolo" id="ipdManualEventTitolo" placeholder="Es. Telefonata con genitore">
                </div>
                <div class="ipd-modal-field">
                    <label for="ipdManualEventMessaggio">Note</label>
                    <textarea name="messaggio" id="ipdManualEventMessaggio" placeholder="Scrivi il contenuto da registrare nello storico."></textarea>
                </div>
                <div class="ipd-modal-field">
                    <label for="ipdManualEventAllegato">Allegato opzionale</label>
                    <input type="file" name="allegato" id="ipdManualEventAllegato" class="form-control" accept="application/pdf,image/jpeg,image/png,.pdf,.jpg,.jpeg,.png">
                </div>
                <div id="ipdManualEventError" class="text-danger" style="margin-top:8px;" hidden></div>
            </div>
            <div class="ipd-modal-actions">
                <button type="button" class="btn btn-default" onclick="ipdCloseManualEventModal()">Annulla</button>
                <button type="submit" class="btn btn-primary" id="ipdManualEventSaveButton">Salva evento</button>
            </div>
        </form>
    </div>
</div>

<div id="ipdBusyModal" class="ipd-modal-backdrop" aria-hidden="true">
    <div class="ipd-busy-box" role="status" aria-live="polite">
        <div class="ipd-busy-spinner"></div>
        <h3 id="ipdBusyTitle" style="margin:0 0 6px;font-weight:800;color:#0f172a;">Operazione in corso</h3>
        <p id="ipdBusyText" class="text-muted" style="margin:0;">Attendere...</p>
    </div>
</div>

<script>
let ipdIntegrationPraticaId = 0;
let ipdCustomMailPraticaId = 0;
let ipdManualEventPraticaId = 0;
let ipdStatusNotePraticaId = 0;
let ipdStatusNoteStato = '';
const ipdTipoIscrizione = <?php echo json_encode($tipoIscrizione); ?>;
const ipdOpenPraticaId = <?php echo intval($openPraticaId); ?>;
let ipdShowCompleted = <?php echo $mostraCompletate ? 'true' : 'false'; ?>;
let ipdBulkMailRunning = false;
let ipdBulkMailSent = 0;
let ipdBulkMailInitialRemaining = 0;

function ipdEscape(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (char) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
    });
}

function ipdFormatDateIt(value) {
    const text = String(value || '').trim();
    const match = text.match(/^(\d{4})-(\d{2})-(\d{2})/);
    return match ? (match[3] + '/' + match[2] + '/' + match[1]) : text;
}

function ipdFormatDateTimeIt(value) {
    const text = String(value || '').trim();
    const match = text.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
    if (!match) return text;
    return match[3] + '/' + match[2] + '/' + match[1] + (match[4] ? ' ' + match[4] + ':' + match[5] : '');
}

function ipdToggleDettagli(id, button) {
    const card = document.getElementById('pratica-' + id);
    if (!card) {
        return;
    }
    const open = card.classList.toggle('open');
    button.textContent = open ? 'Nascondi' : 'Dettagli';
}

function ipdOpenPraticaCard(id) {
    const card = document.getElementById('pratica-' + Number(id || 0));
    if (!card) {
        return;
    }
    card.classList.add('open', 'ipd-target');
    const button = card.querySelector('.ipd-toggle');
    if (button) {
        button.textContent = 'Nascondi';
    }
    setTimeout(function () {
        card.scrollIntoView({behavior: 'smooth', block: 'start'});
    }, 80);
}

function ipdNormalizeFilterText(value) {
    return String(value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

function ipdApplyLiveFilter() {
    const input = document.getElementById('ipdLiveFilter');
    const counter = document.getElementById('ipdFilterCount');
    const query = ipdNormalizeFilterText(input ? input.value : '').trim();
    const terms = query.split(/\s+/).filter(Boolean);
    const cards = Array.from(document.querySelectorAll('.ipd-card[id^="pratica-"]'));
    let visible = 0;

    cards.forEach(card => {
        const haystack = ipdNormalizeFilterText(card.textContent || '');
        const match = terms.length === 0 || terms.every(term => haystack.includes(term));
        const isCompleted = card.dataset.stato === 'verificata';
        const show = match && (ipdShowCompleted || !isCompleted);
        card.style.display = show ? '' : 'none';
        if (show) {
            visible++;
        }
    });

    if (counter) {
        counter.textContent = terms.length === 0 ? '' : visible + ' pratiche trovate';
    }
}

function ipdUpdateCompletedButton() {
    const button = document.getElementById('ipdToggleCompletedButton');
    if (!button) {
        return;
    }
    const icon = button.querySelector('.glyphicon');
    const label = button.querySelector('.ipd-toggle-completed-label');
    button.classList.toggle('btn-warning', ipdShowCompleted);
    button.classList.toggle('btn-default', !ipdShowCompleted);
    if (icon) {
        icon.className = 'glyphicon glyphicon-' + (ipdShowCompleted ? 'eye-close' : 'eye-open');
    }
    if (label) {
        label.textContent = ipdShowCompleted ? 'Nascondi completate' : 'Mostra completate';
    }
}

function ipdToggleCompleted() {
    ipdShowCompleted = !ipdShowCompleted;
    ipdUpdateCompletedButton();
    ipdApplyLiveFilter();
}

document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('ipdLiveFilter');
    if (input) {
        input.addEventListener('input', ipdApplyLiveFilter);
    }
    const hashMatch = String(window.location.hash || '').match(/^#pratica-(\d+)$/);
    const hashPraticaId = hashMatch ? Number(hashMatch[1] || 0) : 0;
    const targetPraticaId = ipdOpenPraticaId > 0 ? ipdOpenPraticaId : hashPraticaId;
    if (targetPraticaId > 0) {
        const targetCard = document.getElementById('pratica-' + targetPraticaId);
        if (targetCard && targetCard.dataset.stato === 'verificata') {
            ipdShowCompleted = true;
        }
        ipdOpenPraticaCard(targetPraticaId);
    }
    ipdUpdateCompletedButton();
    ipdApplyLiveFilter();
});

function ipdSetStato(id, stato) {
    if (stato === 'da_integrare') {
        ipdOpenIntegrationModal(id);
        return;
    }
    if (stato === 'annullata') {
        ipdOpenCambioScuolaModal(id);
        return;
    }
    if (stato === 'inviata') {
        ipdOpenStatusNoteModal(id, stato, 'Riporta la pratica allo stato inviata e indica il motivo.');
        return;
    }
    if (stato === 'bozza') {
        ipdOpenStatusNoteModal(id, stato, 'Rende la pratica di nuovo compilabile dal link dei genitori. Indica il motivo.');
        return;
    }
    if (stato === 'verifica_iniziale_ok') {
        ipdOpenStatusNoteModal(id, stato, 'Indica cosa e\' stato controllato e cosa resta da consegnare in cartaceo.');
        return;
    }

    const labels = {
        verifica_iniziale_ok: 'registrare la verifica iniziale OK',
        verificata: 'segnare la pratica come completata',
        da_integrare: 'segnare la pratica come da integrare',
        bozza: 'rendere la pratica di nuovo compilabile dai genitori',
        inviata: 'riportare la pratica allo stato inviata',
        annullata: 'segnare la pratica come cambio scuola/non prosegue'
    };
    if (!confirm('Vuoi ' + (labels[stato] || 'aggiornare lo stato della pratica') + '?')) {
        return;
    }
    ipdSendStato(id, stato, '');
}

function ipdOpenStatusNoteModal(id, stato, help) {
    ipdStatusNotePraticaId = id;
    ipdStatusNoteStato = stato;
    document.getElementById('ipdStatusNoteHelp').textContent = help || '';
    document.getElementById('ipdStatusNoteText').value = '';
    document.getElementById('ipdStatusNoteError').hidden = true;
    document.getElementById('ipdStatusNoteError').textContent = '';
    const modal = document.getElementById('ipdStatusNoteModal');
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
}

function ipdCloseStatusNoteModal() {
    const modal = document.getElementById('ipdStatusNoteModal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    ipdStatusNotePraticaId = 0;
    ipdStatusNoteStato = '';
}

function ipdSubmitStatusNote() {
    const note = document.getElementById('ipdStatusNoteText').value.trim();
    if (note.length < 3) {
        const error = document.getElementById('ipdStatusNoteError');
        error.textContent = 'Scrivi una breve nota per lo storico.';
        error.hidden = false;
        return;
    }
    const id = ipdStatusNotePraticaId;
    const stato = ipdStatusNoteStato;
    ipdCloseStatusNoteModal();
    ipdSendStato(id, stato, note);
}

function ipdOpenCambioScuolaModal(id) {
    const modal = document.getElementById('ipdCambioScuolaModal');
    const form = document.getElementById('ipdCambioScuolaForm');
    const error = document.getElementById('ipdCambioScuolaError');
    if (form) form.reset();
    if (error) {
        error.hidden = true;
        error.textContent = '';
    }
    document.getElementById('ipdCambioScuolaId').value = id;
    document.getElementById('ipdCambioScuolaStudent').textContent = 'Caricamento dati pratica...';
    ipdRenderCambioScuolaStorico(id, []);
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');

    fetch('iscrizioniPrimeCambioScuolaRead.php?id=' + encodeURIComponent(id), {credentials: 'same-origin'})
        .then(response => response.json())
        .then(data => {
            if (!data.ok) {
                throw new Error(data.message || 'Errore lettura cambio scuola.');
            }
            const pratica = data.pratica || {};
            const record = data.record || {};
            document.getElementById('ipdCambioScuolaStudent').textContent = 'Pratica di ' + (pratica.cognome || '') + ' ' + (pratica.nome || '') + ' - stato attuale: ' + (pratica.stato || '');
            document.getElementById('ipdCambioScuolaData').value = record.richiesta_data || '';
            document.getElementById('ipdCambioScuolaCanale').value = record.canale || 'mail';
            document.getElementById('ipdCambioScuolaScuolaDestinazione').value = record.scuola_destinazione || '';
            document.getElementById('ipdCambioScuolaIstitutoDestinazione').value = record.id_istituto_destinazione || '';
            document.getElementById('ipdCambioScuolaScuolaDestinazioneManuale').value = record.id_istituto_destinazione ? '' : (record.scuola_destinazione || '');
            document.getElementById('ipdCambioScuolaIndirizzoDestinazione').value = record.indirizzo_destinazione || '';
            ipdCambioScuolaUpdateSchoolName();
            const libera = document.getElementById('ipdCambioScuolaScuolaDestinazioneLibera');
            if (libera) {
                libera.style.display = (!record.id_istituto_destinazione && record.scuola_destinazione) ? 'block' : 'none';
                libera.textContent = (!record.id_istituto_destinazione && record.scuola_destinazione) ? 'Valore gia presente: ' + record.scuola_destinazione : '';
            }
            document.getElementById('ipdCambioScuolaColloquio').value = record.colloquio_stato || 'da_valutare';
            document.getElementById('ipdCambioScuolaNullaOsta').value = record.nulla_osta_stato || 'da_richiedere';
            document.getElementById('ipdCambioScuolaDocumenti').value = record.documenti_stato || 'da_verificare';
            document.getElementById('ipdCambioScuolaPraticaStato').value = record.pratica_stato || 'aperta';
            document.getElementById('ipdCambioScuolaNote').value = '';
            ipdRenderCambioScuolaStorico(id, data.eventi || []);
        })
        .catch(error => {
            const box = document.getElementById('ipdCambioScuolaError');
            if (box) {
                box.textContent = error.message;
                box.hidden = false;
            }
        });
}

function ipdCambioScuolaLabel(value) {
    const labels = {
        mail: 'Mail',
        telefono: 'Telefono',
        presenza: 'Di persona',
        altro: 'Altro',
        da_valutare: 'Da valutare',
        da_fare: 'Da fare',
        fatto: 'Fatto',
        non_necessario: 'Non necessario',
        da_richiedere: 'Da richiedere',
        richiesto: 'Richiesto',
        ricevuto: 'Ricevuto/in lavorazione',
        evaso_inviato: 'Evaso / inviato',
        da_verificare: 'Da verificare',
        manca_qualcosa: 'Manca qualcosa',
        completi: 'Completi',
        aperta: 'Aperta',
        in_attesa: 'In attesa',
        completata: 'Completata'
    };
    return labels[value] || value || '-';
}

function ipdCambioScuolaUpdateSchoolName() {
    const select = document.getElementById('ipdCambioScuolaIstitutoDestinazione');
    const hidden = document.getElementById('ipdCambioScuolaScuolaDestinazione');
    const manual = document.getElementById('ipdCambioScuolaScuolaDestinazioneManuale');
    if (!select || !hidden) return;
    const option = select.options[select.selectedIndex];
    if (select.value && option) {
        hidden.value = option.textContent || '';
        if (manual) {
            manual.value = '';
        }
    } else if (manual) {
        hidden.value = manual.value || '';
    }
}

document.getElementById('ipdCambioScuolaIstitutoDestinazione').addEventListener('change', function () {
    ipdCambioScuolaUpdateSchoolName();
    const libera = document.getElementById('ipdCambioScuolaScuolaDestinazioneLibera');
    if (libera) {
        libera.style.display = 'none';
        libera.textContent = '';
    }
});
document.getElementById('ipdCambioScuolaScuolaDestinazioneManuale').addEventListener('input', function () {
    if (this.value.trim() !== '') {
        document.getElementById('ipdCambioScuolaIstitutoDestinazione').value = '';
    }
    ipdCambioScuolaUpdateSchoolName();
});

function ipdRenderCambioScuolaStorico(praticaId, eventi) {
    const box = document.getElementById('ipdCambioScuolaStorico');
    if (!box) return;
    if (!eventi || !eventi.length) {
        box.innerHTML = '<span class="text-muted">Nessun aggiornamento registrato.</span>';
        return;
    }
    box.innerHTML = eventi.map((evento, index) => {
        const allegato = (evento.allegato_path || evento.allegato_drive_file_id)
            ? '<a class="btn btn-xs btn-primary" target="_blank" rel="noopener" href="iscrizioniPrimeCambioScuolaAllegato.php?id=' + encodeURIComponent(praticaId) + '&evento_id=' + encodeURIComponent(evento.id) + '"><span class="glyphicon glyphicon-file"></span> Apri PDF</a> <span class="text-muted">' + ipdEscape(evento.allegato_original_name || '') + '</span>'
            : '<span class="text-muted">Nessun PDF allegato a questo aggiornamento</span>';
        const undo = index === 0 && Number(evento.id || 0) > 0
            ? '<button type="button" class="btn btn-xs btn-danger pull-right" onclick="ipdUndoCambioScuolaLast(' + Number(praticaId) + ')"><span class="glyphicon glyphicon-repeat"></span> Annulla ultimo aggiornamento</button>'
            : '';
        return '<div class="ipd-cambio-event">' +
            '<div class="ipd-cambio-event-head">' +
                '<span>' + ipdEscape(ipdFormatDateTimeIt(evento.created_at || '')) + '</span>' +
                '<span>' + ipdEscape(evento.created_by || '') + '</span>' +
            '</div>' +
            '<div class="ipd-cambio-event-meta">' +
                'Richiesta: ' + ipdEscape(evento.richiesta_data ? ipdFormatDateIt(evento.richiesta_data) : '-') +
                ' &middot; Canale: ' + ipdEscape(ipdCambioScuolaLabel(evento.canale)) +
                ' &middot; Destinazione: ' + ipdEscape(evento.scuola_destinazione || '-') +
                (evento.indirizzo_destinazione ? ' &middot; Indirizzo: ' + ipdEscape(evento.indirizzo_destinazione) : '') +
                ' &middot; Colloquio: ' + ipdEscape(ipdCambioScuolaLabel(evento.colloquio_stato)) +
                ' &middot; Nulla osta: ' + ipdEscape(ipdCambioScuolaLabel(evento.nulla_osta_stato)) +
                ' &middot; Documenti: ' + ipdEscape(ipdCambioScuolaLabel(evento.documenti_stato)) +
                ' &middot; Stato: ' + ipdEscape(ipdCambioScuolaLabel(evento.pratica_stato)) +
            '</div>' +
            (evento.note ? '<div class="ipd-cambio-event-note">' + ipdEscape(evento.note) + '</div>' : '') +
            '<div style="margin-top:8px;">' + allegato + undo + '<div style="clear:both;"></div></div>' +
        '</div>';
    }).join('');
}

function ipdUndoCambioScuolaLast(praticaId) {
    if (!confirm("Vuoi annullare l'ultimo aggiornamento del cambio scuola? L'eventuale PDF collegato a quell'aggiornamento verra cancellato.")) {
        return;
    }
    const error = document.getElementById('ipdCambioScuolaError');
    const data = new FormData();
    data.append('id', praticaId);
    if (error) {
        error.hidden = true;
        error.textContent = '';
    }
    fetch('iscrizioniPrimeCambioScuolaUndoLast.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
    .then(response => response.json().then(result => ({ok: response.ok, result})))
    .then(payload => {
        if (!payload.ok || !payload.result.ok) {
            throw new Error(payload.result.message || 'Annullamento non riuscito.');
        }
        ipdOpenCambioScuolaModal(praticaId);
    })
    .catch(err => {
        if (error) {
            error.textContent = err.message;
            error.hidden = false;
        } else {
            alert(err.message);
        }
    });
}

function ipdCloseCambioScuolaModal() {
    const modal = document.getElementById('ipdCambioScuolaModal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
}

document.getElementById('ipdCambioScuolaForm').addEventListener('submit', function (event) {
    event.preventDefault();
    const error = document.getElementById('ipdCambioScuolaError');
    const button = this.querySelector('button[type="submit"]');
    const data = new FormData(this);
    ipdCambioScuolaUpdateSchoolName();
    data.set('scuola_destinazione', document.getElementById('ipdCambioScuolaScuolaDestinazione').value || '');
    if (button) {
        button.disabled = true;
        button.textContent = 'Salvataggio...';
    }
    if (error) {
        error.hidden = true;
        error.textContent = '';
    }

    fetch('iscrizioniPrimeCambioScuolaSave.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
    .then(response => response.json().then(result => ({ok: response.ok, result})))
    .then(payload => {
        if (!payload.ok || !payload.result.ok) {
            throw new Error(payload.result.message || 'Salvataggio non riuscito.');
        }
        window.location.href = '?tipo_iscrizione=' + encodeURIComponent(ipdTipoIscrizione) + '&stato=annullata';
    })
    .catch(err => {
        if (error) {
            error.textContent = err.message;
            error.hidden = false;
        } else {
            alert(err.message);
        }
    })
    .finally(() => {
        if (button) {
            button.disabled = false;
            button.textContent = 'Salva cambio scuola';
        }
    });
});

function ipdRenderCustomMailRecipients(containerId, recipients) {
    const box = document.getElementById(containerId);
    if (!box) return;
    const items = [];
    const seen = {};
    [['Genitore 1', recipients?.genitore1 || ''], ['Genitore 2', recipients?.genitore2 || '']].forEach(item => {
        const email = String(item[1] || '').trim().toLowerCase();
        if (!email || seen[email]) return;
        seen[email] = true;
        items.push('<label style="display:block;margin:4px 0;font-weight:600;">'
            + '<input type="checkbox" class="ipd-custom-mail-recipient" value="' + ipdEscape(email) + '" checked> '
            + ipdEscape(item[0]) + ' - ' + ipdEscape(email)
            + '</label>');
    });
    box.innerHTML = items.length ? items.join('') : '<span class="text-danger">Nessuna email genitore presente nella pratica.</span>';
}

function ipdOpenCustomMailModal(id, studentName, recipients) {
    ipdCustomMailPraticaId = id;
    const modal = document.getElementById('ipdCustomMailModal');
    const student = document.getElementById('ipdCustomMailStudent');
    const subject = document.getElementById('ipdCustomMailSubject');
    const message = document.getElementById('ipdCustomMailMessage');
    const error = document.getElementById('ipdCustomMailError');
    if (student) {
        student.textContent = studentName ? 'Pratica di ' + studentName : 'Pratica selezionata';
    }
    if (subject && subject.value.trim() === '') {
        subject.value = 'Comunicazione pratica iscrizione';
    }
    if (message) {
        message.value = '';
    }
    ipdRenderCustomMailRecipients('ipdCustomMailRecipients', recipients || {});
    if (error) {
        error.hidden = true;
        error.textContent = '';
    }
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    setTimeout(() => message && message.focus(), 50);
}

function ipdCloseCustomMailModal() {
    const modal = document.getElementById('ipdCustomMailModal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    ipdCustomMailPraticaId = 0;
}

function ipdShowMessageModal(title, text, details, mode) {
    const modal = document.getElementById('ipdMessageModal');
    const icon = document.getElementById('ipdMessageIcon');
    const close = document.getElementById('ipdMessageClose');
    document.getElementById('ipdMessageTitle').textContent = title;
    document.getElementById('ipdMessageText').textContent = text;
    document.getElementById('ipdMessageDetails').innerHTML = details || '';
    document.getElementById('ipdMessageCancel').style.display = 'none';
    document.getElementById('ipdMessageConfirm').style.display = 'none';
    close.style.display = 'inline-block';
    close.onclick = () => ipdCloseMessageModal();
    icon.style.background = mode === 'error' ? '#dc2626' : (mode === 'success' ? '#16a34a' : '#1d4ed8');
    icon.innerHTML = mode === 'error'
        ? '<span class="glyphicon glyphicon-alert"></span>'
        : (mode === 'success' ? '<span class="glyphicon glyphicon-ok"></span>' : '<span class="glyphicon glyphicon-envelope"></span>');
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
}

function ipdCloseMessageModal() {
    const modal = document.getElementById('ipdMessageModal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
}

function ipdShowBusy(title, text) {
    const modal = document.getElementById('ipdBusyModal');
    document.getElementById('ipdBusyTitle').textContent = title || 'Operazione in corso';
    document.getElementById('ipdBusyText').textContent = text || 'Attendere...';
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
}

function ipdHideBusy() {
    const modal = document.getElementById('ipdBusyModal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
}

function ipdOpenManualEventModal(id, studentName) {
    ipdManualEventPraticaId = Number(id || 0);
    const modal = document.getElementById('ipdManualEventModal');
    const form = document.getElementById('ipdManualEventForm');
    const student = document.getElementById('ipdManualEventStudent');
    const error = document.getElementById('ipdManualEventError');
    if (form) {
        form.reset();
    }
    document.getElementById('ipdManualEventPraticaId').value = String(ipdManualEventPraticaId);
    student.textContent = studentName ? 'Pratica di ' + studentName : 'Pratica selezionata';
    if (error) {
        error.hidden = true;
        error.textContent = '';
    }
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    setTimeout(() => document.getElementById('ipdManualEventTitolo').focus(), 50);
    return false;
}

function ipdCloseManualEventModal() {
    const modal = document.getElementById('ipdManualEventModal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    ipdManualEventPraticaId = 0;
}

function ipdConfirmMessageModal(title, text, details) {
    const modal = document.getElementById('ipdMessageModal');
    const cancel = document.getElementById('ipdMessageCancel');
    const confirm = document.getElementById('ipdMessageConfirm');
    const close = document.getElementById('ipdMessageClose');
    const icon = document.getElementById('ipdMessageIcon');
    document.getElementById('ipdMessageTitle').textContent = title;
    document.getElementById('ipdMessageText').textContent = text;
    document.getElementById('ipdMessageDetails').innerHTML = details || '';
    cancel.style.display = 'inline-block';
    confirm.style.display = 'inline-block';
    close.style.display = 'none';
    icon.style.background = '#1d4ed8';
    icon.innerHTML = '<span class="glyphicon glyphicon-envelope"></span>';
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');

    return new Promise(resolve => {
        const cleanup = result => {
            cancel.onclick = null;
            confirm.onclick = null;
            ipdCloseMessageModal();
            resolve(result);
        };
        cancel.onclick = () => cleanup(false);
        confirm.onclick = () => cleanup(true);
    });
}

function ipdOpenBulkMailModal() {
    const modal = document.getElementById('ipdBulkMailModal');
    const message = document.getElementById('ipdBulkMailMessage');
    const error = document.getElementById('ipdBulkMailError');
    const status = document.getElementById('ipdBulkMailStatus');
    const progress = document.getElementById('ipdBulkMailProgress');
    const cancelButton = document.getElementById('ipdBulkMailCancelButton');
    const sendButton = document.getElementById('ipdBulkMailSendButton');
    const statusButton = document.getElementById('ipdBulkMailStatusButton');
    ipdBulkMailRunning = false;
    ipdBulkMailSent = 0;
    ipdBulkMailInitialRemaining = 0;
    if (message) message.value = '';
    if (error) {
        error.hidden = true;
        error.textContent = '';
    }
    if (status) status.textContent = '';
    if (progress) {
        progress.hidden = true;
        progress.querySelector('span').style.width = '0';
    }
    if (cancelButton) {
        cancelButton.textContent = 'Annulla';
        cancelButton.className = 'btn btn-default';
    }
    if (sendButton) {
        sendButton.disabled = false;
        sendButton.textContent = 'Invia a tutti';
        sendButton.className = 'btn btn-primary';
        sendButton.onclick = ipdStartBulkMail;
    }
    if (statusButton) {
        statusButton.disabled = false;
        statusButton.style.display = '';
    }
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    setTimeout(() => message && message.focus(), 50);
}

function ipdCloseBulkMailModal() {
    if (ipdBulkMailRunning) {
        return;
    }
    const modal = document.getElementById('ipdBulkMailModal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
}

function ipdBulkMailFormData(dryRun) {
    const data = new FormData();
    data.append('tipo_iscrizione', ipdTipoIscrizione);
    data.append('subject', (document.getElementById('ipdBulkMailSubject')?.value || '').trim());
    data.append('message', (document.getElementById('ipdBulkMailMessage')?.value || '').trim());
    data.append('signature', (document.getElementById('ipdBulkMailSignature')?.value || '').trim());
    data.append('audience', (document.getElementById('ipdBulkMailAudience')?.value || 'esterni'));
    if (dryRun) {
        data.append('dry_run', '1');
    }
    return data;
}

function ipdFormatTextarea(id, mode) {
    const field = document.getElementById(id);
    if (!field) {
        return;
    }
    const start = field.selectionStart || 0;
    const end = field.selectionEnd || 0;
    const selected = field.value.substring(start, end);
    let replacement = selected;

    if (mode === 'bold') {
        replacement = selected ? '**' + selected + '**' : '**testo in grassetto**';
    } else if (mode === 'ul') {
        const source = selected || 'prima voce\nseconda voce';
        replacement = source.split(/\r?\n/).map(line => {
            line = line.replace(/^\s*[-*]\s+/, '').trim();
            return line ? '- ' + line : '';
        }).join('\n');
    } else if (mode === 'ol') {
        const source = selected || 'prima voce\nseconda voce';
        replacement = source.split(/\r?\n/).map((line, index) => {
            line = line.replace(/^\s*\d+[.)]\s+/, '').trim();
            return line ? (index + 1) + '. ' + line : '';
        }).join('\n');
    }

    field.value = field.value.substring(0, start) + replacement + field.value.substring(end);
    field.focus();
    field.selectionStart = start;
    field.selectionEnd = start + replacement.length;
}

function ipdSetBulkMailProgress(remaining) {
    const status = document.getElementById('ipdBulkMailStatus');
    const progress = document.getElementById('ipdBulkMailProgress');
    if (!progress) return;
    progress.hidden = false;
    const total = Math.max(ipdBulkMailInitialRemaining, ipdBulkMailSent + remaining, 1);
    const percent = Math.max(0, Math.min(100, Math.round((ipdBulkMailSent / total) * 100)));
    progress.querySelector('span').style.width = percent + '%';
    if (status) {
        status.textContent = 'Invio in corso: ' + ipdBulkMailSent + ' inviati, ' + remaining + ' restanti.';
    }
}

function ipdBulkMailRequest(dryRun) {
    return fetch('iscrizioniPrimeMailTutti.php', {
        method: 'POST',
        body: ipdBulkMailFormData(dryRun),
        credentials: 'same-origin'
    }).then(response => response.json().then(result => ({ok: response.ok, result})));
}

function ipdBulkMailStatusRequest() {
    return fetch('iscrizioniPrimeMailTuttiStatus.php', {
        method: 'POST',
        body: ipdBulkMailFormData(false),
        credentials: 'same-origin'
    }).then(response => response.json().then(result => ({ok: response.ok, result})));
}

function ipdBulkMailStatusHtml(result) {
    const testMode = !!result.test_mode_config;
    const gmail = result.gmail_subject_scan || {};
    const accounts = Array.isArray(gmail.accounts) ? gmail.accounts : [];
    const accountHtml = accounts.length
        ? '<details style="margin-top:8px;"><summary>Dettaglio Gmail per account</summary>'
            + '<ul style="margin:6px 0 0 18px;">'
            + accounts.map(row => '<li><strong>' + ipdEscape(row.account || '') + '</strong>: '
                + Number(row.matches || 0) + ' con oggetto, '
                + Number(row.test_matches || 0) + ' in modalita test'
                + (row.warning ? ' <span style="color:#b91c1c;">(' + ipdEscape(row.warning) + ')</span>' : '')
                + '</li>').join('')
            + '</ul></details>'
        : '';
    const warningHtml = Array.isArray(gmail.warnings) && gmail.warnings.length
        ? '<div style="margin-top:8px;color:#b91c1c;"><strong>Avvisi Gmail:</strong><br>'
            + gmail.warnings.map(ipdEscape).join('<br>') + '</div>'
        : '';
    const modeText = testMode
        ? '<strong style="color:#b45309;">Modalita test ATTIVA: il prossimo invio andra agli account mittenti.</strong>'
        : '<strong style="color:#047857;">Modalita test DISATTIVA: il prossimo invio e reale.</strong>';
    return '<div class="alert alert-info" style="margin-bottom:0;">'
        + '<div>' + modeText + '</div>'
        + '<div style="margin-top:8px;">Destinatari comunicazione: <strong>' + Number(result.total_recipients || 0) + '</strong></div>'
        + '<div>Mail reali gia inviate: <strong>' + Number(result.real_sent || 0) + '</strong></div>'
        + '<div>Mail reali ancora da inviare: <strong>' + Number(result.real_pending || 0) + '</strong></div>'
        + '<div>Mail test gia inviate: <strong>' + Number(result.test_sent || 0) + '</strong></div>'
        + '<hr style="margin:10px 0;border-color:#cbd5e1;">'
        + '<div><strong>Controllo Gmail posta inviata, solo per oggetto</strong></div>'
        + '<div>Mail Gmail controllate: <strong>' + Number(gmail.checked || 0) + '</strong></div>'
        + '<div>Mail trovate con questo oggetto: <strong>' + Number(gmail.matches || 0) + '</strong></div>'
        + '<div>Di cui riconosciute come modalita test: <strong>' + Number(gmail.test_matches || 0) + '</strong></div>'
        + '<div>Destinatari test unici dedotti dal corpo: <strong>' + Number(gmail.test_unique_recipients || 0) + '</strong></div>'
        + '<div>Destinatari test presenti nella platea attuale: <strong>' + Number(gmail.test_recipients_in_current_audience || 0) + '</strong></div>'
        + '<div>Destinatari test fuori dalla platea attuale: <strong>' + Number(gmail.test_recipients_outside_current_audience || 0) + '</strong></div>'
        + (Array.isArray(gmail.test_recipients_outside_current_audience_samples) && gmail.test_recipients_outside_current_audience_samples.length
            ? '<div style="margin-top:6px;"><strong>Fuori platea:</strong> ' + gmail.test_recipients_outside_current_audience_samples.map(ipdEscape).join(', ') + '</div>'
            : '')
        + accountHtml
        + warningHtml
        + '</div>';
}

async function ipdCheckBulkMailStatus() {
    const error = document.getElementById('ipdBulkMailError');
    const status = document.getElementById('ipdBulkMailStatus');
    const subject = (document.getElementById('ipdBulkMailSubject')?.value || '').trim();
    const message = (document.getElementById('ipdBulkMailMessage')?.value || '').trim();
    if (subject === '' || message.length < 4) {
        if (error) {
            error.textContent = 'Inserire oggetto e testo della comunicazione.';
            error.hidden = false;
        }
        return;
    }
    if (error) {
        error.hidden = true;
        error.textContent = '';
    }
    if (status) {
        status.textContent = 'Verifica invio in corso...';
    }
    try {
        const payload = await ipdBulkMailStatusRequest();
        if (!payload.ok || !payload.result.ok) {
            throw new Error(payload.result.message || 'Impossibile verificare lo stato dell\'invio.');
        }
        if (status) {
            status.innerHTML = ipdBulkMailStatusHtml(payload.result);
        }
    } catch (err) {
        if (error) {
            error.textContent = err.message;
            error.hidden = false;
        }
    }
}

async function ipdStartBulkMail() {
    const error = document.getElementById('ipdBulkMailError');
    const button = document.getElementById('ipdBulkMailSendButton');
    const cancelButton = document.getElementById('ipdBulkMailCancelButton');
    const statusButton = document.getElementById('ipdBulkMailStatusButton');
    const subject = (document.getElementById('ipdBulkMailSubject')?.value || '').trim();
    const message = (document.getElementById('ipdBulkMailMessage')?.value || '').trim();
    let completed = false;
    if (subject === '' || message.length < 4) {
        if (error) {
            error.textContent = 'Inserire oggetto e testo della comunicazione.';
            error.hidden = false;
        }
        return;
    }
    const audienceLabel = document.getElementById('ipdBulkMailAudience')?.selectedOptions?.[0]?.textContent || 'destinatari selezionati';
    if (!confirm('Inviare questa comunicazione a: ' + audienceLabel + '?')) {
        return;
    }

    ipdBulkMailRunning = true;
    if (button) {
        button.disabled = true;
        button.textContent = 'Invio in corso...';
    }
    if (cancelButton) {
        cancelButton.disabled = true;
        cancelButton.textContent = 'Attendere...';
    }
    if (statusButton) {
        statusButton.disabled = true;
    }
    if (error) {
        error.hidden = true;
        error.textContent = '';
    }

    try {
        const preview = await ipdBulkMailRequest(true);
        if (!preview.ok || !preview.result.ok) {
            throw new Error(preview.result.message || 'Impossibile preparare l\'invio.');
        }
        if (preview.result.test_mode && !confirm('ATTENZIONE: GestOre e in modalita test. Le mail saranno inviate agli account mittenti, non alle famiglie. Continuare?')) {
            completed = false;
            return;
        }
        ipdBulkMailInitialRemaining = Number(preview.result.remaining || preview.result.sent || 0);
        ipdBulkMailSent = 0;
        ipdSetBulkMailProgress(ipdBulkMailInitialRemaining);

        let safety = 0;
        while (safety < 200) {
            safety++;
            const payload = await ipdBulkMailRequest(false);
            if (!payload.ok || !payload.result.ok) {
                throw new Error(payload.result.message || 'Invio interrotto.');
            }
            ipdBulkMailSent += Number(payload.result.sent || 0);
            ipdSetBulkMailProgress(Number(payload.result.remaining || 0));
            if (payload.result.last_batch || Number(payload.result.remaining || 0) <= 0 || Number(payload.result.sent || 0) <= 0) {
                document.getElementById('ipdBulkMailStatus').textContent = payload.result.message || 'Invio completato.';
                completed = true;
                break;
            }
        }
    } catch (err) {
        if (error) {
            error.textContent = err.message;
            error.hidden = false;
        }
    } finally {
        ipdBulkMailRunning = false;
        if (cancelButton) {
            cancelButton.disabled = false;
            cancelButton.style.display = completed ? 'none' : '';
            cancelButton.textContent = 'Annulla';
            cancelButton.className = 'btn btn-default';
        }
        if (button) {
            if (completed) {
                button.disabled = false;
                button.textContent = 'Chiudi';
                button.className = 'btn btn-success';
                button.onclick = ipdCloseBulkMailModal;
            } else {
                button.disabled = false;
                button.textContent = 'Riprova invio';
                button.className = 'btn btn-warning';
                button.onclick = ipdStartBulkMail;
            }
        }
        if (statusButton) {
            statusButton.disabled = false;
            statusButton.style.display = completed ? 'none' : '';
        }
    }
}

async function ipdSendCustomMail() {
    const praticaId = Number(ipdCustomMailPraticaId || 0);
    const subject = document.getElementById('ipdCustomMailSubject');
    const message = document.getElementById('ipdCustomMailMessage');
    const signature = document.getElementById('ipdCustomMailSignature');
    const error = document.getElementById('ipdCustomMailError');
    const subjectValue = (subject && subject.value ? subject.value : '').trim();
    const messageValue = (message && message.value ? message.value : '').trim();
    const signatureValue = (signature && signature.value ? signature.value : '').trim();
    const recipients = Array.from(document.querySelectorAll('.ipd-custom-mail-recipient:checked')).map(el => el.value);

    if (subjectValue === '' || messageValue.length < 4) {
        if (error) {
            error.textContent = 'Inserire oggetto e testo della comunicazione.';
            error.hidden = false;
        }
        return;
    }
    if (recipients.length <= 0) {
        if (error) {
            error.textContent = 'Selezionare almeno un destinatario.';
            error.hidden = false;
        }
        return;
    }
    const confirmed = await ipdConfirmMessageModal(
        'Conferma invio mail',
        'La comunicazione sara inviata a ' + recipients.length + ' destinatari selezionati.',
        recipients.map(ipdEscape).join('<br>')
    );
    if (!confirmed) {
        return;
    }

    const data = new FormData();
    data.append('id', praticaId);
    data.append('subject', subjectValue);
    data.append('message', messageValue);
    data.append('signature', signatureValue);
    recipients.forEach(email => data.append('recipients[]', email));

    fetch('iscrizioniPrimeMailPratica.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
    .then(response => response.json().then(result => ({ok: response.ok, result})))
    .then(payload => {
        if (!payload.ok || !payload.result.ok) {
            throw new Error(payload.result.message || 'Invio non riuscito.');
        }
        ipdCloseCustomMailModal();
        ipdShowMessageModal(
            'Mail inviata',
            payload.result.message || 'Comunicazione inviata.',
            'Destinatari selezionati: <strong>' + recipients.length + '</strong>',
            'success'
        );
        document.getElementById('ipdMessageClose').onclick = () => ipdReloadPratica(praticaId);
    })
    .catch(err => {
        ipdShowMessageModal('Invio non riuscito', err.message, '', 'error');
        if (error) {
            error.textContent = err.message;
            error.hidden = false;
        }
    });
}

function ipdOpenIntegrationModal(id) {
    ipdIntegrationPraticaId = id;
    const modal = document.getElementById('ipdIntegrationModal');
    const note = document.getElementById('ipdIntegrationNote');
    const error = document.getElementById('ipdIntegrationError');
    if (note) {
        note.value = '';
    }
    if (error) {
        error.hidden = true;
        error.textContent = '';
    }
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    setTimeout(() => note && note.focus(), 50);
}

function ipdCloseIntegrationModal() {
    const modal = document.getElementById('ipdIntegrationModal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    ipdIntegrationPraticaId = 0;
}

function ipdSubmitIntegrationRequest() {
    const note = document.getElementById('ipdIntegrationNote');
    const error = document.getElementById('ipdIntegrationError');
    const value = (note && note.value ? note.value : '').trim();
    if (value.length < 8) {
        if (error) {
            error.textContent = 'Scrivi una nota piu dettagliata per il genitore.';
            error.hidden = false;
        }
        return;
    }
    ipdSendStato(ipdIntegrationPraticaId, 'da_integrare', value);
}

function ipdOpenFormationNoteModal(id, studentName, note) {
    document.getElementById('ipdFormationNoteId').value = Number(id || 0);
    document.getElementById('ipdFormationNoteStudent').textContent = 'Pratica di ' + String(studentName || '').trim();
    document.getElementById('ipdFormationNoteText').value = note || '';
    const error = document.getElementById('ipdFormationNoteError');
    if (error) {
        error.hidden = true;
        error.textContent = '';
    }
    const modal = document.getElementById('ipdFormationNoteModal');
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    setTimeout(() => document.getElementById('ipdFormationNoteText')?.focus(), 50);
}

function ipdCloseFormationNoteModal() {
    const modal = document.getElementById('ipdFormationNoteModal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.getElementById('ipdFormationNoteId').value = '';
}

function ipdNoteHtml(value) {
    const text = String(value || '').trim();
    if (text === '') {
        return '<span class="text-muted">Nessuna nota inserita.</span>';
    }
    return ipdEscape(text).replace(/\r?\n/g, '<br>');
}

function ipdSaveFormationNote() {
    const id = Number(document.getElementById('ipdFormationNoteId').value || 0);
    const note = document.getElementById('ipdFormationNoteText').value || '';
    const error = document.getElementById('ipdFormationNoteError');
    const button = document.getElementById('ipdFormationNoteSaveButton');
    const data = new FormData();
    data.append('id', String(id));
    data.append('note_genitori_iscrizione', note);
    if (button) {
        button.disabled = true;
        button.textContent = 'Salvataggio...';
    }
    if (error) {
        error.hidden = true;
        error.textContent = '';
    }

    fetch('iscrizioniPrimeNoteSave.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
    .then(response => response.json().then(payload => ({ok: response.ok, payload})))
    .then(result => {
        if (!result.ok || !result.payload.ok) {
            throw new Error(result.payload.message || 'Errore salvataggio note.');
        }
        const value = document.getElementById('ipd_note_formazione_value_' + id);
        if (value) {
            value.innerHTML = ipdNoteHtml(result.payload.note_genitori_iscrizione || '');
        }
        const button = document.getElementById('ipd_note_formazione_button_' + id);
        if (button) {
            const studentName = String(document.getElementById('ipdFormationNoteStudent').textContent || '').replace(/^Pratica di\s*/i, '');
            const savedNote = result.payload.note_genitori_iscrizione || '';
            button.onclick = function () {
                ipdOpenFormationNoteModal(id, studentName, savedNote);
            };
        }
        ipdCloseFormationNoteModal();
    })
    .catch(err => {
        if (error) {
            error.textContent = err.message;
            error.hidden = false;
        }
    })
    .finally(() => {
        if (button) {
            button.disabled = false;
            button.textContent = 'Salva note';
        }
    });
}

function ipdSendStato(id, stato, note) {
    const data = new FormData();
    data.append('id', id);
    data.append('stato', stato);
    data.append('note', note || '');

    ipdShowBusy('Aggiornamento pratica', 'Sto salvando il nuovo stato. Attendere...');
    fetch('iscrizioniPrimeStato.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(result => {
        if (!result.ok) {
            throw new Error(result.message || 'Aggiornamento non riuscito.');
        }
        if (result.warning) {
            alert(result.message);
        }
        ipdReloadPratica(id);
    })
    .catch(error => {
        ipdHideBusy();
        alert(error.message);
    });
}

function ipdSaveTerzeValues(event, id) {
    event.preventDefault();
    const form = event.target;
    const button = form.querySelector('button[type="submit"]');
    const status = document.getElementById('ipdTerzeValuesStatus-' + id);
    const data = new FormData(form);
    data.append('id', id);

    if (button) {
        button.disabled = true;
    }
    if (status) {
        status.textContent = 'Salvataggio...';
        status.style.color = '#475569';
    }

    fetch('iscrizioniTerzeValoriSave.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
    .then(response => response.json().then(payload => ({ok: response.ok, payload})))
    .then(result => {
        if (!result.ok || !result.payload.ok) {
            throw new Error(result.payload.message || 'Errore salvataggio valori.');
        }
        if (status) {
            status.textContent = result.payload.message || 'Valori salvati.';
            status.style.color = '#166534';
        }
    })
    .catch(error => {
        if (status) {
            status.textContent = error.message;
            status.style.color = '#b91c1c';
        } else {
            alert(error.message);
        }
    })
    .finally(() => {
        if (button) {
            button.disabled = false;
        }
    });

    return false;
}

function ipdReloadPratica(praticaId) {
    const url = new URL(window.location.href);
    const id = Number(praticaId || 0);
    if (id > 0) {
        url.searchParams.set('open_pratica_id', String(id));
    }
    url.hash = 'pratica-' + id;
    window.history.replaceState({}, '', url.toString());
    window.location.reload();
}

function ipdSelectedFileCount(form) {
    const input = form ? form.querySelector('input[type="file"]') : null;
    return input && input.files ? input.files.length : 0;
}

function ipdUploadSegreteriaDocumentoForm(form, praticaId, tipo) {
    const data = new FormData(form);
    const modeInput = form ? form.querySelector('input[name="upload_mode"]') : null;
    data.set('upload_mode', modeInput && modeInput.value === 'append' ? 'append' : 'replace');
    data.append('pratica_id', praticaId);
    data.append('tipo', tipo);

    return fetch('iscrizioniPrimeSegreteriaDocumentoUpload.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
    .then(response => response.text().then(text => {
        let data;
        try {
            data = text ? JSON.parse(text) : {};
        } catch (e) {
            throw new Error('Risposta non valida dal server durante il caricamento PDF.');
        }
        return {ok: response.ok, data};
    }))
    .then(result => {
        if (!result.ok || !result.data.ok) {
            throw new Error(result.data.message || 'Errore caricamento PDF');
        }
        return result.data;
    });
}

function ipdUploadSegreteriaDocumento(event, praticaId, tipo) {
    event.preventDefault();
    const form = event.target;
    const button = form.querySelector('button[type="submit"]');
    const originalHtml = button ? button.innerHTML : '';
    if (ipdSelectedFileCount(form) <= 0) {
        alert('Selezionare almeno un PDF da caricare.');
        return false;
    }

    if (button) {
        button.disabled = true;
        button.textContent = 'Caricamento...';
    }

    ipdShowBusy('Caricamento allegato', 'Sto salvando il PDF nella pratica. Attendere...');
    ipdUploadSegreteriaDocumentoForm(form, praticaId, tipo)
    .then(data => {
        ipdReloadPratica(praticaId);
    })
    .catch(error => {
        ipdHideBusy();
        alert(error.message);
        if (button) {
            button.disabled = false;
            button.innerHTML = originalHtml || '<span class="glyphicon glyphicon-upload"></span> Carica PDF';
        }
    });

    return false;
}

function ipdDeleteSegreteriaDocumento(praticaId, tipo) {
    if (!window.confirm('Cancellare questo allegato? Dopo la cancellazione potrai ricaricare un nuovo PDF.')) {
        return false;
    }
    const data = new FormData();
    data.append('pratica_id', String(praticaId));
    data.append('tipo', tipo);

    ipdShowBusy('Cancellazione allegato', 'Sto aggiornando la pratica. Attendere...');
    fetch('iscrizioniPrimeSegreteriaDocumentoDelete.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
    .then(response => response.text().then(text => {
        let payload;
        try {
            payload = text ? JSON.parse(text) : {};
        } catch (e) {
            throw new Error('Risposta non valida dal server durante la cancellazione allegato.');
        }
        return {ok: response.ok, payload};
    }))
    .then(result => {
        if (!result.ok || !result.payload.ok) {
            throw new Error(result.payload.message || 'Errore cancellazione allegato.');
        }
        ipdReloadPratica(praticaId);
    })
    .catch(error => {
        ipdHideBusy();
        alert(error.message);
    });

    return false;
}

async function ipdUploadSegreteriaDocumentiSelezionati(event, praticaId) {
    event.preventDefault();
    const card = document.getElementById('pratica-' + Number(praticaId || 0));
    const status = document.getElementById('ipdUploadAllStatus-' + Number(praticaId || 0));
    const trigger = event.currentTarget;
    if (!card) {
        return false;
    }
    const forms = Array.from(card.querySelectorAll('.ipd-secretary-upload')).filter(function (form) {
        return ipdSelectedFileCount(form) > 0;
    });
    if (!forms.length) {
        if (status) {
            status.textContent = 'Seleziona almeno un PDF in una riga documento.';
            status.style.color = '#b91c1c';
        }
        return false;
    }

    const buttons = forms.map(function (form) {
        return form.querySelector('button[type="submit"]');
    }).filter(Boolean);
    const originalButtonHtml = new Map();
    buttons.forEach(function (button) {
        originalButtonHtml.set(button, button.innerHTML);
        button.disabled = true;
    });
    if (trigger) {
        trigger.disabled = true;
    }
    ipdShowBusy('Caricamento allegati', 'Sto salvando i PDF selezionati. Attendere...');

    let uploaded = 0;
    const errors = [];
    for (const form of forms) {
        const tipo = form.dataset.tipoDocumento || '';
        const button = form.querySelector('button[type="submit"]');
        if (button) {
            button.textContent = 'Caricamento...';
        }
        if (status) {
            status.textContent = 'Caricamento ' + (uploaded + 1) + ' di ' + forms.length + '...';
            status.style.color = '#475569';
        }
        try {
            await ipdUploadSegreteriaDocumentoForm(form, praticaId, tipo);
            uploaded++;
        } catch (error) {
            errors.push(error.message);
        }
    }

    if (uploaded > 0) {
        if (errors.length) {
            alert(uploaded + ' documenti caricati. Errori: ' + errors.join(' | '));
        }
        ipdReloadPratica(praticaId);
        return false;
    }

    if (status) {
        status.textContent = errors.length ? errors.join(' | ') : 'Nessun documento caricato.';
        status.style.color = '#b91c1c';
    }
    ipdHideBusy();
    buttons.forEach(function (button) {
        button.disabled = false;
        button.innerHTML = originalButtonHtml.get(button) || '<span class="glyphicon glyphicon-upload"></span> Carica PDF';
    });
    if (trigger) {
        trigger.disabled = false;
    }
    return false;
}

document.getElementById('ipdManualEventForm').addEventListener('submit', function (event) {
    event.preventDefault();
    const form = event.target;
    const button = document.getElementById('ipdManualEventSaveButton');
    const error = document.getElementById('ipdManualEventError');
    const data = new FormData(form);
    const praticaId = Number(data.get('pratica_id') || ipdManualEventPraticaId || 0);
    if (error) {
        error.hidden = true;
        error.textContent = '';
    }
    if (button) {
        button.disabled = true;
        button.textContent = 'Salvataggio...';
    }
    ipdShowBusy('Salvataggio evento', 'Sto aggiungendo la riga allo storico. Attendere...');

    fetch('iscrizioniPrimeEventoSave.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
    .then(response => response.json().then(payload => ({ok: response.ok, payload})))
    .then(result => {
        if (!result.ok || !result.payload.ok) {
            throw new Error(result.payload.message || 'Errore salvataggio evento.');
        }
        ipdReloadPratica(praticaId);
    })
    .catch(err => {
        ipdHideBusy();
        if (error) {
            error.textContent = err.message;
            error.hidden = false;
        } else {
            alert(err.message);
        }
        if (button) {
            button.disabled = false;
            button.textContent = 'Salva evento';
        }
    });
});
</script>

</body>
</html>
