<?php
date_default_timezone_set('Europe/Rome');

$ICS_URL = 'https://calendar.google.com/calendar/ical/istituto.tecnico%40buonarroti.tn.it/public/basic.ics';
$AUTO_RELOAD_SECONDS = 300;

$dayOffset = isset($_GET['d']) ? (int)$_GET['d'] : 0;
if ($dayOffset < 0) $dayOffset = 0;
if ($dayOffset > 30) $dayOffset = 30;

function fetchIcs($url) {
    return @file_get_contents($url, false, stream_context_create([
        'http' => ['timeout' => 10, 'user_agent' => 'Buonarroti Calendar Viewer']
    ]));
}

function unfoldIcsLines($ics) {
    $raw = preg_split("/\r\n|\n|\r/", $ics);
    $lines = [];
    foreach ($raw as $line) {
        if ($line === '') continue;
        if (!empty($lines) && ($line[0] === ' ' || $line[0] === "\t")) {
            $lines[count($lines)-1] .= substr($line, 1);
        } else {
            $lines[] = $line;
        }
    }
    return $lines;
}

function eventStatusClass($event) {
    $now = new DateTime('now', new DateTimeZone('Europe/Rome'));

    if (!empty($event['allDay'])) return 'status-current';

    $start = $event['start'];
    $end = !empty($event['end']) ? $event['end'] : clone $start;

    if ($end < $now) return 'status-past';
    if ($start <= $now && $end >= $now) return 'status-current';

    return 'status-future';
}

function cleanIcsText($text) {
    return trim(str_replace(['\\n','\\,','\\;','\\\\'], ["\n",',',';','\\'], $text));
}

function parseIcsDate($value, $params = '') {
    $tz = new DateTimeZone('Europe/Rome');

    if (str_contains($params, 'VALUE=DATE')) {
        return DateTime::createFromFormat('Ymd H:i:s', $value.' 00:00:00', $tz);
    }

    if (str_ends_with($value, 'Z')) {
        $dt = DateTime::createFromFormat('Ymd\THis\Z', $value, new DateTimeZone('UTC'));
        if ($dt) $dt->setTimezone($tz);
        return $dt;
    }

    return DateTime::createFromFormat('Ymd\THis', $value, $tz)
        ?: DateTime::createFromFormat('Ymd', $value, $tz);
}

function parseIcsEvents($ics) {
    $lines = unfoldIcsLines($ics);
    $events = [];
    $event = null;

    foreach ($lines as $line) {
        if ($line === 'BEGIN:VEVENT') {
            $event = [];
            continue;
        }

        if ($line === 'END:VEVENT') {
            if (!empty($event['summary']) && !empty($event['start'])) {
                $events[] = $event;
            }
            $event = null;
            continue;
        }

        if ($event === null) continue;

        [$left, $value] = array_pad(explode(':', $line, 2), 2, '');
        [$key, $params] = array_pad(explode(';', $left, 2), 2, '');
        $key = strtoupper($key);

        if ($key === 'SUMMARY') $event['summary'] = cleanIcsText($value);
        if ($key === 'LOCATION') $event['location'] = cleanIcsText($value);
        if ($key === 'DTSTART') {
            $event['start'] = parseIcsDate($value, $params);
            $event['allDay'] = str_contains($params, 'VALUE=DATE');
        }
        if ($key === 'DTEND') $event['end'] = parseIcsDate($value, $params);
    }

    usort($events, function($a, $b) {
    $cmp = $a['start'] <=> $b['start'];
    if ($cmp !== 0) return $cmp;

    $cmp = strcmp($a['summary'] ?? '', $b['summary'] ?? '');
    if ($cmp !== 0) return $cmp;

    return strcmp($a['location'] ?? '', $b['location'] ?? '');
});
    return $events;
}

function eventTypeClass($title) {
    $t = mb_strtoupper($title);
    if (str_contains($t, 'ESAME') || str_contains($t, 'COMMISSIONE')) return 'type-esame';
    if (str_contains($t, 'COLLEGIO')) return 'type-collegio';
    if (str_contains($t, 'CDC') || str_contains($t, 'CONSIGLIO')) return 'type-cdc';
    if (str_contains($t, 'DIPARTIMENTO')) return 'type-dipartimento';
    return 'type-altro';
}

function formatEventTime($event) {
    if (!empty($event['allDay'])) return 'Tutto il giorno';
    $start = $event['start']->format('H:i');
    $end = !empty($event['end']) ? $event['end']->format('H:i') : '';
    return $end ? "$start - $end" : $start;
}

function dayTitle($targetDay, $dayOffset) {
    if ($dayOffset === 0) return 'OGGI';

    $tomorrow = (new DateTime('tomorrow', new DateTimeZone('Europe/Rome')))->format('Y-m-d');
    if ($targetDay->format('Y-m-d') === $tomorrow) return 'DOMANI';

    $days = ['DOMENICA','LUNEDÌ','MARTEDÌ','MERCOLEDÌ','GIOVEDÌ','VENERDÌ','SABATO'];
    $months = ['','GENNAIO','FEBBRAIO','MARZO','APRILE','MAGGIO','GIUGNO','LUGLIO','AGOSTO','SETTEMBRE','OTTOBRE','NOVEMBRE','DICEMBRE'];

    return $days[(int)$targetDay->format('w')] . ' ' . $targetDay->format('j') . ' ' . $months[(int)$targetDay->format('n')];
}

$ics = fetchIcs($ICS_URL);
$events = $ics ? parseIcsEvents($ics) : [];

$targetDay = (new DateTime('today', new DateTimeZone('Europe/Rome')))->modify("+$dayOffset days");
$dayStart = clone $targetDay;
$dayEnd = (clone $dayStart)->modify('+1 day');

