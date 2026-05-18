<?php

/**
 * Helpers for MasterCom agenda events.
 *
 * MasterCom handles event create/update/delete through /mastercom/index.php
 * and returns an HTML page. These helpers submit form-url-encoded payloads
 * using the existing MasterCom service authentication.
 */

require_once __DIR__ . '/../__MasterCom.php';
require_once __DIR__ . '/admin_lib.php';

function mastercomEventDateParts(string $prefix, string $date): array
{
    $dt = DateTime::createFromFormat('Y-m-d', trim($date), new DateTimeZone('Europe/Rome'));
    if (!$dt instanceof DateTime) {
        throw new Exception('Data evento non valida: ' . $date);
    }

    return [
        $prefix . '_Day' => (string)intval($dt->format('d')),
        $prefix . '_Month' => $dt->format('m'),
        $prefix . '_Year' => $dt->format('Y'),
    ];
}

function mastercomEventTimeParts(string $prefix, string $time): array
{
    $time = trim($time);
    if (!preg_match('/^(\d{1,2}):(\d{2})/', $time, $matches)) {
        throw new Exception('Ora evento non valida: ' . $time);
    }

    return [
        $prefix . '_Hour' => str_pad((string)max(0, min(23, intval($matches[1]))), 2, '0', STR_PAD_LEFT),
        $prefix . '_Minute' => str_pad((string)max(0, min(59, intval($matches[2]))), 2, '0', STR_PAD_LEFT),
    ];
}

function mastercomEventNormalizeWeekDays(array $days = null): array
{
    if ($days === null || empty($days)) {
        return [1, 2, 3, 4, 5, 6];
    }

    $normalized = [];
    foreach ($days as $day) {
        $day = intval($day);
        if ($day >= 1 && $day <= 7) {
            $normalized[$day] = $day;
        }
    }

    return array_values($normalized ?: [1, 2, 3, 4, 5, 6]);
}

