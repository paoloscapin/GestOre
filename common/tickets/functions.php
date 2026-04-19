<?php

declare(strict_types=1);

use Smalot\PdfParser\Parser;

function normalizeString(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return $value;
}

function macroPriority(string $macro): int
{
    return match (strtolower(trim($macro))) {
        'docenti' => 1,
        'ata' => 2,
        'studenti' => 3,
        default => 9,
    };
}

function subgroupPriority(string $group): int
{
    static $map = [
        'info' => 1,
        'cat' => 2,
        'mecc' => 3,
        'chim' => 4,
        'elet' => 5,
        'altri' => 6,
        'ata' => 7,
        'studenti' => 8,
        '' => 90,
    ];

    return $map[strtolower(trim($group))] ?? 99;
}

function seatBlock(int $seat): int
{
    if ($seat >= 1 && $seat <= 12) {
        return 1;
    }
    if ($seat >= 13 && $seat <= 42) {
        return 2;
    }
    if ($seat >= 43 && $seat <= 54) {
        return 3;
    }
    if ($seat >= 55 && $seat <= 66) {
        return 4;
    }

    throw new RuntimeException('Posto fuori range: ' . $seat);
}

function isLowRow(int $row): bool
{
    return in_array($row, [1, 2, 3], true);
}

function tribunaRowCount(string $tribuna, array $tickets = []): int
{
    $t = mb_strtolower(trim($tribuna), 'UTF-8');

    if (strpos($t, 'curva gislimberti') !== false) {
        return 14;
    }

    if ($tickets) {
        return max(10, (int) max(array_column($tickets, 'fila')));
    }

    return 10;
}

function tribunaSeatCount(array $tickets): int
{
    return $tickets ? max(54, (int) max(array_column($tickets, 'posto'))) : 54;
}

function groupColor(string $macro, string $group): string
{
    $macro = strtolower(trim($macro));
    $group = strtolower(trim($group));

    return match ($macro) {
        'docenti' => match ($group) {
            'info' => '#2563eb',
            'cat' => '#06b6d4',
            'mecc' => '#8b5cf6',
            'chim' => '#10b981',
            'elet' => '#f59e0b',
            'altri' => '#6366f1',
            default => '#1d4ed8',
        },
        'ata' => '#ef4444',
        'studenti' => '#22c55e',
        default => '#6b7280',
    };
}

function emailToDisplayName(string $email): string
{
    $local = strtolower(trim(explode('@', $email)[0] ?? ''));
    $parts = array_filter(explode('.', $local), fn($v) => trim($v) !== '');

    if (!$parts) {
        return $email;
    }

    $parts = array_map(function (string $p): string {
        $p = str_replace(['_', '-'], ' ', $p);
        $sub = preg_split('/\s+/', $p) ?: [$p];
        $sub = array_map(fn($x) => mb_convert_case($x, MB_CASE_TITLE, 'UTF-8'), $sub);
        return implode(' ', $sub);
    }, $parts);

    if (count($parts) >= 2) {
        $nome = $parts[0];
        $cognome = implode(' ', array_slice($parts, 1));
        return trim($cognome . ' ' . $nome);
    }

    return $parts[0];
}

function flattenRunsFromOrderedZones(array $orderedZones): array
{
    $runs = [];

    foreach ($orderedZones as $zoneInfo) {
        $zoneRuns = splitIntoConsecutiveRuns($zoneInfo['tickets']);

        foreach ($zoneRuns as $run) {
            if (!$run) {
                continue;
            }

            $runs[] = [
                'zone_key' => $zoneInfo['zone_key'],
                'tribuna' => $zoneInfo['tribuna'],
                'fila' => $zoneInfo['fila'],
                'blocco' => $zoneInfo['blocco'],
                'len' => count($run),
                'tickets' => $run,
            ];
        }
    }

    return $runs;
}

function parseSeatText(string $text): ?array
{
    $lines = preg_split('/\r\n|\r|\n/u', $text) ?: [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $line = preg_replace('/\s+/u', ' ', $line) ?? $line;

        if (stripos($line, 'Fila') === false || stripos($line, 'Posto') === false) {
            continue;
        }

        if (preg_match('/^(?P<tribuna>(Curva|Tribuna)\s+[A-Za-zÀ-ÖØ-öø-ÿ\'`\- ]+?)\s+Fila\s*(?P<fila>\d+)\s+Posto\s*(?P<posto>\d+)/iu', $line, $m)) {
            return [
                'tribuna' => normalizeString((string) $m['tribuna']),
                'fila' => (int) $m['fila'],
                'posto' => (int) $m['posto'],
                'pattern' => 'line_with_fila_posto',
                'matched_line' => $line,
            ];
        }
    }

    $flat = str_replace(["\r", "\n", "\t"], ' ', $text);
    $flat = preg_replace('/\s+/u', ' ', $flat) ?? $flat;

    if (
        preg_match_all(
            '/(?P<tribuna>(Curva|Tribuna)\s+[A-Za-zÀ-ÖØ-öø-ÿ\'`\- ]+?)\s+Fila\s*(?P<fila>\d+)\s+Posto\s*(?P<posto>\d+)/iu',
            $flat,
            $matches,
            PREG_SET_ORDER
        ) && !empty($matches)
    ) {
        $m = $matches[count($matches) - 1];
        return [
            'tribuna' => normalizeString((string) $m['tribuna']),
            'fila' => (int) $m['fila'],
            'posto' => (int) $m['posto'],
            'pattern' => 'fallback_flat_last_match',
            'matched_line' => normalizeString((string) $m[0]),
        ];
    }

    return null;
}

function readUsersCsv(string $csvPath): array
{
    $fh = fopen($csvPath, 'rb');
    if (!$fh) {
        throw new RuntimeException('Impossibile aprire il CSV utenti');
    }

    $header = fgetcsv($fh, 0, ',');
    if (!$header) {
        throw new RuntimeException('CSV vuoto');
    }

    $header = array_map(fn($x) => strtolower(trim((string) $x)), $header);

    $required = ['macrogruppo', 'gruppo', 'email', 'numero_posti', 'affianca'];
    foreach ($required as $col) {
        if (!in_array($col, $header, true)) {
            throw new RuntimeException('Colonna mancante nel CSV: ' . $col);
        }
    }

    $rows = [];
    while (($data = fgetcsv($fh, 0, ',')) !== false) {
        if (!count(array_filter($data, fn($v) => trim((string) $v) !== ''))) {
            continue;
        }

        $row = array_combine($header, array_pad($data, count($header), ''));
        if (!$row) {
            continue;
        }

        $rows[] = [
            'macrogruppo' => strtolower(trim((string) $row['macrogruppo'])),
            'gruppo' => strtolower(trim((string) $row['gruppo'])),
            'email' => trim((string) $row['email']),
            'numero_posti' => (int) $row['numero_posti'],
            'affianca' => trim((string) $row['affianca']) !== '' ? (int) $row['affianca'] : null,
        ];
    }

    fclose($fh);

    foreach ($rows as $i => $row) {
        if ($row['numero_posti'] < 1) {
            throw new RuntimeException('numero_posti non valido alla riga CSV ' . ($i + 2));
        }
        if (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Email non valida alla riga CSV ' . ($i + 2));
        }
    }

    return $rows;
}

