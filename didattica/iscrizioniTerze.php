<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

iscrizioniPrimeEnsureSchema();

$stats = dbGetFirst("
    SELECT
        COUNT(*) AS totale,
        SUM(studente_interno = 1) AS interni,
        SUM(studente_interno = 0) AS esterni,
        SUM(stato = 'bozza' AND studente_interno = 0) AS bozze,
        SUM(stato = 'inviata' AND studente_interno = 0) AS inviate,
        SUM(stato = 'verifica_iniziale_ok' AND studente_interno = 0) AS verifica_iniziale_ok,
        SUM(stato = 'verificata' AND studente_interno = 0) AS verificate,
        SUM((email_genitore_1 IS NOT NULL OR email_genitore_2 IS NOT NULL) AND studente_interno = 0) AS esterni_con_email
    FROM iscrizioni_prime_pratiche
    WHERE tipo_iscrizione = 'terze'
");
$draftSubject = iscrizioniPrimeDraftSubject('terze');
$indirizziGestore = iscrizioniPrimeGestoreAddressOptions();

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <title>Iscrizioni terze</title>
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    ?>
    <style>
        .iscrizioni-mail-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(15, 23, 42, 0.62);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
        }
        .iscrizioni-mail-card {
            width: min(620px, 100%);
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 18px 60px rgba(15, 23, 42, 0.35);
            padding: 28px;
            text-align: center;
            border-top: 8px solid #0ea5e9;
        }
        .iscrizioni-mail-icon {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            background: #0ea5e9;
            font-size: 30px;
            margin-bottom: 12px;
        }
        .iscrizioni-mail-title {
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 6px;
        }
        .iscrizioni-mail-text {
            color: #475569;
            font-size: 16px;
            line-height: 1.45;
            margin-bottom: 18px;
        }
        .iscrizioni-mail-percent {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 8px;
        }
        .iscrizioni-mail-progress {
            height: 18px;
            background: #e2e8f0;
            border-radius: 999px;
            overflow: hidden;
            margin-bottom: 16px;
        }
        .iscrizioni-mail-progress-bar {
            height: 100%;
            width: 0;
            background: linear-gradient(90deg, #0ea5e9, #22c55e);
            transition: width .35s ease;
        }
        .iscrizioni-mail-progress-bar.is-running {
            width: 75%;
            background-size: 42px 42px;
            background-image: linear-gradient(45deg, rgba(255,255,255,.25) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.25) 50%, rgba(255,255,255,.25) 75%, transparent 75%, transparent);
            animation: iscrizioniMailStripe 1s linear infinite;
        }
        @keyframes iscrizioniMailStripe {
            from { background-position: 42px 0; }
            to { background-position: 0 0; }
        }
        .mail-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 12px;
            margin-bottom: 3px;
        }
        .mail-badge-real { background: #dcfce7; color: #166534; }
        .mail-badge-test { background: #dbeafe; color: #1d4ed8; }
        .mail-badge-none { background: #fee2e2; color: #991b1b; }
        .mail-badge-skip { background: #e5e7eb; color: #374151; }
        .mail-badge-bounce { background: #fecaca; color: #7f1d1d; }
        .stud-attr-badge {
            display: inline-block;
            padding: 3px 7px;
            border-radius: 999px;
            background: #fef3c7;
            color: #7c2d12;
            font-weight: 800;
            font-size: 12px;
            margin: 0 3px 3px 0;
        }
        .stud-attr-source {
            display: block;
            color: #64748b;
            font-size: 11px;
            margin-top: 2px;
        }
        .iscrizioni-dashboard {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 12px;
            align-items: start;
        }
        .iscrizioni-dashboard-title {
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 2px;
        }
        .iscrizioni-dashboard-subtitle {
            color: #64748b;
            font-weight: 650;
        }
        .iscrizioni-dashboard-main-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: flex-end;
        }
        .iscrizioni-stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(145px, 1fr));
            gap: 8px;
            margin: 12px 0;
        }
        .iscrizioni-stat-card {
            border: 1px solid #dbe4ef;
            border-left: 5px solid #38bdf8;
            border-radius: 8px;
            background: #fff;
            padding: 8px 10px;
            min-height: 62px;
        }
        .iscrizioni-stat-card .value {
            display: block;
            font-size: 22px;
            line-height: 1;
            font-weight: 850;
            color: #0f172a;
        }
        .iscrizioni-stat-card .label {
            display: block;
            padding: 0;
            margin-top: 6px;
            background: transparent;
            color: #475569;
            font-size: 12px;
            text-align: left;
            white-space: normal;
        }
        .iscrizioni-stat-card.primary { border-left-color: #2563eb; }
        .iscrizioni-stat-card.ok { border-left-color: #16a34a; }
        .iscrizioni-stat-card.warn { border-left-color: #f59e0b; }
        .iscrizioni-stat-card.mail { border-left-color: #0ea5e9; }
        .iscrizioni-stat-card.internal { border-left-color: #64748b; }
        .iscrizioni-action-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(180px, 1fr));
            gap: 8px;
            margin: 8px 0 12px;
        }
        .iscrizioni-action-group {
            border: 1px solid #dbe4ef;
            border-radius: 8px;
            background: #f8fafc;
            padding: 8px;
        }
        .iscrizioni-action-title {
            color: #475569;
            font-weight: 800;
            font-size: 12px;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        .iscrizioni-action-group .btn {
            margin: 0 4px 5px 0;
        }
        .iscrizioni-import-toggle-row {
            display: flex;
            justify-content: flex-end;
            padding-top: 2px;
        }
        .iscrizioni-note-inline {
            border: 1px solid #bae6fd;
            border-left: 5px solid #0ea5e9;
            background: #f0f9ff;
            border-radius: 8px;
            padding: 9px 11px;
            color: #075985;
            font-weight: 650;
            margin: 8px 0 0;
        }
        .iscrizioni-terze-filter {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }
        .iscrizioni-terze-filter input {
            width: min(560px, 100%);
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 9px 11px;
        }
        .iscrizioni-terze-filter-count { color: #64748b; }
        .custom-mail-modal {
            position: fixed;
            inset: 0;
            z-index: 9998;
            background: rgba(15, 23, 42, 0.62);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
        }
        .custom-mail-modal.open { display: flex; }
        .custom-mail-card {
            width: min(760px, 100%);
            max-height: calc(100vh - 36px);
            overflow: auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 22px 56px rgba(0,0,0,.28);
        }
        .custom-mail-head {
            padding: 16px 18px;
            background: #1d4ed8;
            color: #fff;
            font-size: 20px;
            font-weight: 800;
        }
        .custom-mail-body { padding: 18px; }
        .custom-mail-field { margin-bottom: 12px; }
        .custom-mail-field label { display: block; font-weight: 700; margin-bottom: 5px; }
        .custom-mail-field input[type="text"],
        .custom-mail-field textarea {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 9px 10px;
            background: #fff;
        }
        .custom-mail-field textarea { min-height: 150px; resize: vertical; }
        .custom-mail-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding: 12px 18px;
            border-top: 1px solid #e5e7eb;
            background: #f8fafc;
        }
        .custom-mail-tools { margin-bottom: 6px; display: flex; flex-wrap: wrap; gap: 5px; }
        @media (max-width: 760px) {
            .iscrizioni-dashboard { grid-template-columns: 1fr; }
            .iscrizioni-dashboard-main-actions { justify-content: flex-start; }
            .iscrizioni-action-grid { grid-template-columns: 1fr; }
        }
        @media (min-width: 761px) and (max-width: 1100px) {
            .iscrizioni-action-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
<?php require_once '../common/header-didattica.php'; ?>

<div id="iscrizioni_mail_overlay" class="iscrizioni-mail-overlay" role="status" aria-live="polite">
    <div class="iscrizioni-mail-card">
        <div id="iscrizioni_mail_icon" class="iscrizioni-mail-icon">
            <span class="glyphicon glyphicon-send"></span>
        </div>
        <div id="iscrizioni_mail_title" class="iscrizioni-mail-title">Invio mail in corso</div>
        <div id="iscrizioni_mail_text" class="iscrizioni-mail-text">GestOre sta preparando e inviando il lotto. Tieni aperta questa pagina.</div>
        <div id="iscrizioni_mail_percent" class="iscrizioni-mail-percent">0%</div>
        <div class="iscrizioni-mail-progress">
            <div id="iscrizioni_mail_progress_bar" class="iscrizioni-mail-progress-bar is-running"></div>
        </div>
        <div id="iscrizioni_mail_details" class="text-muted"></div>
        <div id="iscrizioni_mail_confirm_actions" style="display:none;margin-top:16px;">
            <button type="button" id="iscrizioni_mail_cancel" class="btn btn-default">Annulla</button>
            <button type="button" id="iscrizioni_mail_confirm" class="btn btn-primary">Conferma invio</button>
        </div>
        <button type="button" id="iscrizioni_mail_close" class="btn btn-primary" style="display:none;margin-top:16px;" onclick="iscrizioniTerzeHideMailOverlay()">Chiudi</button>
    </div>
</div>

<div id="custom_mail_modal" class="custom-mail-modal" aria-hidden="true">
    <div class="custom-mail-card" role="dialog" aria-modal="true" aria-labelledby="custom_mail_title">
        <div id="custom_mail_title" class="custom-mail-head">Scrivi ai genitori</div>
        <div class="custom-mail-body">
            <input type="hidden" id="custom_mail_id">
            <p id="custom_mail_student" class="text-muted"></p>
            <div class="custom-mail-field">
                <label>Destinatari</label>
                <div id="custom_mail_recipients" class="well well-sm" style="margin-bottom:0;"></div>
            </div>
            <div class="custom-mail-field">
                <label for="custom_mail_subject">Oggetto</label>
                <input type="text" id="custom_mail_subject" value="Comunicazione pratica iscrizione">
            </div>
            <div class="custom-mail-field">
                <label for="custom_mail_message">Messaggio</label>
                <div class="custom-mail-tools">
                    <button type="button" class="btn btn-default btn-xs" onclick="iscrizioniTerzeFormatTextarea('custom_mail_message', 'bold')"><strong>B</strong></button>
                    <button type="button" class="btn btn-default btn-xs" onclick="iscrizioniTerzeFormatTextarea('custom_mail_message', 'ul')">Elenco puntato</button>
                    <button type="button" class="btn btn-default btn-xs" onclick="iscrizioniTerzeFormatTextarea('custom_mail_message', 'ol')">Elenco numerato</button>
                </div>
                <textarea id="custom_mail_message" placeholder="Scrivi qui il testo da inviare ai genitori."></textarea>
                <div class="help-block">Puoi usare **testo** per il grassetto, "- " per elenco puntato e "1. " per elenco numerato.</div>
            </div>
            <div class="custom-mail-field">
                <label for="custom_mail_signature">Firma</label>
                <textarea id="custom_mail_signature" style="min-height:90px;">Segreteria didattica
ITT Buonarroti - Trento</textarea>
            </div>
            <div id="custom_mail_error" class="text-danger" style="margin-top:8px;" hidden></div>
        </div>
        <div class="custom-mail-actions">
            <button type="button" class="btn btn-default" onclick="iscrizioniTerzeCloseCustomMail()">Annulla</button>
            <button type="button" class="btn btn-primary" id="custom_mail_send_button" onclick="iscrizioniTerzeSendCustomMail()">Invia mail</button>
        </div>
    </div>
</div>

<div id="parents_modal" class="custom-mail-modal" aria-hidden="true">
    <div class="custom-mail-card" role="dialog" aria-modal="true" aria-labelledby="parents_modal_title">
        <div id="parents_modal_title" class="custom-mail-head" style="background:#0f766e;">Dati genitori</div>
        <form id="parents_form">
            <div class="custom-mail-body">
                <input type="hidden" name="id" id="parents_id">
                <p id="parents_student" class="text-muted"></p>
                <div class="row">
                    <div class="col-sm-6">
                        <h4>Responsabile 1</h4>
                        <div class="custom-mail-field"><label>Tipo rapporto</label><input type="text" name="responsabile_1_tipo" id="parents_r1_tipo"></div>
                        <div class="custom-mail-field"><label>Cognome</label><input type="text" name="responsabile_1_cognome" id="parents_r1_cognome"></div>
                        <div class="custom-mail-field"><label>Nome</label><input type="text" name="responsabile_1_nome" id="parents_r1_nome"></div>
                        <div class="custom-mail-field"><label>Codice fiscale</label><input type="text" name="responsabile_1_codice_fiscale" id="parents_r1_cf" maxlength="16"></div>
                        <div class="custom-mail-field"><label>Email</label><input type="email" name="email_genitore_1" id="parents_r1_email"></div>
                        <div class="custom-mail-field"><label>Telefono</label><input type="text" name="telefono_genitore_1" id="parents_r1_tel"></div>
                    </div>
                    <div class="col-sm-6">
                        <h4>Responsabile 2</h4>
                        <div class="custom-mail-field"><label>Tipo rapporto</label><input type="text" name="responsabile_2_tipo" id="parents_r2_tipo"></div>
                        <div class="custom-mail-field"><label>Cognome</label><input type="text" name="responsabile_2_cognome" id="parents_r2_cognome"></div>
                        <div class="custom-mail-field"><label>Nome</label><input type="text" name="responsabile_2_nome" id="parents_r2_nome"></div>
                        <div class="custom-mail-field"><label>Codice fiscale</label><input type="text" name="responsabile_2_codice_fiscale" id="parents_r2_cf" maxlength="16"></div>
                        <div class="custom-mail-field"><label>Email</label><input type="email" name="email_genitore_2" id="parents_r2_email"></div>
                        <div class="custom-mail-field"><label>Telefono</label><input type="text" name="telefono_genitore_2" id="parents_r2_tel"></div>
                    </div>
                </div>
                <div id="parents_error" class="text-danger" style="margin-top:8px;" hidden></div>
            </div>
            <div class="custom-mail-actions">
                <button type="button" class="btn btn-default" onclick="iscrizioniTerzeCloseParentsModal()">Annulla</button>
                <button type="submit" class="btn btn-primary" id="parents_save_button">Salva dati genitori</button>
            </div>
        </form>
    </div>
</div>

<div class="container-fluid">
    <div class="panel panel-lightblue4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-folder-open"></span>&ensp;Iscrizioni future classi terze
        </div>
        <div class="panel-body">
            <div class="iscrizioni-dashboard">
                <div>
                    <div class="iscrizioni-dashboard-title">Pratiche future terze</div>
                    <div class="iscrizioni-dashboard-subtitle">Esterni, interni gia presenti e raccolta documenti</div>
                </div>
                <div class="iscrizioni-dashboard-main-actions">
                    <button type="button" class="btn btn-default" onclick="iscrizioniTerzeLoadTable()">
                        <span class="glyphicon glyphicon-refresh"></span> Aggiorna elenco
                    </button>
                    <a class="btn btn-primary" href="iscrizioniPrimeDomande.php?tipo_iscrizione=terze">
                        <span class="glyphicon glyphicon-inbox"></span> Domande inviate
                    </a>
                    <a class="btn btn-default" href="iscrizioniContattiVariazioni.php?tipo_iscrizione=terze">
                        <span class="glyphicon glyphicon-transfer"></span> Variazioni contatti
                    </a>
                </div>
            </div>

            <div class="iscrizioni-stat-grid">
                <div class="iscrizioni-stat-card primary"><span class="value" id="stat_totale"><?php echo intval($stats['totale'] ?? 0); ?></span><span class="label">Totale</span></div>
                <div class="iscrizioni-stat-card internal"><span class="value" id="stat_interni"><?php echo intval($stats['interni'] ?? 0); ?></span><span class="label">Interni</span></div>
                <div class="iscrizioni-stat-card ok"><span class="value" id="stat_esterni"><?php echo intval($stats['esterni'] ?? 0); ?></span><span class="label">Esterni</span></div>
                <div class="iscrizioni-stat-card ok"><span class="value" id="stat_domande_inviate"><?php echo intval($stats['inviate'] ?? 0); ?></span><span class="label">Domande inviate</span></div>
                <div class="iscrizioni-stat-card mail"><span class="value" id="stat_mail_reali">0</span><span class="label">Mail reali</span></div>
                <div class="iscrizioni-stat-card"><span class="value" id="stat_mail_test">0</span><span class="label">Mail test</span></div>
            </div>

            <div class="iscrizioni-note-inline">
                Gli studenti gia presenti in GestOre vengono segnati come interni e non ricevono il link. La raccolta dati e documenti viene inviata solo agli studenti esterni.
            </div>

            <div class="iscrizioni-action-grid">
                <div class="iscrizioni-action-group">
                    <div class="iscrizioni-action-title">Link esterni</div>
                    <a class="btn btn-success" href="iscrizioniPrimeLinkExport.php?tipo_iscrizione=terze" onclick="return confirm('Generare un nuovo token per tutte le pratiche esterne non chiuse e scaricare il CSV dei link? I link esportati in precedenza verranno sostituiti.');">
                        <span class="glyphicon glyphicon-envelope"></span> Esporta link
                    </a>
                </div>
                <div class="iscrizioni-action-group">
                    <div class="iscrizioni-action-title">Invio mail</div>
                    <button type="button" class="btn btn-info" onclick="iscrizioniTerzeSendMail(1)">
                        <span class="glyphicon glyphicon-eye-open"></span> Simula
                    </button>
                    <button type="button" class="btn btn-info" onclick="iscrizioniTerzeSendTestMail()">
                        <span class="glyphicon glyphicon-envelope"></span> Test
                    </button>
                    <button type="button" class="btn btn-warning" onclick="iscrizioniTerzeSendMail(0)">
                        <span class="glyphicon glyphicon-send"></span> Lotto
                    </button>
                </div>
                <div class="iscrizioni-action-group">
                    <div class="iscrizioni-action-title">Controlli link</div>
                    <button type="button" class="btn btn-danger" onclick="iscrizioniTerzeCorrectSentLinks(1)">
                        <span class="glyphicon glyphicon-search"></span> Simula
                    </button>
                    <button type="button" class="btn btn-danger" onclick="iscrizioniTerzeCorrectSentLinks(0)">
                        <span class="glyphicon glyphicon-link"></span> Correggi
                    </button>
                </div>
                <div class="iscrizioni-action-group">
                    <div class="iscrizioni-action-title">Bounce</div>
                    <button type="button" class="btn btn-danger" onclick="iscrizioniTerzeCheckBounce()">
                        <span class="glyphicon glyphicon-warning-sign"></span> Bounce
                    </button>
                    <a class="btn btn-default" href="iscrizioniPrimeMailBounceExport.php?tipo_iscrizione=terze&days=30">
                        <span class="glyphicon glyphicon-download-alt"></span> Report
                    </a>
                </div>
            </div>
            <div id="iscrizioni_terze_result" class="alert" style="display:none;margin-top:12px;"></div>
            <div class="iscrizioni-import-toggle-row">
                <button type="button" class="btn btn-default" onclick="iscrizioniTerzeToggleInitialTools()">
                    <span id="iscrizioni_terze_initial_tools_icon" class="glyphicon glyphicon-chevron-down"></span>
                    <span id="iscrizioni_terze_initial_tools_label">Mostra bozza Gmail e import CSV</span>
                </button>
            </div>
            <div id="iscrizioni_terze_initial_tools" style="display:none;margin-top:14px;">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Bozza Gmail per invio mail esterni</strong></div>
                <div class="panel-body">
                    <form id="iscrizioni_terze_draft_subject_form">
                        <input type="hidden" name="tipo_iscrizione" value="terze">
                        <div class="form-group">
                            <label>Oggetto della mail BOZZA in Gmail</label>
                            <input type="text" name="draft_subject" class="form-control" value="<?php echo htmlspecialchars($draftSubject, ENT_QUOTES, 'UTF-8'); ?>" required>
                            <span class="help-block">
                                GestOre cerca questa bozza nelle caselle iscrizioni1, iscrizioni2, ecc. Nella bozza usa
                                <strong>{{LINK_PERSONALE}}</strong>, <strong>{{NOME_STUDENTE}}</strong> e
                                <strong>{blocco traduzioni}</strong>. Gli allegati presenti nella bozza vengono copiati nella mail inviata.
                            </span>
                        </div>
                        <button type="submit" class="btn btn-default">
                            <span class="glyphicon glyphicon-floppy-disk"></span> Salva oggetto bozza
                        </button>
                    </form>
                </div>
            </div>
            <hr>
            <button type="button" class="btn btn-default" onclick="iscrizioniTerzeToggleManuale()">
                <span class="glyphicon glyphicon-plus"></span> Aggiungi pratica esterna manuale
            </button>
            <form id="iscrizioni_terze_manual_form" class="form-horizontal" style="display:none;margin-top:14px;">
                <div class="alert alert-warning">
                    Usa questa funzione per studenti esterni che devono sostenere esami integrativi e non sono ancora nel CSV ufficiale.
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Anno scolastico</label>
                    <div class="col-sm-2"><input type="text" name="anno_scolastico" value="2026/27" class="form-control"></div>
                    <label class="col-sm-2 control-label">Corso richiesto</label>
                    <div class="col-sm-6"><input type="text" name="corso_studi" class="form-control" placeholder="es. INFORMATICA"></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Indirizzo GestOre</label>
                    <div class="col-sm-10">
                        <select name="id_indirizzo_gestore" class="form-control">
                            <option value="">Da ricavare dal corso importato</option>
                            <?php foreach ($indirizziGestore as $indirizzoRow): ?>
                                <option value="<?php echo intval($indirizzoRow['id']); ?>"><?php echo htmlspecialchars((string)$indirizzoRow['nome'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="help-block">Usato per formazione classi e filtri indirizzo. Il testo importato resta nel campo corso richiesto.</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Cognome</label>
                    <div class="col-sm-4"><input type="text" name="cognome" class="form-control" required></div>
                    <label class="col-sm-2 control-label">Nome</label>
                    <div class="col-sm-4"><input type="text" name="nome" class="form-control" required></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Codice fiscale</label>
                    <div class="col-sm-4"><input type="text" name="codice_fiscale" maxlength="16" class="form-control" required></div>
                    <label class="col-sm-2 control-label">Data nascita</label>
                    <div class="col-sm-4"><input type="text" name="data_nascita" class="form-control" placeholder="gg/mm/aaaa"></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Email studente</label>
                    <div class="col-sm-4"><input type="email" name="email_studente" class="form-control"></div>
                    <label class="col-sm-2 control-label">Telefono studente</label>
                    <div class="col-sm-4"><input type="text" name="telefono_studente" class="form-control"></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Resp. 1 cognome</label>
                    <div class="col-sm-3"><input type="text" name="responsabile_1_cognome" class="form-control"></div>
                    <label class="col-sm-2 control-label">Resp. 1 nome</label>
                    <div class="col-sm-2"><input type="text" name="responsabile_1_nome" class="form-control"></div>
                    <label class="col-sm-1 control-label">Email</label>
                    <div class="col-sm-2"><input type="email" name="email_genitore_1" class="form-control"></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label">Resp. 2 cognome</label>
                    <div class="col-sm-3"><input type="text" name="responsabile_2_cognome" class="form-control"></div>
                    <label class="col-sm-2 control-label">Resp. 2 nome</label>
                    <div class="col-sm-2"><input type="text" name="responsabile_2_nome" class="form-control"></div>
                    <label class="col-sm-1 control-label">Email</label>
                    <div class="col-sm-2"><input type="email" name="email_genitore_2" class="form-control"></div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <button type="submit" class="btn btn-primary">
                            <span class="glyphicon glyphicon-ok"></span> Salva pratica manuale
                        </button>
                    </div>
                </div>
            </form>
            <hr>
            <form id="iscrizioni_terze_import_form" class="form-horizontal" enctype="multipart/form-data">
                <input type="hidden" name="tipo_iscrizione" value="terze">
                <div class="form-group">
                    <label class="col-sm-3 control-label">CSV iscrizioni TERZE</label>
                    <div class="col-sm-9">
                        <input type="file" name="prime_csv" accept=".csv,text/csv" class="form-control">
                        <span class="help-block">Obbligatorio solo per il primo import. Negli import successivi puoi caricare anche solo anagrafica, DSA o certificazioni scuola.</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">CSV SAA DSA TERZE</label>
                    <div class="col-sm-9">
                        <input type="file" name="dsa_csv" accept=".csv,text/csv" class="form-control">
                        <span class="help-block">Opzionale: puoi caricarlo anche in un secondo momento per aggiornare DSA/104.</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">CSV anagrafica responsabili</label>
                    <div class="col-sm-9">
                        <input type="file" name="anagrafica_csv" accept=".csv,text/csv" class="form-control">
                        <span class="help-block">Puoi importarlo anche in un secondo momento: aggiorna email, telefoni e responsabili degli esterni.</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">CSV dati aggiuntivi TERZE</label>
                    <div class="col-sm-9">
                        <input type="file" name="dati_aggiuntivi_csv" accept=".csv,text/csv" class="form-control">
                        <span class="help-block">Opzionale: per Costruzioni Ambiente e Territorio importa la scelta curvatura Design/Normale.</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">Excel DSA/Fascia C/104 scuola</label>
                    <div class="col-sm-9">
                        <input type="file" name="dsa_school_xls" accept=".xls,.xlsx,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="form-control">
                        <span class="help-block">Opzionale: aggiorna gli attributi riservati DSA, 104 e Fascia C di tutti gli studenti agganciati per codice fiscale.</span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-3 col-sm-9">
                        <button type="submit" class="btn btn-primary">
                            <span class="glyphicon glyphicon-upload"></span> Importa pratiche terze
                        </button>
                    </div>
                </div>
            </form>
            </div>
        </div>
    </div>

    <div class="panel panel-lima4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-list"></span>&ensp;Pratiche terze importate
        </div>
        <div class="panel-body">
            <div class="iscrizioni-terze-filter">
                <label for="iscrizioni_terze_filter" style="margin:0;">Filtra elenco</label>
                <input type="search" id="iscrizioni_terze_filter" placeholder="Cerca nome, codice fiscale, corso, stato, email...">
                <button type="button" class="btn btn-default btn-sm" onclick="iscrizioniTerzeClearFilter()">Pulisci</button>
                <button type="button" class="btn btn-success btn-sm" onclick="iscrizioniTerzeExportFilteredCsv()">
                    <span class="glyphicon glyphicon-download-alt"></span> Esporta tabella filtrata
                </button>
                <span id="iscrizioni_terze_filter_count" class="iscrizioni-terze-filter-count"></span>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-condensed" id="iscrizioni_terze_table">
                    <thead>
                        <tr>
                            <th>Studente</th>
                            <th>Codice fiscale</th>
                            <th>Corso</th>
                            <th>Indirizzo GestOre</th>
                            <th>Attributi</th>
                            <th>Tipo</th>
                            <th>Stato</th>
                            <th>Email responsabili</th>
                            <th>Mail avviso</th>
                            <th>Token</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="11" class="text-muted">Caricamento...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
let iscrizioniTerzeMailProgressTimer = null;
let iscrizioniTerzeMailProgressValue = 0;
let iscrizioniTerzeRows = [];
let iscrizioniTerzeVisibleRows = [];
const iscrizioniTerzeIndirizziGestore = <?php echo json_encode(array_map(static function ($row) {
    return ['id' => intval($row['id'] ?? 0), 'nome' => (string)($row['nome'] ?? '')];
}, $indirizziGestore), JSON_UNESCAPED_UNICODE); ?>;

function iscrizioniTerzeEscape(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (char) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
    });
}

function iscrizioniTerzeImportList(title, rows) {
    rows = Array.isArray(rows) ? rows : [];
    if (!rows.length) return '';
    return '<div style="margin-top:8px;"><strong>' + iscrizioniTerzeEscape(title) + ':</strong><ul style="margin:4px 0 0 18px;">' +
        rows.slice(0, 40).map(function (row) {
            const parts = [];
            if (row.id) parts.push('#' + row.id);
            if (row.studente) parts.push(row.studente);
            if (row.codice_fiscale) parts.push(row.codice_fiscale);
            if (row.corso_studi) parts.push(row.corso_studi);
            if (row.voto) parts.push('voto ' + row.voto);
            if (row.motivo) parts.push(row.motivo);
            return '<li>' + iscrizioniTerzeEscape(parts.join(' - ') || JSON.stringify(row)) + '</li>';
        }).join('') +
        (rows.length > 40 ? '<li>... altri ' + iscrizioniTerzeEscape(rows.length - 40) + '</li>' : '') +
        '</ul></div>';
}

function iscrizioniTerzeReadJsonResponse(response) {
    return response.text().then(text => {
        const trimmed = String(text || '').trim();
        if (trimmed === '') {
            throw new Error('Risposta vuota dal server. Controlla il log PHP/Apache per iscrizioniPrimeImport.php.');
        }
        try {
            return JSON.parse(trimmed);
        } catch (e) {
            throw new Error('Risposta non JSON dal server: ' + trimmed.slice(0, 500));
        }
    });
}

function iscrizioniTerzeStatoLabel(stato) {
    const labels = {
        inviata: 'Inviata',
        verifica_iniziale_ok: 'Verifica iniziale OK',
        verificata: 'Pratica completata',
        da_integrare: 'Da integrare',
        annullata: 'Cambio scuola'
    };
    return labels[String(stato || '')] || String(stato || '');
}

function iscrizioniTerzeMovimentoStatoLabel(stato) {
    const labels = {
        reiscrizione_confermata: 'Reiscrizione confermata',
        chiusa: 'Chiusa',
        da_verificare: 'Da verificare'
    };
    return labels[String(stato || '')] || String(stato || '').replace(/_/g, ' ');
}

function iscrizioniTerzeSetText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value || 0;
}

function iscrizioniTerzeNumber(value) {
    return value === undefined || value === null || value === '' ? 0 : value;
}

function iscrizioniTerzeToggleInitialTools() {
    const box = document.getElementById('iscrizioni_terze_initial_tools');
    const icon = document.getElementById('iscrizioni_terze_initial_tools_icon');
    const label = document.getElementById('iscrizioni_terze_initial_tools_label');
    if (!box) return;
    const open = box.style.display === 'none' || box.style.display === '';
    box.style.display = open ? 'block' : 'none';
    if (icon) {
        icon.className = 'glyphicon ' + (open ? 'glyphicon-chevron-up' : 'glyphicon-chevron-down');
    }
    if (label) {
        label.textContent = open ? 'Nascondi bozza Gmail e import CSV' : 'Mostra bozza Gmail e import CSV';
    }
}

function iscrizioniTerzeFindRowById(id) {
    id = Number(id || 0);
    return iscrizioniTerzeRows.find(row => Number(row.id || 0) === id) || null;
}

function iscrizioniTerzeRenderCustomMailRecipients(row) {
    const box = document.getElementById('custom_mail_recipients');
    if (!box) return;
    const items = [];
    const seen = {};
    [['Genitore 1', row?.email_genitore_1 || ''], ['Genitore 2', row?.email_genitore_2 || '']].forEach(item => {
        const email = String(item[1] || '').trim().toLowerCase();
        if (!email || seen[email]) return;
        seen[email] = true;
        items.push('<label style="display:block;margin:4px 0;font-weight:600;">'
            + '<input type="checkbox" class="custom-mail-recipient" value="' + iscrizioniTerzeEscape(email) + '" checked> '
            + iscrizioniTerzeEscape(item[0]) + ' - ' + iscrizioniTerzeEscape(email)
            + '</label>');
    });
    box.innerHTML = items.length ? items.join('') : '<span class="text-danger">Nessuna email genitore presente nella pratica.</span>';
}

function iscrizioniTerzeOpenCustomMail(id) {
    const row = iscrizioniTerzeFindRowById(id);
    if (!row) {
        alert('Pratica non trovata.');
        return;
    }
    document.getElementById('custom_mail_id').value = Number(row.id || 0);
    document.getElementById('custom_mail_student').textContent = 'Pratica di ' + String((row.cognome || '') + ' ' + (row.nome || '')).trim();
    const subject = document.getElementById('custom_mail_subject');
    const message = document.getElementById('custom_mail_message');
    const error = document.getElementById('custom_mail_error');
    if (subject && subject.value.trim() === '') {
        subject.value = 'Comunicazione pratica iscrizione';
    }
    if (message) {
        message.value = '';
    }
    iscrizioniTerzeRenderCustomMailRecipients(row);
    if (error) {
        error.hidden = true;
        error.textContent = '';
    }
    const button = document.getElementById('custom_mail_send_button');
    if (button) {
        button.disabled = false;
        button.textContent = 'Invia mail';
    }
    const modal = document.getElementById('custom_mail_modal');
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    setTimeout(() => message && message.focus(), 50);
}

function iscrizioniTerzeCloseCustomMail() {
    const modal = document.getElementById('custom_mail_modal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.getElementById('custom_mail_id').value = '';
}

function iscrizioniTerzeSetInputValue(id, value) {
    const input = document.getElementById(id);
    if (input) input.value = value || '';
}

function iscrizioniTerzeOpenParentsModal(id) {
    const row = iscrizioniTerzeFindRowById(id);
    if (!row) {
        alert('Pratica non trovata.');
        return;
    }
    document.getElementById('parents_id').value = Number(row.id || 0);
    document.getElementById('parents_student').textContent = 'Pratica di ' + String((row.cognome || '') + ' ' + (row.nome || '')).trim();
    iscrizioniTerzeSetInputValue('parents_r1_tipo', row.responsabile_1_tipo);
    iscrizioniTerzeSetInputValue('parents_r1_cognome', row.responsabile_1_cognome);
    iscrizioniTerzeSetInputValue('parents_r1_nome', row.responsabile_1_nome);
    iscrizioniTerzeSetInputValue('parents_r1_cf', row.responsabile_1_codice_fiscale);
    iscrizioniTerzeSetInputValue('parents_r1_email', row.email_genitore_1);
    iscrizioniTerzeSetInputValue('parents_r1_tel', row.telefono_genitore_1);
    iscrizioniTerzeSetInputValue('parents_r2_tipo', row.responsabile_2_tipo);
    iscrizioniTerzeSetInputValue('parents_r2_cognome', row.responsabile_2_cognome);
    iscrizioniTerzeSetInputValue('parents_r2_nome', row.responsabile_2_nome);
    iscrizioniTerzeSetInputValue('parents_r2_cf', row.responsabile_2_codice_fiscale);
    iscrizioniTerzeSetInputValue('parents_r2_email', row.email_genitore_2);
    iscrizioniTerzeSetInputValue('parents_r2_tel', row.telefono_genitore_2);
    const error = document.getElementById('parents_error');
    if (error) {
        error.hidden = true;
        error.textContent = '';
    }
    document.getElementById('parents_modal').classList.add('open');
    document.getElementById('parents_modal').setAttribute('aria-hidden', 'false');
}

function iscrizioniTerzeCloseParentsModal() {
    document.getElementById('parents_modal').classList.remove('open');
    document.getElementById('parents_modal').setAttribute('aria-hidden', 'true');
    document.getElementById('parents_id').value = '';
}

document.getElementById('parents_form')?.addEventListener('submit', function (event) {
    event.preventDefault();
    const error = document.getElementById('parents_error');
    const button = document.getElementById('parents_save_button');
    const data = new FormData(event.target);
    if (button) {
        button.disabled = true;
        button.textContent = 'Salvataggio...';
    }
    if (error) {
        error.hidden = true;
        error.textContent = '';
    }

    fetch('iscrizioniPrimeGenitoriSave.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
    .then(response => response.json().then(result => ({ok: response.ok, result})))
    .then(payload => {
        if (!payload.ok || !payload.result.ok) {
            throw new Error(payload.result.message || 'Salvataggio non riuscito.');
        }
        iscrizioniTerzeCloseParentsModal();
        iscrizioniTerzeLoadTable();
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
            button.textContent = 'Salva dati genitori';
        }
    });
});

function iscrizioniTerzeFormatTextarea(id, mode) {
    const field = document.getElementById(id);
    if (!field) return;
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

function iscrizioniTerzeTipoEffettivo(row) {
    return Number(row.studente_interno_effettivo ?? row.studente_interno ?? 0) === 1 ? 'INTERNO' : 'ESTERNO';
}

function iscrizioniTerzeHasSubmittedPractice(row) {
    return ['inviata', 'verifica_iniziale_ok', 'da_integrare', 'verificata', 'annullata'].includes(String(row.stato || '').toLowerCase());
}

function iscrizioniTerzePracticeButton(row) {
    if (!iscrizioniTerzeHasSubmittedPractice(row)) {
        return '';
    }
    const url = 'iscrizioniPrimeDomande.php?tipo_iscrizione=terze&stato=tutte&open_pratica_id=' + encodeURIComponent(Number(row.id || 0)) + '#pratica-' + encodeURIComponent(Number(row.id || 0));
    return '<a class="btn btn-xs btn-success" href="' + url + '"><span class="glyphicon glyphicon-folder-open"></span> Pratica</a> ';
}

function iscrizioniTerzeAttributiHtml(row) {
    const attrs = Array.isArray(row.attributi_riservati) ? row.attributi_riservati : [];
    if (!attrs.length) {
        return '<span class="text-muted">-</span>';
    }
    return attrs.map(attr => {
        const source = attr.fonte ? '<span class="stud-attr-source">' + iscrizioniTerzeEscape(attr.fonte) + '</span>' : '';
        return '<span class="stud-attr-badge" title="' + iscrizioniTerzeEscape(attr.codice || '') + '">' + iscrizioniTerzeEscape(attr.label || attr.codice || '') + source + '</span>';
    }).join(' ');
}

function iscrizioniTerzeNoteBadge(row) {
    const note = String(row.note_genitori_iscrizione || '').trim();
    if (note === '') return '';
    const shortNote = note.length > 160 ? note.slice(0, 157) + '...' : note;
    return '<div style="margin-top:6px;"><span class="stud-attr-badge" title="' + iscrizioniTerzeEscape(note) + '">Note genitori</span><br><small class="text-muted">' + iscrizioniTerzeEscape(shortNote) + '</small></div>';
}

function iscrizioniTerzeCurvaturaHtml(row) {
    const course = iscrizioniTerzeNormalizeSearch((row.corso_studi || '') + ' ' + (row.indirizzo_gestore_nome || ''));
    const isCat = course.includes('costruzioni') || course.includes('territorio');
    if (!isCat && !row.curvatura_design) return '';
    const value = String(row.curvatura_design || '').toLowerCase();
    if (value === 'design') {
        return '<br><span class="mail-badge mail-badge-real">Design: si</span>';
    }
    if (value === 'normale') {
        return '<br><span class="mail-badge mail-badge-skip">Design: no</span>';
    }
    return '<br><span class="mail-badge mail-badge-none">Design: da indicare</span>';
}

function iscrizioniTerzeNormalizeSearch(value) {
    return String(value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

function iscrizioniTerzeRowSearchText(row) {
    return iscrizioniTerzeNormalizeSearch([
        row.cognome,
        row.nome,
        iscrizioniTerzeTipoEffettivo(row),
        row.codice_fiscale,
        row.corso_studi,
        row.indirizzo_gestore_nome,
        row.note_genitori_iscrizione,
        row.curvatura_design,
        row.stato,
        row.email_genitore_1,
        row.email_genitore_2,
        row.telefono_genitore_1,
        row.telefono_genitore_2,
        row.token_last4,
        row.mail_diagnosi,
        (Array.isArray(row.attributi_riservati) ? row.attributi_riservati.map(attr => attr.label + ' ' + attr.codice + ' ' + (attr.fonte || '')).join(' ') : ''),
        row.scuola_provenienza,
        row.comune_residenza
    ].filter(Boolean).join(' '));
}

function iscrizioniTerzeIndirizzoSelect(row) {
    let html = '<select class="form-control input-sm" onchange="iscrizioniTerzeSaveIndirizzo(' + Number(row.id) + ', this)">';
    html += '<option value="">Da impostare</option>';
    iscrizioniTerzeIndirizziGestore.forEach(item => {
        const selected = Number(row.id_indirizzo_gestore || 0) === Number(item.id) ? ' selected' : '';
        html += '<option value="' + Number(item.id) + '"' + selected + '>' + iscrizioniTerzeEscape(item.nome) + '</option>';
    });
    html += '</select>';
    if (row.corso_studi) {
        html += '<small class="text-muted">import: ' + iscrizioniTerzeEscape(row.corso_studi) + '</small>';
    }
    return html;
}

function iscrizioniTerzeSaveIndirizzo(id, select) {
    const formData = new FormData();
    formData.append('id', String(id));
    formData.append('id_indirizzo_gestore', select.value || '');
    select.disabled = true;
    fetch('iscrizioniTerzeIndirizzoSave.php', {method: 'POST', body: formData})
        .then(response => response.json())
        .then(data => {
            if (!data.ok) throw new Error(data.message || 'Errore salvataggio indirizzo.');
            const row = iscrizioniTerzeRows.find(item => Number(item.id) === Number(id));
            if (row) {
                row.id_indirizzo_gestore = select.value || '';
                const opt = iscrizioniTerzeIndirizziGestore.find(item => Number(item.id) === Number(select.value || 0));
                row.indirizzo_gestore_nome = opt ? opt.nome : '';
            }
        })
        .catch(error => {
            alert(error.message || 'Errore salvataggio indirizzo.');
        })
        .finally(() => {
            select.disabled = false;
        });
}

function iscrizioniTerzeFilteredRows() {
    const filter = document.getElementById('iscrizioni_terze_filter');
    const terms = iscrizioniTerzeNormalizeSearch(filter ? filter.value : '').trim().split(/\s+/).filter(Boolean);
    if (!terms.length) {
        return iscrizioniTerzeRows.slice();
    }
    return iscrizioniTerzeRows.filter(row => {
        const text = iscrizioniTerzeRowSearchText(row);
        return terms.every(term => text.includes(term));
    });
}

function iscrizioniTerzeUpdateStats(stats, mailStats) {
    stats = stats || {};
    mailStats = mailStats || {};
    iscrizioniTerzeSetText('stat_totale', stats.totale || 0);
    iscrizioniTerzeSetText('stat_interni', stats.interni || 0);
    iscrizioniTerzeSetText('stat_esterni', stats.esterni || 0);
    iscrizioniTerzeSetText('stat_domande_inviate', stats.domande_inviate_esterni || stats.domande_inviate || 0);
    iscrizioniTerzeSetText('stat_mail_reali', mailStats.mail_reali || 0);
    iscrizioniTerzeSetText('stat_mail_test', mailStats.mail_test || 0);
}

function iscrizioniTerzeMailStatus(row) {
    if (iscrizioniTerzeTipoEffettivo(row) === 'INTERNO') {
        return '<span class="mail-badge mail-badge-skip">Non richiesta</span>';
    }
    const real = Number(row.mail_reali || 0);
    const test = Number(row.mail_test || 0);
    const bounce = Number(row.mail_bounce || 0);
    let html = '';
    if (bounce > 0) {
        html += '<span class="mail-badge mail-badge-bounce">Bounce</span>';
        if (row.bounce_reason) html += '<br><small>' + iscrizioniTerzeEscape(row.bounce_reason) + '</small>';
        if (row.last_bounced_at) html += '<br><small>' + iscrizioniTerzeEscape(row.last_bounced_at) + '</small>';
    } else if (real > 0) {
        html += '<span class="mail-badge mail-badge-real">Reale inviata</span>';
        if (row.last_real_sent_at) html += '<br><small>' + iscrizioniTerzeEscape(row.last_real_sent_at) + '</small>';
    } else if (test > 0) {
        html += '<span class="mail-badge mail-badge-test">Test inviato</span>';
        if (row.last_test_sent_at) html += '<br><small>' + iscrizioniTerzeEscape(row.last_test_sent_at) + '</small>';
    } else if (Number(row.mail_pending || 0) <= 0 && row.mail_diagnosi) {
        html += '<span class="mail-badge mail-badge-skip">Non richiesta</span>';
        html += '<br><small>' + iscrizioniTerzeEscape(row.mail_diagnosi) + '</small>';
    } else if (!['importata', 'bozza', 'da_integrare'].includes(String(row.stato || '').toLowerCase())) {
        html += '<span class="mail-badge mail-badge-skip">Non richiesta</span>';
        if (row.stato) html += '<br><small>pratica ' + iscrizioniTerzeEscape(iscrizioniTerzeStatoLabel(row.stato)) + '</small>';
    } else {
        html += '<span class="mail-badge mail-badge-none">Da inviare</span>';
    }
    if (real > 1 || test > 1) {
        html += '<br><small>reali ' + real + ' / test ' + test + '</small>';
    }
    return html;
}

function iscrizioniTerzeRenderTable() {
    const tbody = document.querySelector('#iscrizioni_terze_table tbody');
    const counter = document.getElementById('iscrizioni_terze_filter_count');
    iscrizioniTerzeVisibleRows = iscrizioniTerzeFilteredRows();

    if (!iscrizioniTerzeRows.length) {
        tbody.innerHTML = '<tr><td colspan="11" class="text-muted">Nessuna pratica importata.</td></tr>';
        if (counter) counter.textContent = '';
        return;
    }

    if (!iscrizioniTerzeVisibleRows.length) {
        tbody.innerHTML = '<tr><td colspan="11" class="text-muted">Nessuna pratica corrisponde al filtro.</td></tr>';
        if (counter) counter.textContent = '0 di ' + iscrizioniTerzeRows.length + ' pratiche';
        return;
    }

    tbody.innerHTML = iscrizioniTerzeVisibleRows.map(row => {
        const emails = [row.email_genitore_1, row.email_genitore_2].filter(Boolean).join('<br>');
        const isInternal = iscrizioniTerzeTipoEffettivo(row) === 'INTERNO';
        const token = isInternal ? '-' : (row.token_last4 ? ('...' + row.token_last4) : '<span class="text-danger">da esportare</span>');
        const reiscrizione = Number(row.movimento_reiscrizione_id || 0) > 0
            ? '<br><span class="mail-badge mail-badge-real">Reiscrizione: ' + iscrizioniTerzeEscape(iscrizioniTerzeMovimentoStatoLabel(row.movimento_reiscrizione_stato)) + '</span>'
            : '';
        const tipo = isInternal
            ? '<span class="label label-default">interno</span>' + (row.classe_corrente_gestore ? '<br><small class="text-muted">' + iscrizioniTerzeEscape(row.classe_corrente_gestore) + '</small>' : '') + reiscrizione
            : '<span class="label label-warning">esterno</span>' + (row.classe_corrente_gestore ? '<br><small class="text-muted">' + iscrizioniTerzeEscape(row.classe_corrente_gestore) + '</small>' : '') + reiscrizione;
        const testButton = isInternal
            ? '<span class="text-muted">non richiesto</span>'
            : '<button type="button" class="btn btn-xs btn-info" onclick="iscrizioniTerzeOpenTestLink(' + Number(row.id) + ')"><span class="glyphicon glyphicon-new-window"></span> Apri</button>';
        const writeButton = '<button type="button" class="btn btn-xs btn-primary" onclick="iscrizioniTerzeOpenCustomMail(' + Number(row.id) + ')"><span class="glyphicon glyphicon-envelope"></span> Scrivi</button>';
        const resendLinkButton = isInternal
            ? ''
            : ' <button type="button" class="btn btn-xs btn-warning" onclick="iscrizioniTerzeResendPracticeLink(' + Number(row.id) + ')"><span class="glyphicon glyphicon-share-alt"></span> Rimanda link</button>';

        return '<tr>' +
            '<td><strong>' + iscrizioniTerzeEscape(row.cognome) + '</strong> ' + iscrizioniTerzeEscape(row.nome) + iscrizioniTerzeNoteBadge(row) + '</td>' +
            '<td>' + iscrizioniTerzeEscape(row.codice_fiscale) + '</td>' +
            '<td>' + iscrizioniTerzeEscape(row.corso_studi) + iscrizioniTerzeCurvaturaHtml(row) + '</td>' +
            '<td>' + iscrizioniTerzeIndirizzoSelect(row) + '</td>' +
            '<td>' + iscrizioniTerzeAttributiHtml(row) + '</td>' +
            '<td>' + tipo + '</td>' +
            '<td>' + iscrizioniTerzeEscape(iscrizioniTerzeStatoLabel(row.stato)) + '</td>' +
            '<td>' + (emails || '<span class="text-danger">mancante</span>') + '</td>' +
            '<td>' + iscrizioniTerzeMailStatus(row) + '</td>' +
            '<td>' + token + '</td>' +
            '<td>' + writeButton + ' ' + testButton + ' ' + iscrizioniTerzePracticeButton(row) +
                '<button type="button" class="btn btn-xs btn-default" onclick="iscrizioniTerzeOpenParentsModal(' + Number(row.id) + ')"><span class="glyphicon glyphicon-user"></span> Genitori</button>' +
                resendLinkButton + '</td>' +
            '</tr>';
    }).join('');

    if (counter) {
        counter.textContent = iscrizioniTerzeVisibleRows.length + ' di ' + iscrizioniTerzeRows.length + ' pratiche';
    }
}

function iscrizioniTerzeClearFilter() {
    const filter = document.getElementById('iscrizioni_terze_filter');
    if (filter) {
        filter.value = '';
        filter.focus();
    }
    iscrizioniTerzeRenderTable();
}

function iscrizioniTerzeCsvCell(value) {
    const text = String(value ?? '').replace(/\r?\n/g, ' ').trim();
    return '"' + text.replace(/"/g, '""') + '"';
}

function iscrizioniTerzeExportFilteredCsv() {
    const rows = iscrizioniTerzeVisibleRows.length || !iscrizioniTerzeRows.length
        ? iscrizioniTerzeVisibleRows
        : iscrizioniTerzeFilteredRows();
    if (!rows.length) {
        alert('Nessuna riga da esportare.');
        return;
    }

    const header = [
        'Studente',
        'Tipo',
        'Codice fiscale',
        'Corso',
        'Indirizzo GestOre',
        'Attributi',
        'Stato',
        'Email responsabile 1',
        'Email responsabile 2',
        'Mail reali',
        'Mail test',
        'Bounce',
        'Token'
    ];
    const lines = [header.map(iscrizioniTerzeCsvCell).join(';')];
    rows.forEach(row => {
        lines.push([
            ((row.cognome || '') + ' ' + (row.nome || '')).trim(),
            iscrizioniTerzeTipoEffettivo(row),
            row.codice_fiscale,
            row.corso_studi,
            row.indirizzo_gestore_nome,
            Array.isArray(row.attributi_riservati) ? row.attributi_riservati.map(attr => attr.label || attr.codice || '').join(', ') : '',
            row.stato,
            row.email_genitore_1,
            row.email_genitore_2,
            row.mail_reali || 0,
            row.mail_test || 0,
            row.mail_bounce || 0,
            row.token_last4 ? '...' + row.token_last4 : ''
        ].map(iscrizioniTerzeCsvCell).join(';'));
    });

    const blob = new Blob(['\ufeff' + lines.join('\r\n')], {type: 'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    const now = new Date().toISOString().slice(0, 10);
    a.href = url;
    a.download = 'iscrizioni_terze_filtrate_' + now + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function iscrizioniTerzeBounceDetails(data) {
    const totals = data.totals || {};
    let html =
        'Messaggi controllati: <strong>' + iscrizioniTerzeEscape(iscrizioniTerzeNumber(totals.checked)) + '</strong>' +
        ' &middot; collegati a pratiche: <strong>' + iscrizioniTerzeEscape(iscrizioniTerzeNumber(totals.matched)) + '</strong>' +
        ' &middot; non collegati: <strong>' + iscrizioniTerzeEscape(iscrizioniTerzeNumber(totals.unmatched)) + '</strong>' +
        '<br>Limite invio: <strong>' + iscrizioniTerzeEscape(iscrizioniTerzeNumber(totals.quota_limit)) + '</strong>' +
        ' &middot; indirizzo errato: <strong>' + iscrizioniTerzeEscape(iscrizioniTerzeNumber(totals.invalid_recipient)) + '</strong>' +
        ' &middot; casella piena: <strong>' + iscrizioniTerzeEscape(iscrizioniTerzeNumber(totals.mailbox_full)) + '</strong>' +
        ' &middot; altro: <strong>' + iscrizioniTerzeEscape(iscrizioniTerzeNumber(totals.other_bounce)) + '</strong>';

    if (data.export_url) {
        html += '<br><a class="btn btn-sm btn-default" style="margin-top:10px;" href="' + iscrizioniTerzeEscape(data.export_url) + '"><span class="glyphicon glyphicon-download-alt"></span> Scarica report CSV</a>';
    }
    return html;
}

function iscrizioniTerzeCheckBounce() {
    const result = document.getElementById('iscrizioni_terze_result');
    const formData = new FormData();
    formData.append('tipo_iscrizione', 'terze');
    formData.append('max', '60');

    result.className = 'alert alert-info';
    result.style.display = 'block';
    result.textContent = 'Controllo bounce in corso...';
    iscrizioniTerzeShowMailOverlay('Controllo bounce', 'GestOre sta leggendo le notifiche di mancata consegna nelle caselle iscrizioni.');

    fetch('iscrizioniPrimeMailBounceCheck.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        const details = iscrizioniTerzeBounceDetails(data);
        result.className = data.ok ? 'alert alert-success' : 'alert alert-warning';
        result.innerHTML = details;
        iscrizioniTerzeCompleteMailOverlay(
            !!data.ok,
            data.ok ? 'Controllo bounce completato' : 'Controllo completato con avvisi',
            data.ok ? 'Le mail rimbalzate trovate sono state segnate nelle pratiche.' : 'Alcuni account non sono stati controllati correttamente.',
            details
        );
        iscrizioniTerzeLoadTable();
    })
    .catch(error => {
        result.className = 'alert alert-danger';
        result.textContent = error.message;
        iscrizioniTerzeCompleteMailOverlay(false, 'Controllo bounce non riuscito', error.message, '');
    });
}

function iscrizioniTerzeShowMailOverlay(title, text) {
    if (iscrizioniTerzeMailProgressTimer) {
        clearInterval(iscrizioniTerzeMailProgressTimer);
    }
    iscrizioniTerzeMailProgressValue = 3;
    document.getElementById('iscrizioni_mail_overlay').style.display = 'flex';
    document.getElementById('iscrizioni_mail_title').textContent = title;
    document.getElementById('iscrizioni_mail_text').textContent = text;
    document.getElementById('iscrizioni_mail_percent').textContent = iscrizioniTerzeMailProgressValue + '%';
    document.getElementById('iscrizioni_mail_details').textContent = '';
    document.getElementById('iscrizioni_mail_close').style.display = 'none';
    document.getElementById('iscrizioni_mail_confirm_actions').style.display = 'none';
    document.querySelector('.iscrizioni-mail-progress').style.display = '';
    document.getElementById('iscrizioni_mail_percent').style.display = '';
    const bar = document.getElementById('iscrizioni_mail_progress_bar');
    bar.className = 'iscrizioni-mail-progress-bar';
    bar.style.width = iscrizioniTerzeMailProgressValue + '%';
    document.getElementById('iscrizioni_mail_icon').style.background = '#0ea5e9';
    document.getElementById('iscrizioni_mail_icon').innerHTML = '<span class="glyphicon glyphicon-send"></span>';

    iscrizioniTerzeMailProgressTimer = setInterval(function () {
        if (iscrizioniTerzeMailProgressValue < 70) {
            iscrizioniTerzeMailProgressValue += 4;
        } else if (iscrizioniTerzeMailProgressValue < 90) {
            iscrizioniTerzeMailProgressValue += 1;
        }
        document.getElementById('iscrizioni_mail_percent').textContent = iscrizioniTerzeMailProgressValue + '%';
        bar.style.width = iscrizioniTerzeMailProgressValue + '%';
    }, 900);
}

function iscrizioniTerzeConfirmMailDialog(title, text, details) {
    if (iscrizioniTerzeMailProgressTimer) {
        clearInterval(iscrizioniTerzeMailProgressTimer);
        iscrizioniTerzeMailProgressTimer = null;
    }
    document.getElementById('iscrizioni_mail_overlay').style.display = 'flex';
    document.getElementById('iscrizioni_mail_title').textContent = title;
    document.getElementById('iscrizioni_mail_text').textContent = text;
    document.getElementById('iscrizioni_mail_percent').style.display = 'none';
    document.querySelector('.iscrizioni-mail-progress').style.display = 'none';
    document.getElementById('iscrizioni_mail_details').innerHTML = details || '';
    document.getElementById('iscrizioni_mail_close').style.display = 'none';
    document.getElementById('iscrizioni_mail_confirm_actions').style.display = 'block';
    document.getElementById('iscrizioni_mail_icon').style.background = '#1d4ed8';
    document.getElementById('iscrizioni_mail_icon').innerHTML = '<span class="glyphicon glyphicon-envelope"></span>';

    return new Promise(resolve => {
        const cancel = document.getElementById('iscrizioni_mail_cancel');
        const confirm = document.getElementById('iscrizioni_mail_confirm');
        const cleanup = result => {
            cancel.onclick = null;
            confirm.onclick = null;
            document.getElementById('iscrizioni_mail_confirm_actions').style.display = 'none';
            if (!result) {
                iscrizioniTerzeHideMailOverlay();
            }
            resolve(result);
        };
        cancel.onclick = () => cleanup(false);
        confirm.onclick = () => cleanup(true);
    });
}

function iscrizioniTerzeCompleteMailOverlay(ok, title, text, details) {
    if (iscrizioniTerzeMailProgressTimer) {
        clearInterval(iscrizioniTerzeMailProgressTimer);
        iscrizioniTerzeMailProgressTimer = null;
    }
    document.getElementById('iscrizioni_mail_title').textContent = title;
    document.getElementById('iscrizioni_mail_text').textContent = text;
    document.getElementById('iscrizioni_mail_percent').textContent = ok ? '100%' : 'Errore';
    document.getElementById('iscrizioni_mail_details').innerHTML = details || '';
    document.getElementById('iscrizioni_mail_close').style.display = 'inline-block';
    document.getElementById('iscrizioni_mail_confirm_actions').style.display = 'none';
    document.querySelector('.iscrizioni-mail-progress').style.display = '';
    document.getElementById('iscrizioni_mail_percent').style.display = '';
    const bar = document.getElementById('iscrizioni_mail_progress_bar');
    bar.className = 'iscrizioni-mail-progress-bar';
    bar.style.width = '100%';
    bar.style.background = ok ? 'linear-gradient(90deg, #22c55e, #16a34a)' : '#dc2626';
    document.getElementById('iscrizioni_mail_icon').style.background = ok ? '#16a34a' : '#dc2626';
    document.getElementById('iscrizioni_mail_icon').innerHTML = ok ? '<span class="glyphicon glyphicon-ok"></span>' : '<span class="glyphicon glyphicon-alert"></span>';
}

function iscrizioniTerzeHideMailOverlay() {
    document.getElementById('iscrizioni_mail_overlay').style.display = 'none';
    document.getElementById('iscrizioni_mail_confirm_actions').style.display = 'none';
    document.querySelector('.iscrizioni-mail-progress').style.display = '';
    document.getElementById('iscrizioni_mail_percent').style.display = '';
}

async function iscrizioniTerzeSendCustomMail() {
    const id = Number(document.getElementById('custom_mail_id')?.value || 0);
    const subject = (document.getElementById('custom_mail_subject')?.value || '').trim();
    const message = (document.getElementById('custom_mail_message')?.value || '').trim();
    const signature = (document.getElementById('custom_mail_signature')?.value || '').trim();
    const recipients = Array.from(document.querySelectorAll('.custom-mail-recipient:checked')).map(el => el.value);
    const error = document.getElementById('custom_mail_error');
    const button = document.getElementById('custom_mail_send_button');

    if (id <= 0) {
        if (error) {
            error.textContent = 'Pratica non valida.';
            error.hidden = false;
        }
        return;
    }
    if (subject === '' || message.length < 4) {
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

    const confirmed = await iscrizioniTerzeConfirmMailDialog(
        'Conferma invio mail',
        'La comunicazione sara inviata a ' + recipients.length + ' destinatari selezionati.',
        recipients.map(iscrizioniTerzeEscape).join('<br>')
    );
    if (!confirmed) {
        return;
    }
    iscrizioniTerzeShowMailOverlay('Invio mail in corso', 'GestOre sta inviando la comunicazione ai destinatari selezionati.');

    const data = new FormData();
    data.append('id', id);
    data.append('subject', subject);
    data.append('message', message);
    data.append('signature', signature);
    recipients.forEach(email => data.append('recipients[]', email));

    if (button) {
        button.disabled = true;
        button.textContent = 'Invio...';
    }
    if (error) {
        error.hidden = true;
        error.textContent = '';
    }

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
        iscrizioniTerzeCompleteMailOverlay(
            true,
            'Mail inviata',
            payload.result.message || 'Comunicazione inviata.',
            'Destinatari selezionati: <strong>' + recipients.length + '</strong>'
        );
        iscrizioniTerzeCloseCustomMail();
        iscrizioniTerzeLoadTable();
    })
    .catch(err => {
        iscrizioniTerzeCompleteMailOverlay(false, 'Invio non riuscito', err.message, '');
        if (error) {
            error.textContent = err.message;
            error.hidden = false;
        }
    })
    .finally(() => {
        if (button) {
            button.disabled = false;
            button.textContent = 'Invia mail';
        }
    });
}

async function iscrizioniTerzeResendPracticeLink(id) {
    const row = iscrizioniTerzeFindRowById(id);
    if (!row) {
        alert('Pratica non trovata.');
        return;
    }
    if (iscrizioniTerzeTipoEffettivo(row) === 'INTERNO') {
        iscrizioniTerzeCompleteMailOverlay(false, 'Link non previsto', 'Per gli studenti interni la pratica online non e richiesta.', '');
        return;
    }
    const recipients = [row.email_genitore_1, row.email_genitore_2].filter(Boolean);
    if (!recipients.length) {
        iscrizioniTerzeCompleteMailOverlay(false, 'Link non inviato', 'Nessuna email genitore presente nella pratica.', '');
        return;
    }
    const confirmed = await iscrizioniTerzeConfirmMailDialog(
        'Rimandare il link?',
        'GestOre generera un nuovo link per la pratica e lo inviera ai genitori presenti.',
        recipients.map(iscrizioniTerzeEscape).join('<br>')
    );
    if (!confirmed) {
        return;
    }

    iscrizioniTerzeShowMailOverlay('Invio link pratica', 'GestOre sta inviando il link personale ai genitori.');
    const data = new FormData();
    data.append('id', Number(id || 0));

    fetch('iscrizioniPrimeMailLink.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
    .then(response => response.json().then(result => ({ok: response.ok, result})))
    .then(payload => {
        if (!payload.ok || !payload.result.ok) {
            throw new Error(payload.result.message || 'Invio link non riuscito.');
        }
        iscrizioniTerzeCompleteMailOverlay(
            true,
            'Link inviato',
            payload.result.message || 'Link pratica inviato ai genitori.',
            'Nuovo token: <strong>...' + iscrizioniTerzeEscape(payload.result.token_last4 || '') + '</strong>'
        );
        iscrizioniTerzeLoadTable();
    })
    .catch(error => {
        iscrizioniTerzeCompleteMailOverlay(false, 'Invio link non riuscito', error.message, '');
    });
}

function iscrizioniTerzeLoadTable() {
    const tbody = document.querySelector('#iscrizioni_terze_table tbody');
    tbody.innerHTML = '<tr><td colspan="10" class="text-muted">Caricamento...</td></tr>';

    fetch('iscrizioniPrimeRead.php?tipo_iscrizione=terze', {credentials: 'same-origin'})
        .then(response => response.json())
        .then(data => {
            if (!data.ok) throw new Error(data.message || 'Errore lettura pratiche');
            iscrizioniTerzeUpdateStats(data.stats, data.mail_stats);
            iscrizioniTerzeRows = data.rows || [];
            iscrizioniTerzeRenderTable();
        })
        .catch(error => {
            tbody.innerHTML = '<tr><td colspan="10" class="text-danger">' + iscrizioniTerzeEscape(error.message) + '</td></tr>';
        });
}

function iscrizioniTerzeOpenTestLink(id) {
    const formData = new FormData();
    formData.append('id', id);

    fetch('iscrizioniPrimeTestLink.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (!data.ok) throw new Error(data.message || 'Errore generazione link');
        window.open(data.link, '_blank', 'noopener');
        iscrizioniTerzeLoadTable();
    })
    .catch(error => alert(error.message));
}

function iscrizioniTerzeSendMail(dryRun) {
    const result = document.getElementById('iscrizioni_terze_result');
    if (!dryRun && !confirm('Inviare ora il prossimo lotto di mail agli studenti esterni delle terze?')) return;

    const formData = new FormData();
    formData.append('dry_run', dryRun ? '1' : '0');
    formData.append('tipo_iscrizione', 'terze');

    result.className = 'alert alert-info';
    result.style.display = 'block';
    result.textContent = dryRun ? 'Simulazione invio in corso...' : 'Invio mail in corso...';
    iscrizioniTerzeShowMailOverlay(
        dryRun ? 'Simulazione invio mail terze' : 'Invio lotto mail terze',
        dryRun ? 'GestOre sta calcolando quali mail verrebbero inviate agli esterni.' : 'GestOre sta inviando il prossimo lotto agli esterni. Tieni aperta questa pagina fino alla conferma finale.'
    );

    fetch('iscrizioniPrimeMailSend.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        result.className = data.ok ? 'alert alert-success' : 'alert alert-warning';
        result.innerHTML =
            iscrizioniTerzeEscape(data.message || '') +
            '<br>Mail ' + (dryRun ? 'simulabili' : 'inviate') + ': ' + iscrizioniTerzeEscape(iscrizioniTerzeNumber(data.sent)) +
            ' - saltate: ' + iscrizioniTerzeEscape(iscrizioniTerzeNumber(data.skipped)) +
            (data.remaining !== undefined ? ' - restanti: ' + iscrizioniTerzeEscape(iscrizioniTerzeNumber(data.remaining)) : '') +
            (data.errors && data.errors.length ? '<br>Errori: ' + data.errors.map(iscrizioniTerzeEscape).join(', ') : '');
        iscrizioniTerzeCompleteMailOverlay(
            !!data.ok,
            data.ok ? (dryRun ? 'Simulazione completata' : 'Lotto completato') : 'Lotto completato con avvisi',
            data.message || '',
            'Mail ' + (dryRun ? 'simulabili' : 'inviate') + ': <strong>' + iscrizioniTerzeEscape(iscrizioniTerzeNumber(data.sent)) + '</strong>' +
            ' &middot; Saltate: <strong>' + iscrizioniTerzeEscape(iscrizioniTerzeNumber(data.skipped)) + '</strong>' +
            (data.remaining !== undefined ? ' &middot; Restanti: <strong>' + iscrizioniTerzeEscape(iscrizioniTerzeNumber(data.remaining)) + '</strong>' : '') +
            (data.errors && data.errors.length ? '<br>Errori: ' + data.errors.map(iscrizioniTerzeEscape).join(', ') : '')
        );
        iscrizioniTerzeLoadTable();
    })
    .catch(error => {
        result.className = 'alert alert-danger';
        result.textContent = error.message;
        iscrizioniTerzeCompleteMailOverlay(false, 'Invio non riuscito', error.message, '');
    });
}

function iscrizioniTerzeCorrectSentLinks(dryRun) {
    const result = document.getElementById('iscrizioni_terze_result');
    if (!dryRun && !confirm('Controllare le mail gia inviate in Gmail e reinviare il link corretto solo agli esterni di terza che hanno ricevuto un link non piu valido?')) return;

    const formData = new FormData();
    formData.append('dry_run', dryRun ? '1' : '0');
    formData.append('tipo_iscrizione', 'terze');

    result.className = 'alert alert-info';
    result.style.display = 'block';
    result.textContent = dryRun ? 'Controllo link inviati in corso...' : 'Correzione link inviati in corso...';
    iscrizioniTerzeShowMailOverlay(
        dryRun ? 'Simulazione controllo link terze' : 'Correzione link inviati terze',
        'GestOre legge la posta inviata degli account iscrizioni, confronta i link con quelli attuali e prepara solo le correzioni necessarie.'
    );

    fetch('iscrizioniPrimeMailCorrectLinks.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        const correctionDetails = data.details && data.details.length
            ? '<br><div style="margin-top:10px;"><strong>Pratiche individuate:</strong><ul style="margin-top:6px;">' +
                data.details.map(item =>
                    '<li>' +
                    iscrizioniTerzeEscape(item.studente || '') +
                    (item.codice_fiscale ? ' - ' + iscrizioniTerzeEscape(item.codice_fiscale) : '') +
                    ' - ' + iscrizioniTerzeEscape(item.recipient_email || '') +
                    (item.account_email ? ' <span class="text-muted">(' + iscrizioniTerzeEscape(item.account_email) + ')</span>' : '') +
                    '</li>'
                ).join('') +
                '</ul></div>'
            : '';
        result.className = data.ok ? 'alert alert-success' : 'alert alert-warning';
        result.innerHTML =
            iscrizioniTerzeEscape(data.message || '') +
            '<br>Mail Gmail controllate: ' + iscrizioniTerzeEscape(iscrizioniTerzeNumber(data.checked)) +
            '<br>Correzioni ' + (dryRun ? 'simulabili' : 'inviate') + ': ' + iscrizioniTerzeEscape(iscrizioniTerzeNumber(data.sent)) +
            ' - saltate: ' + iscrizioniTerzeEscape(iscrizioniTerzeNumber(data.skipped)) +
            (data.remaining !== undefined ? ' - restanti: ' + iscrizioniTerzeEscape(iscrizioniTerzeNumber(data.remaining)) : '') +
            (data.errors && data.errors.length ? '<br>Errori: ' + data.errors.map(iscrizioniTerzeEscape).join(', ') : '') +
            correctionDetails;
        iscrizioniTerzeCompleteMailOverlay(
            !!data.ok,
            data.ok ? (dryRun ? 'Controllo completato' : 'Correzione completata') : 'Correzione completata con avvisi',
            data.message || '',
            'Mail Gmail controllate: <strong>' + iscrizioniTerzeEscape(iscrizioniTerzeNumber(data.checked)) + '</strong>' +
            '<br>Correzioni ' + (dryRun ? 'simulabili' : 'inviate') + ': <strong>' + iscrizioniTerzeEscape(iscrizioniTerzeNumber(data.sent)) + '</strong>' +
            ' &middot; Saltate: <strong>' + iscrizioniTerzeEscape(iscrizioniTerzeNumber(data.skipped)) + '</strong>' +
            (data.remaining !== undefined ? ' &middot; Restanti: <strong>' + iscrizioniTerzeEscape(iscrizioniTerzeNumber(data.remaining)) + '</strong>' : '') +
            (data.errors && data.errors.length ? '<br>Errori: ' + data.errors.map(iscrizioniTerzeEscape).join(', ') : '') +
            correctionDetails
        );
        iscrizioniTerzeLoadTable();
    })
    .catch(error => {
        result.className = 'alert alert-danger';
        result.textContent = error.message;
        iscrizioniTerzeCompleteMailOverlay(false, 'Controllo link non riuscito', error.message, '');
    });
}

function iscrizioniTerzeSendTestMail() {
    const result = document.getElementById('iscrizioni_terze_result');
    const fd = new FormData();
    fd.append('tipo_iscrizione', 'terze');
    result.className = 'alert alert-info';
    result.style.display = 'block';
    result.textContent = 'Invio mail di test in corso...';
    result.scrollIntoView({behavior: 'smooth', block: 'center'});

    fetch('iscrizioniPrimeMailTest.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
    })
    .then(response => response.text().then(text => {
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            throw new Error('Risposta non valida dal server. Verifica che la sessione sia attiva e riprova.');
        }
        if (!response.ok || !data.ok) {
            throw new Error(data.message || 'Invio mail di test non riuscito.');
        }
        return data;
    }))
    .then(data => {
        result.className = data.ok ? 'alert alert-success' : 'alert alert-danger';
        result.innerHTML = iscrizioniTerzeEscape(data.message || '') +
            (data.to ? '<br>Inviata a: ' + iscrizioniTerzeEscape(data.to) : '') +
            (data.original_recipient ? '<br>Destinatario reale simulato: ' + iscrizioniTerzeEscape(data.original_recipient) : '') +
            (data.student ? '<br>Studente: ' + iscrizioniTerzeEscape(data.student) : '');
        iscrizioniTerzeLoadTable();
    })
    .catch(error => {
        result.className = 'alert alert-danger';
        result.textContent = error.message;
        result.scrollIntoView({behavior: 'smooth', block: 'center'});
    });
}

function iscrizioniTerzeToggleManuale() {
    const form = document.getElementById('iscrizioni_terze_manual_form');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

document.getElementById('iscrizioni_terze_manual_form').addEventListener('submit', function (event) {
    event.preventDefault();
    const result = document.getElementById('iscrizioni_terze_result');
    result.className = 'alert alert-info';
    result.style.display = 'block';
    result.textContent = 'Salvataggio pratica manuale in corso...';

    fetch('iscrizioniTerzeManualSave.php', {
        method: 'POST',
        body: new FormData(event.target),
        credentials: 'same-origin'
    })
    .then(response => response.json().then(data => ({ok: response.ok, data})))
    .then(resultData => {
        if (!resultData.ok || !resultData.data.ok) {
            throw new Error(resultData.data.message || 'Errore salvataggio pratica manuale');
        }
        result.className = 'alert alert-success';
        result.textContent = resultData.data.message || 'Pratica salvata.';
        event.target.reset();
        event.target.style.display = 'none';
        iscrizioniTerzeLoadTable();
    })
    .catch(error => {
        result.className = 'alert alert-danger';
        result.textContent = error.message;
    });
});

document.getElementById('iscrizioni_terze_import_form').addEventListener('submit', function (event) {
    event.preventDefault();
    const result = document.getElementById('iscrizioni_terze_result');
    result.className = 'alert alert-info';
    result.style.display = 'block';
    result.textContent = 'Importazione in corso...';

    fetch('iscrizioniPrimeImport.php', {
        method: 'POST',
        body: new FormData(event.target),
        credentials: 'same-origin'
    })
    .then(iscrizioniTerzeReadJsonResponse)
    .then(data => {
        if (!data.ok) throw new Error(data.message || 'Errore importazione');
        result.className = 'alert alert-success';
        result.innerHTML =
            'Import completato: ' +
            data.inserted + ' nuove, ' +
            data.updated + ' aggiornate. ' +
            'Interni: ' + data.interni + ', esterni: ' + data.esterni + '. ' +
            'Righe DSA: ' + data.dsa_rows + ', aggiornamenti standalone: ' + (data.dsa_updated || 0) + '. ' +
            'Dati aggiuntivi: ' + (data.additional_rows || 0) + ' righe, aggiornate: ' + (data.additional_updated || 0) + ', ignorate: ' + (data.additional_ignored || 0) + '. ' +
            'Certificazioni scuola: ' + (data.school_attr_rows || 0) + ' righe, studenti agganciati: ' + (data.school_attr_matched || 0) + ', non agganciati: ' + (data.school_attr_unmatched || 0) + '. ' +
            'Contatti aggiornati: ' + data.contacts_updated + ', anagrafiche ignorate: ' + data.contacts_ignored + '. ' +
            'Interni non aggiornati: ' + (data.contacts_internal_skipped || 0) + '. ' +
            'Movimenti entrata collegati: ' + (data.movimenti_entrata_collegati || 0) + ', gia collegati: ' + (data.movimenti_entrata_gia_collegati || 0) + ', conflitti: ' + (data.movimenti_entrata_conflitti || 0) + '. ' +
            'Studenti gia nostri marcati interni: ' + (data.interni_marcati_da_gestore || 0) + '. ' +
            'Token nuovi generati: ' + data.generated_tokens + '.' +
            iscrizioniTerzeImportList('Nuove pratiche create', data.inserted_details) +
            iscrizioniTerzeImportList('Pratiche aggiornate - prime 40', data.updated_details) +
            iscrizioniTerzeImportList('Movimenti in entrata agganciati', data.movimenti_entrata_collegati_details) +
            iscrizioniTerzeImportList('Movimenti in entrata gia collegati ad altra pratica', data.movimenti_entrata_conflitti_details) +
            iscrizioniTerzeImportList('Dati aggiuntivi aggiornati', data.additional_updated_details) +
            iscrizioniTerzeImportList('Dati aggiuntivi non agganciati/ignorati', data.additional_ignored_details) +
            iscrizioniTerzeImportList('DSA standalone aggiornati', data.dsa_updated_details) +
            iscrizioniTerzeImportList('DSA standalone non agganciati', data.dsa_ignored_details) +
            iscrizioniTerzeImportList('Certificazioni scuola non agganciate', data.school_attr_unmatched_examples);
        iscrizioniTerzeLoadTable();
    })
    .catch(error => {
        result.className = 'alert alert-danger';
        result.textContent = error.message;
    });
});

document.getElementById('iscrizioni_terze_draft_subject_form').addEventListener('submit', function (event) {
    event.preventDefault();
    const result = document.getElementById('iscrizioni_terze_result');
    result.className = 'alert alert-info';
    result.style.display = 'block';
    result.textContent = 'Salvataggio oggetto bozza in corso...';

    fetch('iscrizioniPrimeDraftSubjectSave.php', {
        method: 'POST',
        body: new FormData(event.target),
        credentials: 'same-origin'
    })
        .then(response => response.json().then(data => ({ok: response.ok, data})))
        .then(resultData => {
            if (!resultData.ok || !resultData.data.ok) {
                throw new Error(resultData.data.message || 'Errore salvataggio oggetto bozza');
            }
            result.className = 'alert alert-success';
            result.textContent = resultData.data.message || 'Oggetto bozza salvato.';
        })
        .catch(error => {
            result.className = 'alert alert-danger';
            result.textContent = error.message;
        });
});

document.addEventListener('DOMContentLoaded', function () {
    const filter = document.getElementById('iscrizioni_terze_filter');
    if (filter) {
        filter.addEventListener('input', iscrizioniTerzeRenderTable);
    }
});

iscrizioniTerzeLoadTable();
</script>
</body>
</html>
