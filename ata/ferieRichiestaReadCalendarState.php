<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('personale-ata');

header('Content-Type: application/json; charset=utf-8');

$sottotipo = isset($_GET['sottotipo']) ? strtoupper(trim((string)$_GET['sottotipo'])) : '';
$editId = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;

$allowedSottotipi = ['ESTIVE', 'NATALE', 'CARNEVALE', 'PASQUA', 'ORDINARIE'];
if (!in_array($sottotipo, $allowedSottotipi, true)) {
    echo json_encode(['ok' => false, 'error' => 'Sottotipo ferie non valido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rows = dbGetAll("
    SELECT
        r.id AS richiesta_id,
        r.stato AS richiesta_stato,
        rr.id AS riga_id,
        rr.data_dal,
        rr.data_al,
        rr.dettagli_json
    FROM permesso_ata_richiesta r
    INNER JOIN permesso_ata_tipo t ON t.id = r.permesso_ata_tipo_id
    INNER JOIN permesso_ata_richiesta_riga rr ON rr.permesso_ata_richiesta_id = r.id
    WHERE r.personale_ata_id = $__ata_id
      AND t.codice = 'FERIE'
      AND UPPER(TRIM(r.ferie_sottotipo)) = " . dbQ($sottotipo) . "
    ORDER BY r.id ASC, rr.data_dal ASC, rr.id ASC
");

if (!is_array($rows)) {
    $rows = [];
}

function expandDateRangeIsoUser($from, $to)
{
    $out = [];
    if (!$from) return $out;
    if (!$to) $to = $from;

    $start = DateTime::createFromFormat('Y-m-d', $from);
    $end   = DateTime::createFromFormat('Y-m-d', $to);

    if (!$start || !$end || $end < $start) return $out;

    $cur = clone $start;
    while ($cur <= $end) {
        $out[] = $cur->format('Y-m-d');
        $cur->modify('+1 day');
    }
    return $out;
}

function normalizeStatoGiornoUser($stato)
{
    $stato = strtoupper(trim((string)$stato));
    if ($stato === '') $stato = 'RICHIESTO';
    return $stato;
}

function priorityStatoStoricoUser($stato)
{
    switch ($stato) {
        case 'APPROVATO': return 400;
        case 'AGGIUNTO': return 300;
        case 'RICHIESTO': return 300;
        case 'RESPINTO':  return 200;
        case 'BOZZA':     return 100;
        default:          return 0;
    }
}

$giorniStorici = [];
$giorniEditCorrente = [];

foreach ($rows as $r) {
    $det = [];
    if (!empty($r['dettagli_json'])) {
        $tmp = json_decode($r['dettagli_json'], true);
        if (is_array($tmp)) $det = $tmp;
    }

    $richiestaId = intval($r['richiesta_id']);
    $richiestaStato = strtoupper(trim((string)($r['richiesta_stato'] ?? '')));
    $statoGiorno = normalizeStatoGiornoUser($det['stato_giorno'] ?? '');
    if ($statoGiorno === 'RIMOSSO') {
        continue;
    }
    if ($statoGiorno === 'AGGIUNTO') {
        $statoGiorno = 'RICHIESTO';
    }

    // se il giorno non ha stato_giorno esplicito ma la richiesta è in BOZZA, trattalo come BOZZA
    if ($statoGiorno === 'RICHIESTO' && $richiestaStato === 'BOZZA') {
        $statoGiorno = 'BOZZA';
    }

    $range = expandDateRangeIsoUser($r['data_dal'] ?? '', $r['data_al'] ?? '');

    foreach ($range as $iso) {
        // bozza corrente in modifica: separata e modificabile
        if ($editId > 0 && $richiestaId === $editId) {
            $giorniEditCorrente[$iso] = [
                'richiesta_id' => $richiestaId,
                'riga_id' => intval($r['riga_id']),
                'stato' => $statoGiorno,
                'motivo' => ($richiestaStato === 'BOZZA' ? 'Bozza corrente' : 'Richiesta corrente')
            ];
            continue;
        }

        $candidate = [
            'richiesta_id' => $richiestaId,
            'riga_id' => intval($r['riga_id']),
            'stato' => $statoGiorno,
            'motivo' => ''
        ];

        if ($statoGiorno === 'APPROVATO') {
            $candidate['motivo'] = 'Già approvato';
        } elseif ($statoGiorno === 'RESPINTO') {
            $candidate['motivo'] = 'Già respinto';
        } elseif ($statoGiorno === 'BOZZA') {
            $candidate['motivo'] = 'Già presente in altra bozza';
        } else {
            $candidate['motivo'] = 'Già richiesto';
        }

        if (!isset($giorniStorici[$iso])) {
            $giorniStorici[$iso] = $candidate;
        } else {
            $oldP = priorityStatoStoricoUser($giorniStorici[$iso]['stato']);
            $newP = priorityStatoStoricoUser($candidate['stato']);
            if ($newP >= $oldP) {
                $giorniStorici[$iso] = $candidate;
            }
        }
    }
}

echo json_encode([
    'ok' => true,
    'giorni_storici' => $giorniStorici,
    'giorni_edit_corrente' => $giorniEditCorrente
], JSON_UNESCAPED_UNICODE);