function extractTicketsFromMultiPagePdf(string $pdfPath): array
{
    $parser = new Parser();
    $pdf = $parser->parseFile($pdfPath);
    $pages = $pdf->getPages();

    $tickets = [];
    $seen = [];

    foreach ($pages as $idx => $page) {
        $pageNo = $idx + 1;
        $text = $page->getText() ?? '';
        $seat = parseSeatText($text);

        if (!$seat) {
            continue;
        }

        $key = strtolower($seat['tribuna']) . '|' . $seat['fila'] . '|' . $seat['posto'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;

        $tickets[] = [
            'ticket_id' => 'P' . $pageNo,
            'page' => $pageNo,
            'tribuna' => $seat['tribuna'],
            'fila' => (int) $seat['fila'],
            'posto' => (int) $seat['posto'],
            'blocco' => seatBlock((int) $seat['posto']),
            'fila_penalizzata' => isLowRow((int) $seat['fila']),
            'pattern' => $seat['pattern'] ?? '',
            'matched_line' => $seat['matched_line'] ?? '',
        ];
    }

    usort(
        $tickets,
        fn($a, $b) =>
        [$a['tribuna'], $a['fila'], $a['blocco'], $a['posto'], $a['page']]
            <=>
            [$b['tribuna'], $b['fila'], $b['blocco'], $b['posto'], $b['page']]
    );

    return $tickets;
}

function buildAssignmentUnits(array $users): array
{
    $units = [];
    $grouped = [];
    $loose = [];

    foreach ($users as $user) {
        if ($user['affianca'] !== null) {
            $key = implode('|', [$user['macrogruppo'], $user['gruppo'], (string) $user['affianca']]);
            $grouped[$key][] = $user;
        } else {
            $loose[] = $user;
        }
    }

    foreach ($grouped as $key => $members) {
        $first = $members[0];
        $units[] = [
            'unit_id' => 'AFF_' . md5($key),
            'macrogruppo' => $first['macrogruppo'],
            'gruppo' => $first['gruppo'],
            'affianca' => $first['affianca'],
            'rigid_affianca' => true,
            'size' => array_sum(array_column($members, 'numero_posti')),
            'users' => $members,
        ];
    }

    foreach ($loose as $i => $user) {
        $units[] = [
            'unit_id' => 'USR_' . $i,
            'macrogruppo' => $user['macrogruppo'],
            'gruppo' => $user['gruppo'],
            'affianca' => null,
            'rigid_affianca' => false,
            'size' => $user['numero_posti'],
            'users' => [$user],
        ];
    }

    usort($units, function (array $a, array $b): int {
        $ka = [
            macroPriority($a['macrogruppo']),
            $a['rigid_affianca'] ? 0 : 1,
            subgroupPriority($a['gruppo']),
            -$a['size'],
        ];

        $kb = [
            macroPriority($b['macrogruppo']),
            $b['rigid_affianca'] ? 0 : 1,
            subgroupPriority($b['gruppo']),
            -$b['size'],
        ];

        return $ka <=> $kb;
    });

    return $units;
}

function rowPriorityForDocenti(array $availableTickets): array
{
    $zones = groupTicketsByZone($availableTickets);
    $rows = [];

    foreach ($zones as $zoneKey => $zoneTickets) {
        $parts = explode('|', $zoneKey);
        if (count($parts) !== 3) {
            continue;
        }

        [$tribuna, $fila, $blocco] = $parts;
        $fila = (int)$fila;
        $blocco = (int)$blocco;

        $runs = splitIntoConsecutiveRuns($zoneTickets);
        if (!$runs) {
            continue;
        }

        $rows[] = [
            'zone_key' => $zoneKey,
            'tribuna' => $tribuna,
            'fila' => $fila,
            'blocco' => $blocco,
            'tickets' => $zoneTickets,
            'runs' => $runs,
            'max_run' => count($runs[0]),
            'total_free' => count($zoneTickets),
        ];
    }

    usort($rows, function (array $a, array $b): int {
        // 1. prima il filotto massimo più lungo
        if ($a['max_run'] !== $b['max_run']) {
            return $b['max_run'] <=> $a['max_run'];
        }

        // 2. poi la fila con più posti liberi totali
        if ($a['total_free'] !== $b['total_free']) {
            return $b['total_free'] <=> $a['total_free'];
        }

        // 3. solo dopo preferisci la fila più bassa numero? NO.
        // Nel tuo caso vuoi davvero seguire la lunghezza del filotto.
        // Quindi come spareggio tieni la fila numericamente più alta.
        if ($a['fila'] !== $b['fila']) {
            return $b['fila'] <=> $a['fila'];
        }

        return abs($a['blocco'] - 2) <=> abs($b['blocco'] - 2);
    });

    return $rows;
}

function reserveDocentiRows(array $availableTickets, int $neededSeats): array
{
    $rows = rowPriorityForDocenti($availableTickets);
    $reserved = [];
    $covered = 0;

    foreach ($rows as $row) {
        $reserved[] = $row['zone_key'];
        $covered += $row['total_free'];

        if ($covered >= $neededSeats) {
            break;
        }
    }

    return $reserved;
}

function sortUnitsForDocenti(array $docentiUnits): array
{
    usort($docentiUnits, function (array $a, array $b): int {
        // 1. prima i blocchi rigidi
        if (($a['rigid_affianca'] ? 1 : 0) !== ($b['rigid_affianca'] ? 1 : 0)) {
            return ($b['rigid_affianca'] ? 1 : 0) <=> ($a['rigid_affianca'] ? 1 : 0);
        }

        // 2. poi i sottogruppi vicini tra loro
        $ga = subgroupPriority((string)$a['gruppo']);
        $gb = subgroupPriority((string)$b['gruppo']);
        if ($ga !== $gb) {
            return $ga <=> $gb;
        }

        // 3. dentro il sottogruppo prima i gruppi più grandi
        if ((int)$a['size'] !== (int)$b['size']) {
            return (int)$b['size'] <=> (int)$a['size'];
        }

        return strcmp((string)$a['unit_id'], (string)$b['unit_id']);
    });

    return $docentiUnits;
}

function allocateDocentiByRows(array $docentiUnits, array &$available, array &$warnings): array
{
    $assignments = [];
    $docentiAssignedTickets = [];

    if (!$docentiUnits) {
        return [
            'assignments' => [],
            'docenti_anchor' => null,
            'docenti_tickets' => [],
        ];
    }

    $docentiUnits = sortUnitsForDocenti($docentiUnits);
    $neededSeats = array_sum(array_map(fn($u) => (int)$u['size'], $docentiUnits));
    $reservedRows = reserveDocentiRows($available, $neededSeats);

    foreach ($docentiUnits as $unit) {
        $placed = false;

        // Ricalcola le zone disponibili ma usa SOLO quelle riservate ai docenti
        $zones = groupTicketsByZone($available);

        foreach ($reservedRows as $zoneKey) {
            if (!isset($zones[$zoneKey])) {
                continue;
            }

            $zoneTickets = $zones[$zoneKey];
            $runs = splitIntoConsecutiveRuns($zoneTickets);

            foreach ($runs as $run) {
                if (count($run) < (int)$unit['size']) {
                    continue;
                }

                // riempi da sinistra a destra
                $candidate = array_slice($run, 0, (int)$unit['size']);

                foreach (distributeCandidateToUsers($candidate, $unit['users']) as $row) {
                    $assignments[] = $row;
                }

                foreach ($candidate as $ticket) {
                    $docentiAssignedTickets[] = $ticket;
                }

                $used = array_flip(array_column($candidate, 'ticket_id'));
                $available = array_values(array_filter(
                    $available,
                    fn($t) => !isset($used[$t['ticket_id']])
                ));

                $placed = true;
                break 2;
            }
        }

        if (!$placed) {
            $who = $unit['affianca'] !== null
                ? 'affianca=' . $unit['affianca']
                : 'unit=' . $unit['unit_id'];

            $warnings[] = 'Nessun blocco docenti trovato per ' . $who
                . ' (' . $unit['macrogruppo']
                . ' / ' . $unit['gruppo']
                . ' / ' . $unit['size']
                . ' posti)';
        }
    }

    return [
        'assignments' => $assignments,
        'docenti_anchor' => $docentiAssignedTickets[0] ?? null,
        'docenti_tickets' => $docentiAssignedTickets,
    ];
}

function getDocentiRowsPriority(array $docentiAssignments): array
{
    $rows = [];

    foreach ($docentiAssignments as $row) {
        $key = implode('|', [
            (string)$row['tribuna'],
            (int)$row['fila'],
            (int)$row['blocco'],
        ]);

        if (!isset($rows[$key])) {
            $rows[$key] = [
                'tribuna' => (string)$row['tribuna'],
                'fila' => (int)$row['fila'],
                'blocco' => (int)$row['blocco'],
                'count' => 0,
            ];
        }

        $rows[$key]['count'] += (int)$row['numero_posti'];
    }

    $rows = array_values($rows);

    usort($rows, function (array $a, array $b): int {
        if ($a['count'] !== $b['count']) {
            return $b['count'] <=> $a['count'];
        }

        if ($a['fila'] !== $b['fila']) {
            return $b['fila'] <=> $a['fila'];
        }

        return abs($a['blocco'] - 2) <=> abs($b['blocco'] - 2);
    });

    return $rows;
}

function reserveAtaRows(array $availableTickets, int $neededSeats, ?array $docentiAnchor): array
{
    $zones = groupTicketsByZone($availableTickets);
    $rows = [];

    foreach ($zones as $zoneKey => $zoneTickets) {
        $parts = explode('|', $zoneKey);
        if (count($parts) !== 3) {
            continue;
        }

        [$tribuna, $fila, $blocco] = $parts;
        $fila = (int)$fila;
        $blocco = (int)$blocco;

        $rows[] = [
            'zone_key' => $zoneKey,
            'tribuna' => $tribuna,
            'fila' => $fila,
            'blocco' => $blocco,
            'tickets' => $zoneTickets,
            'max_run' => longestConsecutiveRunLength($zoneTickets),
            'total_free' => count($zoneTickets),
        ];
    }

    usort($rows, function (array $a, array $b) use ($docentiAnchor): int {
        $scoreA = 0;
        $scoreB = 0;

        if ($docentiAnchor) {
            if ($a['tribuna'] === (string)$docentiAnchor['tribuna']) {
                $scoreA += 3000;
            }
            if ($b['tribuna'] === (string)$docentiAnchor['tribuna']) {
                $scoreB += 3000;
            }

            $diffFilaA = abs($a['fila'] - (int)$docentiAnchor['fila']);
            $diffFilaB = abs($b['fila'] - (int)$docentiAnchor['fila']);

            // stessa fila molto meglio, poi fila vicina
            if ($diffFilaA === 0) $scoreA += 3000;
            elseif ($diffFilaA === 1) $scoreA += 1800;
            elseif ($diffFilaA === 2) $scoreA += 900;
            else $scoreA -= $diffFilaA * 300;

            if ($diffFilaB === 0) $scoreB += 3000;
            elseif ($diffFilaB === 1) $scoreB += 1800;
            elseif ($diffFilaB === 2) $scoreB += 900;
            else $scoreB -= $diffFilaB * 300;

            $scoreA -= abs($a['blocco'] - (int)$docentiAnchor['blocco']) * 150;
            $scoreB -= abs($b['blocco'] - (int)$docentiAnchor['blocco']) * 150;
        }

        // a parità, meglio fila con più posti e filotto maggiore
        $scoreA += $a['max_run'] * 50 + $a['total_free'] * 10;
        $scoreB += $b['max_run'] * 50 + $b['total_free'] * 10;

        return $scoreB <=> $scoreA;
    });

    $reserved = [];
    $covered = 0;

    foreach ($rows as $row) {
        $reserved[] = $row['zone_key'];
        $covered += $row['total_free'];

        if ($covered >= $neededSeats) {
            break;
        }
    }

    return $reserved;
}

function buildAtaZoneOrder(array $available, array $docentiAssignments): array
{
    $zones = groupTicketsByZone($available);
    $docRows = getDocentiRowsPriority($docentiAssignments);
    $ordered = [];
    $seen = [];

    foreach ($docRows as $dr) {
        $tribuna = (string)$dr['tribuna'];
        $filaBase = (int)$dr['fila'];
        $bloccoBase = (int)$dr['blocco'];

        // Prima stessa fila, poi fila vicina
        $rowOffsets = [0, -1, 1, -2, 2, -3, 3];

        foreach ($rowOffsets as $off) {
            $fila = $filaBase + $off;
            if ($fila < 1) {
                continue;
            }

            // Prima stesso blocco, poi blocchi vicini
            foreach ([0, -1, 1, -2, 2] as $boff) {
                $blocco = $bloccoBase + $boff;
                if ($blocco < 1) {
                    continue;
                }

                $key = $tribuna . '|' . $fila . '|' . $blocco;

                if (isset($zones[$key]) && !isset($seen[$key])) {
                    $ordered[] = [
                        'zone_key' => $key,
                        'tribuna' => $tribuna,
                        'fila' => $fila,
                        'blocco' => $blocco,
                        'tickets' => $zones[$key],
                    ];
                    $seen[$key] = true;
                }
            }
        }
    }

    // In fondo tutto il resto
    foreach ($zones as $zoneKey => $zoneTickets) {
        if (isset($seen[$zoneKey])) {
            continue;
        }

        $parts = explode('|', $zoneKey);
        if (count($parts) !== 3) {
            continue;
        }

        [$tribuna, $fila, $blocco] = $parts;

        $ordered[] = [
            'zone_key' => $zoneKey,
            'tribuna' => (string)$tribuna,
            'fila' => (int)$fila,
            'blocco' => (int)$blocco,
            'tickets' => $zoneTickets,
        ];
    }

    return $ordered;
}


function scoreAtaRun(array $runInfo, int $needed, array $ataAssignedTickets, array $docentiAssignments): int
{
    $candidate = array_slice($runInfo['tickets'], 0, $needed);
    if (count($candidate) < $needed) {
        return -999999;
    }

    $score = 0;
    $fila = (int)$runInfo['fila'];
    $blocco = (int)$runInfo['blocco'];
    $tribuna = (string)$runInfo['tribuna'];
    $start = (int)$candidate[0]['posto'];
    $end = (int)$candidate[count($candidate) - 1]['posto'];

    // Meglio usare per intero un filotto quasi della stessa misura, così evitiamo di spezzare file utili.
    $score -= abs((int)$runInfo['len'] - $needed) * 15;
    $score += min((int)$runInfo['len'], $needed + 2) * 10;

    // Fortissima preferenza a stare attaccati ad ATA già piazzati.
    foreach ($ataAssignedTickets as $t) {
        if ((string)$t['tribuna'] !== $tribuna) {
            continue;
        }

        $tf = (int)$t['fila'];
        $tb = (int)$t['blocco'];
        $tp = (int)$t['posto'];

        if ($tf === $fila && $tb === $blocco) {
            if ($tp === $start - 1 || $tp === $end + 1) {
                $score += 10000; // continuità perfetta nello stesso filotto
            }

            if ($tp >= $start - 2 && $tp <= $end + 2) {
                $score += 2500; // molto vicino nello stesso tratto
            }

            $score += 1200;
        } elseif ($tf === $fila) {
            $score += 350;
        } elseif (abs($tf - $fila) === 1) {
            $score += 120;
        }
    }

    // Se non ci sono ancora ATA, stai vicino ai docenti.
    if (!$ataAssignedTickets && $docentiAssignments) {
        foreach ($docentiAssignments as $row) {
            if ((string)$row['tribuna'] !== $tribuna) {
                continue;
            }

            $df = (int)$row['fila'];
            $db = (int)$row['blocco'];
            $score -= abs($fila - $df) * 120;
            $score -= abs($blocco - $db) * 60;

            if ($fila === $df) {
                $score += 1000;
            } elseif (abs($fila - $df) === 1) {
                $score += 450;
            }
        }
    }

    return $score;
}

function allocateAtaByReservedRows(array $ataUnits, array &$available, array &$warnings, array $docentiAssignments): array
{
    $assignments = [];
    $ataAssignedTickets = [];

    if (!$ataUnits) {
        return $assignments;
    }

    usort($ataUnits, function (array $a, array $b): int {
        if (($a['rigid_affianca'] ? 1 : 0) !== ($b['rigid_affianca'] ? 1 : 0)) {
            return ($b['rigid_affianca'] ? 1 : 0) <=> ($a['rigid_affianca'] ? 1 : 0);
        }

        if ((int)$a['size'] !== (int)$b['size']) {
            return (int)$b['size'] <=> (int)$a['size'];
        }

        return strcmp((string)$a['unit_id'], (string)$b['unit_id']);
    });

    foreach ($ataUnits as $unit) {
        $placed = false;

        // Zone ordinate vicino ai docenti; dentro queste scegli il run migliore
        // favorendo fortemente la continuità con ATA già piazzati.
        $orderedZones = buildAtaZoneOrder($available, $docentiAssignments);
        $runs = flattenRunsFromOrderedZones($orderedZones);

        $candidateRuns = [];
        foreach ($runs as $runInfo) {
            if ($runInfo['len'] < (int)$unit['size']) {
                continue;
            }

            $candidateRuns[] = $runInfo;
        }

        usort($candidateRuns, function (array $a, array $b) use ($unit, $ataAssignedTickets, $docentiAssignments): int {
            return scoreAtaRun($b, (int)$unit['size'], $ataAssignedTickets, $docentiAssignments)
                <=> scoreAtaRun($a, (int)$unit['size'], $ataAssignedTickets, $docentiAssignments);
        });

        foreach ($candidateRuns as $runInfo) {
            $candidate = array_slice($runInfo['tickets'], 0, (int)$unit['size']);

            foreach (distributeCandidateToUsers($candidate, $unit['users']) as $row) {
                $assignments[] = $row;
            }

            foreach ($candidate as $ticket) {
                $ataAssignedTickets[] = $ticket;
            }

            $used = array_flip(array_column($candidate, 'ticket_id'));
            $available = array_values(array_filter(
                $available,
                fn($t) => !isset($used[$t['ticket_id']])
            ));

            $placed = true;
            break;
        }

        if (!$placed) {
            $who = $unit['affianca'] !== null
                ? 'affianca=' . $unit['affianca']
                : 'unit=' . $unit['unit_id'];

            $warnings[] = 'Nessun blocco ATA trovato per ' . $who
                . ' (' . $unit['macrogruppo']
                . ' / ' . $unit['gruppo']
                . ' / ' . $unit['size']
                . ' posti)';
        }
    }

    return $assignments;
}

function allocateAtaByRows(array $ataUnits, array &$available, array &$warnings, ?array $docentiAnchor): array
{
    $assignments = [];

    if (!$ataUnits) {
        return $assignments;
    }

    // prima i gruppi ATA più grandi
    usort($ataUnits, function (array $a, array $b): int {
        if (($a['rigid_affianca'] ? 1 : 0) !== ($b['rigid_affianca'] ? 1 : 0)) {
            return ($b['rigid_affianca'] ? 1 : 0) <=> ($a['rigid_affianca'] ? 1 : 0);
        }
        return (int)$b['size'] <=> (int)$a['size'];
    });

    foreach ($ataUnits as $unit) {
        $placed = false;
        $zones = groupTicketsByZone($available);

        $orderedZones = [];
        foreach ($zones as $zoneKey => $zoneTickets) {
            $parts = explode('|', $zoneKey);
            if (count($parts) !== 3) {
                continue;
            }

            [$tribuna, $fila, $blocco] = $parts;
            $fila = (int)$fila;
            $blocco = (int)$blocco;

            $orderedZones[] = [
                'zone_key' => $zoneKey,
                'tribuna' => $tribuna,
                'fila' => $fila,
                'blocco' => $blocco,
                'tickets' => $zoneTickets,
                'max_run' => longestConsecutiveRunLength($zoneTickets),
            ];
        }

        usort($orderedZones, function (array $a, array $b) use ($docentiAnchor): int {
            if (!$docentiAnchor) {
                return $b['max_run'] <=> $a['max_run'];
            }

            $scoreA = 0;
            $scoreB = 0;

            // stessa tribuna
            $scoreA += ($a['tribuna'] === (string)$docentiAnchor['tribuna']) ? 1000 : 0;
            $scoreB += ($b['tribuna'] === (string)$docentiAnchor['tribuna']) ? 1000 : 0;

            // stessa fila prima, poi fila vicina
            $scoreA -= abs($a['fila'] - (int)$docentiAnchor['fila']) * 300;
            $scoreB -= abs($b['fila'] - (int)$docentiAnchor['fila']) * 300;

            // stesso blocco aiuta
            $scoreA -= abs($a['blocco'] - (int)$docentiAnchor['blocco']) * 120;
            $scoreB -= abs($b['blocco'] - (int)$docentiAnchor['blocco']) * 120;

            // a parità, meglio il filotto più lungo
            $scoreA += $a['max_run'] * 20;
            $scoreB += $b['max_run'] * 20;

            return $scoreB <=> $scoreA;
        });

        foreach ($orderedZones as $zoneInfo) {
            if ($zoneInfo['max_run'] < (int)$unit['size']) {
                continue;
            }

            $candidate = tryAllocateUnitInSpecificRow($unit, $zoneInfo['tickets']);
            if (!$candidate) {
                continue;
            }

            foreach (distributeCandidateToUsers($candidate, $unit['users']) as $row) {
                $assignments[] = $row;
            }

            $used = array_flip(array_column($candidate, 'ticket_id'));
            $available = array_values(array_filter(
                $available,
                fn($t) => !isset($used[$t['ticket_id']])
            ));

            $placed = true;
            break;
        }

        if (!$placed) {
            $who = $unit['affianca'] !== null
                ? 'affianca=' . $unit['affianca']
                : 'unit=' . $unit['unit_id'];

            $warnings[] = 'Nessun blocco ATA trovato per ' . $who
                . ' (' . $unit['macrogruppo']
                . ' / ' . $unit['gruppo']
                . ' / ' . $unit['size']
                . ' posti)';
        }
    }

    return $assignments;
}

function groupTicketsByZone(array $tickets): array
{
    $zones = [];

    foreach ($tickets as $ticket) {
        $key = implode('|', [$ticket['tribuna'], $ticket['fila'], $ticket['blocco']]);
        $zones[$key][] = $ticket;
    }

    foreach ($zones as &$zoneTickets) {
        usort($zoneTickets, fn($a, $b) => $a['posto'] <=> $b['posto']);
    }
    unset($zoneTickets);

    return $zones;
}



function tryAllocateUnitInSpecificRow(array $unit, array $rowTickets): ?array
{
    $needed = (int)$unit['size'];
    $runs = splitIntoConsecutiveRuns($rowTickets);

    foreach ($runs as $run) {
        if (count($run) < $needed) {
            continue;
        }

        // usa il blocco più a sinistra del run
        return array_slice($run, 0, $needed);
    }

    return null;
}

function nearbyRowWindows(array $zones, int $size): array
{
    $candidates = [];

    // indicizza per tribuna|blocco|fila
    $byKey = [];
    foreach ($zones as $zoneKey => $zoneTickets) {
        $parts = explode('|', $zoneKey);
        if (count($parts) !== 3) {
            continue;
        }

        [$tribuna, $fila, $blocco] = $parts;
        $byKey[$tribuna . '|' . $blocco . '|' . $fila] = $zoneTickets;
    }

    // cerca coppie di file adiacenti
    foreach ($zones as $zoneKey => $zoneTickets) {
        $parts = explode('|', $zoneKey);
        if (count($parts) !== 3) {
            continue;
        }

        [$tribuna, $fila, $blocco] = $parts;
        $fila = (int)$fila;
        $keyNext = $tribuna . '|' . $blocco . '|' . ($fila + 1);
        $keyPrev = $tribuna . '|' . $blocco . '|' . ($fila - 1);

        foreach ([$keyPrev, $keyNext] as $otherKey) {
            if (!isset($byKey[$otherKey])) {
                continue;
            }

            $otherTickets = $byKey[$otherKey];

            // mappa posto => ticket
            $map1 = [];
            foreach ($zoneTickets as $t) {
                $map1[(int)$t['posto']] = $t;
            }

            $map2 = [];
            foreach ($otherTickets as $t) {
                $map2[(int)$t['posto']] = $t;
            }

            $commonSeats = array_values(array_intersect(array_keys($map1), array_keys($map2)));
            sort($commonSeats);

            if (count($commonSeats) >= $size) {
                for ($i = 0; $i <= count($commonSeats) - $size; $i++) {
                    $slice = array_slice($commonSeats, $i, $size);

                    $ok = true;
                    for ($j = 1; $j < count($slice); $j++) {
                        if ($slice[$j] !== $slice[$j - 1] + 1) {
                            $ok = false;
                            break;
                        }
                    }

                    if (!$ok) {
                        continue;
                    }

                    $candidate = [];
                    foreach ($slice as $seatNo) {
                        // alterna fila principale e fila vicina
                        $candidate[] = $map1[$seatNo];
                    }

                    $candidates[] = [
                        'mode' => 'nearby_row_same_seat',
                        'tickets' => $candidate,
                        'anchor_row' => $fila,
                        'other_row' => (int)explode('|', $otherKey)[2],
                    ];
                }
            }
        }
    }

    return $candidates;
}

function candidateScore(array $unit, array $candidate, ?array $docentiAnchor): int
{
    $first = $candidate[0];
    $fila = (int) $first['fila'];
    $tribuna = (string) $first['tribuna'];
    $blocco = (int) $first['blocco'];
    $macro = strtolower((string)$unit['macrogruppo']);
    $group = strtolower((string)($unit['gruppo'] ?? ''));

    $score = 0;

    // file 1-2-3: bene per studenti, male per docenti/ata
    if ($fila <= 3) {
        if ($macro === 'studenti') {
            $score += 1000;
        } elseif ($macro === 'ata') {
            $score -= 900;
        } else {
            $score -= 2200;
        }
    } else {
        $score += 900;
    }

    // priorità macrogruppi
    if ($macro === 'docenti') {
        $score += 3000;

        $score += match ($group) {
            'info' => 300,
            'cat'  => 260,
            'mecc' => 240,
            'chim' => 220,
            'elet' => 200,
            'altri' => 100,
            default => 0,
        };
    } elseif ($macro === 'ata') {
        $score += 2200; // più forte di prima
    } else {
        $score -= 1200; // studenti più sacrificabili
    }

    // posti centrali un po' migliori
    $posti = array_column($candidate, 'posto');
    $avgSeat = (int) round(array_sum($posti) / max(1, count($posti)));
    $score -= abs($avgSeat - 27) * 8;

    // ATA: fortissima preferenza per stare vicino ai docenti
    if ($docentiAnchor && $macro === 'ata') {
        if ($tribuna === (string)$docentiAnchor['tribuna']) {
            $score += 1200;
        } else {
            $score -= 500;
        }

        $diffFila = abs($fila - (int)$docentiAnchor['fila']);
        $diffBlocco = abs($blocco - (int)$docentiAnchor['blocco']);

        // stessa fila molto premiata
        if ($diffFila === 0) {
            $score += 1400;
        } elseif ($diffFila === 1) {
            $score += 700;
        } elseif ($diffFila === 2) {
            $score += 250;
        } else {
            $score -= $diffFila * 180;
        }

        $score -= $diffBlocco * 120;
    }

    // docenti vicini tra loro
    if ($docentiAnchor && $macro === 'docenti') {
        if ($tribuna === (string)$docentiAnchor['tribuna']) {
            $score += 300;
        }
        $score -= abs($fila - (int)$docentiAnchor['fila']) * 35;
        $score -= abs($blocco - (int)$docentiAnchor['blocco']) * 45;
    }

    // studenti: un po' meglio se più lontani dai docenti
    if ($docentiAnchor && $macro === 'studenti') {
        $score += abs($fila - (int)$docentiAnchor['fila']) * 40;
    }

    return (int) $score;
}

function candidateScoreExtended(array $unit, array $candidateData, ?array $docentiAnchor): int
{
    $candidate = $candidateData['tickets'];
    $mode = $candidateData['mode'] ?? 'consecutive';
    $macro = strtolower((string)$unit['macrogruppo']);

    $score = candidateScore($unit, $candidate, $docentiAnchor);

    if ($mode === 'nearby_row_same_seat') {
        $score -= 700;
    }

    if ($macro === 'docenti') {
        $score += ((int)($candidateData['row_max_run'] ?? 0)) * 500;
    }

    return (int)$score;
}

function consecutiveWindows(array $zoneTickets, int $size): array
{
    $windows = [];
    $count = count($zoneTickets);

    if ($size <= 0 || $count < $size) {
        return $windows;
    }

    for ($i = 0; $i <= $count - $size; $i++) {
        $slice = array_slice($zoneTickets, $i, $size);
        $ok = true;

        for ($j = 1; $j < count($slice); $j++) {
            if ($slice[$j]['posto'] !== $slice[$j - 1]['posto'] + 1) {
                $ok = false;
                break;
            }
        }

        if ($ok) {
            $windows[] = $slice;
        }
    }

    return $windows;
}

function splitIntoConsecutiveRuns(array $zoneTickets): array
{
    if (!$zoneTickets) {
        return [];
    }

    usort($zoneTickets, fn($a, $b) => $a['posto'] <=> $b['posto']);

    $runs = [];
    $current = [$zoneTickets[0]];

    for ($i = 1; $i < count($zoneTickets); $i++) {
        $prev = $zoneTickets[$i - 1];
        $curr = $zoneTickets[$i];

        if ((int)$curr['posto'] === (int)$prev['posto'] + 1) {
            $current[] = $curr;
        } else {
            $runs[] = $current;
            $current = [$curr];
        }
    }

    $runs[] = $current;

    usort($runs, fn($a, $b) => count($b) <=> count($a));

    return $runs;
}

function getDocentiPreferredRuns(array $availableTickets): array
{
    $zones = groupTicketsByZone($availableTickets);
    $rows = [];

    foreach ($zones as $zoneKey => $zoneTickets) {
        $parts = explode('|', $zoneKey);
        if (count($parts) !== 3) {
            continue;
        }

        [$tribuna, $fila, $blocco] = $parts;
        $fila = (int)$fila;
        $blocco = (int)$blocco;

        $runs = splitIntoConsecutiveRuns($zoneTickets);
        if (!$runs) {
            continue;
        }

        $bestRun = $runs[0];

        $rows[] = [
            'zone_key' => $zoneKey,
            'tribuna' => $tribuna,
            'fila' => $fila,
            'blocco' => $blocco,
            'runs' => $runs,
            'best_run_len' => count($bestRun),
        ];
    }

    usort($rows, function (array $a, array $b): int {
        // 1. prima i filotti più lunghi
        if ($a['best_run_len'] !== $b['best_run_len']) {
            return $b['best_run_len'] <=> $a['best_run_len'];
        }

        // 2. poi preferisci file buone per i docenti (circa 4-7)
        $fa = abs($a['fila'] - 6);
        $fb = abs($b['fila'] - 6);
        if ($fa !== $fb) {
            return $fa <=> $fb;
        }

        // 3. poi blocco centrale
        return abs($a['blocco'] - 2) <=> abs($b['blocco'] - 2);
    });

    return $rows;
}



function splitDocentiUnitsBySubgroup(array $docentiUnits): array
{
    $rigid = [];
    $byGroup = [];

    foreach ($docentiUnits as $unit) {
        if (!empty($unit['rigid_affianca'])) {
            $rigid[] = $unit;
            continue;
        }

        $group = strtolower((string)($unit['gruppo'] ?? ''));
        $byGroup[$group][] = $unit;
    }

    // ordina i gruppi per priorità
    uksort($byGroup, function (string $a, string $b): int {
        return subgroupPriority($a) <=> subgroupPriority($b);
    });

    // dentro ogni gruppo, prima i blocchi più grandi
    foreach ($byGroup as &$units) {
        usort($units, function (array $a, array $b): int {
            return (int)$b['size'] <=> (int)$a['size'];
        });
    }
    unset($units);

    return [
        'rigid' => $rigid,
        'groups' => $byGroup,
    ];
}

function allocateDocentiUnits(array $docentiUnits, array &$available, array &$warnings): array
{
    $assignments = [];
    $docentiAssignedTickets = [];

    if (!$docentiUnits) {
        return [
            'assignments' => [],
            'docenti_anchor' => null,
            'docenti_tickets' => [],
        ];
    }

    $neededSeats = array_sum(array_map(fn($u) => (int)$u['size'], $docentiUnits));
    $reservedRows = reserveDocentiRows($available, $neededSeats);

    $split = splitDocentiUnitsBySubgroup($docentiUnits);
    $rigidUnits = $split['rigid'];
    $groupedUnits = $split['groups'];

    // 1. prima i blocchi rigid_affianca
    usort($rigidUnits, function (array $a, array $b): int {
        return (int)$b['size'] <=> (int)$a['size'];
    });

    $orderedUnits = $rigidUnits;
    foreach ($groupedUnits as $units) {
        foreach ($units as $u) {
            $orderedUnits[] = $u;
        }
    }

    foreach ($orderedUnits as $unit) {
        $placed = false;

        // consideriamo SOLO le file riservate ai docenti
        $zones = groupTicketsByZone($available);

        foreach ($reservedRows as $zoneKey) {
            if (!isset($zones[$zoneKey])) {
                continue;
            }

            $zoneTickets = $zones[$zoneKey];
            $candidate = tryAllocateUnitInSpecificRow($unit, $zoneTickets);

            if (!$candidate) {
                continue;
            }

            foreach (distributeCandidateToUsers($candidate, $unit['users']) as $row) {
                $assignments[] = $row;
            }

            foreach ($candidate as $ticket) {
                $docentiAssignedTickets[] = $ticket;
            }

            $used = array_flip(array_column($candidate, 'ticket_id'));
            $available = array_values(array_filter(
                $available,
                fn($t) => !isset($used[$t['ticket_id']])
            ));

            $placed = true;
            break;
        }

        // fallback: se proprio non entra nelle file riservate, prova altrove
        if (!$placed) {
            $priorityRows = getDocentiPreferredRuns($available);

            foreach ($priorityRows as $rowInfo) {
                $candidate = tryAllocateUnitInSpecificRow($unit, $rowInfo['runs'][0] ?? []);
                if (!$candidate) {
                    continue;
                }

                foreach (distributeCandidateToUsers($candidate, $unit['users']) as $row) {
                    $assignments[] = $row;
                }

                foreach ($candidate as $ticket) {
                    $docentiAssignedTickets[] = $ticket;
                }

                $used = array_flip(array_column($candidate, 'ticket_id'));
                $available = array_values(array_filter(
                    $available,
                    fn($t) => !isset($used[$t['ticket_id']])
                ));

                $placed = true;
                break;
            }
        }

        if (!$placed) {
            $who = $unit['affianca'] !== null
                ? 'affianca=' . $unit['affianca']
                : 'unit=' . $unit['unit_id'];

            $warnings[] = 'Nessun blocco docenti trovato per ' . $who
                . ' (' . $unit['macrogruppo']
                . ' / ' . $unit['gruppo']
                . ' / ' . $unit['size']
                . ' posti)';
        }
    }

    return [
        'assignments' => $assignments,
        'docenti_anchor' => $docentiAssignedTickets[0] ?? null,
        'docenti_tickets' => $docentiAssignedTickets,
    ];
}

function longestConsecutiveRunLength(array $zoneTickets): int
{
    if (!$zoneTickets) {
        return 0;
    }

    usort($zoneTickets, fn($a, $b) => $a['posto'] <=> $b['posto']);

    $best = 1;
    $current = 1;

    for ($i = 1; $i < count($zoneTickets); $i++) {
        if ((int)$zoneTickets[$i]['posto'] === (int)$zoneTickets[$i - 1]['posto'] + 1) {
            $current++;
            if ($current > $best) {
                $best = $current;
            }
        } else {
            $current = 1;
        }
    }

    return $best;
}

function docenteZonePriority(array $availableTickets): array
{
    $zones = groupTicketsByZone($availableTickets);
    $rows = [];

    foreach ($zones as $zoneKey => $zoneTickets) {
        $parts = explode('|', $zoneKey);
        if (count($parts) !== 3) {
            continue;
        }

        [$tribuna, $fila, $blocco] = $parts;
        $fila = (int)$fila;
        $blocco = (int)$blocco;

        $rows[] = [
            'zone_key' => $zoneKey,
            'tribuna' => $tribuna,
            'fila' => $fila,
            'blocco' => $blocco,
            'max_run' => longestConsecutiveRunLength($zoneTickets),
            'tickets' => $zoneTickets,
        ];
    }

    usort($rows, function (array $a, array $b): int {
        // 1. prima il blocco consecutivo più lungo
        if ($a['max_run'] !== $b['max_run']) {
            return $b['max_run'] <=> $a['max_run'];
        }

        // 2. poi preferisci file non troppo basse, ideale attorno a 5-7
        $scoreA = abs($a['fila'] - 6);
        $scoreB = abs($b['fila'] - 6);
        if ($scoreA !== $scoreB) {
            return $scoreA <=> $scoreB;
        }

        // 3. poi blocchi più centrali
        return abs($a['blocco'] - 2) <=> abs($b['blocco'] - 2);
    });

    return $rows;
}

function findBestCandidate(array $unit, array $availableTickets, ?array $docentiAnchor): ?array
{
    $zones = groupTicketsByZone($availableTickets);
    $candidates = [];

    foreach ($zones as $zoneTickets) {
        foreach (consecutiveWindows($zoneTickets, (int)$unit['size']) as $window) {
            $candidates[] = [
                'mode' => 'consecutive',
                'tickets' => $window,
            ];
        }
    }

    if (!$candidates) {
        foreach (nearbyRowWindows($zones, (int)$unit['size']) as $nearby) {
            $candidates[] = $nearby;
        }
    }

    if (!$candidates) {
        return null;
    }

    usort($candidates, function (array $a, array $b) use ($unit, $docentiAnchor): int {
        return candidateScoreExtended($unit, $b, $docentiAnchor)
            <=> candidateScoreExtended($unit, $a, $docentiAnchor);
    });

    return $candidates[0]['tickets'];
}

function distributeCandidateToUsers(array $candidate, array $users): array
{
    $out = [];
    $offset = 0;

    foreach ($users as $user) {
        $slice = array_slice($candidate, $offset, $user['numero_posti']);

        if (count($slice) !== $user['numero_posti']) {
            throw new RuntimeException('Distribuzione interna non coerente');
        }

        $offset += $user['numero_posti'];

        $out[] = [
            'macrogruppo' => $user['macrogruppo'],
            'gruppo' => $user['gruppo'],
            'email' => $user['email'],
            'display_name' => emailToDisplayName($user['email']),
            'numero_posti' => $user['numero_posti'],
            'affianca' => $user['affianca'],
            'tribuna' => $slice[0]['tribuna'],
            'fila' => $slice[0]['fila'],
            'blocco' => $slice[0]['blocco'],
            'posti' => array_column($slice, 'posto'),
            'pages' => array_column($slice, 'page'),
            'ticket_ids' => array_column($slice, 'ticket_id'),
            'fila_penalizzata' => $slice[0]['fila_penalizzata'] ? 1 : 0,
            'color' => groupColor($user['macrogruppo'], $user['gruppo']),
        ];
    }

    return $out;
}

function allocateTickets(array $tickets, array $users): array
{
    $units = buildAssignmentUnits($users);

    $docentiUnits = [];
    $ataUnits = [];
    $studentiUnits = [];

    foreach ($units as $unit) {
        $macro = strtolower((string)$unit['macrogruppo']);

        if ($macro === 'docenti') {
            $docentiUnits[] = $unit;
        } elseif ($macro === 'ata') {
            $ataUnits[] = $unit;
        } else {
            $studentiUnits[] = $unit;
        }
    }

    $available = $tickets;
    $assignments = [];
    $warnings = [];

    // 1. DOCENTI
    $docentiAlloc = allocateDocentiByRows($docentiUnits, $available, $warnings);
    $assignments = array_merge($assignments, $docentiAlloc['assignments']);
    $docentiAnchor = $docentiAlloc['docenti_anchor'] ?? null;
    $docentiAssignments = $docentiAlloc['assignments'] ?? [];

    // 2. ATA
    $ataAssignments = allocateAtaByReservedRows($ataUnits, $available, $warnings, $docentiAssignments);
    $assignments = array_merge($assignments, $ataAssignments);

    // 3. STUDENTI
    foreach ($studentiUnits as $unit) {
        $candidate = findBestCandidate($unit, $available, $docentiAnchor);

        if (!$candidate) {
            $who = $unit['affianca'] !== null
                ? 'affianca=' . $unit['affianca']
                : 'unit=' . $unit['unit_id'];

            $warnings[] = 'Nessun blocco studenti trovato per ' . $who
                . ' (' . $unit['macrogruppo']
                . ' / ' . $unit['gruppo']
                . ' / ' . $unit['size']
                . ' posti)';
            continue;
        }

        foreach (distributeCandidateToUsers($candidate, $unit['users']) as $row) {
            $assignments[] = $row;
        }

        $used = array_flip(array_column($candidate, 'ticket_id'));
        $available = array_values(array_filter(
            $available,
            fn($t) => !isset($used[$t['ticket_id']])
        ));
    }

    usort(
        $assignments,
        fn($a, $b) =>
        [
            macroPriority($a['macrogruppo']),
            subgroupPriority($a['gruppo']),
            $a['tribuna'],
            $a['fila'],
            $a['blocco'],
            implode(',', $a['posti']),
            $a['email'],
        ]
            <=>
            [
                macroPriority($b['macrogruppo']),
                subgroupPriority($b['gruppo']),
                $b['tribuna'],
                $b['fila'],
                $b['blocco'],
                implode(',', $b['posti']),
                $b['email'],
            ]
    );

    return [
        'assignments' => $assignments,
        'warnings' => $warnings,
        'unassigned_ticket_count' => count($available),
        'remaining_tickets' => $available,
    ];
}

function buildSeatMapData(array $tickets, array $assignments): array
{
    $map = [];

    foreach ($tickets as $t) {
        $key = $t['fila'] . '-' . $t['posto'];
        $map[$key] = [
            'fila' => $t['fila'],
            'posto' => $t['posto'],
            'tribuna' => $t['tribuna'],
            'blocco' => $t['blocco'],
            'ticket_id' => $t['ticket_id'],
            'page' => $t['page'],
            'assigned' => false,
            'display_name' => '',
            'email' => '',
            'color' => '',
            'tooltip' => sprintf(
                'Fila %d - Posto %d - BIGLIETTO PRESENTE - Pagina %d',
                $t['fila'],
                $t['posto'],
                $t['page']
            ),
        ];
    }

    foreach ($assignments as $assignmentIndex => $row) {
        foreach ($row['posti'] as $i => $posto) {
            $key = ((int) $row['fila']) . '-' . ((int) $posto);
            if (!isset($map[$key])) {
                continue;
            }

            $map[$key]['assigned'] = true;
            $map[$key]['assignment_index'] = $assignmentIndex;
            $map[$key]['display_name'] = $row['display_name'];
            $map[$key]['email'] = $row['email'];
            $map[$key]['macrogruppo'] = $row['macrogruppo'];
            $map[$key]['gruppo'] = $row['gruppo'];
            $map[$key]['color'] = $row['color'];
            $map[$key]['ticket_id'] = $row['ticket_ids'][$i] ?? '';
            $map[$key]['page'] = $row['pages'][$i] ?? '';
            $map[$key]['tooltip'] = sprintf(
                '%s | %s | %s | Fila %d Posto %d | Pagina %s',
                $row['display_name'],
                $row['macrogruppo'],
                $row['gruppo'],
                (int) $row['fila'],
                (int) $posto,
                (string) ($row['pages'][$i] ?? '')
            );
        }
    }

    ksort($map);
    return array_values($map);
}

function debugZones(array $tickets): array
{
    $zones = groupTicketsByZone($tickets);
    $out = [];

    foreach ($zones as $key => $zoneTickets) {
        $out[] = [
            'zona' => $key,
            'count' => count($zoneTickets),
            'posti' => implode(',', array_column($zoneTickets, 'posto')),
            'pagine' => implode(',', array_column($zoneTickets, 'page')),
        ];
    }

    return $out;
}

function swapAssignmentRows(array $assignments, int $idxA, int $idxB): array
{
    if (!isset($assignments[$idxA], $assignments[$idxB])) {
        throw new RuntimeException('Assegnazioni da scambiare non trovate');
    }

    if ($idxA === $idxB) {
        return array_values($assignments);
    }

    $a = $assignments[$idxA];
    $b = $assignments[$idxB];

    if ((int)($a['numero_posti'] ?? 0) !== (int)($b['numero_posti'] ?? 0)) {
        throw new RuntimeException('Puoi scambiare solo assegnazioni con lo stesso numero di posti');
    }

    foreach (['tribuna', 'fila', 'blocco', 'posti', 'pages', 'ticket_ids', 'fila_penalizzata'] as $field) {
        $tmp = $a[$field];
        $a[$field] = $b[$field];
        $b[$field] = $tmp;
    }

    $assignments[$idxA] = $a;
    $assignments[$idxB] = $b;

    return array_values($assignments);
}