function mastercomEventAllTeacherIds(): array
{
    if (!mastercomAdminTableExists('mastercom_docenti')
        || !mastercomAdminTableColumnExists('mastercom_docenti', 'mastercom_id_user')) {
        return [];
    }

    $rows = dbGetAll("
        SELECT mastercom_id_user
        FROM mastercom_docenti
        WHERE mastercom_id_user IS NOT NULL
          AND mastercom_id_user > 0
        ORDER BY mastercom_id_user ASC
    ");
    if (!is_array($rows)) {
        return [];
    }

    $ids = [];
    foreach ($rows as $row) {
        $id = intval($row['mastercom_id_user'] ?? 0);
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    return array_values($ids);
}

function mastercomEventBuildTeachersPayload(array $selectedTeachers = null): array
{
    $payload = [];
    foreach (mastercomEventAllTeacherIds() as $teacherId) {
        $payload[$teacherId] = '';
    }

    if (is_array($selectedTeachers)) {
        foreach ($selectedTeachers as $teacherId => $value) {
            $teacherId = intval($teacherId);
            if ($teacherId > 0) {
                $payload[$teacherId] = (string)$value;
            }
        }
    }

    return $payload;
}

function mastercomEventBuildPayload(array $event): array
{
    $mode = trim((string)($event['mode'] ?? 'update'));
    $isUpdate = $mode === 'update' || intval($event['id_evento'] ?? 0) > 0;

    $payload = [
        'form_stato' => 'amministratore',
        'stato_principale' => 'eventi_principale',
        'stato_secondario' => $isUpdate ? 'modifica_evento_update' : 'inserisci_evento_update',
        'nome' => trim((string)($event['nome'] ?? '')),
        'descrizione' => trim((string)($event['descrizione'] ?? '')),
        'libera_docenti' => !empty($event['libera_docenti']) ? 'SI' : 'NO',
        'tipo_permesso' => trim((string)($event['tipo_permesso'] ?? 'G')),
        'tipo_cancellazione' => trim((string)($event['tipo_cancellazione'] ?? 'NO')),
        'mat_settimana' => mastercomEventNormalizeWeekDays($event['mat_settimana'] ?? null),
        'elenco_studenti_json' => json_encode(
            array_values(array_unique(array_map('strval', $event['studenti'] ?? []))),
            JSON_UNESCAPED_UNICODE
        ),
    ];

    if ($isUpdate) {
        $payload['id_evento'] = intval($event['id_evento'] ?? 0);
    }

    if ($payload['nome'] === '') {
        throw new Exception('Nome evento obbligatorio');
    }

    $startDate = trim((string)($event['data_inizio'] ?? ''));
    $endDate = trim((string)($event['data_fine'] ?? $startDate));
    $startTime = trim((string)($event['ora_inizio'] ?? ''));
    $endTime = trim((string)($event['ora_fine'] ?? ''));

    $payload = array_merge(
        $payload,
        mastercomEventDateParts('inizio', $startDate),
        mastercomEventTimeParts('ora_inizio', $startTime),
        mastercomEventDateParts('fine', $endDate),
        mastercomEventTimeParts('ora_fine', $endTime)
    );

    $payload['mat_professore'] = mastercomEventBuildTeachersPayload(
        is_array($event['professori'] ?? null) ? $event['professori'] : null
    );

    return $payload;
}

function mastercomEventCleanHtmlText(string $html): string
{
    return trim(preg_replace(
        '/\s+/u',
        ' ',
        strip_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
    ));
}

function mastercomEventNormalizeListTitle(string $title): string
{
    $title = trim($title);
    $title = preg_replace('/^\[[^\]]+\]\s*/u', '', $title);
    return trim(preg_replace('/\s+/u', ' ', $title));
}

function mastercomEventParseListDate(string $date): string
{
    $date = trim($date);
    $dt = DateTime::createFromFormat('d/m/Y', $date, new DateTimeZone('Europe/Rome'));
    return $dt instanceof DateTime ? $dt->format('Y-m-d') : '';
}

function mastercomEventPayloadDate(array $payload, string $prefix): string
{
    $day = intval($payload[$prefix . '_Day'] ?? 0);
    $month = intval($payload[$prefix . '_Month'] ?? 0);
    $year = intval($payload[$prefix . '_Year'] ?? 0);
    if ($day <= 0 || $month <= 0 || $year <= 0) {
        return '';
    }

    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

function mastercomEventParseListHtml(string $html): array
{
    if ($html === '') {
        return [];
    }

    preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $html, $rowMatches);
    $events = [];
    foreach ($rowMatches[1] as $rowHtml) {
        if (stripos($rowHtml, 'modifica_evento_display') === false) {
            continue;
        }
        if (!preg_match('/\bname=["\']id_evento["\'][^>]*\bvalue=["\']?(\d+)/i', $rowHtml, $idMatch)
            && !preg_match('/\bvalue=["\']?(\d+)["\']?[^>]*\bname=["\']id_evento["\']/i', $rowHtml, $idMatch)) {
            continue;
        }

        preg_match_all('/<td\b[^>]*>(.*?)<\/td>/is', $rowHtml, $cellMatches);
        if (count($cellMatches[1]) < 3) {
            continue;
        }

        $startRaw = mastercomEventCleanHtmlText($cellMatches[1][0]);
        $endRaw = mastercomEventCleanHtmlText($cellMatches[1][1]);
        $titleRaw = mastercomEventCleanHtmlText($cellMatches[1][2]);
        $status = '';
        $title = $titleRaw;
        if (preg_match('/^\[([^\]]+)\]\s*(.*)$/u', $titleRaw, $titleMatch)) {
            $status = trim($titleMatch[1]);
            $title = trim($titleMatch[2]);
        }

        $events[] = [
            'id_evento' => intval($idMatch[1]),
            'data_inizio_raw' => $startRaw,
            'data_fine_raw' => $endRaw,
            'data_inizio' => mastercomEventParseListDate($startRaw),
            'data_fine' => mastercomEventParseListDate($endRaw),
            'stato' => $status,
            'titolo_raw' => $titleRaw,
            'titolo' => mastercomEventNormalizeListTitle($title),
        ];
    }

    usort($events, function ($a, $b) {
        return intval($b['id_evento'] ?? 0) <=> intval($a['id_evento'] ?? 0);
    });

    return $events;
}

function mastercomEventExtractIdsFromHtml(string $html, array $payload = []): array
{
    $debug = [
        'hidden_id_evento' => [],
        'id_evento_occurrences' => [],
        'events_count' => 0,
        'exact_event_ids' => [],
        'title_candidate_ids' => [],
        'title_snippets' => [],
        'created_event_id' => null,
        'created_event_id_confidence' => 'none',
    ];

    if ($html === '') {
        return $debug;
    }

    $listEvents = mastercomEventParseListHtml($html);
    $debug['events_count'] = count($listEvents);
    $payloadTitle = mastercomEventNormalizeListTitle((string)($payload['nome'] ?? ''));
    $payloadStartDate = mastercomEventPayloadDate($payload, 'inizio');
    $payloadEndDate = mastercomEventPayloadDate($payload, 'fine');
    if ($payloadTitle !== '') {
        foreach ($listEvents as $event) {
            if (mastercomEventNormalizeListTitle((string)($event['titolo'] ?? '')) !== $payloadTitle) {
                continue;
            }
            if ($payloadStartDate !== '' && (string)($event['data_inizio'] ?? '') !== $payloadStartDate) {
                continue;
            }
            if ($payloadEndDate !== '' && (string)($event['data_fine'] ?? '') !== $payloadEndDate) {
                continue;
            }
            $eventId = intval($event['id_evento'] ?? 0);
            if ($eventId > 0) {
                $debug['exact_event_ids'][$eventId] = $eventId;
            }
        }
        $debug['exact_event_ids'] = array_values($debug['exact_event_ids']);
        rsort($debug['exact_event_ids'], SORT_NUMERIC);
    }

    if (preg_match_all('/<input\b[^>]*>/i', $html, $inputMatches)) {
        $hiddenIds = [];
        foreach ($inputMatches[0] as $inputTag) {
            if (!preg_match('/\bname=["\']id_evento["\']/i', $inputTag)) {
                continue;
            }
            if (preg_match('/\bvalue=["\']?(\d+)/i', $inputTag, $valueMatch)) {
                $hiddenIds[] = intval($valueMatch[1]);
            }
        }
        $debug['hidden_id_evento'] = array_values(array_unique(array_filter($hiddenIds)));
    }

    if (preg_match_all('/\bid_evento\b[^0-9]{0,40}(\d+)/i', $html, $matches)) {
        $debug['id_evento_occurrences'] = array_values(array_unique(array_map('intval', $matches[1])));
    }

    $title = trim((string)($payload['nome'] ?? ''));
    if ($title === '') {
        return $debug;
    }

    $decodedHtml = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $titleVariants = array_values(array_unique(array_filter([
        $title,
        html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        str_replace(['"', "'"], ['&quot;', '&#039;'], $title),
    ])));

    $candidateIds = [];
    foreach ($titleVariants as $titleVariant) {
        $offset = 0;
        while (($position = mb_stripos($decodedHtml, $titleVariant, $offset, 'UTF-8')) !== false) {
            $start = max(0, $position - 1600);
            $length = mb_strlen($titleVariant, 'UTF-8') + 3200;
            $snippet = mb_substr($decodedHtml, $start, $length, 'UTF-8');
            $plainSnippet = trim(preg_replace('/\s+/u', ' ', strip_tags($snippet)));
            if ($plainSnippet !== '') {
                $debug['title_snippets'][] = mb_substr($plainSnippet, 0, 700, 'UTF-8');
            }

            if (preg_match_all('/\bid_evento\b[^0-9]{0,80}(\d+)/i', $snippet, $matches)) {
                foreach ($matches[1] as $id) {
                    $id = intval($id);
                    if ($id > 0) {
                        $candidateIds[$id] = $id;
                    }
                }
            }
            if (preg_match_all('/\b(?:modifica_evento|cancella_evento|visualizza_evento)[^0-9]{0,120}(\d+)/i', $snippet, $matches)) {
                foreach ($matches[1] as $id) {
                    $id = intval($id);
                    if ($id > 0) {
                        $candidateIds[$id] = $id;
                    }
                }
            }

            $offset = $position + max(1, mb_strlen($titleVariant, 'UTF-8'));
        }
    }

    $debug['title_snippets'] = array_values(array_unique($debug['title_snippets']));
    $debug['title_candidate_ids'] = array_values($candidateIds);

    rsort($debug['hidden_id_evento'], SORT_NUMERIC);
    rsort($debug['id_evento_occurrences'], SORT_NUMERIC);
    rsort($debug['title_candidate_ids'], SORT_NUMERIC);

    if (count($debug['exact_event_ids']) === 1) {
        $debug['created_event_id'] = $debug['exact_event_ids'][0];
        $debug['created_event_id_confidence'] = 'event_list_exact_match';
    } elseif (count($debug['exact_event_ids']) > 1) {
        $debug['created_event_id'] = max($debug['exact_event_ids']);
        $debug['created_event_id_confidence'] = 'event_list_exact_match_max';
    } elseif (count($debug['title_candidate_ids']) === 1) {
        $debug['created_event_id'] = $debug['title_candidate_ids'][0];
        $debug['created_event_id_confidence'] = 'title_context_unique';
    } elseif (count($debug['title_candidate_ids']) > 1) {
        $debug['created_event_id'] = max($debug['title_candidate_ids']);
        $debug['created_event_id_confidence'] = 'title_context_max_unverified';
    } elseif (count($debug['hidden_id_evento']) === 1) {
        $debug['created_event_id'] = $debug['hidden_id_evento'][0];
        $debug['created_event_id_confidence'] = 'hidden_only_unverified';
    } elseif (count($debug['hidden_id_evento']) > 1) {
        $debug['created_event_id'] = max($debug['hidden_id_evento']);
        $debug['created_event_id_confidence'] = 'hidden_max_unverified';
    }

    return $debug;
}

function mastercomEventFetchList(array $options = []): array
{
    return mastercomEventSubmitPayload([
        'form_stato' => 'amministratore',
        'stato_principale' => 'eventi_principale',
    ], $options);
}

function mastercomEventHtmlFieldValue(string $html, string $name): string
{
    $quotedName = preg_quote($name, '/');

    if (preg_match('/<textarea\b[^>]*\bname=["\']' . $quotedName . '["\'][^>]*>(.*?)<\/textarea>/is', $html, $matches)) {
        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    if (preg_match_all('/<input\b[^>]*>/i', $html, $matches)) {
        foreach ($matches[0] as $inputTag) {
            if (!preg_match('/\bname=["\']' . $quotedName . '["\']/i', $inputTag)) {
                continue;
            }
            if (preg_match('/\bvalue=["\']([^"\']*)["\']/i', $inputTag, $valueMatch)) {
                return html_entity_decode($valueMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            return '';
        }
    }

    if (preg_match('/<select\b[^>]*\bname=["\']' . $quotedName . '["\'][^>]*>(.*?)<\/select>/is', $html, $matches)) {
        $selectHtml = $matches[1];
        if (preg_match('/<option\b[^>]*\bselected\b[^>]*\bvalue=["\']([^"\']*)["\']/is', $selectHtml, $selectedMatch)
            || preg_match('/<option\b[^>]*\bvalue=["\']([^"\']*)["\'][^>]*\bselected\b/is', $selectHtml, $selectedMatch)) {
            return html_entity_decode($selectedMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    return '';
}

function mastercomEventParseDetailHtml(string $html): array
{
    $id = intval(mastercomEventHtmlFieldValue($html, 'id_evento'));
    $startDay = mastercomEventHtmlFieldValue($html, 'inizio_Day');
    $startMonth = mastercomEventHtmlFieldValue($html, 'inizio_Month');
    $startYear = mastercomEventHtmlFieldValue($html, 'inizio_Year');
    $endDay = mastercomEventHtmlFieldValue($html, 'fine_Day');
    $endMonth = mastercomEventHtmlFieldValue($html, 'fine_Month');
    $endYear = mastercomEventHtmlFieldValue($html, 'fine_Year');

    $students = [];
    if (preg_match_all('/<input\b[^>]*\bname=["\']check_studente\[\]["\'][^>]*>/i', $html, $matches)) {
        foreach ($matches[0] as $inputTag) {
            if (stripos($inputTag, 'checked') === false) {
                continue;
            }
            if (preg_match('/\bvalue=["\']?(\d+)/i', $inputTag, $valueMatch)) {
                $studentId = intval($valueMatch[1]);
                if ($studentId > 0) {
                    $students[$studentId] = (string)$studentId;
                }
            }
        }
    }

    return [
        'id_evento' => $id,
        'nome' => mastercomEventHtmlFieldValue($html, 'nome'),
        'descrizione' => mastercomEventHtmlFieldValue($html, 'descrizione'),
        'libera_docenti' => mastercomEventHtmlFieldValue($html, 'libera_docenti'),
        'tipo_permesso' => mastercomEventHtmlFieldValue($html, 'tipo_permesso'),
        'tipo_cancellazione' => mastercomEventHtmlFieldValue($html, 'tipo_cancellazione'),
        'data_inizio' => sprintf('%04d-%02d-%02d', intval($startYear), intval($startMonth), intval($startDay)),
        'ora_inizio' => sprintf(
            '%02d:%02d',
            intval(mastercomEventHtmlFieldValue($html, 'ora_inizio_Hour')),
            intval(mastercomEventHtmlFieldValue($html, 'ora_inizio_Minute'))
        ),
        'data_fine' => sprintf('%04d-%02d-%02d', intval($endYear), intval($endMonth), intval($endDay)),
        'ora_fine' => sprintf(
            '%02d:%02d',
            intval(mastercomEventHtmlFieldValue($html, 'ora_fine_Hour')),
            intval(mastercomEventHtmlFieldValue($html, 'ora_fine_Minute'))
        ),
        'studenti' => array_values($students),
    ];
}

function mastercomEventFetchDetail(int $eventId, array $options = []): array
{
    if ($eventId <= 0) {
        return ['ok' => false, 'error' => 'ID evento non valido', 'http_code' => 0];
    }

    $result = mastercomEventSubmitPayload([
        'x' => 7,
        'y' => 12,
        'form_stato' => 'amministratore',
        'stato_principale' => 'eventi_principale',
        'stato_secondario' => 'modifica_evento_display',
        'id_evento' => $eventId,
    ], $options);

    if (!empty($result['ok'])) {
        $result['event_detail'] = mastercomEventParseDetailHtml((string)($result['body'] ?? ''));
    }

    return $result;
}

function mastercomEventSubmitPayload(array $payload, array $options = []): array
{
    $authResult = mastercomAuthenticateService([
        'profile' => trim((string)($options['profile'] ?? 'MasterComAuth')),
        'method' => 'POST',
        'timeout' => intval($options['auth_timeout'] ?? 60),
    ]);

    if (empty($authResult['ok'])) {
        return [
            'ok' => false,
            'error' => 'Autenticazione MasterCom amministratore fallita',
            'http_code' => intval($authResult['http_code'] ?? 0),
        ];
    }

    $payload['form_stato'] = $payload['form_stato'] ?? 'amministratore';
    $payload['stato_principale'] = $payload['stato_principale'] ?? 'eventi_principale';

    $result = mastercomSubmitAdminAbsenceAction($authResult, $payload, [
        'base_url' => mastercomIndexUrl(),
        'method' => 'POST',
        'timeout' => intval($options['timeout'] ?? 180),
        'send_in_body' => true,
    ]);

    if (empty($result['ok'])) {
        return $result;
    }

    $body = (string)($result['body'] ?? '');
    $plainBody = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
    $result['message'] = $plainBody !== '' ? mb_substr($plainBody, 0, 500, 'UTF-8') : 'Risposta HTML MasterCom ricevuta';
    $result['event_id_debug'] = mastercomEventExtractIdsFromHtml($body, $payload);
    return $result;
}

function mastercomEventSave(array $event, array $options = []): array
{
    return mastercomEventSubmitPayload(mastercomEventBuildPayload($event), $options);
}

function mastercomEventDelete(int $eventId, array $options = []): array
{
    if ($eventId <= 0) {
        return ['ok' => false, 'error' => 'ID evento non valido', 'http_code' => 0];
    }

    return mastercomEventSubmitPayload([
        'form_stato' => 'amministratore',
        'stato_principale' => 'eventi_principale',
        'stato_secondario' => 'elimina_evento',
        'id_evento' => $eventId,
    ], $options);
}
