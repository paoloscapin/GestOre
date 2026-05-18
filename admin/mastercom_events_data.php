<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/events_lib.php';

ruoloRichiesto('admin');

header('Content-Type: application/json; charset=utf-8');

function mastercomEventsDataJson(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function mastercomEventsDataTs(string $date, string $time = '00:00'): int
{
    $date = trim($date);
    $time = trim($time) !== '' ? trim($time) : '00:00';
    if ($date === '') {
        return 0;
    }
    $dt = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time, new DateTimeZone('Europe/Rome'));
    return $dt instanceof DateTime ? $dt->getTimestamp() : 0;
}

function mastercomEventsDataEnrich(array $events): array
{
    $now = new DateTime('now', new DateTimeZone('Europe/Rome'));
    $today = $now->format('Y-m-d');
    $nowTs = $now->getTimestamp();
    $detailCache = [];

    foreach ($events as &$event) {
        $startDate = (string)($event['data_inizio'] ?? '');
        $endDate = (string)($event['data_fine'] ?? '');
        $event['ora_inizio'] = '';
        $event['ora_fine'] = '';
        $event['calculated_status'] = '';
        $event['sort_group'] = ($endDate !== '' && $endDate < $today) ? 1 : 0;
        $event['sort_date'] = $event['sort_group'] === 0 ? $startDate : $endDate;

        if ($startDate === '' || $endDate === '') {
            continue;
        }

        if ($startDate <= $today && $endDate >= $today) {
            $eventId = intval($event['id_evento'] ?? 0);
            if ($eventId > 0 && !isset($detailCache[$eventId])) {
                $detailResult = mastercomEventFetchDetail($eventId);
                $detailCache[$eventId] = !empty($detailResult['ok']) && is_array($detailResult['event_detail'] ?? null)
                    ? $detailResult['event_detail']
                    : [];
            }
            $detail = $detailCache[$eventId] ?? [];
            $startTime = trim((string)($detail['ora_inizio'] ?? '00:00'));
            $endTime = trim((string)($detail['ora_fine'] ?? '23:59'));
            $event['ora_inizio'] = $startTime;
            $event['ora_fine'] = $endTime;

            $startTs = mastercomEventsDataTs($startDate, $startDate === $today ? $startTime : '00:00');
            $endTs = mastercomEventsDataTs($endDate, $endDate === $today ? $endTime : '23:59');
            if ($startTs > 0 && $endTs > 0 && $nowTs >= $startTs && $nowTs <= $endTs) {
                $event['calculated_status'] = 'IN CORSO';
            }
        }
    }
    unset($event);

    usort($events, function ($a, $b) {
        $groupCompare = intval($a['sort_group'] ?? 0) <=> intval($b['sort_group'] ?? 0);
        if ($groupCompare !== 0) {
            return $groupCompare;
        }
        if (intval($a['sort_group'] ?? 0) === 0) {
            $dateCompare = strcmp((string)($a['sort_date'] ?? ''), (string)($b['sort_date'] ?? ''));
        } else {
            $dateCompare = strcmp((string)($b['sort_date'] ?? ''), (string)($a['sort_date'] ?? ''));
        }
        if ($dateCompare !== 0) {
            return $dateCompare;
        }
        return intval($b['id_evento'] ?? 0) <=> intval($a['id_evento'] ?? 0);
    });

    return $events;
}

$action = trim((string)($_POST['action'] ?? $_GET['action'] ?? 'list'));

if ($action === 'delete') {
    $eventId = intval($_POST['id_evento'] ?? 0);
    $deleteResult = mastercomEventDelete($eventId);
    mastercomEventsDataJson([
        'ok' => !empty($deleteResult['ok']),
        'message' => !empty($deleteResult['ok']) ? ('Evento #' . $eventId . ' eliminato su MasterCom.') : '',
        'error' => empty($deleteResult['ok']) ? (string)($deleteResult['error'] ?? 'DELETE_FAILED') : '',
    ]);
}

if ($action === 'participant_count') {
    $eventId = intval($_GET['id_evento'] ?? $_POST['id_evento'] ?? 0);
    if ($eventId <= 0) {
        mastercomEventsDataJson([
            'ok' => false,
            'error' => 'ID evento non valido',
            'count' => null,
        ]);
    }

    $detailResult = mastercomEventFetchDetail($eventId);
    if (empty($detailResult['ok'])) {
        mastercomEventsDataJson([
            'ok' => false,
            'error' => (string)($detailResult['error'] ?? 'FETCH_DETAIL_FAILED'),
            'count' => null,
        ]);
    }

    $detail = is_array($detailResult['event_detail'] ?? null) ? $detailResult['event_detail'] : [];
    mastercomEventsDataJson([
        'ok' => true,
        'id_evento' => $eventId,
        'count' => count($detail['studenti'] ?? []),
    ]);
}

$q = trim((string)($_GET['q'] ?? ''));
$fetchResult = mastercomEventFetchList();
if (empty($fetchResult['ok'])) {
    mastercomEventsDataJson([
        'ok' => false,
        'error' => (string)($fetchResult['error'] ?? 'FETCH_FAILED'),
        'events' => [],
    ]);
}

$events = mastercomEventParseListHtml((string)($fetchResult['body'] ?? ''));
if ($q !== '') {
    $events = array_values(array_filter($events, function ($event) use ($q) {
        return mb_stripos((string)($event['titolo'] ?? ''), $q, 0, 'UTF-8') !== false
            || mb_stripos((string)($event['titolo_raw'] ?? ''), $q, 0, 'UTF-8') !== false
            || (string)($event['id_evento'] ?? '') === $q;
    }));
}

mastercomEventsDataJson([
    'ok' => true,
    'events' => mastercomEventsDataEnrich($events),
]);
