<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

iscrizioniPrimeEnsureSchema();

$stats = dbGetFirst("
    SELECT
        COUNT(*) AS totale,
        SUM(stato = 'importata') AS importate,
        SUM(stato = 'bozza') AS bozze,
        SUM(stato = 'inviata') AS inviate,
        SUM(stato = 'verifica_iniziale_ok') AS verifica_iniziale_ok,
        SUM(stato = 'verificata') AS verificate,
        SUM(stato = 'annullata') AS annullate,
        SUM(tablet_scelto = 1) AS tablet_scelti,
        SUM(tablet_stato = 'confermato') AS tablet_confermati,
        SUM(tablet_stato = 'escluso') AS tablet_esclusi,
        SUM(tablet_stato = 'rinuncia') AS tablet_rinunce,
        SUM(tablet_stato = 'confermato' AND tablet_acquistato = 1) AS tablet_acquistati,
        SUM(tablet_stato = 'confermato' AND tablet_acquistato = 0) AS tablet_da_acquistare,
        SUM(email_genitore_1 IS NOT NULL OR email_genitore_2 IS NOT NULL) AS con_email
    FROM iscrizioni_prime_pratiche
    WHERE tipo_iscrizione = 'prime'
");
$draftSubject = iscrizioniPrimeDraftSubject('prime');

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <title>Iscrizioni prime</title>
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
        .tablet-badge { display:inline-block; padding:4px 8px; border-radius:999px; font-weight:800; font-size:12px; margin:0 3px 3px 0; }
        .tablet-badge-ok { background:#dcfce7; color:#166534; }
        .tablet-badge-wait { background:#fef3c7; color:#92400e; }
        .tablet-badge-out { background:#e5e7eb; color:#374151; }
        .tablet-badge-stop { background:#fee2e2; color:#991b1b; }
        .tablet-actions .btn { margin:0 2px 3px 0; }
        .tablet-actions select {
            max-width: 145px;
            height: 24px;
            padding: 2px 4px;
            margin: 0 2px 3px 0;
            display: inline-block;
        }
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
        .iscrizioni-table-tools {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-bottom: 12px;
        }
        .iscrizioni-table-tools input {
            width: min(460px, 100%);
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 10px;
        }
        .iscrizioni-table-tools select {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 10px;
            background: #fff;
        }
        .iscrizioni-filter-count {
            color: #64748b;
            font-weight: 700;
        }
        .cambio-scuola-modal {
            position: fixed;
            inset: 0;
            z-index: 9998;
            background: rgba(15, 23, 42, 0.62);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
        }
        .cambio-scuola-modal.open { display: flex; }
        .cambio-scuola-card {
            width: min(1180px, 100%);
            max-height: calc(100vh - 36px);
            overflow: auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 22px 56px rgba(0,0,0,.28);
        }
        .cambio-scuola-head {
            padding: 16px 18px;
            background: #7f1d1d;
            color: #fff;
            font-size: 20px;
            font-weight: 800;
        }
        .cambio-scuola-body { padding: 18px; }
        .cambio-scuola-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(360px, .95fr);
            gap: 16px;
            align-items: start;
        }
        .cambio-scuola-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .cambio-scuola-field label { display: block; font-weight: 700; margin-bottom: 5px; }
        .cambio-scuola-field input,
        .cambio-scuola-field select,
        .cambio-scuola-field textarea {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 9px 10px;
            background: #fff;
        }
        .cambio-scuola-field textarea { min-height: 120px; resize: vertical; }
        .cambio-scuola-wide { grid-column: 1 / -1; }
        .cambio-scuola-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding: 12px 18px;
            border-top: 1px solid #e5e7eb;
            background: #f8fafc;
        }
        .cambio-scuola-history {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px;
            background: #fff;
            max-height: calc(100vh - 210px);
            overflow: auto;
        }
        .cambio-scuola-event {
            border: 1px solid #dbe4ef;
            border-left: 5px solid #7f1d1d;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 8px;
            background: #f8fafc;
        }
        .cambio-scuola-event-head {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            flex-wrap: wrap;
            font-weight: 800;
        }
        .cambio-scuola-event-meta { color: #64748b; margin-top: 4px; }
        .cambio-scuola-event-note { margin-top: 6px; white-space: pre-wrap; }
        @media (max-width: 980px) {
            .cambio-scuola-layout { grid-template-columns: 1fr; }
            .cambio-scuola-history { max-height: none; }
        }
        @media (max-width: 760px) {
            .cambio-scuola-grid { grid-template-columns: 1fr; }
        }
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
        <button type="button" id="iscrizioni_mail_close" class="btn btn-primary" style="display:none;margin-top:16px;" onclick="iscrizioniPrimeHideMailOverlay()">Chiudi</button>
    </div>
</div>

<div id="cambio_scuola_modal" class="cambio-scuola-modal" aria-hidden="true">
    <div class="cambio-scuola-card" role="dialog" aria-modal="true" aria-labelledby="cambio_scuola_title">
        <div id="cambio_scuola_title" class="cambio-scuola-head">Cambio scuola</div>
        <form id="cambio_scuola_form" enctype="multipart/form-data">
            <div class="cambio-scuola-body">
                <input type="hidden" name="id" id="cambio_scuola_id">
                <p id="cambio_scuola_student" class="text-muted"></p>
                <div class="alert alert-warning">
                    Questa pratica verra' segnata come cambio scuola e non ricevera' piu comunicazioni automatiche per completare l'iscrizione.
                </div>
                <div class="cambio-scuola-layout">
                <div>
                    <h4>Nuovo aggiornamento</h4>
                    <div class="cambio-scuola-grid">
                    <div class="cambio-scuola-field">
                        <label for="cambio_scuola_richiesta_data">Data richiesta</label>
                        <input type="date" name="richiesta_data" id="cambio_scuola_richiesta_data">
                    </div>
                    <div class="cambio-scuola-field">
                        <label for="cambio_scuola_canale">Richiesta arrivata via</label>
                        <select name="canale" id="cambio_scuola_canale">
                            <option value="mail">Mail</option>
                            <option value="telefono">Telefono</option>
                            <option value="presenza">Di persona</option>
                            <option value="altro">Altro</option>
                        </select>
                    </div>
                    <div class="cambio-scuola-field cambio-scuola-wide">
                        <label for="cambio_scuola_scuola_destinazione">Scuola di destinazione</label>
                        <input type="text" name="scuola_destinazione" id="cambio_scuola_scuola_destinazione" placeholder="Nome della scuola verso cui la famiglia intende trasferirsi">
                    </div>
                    <div class="cambio-scuola-field">
                        <label for="cambio_scuola_colloquio">Colloquio uscita</label>
                        <select name="colloquio_stato" id="cambio_scuola_colloquio">
                            <option value="da_valutare">Da valutare</option>
                            <option value="da_fare">Da fare</option>
                            <option value="fatto">Fatto</option>
                            <option value="non_necessario">Non necessario</option>
                        </select>
                    </div>
                    <div class="cambio-scuola-field">
                        <label for="cambio_scuola_nulla_osta">Nulla osta</label>
                        <select name="nulla_osta_stato" id="cambio_scuola_nulla_osta">
                            <option value="da_richiedere">Da richiedere</option>
                            <option value="richiesto">Richiesto dalla famiglia</option>
                            <option value="ricevuto">Ricevuto/in lavorazione</option>
                            <option value="evaso_inviato">Evaso / inviato</option>
                            <option value="non_necessario">Non necessario</option>
                        </select>
                    </div>
                    <div class="cambio-scuola-field">
                        <label for="cambio_scuola_documenti">Documenti pratica</label>
                        <select name="documenti_stato" id="cambio_scuola_documenti">
                            <option value="da_verificare">Da verificare</option>
                            <option value="manca_qualcosa">Manca qualcosa</option>
                            <option value="completi">Completi</option>
                        </select>
                    </div>
                    <div class="cambio-scuola-field">
                        <label for="cambio_scuola_pratica_stato">Stato pratica cambio scuola</label>
                        <select name="pratica_stato" id="cambio_scuola_pratica_stato">
                            <option value="aperta">Aperta</option>
                            <option value="in_attesa">In attesa</option>
                            <option value="completata">Completata</option>
                        </select>
                    </div>
                    <div class="cambio-scuola-field cambio-scuola-wide">
                        <label for="cambio_scuola_allegato">PDF collegato a questo aggiornamento</label>
                        <input type="file" name="allegato" id="cambio_scuola_allegato" accept="application/pdf,.pdf">
                        <div class="help-block">Puoi allegare, per esempio, la stampa PDF della mail ricevuta o inviata. Ogni salvataggio resta nello storico.</div>
                    </div>
                    <div class="cambio-scuola-field cambio-scuola-wide">
                        <label for="cambio_scuola_note">Note segreteria</label>
                        <textarea name="note" id="cambio_scuola_note" placeholder="Annota cosa e' stato comunicato, eventuali documenti mancanti, contatti con la famiglia o con la scuola di destinazione."></textarea>
                    </div>
                    </div>
                </div>
                    <div class="cambio-scuola-history">
                        <h4>Storico aggiornamenti</h4>
                        <div id="cambio_scuola_storico" class="text-muted">Nessun aggiornamento registrato.</div>
                    </div>
                </div>
                <div id="cambio_scuola_error" class="text-danger" style="margin-top:10px;" hidden></div>
            </div>
            <div class="cambio-scuola-actions">
                <button type="button" class="btn btn-default" onclick="iscrizioniPrimeCloseCambioScuola()">Annulla</button>
                <button type="submit" class="btn btn-danger">Salva cambio scuola</button>
            </div>
        </form>
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
                    <button type="button" class="btn btn-default btn-xs" onclick="iscrizioniPrimeFormatTextarea('custom_mail_message', 'bold')"><strong>B</strong></button>
                    <button type="button" class="btn btn-default btn-xs" onclick="iscrizioniPrimeFormatTextarea('custom_mail_message', 'ul')">Elenco puntato</button>
                    <button type="button" class="btn btn-default btn-xs" onclick="iscrizioniPrimeFormatTextarea('custom_mail_message', 'ol')">Elenco numerato</button>
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
            <button type="button" class="btn btn-default" onclick="iscrizioniPrimeCloseCustomMail()">Annulla</button>
            <button type="button" class="btn btn-primary" id="custom_mail_send_button" onclick="iscrizioniPrimeSendCustomMail()">Invia mail</button>
        </div>
    </div>
</div>

<div id="tablet_rinuncia_modal" class="custom-mail-modal" aria-hidden="true">
    <div class="custom-mail-card" role="dialog" aria-modal="true" aria-labelledby="tablet_rinuncia_title">
        <div id="tablet_rinuncia_title" class="custom-mail-head" style="background:#b45309;">Rinuncia classe tablet</div>
        <form id="tablet_rinuncia_form" enctype="multipart/form-data">
            <div class="custom-mail-body">
                <input type="hidden" name="id" id="tablet_rinuncia_id">
                <input type="hidden" name="action" value="rinuncia">
                <p id="tablet_rinuncia_student" class="text-muted"></p>
                <div class="alert alert-warning">
                    La rinuncia segna lo studente come non piu confermato e ripesca il primo escluso disponibile.
                </div>
                <div class="custom-mail-field">
                    <label>Destinatari conferma</label>
                    <div id="tablet_rinuncia_recipients" class="well well-sm" style="margin-bottom:0;"></div>
                    <label style="margin-top:8px;font-weight:600;">
                        <input type="checkbox" name="send_mail" id="tablet_rinuncia_send_mail" value="1" checked>
                        invia mail di conferma alla famiglia
                    </label>
                </div>
                <div class="custom-mail-field">
                    <label for="tablet_rinuncia_note">Messaggio interno segreteria</label>
                    <textarea name="note" id="tablet_rinuncia_note" placeholder="Annota data, canale e riferimento della richiesta ricevuta dalla famiglia."></textarea>
                </div>
                <div class="custom-mail-field">
                    <label for="tablet_rinuncia_allegato">PDF richiesta genitore</label>
                    <input type="file" name="allegato" id="tablet_rinuncia_allegato" accept="application/pdf,.pdf">
                    <div class="help-block">Allega la mail o la richiesta firmata salvata in PDF.</div>
                </div>
                <div class="custom-mail-field">
                    <label for="tablet_rinuncia_subject">Oggetto mail</label>
                    <input type="text" name="mail_subject" id="tablet_rinuncia_subject" value="Conferma rinuncia classe tablet">
                </div>
                <div class="custom-mail-field">
                    <label for="tablet_rinuncia_message">Testo mail</label>
                    <textarea name="mail_message" id="tablet_rinuncia_message"></textarea>
                </div>
                <div class="custom-mail-field">
                    <label for="tablet_rinuncia_signature">Firma</label>
                    <textarea name="mail_signature" id="tablet_rinuncia_signature" style="min-height:80px;">Segreteria didattica
ITT Buonarroti - Trento</textarea>
                </div>
                <div id="tablet_rinuncia_error" class="text-danger" style="margin-top:8px;" hidden></div>
            </div>
            <div class="custom-mail-actions">
                <button type="button" class="btn btn-default" onclick="iscrizioniPrimeCloseTabletRinuncia()">Annulla</button>
                <button type="submit" class="btn btn-warning" id="tablet_rinuncia_save_button">Registra rinuncia</button>
            </div>
        </form>
    </div>
</div>

<div class="container-fluid">
    <div class="panel panel-lightblue4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-folder-open"></span>&ensp;Iscrizioni future classi prime
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-2"><strong>Pratiche:</strong> <span id="stat_totale"><?php echo intval($stats['totale'] ?? 0); ?></span></div>
                <div class="col-md-2"><strong>Bozze:</strong> <span id="stat_bozze"><?php echo intval($stats['bozze'] ?? 0); ?></span></div>
                <div class="col-md-2"><strong>Domande inviate:</strong> <span id="stat_domande_inviate"><?php echo intval($stats['inviate'] ?? 0); ?></span></div>
                <div class="col-md-2"><strong>Con email:</strong> <span id="stat_con_email"><?php echo intval($stats['con_email'] ?? 0); ?></span></div>
                <div class="col-md-2"><strong>Cambio scuola:</strong> <span id="stat_annullate"><?php echo intval($stats['annullate'] ?? 0); ?></span></div>
                <div class="col-md-2"><strong>Mail reali:</strong> <span id="stat_mail_reali">0</span></div>
                <div class="col-md-2" style="margin-top:8px;"><strong>Mail test:</strong> <span id="stat_mail_test">0</span></div>
                <div class="col-md-2" style="margin-top:8px;"><strong>Tablet confermati:</strong> <span id="stat_tablet_confermati">0</span></div>
                <div class="col-md-2" style="margin-top:8px;"><strong>Tablet da acquistare:</strong> <span id="stat_tablet_da_acquistare">0</span></div>
                <div class="col-md-2" style="margin-top:8px;"><strong>Tablet esclusi:</strong> <span id="stat_tablet_esclusi">0</span></div>
            </div>
            <div class="row" style="margin-top:14px;">
                <div class="col-md-12">
                    <a class="btn btn-success" href="iscrizioniPrimeLinkExport.php" onclick="return confirm('Generare un nuovo token per tutte le pratiche non chiuse e scaricare il CSV dei link? I link esportati in precedenza verranno sostituiti.');">
                        <span class="glyphicon glyphicon-envelope"></span> Esporta link famiglie
                    </a>
                    <button type="button" class="btn btn-default" onclick="iscrizioniPrimeLoadTable()">
                        <span class="glyphicon glyphicon-refresh"></span> Aggiorna elenco
                    </button>
                    <a class="btn btn-primary" href="iscrizioniPrimeDomande.php">
                        <span class="glyphicon glyphicon-inbox"></span> Domande inviate
                    </a>
                    <a class="btn btn-default" href="iscrizioniContattiVariazioni.php?tipo_iscrizione=prime">
                        <span class="glyphicon glyphicon-transfer"></span> Variazioni contatti
                    </a>
                    <button type="button" class="btn btn-info" onclick="iscrizioniPrimeSendMail(1)">
                        <span class="glyphicon glyphicon-eye-open"></span> Simula invio mail
                    </button>
                    <button type="button" class="btn btn-info" onclick="iscrizioniPrimeSendTestMail()">
                        <span class="glyphicon glyphicon-envelope"></span> Invia test mail
                    </button>
                    <button type="button" class="btn btn-warning" onclick="iscrizioniPrimeSendMail(0)">
                        <span class="glyphicon glyphicon-send"></span> Invia prossimo lotto
                    </button>
                    <button type="button" class="btn btn-danger" onclick="iscrizioniPrimeCorrectSentLinks(1)">
                        <span class="glyphicon glyphicon-search"></span> Simula controllo link inviati
                    </button>
                    <button type="button" class="btn btn-danger" onclick="iscrizioniPrimeCorrectSentLinks(0)">
                        <span class="glyphicon glyphicon-link"></span> Correggi link inviati
                    </button>
                    <button type="button" class="btn btn-danger" onclick="iscrizioniPrimeCheckBounce()">
                        <span class="glyphicon glyphicon-warning-sign"></span> Bounce
                    </button>
                    <a class="btn btn-default" href="iscrizioniPrimeMailBounceExport.php?tipo_iscrizione=prime&days=30">
                        <span class="glyphicon glyphicon-download-alt"></span> Esporta report bounce
                    </a>
                </div>
            </div>
            <div id="iscrizioni_prime_result" class="alert" style="display:none;margin-top:12px;"></div>
            <hr>
            <button type="button" class="btn btn-default" onclick="iscrizioniPrimeToggleInitialTools()">
                <span id="iscrizioni_prime_initial_tools_icon" class="glyphicon glyphicon-chevron-down"></span>
                <span id="iscrizioni_prime_initial_tools_label">Mostra bozza Gmail e import CSV</span>
            </button>
            <div id="iscrizioni_prime_initial_tools" style="display:none;margin-top:14px;">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Bozza Gmail per invio mail</strong></div>
                <div class="panel-body">
                    <form id="iscrizioni_prime_draft_subject_form">
                        <input type="hidden" name="tipo_iscrizione" value="prime">
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
            <form id="iscrizioni_prime_import_form" class="form-horizontal" enctype="multipart/form-data">
                <input type="hidden" name="tipo_iscrizione" value="prime">
                <div class="form-group">
                    <label class="col-sm-3 control-label">CSV iscrizioni PRIME</label>
                    <div class="col-sm-9">
                        <input type="file" name="prime_csv" accept=".csv,text/csv" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">CSV SAA DSA PRIME</label>
                    <div class="col-sm-9">
                        <input type="file" name="dsa_csv" accept=".csv,text/csv" class="form-control">
                        <span class="help-block">Opzionale: puoi caricarlo anche in un secondo momento per aggiornare DSA/104.</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">CSV esame licenza media</label>
                    <div class="col-sm-9">
                        <input type="file" name="licenza_media_csv" accept=".csv,text/csv" class="form-control">
                        <span class="help-block">Opzionale: importa scuola media di provenienza, anno esame, esito e voto.</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">CSV anagrafica responsabili</label>
                    <div class="col-sm-9">
                        <input type="file" name="anagrafica_csv" accept=".csv,text/csv" class="form-control">
                        <span class="help-block">Opzionale: aggiorna email, telefoni e responsabili. Gli studenti non presenti nelle pratiche prime vengono ignorati.</span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-3 col-sm-9">
                        <button type="submit" class="btn btn-primary">
                            <span class="glyphicon glyphicon-upload"></span> Importa pratiche
                        </button>
                    </div>
                </div>
            </form>
            <hr>
            <form id="iscrizioni_prime_tablet_import_form" class="form-horizontal" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="col-sm-3 control-label">Excel classi tablet</label>
                    <div class="col-sm-9">
                        <input type="file" name="tablet_xlsx" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="form-control" required>
                        <span class="help-block">Importa confermati, Digital Science ed esclusi da TABLET.xlsx. L'elenco esclusi resta ordinato per ripescaggio.</span>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-3 col-sm-9">
                        <button type="submit" class="btn btn-primary">
                            <span class="glyphicon glyphicon-blackboard"></span> Importa tablet
                        </button>
                    </div>
                </div>
            </form>
            </div>
        </div>
    </div>

    <div class="panel panel-lima4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-list"></span>&ensp;Pratiche importate
        </div>
        <div class="panel-body">
            <div class="iscrizioni-table-tools">
                <input type="text" id="iscrizioni_prime_filter" placeholder="Filtra per nome, codice fiscale, corso, stato, email, tipo..." autocomplete="off">
                <select id="iscrizioni_prime_tablet_filter" title="Filtro tablet">
                    <option value="">Tablet: tutti</option>
                    <option value="classi_tablet">Classi tablet</option>
                    <option value="confermato">Confermato</option>
                    <option value="escluso">Escluso</option>
                    <option value="rinuncia">Rinuncia</option>
                    <option value="non_tablet">Non tablet</option>
                </select>
                <select id="iscrizioni_prime_acquisto_filter" title="Filtro acquisto tablet">
                    <option value="">Acquisto: tutti</option>
                    <option value="acquistato">Acquistato</option>
                    <option value="da_acquistare">Da acquistare</option>
                </select>
                <button type="button" class="btn btn-default" onclick="iscrizioniPrimeClearFilter()">
                    <span class="glyphicon glyphicon-remove"></span> Pulisci filtro
                </button>
                <button type="button" class="btn btn-success" onclick="iscrizioniPrimeExportFilteredCsv()">
                    <span class="glyphicon glyphicon-download-alt"></span> Esporta tabella filtrata
                </button>
                <span id="iscrizioni_prime_filter_count" class="iscrizioni-filter-count"></span>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-condensed" id="iscrizioni_prime_table">
                    <thead>
                        <tr>
                            <th>Studente</th>
                            <th>Tipo</th>
                            <th>Codice fiscale</th>
                            <th>Corso</th>
                            <th>Attributi</th>
                            <th>Tablet</th>
                            <th>Stato</th>
                            <th>Genitori</th>
                            <th>Email responsabili</th>
                            <th>Mail avviso</th>
                            <th>Token</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="12" class="text-muted">Caricamento...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
let iscrizioniPrimeMailProgressTimer = null;
let iscrizioniPrimeMailProgressValue = 0;
let iscrizioniPrimeRows = [];
let iscrizioniPrimeVisibleRows = [];

function iscrizioniPrimeEscape(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (char) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
    });
}

function iscrizioniPrimeReadJsonResponse(response) {
    return response.text().then(text => {
        const trimmed = String(text || '').trim();
        if (trimmed === '') {
            throw new Error('Risposta vuota dal server. Controlla il log PHP/Apache per iscrizioniPrimeRead.php.');
        }
        try {
            return JSON.parse(trimmed);
        } catch (e) {
            throw new Error('Risposta non JSON dal server: ' + trimmed.slice(0, 500));
        }
    });
}

function iscrizioniPrimeStatoLabel(stato) {
    const labels = {
        inviata: 'Inviata',
        verifica_iniziale_ok: 'Verifica iniziale OK',
        verificata: 'Pratica completata',
        da_integrare: 'Da integrare',
        annullata: 'Cambio scuola'
    };
    return labels[String(stato || '')] || String(stato || '');
}

function iscrizioniPrimeMovimentoStatoLabel(stato) {
    const labels = {
        reiscrizione_confermata: 'Reiscrizione confermata',
        chiusa: 'Chiusa',
        da_verificare: 'Da verificare'
    };
    return labels[String(stato || '')] || String(stato || '').replace(/_/g, ' ');
}

function iscrizioniPrimeFormatDateIt(value) {
    const text = String(value || '').trim();
    const match = text.match(/^(\d{4})-(\d{2})-(\d{2})/);
    return match ? (match[3] + '/' + match[2] + '/' + match[1]) : text;
}

function iscrizioniPrimeFormatDateTimeIt(value) {
    const text = String(value || '').trim();
    const match = text.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
    if (!match) return text;
    return match[3] + '/' + match[2] + '/' + match[1] + (match[4] ? ' ' + match[4] + ':' + match[5] : '');
}

function iscrizioniPrimeSetText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value || 0;
}

function iscrizioniPrimeNumber(value) {
    return value === undefined || value === null || value === '' ? 0 : value;
}

function iscrizioniPrimeToggleInitialTools() {
    const box = document.getElementById('iscrizioni_prime_initial_tools');
    const icon = document.getElementById('iscrizioni_prime_initial_tools_icon');
    const label = document.getElementById('iscrizioni_prime_initial_tools_label');
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

function iscrizioniPrimeFindRowById(id) {
    id = Number(id || 0);
    return iscrizioniPrimeRows.find(row => Number(row.id || 0) === id) || null;
}

function iscrizioniPrimeRenderCustomMailRecipients(row) {
    const box = document.getElementById('custom_mail_recipients');
    if (!box) return;
    const items = [];
    const seen = {};
    [['Genitore 1', row?.email_genitore_1 || ''], ['Genitore 2', row?.email_genitore_2 || '']].forEach(item => {
        const email = String(item[1] || '').trim().toLowerCase();
        if (!email || seen[email]) return;
        seen[email] = true;
        items.push('<label style="display:block;margin:4px 0;font-weight:600;">'
            + '<input type="checkbox" class="custom-mail-recipient" value="' + iscrizioniPrimeEscape(email) + '" checked> '
            + iscrizioniPrimeEscape(item[0]) + ' - ' + iscrizioniPrimeEscape(email)
            + '</label>');
    });
    box.innerHTML = items.length ? items.join('') : '<span class="text-danger">Nessuna email genitore presente nella pratica.</span>';
}

function iscrizioniPrimeOpenCustomMail(id) {
    const row = iscrizioniPrimeFindRowById(id);
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
    iscrizioniPrimeRenderCustomMailRecipients(row);
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

function iscrizioniPrimeCloseCustomMail() {
    const modal = document.getElementById('custom_mail_modal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.getElementById('custom_mail_id').value = '';
}

function iscrizioniPrimeFormatTextarea(id, mode) {
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

async function iscrizioniPrimeSendCustomMail() {
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
    const confirmed = await iscrizioniPrimeConfirmMailDialog(
        'Conferma invio mail',
        'La comunicazione sara inviata a ' + recipients.length + ' destinatari selezionati.',
        recipients.map(iscrizioniPrimeEscape).join('<br>')
    );
    if (!confirmed) {
        return;
    }
    iscrizioniPrimeShowMailOverlay('Invio mail in corso', 'GestOre sta inviando la comunicazione ai destinatari selezionati.');

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
        iscrizioniPrimeCompleteMailOverlay(
            true,
            'Mail inviata',
            payload.result.message || 'Comunicazione inviata.',
            'Destinatari selezionati: <strong>' + recipients.length + '</strong>'
        );
        iscrizioniPrimeCloseCustomMail();
    })
    .catch(err => {
        iscrizioniPrimeCompleteMailOverlay(false, 'Invio non riuscito', err.message, '');
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

function iscrizioniPrimeUpdateStats(stats, mailStats) {
    stats = stats || {};
    mailStats = mailStats || {};
    iscrizioniPrimeSetText('stat_totale', stats.totale || 0);
    iscrizioniPrimeSetText('stat_bozze', stats.bozze || 0);
    iscrizioniPrimeSetText('stat_domande_inviate', stats.domande_inviate || 0);
    iscrizioniPrimeSetText('stat_annullate', stats.annullate || 0);
    iscrizioniPrimeSetText('stat_con_email', stats.con_email || 0);
    iscrizioniPrimeSetText('stat_mail_reali', mailStats.mail_reali || 0);
    iscrizioniPrimeSetText('stat_mail_test', mailStats.mail_test || 0);
    iscrizioniPrimeSetText('stat_tablet_confermati', stats.tablet_confermati || 0);
    iscrizioniPrimeSetText('stat_tablet_da_acquistare', stats.tablet_da_acquistare || 0);
    iscrizioniPrimeSetText('stat_tablet_esclusi', stats.tablet_esclusi || 0);
}

function iscrizioniPrimeMailStatus(row) {
    const real = Number(row.mail_reali || 0);
    const test = Number(row.mail_test || 0);
    const bounce = Number(row.mail_bounce || 0);
    let html = '';
    if (bounce > 0) {
        html += '<span class="mail-badge mail-badge-bounce">Bounce</span>';
        if (row.bounce_reason) html += '<br><small>' + iscrizioniPrimeEscape(row.bounce_reason) + '</small>';
        if (row.last_bounced_at) html += '<br><small>' + iscrizioniPrimeEscape(row.last_bounced_at) + '</small>';
    } else if (real > 0) {
        html += '<span class="mail-badge mail-badge-real">Reale inviata</span>';
        if (row.last_real_sent_at) html += '<br><small>' + iscrizioniPrimeEscape(row.last_real_sent_at) + '</small>';
    } else if (test > 0) {
        html += '<span class="mail-badge mail-badge-test">Test inviato</span>';
        if (row.last_test_sent_at) html += '<br><small>' + iscrizioniPrimeEscape(row.last_test_sent_at) + '</small>';
    } else if (Number(row.mail_pending || 0) <= 0 && row.mail_diagnosi) {
        html += '<span class="mail-badge mail-badge-skip">Non richiesta</span>';
        html += '<br><small>' + iscrizioniPrimeEscape(row.mail_diagnosi) + '</small>';
    } else if (!['importata', 'bozza', 'da_integrare'].includes(String(row.stato || '').toLowerCase())) {
        html += '<span class="mail-badge mail-badge-skip">Non richiesta</span>';
        if (row.stato) html += '<br><small>pratica ' + iscrizioniPrimeEscape(iscrizioniPrimeStatoLabel(row.stato)) + '</small>';
    } else {
        html += '<span class="mail-badge mail-badge-none">Da inviare</span>';
    }
    if (real > 1 || test > 1) {
        html += '<br><small>reali ' + real + ' / test ' + test + '</small>';
    }
    return html;
}

function iscrizioniPrimeStatoHtml(row) {
    let html = iscrizioniPrimeEscape(iscrizioniPrimeStatoLabel(row.stato));
    if (String(row.stato || '') === 'annullata' && row.cambio_scuola_pratica_stato) {
        html += '<br><span class="mail-badge mail-badge-skip">Cambio scuola: ' + iscrizioniPrimeEscape(row.cambio_scuola_pratica_stato) + '</span>';
        if (row.cambio_scuola_richiesta_data) {
            html += '<br><small>Richiesta ' + iscrizioniPrimeEscape(iscrizioniPrimeFormatDateIt(row.cambio_scuola_richiesta_data)) + '</small>';
        }
    }
    return html;
}

function iscrizioniPrimeTipoHtml(row) {
    const interno = Number(row.studente_interno_effettivo || 0) === 1;
    const classeCorrente = row.classe_corrente_gestore ? '<br><small class="text-muted">classe attuale: ' + iscrizioniPrimeEscape(row.classe_corrente_gestore) + '</small>' : '';
    const reiscrizione = Number(row.movimento_reiscrizione_id || 0) > 0
        ? '<br><span class="mail-badge mail-badge-real">Reiscrizione: ' + iscrizioniPrimeEscape(iscrizioniPrimeMovimentoStatoLabel(row.movimento_reiscrizione_stato)) + '</span>'
        : '';
    return interno
        ? '<span class="label label-warning">INTERNO</span><br><small class="text-muted">gia nostro</small>' + classeCorrente + reiscrizione
        : '<span class="label label-success">ESTERNO</span><br><small class="text-muted">nuovo studente</small>' + classeCorrente + reiscrizione;
}

function iscrizioniPrimeTipoLabel(row) {
    return Number(row.studente_interno_effettivo || 0) === 1 ? 'INTERNO' : 'ESTERNO';
}

function iscrizioniPrimeAttributiHtml(row) {
    const attrs = Array.isArray(row.attributi_riservati) ? row.attributi_riservati : [];
    if (!attrs.length) {
        return '<span class="text-muted">-</span>';
    }
    return attrs.map(attr => {
        const source = attr.fonte ? '<span class="stud-attr-source">' + iscrizioniPrimeEscape(attr.fonte) + '</span>' : '';
        return '<span class="stud-attr-badge" title="' + iscrizioniPrimeEscape(attr.codice || '') + '">' + iscrizioniPrimeEscape(attr.label || attr.codice || '') + source + '</span>';
    }).join(' ');
}

function iscrizioniPrimeTabletLabel(status) {
    const labels = {
        '': 'Non indicato',
        richiesto: 'Richiesto',
        confermato: 'Confermato',
        escluso: 'Escluso',
        rinuncia: 'Rinuncia'
    };
    return labels[status || ''] || status;
}

function iscrizioniPrimeTabletGroupLabel(group) {
    const labels = {
        ipad: 'Classi tablet',
        digital_science: 'Digital Science'
    };
    return labels[group || ''] || group || '';
}

function iscrizioniPrimeTabletHtml(row) {
    const chosen = Number(row.tablet_scelto || 0) === 1;
    const status = String(row.tablet_stato || '');
    const cls = status === 'confermato' ? 'tablet-badge-ok' : (status === 'escluso' ? 'tablet-badge-out' : (status === 'rinuncia' ? 'tablet-badge-stop' : 'tablet-badge-wait'));
    let html = chosen
        ? '<span class="tablet-badge ' + cls + '">' + iscrizioniPrimeEscape(iscrizioniPrimeTabletLabel(status)) + '</span>'
        : '<span class="text-muted">Non tablet</span>';
    if (row.tablet_gruppo) html += '<br><small>' + iscrizioniPrimeEscape(iscrizioniPrimeTabletGroupLabel(row.tablet_gruppo)) + '</small>';
    if (row.tablet_posizione) html += '<br><small>pos. ' + iscrizioniPrimeEscape(row.tablet_posizione) + '</small>';
    if (status === 'confermato') {
        html += '<br>' + (Number(row.tablet_acquistato || 0) === 1
            ? '<span class="tablet-badge tablet-badge-ok">Acquistato</span>'
            : '<span class="tablet-badge tablet-badge-wait">Da acquistare</span>');
    }
    if (row.tablet_ripescato_da_pratica_id) html += '<br><small>ripescato</small>';
    if (row.tablet_rinuncia_allegato_original_name) html += '<br><small>PDF: ' + iscrizioniPrimeEscape(row.tablet_rinuncia_allegato_original_name).slice(0, 45) + '</small>';
    if (row.tablet_note) html += '<br><small>' + iscrizioniPrimeEscape(row.tablet_note).slice(0, 90) + '</small>';
    html += '<div class="tablet-actions">';
    html += '<select class="form-control input-sm" onchange="iscrizioniPrimeTabletManualStatus(' + Number(row.id) + ', this.value, &quot;' + iscrizioniPrimeEscape(row.tablet_gruppo || '') + '&quot;)">';
    [
        ['0|', 'Non tablet'],
        ['1|richiesto', 'Richiesto'],
        ['1|confermato', 'Confermato'],
        ['1|escluso', 'Escluso'],
        ['1|rinuncia', 'Rinuncia']
    ].forEach(item => {
        const selected = (chosen ? '1|' + status : '0|') === item[0] ? ' selected' : '';
        html += '<option value="' + item[0] + '"' + selected + '>' + item[1] + '</option>';
    });
    html += '</select>';
    if (status === 'confermato') {
        if (Number(row.tablet_acquistato || 0) === 1) {
            html += '<button type="button" class="btn btn-xs btn-default" onclick="iscrizioniPrimeTabletAction(' + Number(row.id) + ', &quot;non_acquistato&quot;)">No acquisto</button>';
        } else {
            html += '<button type="button" class="btn btn-xs btn-success" onclick="iscrizioniPrimeTabletAction(' + Number(row.id) + ', &quot;acquistato&quot;)">Acquistato</button>';
        }
        html += '<button type="button" class="btn btn-xs btn-warning" onclick="iscrizioniPrimeOpenTabletRinuncia(' + Number(row.id) + ')">Rinuncia</button>';
    }
    html += '</div>';
    return html;
}

function iscrizioniPrimeNormalizeSearch(value) {
    return String(value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

function iscrizioniPrimeRowSearchText(row) {
    return iscrizioniPrimeNormalizeSearch([
        row.cognome,
        row.nome,
        iscrizioniPrimeTipoLabel(row),
        row.codice_fiscale,
        row.corso_studi,
        row.stato,
        row.email_genitore_1,
        row.email_genitore_2,
        row.responsabile_1_cognome,
        row.responsabile_1_nome,
        row.responsabile_2_cognome,
        row.responsabile_2_nome,
        row.telefono_genitore_1,
        row.telefono_genitore_2,
        row.token_last4,
        row.mail_diagnosi,
        iscrizioniPrimeTabletLabel(row.tablet_stato || ''),
        iscrizioniPrimeTabletGroupLabel(row.tablet_gruppo || ''),
        row.tablet_posizione,
        row.tablet_acquistato ? 'tablet acquistato' : '',
        row.tablet_note,
        (Array.isArray(row.attributi_riservati) ? row.attributi_riservati.map(attr => attr.label + ' ' + attr.codice + ' ' + (attr.fonte || '')).join(' ') : ''),
        row.cambio_scuola_pratica_stato,
        row.cambio_scuola_canale,
        row.cambio_scuola_scuola_destinazione,
        row.cambio_scuola_richiesta_data
    ].filter(Boolean).join(' '));
}

function iscrizioniPrimeFilteredRows() {
    const filter = document.getElementById('iscrizioni_prime_filter');
    const tabletFilter = document.getElementById('iscrizioni_prime_tablet_filter');
    const acquistoFilter = document.getElementById('iscrizioni_prime_acquisto_filter');
    const terms = iscrizioniPrimeNormalizeSearch(filter ? filter.value : '').trim().split(/\s+/).filter(Boolean);
    return iscrizioniPrimeRows.filter(row => {
        const tabletValue = tabletFilter ? tabletFilter.value : '';
        if (tabletValue === 'classi_tablet' && !(Number(row.tablet_scelto || 0) === 1 && row.tablet_gruppo === 'ipad' && row.tablet_stato === 'confermato')) return false;
        if (tabletValue === 'non_tablet' && Number(row.tablet_scelto || 0) === 1) return false;
        if (['confermato', 'escluso', 'rinuncia'].includes(tabletValue) && row.tablet_stato !== tabletValue) return false;
        const acquistoValue = acquistoFilter ? acquistoFilter.value : '';
        if (acquistoValue === 'acquistato' && !(row.tablet_stato === 'confermato' && Number(row.tablet_acquistato || 0) === 1)) return false;
        if (acquistoValue === 'da_acquistare' && !(row.tablet_stato === 'confermato' && Number(row.tablet_acquistato || 0) !== 1)) return false;
        if (!terms.length) return true;
        const text = iscrizioniPrimeRowSearchText(row);
        return terms.every(term => text.includes(term));
    });
}

function iscrizioniPrimeRenderTable() {
    const tbody = document.querySelector('#iscrizioni_prime_table tbody');
    const counter = document.getElementById('iscrizioni_prime_filter_count');
    iscrizioniPrimeVisibleRows = iscrizioniPrimeFilteredRows();

    if (!iscrizioniPrimeRows.length) {
        tbody.innerHTML = '<tr><td colspan="12" class="text-muted">Nessuna pratica importata.</td></tr>';
        if (counter) counter.textContent = '';
        return;
    }

    if (!iscrizioniPrimeVisibleRows.length) {
        tbody.innerHTML = '<tr><td colspan="12" class="text-muted">Nessuna pratica corrisponde al filtro.</td></tr>';
        if (counter) counter.textContent = '0 di ' + iscrizioniPrimeRows.length + ' pratiche';
        return;
    }

    tbody.innerHTML = iscrizioniPrimeVisibleRows.map(row => {
        const emails = [row.email_genitore_1, row.email_genitore_2].filter(Boolean).join('<br>');
        const parents = [
            [row.responsabile_1_cognome, row.responsabile_1_nome].filter(Boolean).join(' '),
            [row.responsabile_2_cognome, row.responsabile_2_nome].filter(Boolean).join(' ')
        ].filter(Boolean).map(iscrizioniPrimeEscape).join('<br>');
        const token = row.token_last4 ? ('...' + row.token_last4) : '<span class="text-danger">da esportare</span>';

        return '<tr>' +
            '<td><strong>' + iscrizioniPrimeEscape(row.cognome) + '</strong> ' + iscrizioniPrimeEscape(row.nome) + '</td>' +
            '<td>' + iscrizioniPrimeTipoHtml(row) + '</td>' +
            '<td>' + iscrizioniPrimeEscape(row.codice_fiscale) + '</td>' +
            '<td>' + iscrizioniPrimeEscape(row.corso_studi) + '</td>' +
            '<td>' + iscrizioniPrimeAttributiHtml(row) + '</td>' +
            '<td>' + iscrizioniPrimeTabletHtml(row) + '</td>' +
            '<td>' + iscrizioniPrimeStatoHtml(row) + '</td>' +
            '<td>' + (parents || '<span class="text-muted">-</span>') + '</td>' +
            '<td>' + (emails || '<span class="text-danger">mancante</span>') + '</td>' +
            '<td>' + iscrizioniPrimeMailStatus(row) + '</td>' +
            '<td>' + token + '</td>' +
            '<td>' +
                '<button type="button" class="btn btn-xs btn-info" onclick="iscrizioniPrimeOpenTestLink(' + Number(row.id) + ')"><span class="glyphicon glyphicon-new-window"></span> Apri</button> ' +
                '<button type="button" class="btn btn-xs btn-primary" onclick="iscrizioniPrimeOpenCustomMail(' + Number(row.id) + ')"><span class="glyphicon glyphicon-envelope"></span> Scrivi</button> ' +
                '<button type="button" class="btn btn-xs btn-danger" onclick="iscrizioniPrimeOpenCambioScuola(' + Number(row.id) + ')"><span class="glyphicon glyphicon-transfer"></span> Cambio scuola</button>' +
            '</td>' +
            '</tr>';
    }).join('');

    if (counter) {
        counter.textContent = iscrizioniPrimeVisibleRows.length + ' di ' + iscrizioniPrimeRows.length + ' pratiche';
    }
}

function iscrizioniPrimeClearFilter() {
    const filter = document.getElementById('iscrizioni_prime_filter');
    if (filter) {
        filter.value = '';
        filter.focus();
    }
    const tabletFilter = document.getElementById('iscrizioni_prime_tablet_filter');
    const acquistoFilter = document.getElementById('iscrizioni_prime_acquisto_filter');
    if (tabletFilter) tabletFilter.value = '';
    if (acquistoFilter) acquistoFilter.value = '';
    iscrizioniPrimeRenderTable();
}

function iscrizioniPrimeCsvCell(value) {
    const text = String(value ?? '').replace(/\r?\n/g, ' ').trim();
    return '"' + text.replace(/"/g, '""') + '"';
}

function iscrizioniPrimeExportFilteredCsv() {
    const rows = iscrizioniPrimeVisibleRows.length || !iscrizioniPrimeRows.length
        ? iscrizioniPrimeVisibleRows
        : iscrizioniPrimeFilteredRows();
    if (!rows.length) {
        alert('Nessuna riga da esportare.');
        return;
    }

    const header = [
        'Studente',
        'Tipo',
        'Codice fiscale',
        'Corso',
        'Attributi',
        'Tablet stato',
        'Tablet gruppo',
        'Tablet posizione',
        'Tablet acquistato',
        'Tablet note',
        'Stato',
        'Genitore 1',
        'Genitore 2',
        'Email responsabile 1',
        'Email responsabile 2',
        'Mail reali',
        'Mail test',
        'Bounce',
        'Token',
        'Cambio scuola data',
        'Cambio scuola destinazione',
        'Cambio scuola stato'
    ];
    const lines = [header.map(iscrizioniPrimeCsvCell).join(';')];
    rows.forEach(row => {
        lines.push([
            ((row.cognome || '') + ' ' + (row.nome || '')).trim(),
            iscrizioniPrimeTipoLabel(row),
            row.codice_fiscale,
            row.corso_studi,
            Array.isArray(row.attributi_riservati) ? row.attributi_riservati.map(attr => attr.label || attr.codice || '').join(', ') : '',
            iscrizioniPrimeTabletLabel(row.tablet_stato || ''),
            iscrizioniPrimeTabletGroupLabel(row.tablet_gruppo || ''),
            row.tablet_posizione || '',
            Number(row.tablet_acquistato || 0) === 1 ? 'si' : 'no',
            row.tablet_note || '',
            row.stato,
            [row.responsabile_1_cognome, row.responsabile_1_nome].filter(Boolean).join(' '),
            [row.responsabile_2_cognome, row.responsabile_2_nome].filter(Boolean).join(' '),
            row.email_genitore_1,
            row.email_genitore_2,
            row.mail_reali || 0,
            row.mail_test || 0,
            row.mail_bounce || 0,
            row.token_last4 ? '...' + row.token_last4 : '',
            row.cambio_scuola_richiesta_data || '',
            row.cambio_scuola_scuola_destinazione || '',
            row.cambio_scuola_pratica_stato || ''
        ].map(iscrizioniPrimeCsvCell).join(';'));
    });

    const blob = new Blob(['\ufeff' + lines.join('\r\n')], {type: 'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    const now = new Date().toISOString().slice(0, 10);
    a.href = url;
    a.download = 'iscrizioni_prime_filtrate_' + now + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function iscrizioniPrimeCambioScuolaLabel(value) {
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

function iscrizioniPrimeRenderCambioScuolaStorico(praticaId, eventi) {
    const box = document.getElementById('cambio_scuola_storico');
    if (!box) return;
    if (!eventi || !eventi.length) {
        box.innerHTML = '<span class="text-muted">Nessun aggiornamento registrato.</span>';
        return;
    }
    box.innerHTML = eventi.map((evento, index) => {
        const allegato = evento.allegato_path
            ? '<a class="btn btn-xs btn-primary" target="_blank" rel="noopener" href="iscrizioniPrimeCambioScuolaAllegato.php?id=' + encodeURIComponent(praticaId) + '&evento_id=' + encodeURIComponent(evento.id) + '"><span class="glyphicon glyphicon-file"></span> Apri PDF</a> <span class="text-muted">' + iscrizioniPrimeEscape(evento.allegato_original_name || '') + '</span>'
            : '<span class="text-muted">Nessun PDF allegato a questo aggiornamento</span>';
        const undo = index === 0 && Number(evento.id || 0) > 0
            ? '<button type="button" class="btn btn-xs btn-danger pull-right" onclick="iscrizioniPrimeUndoCambioScuolaLast(' + Number(praticaId) + ')"><span class="glyphicon glyphicon-repeat"></span> Annulla ultimo aggiornamento</button>'
            : '';
        return '<div class="cambio-scuola-event">' +
            '<div class="cambio-scuola-event-head">' +
                '<span>' + iscrizioniPrimeEscape(iscrizioniPrimeFormatDateTimeIt(evento.created_at || '')) + '</span>' +
                '<span>' + iscrizioniPrimeEscape(evento.created_by || '') + '</span>' +
            '</div>' +
            '<div class="cambio-scuola-event-meta">' +
                'Richiesta: ' + iscrizioniPrimeEscape(evento.richiesta_data ? iscrizioniPrimeFormatDateIt(evento.richiesta_data) : '-') +
                ' &middot; Canale: ' + iscrizioniPrimeEscape(iscrizioniPrimeCambioScuolaLabel(evento.canale)) +
                ' &middot; Destinazione: ' + iscrizioniPrimeEscape(evento.scuola_destinazione || '-') +
                ' &middot; Colloquio: ' + iscrizioniPrimeEscape(iscrizioniPrimeCambioScuolaLabel(evento.colloquio_stato)) +
                ' &middot; Nulla osta: ' + iscrizioniPrimeEscape(iscrizioniPrimeCambioScuolaLabel(evento.nulla_osta_stato)) +
                ' &middot; Documenti: ' + iscrizioniPrimeEscape(iscrizioniPrimeCambioScuolaLabel(evento.documenti_stato)) +
                ' &middot; Stato: ' + iscrizioniPrimeEscape(iscrizioniPrimeCambioScuolaLabel(evento.pratica_stato)) +
            '</div>' +
            (evento.note ? '<div class="cambio-scuola-event-note">' + iscrizioniPrimeEscape(evento.note) + '</div>' : '') +
            '<div style="margin-top:8px;">' + allegato + undo + '<div style="clear:both;"></div></div>' +
        '</div>';
    }).join('');
}

function iscrizioniPrimeUndoCambioScuolaLast(praticaId) {
    if (!confirm("Vuoi annullare l'ultimo aggiornamento del cambio scuola? L'eventuale PDF collegato a quell'aggiornamento verra cancellato.")) {
        return;
    }
    const error = document.getElementById('cambio_scuola_error');
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
        iscrizioniPrimeOpenCambioScuola(praticaId);
        iscrizioniPrimeLoadTable();
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

function iscrizioniPrimeBounceDetails(data) {
    const totals = data.totals || {};
    let html =
        'Messaggi controllati: <strong>' + iscrizioniPrimeEscape(iscrizioniPrimeNumber(totals.checked)) + '</strong>' +
        ' &middot; collegati a pratiche: <strong>' + iscrizioniPrimeEscape(iscrizioniPrimeNumber(totals.matched)) + '</strong>' +
        ' &middot; non collegati: <strong>' + iscrizioniPrimeEscape(iscrizioniPrimeNumber(totals.unmatched)) + '</strong>' +
        '<br>Limite invio: <strong>' + iscrizioniPrimeEscape(iscrizioniPrimeNumber(totals.quota_limit)) + '</strong>' +
        ' &middot; indirizzo errato: <strong>' + iscrizioniPrimeEscape(iscrizioniPrimeNumber(totals.invalid_recipient)) + '</strong>' +
        ' &middot; casella piena: <strong>' + iscrizioniPrimeEscape(iscrizioniPrimeNumber(totals.mailbox_full)) + '</strong>' +
        ' &middot; altro: <strong>' + iscrizioniPrimeEscape(iscrizioniPrimeNumber(totals.other_bounce)) + '</strong>';

    if (data.export_url) {
        html += '<br><a class="btn btn-sm btn-default" style="margin-top:10px;" href="' + iscrizioniPrimeEscape(data.export_url) + '"><span class="glyphicon glyphicon-download-alt"></span> Scarica report CSV</a>';
    }
    return html;
}

function iscrizioniPrimeCheckBounce() {
    const result = document.getElementById('iscrizioni_prime_result');
    const formData = new FormData();
    formData.append('tipo_iscrizione', 'prime');
    formData.append('max', '60');

    result.className = 'alert alert-info';
    result.style.display = 'block';
    result.textContent = 'Controllo bounce in corso...';
    iscrizioniPrimeShowMailOverlay('Controllo bounce', 'GestOre sta leggendo le notifiche di mancata consegna nelle caselle iscrizioni.');

    fetch('iscrizioniPrimeMailBounceCheck.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        const details = iscrizioniPrimeBounceDetails(data);
        result.className = data.ok ? 'alert alert-success' : 'alert alert-warning';
        result.innerHTML = details;
        iscrizioniPrimeCompleteMailOverlay(
            !!data.ok,
            data.ok ? 'Controllo bounce completato' : 'Controllo completato con avvisi',
            data.ok ? 'Le mail rimbalzate trovate sono state segnate nelle pratiche.' : 'Alcuni account non sono stati controllati correttamente.',
            details
        );
        iscrizioniPrimeLoadTable();
    })
    .catch(error => {
        result.className = 'alert alert-danger';
        result.textContent = error.message;
        iscrizioniPrimeCompleteMailOverlay(false, 'Controllo bounce non riuscito', error.message, '');
    });
}

function iscrizioniPrimeTabletAction(id, action) {
    const result = document.getElementById('iscrizioni_prime_result');
    const data = new FormData();
    data.append('id', id);
    data.append('action', action);
    data.append('note', '');
    result.className = 'alert alert-info';
    result.style.display = 'block';
    result.textContent = 'Aggiornamento tablet in corso...';
    fetch('iscrizioniPrimeTabletSave.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
    .then(response => response.json().then(payload => ({ok: response.ok, payload})))
    .then(({ok, payload}) => {
        if (!ok || !payload.ok) {
            throw new Error(payload.message || 'Aggiornamento tablet non riuscito.');
        }
        result.className = 'alert alert-success';
        let html = iscrizioniPrimeEscape(payload.message || 'Aggiornamento tablet completato.');
        if (payload.replacement && payload.replacement.id) {
            html += '<br><strong>Ripescato da avvisare:</strong> ' +
                iscrizioniPrimeEscape((payload.replacement.cognome || '') + ' ' + (payload.replacement.nome || '')) +
                ' - ' + iscrizioniPrimeEscape(payload.replacement.codice_fiscale || '') +
                '. Usa il pulsante Scrivi sulla sua pratica per inviare la mail.';
        }
        result.innerHTML = html;
        iscrizioniPrimeLoadTable();
    })
    .catch(error => {
        result.className = 'alert alert-danger';
        result.textContent = error.message;
    });
}

function iscrizioniPrimeTabletManualStatus(id, packed, group) {
    const parts = String(packed || '').split('|');
    const data = new FormData();
    data.append('id', id);
    data.append('action', 'stato');
    data.append('tablet_scelto', parts[0] === '1' ? '1' : '0');
    data.append('tablet_stato', parts[1] || '');
    data.append('tablet_gruppo', group || 'ipad');
    data.append('note', '');
    const result = document.getElementById('iscrizioni_prime_result');
    result.className = 'alert alert-info';
    result.style.display = 'block';
    result.textContent = 'Aggiornamento stato tablet...';
    fetch('iscrizioniPrimeTabletSave.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
    .then(response => response.json().then(payload => ({ok: response.ok, payload})))
    .then(({ok, payload}) => {
        if (!ok || !payload.ok) throw new Error(payload.message || 'Aggiornamento stato tablet non riuscito.');
        result.className = 'alert alert-success';
        result.textContent = payload.message || 'Stato tablet aggiornato.';
        iscrizioniPrimeLoadTable();
    })
    .catch(error => {
        result.className = 'alert alert-danger';
        result.textContent = error.message;
        iscrizioniPrimeLoadTable();
    });
}

function iscrizioniPrimeOpenTabletRinuncia(id) {
    const row = iscrizioniPrimeRows.find(item => Number(item.id) === Number(id));
    if (!row) return;
    const modal = document.getElementById('tablet_rinuncia_modal');
    const form = document.getElementById('tablet_rinuncia_form');
    const error = document.getElementById('tablet_rinuncia_error');
    form.reset();
    error.hidden = true;
    error.textContent = '';
    document.getElementById('tablet_rinuncia_id').value = row.id;
    const student = ((row.cognome || '') + ' ' + (row.nome || '')).trim();
    document.getElementById('tablet_rinuncia_student').textContent = 'Pratica di ' + student;
    document.getElementById('tablet_rinuncia_message').value =
        'Gentili genitori,\n\nconfermiamo di aver registrato la rinuncia alla classe tablet per ' + student + '.\n\nLa pratica e stata aggiornata dalla segreteria didattica.';
    iscrizioniPrimeRenderTabletRecipients(row);
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
}

function iscrizioniPrimeRenderTabletRecipients(row) {
    const box = document.getElementById('tablet_rinuncia_recipients');
    const items = [];
    const seen = {};
    [
        ['Genitore 1', row.email_genitore_1],
        ['Genitore 2', row.email_genitore_2]
    ].forEach(item => {
        const email = String(item[1] || '').trim().toLowerCase();
        if (!email || seen[email]) return;
        seen[email] = true;
        items.push('<label style="display:block;font-weight:600;"><input type="checkbox" name="recipients[]" value="' + iscrizioniPrimeEscape(email) + '" checked> ' + iscrizioniPrimeEscape(item[0] + ' - ' + email) + '</label>');
    });
    box.innerHTML = items.length ? items.join('') : '<span class="text-danger">Nessuna email genitore presente nella pratica.</span>';
}

function iscrizioniPrimeCloseTabletRinuncia() {
    const modal = document.getElementById('tablet_rinuncia_modal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
}

function iscrizioniPrimeShowMailOverlay(title, text) {
    if (iscrizioniPrimeMailProgressTimer) {
        clearInterval(iscrizioniPrimeMailProgressTimer);
    }
    iscrizioniPrimeMailProgressValue = 3;
    document.getElementById('iscrizioni_mail_overlay').style.display = 'flex';
    document.getElementById('iscrizioni_mail_title').textContent = title;
    document.getElementById('iscrizioni_mail_text').textContent = text;
    document.getElementById('iscrizioni_mail_percent').textContent = iscrizioniPrimeMailProgressValue + '%';
    document.getElementById('iscrizioni_mail_details').textContent = '';
    document.getElementById('iscrizioni_mail_close').style.display = 'none';
    document.getElementById('iscrizioni_mail_confirm_actions').style.display = 'none';
    document.querySelector('.iscrizioni-mail-progress').style.display = '';
    document.getElementById('iscrizioni_mail_percent').style.display = '';
    const bar = document.getElementById('iscrizioni_mail_progress_bar');
    bar.className = 'iscrizioni-mail-progress-bar';
    bar.style.width = iscrizioniPrimeMailProgressValue + '%';
    document.getElementById('iscrizioni_mail_icon').style.background = '#0ea5e9';
    document.getElementById('iscrizioni_mail_icon').innerHTML = '<span class="glyphicon glyphicon-send"></span>';

    iscrizioniPrimeMailProgressTimer = setInterval(function () {
        if (iscrizioniPrimeMailProgressValue < 70) {
            iscrizioniPrimeMailProgressValue += 4;
        } else if (iscrizioniPrimeMailProgressValue < 90) {
            iscrizioniPrimeMailProgressValue += 1;
        }
        document.getElementById('iscrizioni_mail_percent').textContent = iscrizioniPrimeMailProgressValue + '%';
        bar.style.width = iscrizioniPrimeMailProgressValue + '%';
    }, 900);
}

function iscrizioniPrimeConfirmMailDialog(title, text, details) {
    if (iscrizioniPrimeMailProgressTimer) {
        clearInterval(iscrizioniPrimeMailProgressTimer);
        iscrizioniPrimeMailProgressTimer = null;
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
                iscrizioniPrimeHideMailOverlay();
            }
            resolve(result);
        };
        cancel.onclick = () => cleanup(false);
        confirm.onclick = () => cleanup(true);
    });
}

function iscrizioniPrimeCompleteMailOverlay(ok, title, text, details) {
    if (iscrizioniPrimeMailProgressTimer) {
        clearInterval(iscrizioniPrimeMailProgressTimer);
        iscrizioniPrimeMailProgressTimer = null;
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
    bar.style.width = ok ? '100%' : '100%';
    bar.style.background = ok ? 'linear-gradient(90deg, #22c55e, #16a34a)' : '#dc2626';
    document.getElementById('iscrizioni_mail_icon').style.background = ok ? '#16a34a' : '#dc2626';
    document.getElementById('iscrizioni_mail_icon').innerHTML = ok ? '<span class="glyphicon glyphicon-ok"></span>' : '<span class="glyphicon glyphicon-alert"></span>';
}

function iscrizioniPrimeHideMailOverlay() {
    document.getElementById('iscrizioni_mail_overlay').style.display = 'none';
    document.getElementById('iscrizioni_mail_confirm_actions').style.display = 'none';
    document.querySelector('.iscrizioni-mail-progress').style.display = '';
    document.getElementById('iscrizioni_mail_percent').style.display = '';
}

function iscrizioniPrimeLoadTable() {
    const tbody = document.querySelector('#iscrizioni_prime_table tbody');
    tbody.innerHTML = '<tr><td colspan="12" class="text-muted">Caricamento...</td></tr>';

    fetch('iscrizioniPrimeRead.php?tipo_iscrizione=prime', {credentials: 'same-origin'})
        .then(iscrizioniPrimeReadJsonResponse)
        .then(data => {
            if (!data.ok) {
                throw new Error(data.message || 'Errore lettura pratiche');
            }
            iscrizioniPrimeUpdateStats(data.stats, data.mail_stats);
            iscrizioniPrimeRows = data.rows || [];
            iscrizioniPrimeRenderTable();
        })
        .catch(error => {
            tbody.innerHTML = '<tr><td colspan="12" class="text-danger">' + iscrizioniPrimeEscape(error.message) + '</td></tr>';
        });
}

document.addEventListener('DOMContentLoaded', function () {
    const filter = document.getElementById('iscrizioni_prime_filter');
    if (filter) {
        filter.addEventListener('input', iscrizioniPrimeRenderTable);
    }
    const tabletFilter = document.getElementById('iscrizioni_prime_tablet_filter');
    const acquistoFilter = document.getElementById('iscrizioni_prime_acquisto_filter');
    if (tabletFilter) tabletFilter.addEventListener('change', iscrizioniPrimeRenderTable);
    if (acquistoFilter) acquistoFilter.addEventListener('change', iscrizioniPrimeRenderTable);
});

document.getElementById('tablet_rinuncia_form').addEventListener('submit', function (event) {
    event.preventDefault();
    const error = document.getElementById('tablet_rinuncia_error');
    const button = document.getElementById('tablet_rinuncia_save_button');
    const result = document.getElementById('iscrizioni_prime_result');
    const data = new FormData(this);
    if (document.getElementById('tablet_rinuncia_send_mail').checked && String(data.get('mail_message') || '').trim() === '') {
        error.textContent = 'Inserisci il testo della mail di conferma rinuncia.';
        error.hidden = false;
        return;
    }
    error.hidden = true;
    error.textContent = '';
    button.disabled = true;
    button.textContent = 'Salvataggio...';
    result.className = 'alert alert-info';
    result.style.display = 'block';
    result.textContent = 'Registrazione rinuncia tablet...';

    fetch('iscrizioniPrimeTabletSave.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
    .then(response => response.json().then(payload => ({ok: response.ok, payload})))
    .then(({ok, payload}) => {
        if (!ok || !payload.ok) throw new Error(payload.message || 'Registrazione rinuncia non riuscita.');
        iscrizioniPrimeCloseTabletRinuncia();
        result.className = 'alert alert-success';
        let html = iscrizioniPrimeEscape(payload.message || 'Rinuncia tablet registrata.');
        if (payload.mail) {
            html += '<br>Mail conferma: ' + iscrizioniPrimeEscape(payload.mail.message || (payload.mail.ok ? 'inviata' : 'non inviata'));
        }
        if (payload.replacement && payload.replacement.id) {
            html += '<br><strong>Ripescato da avvisare:</strong> ' +
                iscrizioniPrimeEscape((payload.replacement.cognome || '') + ' ' + (payload.replacement.nome || '')) +
                ' - ' + iscrizioniPrimeEscape(payload.replacement.codice_fiscale || '') + '.';
        }
        result.innerHTML = html;
        iscrizioniPrimeLoadTable();
    })
    .catch(err => {
        error.textContent = err.message;
        error.hidden = false;
        result.className = 'alert alert-danger';
        result.textContent = err.message;
    })
    .finally(() => {
        button.disabled = false;
        button.textContent = 'Registra rinuncia';
    });
});

function iscrizioniPrimeOpenCambioScuola(id) {
    const modal = document.getElementById('cambio_scuola_modal');
    const form = document.getElementById('cambio_scuola_form');
    const error = document.getElementById('cambio_scuola_error');
    if (form) form.reset();
    if (error) {
        error.hidden = true;
        error.textContent = '';
    }
    document.getElementById('cambio_scuola_id').value = id;
    document.getElementById('cambio_scuola_student').textContent = 'Caricamento dati pratica...';
    iscrizioniPrimeRenderCambioScuolaStorico(id, []);
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');

    fetch('iscrizioniPrimeCambioScuolaRead.php?id=' + encodeURIComponent(id), {credentials: 'same-origin'})
        .then(response => response.json())
        .then(data => {
            if (!data.ok) throw new Error(data.message || 'Errore lettura cambio scuola.');
            const pratica = data.pratica || {};
            const record = data.record || {};
            document.getElementById('cambio_scuola_student').textContent = 'Pratica di ' + (pratica.cognome || '') + ' ' + (pratica.nome || '') + ' - stato attuale: ' + (pratica.stato || '');
            document.getElementById('cambio_scuola_richiesta_data').value = record.richiesta_data || '';
            document.getElementById('cambio_scuola_canale').value = record.canale || 'mail';
            document.getElementById('cambio_scuola_scuola_destinazione').value = record.scuola_destinazione || '';
            document.getElementById('cambio_scuola_colloquio').value = record.colloquio_stato || 'da_valutare';
            document.getElementById('cambio_scuola_nulla_osta').value = record.nulla_osta_stato || 'da_richiedere';
            document.getElementById('cambio_scuola_documenti').value = record.documenti_stato || 'da_verificare';
            document.getElementById('cambio_scuola_pratica_stato').value = record.pratica_stato || 'aperta';
            document.getElementById('cambio_scuola_note').value = '';
            iscrizioniPrimeRenderCambioScuolaStorico(id, data.eventi || []);
        })
        .catch(err => {
            if (error) {
                error.textContent = err.message;
                error.hidden = false;
            }
        });
}

function iscrizioniPrimeCloseCambioScuola() {
    const modal = document.getElementById('cambio_scuola_modal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
}

document.getElementById('cambio_scuola_form').addEventListener('submit', function (event) {
    event.preventDefault();
    const error = document.getElementById('cambio_scuola_error');
    const button = this.querySelector('button[type="submit"]');
    const data = new FormData(this);
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
        iscrizioniPrimeCloseCambioScuola();
        iscrizioniPrimeLoadTable();
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

function iscrizioniPrimeOpenTestLink(id) {
    const formData = new FormData();
    formData.append('id', id);

    fetch('iscrizioniPrimeTestLink.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (!data.ok) {
            throw new Error(data.message || 'Errore generazione link');
        }
        window.open(data.link, '_blank', 'noopener');
        iscrizioniPrimeLoadTable();
    })
    .catch(error => alert(error.message));
}

function iscrizioniPrimeSendMail(dryRun) {
    const result = document.getElementById('iscrizioni_prime_result');

    if (!dryRun && !confirm('Inviare ora il prossimo lotto di mail alle famiglie usando gli account configurati?')) {
        return;
    }

    const formData = new FormData();
    formData.append('dry_run', dryRun ? '1' : '0');
    formData.append('tipo_iscrizione', 'prime');

    result.className = 'alert alert-info';
    result.style.display = 'block';
    result.textContent = dryRun ? 'Simulazione invio in corso...' : 'Invio mail in corso...';
    iscrizioniPrimeShowMailOverlay(
        dryRun ? 'Simulazione invio mail' : 'Invio lotto mail',
        dryRun ? 'GestOre sta calcolando quali mail verrebbero inviate.' : 'GestOre sta inviando il prossimo lotto. Tieni aperta questa pagina fino alla conferma finale.'
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
            iscrizioniPrimeEscape(data.message || '') +
            '<br>Mail ' + (dryRun ? 'simulabili' : 'inviate') + ': ' + iscrizioniPrimeEscape(iscrizioniPrimeNumber(data.sent)) +
            ' - saltate: ' + iscrizioniPrimeEscape(iscrizioniPrimeNumber(data.skipped)) +
            (data.remaining !== undefined ? ' - restanti: ' + iscrizioniPrimeEscape(iscrizioniPrimeNumber(data.remaining)) : '') +
            (data.errors && data.errors.length ? '<br>Errori: ' + data.errors.map(iscrizioniPrimeEscape).join(', ') : '');
        iscrizioniPrimeCompleteMailOverlay(
            !!data.ok,
            data.ok ? (dryRun ? 'Simulazione completata' : 'Lotto completato') : 'Lotto completato con avvisi',
            data.message || '',
            'Mail ' + (dryRun ? 'simulabili' : 'inviate') + ': <strong>' + iscrizioniPrimeEscape(iscrizioniPrimeNumber(data.sent)) + '</strong>' +
            ' &middot; Saltate: <strong>' + iscrizioniPrimeEscape(iscrizioniPrimeNumber(data.skipped)) + '</strong>' +
            (data.remaining !== undefined ? ' &middot; Restanti: <strong>' + iscrizioniPrimeEscape(iscrizioniPrimeNumber(data.remaining)) + '</strong>' : '') +
            (data.errors && data.errors.length ? '<br>Errori: ' + data.errors.map(iscrizioniPrimeEscape).join(', ') : '')
        );
        iscrizioniPrimeLoadTable();
    })
    .catch(error => {
        result.className = 'alert alert-danger';
        result.textContent = error.message;
        iscrizioniPrimeCompleteMailOverlay(false, 'Invio non riuscito', error.message, '');
    });
}

function iscrizioniPrimeCorrectSentLinks(dryRun) {
    const result = document.getElementById('iscrizioni_prime_result');

    if (!dryRun && !confirm('Controllare le mail gia inviate in Gmail e reinviare il link corretto solo alle famiglie che hanno ricevuto un link non piu valido?')) {
        return;
    }

    const formData = new FormData();
    formData.append('dry_run', dryRun ? '1' : '0');
    formData.append('tipo_iscrizione', 'prime');

    result.className = 'alert alert-info';
    result.style.display = 'block';
    result.textContent = dryRun ? 'Controllo link inviati in corso...' : 'Correzione link inviati in corso...';
    iscrizioniPrimeShowMailOverlay(
        dryRun ? 'Simulazione controllo link' : 'Correzione link inviati',
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
                    iscrizioniPrimeEscape(item.studente || '') +
                    (item.codice_fiscale ? ' - ' + iscrizioniPrimeEscape(item.codice_fiscale) : '') +
                    ' - ' + iscrizioniPrimeEscape(item.recipient_email || '') +
                    (item.account_email ? ' <span class="text-muted">(' + iscrizioniPrimeEscape(item.account_email) + ')</span>' : '') +
                    '</li>'
                ).join('') +
                '</ul></div>'
            : '';
        const warningDetails = data.warnings && data.warnings.length
            ? '<br><div style="margin-top:10px;"><strong>Avvisi Gmail:</strong><ul style="margin-top:6px;">' +
                data.warnings.map(item => '<li>' + iscrizioniPrimeEscape(item) + '</li>').join('') +
                '</ul></div>'
            : '';
        result.className = data.ok ? 'alert alert-success' : 'alert alert-warning';
        result.innerHTML =
            iscrizioniPrimeEscape(data.message || '') +
            '<br>Mail Gmail controllate: ' + iscrizioniPrimeEscape(iscrizioniPrimeNumber(data.checked)) +
            '<br>Correzioni ' + (dryRun ? 'simulabili' : 'inviate') + ': ' + iscrizioniPrimeEscape(iscrizioniPrimeNumber(data.sent)) +
            ' - saltate: ' + iscrizioniPrimeEscape(iscrizioniPrimeNumber(data.skipped)) +
            (data.remaining !== undefined ? ' - restanti: ' + iscrizioniPrimeEscape(iscrizioniPrimeNumber(data.remaining)) : '') +
            (data.errors && data.errors.length ? '<br>Errori: ' + data.errors.map(iscrizioniPrimeEscape).join(', ') : '') +
            warningDetails +
            correctionDetails;
        iscrizioniPrimeCompleteMailOverlay(
            !!data.ok,
            data.ok ? (dryRun ? 'Controllo completato' : 'Correzione completata') : 'Correzione completata con avvisi',
            data.message || '',
            'Mail Gmail controllate: <strong>' + iscrizioniPrimeEscape(iscrizioniPrimeNumber(data.checked)) + '</strong>' +
            '<br>Correzioni ' + (dryRun ? 'simulabili' : 'inviate') + ': <strong>' + iscrizioniPrimeEscape(iscrizioniPrimeNumber(data.sent)) + '</strong>' +
            ' &middot; Saltate: <strong>' + iscrizioniPrimeEscape(iscrizioniPrimeNumber(data.skipped)) + '</strong>' +
            (data.remaining !== undefined ? ' &middot; Restanti: <strong>' + iscrizioniPrimeEscape(iscrizioniPrimeNumber(data.remaining)) + '</strong>' : '') +
            (data.errors && data.errors.length ? '<br>Errori: ' + data.errors.map(iscrizioniPrimeEscape).join(', ') : '') +
            warningDetails +
            correctionDetails
        );
        iscrizioniPrimeLoadTable();
    })
    .catch(error => {
        result.className = 'alert alert-danger';
        result.textContent = error.message;
        iscrizioniPrimeCompleteMailOverlay(false, 'Controllo link non riuscito', error.message, '');
    });
}

function iscrizioniPrimeSendTestMail() {
    const result = document.getElementById('iscrizioni_prime_result');
    result.className = 'alert alert-info';
    result.style.display = 'block';
    result.textContent = 'Invio mail di test in corso...';
    result.scrollIntoView({behavior: 'smooth', block: 'center'});

    fetch('iscrizioniPrimeMailTest.php', {
        method: 'POST',
        body: (() => { const fd = new FormData(); fd.append('tipo_iscrizione', 'prime'); return fd; })(),
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
        result.innerHTML = iscrizioniPrimeEscape(data.message || '') +
            (data.to ? '<br>Inviata a: ' + iscrizioniPrimeEscape(data.to) : '') +
            (data.original_recipient ? '<br>Destinatario reale simulato: ' + iscrizioniPrimeEscape(data.original_recipient) : '') +
            (data.student ? '<br>Studente: ' + iscrizioniPrimeEscape(data.student) : '');
        iscrizioniPrimeLoadTable();
    })
    .catch(error => {
        result.className = 'alert alert-danger';
        result.textContent = error.message;
        result.scrollIntoView({behavior: 'smooth', block: 'center'});
    });
}

document.getElementById('iscrizioni_prime_draft_subject_form').addEventListener('submit', function (event) {
    event.preventDefault();
    const result = document.getElementById('iscrizioni_prime_result');
    result.className = 'alert alert-info';
    result.style.display = 'block';
    result.textContent = 'Salvataggio oggetto bozza in corso...';
    fetch('iscrizioniPrimeDraftSubjectSave.php', { method: 'POST', body: new FormData(event.target), credentials: 'same-origin' })
        .then(response => response.json().then(data => ({ok: response.ok, data})))
        .then(resultData => {
            if (!resultData.ok || !resultData.data.ok) throw new Error(resultData.data.message || 'Errore salvataggio oggetto bozza');
            result.className = 'alert alert-success';
            result.textContent = resultData.data.message || 'Oggetto bozza salvato.';
        })
        .catch(error => {
            result.className = 'alert alert-danger';
            result.textContent = error.message;
        });
});

document.getElementById('iscrizioni_prime_import_form').addEventListener('submit', function (event) {
    event.preventDefault();

    const result = document.getElementById('iscrizioni_prime_result');
    result.className = 'alert alert-info';
    result.style.display = 'block';
    result.textContent = 'Importazione in corso...';

    fetch('iscrizioniPrimeImport.php', {
        method: 'POST',
        body: new FormData(event.target),
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (!data.ok) {
            throw new Error(data.message || 'Errore importazione');
        }

        result.className = 'alert alert-success';
        result.innerHTML =
            'Import completato: ' +
            data.inserted + ' nuove, ' +
            data.updated + ' aggiornate. ' +
            'Righe PRIME: ' + data.prime_rows + ', righe DSA: ' + data.dsa_rows + '. ' +
            'Righe licenza media: ' + (data.licenza_media_rows || 0) + '. ' +
            'Contatti aggiornati: ' + data.contacts_updated + ', anagrafiche ignorate: ' + data.contacts_ignored + '. ' +
            'Studenti gia nostri marcati interni: ' + (data.interni_marcati_da_gestore || 0) + '. ' +
            'Token nuovi generati: ' + data.generated_tokens + '.';
        iscrizioniPrimeLoadTable();
    })
    .catch(error => {
        result.className = 'alert alert-danger';
        result.textContent = error.message;
    });
});

document.getElementById('iscrizioni_prime_tablet_import_form').addEventListener('submit', function (event) {
    event.preventDefault();

    const result = document.getElementById('iscrizioni_prime_result');
    result.className = 'alert alert-info';
    result.style.display = 'block';
    result.textContent = 'Import tablet in corso...';

    fetch('iscrizioniPrimeTabletImport.php', {
        method: 'POST',
        body: new FormData(event.target),
        credentials: 'same-origin'
    })
    .then(response => response.json().then(data => ({ok: response.ok, data})))
    .then(({ok, data}) => {
        if (!ok || !data.ok) {
            throw new Error(data.message || 'Errore import tablet');
        }
        result.className = 'alert alert-success';
        let html =
            iscrizioniPrimeEscape(data.message || 'Import tablet completato.') +
            '<br>Righe lette: <strong>' + iscrizioniPrimeEscape(data.rows || 0) + '</strong>' +
            ' &middot; agganciate: <strong>' + iscrizioniPrimeEscape(data.matched || 0) + '</strong>' +
            ' &middot; confermati: <strong>' + iscrizioniPrimeEscape(data.confirmed || 0) + '</strong>' +
            ' &middot; esclusi: <strong>' + iscrizioniPrimeEscape(data.excluded || 0) + '</strong>' +
            ' &middot; Digital Science: <strong>' + iscrizioniPrimeEscape(data.digital_science || 0) + '</strong>';
        if (Array.isArray(data.unmatched) && data.unmatched.length) {
            html += '<br><strong>Non agganciati:</strong><br>' + data.unmatched.slice(0, 20).map(iscrizioniPrimeEscape).join('<br>');
            if (data.unmatched.length > 20) html += '<br>Altri: ' + (data.unmatched.length - 20);
        }
        result.innerHTML = html;
        iscrizioniPrimeLoadTable();
    })
    .catch(error => {
        result.className = 'alert alert-danger';
        result.textContent = error.message;
    });
});

iscrizioniPrimeLoadTable();
</script>

</body>
</html>
