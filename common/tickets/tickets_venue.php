<?php

declare(strict_types=1);

/**
 * Logica di normalizzazione tribune per allocazione biglietti.
 *
 * Regole:
 * - un'elaborazione contiene sempre biglietti di una sola tribuna
 * - Curva Gisilimberti: 14 file, multi-blocco
 * - Tribuna Est: 10 file, multi-blocco
 * - tutte le altre tribune: stessa logica di assegnazione, ma blocco unico
 * - blocchi VIP / abbinamenti R,S,T,X,Z esclusi
 * - per le tribune "blocco unico" le file basse sono penalizzate
 */

function ticketsVenueNormalizeLabel(?string $value): string
{
    $v = strtoupper(trim((string)$value));
    $v = preg_replace('/\s+/', ' ', $v ?? '');
    return (string)$v;
}

function ticketsVenueIsCurvaGislimberti(string $tribuna): bool
{
    return strpos(ticketsVenueNormalizeLabel($tribuna), 'CURVA GISLIMBERTI') !== false;
}

function ticketsVenueIsTribunaEst(string $tribuna): bool
{
    $tribuna = ticketsVenueNormalizeLabel($tribuna);

    return strpos($tribuna, 'TRIBUNA EST') !== false
        || strpos($tribuna, 'CURVA EST') !== false;
}

function ticketsVenueIsGradinata(string $tribuna): bool
{
    return strpos(ticketsVenueNormalizeLabel($tribuna), 'GRADINATA') !== false;
}

function ticketsVenueUsesSegmentedSeatMap(string $tribuna): bool
{
    return ticketsVenueIsCurvaGislimberti($tribuna) || ticketsVenueIsTribunaEst($tribuna);
}

function ticketsVenueDetectSingleTribuna(array $tickets): string
{
    $found = [];

    foreach ($tickets as $t) {
        $tribuna = ticketsVenueNormalizeLabel($t['tribuna'] ?? '');
        if ($tribuna !== '') {
            $found[$tribuna] = true;
        }
    }

    $tribune = array_keys($found);

    if (count($tribune) === 0) {
        throw new RuntimeException('Nessuna tribuna rilevata nei biglietti');
    }

    if (count($tribune) > 1) {
        throw new RuntimeException('Il PDF contiene più tribune: ' . implode(', ', $tribune));
    }

    return $tribune[0];
}

function ticketsVenueProfile(string $tribuna): array
{
    $tribuna = ticketsVenueNormalizeLabel($tribuna);

    if (ticketsVenueIsCurvaGislimberti($tribuna)) {
        return [
            'tribuna' => $tribuna,
            'rows' => 14,
            'multi_block' => true,
            'penalized_rows' => [],
        ];
    }

    if (ticketsVenueIsTribunaEst($tribuna)) {
        return [
            'tribuna' => $tribuna,
            'rows' => 10,
            'multi_block' => true,
            'penalized_rows' => [],
        ];
    }

    if (ticketsVenueIsGradinata($tribuna)) {
        return [
            'tribuna' => $tribuna,
            'rows' => 10,
            'multi_block' => true,
            'penalized_rows' => [1, 2],
        ];
    }

    // GRADINATE e tutto il resto
    return [
        'tribuna' => $tribuna,
        'rows' => 10,
        'multi_block' => false,
        'penalized_rows' => [1, 2],
    ];
}

function ticketsVenueExtractBlockLabel(array $ticket): string
{
    // caso GRADINATA → Settore B
    if (!empty($ticket['settore'])) {
        return strtoupper(trim((string)$ticket['settore']));
    }

    // fallback standard
    if (!empty($ticket['blocco_label'])) {
        return strtoupper(trim((string)$ticket['blocco_label']));
    }

    if (!empty($ticket['blocco'])) {
        return strtoupper(trim((string)$ticket['blocco']));
    }

    return '';
}

function ticketsVenueIsExcludedBlock(array $ticket, array $profile): bool
{
    $label = ticketsVenueExtractBlockLabel($ticket);

    if ($label === '') {
        return false;
    }

    return in_array($label, ['R', 'S', 'T', 'X', 'Z'], true);
}

function ticketsVenueNormalizeSingleTicket(array $ticket, array $profile): ?array
{
    $tribuna = $profile['tribuna'];
    $multiBlock = !empty($profile['multi_block']);

    $row = (int)($ticket['fila'] ?? 0);
    $seat = (int)($ticket['posto'] ?? 0);

    $norm = $ticket;
    $norm['tribuna'] = $tribuna;
    $norm['fila'] = $row;
    $norm['posto'] = $seat;

    $blockLabel = ticketsVenueExtractBlockLabel($ticket);

    if (in_array($blockLabel, ['R', 'S', 'T', 'X', 'Z'], true)) {
        return null;
    }

    if ($multiBlock) {
        if ($blockLabel !== '' && ctype_alpha($blockLabel)) {
            $norm['blocco'] = ord($blockLabel) - 64;
        } elseif (is_numeric($ticket['blocco'] ?? null)) {
            $norm['blocco'] = (int)$ticket['blocco'];
        } else {
            $norm['blocco'] = 1;
        }
    } else {
        $norm['blocco'] = 1;
    }

    $norm['blocco_label'] = $blockLabel !== '' ? $blockLabel : (string)$norm['blocco'];
    $norm['fila_penalizzata'] = in_array($row, $profile['penalized_rows'] ?? [], true) ? 1 : 0;

    return $norm;
}

/**
 * Prepara il contesto completo per l'allocazione.
 *
 * Ritorna:
 * [
 *   'tribuna' => '...',
 *   'profile' => [...],
 *   'tickets'  => [... biglietti normalizzati ...],
 *   'excluded_tickets' => [... scartati R,S,T,X,Z ...],
 * ]
 */
function ticketsVenuePrepareAllocationContext(array $tickets): array
{
    $tribuna = ticketsVenueDetectSingleTribuna($tickets);
    $profile = ticketsVenueProfile($tribuna);

    $normalized = [];
    $excluded = [];

    foreach ($tickets as $ticket) {
        if (ticketsVenueIsExcludedBlock($ticket, $profile)) {
            $excluded[] = $ticket;
            continue;
        }

        $norm = ticketsVenueNormalizeSingleTicket($ticket, $profile);

        if ($norm === null) {
            $excluded[] = $ticket;
            continue;
        }

        $normalized[] = $norm;
    }

    return [
        'tribuna' => $tribuna,
        'profile' => $profile,
        'tickets' => array_values($normalized),
        'excluded_tickets' => array_values($excluded),
    ];
}

/**
 * Penalità riga da usare nello scoring dell'allocatore.
 * Più alto = peggio.
 */
function ticketsVenueRowPenalty(array $ticket): int
{
    $row = (int)($ticket['fila'] ?? 0);
    $pen = !empty($ticket['fila_penalizzata']);

    if (!$pen) {
        return 0;
    }

    if ($row === 1) {
        return 100;
    }

    if ($row === 2) {
        return 50;
    }

    return 20;
}
