<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/send-mail.php';
require_once '../common/mail-ui.php';
require_once '../common/__Log.php';
require_once '../common/__Settings.php';

ruoloRichiesto('dirigente', 'segreteria-ata');

header('Content-Type: application/json; charset=utf-8');

global $__con;
$MAIL_TEST_OVERRIDE = '';

function hMail($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function fmtDateITMail(?string $ymd): string
{
    $ymd = trim((string)$ymd);
    if ($ymd === '') return '';
    $dt = DateTime::createFromFormat('Y-m-d', $ymd);
    if ($dt) return $dt->format('d/m/Y');
    $ts = strtotime($ymd);
    return $ts ? date('d/m/Y', $ts) : $ymd;
}

function buildFerieEsitoMailHtml($nomeCompleto, $sottotipo, $statoRichiesta, array $giorniRows, $noteRichiedente, $notaApprovatore, $toName = ''): string
{
    $statoRichiesta = strtoupper(trim((string)$statoRichiesta));

    $headerTitle = 'ESITO RICHIESTA FERIE';
    $intro = 'La tua richiesta ferie è stata aggiornata in <b>GestOre</b>.';
    $footer = 'Messaggio automatico da <b>GestOre</b>.';
    $badgeHtml = badge('AGGIORNATA', '#dbeafe', '#1e3a8a');
    $theme = 'warning';
    $esitoLabel = 'Aggiornata';

    if ($statoRichiesta === 'APPROVATO') {
        $headerTitle = 'FERIE APPROVATE';
        $intro = 'La tua richiesta ferie è stata <b>approvata</b>.';
        $footer = 'Messaggio automatico da <b>GestOre</b>.';
        $badgeHtml = badge('APPROVATA', '#dcfce7', '#14532d');
        $esitoLabel = 'Approvata';
    } elseif ($statoRichiesta === 'RESPINTO') {
        $headerTitle = 'FERIE RESPINTE';
        $intro = 'La tua richiesta ferie è stata <b>respinta</b>.';
        $footer = 'Messaggio automatico da <b>GestOre</b>.';
        $badgeHtml = badge('RESPINTA', '#fee2e2', '#7f1d1d');
        $theme = 'annullamento';
        $esitoLabel = 'Respinta';
    } elseif ($statoRichiesta === 'PARZIALE') {
        $headerTitle = 'FERIE PARZIALMENTE AGGIORNATE';
        $intro = 'La tua richiesta ferie è stata <b>aggiornata parzialmente</b>: alcuni giorni risultano approvati o respinti, altri possono essere ancora in attesa.';
        $footer = 'Messaggio automatico da <b>GestOre</b>. Verifica il dettaglio della richiesta.';
        $badgeHtml = badge('PARZIALE', '#fef3c7', '#92400e');
        $theme = 'warning';
        $esitoLabel = 'Parziale';
    }

    $rowsHtml = '';
    $cntRichiesti = 0;
    $cntApprovati = 0;
    $cntRespinti = 0;

    foreach ($giorniRows as $r) {
        $det = [];
        if (!empty($r['dettagli_json'])) {
            $tmp = json_decode($r['dettagli_json'], true);
            if (is_array($tmp)) {
                $det = $tmp;
            }
        }

        $sg = strtoupper((string)($det['stato_giorno'] ?? 'RICHIESTO'));
        if ($sg === 'APPROVATO') $cntApprovati++;
        elseif ($sg === 'RESPINTO') $cntRespinti++;
        else $cntRichiesti++;

        $label = 'Richiesto';
        if ($sg === 'APPROVATO') $label = 'Approvato';
        elseif ($sg === 'RESPINTO') $label = 'Respinto';

        $rowsHtml .= '
          <tr>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9;font-weight:800;">' . hMail(fmtDateITMail($r['data_dal'] ?? '')) . '</td>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9;">' . hMail($label) . '</td>
          </tr>';
    }

    $content = '
      <div style="margin:0 0 12px 0;">
        ' . $badgeHtml . '
      </div>

      <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:12px 12px;margin:0 0 14px 0;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
          ' . kvRow('Dipendente', $nomeCompleto) . '
          ' . kvRow('Tipologia ferie', $sottotipo) . '
          ' . kvRow('Esito', $esitoLabel) . '
          ' . kvRow('Giorni approvati', (string)$cntApprovati) . '
          ' . kvRow('Giorni respinti', (string)$cntRespinti) . '
          ' . kvRow('Giorni in attesa', (string)$cntRichiesti) . '
        </table>
      </div>

      <div style="margin-top:14px;">
        <div style="font-weight:900;font-size:14px;margin:0 0 8px 0;">Dettaglio giorni</div>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
          <thead style="background:#f8fafc;">
            <tr>
              <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;font-size:12.5px;color:#6b7280;">Data</th>
              <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;font-size:12.5px;color:#6b7280;">Stato giorno</th>
            </tr>
          </thead>
          <tbody>' . $rowsHtml . '</tbody>
        </table>
      </div>
    ';

    if (trim((string)$noteRichiedente) !== '') {
        $content .= '
          <div style="margin-top:12px;padding:12px;border:1px solid #e5e7eb;border-radius:14px;background:#ffffff;">
            <div style="font-weight:800;color:#111827;margin-bottom:6px;">Note del richiedente</div>
            <div style="font-size:13.5px;line-height:1.55;color:#374151;">' . nl2br(hMail($noteRichiedente)) . '</div>
          </div>
        ';
    }

    if (trim((string)$notaApprovatore) !== '') {
        $content .= '
          <div style="margin-top:12px;padding:12px;border:1px solid #e5e7eb;border-radius:14px;background:#ffffff;">
            <div style="font-weight:800;color:#111827;margin-bottom:6px;">Nota della segreteria</div>
            <div style="font-size:13.5px;line-height:1.55;color:#374151;">' . nl2br(hMail($notaApprovatore)) . '</div>
          </div>
        ';
    }

    return mailWrap(
        $headerTitle,
        $toName !== '' ? $toName : $nomeCompleto,
        $intro,
        $content,
        $footer,
        $theme
    );
}

function buildFerieEsitoSegreteriaMailHtml($nomeCompleto, $emailUtente, $sottotipo, $statoRichiesta, array $giorniRows, $noteRichiedente, $notaApprovatore, $toName = ''): string
{
    $statoRichiesta = strtoupper(trim((string)$statoRichiesta));

    $headerTitle = 'ESITO RICHIESTA FERIE';
    $intro = 'Una richiesta ferie è stata aggiornata in <b>GestOre</b>.';
    $footer = 'Messaggio automatico da <b>GestOre</b>.';
    $badgeHtml = badge('AGGIORNATA', '#dbeafe', '#1e3a8a');
    $theme = 'warning';
    $esitoLabel = 'Aggiornata';

    if ($statoRichiesta === 'APPROVATO') {
        $headerTitle = 'FERIE APPROVATE';
        $intro = 'La richiesta ferie è stata <b>approvata</b> dalla segreteria.';
        $badgeHtml = badge('APPROVATA', '#dcfce7', '#14532d');
        $esitoLabel = 'Approvata';
    } elseif ($statoRichiesta === 'RESPINTO') {
        $headerTitle = 'FERIE RESPINTE';
        $intro = 'La richiesta ferie è stata <b>respinta</b> dalla segreteria.';
        $badgeHtml = badge('RESPINTA', '#fee2e2', '#7f1d1d');
        $theme = 'annullamento';
        $esitoLabel = 'Respinta';
    } elseif ($statoRichiesta === 'PARZIALE') {
        $headerTitle = 'FERIE AGGIORNATE PARZIALMENTE';
        $intro = 'La richiesta ferie è stata <b>aggiornata parzialmente</b> dalla segreteria.';
        $badgeHtml = badge('PARZIALE', '#fef3c7', '#92400e');
        $theme = 'warning';
        $esitoLabel = 'Parziale';
    }

    $rowsHtml = '';
    $cntRichiesti = 0;
    $cntApprovati = 0;
    $cntRespinti = 0;

    foreach ($giorniRows as $r) {
        $det = [];
        if (!empty($r['dettagli_json'])) {
            $tmp = json_decode($r['dettagli_json'], true);
            if (is_array($tmp)) {
                $det = $tmp;
            }
        }

        $sg = strtoupper((string)($det['stato_giorno'] ?? 'RICHIESTO'));
        if ($sg === 'APPROVATO') $cntApprovati++;
        elseif ($sg === 'RESPINTO') $cntRespinti++;
        else $cntRichiesti++;

        $label = 'Richiesto';
        if ($sg === 'APPROVATO') $label = 'Approvato';
        elseif ($sg === 'RESPINTO') $label = 'Respinto';

        $rowsHtml .= '
          <tr>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9;font-weight:800;">' . hMail(fmtDateITMail($r['data_dal'] ?? '')) . '</td>
            <td style="padding:10px;border-bottom:1px solid #f1f5f9;">' . hMail($label) . '</td>
          </tr>';
    }

    $content = '
      <div style="margin:0 0 12px 0;">
        ' . $badgeHtml . '
      </div>

      <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:12px 12px;margin:0 0 14px 0;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
          ' . kvRow('Dipendente', $nomeCompleto) . '
          ' . kvRow('Email utente', ($emailUtente !== '' ? $emailUtente : '—')) . '
          ' . kvRow('Tipologia ferie', $sottotipo) . '
          ' . kvRow('Esito', $esitoLabel) . '
          ' . kvRow('Giorni approvati', (string)$cntApprovati) . '
          ' . kvRow('Giorni respinti', (string)$cntRespinti) . '
          ' . kvRow('Giorni in attesa', (string)$cntRichiesti) . '
        </table>
      </div>

      <div style="margin-top:14px;">
        <div style="font-weight:900;font-size:14px;margin:0 0 8px 0;">Dettaglio giorni</div>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
          <thead style="background:#f8fafc;">
            <tr>
              <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;font-size:12.5px;color:#6b7280;">Data</th>
              <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;font-size:12.5px;color:#6b7280;">Stato giorno</th>
            </tr>
          </thead>
          <tbody>' . $rowsHtml . '</tbody>
        </table>
      </div>
    ';

    if (trim((string)$noteRichiedente) !== '') {
        $content .= '
          <div style="margin-top:12px;padding:12px;border:1px solid #e5e7eb;border-radius:14px;background:#ffffff;">
            <div style="font-weight:800;color:#111827;margin-bottom:6px;">Note del richiedente</div>
            <div style="font-size:13.5px;line-height:1.55;color:#374151;">' . nl2br(hMail($noteRichiedente)) . '</div>
          </div>
        ';
    }

    if (trim((string)$notaApprovatore) !== '') {
        $content .= '
          <div style="margin-top:12px;padding:12px;border:1px solid #e5e7eb;border-radius:14px;background:#ffffff;">
            <div style="font-weight:800;color:#111827;margin-bottom:6px;">Nota della segreteria</div>
            <div style="font-size:13.5px;line-height:1.55;color:#374151;">' . nl2br(hMail($notaApprovatore)) . '</div>
          </div>
        ';
    }

    return mailWrap(
        $headerTitle,
        $toName !== '' ? $toName : 'Segreteria ATA Permessi',
        $intro,
        $content,
        $footer,
        $theme
    );
}

if (!isset($_POST['richiesta_id']) || !isset($_POST['stato_giorno'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Parametri mancanti'], JSON_UNESCAPED_UNICODE);
    exit;
}

$richiestaId = intval($_POST['richiesta_id']);
$statoGiorno = strtoupper(trim((string)$_POST['stato_giorno']));
$notaApprovatore = trim((string)($_POST['nota_approvatore'] ?? ''));

if ($richiestaId <= 0 || !in_array($statoGiorno, ['APPROVATO', 'RESPINTO'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Dati non validi'], JSON_UNESCAPED_UNICODE);
    exit;
}

$richiesta = dbGetFirst("
    SELECT
        req.*,
        t.codice AS tipo_codice,
        p.nome,
        p.cognome,
        p.email
    FROM permesso_ata_richiesta req
    JOIN permesso_ata_tipo t ON t.id = req.permesso_ata_tipo_id
    JOIN personale_ata p ON p.id = req.personale_ata_id
    WHERE req.id = $richiestaId
    LIMIT 1
");

if (!$richiesta || !is_array($richiesta)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Richiesta non trovata'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($richiesta['tipo_codice'] ?? '') !== 'FERIE') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'La richiesta non è FERIE'], JSON_UNESCAPED_UNICODE);
    exit;
}

$righe = dbGetAll("
    SELECT id, dettagli_json
    FROM permesso_ata_richiesta_riga
    WHERE permesso_ata_richiesta_id = $richiestaId
");

if (!is_array($righe) || count($righe) === 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nessuna riga ferie trovata'], JSON_UNESCAPED_UNICODE);
    exit;
}

foreach ($righe as $rr) {
    $det = [];
    if (!empty($rr['dettagli_json'])) {
        $tmp = json_decode($rr['dettagli_json'], true);
        if (is_array($tmp)) {
            $det = $tmp;
        }
    }

    $det['stato_giorno'] = $statoGiorno;
    $det['nota_approvatore'] = $notaApprovatore;

    $detJsonEsc = mysqli_real_escape_string($__con, json_encode($det, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $ok = dbExec("
        UPDATE permesso_ata_richiesta_riga
        SET dettagli_json = '$detJsonEsc'
        WHERE id = " . intval($rr['id']) . "
        LIMIT 1
    ");

    if ($ok === false) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Errore aggiornamento righe'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$righeRichiesta = dbGetAll("
    SELECT id, data_dal, dettagli_json
    FROM permesso_ata_richiesta_riga
    WHERE permesso_ata_richiesta_id = $richiestaId
    ORDER BY data_dal ASC, id ASC
");

if (!is_array($righeRichiesta)) {
    $righeRichiesta = [];
}

$cntRichiesti = 0;
$cntApprovati = 0;
$cntRespinti = 0;

foreach ($righeRichiesta as $rr) {
    $dj = [];
    if (!empty($rr['dettagli_json'])) {
        $tmp = json_decode($rr['dettagli_json'], true);
        if (is_array($tmp)) {
            $dj = $tmp;
        }
    }

    $sg = strtoupper((string)($dj['stato_giorno'] ?? 'RICHIESTO'));

    if ($sg === 'APPROVATO') {
        $cntApprovati++;
    } elseif ($sg === 'RESPINTO') {
        $cntRespinti++;
    } else {
        $cntRichiesti++;
    }
}

$nuovoStato = 'INVIATO';
if ($cntApprovati > 0 && $cntRespinti === 0 && $cntRichiesti === 0) {
    $nuovoStato = 'APPROVATO';
} elseif ($cntRespinti > 0 && $cntApprovati === 0 && $cntRichiesti === 0) {
    $nuovoStato = 'RESPINTO';
} elseif ($cntApprovati > 0 || $cntRespinti > 0) {
    $nuovoStato = 'PARZIALE';
}

$headDet = [];
if (!empty($richiesta['dettagli_json'])) {
    $tmp = json_decode($richiesta['dettagli_json'], true);
    if (is_array($tmp)) {
        $headDet = $tmp;
    }
}

$headDet['giorni_richiesti_count'] = count($righeRichiesta);
$headDet['giorni_approvati_count'] = $cntApprovati;
$headDet['giorni_respinti_count'] = $cntRespinti;

$headDetJsonEsc = mysqli_real_escape_string($__con, json_encode($headDet, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$nuovoStatoEsc = mysqli_real_escape_string($__con, $nuovoStato);

$gestitoDa = (isset($__utente_id) && intval($__utente_id) > 0) ? intval($__utente_id) : "NULL";

$ok = dbExec("
    UPDATE permesso_ata_richiesta
    SET
        stato = '$nuovoStatoEsc',
        dettagli_json = '$headDetJsonEsc',
        updated_at = NOW(),
        gestito_il = NOW(),
        gestito_da_utente_id = $gestitoDa
    WHERE id = $richiestaId
    LIMIT 1
");

if ($ok === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Errore aggiornamento richiesta'], JSON_UNESCAPED_UNICODE);
    exit;
}
    info("permessoFerieRichiestaBulkUpdate.php: richiesta_id=$richiestaId aggiornata a stato=$nuovoStato senza invio mail (giorno aggiornato a stato_giorno=$statoGiorno cntApprovati=$cntApprovati cntRespinti=$cntRespinti cntRichiesti=$cntRichiesti)");

echo json_encode([
    'ok' => true,
    'richiesta_id' => $richiestaId,
    'stato_richiesta' => $nuovoStato,
    'giorni' => [
        'richiesti' => $cntRichiesti,
        'approvati' => $cntApprovati,
        'respinti' => $cntRespinti
    ]
], JSON_UNESCAPED_UNICODE);
