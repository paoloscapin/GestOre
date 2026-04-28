<?php

declare(strict_types=1);

require_once __DIR__ . '/../checkSession.php';

ruoloRichiesto('admin');

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../ticket_eventi_lib.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/pdf_tools.php';
require_once __DIR__ . '/send_mail_wrapper.php';
require_once __DIR__ . '/tickets_venue.php';

const APP_TITLE    = 'GestOre - Assegnazione biglietti Trentino Volley';
const STORAGE_DIR  = __DIR__ . '/data';
const UPLOAD_DIR   = STORAGE_DIR . '/uploads';
const OUTPUT_DIR   = STORAGE_DIR . '/output';
const PDF_OUT_DIR  = OUTPUT_DIR . '/pdf_email';
const TEMP_PDF_DIR = OUTPUT_DIR . '/tmp';
const LAST_RESULT_FILE = STORAGE_DIR . '/last_result.json';
const TRENTINO_VOLLEY_LOGO_URL = 'https://www.buonarroti.tn.it/GestOre/common/tickets/icon-trentinovolley.png';

date_default_timezone_set('Europe/Rome');

function h(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function ensureDirs(): void
{
    foreach ([STORAGE_DIR, UPLOAD_DIR, OUTPUT_DIR, PDF_OUT_DIR, TEMP_PDF_DIR] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if (!$items) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($dir);
}

function resetWorkingDirs(): void
{
    if (is_dir(UPLOAD_DIR)) {
        rrmdir(UPLOAD_DIR);
    }
    if (is_dir(OUTPUT_DIR)) {
        rrmdir(OUTPUT_DIR);
    }
    ensureDirs();
}

function saveLastResult(array $data): void
{
    file_put_contents(
        LAST_RESULT_FILE,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
    );
}

function loadLastResult(): ?array
{
    if (!is_file(LAST_RESULT_FILE)) {
        return null;
    }

    $json = file_get_contents(LAST_RESULT_FILE);
    if ($json === false || trim($json) === '') {
        return null;
    }

    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

function hydrateLoadedResult(array $result): array
{
    $assignments = isset($result['assignments']) && is_array($result['assignments'])
        ? array_values($result['assignments'])
        : [];

    $tickets = isset($result['parsed_tickets']) && is_array($result['parsed_tickets'])
        ? array_values($result['parsed_tickets'])
        : [];

    $ticketsRaw = isset($result['parsed_tickets_raw']) && is_array($result['parsed_tickets_raw'])
        ? array_values($result['parsed_tickets_raw'])
        : [];

    if (!$tickets && $ticketsRaw) {
        $venueCtx = ticketsVenuePrepareAllocationContext($ticketsRaw);
        $tickets = array_values($venueCtx['tickets'] ?? []);
        $result['tribuna'] = $venueCtx['tribuna'] ?? ($result['tribuna'] ?? '');
        $result['tribuna_profile'] = $venueCtx['profile'] ?? ($result['tribuna_profile'] ?? []);
        $result['excluded_tickets'] = array_values($venueCtx['excluded_tickets'] ?? []);
    }

    if ($tickets) {
        $tribuna = (string)($result['tribuna'] ?? ($tickets[0]['tribuna'] ?? ''));
        $profile = isset($result['tribuna_profile']) && is_array($result['tribuna_profile'])
            ? $result['tribuna_profile']
            : [];

        $result['assignments'] = $assignments;
        $result['parsed_tickets'] = $tickets;
        $result['tribuna'] = $tribuna;
        $result['tribuna_profile'] = $profile;
        $result['ticket_count'] = count($tickets);
        $result['excluded_ticket_count'] = count($result['excluded_tickets'] ?? []);
        $result['assignment_count'] = count($assignments);
        $result['row_count'] = tribunaRowCount($tribuna, $tickets);
        $result['seat_count'] = tribunaSeatCount($tickets, $profile);
        $result['seat_map'] = buildSeatMapData($tickets, $assignments);
        $result['debug_zones'] = debugZones($tickets);
        $result['unassigned_ticket_count'] = max(
            0,
            count($tickets) - array_sum(array_map(fn($row) => (int)($row['numero_posti'] ?? 0), $assignments))
        );
    }

    return $result;
}

function writeAssignmentsCsv(array $assignments, string $outPath): void
{
    $fh = fopen($outPath, 'wb');
    if (!$fh) {
        throw new RuntimeException('Impossibile scrivere il CSV assegnazioni');
    }

    fputcsv($fh, [
        'macrogruppo',
        'gruppo',
        'email',
        'display_name',
        'numero_posti',
        'affianca',
        'tribuna',
        'fila',
        'blocco',
        'posti',
        'pages',
        'ticket_ids',
        'fila_penalizzata',
    ]);

    foreach ($assignments as $row) {
        fputcsv($fh, [
            $row['macrogruppo'],
            $row['gruppo'],
            $row['email'],
            $row['display_name'],
            $row['numero_posti'],
            $row['affianca'],
            $row['tribuna'],
            $row['fila'],
            $row['blocco'],
            implode(',', $row['posti']),
            implode(',', $row['pages']),
            implode(',', $row['ticket_ids']),
            $row['fila_penalizzata'],
        ]);
    }

    fclose($fh);
}

function seatMapHasGapAfter(int $seat, string $tribuna): bool
  {
        if (ticketsVenueUsesSegmentedSeatMap($tribuna)) {
            return in_array($seat, [12, 42], true);
        }

        return false;
    }

function seatMapFixedSeatRange(string $tribuna): ?array
    {
        if (ticketsVenueUsesSegmentedSeatMap($tribuna)) {
            return [1, 54];
        }

        return null;
    }

function seatMapGapAfterSeats(string $tribuna): array
{
    if (ticketsVenueUsesSegmentedSeatMap($tribuna)) {
        return [12, 42];
    }

    return [];
}

function seatMapDisplaySeats(int $seatStart, int $seatEnd): array
{
    if ($seatEnd < $seatStart) {
        return [];
    }

    return range($seatEnd, $seatStart);
}

function resultVenueDisplayTitle(array $result): string
{
    $tribuna = trim((string)($result['tribuna'] ?? 'Tribuna'));
    $tickets = isset($result['parsed_tickets']) && is_array($result['parsed_tickets'])
        ? $result['parsed_tickets']
        : [];

    if (function_exists('ticketsVenueIsGradinata') && ticketsVenueIsGradinata($tribuna)) {
        $settori = [];

        foreach ($tickets as $ticket) {
            $settore = trim((string)($ticket['settore'] ?? ''));
            if ($settore !== '') {
                $settori[strtoupper($settore)] = true;
            }
        }

        if (count($settori) === 1) {
            return $tribuna . ' - Settore ' . array_key_first($settori);
        }
    }

    return $tribuna;
}

function ticketEventDisplayLabel(array $event): string
{
    $title = trim((string)($event['titolo'] ?? 'Evento senza titolo'));
    $luogo = trim((string)($event['luogo'] ?? ''));
    $date = trim((string)($event['data_evento'] ?? ''));
    $dateLabel = $date !== '' ? date('d/m/Y H:i', strtotime($date)) : '';

    $parts = array_filter([$title, $luogo, $dateLabel], fn($value) => trim((string)$value) !== '');
    return implode(' | ', $parts);
}

function rebuildResultArtifacts(array &$result, array $assignments): void
{
    $result['assignments'] = array_values($assignments);
    $result['assignment_count'] = count($assignments);
    $result['unassigned_ticket_count'] = max(0, (int)($result['ticket_count'] ?? 0) - array_sum(array_map(fn($r) => (int)($r['numero_posti'] ?? 0), $assignments)));
    $result['seat_map'] = buildSeatMapData($result['parsed_tickets'] ?? [], $result['assignments']);

    if (!empty($result['assignments_csv'])) {
        writeAssignmentsCsv($result['assignments'], $result['assignments_csv']);
    }

    $pdfPath = UPLOAD_DIR . '/tickets.pdf';
    if (is_file($pdfPath)) {
        $result['pdf_files'] = createPdfsPerEmail($pdfPath, $result['assignments'], PDF_OUT_DIR, TEMP_PDF_DIR);
    }
}

ensureDirs();
ticketEventiEnsureSchema();

$error = null;
$result = null;
$ticketEvents = ticketEventiGetEventsForAdmin((int)$__anno_scolastico_corrente_id);
$selectedEventId = 0;
$ticketEventsById = [];
foreach ($ticketEvents as $ticketEventRow) {
    $ticketEventsById[(int)($ticketEventRow['id'] ?? 0)] = $ticketEventRow;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $selectedEventId = isset($_POST['evento_id']) ? (int)$_POST['evento_id'] : 0;

        if (isset($_POST['action']) && $_POST['action'] === 'swap_assignments') {
            $result = $_SESSION['tickets_last_result'] ?? null;
            $assignments = $_SESSION['tickets_assignments'] ?? [];

            if (!$result || !$assignments) {
                throw new RuntimeException('Nessuna elaborazione disponibile per lo scambio');
            }

            $idxA = isset($_POST['swap_a']) ? (int) $_POST['swap_a'] : -1;
            $idxB = isset($_POST['swap_b']) ? (int) $_POST['swap_b'] : -1;
            $assignments = swapAssignmentRows($assignments, $idxA, $idxB);

            $_SESSION['tickets_assignments'] = $assignments;
            rebuildResultArtifacts($result, $assignments);
            $_SESSION['tickets_last_result'] = $result;
        }

        if (isset($_POST['action']) && $_POST['action'] === 'send_emails') {
            $lastResult = $_SESSION['tickets_last_result'] ?? loadLastResult();

            if (!$lastResult || empty($lastResult['assignments'])) {
                throw new RuntimeException('Nessuna elaborazione disponibile da inviare');
            }

            $emailResults = sendAllEmails($lastResult['assignments'], PDF_OUT_DIR);

            $lastResult['email_results'] = $emailResults;
            $lastResult['last_email_send'] = date('Y-m-d H:i:s');

            $_SESSION['tickets_assignments'] = $lastResult['assignments'];
            $_SESSION['tickets_last_result'] = $lastResult;
            saveLastResult($lastResult);

            $result = $lastResult;
        }

        if (isset($_POST['action']) && $_POST['action'] === 'process') {
            resetWorkingDirs();
            checkGhostscript();

            if (!isset($_FILES['tickets_pdf'])) {
                throw new RuntimeException('File mancanti');
            }

            if ($_FILES['tickets_pdf']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Errore upload PDF biglietti');
            }

            if ($selectedEventId <= 0) {
                throw new RuntimeException('Seleziona un evento con prenotazioni');
            }

            $pdfPath = UPLOAD_DIR . '/tickets.pdf';

            if (!move_uploaded_file($_FILES['tickets_pdf']['tmp_name'], $pdfPath)) {
                throw new RuntimeException('Impossibile salvare il PDF biglietti');
            }

            $selectedEvent = ticketEventiGetEventById($selectedEventId, (int)$__anno_scolastico_corrente_id);
            if (!$selectedEvent) {
                throw new RuntimeException('Evento non trovato');
            }

            $ticketsRaw = extractTicketsFromMultiPagePdf($pdfPath);
            $reservations = ticketEventiGetReservationsForAllocation($selectedEventId);
            $users = ticketReservationsToUsers($reservations);

            if (!$users) {
                throw new RuntimeException('Nessuna prenotazione attiva disponibile per l\'evento selezionato');
            }

            $venueCtx = ticketsVenuePrepareAllocationContext($ticketsRaw);
            $tickets  = $venueCtx['tickets'];

            $allocation  = allocateTickets($tickets, $users);
            $assignments = $allocation['assignments'];
            if (!empty($venueCtx['excluded_tickets'])) {
                $allocation['warnings'][] = 'Esclusi ' . count($venueCtx['excluded_tickets']) . ' biglietti da blocchi VIP/abbinamenti (R, S, T, X, Z).';
            }
            $assignmentsCsv = OUTPUT_DIR . '/assegnazioni.csv';
            writeAssignmentsCsv($assignments, $assignmentsCsv);

            $pdfFiles = createPdfsPerEmail($pdfPath, $assignments, PDF_OUT_DIR, TEMP_PDF_DIR);

            $_SESSION['tickets_assignments'] = $assignments;
            $lastResult = [
                'ticket_count'            => count($tickets),
                'ticket_count_raw'        => count($ticketsRaw),
                'excluded_ticket_count'   => count($venueCtx['excluded_tickets'] ?? []),
                'reservation_count'       => count($reservations),
                'reservation_event_id'    => $selectedEventId,
                'reservation_event_title' => (string)($selectedEvent['titolo'] ?? ''),
                'reservation_event_label' => ticketEventDisplayLabel($selectedEvent),
                'user_count'              => count($users),
                'assignment_count'        => count($assignments),
                'warnings'                => $allocation['warnings'],
                'unassigned_ticket_count' => $allocation['unassigned_ticket_count'],
                'assignments'             => $assignments,
                'assignments_csv'         => $assignmentsCsv,
                'pdf_files'               => $pdfFiles,
                'seat_map'                => buildSeatMapData($tickets, $assignments),
                'tribuna'                 => $venueCtx['tribuna'] ?? ($tickets[0]['tribuna'] ?? ''),
                'tribuna_profile'         => $venueCtx['profile'] ?? [],
                'row_count'               => tribunaRowCount($tickets[0]['tribuna'] ?? '', $tickets),
                'seat_count'              => tribunaSeatCount($tickets),
                'parsed_tickets'          => $tickets,
                'parsed_tickets_raw'      => $ticketsRaw,
                'excluded_tickets'        => $venueCtx['excluded_tickets'] ?? [],
                'debug_zones'             => debugZones($tickets),
                'last_update'             => date('Y-m-d H:i:s'),
            ];

            $_SESSION['tickets_assignments'] = $assignments;
            $_SESSION['tickets_last_result'] = $lastResult;
            saveLastResult($lastResult);

            $result = $lastResult;
        }
    } else {
        if (!empty($_SESSION['tickets_last_result'])) {
            $result = hydrateLoadedResult($_SESSION['tickets_last_result']);
            $_SESSION['tickets_last_result'] = $result;
            $selectedEventId = (int)($result['reservation_event_id'] ?? 0);
        } else {
            $result = loadLastResult();
            if ($result) {
                $result = hydrateLoadedResult($result);
                $_SESSION['tickets_last_result'] = $result;
                $selectedEventId = (int)($result['reservation_event_id'] ?? 0);
                if (!empty($result['assignments'])) {
                    $_SESSION['tickets_assignments'] = $result['assignments'];
                }
            }
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$selectedTicketEvent = $selectedEventId > 0 ? ($ticketEventsById[$selectedEventId] ?? null) : null;
?>
<!doctype html>
<html lang="it">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(APP_TITLE) ?></title>
    <?php require_once __DIR__ . '/../header-common.php'; ?>
    <?php require_once __DIR__ . '/../style.php'; ?>
    <link rel="icon" type="image/png" href="icon-ticket.png">
    <link rel="stylesheet" href="tickets.css?v=1">
</head>

<body>
    <?php require_once __DIR__ . '/../header-admin.php'; ?>
    <div class="wrap">
        <div class="hero" style="display:flex; align-items:center; justify-content:space-between; gap:16px;">
            <div>
                <h1><?= h(APP_TITLE) ?></h1>
                <p>Elabori, controlli mappa e PDF creati, poi invii solo all’indirizzo di test impostato nel wrapper.</p>
            </div>

            <div style="flex:0 0 auto;">
                <img src="<?= h(TRENTINO_VOLLEY_LOGO_URL) ?>"
                    alt="Trentino Volley"
                    style="display:block; max-height:80px; width:auto; background:#fff; border-radius:12px; padding:6px;">
            </div>
        </div>
        <div class="grid">
            <div>
                <div class="box">
                    <h2>Caricamento</h2>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="process">
                        <div class="form-row">
                            <label class="label">PDF multipagina biglietti</label>
                            <input type="file" name="tickets_pdf" accept="application/pdf" required>
                        </div>
                        <div class="form-row">
                            <label class="label">Evento con prenotazioni</label>
                            <select id="evento_id_select" name="evento_id" required style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;">
                                <option value="">Seleziona evento</option>
                                <?php foreach ($ticketEvents as $event): ?>
                                    <?php $eventId = (int)($event['id'] ?? 0); ?>
                                    <option value="<?= $eventId ?>" <?= $selectedEventId === $eventId ? 'selected' : '' ?>>
                                        <?= h(ticketEventDisplayLabel($event)) ?> - prenotazioni: <?= h((string)($event['prenotazioni_attive'] ?? 0)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-row" id="evento_summary_box" style="<?= $selectedTicketEvent ? '' : 'display:none;' ?>">
                            <div style="padding:12px 14px;border:1px solid #dbe3ef;border-radius:12px;background:#f8fbff;">
                                <div style="font-weight:700;margin-bottom:6px;" id="evento_summary_title">
                                    <?= h((string)($selectedTicketEvent['titolo'] ?? '')) ?>
                                </div>
                                <div class="muted" id="evento_summary_datetime">
                                    <?php if ($selectedTicketEvent): ?>
                                        <?= h(ticketEventiFormatDateTime((string)($selectedTicketEvent['data_evento'] ?? ''))) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="muted" id="evento_summary_location">
                                    <?php if ($selectedTicketEvent): ?>
                                        <?= h((string)($selectedTicketEvent['luogo'] ?? '')) ?>
                                    <?php endif; ?>
                                </div>
                                <div style="margin-top:6px;font-weight:600;" id="evento_summary_reservations">
                                    <?php if ($selectedTicketEvent): ?>
                                        <?= h('Prenotazioni attive: ' . (string)($selectedTicketEvent['prenotazioni_attive'] ?? 0)) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <script>
                            (function () {
                                const select = document.getElementById('evento_id_select');
                                const box = document.getElementById('evento_summary_box');
                                const title = document.getElementById('evento_summary_title');
                                const datetime = document.getElementById('evento_summary_datetime');
                                const location = document.getElementById('evento_summary_location');
                                const reservations = document.getElementById('evento_summary_reservations');

                                if (!select) {
                                    return;
                                }

                                const eventMap = {
                                    <?php foreach ($ticketEvents as $event): ?>
                                    <?= (int)($event['id'] ?? 0) ?>: {
                                        title: <?= json_encode((string)($event['titolo'] ?? '')) ?>,
                                        datetime: <?= json_encode(ticketEventiFormatDateTime((string)($event['data_evento'] ?? ''))) ?>,
                                        location: <?= json_encode((string)($event['luogo'] ?? '')) ?>,
                                        reservations: <?= json_encode('Prenotazioni attive: ' . (string)($event['prenotazioni_attive'] ?? 0)) ?>
                                    },
                                    <?php endforeach; ?>
                                };

                                function refreshSummary() {
                                    const eventId = select.value;
                                    const data = eventMap[eventId];

                                    if (!data) {
                                        box.style.display = 'none';
                                        title.textContent = '';
                                        datetime.textContent = '';
                                        location.textContent = '';
                                        reservations.textContent = '';
                                        return;
                                    }

                                    box.style.display = '';
                                    title.textContent = data.title || '';
                                    datetime.textContent = data.datetime || '';
                                    location.textContent = data.location || '';
                                    reservations.textContent = data.reservations || '';
                                }

                                select.addEventListener('change', refreshSummary);
                                refreshSummary();
                            })();
                        </script>
                        <div class="actions">
                            <button type="submit">Elabora assegnazione</button>
                        </div>
                    </form>
                </div>

                <div class="box">
                    <h3>Sorgente prenotazioni</h3>
                    <p>Le assegnazioni usano le prenotazioni attive dell’evento selezionato.</p>
                    <p class="muted">Per i docenti, il sottogruppo deriva dal dipartimento ricavato da <code>profilo_docente</code> dell’anno corrente.</p>
                    <p class="muted">Invio reale disattivato: il wrapper usa <code>MAIL_TEST_OVERRIDE</code>.</p>
                </div>

                <?php if ($error): ?>
                    <div class="box error"><strong>Errore:</strong> <?= h($error) ?></div>
                <?php endif; ?>

                <?php if ($result): ?>
                    <div class="box ok">
                        <strong>Elaborazione disponibile.</strong><br>

                        <?php
                        $csvPath = $result['assignments_csv'] ?? '';
                        $csvName = $csvPath ? basename($csvPath) : '';
                        ?>

                        <div style="margin-top:6px;">
                            <?php if (!empty($result['reservation_event_label'])): ?>
                                <div style="margin-bottom:8px;">
                                    <strong>Evento prenotazioni:</strong>
                                    <span style="font-weight:600;"><?= h((string)$result['reservation_event_label']) ?></span>
                                </div>
                            <?php endif; ?>
                            <div>
                                <strong>CSV assegnazioni:</strong>
                                <span style="font-weight:600;"><?= h($csvName) ?></span>
                            </div>

                            <div style="margin-top:6px;background:#eef2f7;border:1px solid #dbe3ef;border-radius:10px;padding:8px 10px;font-family:monospace;font-size:12px;color:#64748b;overflow-wrap:anywhere;">
                                <?= h($csvPath) ?>
                            </div>

                            <?php if (!empty($csvPath)): ?>
                                <?php $csvUrl = str_replace($_SERVER['DOCUMENT_ROOT'], '', $csvPath); ?>
                                <div style="margin-top:8px;">
                                    <a href="<?= h($csvUrl) ?>"
                                        target="_blank"
                                        style="display:inline-block;padding:6px 12px;background:#2563eb;color:#fff;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;">
                                        ⬇ Scarica CSV
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php
                        $dtUpdate = null;
                        if (!empty($result['last_update'])) {
                            $dtUpdate = new DateTime($result['last_update']);
                            $dtUpdate->setTimezone(new DateTimeZone('Europe/Rome'));
                        }

                        $dtMail = null;
                        if (!empty($result['last_email_send'])) {
                            $dtMail = new DateTime($result['last_email_send']);
                            $dtMail->setTimezone(new DateTimeZone('Europe/Rome'));
                        }
                        ?>

                        <?php if ($dtUpdate): ?>
                            <div class="muted" style="margin-top:8px;">Ultima elaborazione: <?= $dtUpdate->format('d/m/Y H:i') ?></div>
                        <?php endif; ?>

                        <?php if ($dtMail): ?>
                            <div class="muted">Ultimo invio email: <?= $dtMail->format('d/m/Y H:i') ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($result['warnings'])): ?>
                    <div class="box warn">
                        <strong>Avvisi</strong>
                        <ul>
                            <?php foreach ($result['warnings'] as $w): ?>
                                <li><?= h($w) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($result['assignments'])): ?>
                    <div class="box">
                        <h3>Invio email</h3>
                        <p class="muted">I PDF allegati sono già stati creati. Il pulsante invia tutto all’indirizzo di test configurato nel wrapper.</p>
                        <form method="post">
                            <input type="hidden" name="action" value="send_emails">
                            <button type="submit" class="secondary">Invia email di test</button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if ($result): ?>
                    <div class="box">
                        <h3>Riepilogo</h3>
                        <div class="left-summary-stats">
                            <div class="stat">
                                <div class="n"><?= (int)($result['ticket_count'] ?? 0) ?></div>
                                <div class="k">Biglietti allocabili</div>
                            </div>
                            <div class="stat">
                                <div class="n"><?= (int)($result['excluded_ticket_count'] ?? 0) ?></div>
                                <div class="k">Biglietti esclusi</div>
                            </div>
                            <div class="stat">
                                <div class="n"><?= (int)($result['user_count'] ?? 0) ?></div>
                                <div class="k">Utenti letti</div>
                            </div>
                            <div class="stat">
                                <div class="n"><?= (int)($result['assignment_count'] ?? 0) ?></div>
                                <div class="k">Assegnazioni</div>
                            </div>
                            <div class="stat">
                                <div class="n"><?= (int)($result['unassigned_ticket_count'] ?? 0) ?></div>
                                <div class="k">Biglietti liberi</div>
                            </div>
                            <div class="stat">
                                <div class="n"><?= (int)($result['ticket_count_raw'] ?? 0) ?></div>
                                <div class="k">Biglietti totali PDF</div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="right-main-col">
                <?php if ($result): ?>

                    <div class="top-results-row">

                        <?php if (!empty($result['email_results'])): ?>
                            <div class="box email-results-box">
                                <h2>Esito invio email</h2>
                                <div class="email-results-wrap">
                                    <table class="email-results-table">
                                        <thead>
                                            <tr>
                                                <th>Email originale</th>
                                                <th>Inviata a</th>
                                                <th>Esito</th>
                                                <th>Dettaglio</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($result['email_results'] as $er): ?>
                                                <tr>
                                                    <td><?= h($er['email_originale']) ?></td>
                                                    <td><?= h($er['email_inviata_a']) ?></td>
                                                    <td class="<?= !empty($er['ok']) ? 'mail-res-ok' : 'mail-res-ko' ?>">
                                                        <?= !empty($er['ok']) ? 'OK' : 'ERRORE' ?>
                                                    </td>
                                                    <td><?= h($er['detail']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($result['pdf_files'])): ?>
                            <div class="box pdf-created-box">
                                <h2>PDF creati</h2>
                                <ul class="pdf-created-list">
                                    <?php foreach ($result['pdf_files'] as $item): ?>
                                        <?php
                                        $path = is_array($item) ? ($item['path'] ?? $item['file'] ?? null) : $item;
                                        if (!$path || !is_string($path)) {
                                            continue;
                                        }
                                        $fileName = basename($path);
                                        $url = str_replace($_SERVER['DOCUMENT_ROOT'], '', $path);
                                        ?>
                                        <li>
                                            <span class="pdf-created-name"><?= h($fileName) ?></span>
                                            <a class="pdf-created-download"
                                                href="<?= h($url) ?>"
                                                target="_blank"
                                                title="Scarica PDF">📥</a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                    </div>

                    <?php if (!empty($result['seat_map'])): ?>
                        <div class="box">
                            <h2>Anteprima grafica tribuna</h2>
                            <div class="tribuna-wrap">
                                <div class="tribuna-title">
                                    <?= h(resultVenueDisplayTitle($result)) ?>
                                    <span class="pill"><?= (int)($result['row_count'] ?? 10) ?> file · <?= (int)($result['seat_count'] ?? 54) ?> posti max</span>
                                </div>

                                <div class="stage">
                                    <div class="stage-bar">CAMPO / PALCO</div>
                                </div>

                                <div class="legend">
                                    <div class="legend-item"><span class="legend-dot" style="background:#2563eb"></span>Docenti Info</div>
                                    <div class="legend-item"><span class="legend-dot" style="background:#06b6d4"></span>Docenti CAT</div>
                                    <div class="legend-item"><span class="legend-dot" style="background:#8b5cf6"></span>Docenti Mecc</div>
                                    <div class="legend-item"><span class="legend-dot" style="background:#10b981"></span>Docenti Chim</div>
                                    <div class="legend-item"><span class="legend-dot" style="background:#f59e0b"></span>Docenti Elet</div>
                                    <div class="legend-item"><span class="legend-dot" style="background:#6366f1"></span>Docenti Altri</div>
                                    <div class="legend-item"><span class="legend-dot" style="background:#ef4444"></span>ATA</div>
                                    <div class="legend-item"><span class="legend-dot" style="background:#22c55e"></span>Studenti</div>
                                    <div class="legend-item"><span class="legend-dot" style="background:#94a3b8"></span>Biglietto presente non assegnato</div>
                                    <div class="legend-item"><span class="legend-dot" style="background:#f1f5f9"></span>Nessun biglietto</div>
                                </div>

                                <?php
                                $seatMapIndexed = seatMapCellIndex($result['seat_map'] ?? []);
                                $blocks = seatMapBlocks($result['parsed_tickets'] ?? [], (string)($result['tribuna'] ?? ''));
                                $gapAfterSeats = seatMapGapAfterSeats((string)($result['tribuna'] ?? ''));
                                ?>
                                <div style="display:flex; flex-wrap:wrap; gap:18px; align-items:flex-start;">
                                    <?php foreach ($blocks as $blockInfo): ?>
                                        <?php
                                    $block = (int)$blockInfo['block'];
                                    $rowCount = (int)($result['row_count'] ?? 10);

                                    $fixedRange = seatMapFixedSeatRange((string)($result['tribuna'] ?? ''));
                                    if ($fixedRange !== null) {
                                        [$seatStart, $seatEnd] = $fixedRange;
                                    } else {
                                        $seatStart = max(1, (int)($blockInfo['min_seat'] ?? 1));
                                        $seatEnd = max($seatStart, (int)($blockInfo['max_seat'] ?? 1));
                                    }

                                    $displaySeats = seatMapDisplaySeats($seatStart, $seatEnd);
                                    $seatCount = count($displaySeats);
                                    $gapCount = count(array_filter(
                                        $gapAfterSeats,
                                        fn($gapSeat) => $gapSeat >= $seatStart && $gapSeat < $seatEnd
                                    ));
                                    $gridTemplate = '66px ' . implode(' ', array_fill(0, $seatCount, 'minmax(24px, 1fr)'));
                                    if ($gapCount > 0) {
                                        $gridTemplate .= ' ' . implode(' ', array_fill(0, $gapCount, '18px'));
                                    }
                                    $blockLabel = 'Blocco ' . $block;
                                        ?>

                                        <div style="border:1px solid #dbe3ef; border-radius:16px; padding:12px; background:#fff;">
                                            <?php if (count($blocks) > 1): ?>
                                                <div style="font-weight:700; margin-bottom:8px; text-align:center;"><?= h($blockLabel) ?></div>
                                            <?php endif; ?>

                                            <div class="seat-grid" style="grid-template-columns: <?= h($gridTemplate) ?>; min-width: <?= 66 + ($seatCount * 28) + ($gapCount * 18) ?>px;">
                                                <div class="row-label"></div>
                                                <?php foreach ($displaySeats as $seatIndex => $seat): ?>
                                                    <div class="seat-num"><?= $seat ?></div>
                                                    <?php
                                                    $nextSeat = $displaySeats[$seatIndex + 1] ?? null;
                                                    $gapBetweenSeats = $nextSeat !== null
                                                        && in_array(min($seat, $nextSeat), $gapAfterSeats, true)
                                                        && abs($seat - $nextSeat) === 1;
                                                    ?>
                                                    <?php if ($gapBetweenSeats): ?>
                                                        <div class="seat-gap" aria-hidden="true"></div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>

                                                <?php for ($fila = 1; $fila <= $rowCount; $fila++): ?>
                                                    <div class="row-label">Fila <?= $fila ?></div>

                                                    <?php foreach ($displaySeats as $seatIndex => $seat): ?>
                                                        <?php
                                                            $tribunaNow = (string)($result['tribuna'] ?? '');

                                                            if (ticketsVenueUsesSegmentedSeatMap($tribunaNow)) {
                                                                $seatBlockForMap = seatBlock((int)$seat);
                                                            } else {
                                                                $seatBlockForMap = $block;
                                                            }

                                                            $cell = $seatMapIndexed[$seatBlockForMap][$fila][$seat] ?? null;
                                                            ?>

                                                        <?php if ($cell): ?>
                                                            <?php
                                                            $classes = ['seat'];
                                                            if (!empty($cell['assigned'])) {
                                                                $classes[] = 'assigned';
                                                            } else {
                                                                $classes[] = 'available-ticket';
                                                            }
                                                            ?>
                                                            <div
                                                                class="<?= h(implode(' ', $classes)) ?>"
                                                                style="<?= !empty($cell['assigned']) ? 'background:' . h($cell['color']) . ';' : '' ?>"
                                                                data-tooltip="<?= h($cell['tooltip']) ?>"
                                                                <?php if (!empty($cell['assigned'])): ?>
                                                                data-assignment-index="<?= (int)($cell['assignment_index'] ?? -1) ?>"
                                                                data-display-name="<?= h($cell['display_name'] ?? '') ?>"
                                                                <?php endif; ?>><?= $seat ?></div>
                                                        <?php else: ?>
                                                            <div
                                                                class="seat no-ticket"
                                                                data-tooltip="Blocco <?= $block ?> - Fila <?= $fila ?> - Posto <?= $seat ?> - NESSUN BIGLIETTO"><?= $seat ?></div>
                                                        <?php endif; ?>
                                                        <?php
                                                        $nextSeat = $displaySeats[$seatIndex + 1] ?? null;
                                                        $gapBetweenSeats = $nextSeat !== null
                                                            && in_array(min($seat, $nextSeat), $gapAfterSeats, true)
                                                            && abs($seat - $nextSeat) === 1;
                                                        ?>
                                                        <?php if ($gapBetweenSeats): ?>
                                                            <div class="seat-gap" aria-hidden="true"></div>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($result['assignments'])): ?>
                        <div class="box">
                            <h2>Assegnazioni</h2>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Macrogruppo</th>
                                            <th>Gruppo</th>
                                            <th>Nome</th>
                                            <th>Email</th>
                                            <th>Posti</th>
                                            <th>Affianca</th>
                                            <th>Tribuna</th>
                                            <th>Fila</th>
                                            <th>Blocco</th>
                                            <th>Posti assegnati</th>
                                            <th>Pagine</th>
                                            <th>Ticket</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($result['assignments'] as $row): ?>
                                            <tr>
                                                <td><?= h($row['macrogruppo']) ?></td>
                                                <td><?= h($row['gruppo']) ?></td>
                                                <td><?= h($row['display_name']) ?></td>
                                                <td><?= h($row['email']) ?></td>
                                                <td><?= (int)$row['numero_posti'] ?></td>
                                                <td><?= h((string)$row['affianca']) ?></td>
                                                <td><?= h($row['tribuna']) ?></td>
                                                <td><?= (int)$row['fila'] ?></td>
                                                <td><?= h(ticketBlockDisplayLabel($row)) ?></td>
                                                <td><?= h(implode(', ', $row['posti'])) ?></td>
                                                <td><?= h(implode(', ', $row['pages'])) ?></td>
                                                <td><?= h(implode(', ', $row['ticket_ids'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>

        <div class="seat-tooltip" id="seatTooltip"></div>

        <form method="post" id="swapForm" style="display:none;">
            <input type="hidden" name="action" value="swap_assignments">
            <input type="hidden" name="swap_a" id="swapA" value="">
            <input type="hidden" name="swap_b" id="swapB" value="">
        </form>

        <div id="seatMenu" style="display:none; position:fixed; z-index:10000; background:#fff; border:1px solid #dbe3ef; border-radius:12px; box-shadow:0 10px 24px rgba(15,23,42,.18); min-width:220px; overflow:hidden;">
            <div id="seatMenuTitle" style="padding:10px 12px; font-weight:700; border-bottom:1px solid #e5e7eb; background:#f8fafc;"></div>
            <button type="button" id="seatMenuAction" style="display:block; width:100%; border:0; background:#2563eb; color:#fff; border-radius:0; box-shadow:none; text-align:left; padding:12px;">Azione</button>
            <button type="button" id="seatMenuCancel" style="display:block; width:100%; border:0; background:#fff; color:#111827; border-radius:0; box-shadow:none; text-align:left; padding:12px; border-top:1px solid #e5e7eb;">Chiudi</button>
        </div>

        <div id="swapStatus" style="display:none; position:fixed; left:16px; bottom:16px; z-index:9998; background:#111827; color:#fff; padding:10px 14px; border-radius:999px; box-shadow:0 12px 24px rgba(0,0,0,.22); font-size:13px;"></div>

        <script>
            (function() {
                const tooltip = document.getElementById('seatTooltip');
                const seatMenu = document.getElementById('seatMenu');
                const seatMenuTitle = document.getElementById('seatMenuTitle');
                const seatMenuAction = document.getElementById('seatMenuAction');
                const seatMenuCancel = document.getElementById('seatMenuCancel');
                const swapStatus = document.getElementById('swapStatus');
                const swapForm = document.getElementById('swapForm');
                const swapA = document.getElementById('swapA');
                const swapB = document.getElementById('swapB');
                let selectedSwap = null;
                let currentSeat = null;

                document.querySelectorAll('.seat[data-tooltip]').forEach(seat => {
                    seat.addEventListener('mouseenter', function(e) {
                        if (seatMenu.style.display === 'block') return;
                        tooltip.textContent = this.getAttribute('data-tooltip') || '';
                        tooltip.style.display = 'block';
                        move(e);
                    });
                    seat.addEventListener('mousemove', move);
                    seat.addEventListener('mouseleave', function() {
                        tooltip.style.display = 'none';
                    });

                    if (seat.dataset.assignmentIndex !== undefined) {
                        seat.style.cursor = 'pointer';
                        seat.addEventListener('click', function(e) {
                            e.preventDefault();
                            tooltip.style.display = 'none';
                            openMenu(this, e.clientX, e.clientY);
                        });
                    }
                });

                document.addEventListener('click', function(e) {
                    if (!seatMenu.contains(e.target) && !e.target.closest('.seat[data-assignment-index]')) {
                        closeMenu();
                    }
                });

                seatMenuCancel.addEventListener('click', closeMenu);

                function move(e) {
                    if (tooltip.style.display !== 'block') return;
                    tooltip.style.left = (e.clientX + 14) + 'px';
                    tooltip.style.top = (e.clientY + 14) + 'px';
                }

                function openMenu(seat, x, y) {
                    currentSeat = seat;
                    const idx = Number(seat.dataset.assignmentIndex);
                    const name = seat.dataset.displayName || 'Assegnazione';
                    seatMenuTitle.textContent = name;

                    if (selectedSwap === null) {
                        seatMenuAction.textContent = 'Seleziona per scambio';
                        seatMenuAction.onclick = function() {
                            selectedSwap = idx;
                            showStatus('Selezionato per scambio: ' + name);
                            closeMenu();
                        };
                    } else if (selectedSwap === idx) {
                        seatMenuAction.textContent = 'Annulla selezione';
                        seatMenuAction.onclick = function() {
                            selectedSwap = null;
                            showStatus('Selezione annullata');
                            closeMenu();
                        };
                    } else {
                        seatMenuAction.textContent = 'Scambia con selezionato';
                        seatMenuAction.onclick = function() {
                            if (!window.confirm('Confermi lo scambio tra le due assegnazioni?')) {
                                return;
                            }
                            swapA.value = String(selectedSwap);
                            swapB.value = String(idx);
                            swapForm.submit();
                        };
                    }

                    seatMenu.style.display = 'block';
                    seatMenu.style.left = Math.min(x + 10, window.innerWidth - 240) + 'px';
                    seatMenu.style.top = Math.min(y + 10, window.innerHeight - 160) + 'px';
                }

                function closeMenu() {
                    seatMenu.style.display = 'none';
                    currentSeat = null;
                }

                function showStatus(text) {
                    swapStatus.textContent = text;
                    swapStatus.style.display = 'block';
                    clearTimeout(showStatus._timer);
                    showStatus._timer = setTimeout(() => {
                        swapStatus.style.display = 'none';
                    }, 2400);
                }
            })();
        </script>
</body>

</html>
