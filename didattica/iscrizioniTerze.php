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
        SUM((email_genitore_1 IS NOT NULL OR email_genitore_2 IS NOT NULL) AND studente_interno = 0) AS esterni_con_email
    FROM iscrizioni_prime_pratiche
    WHERE tipo_iscrizione = 'terze'
");
$draftSubject = iscrizioniPrimeDraftSubject('terze');

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
        <button type="button" id="iscrizioni_mail_close" class="btn btn-primary" style="display:none;margin-top:16px;" onclick="iscrizioniTerzeHideMailOverlay()">Chiudi</button>
    </div>
</div>

<div class="container-fluid">
    <div class="panel panel-lightblue4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-folder-open"></span>&ensp;Iscrizioni future classi terze
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-2"><strong>Totale:</strong> <span id="stat_totale"><?php echo intval($stats['totale'] ?? 0); ?></span></div>
                <div class="col-md-2"><strong>Interni:</strong> <span id="stat_interni"><?php echo intval($stats['interni'] ?? 0); ?></span></div>
                <div class="col-md-2"><strong>Esterni:</strong> <span id="stat_esterni"><?php echo intval($stats['esterni'] ?? 0); ?></span></div>
                <div class="col-md-2"><strong>Domande inviate:</strong> <span id="stat_domande_inviate"><?php echo intval($stats['inviate'] ?? 0); ?></span></div>
                <div class="col-md-2"><strong>Mail reali:</strong> <span id="stat_mail_reali">0</span></div>
                <div class="col-md-2"><strong>Mail test:</strong> <span id="stat_mail_test">0</span></div>
            </div>
            <div class="alert alert-info" style="margin-top:14px;">
                Gli studenti gia presenti in GestOre vengono segnati come interni e non ricevono il link. La raccolta dati e documenti viene inviata solo agli studenti esterni.
            </div>
            <div class="row" style="margin-top:14px;">
                <div class="col-md-12">
                    <button type="button" class="btn btn-default" onclick="iscrizioniTerzeLoadTable()">
                        <span class="glyphicon glyphicon-refresh"></span> Aggiorna elenco
                    </button>
                    <a class="btn btn-success" href="iscrizioniPrimeLinkExport.php?tipo_iscrizione=terze" onclick="return confirm('Generare un nuovo token per tutte le pratiche esterne non chiuse e scaricare il CSV dei link? I link esportati in precedenza verranno sostituiti.');">
                        <span class="glyphicon glyphicon-envelope"></span> Esporta link esterni
                    </a>
                    <a class="btn btn-primary" href="iscrizioniPrimeDomande.php?tipo_iscrizione=terze">
                        <span class="glyphicon glyphicon-inbox"></span> Domande inviate
                    </a>
                    <a class="btn btn-default" href="iscrizioniContattiVariazioni.php?tipo_iscrizione=terze">
                        <span class="glyphicon glyphicon-transfer"></span> Variazioni contatti
                    </a>
                    <button type="button" class="btn btn-info" onclick="iscrizioniTerzeSendMail(1)">
                        <span class="glyphicon glyphicon-eye-open"></span> Simula invio mail esterni
                    </button>
                    <button type="button" class="btn btn-info" onclick="iscrizioniTerzeSendTestMail()">
                        <span class="glyphicon glyphicon-envelope"></span> Invia test mail esterni
                    </button>
                    <button type="button" class="btn btn-warning" onclick="iscrizioniTerzeSendMail(0)">
                        <span class="glyphicon glyphicon-send"></span> Invia prossimo lotto esterni
                    </button>
                    <button type="button" class="btn btn-danger" onclick="iscrizioniTerzeCorrectSentLinks(1)">
                        <span class="glyphicon glyphicon-search"></span> Simula controllo link inviati
                    </button>
                    <button type="button" class="btn btn-danger" onclick="iscrizioniTerzeCorrectSentLinks(0)">
                        <span class="glyphicon glyphicon-link"></span> Correggi link inviati
                    </button>
                    <button type="button" class="btn btn-danger" onclick="iscrizioniTerzeCheckBounce()">
                        <span class="glyphicon glyphicon-warning-sign"></span> Bounce
                    </button>
                    <a class="btn btn-default" href="iscrizioniPrimeMailBounceExport.php?tipo_iscrizione=terze&days=30">
                        <span class="glyphicon glyphicon-download-alt"></span> Esporta report bounce
                    </a>
                </div>
            </div>
            <div id="iscrizioni_terze_result" class="alert" style="display:none;margin-top:12px;"></div>
            <hr>
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
                        <input type="file" name="prime_csv" accept=".csv,text/csv" class="form-control" required>
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
                    <div class="col-sm-offset-3 col-sm-9">
                        <button type="submit" class="btn btn-primary">
                            <span class="glyphicon glyphicon-upload"></span> Importa pratiche terze
                        </button>
                    </div>
                </div>
            </form>
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
                            <th>Tipo</th>
                            <th>Stato</th>
                            <th>Email responsabili</th>
                            <th>Mail avviso</th>
                            <th>Token</th>
                            <th>Test</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="9" class="text-muted">Caricamento...</td></tr>
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

function iscrizioniTerzeEscape(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (char) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
    });
}

function iscrizioniTerzeSetText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value || 0;
}

function iscrizioniTerzeNumber(value) {
    return value === undefined || value === null || value === '' ? 0 : value;
}