$events = array_values(array_filter($events, function($e) use ($dayStart, $dayEnd) {
    return $e['start'] >= $dayStart && $e['start'] < $dayEnd;
}));

$mid = ceil(count($events) / 2);
$columns = [
    array_slice($events, 0, $mid),
    array_slice($events, $mid)
];

$prevOffset = $dayOffset;
$nextOffset = $dayOffset;

/* giorno precedente */
$tmp = (new DateTime('today', new DateTimeZone('Europe/Rome')))
    ->modify('+' . max(0, $dayOffset - 1) . ' days');

do {
    $prevOffset--;
    if ($prevOffset < 0) {
        $prevOffset = 0;
        break;
    }

    $tmp = (new DateTime('today', new DateTimeZone('Europe/Rome')))
        ->modify("+{$prevOffset} days");

} while ($tmp->format('N') == 7); // domenica

/* giorno successivo */
do {
    $nextOffset++;

    $tmp = (new DateTime('today', new DateTimeZone('Europe/Rome')))
        ->modify("+{$nextOffset} days");

} while ($tmp->format('N') == 7 && $nextOffset <= 30);
$currentDayTitle = dayTitle($targetDay, $dayOffset);
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="<?= (int)$AUTO_RELOAD_SECONDS ?>;url=<?= htmlspecialchars(basename($_SERVER['PHP_SELF'])) ?>">
<title>Calendario Istituto</title>

<style>
* { box-sizing: border-box; }

html, body {
    margin: 0;
    width: 100%;
    min-height: 100%;
    font-family: Arial, Helvetica, sans-serif;
    background: linear-gradient(135deg, #eef7f8 0%, #f8fbff 58%, #eef2f7 100%);
    color: #111827;
}

.page {
    width: 100vw;
    min-height: 100vh;
    padding: 6px 12px;
}

.nav-day {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin: 0 0 6px;
}

.day-title {
    min-width: 220px;
    text-align: center;
    font-size: 18px;
    font-weight: 900;
    margin: 0;
}

.arrow {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: #111827;
    color: white;
    text-decoration: none;
    font-size: 28px;
    line-height: 32px;
    font-weight: 900;
    text-align: center;
}

.arrow.disabled {
    opacity: .25;
    pointer-events: none;
}

.grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.event {
    display: grid;
    grid-template-columns: 180px 1fr;
    background: white;
    border-radius: 12px;
    margin-bottom: 4px;
    overflow: hidden;
    box-shadow: 0 5px 12px rgba(15, 40, 70, .10);
}

.event-time {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4px 8px;
    color: white;
    font-size: 18px;
    font-weight: 900;
    white-space: nowrap;
    line-height: 1;
}

.event-body {
    padding: 6px 10px;
    min-width: 0;
}

.event-title {
    font-size: 15px;
    font-weight: 900;
    line-height: 1.08;
    white-space: normal;
    overflow: visible;
}

.event-location {
    margin-top: 2px;
    font-size: 14px;
    font-weight: 800;
    color: #4b5563;
    white-space: normal;
}

.type-esame .event-time { background: #7a3fa4; }
.type-collegio .event-time { background: #b23c48; }
.type-cdc .event-time { background: #18579d; }
.type-dipartimento .event-time { background: #11836f; }
.type-altro .event-time { background: #2f6071; }

.empty {
    height: calc(100vh - 60px);
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    border-radius: 24px;
    font-size: 38px;
    font-weight: 900;
    color: #374151;
    box-shadow: 0 12px 26px rgba(15, 40, 70, .13);
}

.error {
    background: #fff0f0;
    color: #8a1f1f;
}

.event.status-past {
    opacity: .35;
    transform: scale(.96);
    transform-origin: left center;
}

.event.status-past .event-time {
    background: #6b7280 !important;
}

.event.status-past .event-title {
    font-size: 14px;
    font-weight: 700;
}

.event.status-past .event-location {
    font-size: 12px;
}

.event.status-current {
    outline: 4px solid #facc15;
    box-shadow: 0 0 0 4px rgba(250, 204, 21, .35), 0 8px 22px rgba(15, 40, 70, .22);
}

.event.status-current .event-title {
    font-size: 19px;
}

.home-btn-inline{
    background:#111827;
    color:#fff;
    text-decoration:none;
    padding:10px 18px;
    border-radius:10px;
    font-size:20px;
    font-weight:900;
}
</style>
</head>

<body>
<div class="page">


    <div class="nav-day">
        <a class="home-btn-inline" href="index.php">⌂ Home</a>
        <a class="arrow <?= $dayOffset === 0 ? 'disabled' : '' ?>" href="?d=<?= $prevOffset ?>">‹</a>
        <div class="day-title"><?= htmlspecialchars($currentDayTitle) ?></div>
        <a class="arrow" href="?d=<?= $nextOffset ?>">›</a>
    </div>

    <?php if (!$ics): ?>

        <div class="empty error">Impossibile leggere il calendario pubblico.</div>

    <?php elseif (empty($events)): ?>

        <div class="empty">Nessun evento previsto per questa giornata.</div>

    <?php else: ?>

        <div class="grid">
            <?php foreach ($columns as $column): ?>
                <div>
                    <?php foreach ($column as $event): ?>
                        <article class="event <?= htmlspecialchars(eventTypeClass($event['summary'])) ?> <?= htmlspecialchars(eventStatusClass($event)) ?>">
                            <div class="event-time">
                                <?= htmlspecialchars(formatEventTime($event)) ?>
                            </div>
                            <div class="event-body">
                                <div class="event-title">
                                    <?= htmlspecialchars($event['summary']) ?>
                                </div>
                                <?php if (!empty($event['location'])): ?>
                                    <div class="event-location">
                                        <?= htmlspecialchars($event['location']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</div>
</body>
</html>