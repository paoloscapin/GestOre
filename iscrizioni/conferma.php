<?php

require_once '../common/path.php';
require_once '../common/__Settings.php';
require_once '../common/iscrizioniPrimeLib.php';

iscrizioniPrimeEnsureSchema();

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function confirmedValue(array $pratica, array $confirmed, string $field): string
{
    if (array_key_exists($field, $confirmed)) {
        return (string)$confirmed[$field];
    }

    return (string)($pratica[$field] ?? '');
}

function hasSecondResponsible(array $pratica, array $confirmed): bool
{
    $values = [
        $pratica['responsabile_2_cognome'] ?? '',
        $pratica['responsabile_2_nome'] ?? '',
        confirmedValue($pratica, $confirmed, 'email_genitore_2'),
        confirmedValue($pratica, $confirmed, 'telefono_genitore_2'),
    ];

    foreach ($values as $value) {
        if (trim((string)$value) !== '') {
            return true;
        }
    }

    return false;
}

$token = trim((string)($_GET['t'] ?? ''));
$pratica = iscrizioniPrimeGetByToken($token);
$confirmed = [];
$documents = [];
$annoScolastico = $pratica ? trim((string)($pratica['anno_scolastico'] ?? '')) : '';
if ($annoScolastico === '') {
    $annoScolastico = '2026-27';
}
$nomeIstituto = trim((string)($__settings->local->nomeIstituto ?? 'ITT Buonarroti - Trento'));
$praticaBloccata = $pratica && in_array((string)($pratica['stato'] ?? ''), ['inviata', 'verificata', 'annullata'], true);

