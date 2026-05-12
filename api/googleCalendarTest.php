<?php

require_once __DIR__ . '/../common/__Settings.php';

require_once __DIR__ . '/../common/checkSession.php';

require_once __DIR__ . '/googleCalendarLib.php';
require_once __DIR__ . '/../common/__Log.php';

ruoloRichiesto('admin');

header('Content-Type: text/html; charset=utf-8');

try {

    $result = googleCalendarListCalendars();

    echo '<h2>Calendari Google accessibili da GestOre</h2>';

    if (
        !isset($result['items']) ||
        count($result['items']) === 0
    ) {

        echo '<p>Nessun calendario trovato.</p>';

        exit;
    }

    echo '<table border="1" cellpadding="6" cellspacing="0">';

    echo '<tr>';
    echo '<th>Nome</th>';
    echo '<th>Calendar ID</th>';
    echo '<th>Ruolo</th>';
    echo '<th>Timezone</th>';
    echo '</tr>';

    foreach ($result['items'] as $calendar) {

        echo '<tr>';

        echo '<td>' .
            htmlspecialchars($calendar['summary'] ?? '') .
            '</td>';

        echo '<td><code>' .
            htmlspecialchars($calendar['id'] ?? '') .
            '</code></td>';

        echo '<td>' .
            htmlspecialchars($calendar['accessRole'] ?? '') .
            '</td>';

        echo '<td>' .
            htmlspecialchars($calendar['timeZone'] ?? '') .
            '</td>';

        echo '</tr>';
    }

    echo '</table>';

} catch (Exception $e) {

    echo '<h2>Errore Google Calendar</h2>';

    echo '<pre>' .
        htmlspecialchars($e->getMessage()) .
        '</pre>';
}