function iscrizioniTerzeTipoEffettivo(row) {
    return Number(row.studente_interno_effettivo ?? row.studente_interno ?? 0) === 1 ? 'INTERNO' : 'ESTERNO';
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
        row.stato,
        row.email_genitore_1,
        row.email_genitore_2,
        row.telefono_genitore_1,
        row.telefono_genitore_2,
        row.token_last4,
        row.mail_diagnosi,
        row.scuola_provenienza,
        row.comune_residenza
    ].filter(Boolean).join(' '));
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
        if (row.stato) html += '<br><small>pratica ' + iscrizioniTerzeEscape(row.stato) + '</small>';
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
        tbody.innerHTML = '<tr><td colspan="9" class="text-muted">Nessuna pratica importata.</td></tr>';
        if (counter) counter.textContent = '';
        return;
    }

    if (!iscrizioniTerzeVisibleRows.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-muted">Nessuna pratica corrisponde al filtro.</td></tr>';
        if (counter) counter.textContent = '0 di ' + iscrizioniTerzeRows.length + ' pratiche';
        return;
    }

    tbody.innerHTML = iscrizioniTerzeVisibleRows.map(row => {
        const emails = [row.email_genitore_1, row.email_genitore_2].filter(Boolean).join('<br>');
        const isInternal = iscrizioniTerzeTipoEffettivo(row) === 'INTERNO';
        const token = isInternal ? '-' : (row.token_last4 ? ('...' + row.token_last4) : '<span class="text-danger">da esportare</span>');
        const tipo = isInternal
            ? '<span class="label label-default">interno</span>' + (row.classe_corrente_gestore ? '<br><small class="text-muted">' + iscrizioniTerzeEscape(row.classe_corrente_gestore) + '</small>' : '')
            : '<span class="label label-warning">esterno</span>' + (row.classe_corrente_gestore ? '<br><small class="text-muted">' + iscrizioniTerzeEscape(row.classe_corrente_gestore) + '</small>' : '');
        const testButton = isInternal
            ? '<span class="text-muted">non richiesto</span>'
            : '<button type="button" class="btn btn-xs btn-info" onclick="iscrizioniTerzeOpenTestLink(' + Number(row.id) + ')"><span class="glyphicon glyphicon-new-window"></span> Apri</button>';

        return '<tr>' +
            '<td><strong>' + iscrizioniTerzeEscape(row.cognome) + '</strong> ' + iscrizioniTerzeEscape(row.nome) + '</td>' +
            '<td>' + iscrizioniTerzeEscape(row.codice_fiscale) + '</td>' +
            '<td>' + iscrizioniTerzeEscape(row.corso_studi) + '</td>' +
            '<td>' + tipo + '</td>' +
            '<td>' + iscrizioniTerzeEscape(row.stato) + '</td>' +
            '<td>' + (emails || '<span class="text-danger">mancante</span>') + '</td>' +
            '<td>' + iscrizioniTerzeMailStatus(row) + '</td>' +
            '<td>' + token + '</td>' +
            '<td>' + testButton + '</td>' +
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
    const bar = document.getElementById('iscrizioni_mail_progress_bar');
    bar.className = 'iscrizioni-mail-progress-bar';
    bar.style.width = '100%';
    bar.style.background = ok ? 'linear-gradient(90deg, #22c55e, #16a34a)' : '#dc2626';
    document.getElementById('iscrizioni_mail_icon').style.background = ok ? '#16a34a' : '#dc2626';
    document.getElementById('iscrizioni_mail_icon').innerHTML = ok ? '<span class="glyphicon glyphicon-ok"></span>' : '<span class="glyphicon glyphicon-alert"></span>';
}

function iscrizioniTerzeHideMailOverlay() {
    document.getElementById('iscrizioni_mail_overlay').style.display = 'none';
}

function iscrizioniTerzeLoadTable() {
    const tbody = document.querySelector('#iscrizioni_terze_table tbody');
    tbody.innerHTML = '<tr><td colspan="9" class="text-muted">Caricamento...</td></tr>';

    fetch('iscrizioniPrimeRead.php?tipo_iscrizione=terze', {credentials: 'same-origin'})
        .then(response => response.json())
        .then(data => {
            if (!data.ok) throw new Error(data.message || 'Errore lettura pratiche');
            iscrizioniTerzeUpdateStats(data.stats, data.mail_stats);
            iscrizioniTerzeRows = data.rows || [];
            iscrizioniTerzeRenderTable();
        })
        .catch(error => {
            tbody.innerHTML = '<tr><td colspan="9" class="text-danger">' + iscrizioniTerzeEscape(error.message) + '</td></tr>';
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
    .then(response => response.json())
    .then(data => {
        if (!data.ok) throw new Error(data.message || 'Errore importazione');
        result.className = 'alert alert-success';
        result.innerHTML =
            'Import completato: ' +
            data.inserted + ' nuove, ' +
            data.updated + ' aggiornate. ' +
            'Interni: ' + data.interni + ', esterni: ' + data.esterni + '. ' +
            'Righe DSA: ' + data.dsa_rows + '. ' +
            'Contatti aggiornati: ' + data.contacts_updated + ', anagrafiche ignorate: ' + data.contacts_ignored + '. ' +
            'Interni non aggiornati: ' + (data.contacts_internal_skipped || 0) + '. ' +
            'Studenti gia nostri marcati interni: ' + (data.interni_marcati_da_gestore || 0) + '. ' +
            'Token nuovi generati: ' + data.generated_tokens + '.';
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