if (!$pratica) {
    http_response_code(404);
} elseif (!empty($pratica['dati_confermati_json'])) {
    $decoded = json_decode((string)$pratica['dati_confermati_json'], true);
    if (is_array($decoded)) {
        $confirmed = $decoded;
    }
    $documents = iscrizioniPrimeDocumentsForPratica((int)$pratica['id']);
} elseif ($pratica) {
    $documents = iscrizioniPrimeDocumentsForPratica((int)$pratica['id']);
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Conferma iscrizione</title>
    <link rel="icon" href="<?php echo h($__application_base_path); ?>/ore-32.png" type="image/png">
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        [hidden] { display: none !important; }
        body { margin: 0; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f5f7fb; color: #172033; }
        .page { max-width: 920px; margin: 0 auto; padding: 18px; }
        .card { background: #fff; border: 1px solid #d9e0ea; border-radius: 8px; box-shadow: 0 8px 28px rgba(23,32,51,.08); padding: 18px; margin: 14px 0; }
        .school-header { display: flex; gap: 14px; align-items: center; }
        .school-logo { width: 76px; height: 58px; object-fit: contain; flex: 0 0 auto; }
        .school-kicker { color: #475569; font-size: 14px; font-weight: 750; text-transform: uppercase; letter-spacing: .02em; }
        .school-name { font-size: 18px; font-weight: 800; margin-top: 2px; }
        .school-year { display: inline-block; margin-top: 8px; border-radius: 999px; background: #e0f2fe; color: #075985; padding: 5px 10px; font-size: 13px; font-weight: 800; }
        h1 { font-size: 24px; margin: 0 0 8px; }
        h2 { font-size: 18px; margin: 0 0 12px; }
        h3 { font-size: 15px; margin: 18px 0 10px; }
        .muted { color: #64748b; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 16px; }
        .field { border-bottom: 1px solid #edf1f6; padding: 8px 0; }
        .label { color: #64748b; font-size: 13px; margin-bottom: 4px; }
        .value { font-weight: 650; overflow-wrap: anywhere; }
        .notice { border-left: 4px solid #0ea5e9; background: #eaf6fc; padding: 12px; border-radius: 6px; }
        .error { border-left-color: #dc2626; background: #fee2e2; }
        .success { border-left-color: #16a34a; background: #e9f8ef; }
        .privacy-link { display: inline-block; margin-top: 8px; color: #0369a1; font-weight: 750; }
        .form-row { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
        label { font-weight: 650; }
        .hint { color: #64748b; font-size: 13px; line-height: 1.35; }
        input[type="email"], input[type="tel"], input[type="text"] { width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 11px 12px; font: inherit; background: #fff; color: #172033; }
        input:focus { border-color: #0ea5e9; outline: 3px solid rgba(14,165,233,.18); }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
        button { border: 0; border-radius: 6px; padding: 11px 16px; font: inherit; font-weight: 700; cursor: pointer; }
        .btn-primary { background: #0f766e; color: #fff; }
        .btn-secondary { background: #334155; color: #fff; }
        .btn-primary:disabled, .btn-secondary:disabled { opacity: .65; cursor: wait; }
        .check { display: flex; gap: 9px; align-items: flex-start; margin-top: 10px; color: #334155; }
        .check input { margin-top: 4px; }
        .status-line { min-height: 24px; margin-top: 12px; font-weight: 650; }
        .doc-list { display: grid; gap: 12px; }
        .doc-item { border: 1px solid var(--doc-border, #d9e0ea); border-left: 7px solid var(--doc-accent, var(--doc-border, #d9e0ea)); border-radius: 8px; padding: 12px; background: var(--doc-bg, #fbfdff); box-shadow: inset 0 0 0 9999px rgba(255,255,255,.18); }
        .doc-head { display: flex; justify-content: space-between; gap: 10px; align-items: flex-start; margin-bottom: 8px; }
        .doc-meta { min-width: 0; }
        .doc-title { font-weight: 750; }
        .doc-current { overflow-wrap: anywhere; }
        .badge { border-radius: 999px; padding: 4px 9px; font-size: 12px; font-weight: 750; background: #e2e8f0; color: #334155; white-space: nowrap; }
        .badge-ok { background: #dcfce7; color: #166534; }
        .badge-paper { background: #fef3c7; color: #92400e; }
        .doc-upload { display: grid; gap: 12px; margin-top: 10px; }
        .doc-action-group { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; background: #fff; }
        .doc-choice-intro { border: 2px solid var(--doc-accent, #0ea5e9); background: rgba(255,255,255,.72); border-radius: 8px; padding: 10px; font-weight: 800; color: #172033; }
        .doc-form.is-complete { padding-top: 10px; padding-bottom: 10px; }
        .doc-form.is-complete .doc-head { margin-bottom: 0; }
        .doc-form.is-complete .doc-upload { margin-top: 8px; }
        .doc-form.is-complete .doc-choice-intro,
        .doc-form.is-complete .doc-choice-group,
        .doc-form.is-complete .doc-existing-options,
        .doc-form.is-complete .doc-final-group,
        .doc-form.is-complete .photo-preview,
        .doc-form.is-complete .doc-pending,
        .doc-form.is-complete .doc-status { display: none !important; }
        .doc-form.is-complete .doc-current { font-size: 13px; }
        .doc-form.is-complete .doc-clear-group { padding: 8px; }
        .doc-form.is-pending .doc-choice-intro,
        .doc-form.is-pending .doc-choice-group,
        .doc-form.is-pending .doc-existing-options,
        .doc-form.is-pending .doc-clear-group { display: none !important; }
        .doc-form.is-missing .doc-clear-group,
        .doc-form.is-missing .doc-existing-options { display: none !important; }
        .readonly-documents .doc-upload,
        .readonly-documents .photo-preview,
        .readonly-documents .doc-pending,
        .readonly-documents .doc-status { display: none !important; }
        .doc-action-title { color: #334155; font-size: 13px; font-weight: 750; margin-bottom: 8px; }
        .doc-action-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
        .doc-mode-options { display: grid; gap: 8px; }
        .doc-mode-options label { display: flex; gap: 8px; align-items: flex-start; font-weight: 650; color: #334155; }
        .doc-mode-options input { margin-top: 3px; }
        .doc-upload input[type="file"] { position: absolute; left: -9999px; width: 1px; height: 1px; opacity: 0; }
        .doc-upload button { background: #0ea5e9; color: #fff; }
        .doc-choice-title { font-size: 16px; font-weight: 800; margin-bottom: 4px; color: #172033; }
        .doc-choice-step { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 999px; background: #172033; color: #fff; font-weight: 800; margin-right: 6px; }
        .doc-final-group { border-color: #bbf7d0; background: #f0fdf4; }
        .btn-file { background: #0ea5e9 !important; color: #fff; }
        .btn-native-camera { background: #2563eb !important; color: #fff; }
        .btn-paper { background: #a16207 !important; color: #fff; }
        .btn-delete { background: #b91c1c !important; color: #fff; }
        .btn-final { background: #0f766e !important; color: #fff; }
        .btn-submit-final { background: #7c2d12; color: #fff; font-size: 17px; }
        .btn-final:disabled { opacity: .55; cursor: not-allowed; }
        .doc-help { margin: 8px 0 0; color: #475569; font-size: 13px; line-height: 1.4; }
        .doc-pending { margin-top: 10px; padding: 9px 10px; border-radius: 6px; background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; font-weight: 700; display: none; }
        .doc-view { display: inline-block; margin-top: 8px; color: #0369a1; font-weight: 700; }
        .photo-preview { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
        .photo-chip { border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px; background: #fff; display: flex; align-items: center; gap: 6px; max-width: 100%; }
        .photo-chip img { width: 52px; height: 52px; object-fit: cover; border-radius: 4px; flex: 0 0 auto; }
        .photo-chip button { background: #b91c1c; color: #fff; padding: 6px 9px; }
        .camera-modal { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(15,23,42,.72); z-index: 2000; padding: 14px; }
        .camera-modal.open { display: flex; }
        .camera-panel { width: min(760px, 100%); max-height: calc(100vh - 28px); overflow: auto; background: #fff; border-radius: 8px; padding: 14px; box-shadow: 0 16px 42px rgba(0,0,0,.28); display: flex; flex-direction: column; }
        .camera-video { width: 100%; max-height: 70vh; background: #0f172a; border-radius: 6px; }
        .camera-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px; position: sticky; bottom: -14px; background: #fff; padding: 10px 0 calc(10px + env(safe-area-inset-bottom)); z-index: 2; }
        .camera-actions button { background: #334155; color: #fff; }
        .camera-actions .btn-primary { background: #0f766e; }
        .edit-image { max-width: 100%; max-height: 68vh; display: block; }
        .edit-canvas { width: 100%; max-height: 58vh; display: block; background: #111827; border-radius: 6px; touch-action: none; transform-origin: center center; transition: transform .08s linear; }
        .rotate-control { display: flex; align-items: center; gap: 10px; flex: 1 1 100%; color: #334155; font-weight: 700; }
        .rotate-control input { flex: 1; min-width: 140px; }
        .busy-overlay { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; z-index: 3000; background: rgba(15,23,42,.72); padding: 16px; }
        .busy-overlay.open { display: flex; }
        .busy-box { background: #fff; border-radius: 8px; padding: 18px; width: min(360px, 100%); text-align: center; font-weight: 750; box-shadow: 0 18px 46px rgba(0,0,0,.3); }
        .busy-spinner { width: 42px; height: 42px; margin: 0 auto 12px; border: 5px solid #dbeafe; border-top-color: #2563eb; border-radius: 999px; animation: spin 1s linear infinite; }
        .success-overlay { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; z-index: 3200; background: rgba(15,23,42,.76); padding: 16px; }
        .success-overlay.open { display: flex; }
        .success-card { width: min(520px, 100%); background: #fff; border-radius: 8px; padding: 24px; text-align: center; box-shadow: 0 22px 56px rgba(0,0,0,.34); border-top: 8px solid #15803d; }
        .success-card.warning { border-top-color: #b45309; }
        .success-icon { width: 74px; height: 74px; margin: 0 auto 14px; border-radius: 999px; background: #dcfce7; color: #15803d; display: flex; align-items: center; justify-content: center; font-size: 46px; font-weight: 900; line-height: 1; }
        .success-card.warning .success-icon { background: #fef3c7; color: #b45309; }
        .success-card h2 { margin: 0 0 8px; font-size: 26px; color: #172033; }
        .success-card p { margin: 8px 0; color: #334155; font-size: 16px; line-height: 1.45; }
        .success-card .success-main { font-weight: 750; color: #14532d; }
        .success-card.warning .success-main { color: #7c2d12; }
        .success-card button { margin-top: 16px; background: #15803d; color: #fff; width: 100%; }
        .success-card.warning button { background: #b45309; }
        .error-list { text-align: left; margin: 12px 0 4px; padding-left: 20px; color: #7c2d12; font-weight: 750; line-height: 1.45; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (max-width: 680px) {
            .grid { grid-template-columns: 1fr; }
            .page { padding: 10px; }
            .school-header { align-items: flex-start; }
            .school-logo { width: 68px; height: 52px; }
            .actions button { width: 100%; }
            .doc-head { align-items: flex-start; }
            .doc-action-buttons { display: grid; grid-template-columns: 1fr 1fr; }
            .doc-action-buttons button { width: 100%; padding-left: 8px; padding-right: 8px; }
            .doc-action-buttons.single { grid-template-columns: 1fr; }
            .doc-help { font-size: 12px; }
            .photo-chip { width: 100%; align-items: flex-start; }
            .photo-chip span { flex: 1; min-width: 0; overflow-wrap: anywhere; }
            .camera-modal { padding: 6px; align-items: stretch; }
            .camera-panel { max-height: calc(100vh - 12px); border-radius: 6px; }
            .camera-actions button { flex: 1 1 42%; }
        }
    </style>
</head>
<body>
<main class="page">
    <div class="card">
        <div class="school-header">
            <img class="school-logo" src="<?php echo h($__application_base_path); ?>/img/logoB_google.png" alt="Logo <?php echo h($nomeIstituto); ?>">
            <div>
                <div class="school-kicker">Istituto scolastico</div>
                <div class="school-name"><?php echo h($nomeIstituto); ?></div>
                <div class="school-year">Anno scolastico <?php echo h($annoScolastico); ?></div>
            </div>
        </div>
        <h1 style="margin-top: 18px;">Conferma dati iscrizione</h1>
        <div class="muted">Future classi prime</div>
    </div>

    <?php if (!$pratica) : ?>
        <div class="card notice error">
            Link non valido, scaduto o pratica non disponibile.
        </div>
    <?php else : ?>
        <?php if ($praticaBloccata) : ?>
            <div class="card notice success">
                La conferma dati iscrizione e gia stata inviata. Da questo link puoi consultare il riepilogo e i documenti caricati, ma non puoi piu modificare la pratica.
            </div>
        <?php else : ?>
            <div class="card notice">
                Verifica i dati anagrafici e aggiorna email e telefoni. Puoi salvare una bozza e rientrare da questo stesso link prima dell'invio definitivo.
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>Studente</h2>
            <div class="grid">
                <div class="field"><div class="label">Cognome</div><div class="value"><?php echo h($pratica['cognome']); ?></div></div>
                <div class="field"><div class="label">Nome</div><div class="value"><?php echo h($pratica['nome']); ?></div></div>
                <div class="field"><div class="label">Codice fiscale</div><div class="value"><?php echo h($pratica['codice_fiscale']); ?></div></div>
                <div class="field"><div class="label">Data nascita</div><div class="value"><?php echo h(iscrizioniPrimeFormatDateIt($pratica['data_nascita'] ?? '')); ?></div></div>
                <div class="field"><div class="label">Corso</div><div class="value"><?php echo h($pratica['corso_studi']); ?></div></div>
                <div class="field"><div class="label">Stato pratica</div><div class="value"><?php echo h($pratica['stato']); ?></div></div>
            </div>
        </div>

        <form id="iscrizioneForm" class="card" autocomplete="on">
            <h2>Dati da confermare</h2>
            <input type="hidden" name="token" value="<?php echo h($token); ?>">

            <h3>Studente</h3>
            <div class="grid">
                <div class="form-row">
                    <label for="email_studente">Email studente</label>
                    <input type="email" id="email_studente" name="email_studente" value="<?php echo h(confirmedValue($pratica, $confirmed, 'email_studente')); ?>" <?php echo $praticaBloccata ? 'disabled' : ''; ?>>
                    <div class="hint">Indicare solo una email personale dello studente. Non usare la mail della scuola media; se non disponibile lasciare vuoto.</div>
                </div>
                <div class="form-row">
                    <label for="telefono_studente">Telefono studente</label>
                    <input type="tel" id="telefono_studente" name="telefono_studente" value="<?php echo h(confirmedValue($pratica, $confirmed, 'telefono_studente')); ?>" <?php echo $praticaBloccata ? 'disabled' : ''; ?>>
                </div>
            </div>

            <h3><?php echo h($pratica['responsabile_1_tipo'] ?: 'Responsabile 1'); ?></h3>
            <div class="field">
                <div class="label">Nome</div>
                <div class="value"><?php echo h(trim(($pratica['responsabile_1_cognome'] ?? '') . ' ' . ($pratica['responsabile_1_nome'] ?? ''))); ?></div>
            </div>
            <div class="grid">
                <div class="form-row">
                    <label for="email_genitore_1">Email responsabile 1</label>
                    <input type="email" id="email_genitore_1" name="email_genitore_1" value="<?php echo h(confirmedValue($pratica, $confirmed, 'email_genitore_1')); ?>" <?php echo $praticaBloccata ? 'disabled' : ''; ?>>
                </div>
                <div class="form-row">
                    <label for="telefono_genitore_1">Telefono responsabile 1</label>
                    <input type="tel" id="telefono_genitore_1" name="telefono_genitore_1" value="<?php echo h(confirmedValue($pratica, $confirmed, 'telefono_genitore_1')); ?>" <?php echo $praticaBloccata ? 'disabled' : ''; ?>>
                </div>
            </div>

            <h3><?php echo h($pratica['responsabile_2_tipo'] ?: 'Responsabile 2'); ?></h3>
            <div class="field">
                <div class="label">Nome</div>
                <div class="value"><?php echo h(trim(($pratica['responsabile_2_cognome'] ?? '') . ' ' . ($pratica['responsabile_2_nome'] ?? ''))); ?></div>
            </div>
            <div class="grid">
                <div class="form-row">
                    <label for="email_genitore_2">Email responsabile 2</label>
                    <input type="email" id="email_genitore_2" name="email_genitore_2" value="<?php echo h(confirmedValue($pratica, $confirmed, 'email_genitore_2')); ?>" <?php echo $praticaBloccata ? 'disabled' : ''; ?>>
                </div>
                <div class="form-row">
                    <label for="telefono_genitore_2">Telefono responsabile 2</label>
                    <input type="tel" id="telefono_genitore_2" name="telefono_genitore_2" value="<?php echo h(confirmedValue($pratica, $confirmed, 'telefono_genitore_2')); ?>" <?php echo $praticaBloccata ? 'disabled' : ''; ?>>
                </div>
            </div>

            <label class="check">
                <input type="checkbox" name="privacy_confermata" value="1" <?php echo !empty($confirmed['privacy_confermata']) ? 'checked' : ''; ?> <?php echo $praticaBloccata ? 'disabled' : ''; ?>>
                <span>Confermo che i dati indicati sono corretti o aggiornati.</span>
            </label>

            <?php if (!$praticaBloccata) : ?>
                <div class="actions">
                    <button type="submit" class="btn-primary" data-action="draft">Salva bozza</button>
                    <button type="submit" class="btn-secondary" data-action="documents">Salva e vai ai documenti</button>
                </div>
            <?php endif; ?>
            <div id="saveStatus" class="status-line" aria-live="polite"></div>
        </form>

        <div class="card <?php echo $praticaBloccata ? 'readonly-documents' : ''; ?>">
            <h2>Documenti</h2>
            <div class="muted">Puoi caricare uno o piu PDF gia pronti oppure, da telefono, acquisire il documento come foto. Se servono piu pagine, seleziona o scatta piu foto: GestOre unisce tutto in un unico PDF multipagina.</div>
            <div class="doc-help">Le foto devono essere chiare, dritte e leggibili. Prima di caricare puoi eliminare una foto venuta male dalla selezione del telefono; dopo il caricamento puoi cancellare il documento e rifarlo.</div>
            <div class="notice" style="margin-top: 12px;">
                I documenti vengono raccolti solo per la gestione della pratica di iscrizione e per gli adempimenti scolastici collegati.
                <br>
                <a class="privacy-link" href="privacy_documenti.php?t=<?php echo rawurlencode($token); ?>" target="_blank" rel="noopener">Leggi l'informativa privacy sui documenti caricati</a>
            </div>
            <div class="doc-list" style="margin-top: 14px;">
                <?php
                $documentColors = [
                    ['#bfdbfe', '#60a5fa', '#1d4ed8'],
                    ['#bbf7d0', '#4ade80', '#15803d'],
                    ['#fed7aa', '#fb923c', '#c2410c'],
                    ['#fde68a', '#facc15', '#a16207'],
                    ['#fbcfe8', '#f472b6', '#be185d'],
                    ['#ddd6fe', '#a78bfa', '#6d28d9'],
                    ['#99f6e4', '#2dd4bf', '#0f766e'],
                    ['#fecaca', '#f87171', '#b91c1c'],
                ];
                $documentIndex = 0;
                foreach ($documents as $document) :
                    $tipo = (string)$document['tipo_documento'];
                    if (in_array($tipo, ['documento_identita_genitore_2', 'codice_fiscale_genitore_2'], true) && !hasSecondResponsible($pratica, $confirmed)) {
                        continue;
                    }
                    $documentColor = $documentColors[$documentIndex % count($documentColors)];
                    $documentIndex++;
                    $label = iscrizioniPrimeDocumentTypes()[$tipo] ?? $tipo;
                    $isOptional = in_array($tipo, ['attestazione_erogazione_liberale', 'altro'], true);
                    $isPaper = (string)$document['stato'] === 'consegna_cartacea';
                    $isUploaded = !$isPaper && (string)$document['stato'] !== 'mancante' && !empty($document['original_name']);
                    $documentStatusText = $isPaper ? 'Consegna cartacea in segreteria didattica' : ($isUploaded ? (string)$document['original_name'] : 'Non ancora caricato');
                    $badgeText = $isPaper ? 'Cartaceo' : ($isUploaded ? 'Caricato' : 'Mancante');
                    $badgeClass = $isPaper ? 'badge-paper' : ($isUploaded ? 'badge-ok' : '');
                    $viewUrl = 'visualizza_documento.php?t=' . rawurlencode($token) . '&tipo=' . rawurlencode($tipo);
                ?>
                    <form class="doc-item doc-form" enctype="multipart/form-data" data-doc-state="<?php echo $isPaper ? 'paper' : ($isUploaded ? 'uploaded' : 'missing'); ?>" style="--doc-bg: <?php echo h($documentColor[0]); ?>; --doc-border: <?php echo h($documentColor[1]); ?>; --doc-accent: <?php echo h($documentColor[2]); ?>;">
                        <input type="hidden" name="token" value="<?php echo h($token); ?>">
                        <input type="hidden" name="tipo_documento" value="<?php echo h($tipo); ?>">
                        <div class="doc-head">
                            <div class="doc-meta">
                                <div class="doc-title"><?php echo h($label); ?></div>
                                <?php if ($isOptional) : ?>
                                    <div class="hint">Facoltativo: da caricare solo se disponibile o se non gia consegnato/versato al momento dell'iscrizione.</div>
                                <?php endif; ?>
                                <div class="muted doc-current"><?php echo h($documentStatusText); ?></div>
                                <?php if ($isUploaded) : ?>
                                    <a class="doc-view" href="<?php echo h($viewUrl); ?>" target="_blank" rel="noopener">Visualizza PDF caricato</a>
                                <?php else : ?>
                                    <a class="doc-view" href="<?php echo h($viewUrl); ?>" target="_blank" rel="noopener" hidden>Visualizza PDF caricato</a>
                                <?php endif; ?>
                            </div>
                            <span class="badge <?php echo h($badgeClass); ?>"><?php echo h($badgeText); ?></span>
                        </div>
                        <div class="doc-upload">
                            <input type="file" class="doc-file-input" name="documento[]" accept="application/pdf,.pdf" multiple>
                            <input type="file" class="doc-native-camera-input" accept="image/jpeg,image/png" capture="environment" multiple>
                            <input type="hidden" name="upload_mode" value="<?php echo $isUploaded ? 'append' : 'replace'; ?>" class="doc-upload-mode">
                            <div class="doc-choice-intro">
                                <div>Scegli una sola possibilita:</div>
                                <div>1, 2 oppure 3.</div>
                            </div>
                            <div class="doc-action-group doc-clear-group" <?php echo ($isUploaded || $isPaper) ? '' : 'hidden'; ?>>
                                <div class="doc-action-title">Scelta gia registrata</div>
                                <div class="doc-action-buttons single">
                                    <button type="button" class="btn-delete doc-delete"><?php echo $isPaper ? 'Annulla scelta cartacea' : 'Cancella PDF caricato'; ?></button>
                                </div>
                                <div class="doc-help">Se devi cambiare scelta, annulla prima quella registrata e poi scegli 1, 2 oppure 3.</div>
                            </div>
                            <div class="doc-action-group doc-existing-options" <?php echo $isUploaded ? '' : 'hidden'; ?>>
                                <div class="doc-action-title">PDF gia caricato</div>
                                <div class="doc-mode-options">
                                    <label>
                                        <input type="radio" name="upload_mode_choice_<?php echo h($tipo); ?>" value="append" checked>
                                        <span>Aggiungi i nuovi file al PDF gia caricato.</span>
                                    </label>
                                    <label>
                                        <input type="radio" name="upload_mode_choice_<?php echo h($tipo); ?>" value="replace">
                                        <span>Sostituisci il PDF gia caricato con i nuovi file.</span>
                                    </label>
                                </div>
                            </div>
                            <div class="doc-action-group doc-choice-group">
                                <div class="doc-choice-title"><span class="doc-choice-step">1</span>Carico un PDF gia pronto</div>
                                <div class="doc-action-buttons">
                                    <button type="button" class="btn-file doc-file-button">Aggiungi PDF</button>
                                </div>
                                <div class="doc-help">Usa questa scelta se hai gia il documento in PDF. Puoi aggiungere anche piu PDF: GestOre li unira in un unico file finale.</div>
                            </div>
                            <div class="doc-action-group doc-choice-group">
                                <div class="doc-choice-title"><span class="doc-choice-step">2</span>Scatto una foto del documento</div>
                                <div class="doc-action-buttons">
                                    <button type="button" class="btn-native-camera doc-native-camera">Scatta foto</button>
                                </div>
                                <div class="doc-help">Usa questa scelta se hai il documento su carta. Puoi fare una o piu foto con il telefono; GestOre le trasformera in PDF.</div>
                            </div>
                            <div class="doc-action-group doc-choice-group">
                                <div class="doc-choice-title"><span class="doc-choice-step">3</span>Porto una fotocopia a scuola</div>
                                <div class="doc-action-buttons single">
                                    <button type="button" class="btn-paper doc-paper" <?php echo $isPaper ? 'hidden' : ''; ?>>Consegno fotocopia in segreteria</button>
                                </div>
                                <div class="doc-help">Usa questa scelta come alternativa al caricamento online. La segreteria sapra che consegnerai una copia cartacea.</div>
                            </div>
                            <div class="doc-action-group doc-final-group" hidden>
                                <div class="doc-action-title doc-final-title">Conferma caricamento online</div>
                                <div class="doc-action-buttons single">
                                    <button type="submit" class="btn-final doc-upload-button" disabled>Carica documento</button>
                                </div>
                                <div class="doc-help doc-final-help">Premi qui per salvare online i file che hai appena aggiunto.</div>
                            </div>
                        </div>
                        <div class="photo-preview"></div>
                        <div class="doc-pending"></div>
                        <div class="status-line muted doc-status" aria-live="polite"></div>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($praticaBloccata) : ?>
            <div class="card notice success">
                <strong>Conferma dati iscrizione gia inviata.</strong><br>
                Non serve fare altro da questa pagina. La segreteria didattica ha ricevuto la pratica e potra verificare i documenti caricati o indicati come consegna cartacea.
            </div>
        <?php else : ?>
            <div class="card">
                <h2>Invio domanda</h2>
                <div class="muted">Quando hai controllato i dati e caricato i documenti, oppure indicato quelli che consegnerai in segreteria didattica, puoi inviare definitivamente la domanda.</div>
                <div class="actions">
                    <button type="button" id="submitApplication" class="btn-submit-final">SALVA ED INVIA CONFERMA DATI ISCRIZIONE</button>
                </div>
                <div id="submitStatus" class="status-line" aria-live="polite"></div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>
<div id="editPhotoModal" class="camera-modal" aria-hidden="true">
    <div class="camera-panel">
        <h2>Sistema foto</h2>
        <canvas id="editPhotoCanvas" class="edit-canvas"></canvas>
        <canvas id="editOutputCanvas" hidden></canvas>
        <img id="editPhotoImage" class="edit-image" alt="Foto da ritagliare" hidden>
        <div class="doc-help">Sposta i quattro punti blu sui vertici del documento. Con due dita puoi ruotare la foto; poi premi Conferma foto.</div>
        <div class="camera-actions">
            <button type="button" id="editRotateLeft">Ruota -90</button>
            <button type="button" id="editRotateRight">Ruota +90</button>
            <label class="rotate-control" for="editRotateFine">
                Rotazione fine
                <input type="range" id="editRotateFine" min="-12" max="12" step="0.5" value="0">
                <span id="editRotateFineValue">0</span>
            </label>
            <button type="button" id="editApplyFineRotate">Applica rotazione</button>
            <button type="button" id="editSkewLeft" hidden>Raddrizza -1</button>
            <button type="button" id="editSkewRight" hidden>Raddrizza +1</button>
            <button type="button" id="editReset">Ripristina</button>
            <button type="button" id="editConfirm" class="btn-primary">Conferma foto</button>
            <button type="button" id="editCancel">Annulla</button>
        </div>
        <div id="editPhotoStatus" class="status-line muted" aria-live="polite"></div>
    </div>
</div>
<div id="busyOverlay" class="busy-overlay" aria-hidden="true">
    <div class="busy-box">
        <div class="busy-spinner"></div>
        <div id="busyOverlayText">Elaborazione in corso...</div>
    </div>
</div>
<div id="submitSuccessOverlay" class="success-overlay" aria-hidden="true">
    <div class="success-card" role="dialog" aria-modal="true" aria-labelledby="submitSuccessTitle">
        <div class="success-icon" aria-hidden="true">✓</div>
        <h2 id="submitSuccessTitle">Conferma inviata</h2>
        <p class="success-main">I dati dell'iscrizione sono stati salvati e inviati correttamente.</p>
        <p>La segreteria didattica ricevera la conferma e potra verificare i documenti caricati o indicati come consegna cartacea.</p>
        <p>Una mail di conferma viene inviata agli indirizzi indicati, se l'invio mail e configurato correttamente.</p>
        <button type="button" id="submitSuccessClose">Ho capito</button>
    </div>
</div>
<div id="submitErrorOverlay" class="success-overlay" aria-hidden="true">
    <div class="success-card warning" role="dialog" aria-modal="true" aria-labelledby="submitErrorTitle">
        <div class="success-icon" aria-hidden="true">!</div>
        <h2 id="submitErrorTitle">Manca un passaggio</h2>
        <p id="submitErrorMessage" class="success-main">Prima di inviare devi completare i dati richiesti.</p>
        <p>Controlla la pagina e correggi il punto indicato. Poi potrai inviare di nuovo la conferma.</p>
        <button type="button" id="submitErrorClose">Torno a correggere</button>
    </div>
</div>
<?php if ($pratica) : ?>
<script src="<?php echo h($__application_base_path); ?>/common/opencvjs/opencv.js"></script>
<script>
let activeCropperObjectUrl = '';
let activeCropperForm = null;
let activeCropperIndex = null;
let perspectiveImage = null;
let perspectiveSourceCanvas = document.createElement('canvas');
let perspectivePoints = [];
let perspectiveDraggingPoint = null;
let perspectiveGesture = null;
let perspectiveViewZoom = 1;
const pendingNativeImages = new WeakMap();

function readyFilesInfo(files) {
    const count = files.length;
    const imageCount = files.filter((file) => file.type.startsWith('image/')).length;
    const pdfCount = files.filter((file) => file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')).length;
    const allPdf = count > 0 && pdfCount === count;
    const allImages = count > 0 && imageCount === count;

    if (allPdf) {
        return {
            button: count > 1 ? 'Unisci PDF e carica' : 'Carica PDF',
            pending: count > 1
                ? count + ' PDF pronti. Premi Unisci PDF e carica per salvarli in un unico documento online.'
                : 'PDF pronto. Premi Carica PDF per salvarlo online.',
            help: count > 1
                ? 'GestOre unira i PDF selezionati in un unico documento finale.'
                : 'GestOre salvera online il PDF selezionato.',
            status: count > 1
                ? count + ' PDF pronti da caricare.'
                : 'PDF pronto da caricare.'
        };
    }

    if (allImages) {
        return {
            button: 'Crea PDF dalle foto e carica',
            pending: count > 1
                ? count + ' foto pronte. Premi Crea PDF dalle foto e carica per salvarle in un unico documento online.'
                : 'Foto pronta. Premi Crea PDF dalle foto e carica per salvare il documento online.',
            help: 'GestOre trasformera le foto in un PDF finale.',
            status: count > 1
                ? count + ' foto pronte da trasformare in PDF.'
                : 'Foto pronta da trasformare in PDF.'
        };
    }

    return {
        button: 'Crea PDF finale e carica',
        pending: count + ' file pronti. Premi Crea PDF finale e carica per salvare il documento online.',
        help: 'GestOre preparera un unico PDF finale con i file selezionati.',
        status: count + ' file pronti da caricare.'
    };
}

function setDocumentUiState(form, state) {
    form.dataset.docState = state;
    form.classList.toggle('is-missing', state === 'missing');
    form.classList.toggle('is-pending', state === 'pending');
    form.classList.toggle('is-complete', state === 'uploaded' || state === 'paper');

    const intro = form.querySelector('.doc-choice-intro');
    const choices = form.querySelectorAll('.doc-choice-group');
    const clearGroup = form.querySelector('.doc-clear-group');
    const existingOptions = form.querySelector('.doc-existing-options');
    const finalGroup = form.querySelector('.doc-final-group');
    const hasFinalFiles = form.querySelector('.doc-file-input').files.length > 0;

    if (intro) intro.hidden = state !== 'missing';
    choices.forEach((group) => group.hidden = state !== 'missing');
    if (clearGroup) clearGroup.hidden = !(state === 'uploaded' || state === 'paper');
    if (existingOptions) existingOptions.hidden = true;
    if (finalGroup) finalGroup.hidden = !(state === 'pending' && hasFinalFiles);
}

function refreshPhotoPreview(form) {
    const input = form.querySelector('.doc-file-input');
    const preview = form.querySelector('.photo-preview');
    const pending = form.querySelector('.doc-pending');
    const uploadButton = form.querySelector('.doc-upload-button');
    const finalGroup = form.querySelector('.doc-final-group');
    const finalHelp = form.querySelector('.doc-final-help');
    preview.innerHTML = '';

    const files = Array.from(input.files);
    const info = files.length ? readyFilesInfo(files) : null;
    uploadButton.disabled = files.length === 0;
    if (info) {
        uploadButton.textContent = info.button;
        if (finalHelp) {
            finalHelp.textContent = info.help;
        }
    }
    if (finalGroup) {
        finalGroup.hidden = files.length === 0;
    }
    if (files.length) {
        setDocumentUiState(form, 'pending');
        pending.style.display = 'block';
        pending.textContent = info.pending;
    } else {
        const emptyState = form.dataset.docState === 'pending' ? 'missing' : (form.dataset.docState || 'missing');
        setDocumentUiState(form, emptyState);
        pending.style.display = 'none';
        pending.textContent = '';
    }

    files.forEach(function (file, index) {
        const chip = document.createElement('div');
        chip.className = 'photo-chip';

        if (file.type.startsWith('image/')) {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.onload = function () { URL.revokeObjectURL(img.src); };
            chip.appendChild(img);
        }

        const label = document.createElement('span');
        label.textContent = file.name;
        chip.appendChild(label);

        if (file.type.startsWith('image/')) {
            const edit = document.createElement('button');
            edit.type = 'button';
            edit.textContent = 'Sistema';
            edit.style.background = '#475569';
            edit.addEventListener('click', function () {
                openPhotoEditor(form, file, index);
            });
            chip.appendChild(edit);
        }

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.textContent = 'Togli';
        remove.addEventListener('click', function () {
            const transfer = new DataTransfer();
            Array.from(input.files).forEach(function (currentFile, currentIndex) {
                if (currentIndex !== index) {
                    transfer.items.add(currentFile);
                }
            });
            input.files = transfer.files;
            refreshPhotoPreview(form);
        });
        chip.appendChild(remove);
        preview.appendChild(chip);
    });
}

function replaceFileInInput(form, file, index) {
    const input = form.querySelector('.doc-file-input');
    const transfer = new DataTransfer();
    Array.from(input.files).forEach(function (currentFile, currentIndex) {
        if (index === null || currentIndex !== index) {
            transfer.items.add(currentFile);
        }
        if (currentIndex === index) {
            transfer.items.add(file);
        }
    });
    if (index === null) {
        transfer.items.add(file);
    }
    input.files = transfer.files;
    refreshPhotoPreview(form);
}

function appendReadyFile(form, file) {
    const input = form.querySelector('.doc-file-input');
    const transfer = new DataTransfer();
    Array.from(input.files).forEach((currentFile) => transfer.items.add(currentFile));
    transfer.items.add(file);
    input.files = transfer.files;
    refreshPhotoPreview(form);
}

function processNextNativeImage(form) {
    const queue = pendingNativeImages.get(form) || [];
    const status = form.querySelector('.doc-status');

    if (!queue.length) {
        pendingNativeImages.delete(form);
        const files = Array.from(form.querySelector('.doc-file-input').files);
        status.textContent = files.length ? readyFilesInfo(files).status : '';
        return;
    }

    const next = queue.shift();
    pendingNativeImages.set(form, queue);
    status.textContent = 'Sistema la foto ' + next.position + ' di ' + next.total + ', poi confermala.';
    openPhotoEditor(form, next.file, null);
}

function makePhotoFile(blob, prefix) {
    return new File([blob], prefix + '_' + Date.now() + '.jpg', { type: 'image/jpeg' });
}

function showBusy(message) {
    const overlay = document.getElementById('busyOverlay');
    document.getElementById('busyOverlayText').textContent = message || 'Elaborazione in corso...';
    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');
}

function hideBusy() {
    const overlay = document.getElementById('busyOverlay');
    overlay.classList.remove('open');
    overlay.setAttribute('aria-hidden', 'true');
}

function showSubmitSuccess() {
    const overlay = document.getElementById('submitSuccessOverlay');
    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, function (char) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char];
    });
}

function showSubmitError(message) {
    const overlay = document.getElementById('submitErrorOverlay');
    const title = document.getElementById('submitErrorTitle');
    const messageBox = document.getElementById('submitErrorMessage');
    const text = message || 'Prima di inviare devi completare i dati richiesti.';

    title.textContent = 'Manca un passaggio';
    messageBox.textContent = text;

    const marker = 'questi documenti:';
    if (text.indexOf(marker) !== -1) {
        const before = text.slice(0, text.indexOf(marker) + marker.length);
        const after = text.slice(text.indexOf(marker) + marker.length).replace(/\.$/, '');
        const items = after.split(',').map((item) => item.trim()).filter(Boolean);
        title.textContent = 'Documenti mancanti';
        messageBox.innerHTML = escapeHtml(before) + '<ul class="error-list">' + items.map((item) => '<li>' + escapeHtml(item) + '</li>').join('') + '</ul>';
    }

    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');
}

function cvReady() {
    return typeof cv !== 'undefined' && cv.Mat && cv.getPerspectiveTransform;
}

function getPointEventPosition(event, canvas) {
    const touch = event.touches && event.touches.length ? event.touches[0] : event;
    const rect = canvas.getBoundingClientRect();
    return {
        x: (touch.clientX - rect.left) * (canvas.width / rect.width),
        y: (touch.clientY - rect.top) * (canvas.height / rect.height)
    };
}

function getTouchMetrics(event, canvas) {
    if (!event.touches || event.touches.length < 2) {
        return null;
    }
    const p1 = getPointEventPosition({ touches: [event.touches[0]] }, canvas);
    const p2 = getPointEventPosition({ touches: [event.touches[1]] }, canvas);
    return {
        angle: Math.atan2(p2.y - p1.y, p2.x - p1.x) * 180 / Math.PI,
        distance: Math.hypot(p2.x - p1.x, p2.y - p1.y)
    };
}

function clampNumber(value, min, max) {
    return Math.max(min, Math.min(max, value));
}

function normalizeGestureAngle(degrees) {
    let normalized = degrees;
    while (normalized > 180) {
        normalized -= 360;
    }
    while (normalized < -180) {
        normalized += 360;
    }
    return normalized;
}

function applyPerspectiveViewZoom() {
    const canvas = document.getElementById('editPhotoCanvas');
    if (!canvas) {
        return;
    }
    canvas.style.transform = '';
    canvas.style.width = perspectiveViewZoom > 1.01 ? Math.round(perspectiveViewZoom * 100) + '%' : '100%';
    canvas.style.maxHeight = perspectiveViewZoom > 1.01 ? 'none' : '58vh';
}

function initPerspectivePoints(canvas) {
    const mx = canvas.width * 0.09;
    const my = canvas.height * 0.09;
    perspectivePoints = [
        { x: mx, y: my },
        { x: canvas.width - mx, y: my },
        { x: canvas.width - mx, y: canvas.height - my },
        { x: mx, y: canvas.height - my }
    ];
}

function orderDetectedPoints(points) {
    const ordered = [null, null, null, null];
    points.forEach(function (point) {
        point.sum = point.x + point.y;
        point.diff = point.x - point.y;
    });
    ordered[0] = points.reduce((best, point) => point.sum < best.sum ? point : best, points[0]);
    ordered[2] = points.reduce((best, point) => point.sum > best.sum ? point : best, points[0]);
    ordered[1] = points.reduce((best, point) => point.diff > best.diff ? point : best, points[0]);
    ordered[3] = points.reduce((best, point) => point.diff < best.diff ? point : best, points[0]);
    return ordered.map(function (point) {
        return { x: point.x, y: point.y };
    });
}

function contourExtremePoints(contour) {
    const points = [];
    for (let i = 0; i < contour.rows; i++) {
        points.push({
            x: contour.intPtr(i, 0)[0],
            y: contour.intPtr(i, 0)[1]
        });
    }
    return points.length ? orderDetectedPoints(points) : [];
}

function approxContourPoints(contour) {
    const peri = cv.arcLength(contour, true);
    const hull = new cv.Mat();
    const epsilons = [0.01, 0.015, 0.025, 0.04, 0.06, 0.085];

    try {
        cv.convexHull(contour, hull, false, true);
        for (let epsilonIndex = 0; epsilonIndex < epsilons.length; epsilonIndex++) {
            const approx = new cv.Mat();
            cv.approxPolyDP(hull, approx, epsilons[epsilonIndex] * peri, true);

            if (approx.rows === 4) {
                const points = [];
                for (let i = 0; i < 4; i++) {
                    points.push({
                        x: approx.intPtr(i, 0)[0],
                        y: approx.intPtr(i, 0)[1]
                    });
                }
                approx.delete();
                return orderDetectedPoints(points);
            }
            approx.delete();
        }
    } finally {
        hull.delete();
    }

    return [];
}

function createDetectionMasks(gray, blurred) {
    const masks = [];
    const edges = new cv.Mat();
    const edgeDilated = new cv.Mat();
    const thresholdLow = new cv.Mat();
    const thresholdMid = new cv.Mat();
    const thresholdOtsu = new cv.Mat();
    const kernel = cv.Mat.ones(7, 7, cv.CV_8U);

    cv.Canny(blurred, edges, 35, 130);
    cv.dilate(edges, edgeDilated, kernel);
    cv.morphologyEx(edgeDilated, edgeDilated, cv.MORPH_CLOSE, kernel);
    masks.push(edgeDilated);
    edges.delete();

    cv.threshold(gray, thresholdLow, 28, 255, cv.THRESH_BINARY);
    cv.morphologyEx(thresholdLow, thresholdLow, cv.MORPH_CLOSE, kernel);
    masks.push(thresholdLow);

    cv.threshold(gray, thresholdMid, 45, 255, cv.THRESH_BINARY);
    cv.morphologyEx(thresholdMid, thresholdMid, cv.MORPH_CLOSE, kernel);
    masks.push(thresholdMid);

    cv.threshold(blurred, thresholdOtsu, 0, 255, cv.THRESH_BINARY + cv.THRESH_OTSU);
    cv.morphologyEx(thresholdOtsu, thresholdOtsu, cv.MORPH_CLOSE, kernel);
    masks.push(thresholdOtsu);

    kernel.delete();
    return masks;
}

function detectDocumentCorners(canvas) {
    if (!cvReady()) {
        return false;
    }

    let src = null;
    let gray = null;
    let blurred = null;
    let contours = null;
    let hierarchy = null;
    let best = null;
    let fallback = null;
    let masks = [];

    try {
        src = cv.imread(canvas);
        gray = new cv.Mat();
        blurred = new cv.Mat();

        cv.cvtColor(src, gray, cv.COLOR_RGBA2GRAY);
        cv.GaussianBlur(gray, blurred, new cv.Size(5, 5), 0);
        masks = createDetectionMasks(gray, blurred);

        const minArea = canvas.width * canvas.height * 0.055;
        for (let maskIndex = 0; maskIndex < masks.length; maskIndex++) {
            contours = new cv.MatVector();
            hierarchy = new cv.Mat();
            cv.findContours(masks[maskIndex], contours, hierarchy, cv.RETR_EXTERNAL, cv.CHAIN_APPROX_SIMPLE);

            for (let i = 0; i < contours.size(); i++) {
                const contour = contours.get(i);
                const area = cv.contourArea(contour);
                if (area < minArea) {
                    contour.delete();
                    continue;
                }

                const rect = cv.boundingRect(contour);
                const rectArea = rect.width * rect.height;
                if (rect.width < canvas.width * 0.18 || rect.height < canvas.height * 0.18 || rectArea < minArea) {
                    contour.delete();
                    continue;
                }

                const approxPoints = approxContourPoints(contour);
                if (approxPoints.length === 4 && area > (best ? best.area : 0)) {
                    best = { area: area, points: approxPoints };
                }

                const extremePoints = contourExtremePoints(contour);
                if (extremePoints.length === 4 && area > (fallback ? fallback.area : 0)) {
                    fallback = { area: area, points: extremePoints };
                }
                contour.delete();
            }

            contours.delete();
            contours = null;
            hierarchy.delete();
            hierarchy = null;
        }

        const points = best ? best.points : (fallback ? fallback.points : []);
        if (points.length !== 4) {
            return false;
        }
        perspectivePoints = points;
        return true;
    } catch (error) {
        return false;
    } finally {
        if (src) src.delete();
        if (gray) gray.delete();
        if (blurred) blurred.delete();
        if (contours) contours.delete();
        if (hierarchy) hierarchy.delete();
        masks.forEach(function (mask) {
            mask.delete();
        });
    }
}

function drawPerspectiveEditor() {
    const canvas = document.getElementById('editPhotoCanvas');
    const ctx = canvas.getContext('2d');

    ctx.clearRect(0, 0, canvas.width, canvas.height);
    if (!perspectiveImage) {
        return;
    }

    ctx.drawImage(perspectiveImage, 0, 0, canvas.width, canvas.height);
    ctx.save();
    ctx.lineWidth = Math.max(3, canvas.width / 220);
    ctx.strokeStyle = '#38bdf8';
    ctx.fillStyle = 'rgba(14, 165, 233, .16)';
    ctx.beginPath();
    perspectivePoints.forEach(function (point, index) {
        if (index === 0) {
            ctx.moveTo(point.x, point.y);
        } else {
            ctx.lineTo(point.x, point.y);
        }
    });
    ctx.closePath();
    ctx.fill();
    ctx.stroke();

    perspectivePoints.forEach(function (point, index) {
        ctx.beginPath();
        ctx.fillStyle = '#0284c7';
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 3;
        ctx.arc(point.x, point.y, Math.max(10, canvas.width / 70), 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 13px system-ui';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(String(index + 1), point.x, point.y);
    });
    ctx.restore();
}

function nearestPerspectivePoint(pos) {
    let nearest = null;
    let distance = Infinity;
    perspectivePoints.forEach(function (point, index) {
        const current = Math.hypot(point.x - pos.x, point.y - pos.y);
        if (current < distance) {
            nearest = index;
            distance = current;
        }
    });
    return distance < 42 ? nearest : null;
}

function setupPerspectiveCanvasEvents() {
    const canvas = document.getElementById('editPhotoCanvas');
    if (canvas.dataset.ready === '1') {
        return;
    }
    canvas.dataset.ready = '1';

    function start(event) {
        if (event.touches && event.touches.length >= 2) {
            const metrics = getTouchMetrics(event, canvas);
            if (metrics) {
                perspectiveGesture = {
                    startAngle: metrics.angle,
                    startDistance: Math.max(1, metrics.distance),
                    startZoom: perspectiveViewZoom,
                    deltaAngle: 0,
                    scale: 1,
                    mode: null
                };
                perspectiveDraggingPoint = null;
                event.preventDefault();
            }
            return;
        }

        const pos = getPointEventPosition(event, canvas);
        perspectiveDraggingPoint = nearestPerspectivePoint(pos);
        if (perspectiveDraggingPoint !== null) {
            event.preventDefault();
        }
    }

    function move(event) {
        if (perspectiveGesture && event.touches && event.touches.length >= 2) {
            const metrics = getTouchMetrics(event, canvas);
            if (metrics) {
                const deltaAngle = normalizeGestureAngle(metrics.angle - perspectiveGesture.startAngle);
                const scale = metrics.distance / perspectiveGesture.startDistance;
                const angleMovement = Math.abs(deltaAngle);
                const scaleMovement = Math.abs(scale - 1);

                perspectiveGesture.deltaAngle = deltaAngle;
                perspectiveGesture.scale = scale;

                if (!perspectiveGesture.mode) {
                    if (scaleMovement >= 0.08 && angleMovement < 10) {
                        perspectiveGesture.mode = 'zoom';
                    } else if (angleMovement >= 8 && scaleMovement < 0.12) {
                        perspectiveGesture.mode = 'rotate';
                    } else if (scaleMovement >= 0.12 || angleMovement >= 12) {
                        perspectiveGesture.mode = (scaleMovement * 90) > angleMovement ? 'zoom' : 'rotate';
                    }
                }

                if (perspectiveGesture.mode === 'zoom') {
                    perspectiveViewZoom = clampNumber(perspectiveGesture.startZoom * scale, 1, 3);
                    applyPerspectiveViewZoom();
                    document.getElementById('editPhotoStatus').textContent =
                        'Zoom ' + Math.round(perspectiveViewZoom * 100) + '%. Usa un dito per sistemare i punti.';
                } else if (perspectiveGesture.mode === 'rotate') {
                    document.getElementById('editPhotoStatus').textContent =
                        'Rotazione: ' + deltaAngle.toFixed(1) + ' gradi. Rilascia per applicare.';
                }
                event.preventDefault();
            }
            return;
        }

        if (perspectiveDraggingPoint === null) {
            return;
        }
        event.preventDefault();
        const pos = getPointEventPosition(event, canvas);
        perspectivePoints[perspectiveDraggingPoint] = {
            x: Math.max(0, Math.min(canvas.width, pos.x)),
            y: Math.max(0, Math.min(canvas.height, pos.y))
        };
        drawPerspectiveEditor();
    }

    function end() {
        if (perspectiveGesture) {
            const delta = perspectiveGesture.deltaAngle || 0;
            const mode = perspectiveGesture.mode;
            perspectiveGesture = null;
            if (mode === 'rotate' && Math.abs(delta) >= 0.8) {
                rotatePerspectiveImage(delta);
            } else if (mode === 'zoom') {
                document.getElementById('editPhotoStatus').textContent =
                    'Zoom ' + Math.round(perspectiveViewZoom * 100) + '%. Trascina i punti o allarga/stringi ancora con due dita.';
            } else {
                document.getElementById('editPhotoStatus').textContent = 'Gesto troppo piccolo: nessuna modifica applicata.';
            }
        }
        perspectiveDraggingPoint = null;
    }

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    window.addEventListener('mouseup', end);
    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove', move, { passive: false });
    canvas.addEventListener('touchend', end);
}

function loadPerspectiveImage(blobOrFile) {
    const canvas = document.getElementById('editPhotoCanvas');
    const image = new Image();
    image.onload = function () {
        const maxWidth = Math.min(900, window.innerWidth - 48);
        const maxHeight = Math.min(680, window.innerHeight - 220);
        const ratio = Math.min(maxWidth / image.naturalWidth, maxHeight / image.naturalHeight, 1);
        canvas.width = Math.max(320, Math.round(image.naturalWidth * ratio));
        canvas.height = Math.max(240, Math.round(image.naturalHeight * ratio));

        perspectiveSourceCanvas.width = image.naturalWidth;
        perspectiveSourceCanvas.height = image.naturalHeight;
        perspectiveSourceCanvas.getContext('2d').drawImage(image, 0, 0);
        perspectiveImage = image;
        initPerspectivePoints(canvas);
        canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
        const detected = detectDocumentCorners(canvas);
        setupPerspectiveCanvasEvents();
        drawPerspectiveEditor();
        document.getElementById('editPhotoStatus').textContent = detected
            ? 'Ho provato a riconoscere gli angoli: controlla i punti blu e premi Conferma foto.'
            : 'Non ho trovato automaticamente gli angoli: sposta i quattro punti blu sui vertici del documento.';
    };
    image.src = activeCropperObjectUrl;
}

function rotatePerspectiveImage(degrees) {
    if (!perspectiveImage) {
        return;
    }

    const src = perspectiveSourceCanvas;
    const rotated = document.createElement('canvas');
    if (Math.abs(degrees) === 90) {
        rotated.width = src.height;
        rotated.height = src.width;
    } else {
        rotated.width = src.width;
        rotated.height = src.height;
    }
    const ctx = rotated.getContext('2d');
    ctx.translate(rotated.width / 2, rotated.height / 2);
    ctx.rotate(degrees * Math.PI / 180);
    ctx.drawImage(src, -src.width / 2, -src.height / 2);
    rotated.toBlob(function (blob) {
        if (blob) {
            openPhotoEditor(activeCropperForm, blob, activeCropperIndex);
            document.getElementById('editRotateFine').value = '0';
            document.getElementById('editRotateFineValue').textContent = '0';
        }
    }, 'image/jpeg', 0.92);
}

function distanceBetween(a, b) {
    return Math.hypot(a.x - b.x, a.y - b.y);
}

function confirmPerspectivePhoto(callback) {
    if (!cvReady()) {
        callback(null, 'OpenCV.js non e ancora pronto. Attendere qualche secondo e riprovare.');
        return;
    }

    const displayCanvas = document.getElementById('editPhotoCanvas');
    const scaleX = perspectiveSourceCanvas.width / displayCanvas.width;
    const scaleY = perspectiveSourceCanvas.height / displayCanvas.height;
    const p = perspectivePoints.map(function (point) {
        return { x: point.x * scaleX, y: point.y * scaleY };
    });

    const targetWidth = Math.max(distanceBetween(p[0], p[1]), distanceBetween(p[3], p[2]));
    const targetHeight = Math.max(distanceBetween(p[0], p[3]), distanceBetween(p[1], p[2]));
    const maxSide = 2200;
    const ratio = Math.min(maxSide / Math.max(targetWidth, targetHeight), 1);
    const outWidth = Math.max(300, Math.round(targetWidth * ratio));
    const outHeight = Math.max(300, Math.round(targetHeight * ratio));

    let src = null;
    let dst = null;
    let srcTri = null;
    let dstTri = null;
    let matrix = null;
    try {
        src = cv.imread(perspectiveSourceCanvas);
        dst = new cv.Mat();
        srcTri = cv.matFromArray(4, 1, cv.CV_32FC2, [
            p[0].x, p[0].y,
            p[1].x, p[1].y,
            p[2].x, p[2].y,
            p[3].x, p[3].y
        ]);
        dstTri = cv.matFromArray(4, 1, cv.CV_32FC2, [
            0, 0,
            outWidth, 0,
            outWidth, outHeight,
            0, outHeight
        ]);
        matrix = cv.getPerspectiveTransform(srcTri, dstTri);
        cv.warpPerspective(src, dst, matrix, new cv.Size(outWidth, outHeight), cv.INTER_LINEAR, cv.BORDER_CONSTANT, new cv.Scalar());
        const output = document.getElementById('editOutputCanvas');
        output.width = outWidth;
        output.height = outHeight;
        cv.imshow(output, dst);
        output.toBlob(function (blob) {
            callback(blob, blob ? '' : 'Impossibile salvare la foto raddrizzata.');
        }, 'image/jpeg', 0.9);
    } catch (error) {
        callback(null, error.message || 'Errore durante il raddrizzamento della foto.');
    } finally {
        if (src) src.delete();
        if (dst) dst.delete();
        if (srcTri) srcTri.delete();
        if (dstTri) dstTri.delete();
        if (matrix) matrix.delete();
    }
}

function closePhotoEditor() {
    const modal = document.getElementById('editPhotoModal');
    const image = document.getElementById('editPhotoImage');

    activeCropperForm = null;
    activeCropperIndex = null;
    perspectiveImage = null;
    perspectivePoints = [];
    perspectiveGesture = null;
    perspectiveViewZoom = 1;
    applyPerspectiveViewZoom();
    image.removeAttribute('src');
    if (activeCropperObjectUrl) {
        URL.revokeObjectURL(activeCropperObjectUrl);
    }
    activeCropperObjectUrl = '';
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
}

function openPhotoEditor(form, blobOrFile, index) {
    const modal = document.getElementById('editPhotoModal');
    const status = document.getElementById('editPhotoStatus');

    if (typeof cv === 'undefined') {
        status.textContent = 'Editor prospettiva non disponibile: manca OpenCV.js.';
        replaceFileInInput(form, blobOrFile instanceof File ? blobOrFile : makePhotoFile(blobOrFile, 'foto_documento'), index);
        return;
    }

    if (activeCropperObjectUrl) {
        URL.revokeObjectURL(activeCropperObjectUrl);
    }

    activeCropperForm = form;
    activeCropperIndex = index;
    activeCropperObjectUrl = URL.createObjectURL(blobOrFile);
    perspectiveViewZoom = 1;
    applyPerspectiveViewZoom();
    document.getElementById('editRotateFine').value = '0';
    document.getElementById('editRotateFineValue').textContent = '0';
    status.textContent = cvReady()
        ? 'Sposta i quattro punti sui vertici del documento e premi Conferma foto.'
        : 'OpenCV.js si sta caricando. Puoi gia sistemare i punti; se Conferma non parte, attendi qualche secondo.';
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    loadPerspectiveImage(blobOrFile);
}

const iscrizioneForm = document.getElementById('iscrizioneForm');
if (iscrizioneForm) {
    iscrizioneForm.addEventListener('submit', async function (event) {
        event.preventDefault();

        const form = event.currentTarget;
        const status = document.getElementById('saveStatus');
        const buttons = form.querySelectorAll('button');
        const submitter = event.submitter;
        const goDocuments = submitter && submitter.dataset.action === 'documents';

        buttons.forEach((button) => button.disabled = true);
        status.textContent = 'Salvataggio in corso...';
        status.className = 'status-line muted';

        try {
            const response = await fetch('salva.php', {
                method: 'POST',
                body: new FormData(form)
            });
            const result = await response.json();

            if (!result.ok) {
                throw new Error(result.message || 'Salvataggio non riuscito.');
            }

            status.textContent = goDocuments
                ? 'Dati salvati. Ora possiamo procedere al caricamento documenti.'
                : result.message;
            status.className = 'status-line';
        } catch (error) {
            status.textContent = error.message;
            status.className = 'status-line';
        } finally {
            buttons.forEach((button) => button.disabled = false);
        }
    });
}

const submitApplication = document.getElementById('submitApplication');
if (submitApplication) {
    submitApplication.addEventListener('click', async function () {
        const button = this;
        const form = document.getElementById('iscrizioneForm');
        const status = document.getElementById('submitStatus');

        if (!window.confirm('Inviare definitivamente la domanda? Dopo l\'invio non sara piu possibile modificarla da questo link.')) {
            return;
        }

        button.disabled = true;
        status.textContent = 'Salvataggio e invio domanda in corso...';
        status.className = 'status-line muted';

        try {
            const response = await fetch('invia.php', {
                method: 'POST',
                body: new FormData(form)
            });
            const result = await response.json();

            if (!result.ok) {
                throw new Error(result.message || 'Invio non riuscito.');
            }

            status.textContent = result.message;
            status.className = 'status-line';
            showSubmitSuccess();
            document.querySelectorAll('button, input').forEach(function (control) {
                if (control.id !== 'submitSuccessClose') {
                    control.disabled = true;
                }
            });
        } catch (error) {
            status.textContent = error.message;
            status.className = 'status-line';
            showSubmitError(error.message);
            button.disabled = false;
        }
    });
}

document.getElementById('submitSuccessClose').addEventListener('click', function () {
    const overlay = document.getElementById('submitSuccessOverlay');
    overlay.classList.remove('open');
    overlay.setAttribute('aria-hidden', 'true');
});

document.getElementById('submitErrorClose').addEventListener('click', function () {
    const overlay = document.getElementById('submitErrorOverlay');
    overlay.classList.remove('open');
    overlay.setAttribute('aria-hidden', 'true');
});

document.querySelectorAll('.doc-form').forEach(function (form) {
    const input = form.querySelector('.doc-file-input');
    const nativeCameraInput = form.querySelector('.doc-native-camera-input');
    setDocumentUiState(form, form.dataset.docState || 'missing');

    const handleSelectedFiles = function (fileInput, mode) {
        const selected = Array.from(fileInput.files);
        const imageFiles = mode === 'camera' ? selected.filter((file) => file.type.startsWith('image/')) : [];
        const otherFiles = selected.filter((file) => !file.type.startsWith('image/'));

        fileInput.value = '';
        if (mode === 'file' && selected.some((file) => file.type.startsWith('image/'))) {
            form.querySelector('.doc-status').textContent = 'Per le immagini usare Scatta foto, cosi vengono ritagliate e raddrizzate.';
        }
        otherFiles.forEach((file) => appendReadyFile(form, file));

        if (imageFiles.length) {
            pendingNativeImages.set(form, imageFiles.map(function (file, index) {
                return {
                    file: file,
                    position: index + 1,
                    total: imageFiles.length
                };
            }));
            processNextNativeImage(form);
        } else {
            refreshPhotoPreview(form);
        }
    };

    input.addEventListener('change', function () {
        handleSelectedFiles(input, 'file');
    });

    nativeCameraInput.addEventListener('change', function () {
        handleSelectedFiles(nativeCameraInput, 'camera');
    });

    form.querySelector('.doc-file-button').addEventListener('click', function () {
        input.click();
    });

    form.querySelector('.doc-native-camera').addEventListener('click', function () {
        nativeCameraInput.click();
    });

    form.querySelectorAll('.doc-mode-options input[type="radio"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            const uploadMode = form.querySelector('.doc-upload-mode');
            if (uploadMode && radio.checked) {
                uploadMode.value = radio.value;
                refreshPhotoPreview(form);
            }
        });
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const button = form.querySelector('.doc-upload-button');
        const status = form.querySelector('.doc-status');
        const current = form.querySelector('.doc-current');
        const badge = form.querySelector('.badge');
        const input = form.querySelector('.doc-file-input');
        const deleteButton = form.querySelector('.doc-delete');
        const viewLink = form.querySelector('.doc-view');
        const uploadMode = form.querySelector('.doc-upload-mode');

        if (!input.files.length) {
            status.textContent = 'Selezionare un file.';
            return;
        }

        button.disabled = true;
        status.textContent = 'Caricamento in corso...';

        try {
            const response = await fetch('upload_documento.php', {
                method: 'POST',
                body: new FormData(form)
            });
            const result = await response.json();

            if (!result.ok) {
                throw new Error(result.message || 'Caricamento non riuscito.');
            }

            status.textContent = result.message;
            if (result.document && result.document.original_name) {
                current.textContent = result.document.original_name;
            }
            badge.textContent = 'Caricato';
            badge.classList.add('badge-ok');
            badge.classList.remove('badge-paper');
            deleteButton.hidden = false;
            deleteButton.textContent = 'Cancella PDF caricato';
            viewLink.hidden = false;
            const paperButton = form.querySelector('.doc-paper');
            if (paperButton) {
                paperButton.hidden = false;
            }
            if (uploadMode) {
                uploadMode.value = 'replace';
            }
            input.value = '';
            setDocumentUiState(form, 'uploaded');
            refreshPhotoPreview(form);
        } catch (error) {
            status.textContent = error.message;
        } finally {
            button.disabled = false;
        }
    });

    const deleteButton = form.querySelector('.doc-delete');
    const paperButton = form.querySelector('.doc-paper');
    if (paperButton) {
        paperButton.addEventListener('click', async function () {
            const status = form.querySelector('.doc-status');
            const current = form.querySelector('.doc-current');
            const badge = form.querySelector('.badge');
            const viewLink = form.querySelector('.doc-view');
            const input = form.querySelector('.doc-file-input');
            const uploadMode = form.querySelector('.doc-upload-mode');
            const existingOptions = form.querySelector('.doc-existing-options');
            const clearGroup = form.querySelector('.doc-clear-group');

            if (!window.confirm('Confermi che questo documento verra consegnato come fotocopia in segreteria didattica?')) {
                return;
            }

            paperButton.disabled = true;
            status.textContent = 'Registrazione consegna cartacea...';

            try {
                const response = await fetch('consegna_cartacea_documento.php', {
                    method: 'POST',
                    body: new FormData(form)
                });
                const result = await response.json();

                if (!result.ok) {
                    throw new Error(result.message || 'Registrazione non riuscita.');
                }

                status.textContent = result.message;
                current.textContent = result.document && result.document.original_name
                    ? result.document.original_name
                    : 'Consegna cartacea in segreteria didattica';
                badge.textContent = 'Cartaceo';
                badge.classList.remove('badge-ok');
                badge.classList.add('badge-paper');
                viewLink.hidden = true;
                paperButton.hidden = true;
                deleteButton.hidden = false;
                deleteButton.textContent = 'Annulla scelta cartacea';
                if (clearGroup) {
                    clearGroup.hidden = false;
                }
                if (existingOptions) {
                    existingOptions.hidden = true;
                }
                if (uploadMode) {
                    uploadMode.value = 'replace';
                }
                input.value = '';
                setDocumentUiState(form, 'paper');
                refreshPhotoPreview(form);
            } catch (error) {
                status.textContent = error.message;
            } finally {
                paperButton.disabled = false;
            }
        });
    }

    deleteButton.addEventListener('click', async function () {
        const status = form.querySelector('.doc-status');
        const current = form.querySelector('.doc-current');
        const badge = form.querySelector('.badge');
        const viewLink = form.querySelector('.doc-view');
        const uploadMode = form.querySelector('.doc-upload-mode');
        const existingOptions = form.querySelector('.doc-existing-options');
        const clearGroup = form.querySelector('.doc-clear-group');

        const isPaperChoice = badge.textContent.trim().toLowerCase() === 'cartaceo';
        const confirmMessage = isPaperChoice
            ? 'Annullare la scelta di consegna cartacea in segreteria?'
            : 'Cancellare il PDF caricato?';

        if (!window.confirm(confirmMessage)) {
            return;
        }

        deleteButton.disabled = true;
        status.textContent = isPaperChoice ? 'Annullamento scelta cartacea...' : 'Cancellazione PDF in corso...';

        try {
            const response = await fetch('cancella_documento.php', {
                method: 'POST',
                body: new FormData(form)
            });
            const result = await response.json();

            if (!result.ok) {
                throw new Error(result.message || 'Cancellazione non riuscita.');
            }

            status.textContent = result.message;
            current.textContent = 'Non ancora caricato';
            badge.textContent = 'Mancante';
            badge.classList.remove('badge-ok');
            badge.classList.remove('badge-paper');
            deleteButton.hidden = true;
            deleteButton.textContent = 'Cancella PDF caricato';
            if (clearGroup) {
                clearGroup.hidden = true;
            }
            viewLink.hidden = true;
            if (paperButton) {
                paperButton.hidden = false;
            }
            if (existingOptions) {
                existingOptions.hidden = true;
            }
            if (uploadMode) {
                uploadMode.value = 'replace';
            }
            input.value = '';
            setDocumentUiState(form, 'missing');
            refreshPhotoPreview(form);
        } catch (error) {
            status.textContent = error.message;
        } finally {
            deleteButton.disabled = false;
        }
    });
});

document.getElementById('editCancel').addEventListener('click', function () {
    const form = activeCropperForm;
    closePhotoEditor();
    if (form) {
        processNextNativeImage(form);
    }
});
document.getElementById('editRotateLeft').addEventListener('click', function () {
    rotatePerspectiveImage(-90);
});
document.getElementById('editRotateRight').addEventListener('click', function () {
    rotatePerspectiveImage(90);
});
document.getElementById('editRotateFine').addEventListener('input', function () {
    document.getElementById('editRotateFineValue').textContent = this.value;
});
document.getElementById('editApplyFineRotate').addEventListener('click', function () {
    const degrees = parseFloat(document.getElementById('editRotateFine').value || '0');
    if (Math.abs(degrees) >= 0.1) {
        rotatePerspectiveImage(degrees);
    }
});
document.getElementById('editSkewLeft').addEventListener('click', function () {
    rotatePerspectiveImage(-1);
});
document.getElementById('editSkewRight').addEventListener('click', function () {
    rotatePerspectiveImage(1);
});
document.getElementById('editReset').addEventListener('click', function () {
    const canvas = document.getElementById('editPhotoCanvas');
    perspectiveViewZoom = 1;
    applyPerspectiveViewZoom();
    initPerspectivePoints(canvas);
    drawPerspectiveEditor();
});
document.getElementById('editConfirm').addEventListener('click', function () {
    const status = document.getElementById('editPhotoStatus');

    if (!activeCropperForm || !perspectiveImage) {
        status.textContent = 'Nessuna foto da confermare.';
        return;
    }

    status.textContent = 'Raddrizzamento prospettiva in corso...';
    showBusy('Raddrizzamento della foto in corso...');
    window.setTimeout(function () {
        confirmPerspectivePhoto(function (blob, error) {
            hideBusy();
            if (!blob) {
                status.textContent = error || 'Impossibile salvare la foto sistemata.';
                return;
            }

            const file = makePhotoFile(blob, 'foto_raddrizzata');
            const form = activeCropperForm;
            const index = activeCropperIndex;
            replaceFileInInput(form, file, index);
            closePhotoEditor();

            const files = Array.from(form.querySelector('.doc-file-input').files);
            form.querySelector('.doc-status').textContent = files.length ? readyFilesInfo(files).status : '';
            processNextNativeImage(form);
        });
    }, 30);
});
</script>
<?php endif; ?>
</body>
</html>
