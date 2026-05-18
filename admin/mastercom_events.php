<?php

require_once '../common/checkSession.php';

ruoloRichiesto('admin');

function mastercomEventsH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$q = trim((string)($_GET['q'] ?? ''));
?>
<!DOCTYPE html>
<html>
<head>
    <title>MasterCom Eventi</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <style>
        .event-actions {
            white-space: nowrap;
            width: 250px;
        }
        .event-participants {
            text-align: center;
            width: 110px;
        }
        .event-state {
            display: inline-block;
            margin-right: 5px;
        }
        .event-row-current {
            background-color: #fff200 !important;
        }
        .event-row-current > td {
            background-color: #fff200 !important;
        }
        .event-loading-overlay {
            align-items: center;
            background: rgba(255, 255, 255, 0.78);
            bottom: 0;
            display: none;
            justify-content: center;
            left: 0;
            position: fixed;
            right: 0;
            top: 0;
            z-index: 9999;
        }
        .event-loading-box {
            background: #ffffff;
            border: 1px solid #b8d9ef;
            border-radius: 6px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.18);
            color: #1f4e79;
            font-size: 18px;
            min-width: 320px;
            padding: 24px 34px;
            text-align: center;
        }
        .event-loading-box .glyphicon {
            margin-right: 8px;
        }
    </style>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
<div class="event-loading-overlay" id="eventLoadingOverlay">
    <div class="event-loading-box">
        <span class="glyphicon glyphicon-refresh"></span>
        Caricamento eventi MasterCom in corso...
        <div class="text-muted" style="font-size:12px;margin-top:6px;">Attendere la risposta di MasterCom.</div>
    </div>
</div>
<div class="container-fluid">
    <div class="panel panel-lightblue4">
        <div class="panel-heading"><span class="glyphicon glyphicon-list"></span>&emsp;Eventi MasterCom</div>
        <div class="panel-body">
            <div id="eventsMessage"></div>

            <form method="get" class="form-inline" id="eventsSearchForm" style="margin-bottom:15px;">
                <div class="form-group">
                    <input type="text" class="form-control" id="eventsSearch" name="q" value="<?php echo mastercomEventsH($q); ?>" placeholder="Cerca titolo o ID">
                </div>
                <button type="submit" class="btn btn-default"><span class="glyphicon glyphicon-search"></span> Cerca</button>
                <a href="mastercom_events.php" class="btn btn-default" id="eventsAll">Tutti</a>
                <a href="mastercom_event_create.php" class="btn btn-primary"><span class="glyphicon glyphicon-plus"></span> Nuovo evento</a>
            </form>

            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Inizio</th>
                        <th>Fine</th>
                        <th>Evento</th>
                        <th class="event-participants">Partecipanti</th>
                        <th class="event-actions">Azioni</th>
                    </tr>
                    </thead>
                    <tbody id="eventsBody">
                    <tr><td colspan="6" class="text-center text-muted">Caricamento...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
    function mastercomEventsEscape(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function mastercomEventsShowLoading(show) {
        $('#eventLoadingOverlay').css('display', show ? 'flex' : 'none');
    }

    function mastercomEventsShowMessage(type, text) {
        if (!text) {
            $('#eventsMessage').empty();
            return;
        }
        $('#eventsMessage').html('<div class="alert alert-' + type + '">' + mastercomEventsEscape(text) + '</div>');
    }

    var mastercomParticipantObserver = null;

    function mastercomEventsLoadParticipantCount($cell) {
        if (!$cell.length || $cell.data('loaded') || $cell.data('loading')) {
            return;
        }
        var eventId = parseInt($cell.data('id'), 10);
        if (!eventId) {
            $cell.text('-').data('loaded', true);
            return;
        }
        $cell.data('loading', true).html('<span class="text-muted">...</span>');
        $.getJSON('mastercom_events_data.php', { action: 'participant_count', id_evento: eventId })
            .done(function (data) {
                if (data && data.ok) {
                    $cell.html('<span class="label label-info">' + parseInt(data.count || 0, 10) + '</span>');
                } else {
                    $cell.html('<span class="text-danger" title="' + mastercomEventsEscape((data && data.error) ? data.error : 'Errore lettura partecipanti') + '">!</span>');
                }
            })
            .fail(function () {
                $cell.html('<span class="text-danger" title="Errore lettura partecipanti">!</span>');
            })
            .always(function () {
                $cell.data('loading', false).data('loaded', true);
            });
    }

    function mastercomEventsObserveParticipantCounts() {
        if (mastercomParticipantObserver) {
            mastercomParticipantObserver.disconnect();
            mastercomParticipantObserver = null;
        }

        var cells = document.querySelectorAll('.js-participant-count');
        if (!('IntersectionObserver' in window)) {
            $('.js-participant-count').each(function () {
                mastercomEventsLoadParticipantCount($(this));
            });
            return;
        }

        mastercomParticipantObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }
                mastercomEventsLoadParticipantCount($(entry.target));
                mastercomParticipantObserver.unobserve(entry.target);
            });
        }, { rootMargin: '180px' });

        Array.prototype.forEach.call(cells, function (cell) {
            mastercomParticipantObserver.observe(cell);
        });
    }

    function mastercomEventsRender(events) {
        var $body = $('#eventsBody');
        $body.empty();
        if (!events.length) {
            $body.append('<tr><td colspan="6" class="text-center text-muted">Nessun evento trovato.</td></tr>');
            return;
        }
        events.forEach(function (event) {
            var eventId = parseInt(event.id_evento || 0, 10);
            var editUrl = 'mastercom_event_create.php?' + $.param({
                id_evento: eventId,
                nome: event.titolo || '',
                data_inizio: event.data_inizio || '',
                data_fine: event.data_fine || ''
            });
            var status = event.calculated_status || '';
            var statusHtml = status
                ? '<span class="label label-warning event-state">' + mastercomEventsEscape(status) + '</span>'
                : '';
            var timeSuffix = '';
            if (event.ora_inizio || event.ora_fine) {
                timeSuffix = ' <span class="text-muted">(' + mastercomEventsEscape(event.ora_inizio || '') + ' - ' + mastercomEventsEscape(event.ora_fine || '') + ')</span>';
            }
            var rowClass = status === 'IN CORSO' ? ' class="event-row-current"' : '';
            var row = '<tr' + rowClass + '>' +
                '<td>' + eventId + '</td>' +
                '<td>' + mastercomEventsEscape(event.data_inizio_raw || '') + '</td>' +
                '<td>' + mastercomEventsEscape(event.data_fine_raw || '') + timeSuffix + '</td>' +
                '<td>' + statusHtml + mastercomEventsEscape(event.titolo || '') + '</td>' +
                '<td class="event-participants js-participant-count" data-id="' + eventId + '"><span class="text-muted">...</span></td>' +
                '<td class="event-actions">' +
                '<a class="btn btn-xs btn-warning" href="' + mastercomEventsEscape(editUrl) + '"><span class="glyphicon glyphicon-pencil"></span> Modifica</a> ' +
                '<a class="btn btn-xs btn-info" target="_blank" href="mastercom_event_pdf.php?id_evento=' + eventId + '"><span class="glyphicon glyphicon-print"></span> Stampa</a> ' +
                '<button type="button" class="btn btn-xs btn-danger js-delete-event" data-id="' + eventId + '"><span class="glyphicon glyphicon-trash"></span> Elimina</button>' +
                '</td>' +
                '</tr>';
            $body.append(row);
        });
        mastercomEventsObserveParticipantCounts();
    }

    function mastercomEventsLoad(query) {
        mastercomEventsShowLoading(true);
        mastercomEventsShowMessage('', '');
        $.getJSON('mastercom_events_data.php', { q: query || '' })
            .done(function (data) {
                if (!data || !data.ok) {
                    mastercomEventsShowMessage('danger', (data && data.error) ? data.error : 'Lettura eventi MasterCom fallita.');
                    mastercomEventsRender([]);
                    return;
                }
                mastercomEventsRender(data.events || []);
            })
            .fail(function () {
                mastercomEventsShowMessage('danger', 'Errore di comunicazione durante la lettura eventi MasterCom.');
                mastercomEventsRender([]);
            })
            .always(function () {
                mastercomEventsShowLoading(false);
            });
    }

    $(function () {
        mastercomEventsLoad($('#eventsSearch').val());

        $('#eventsSearchForm').on('submit', function (event) {
            event.preventDefault();
            mastercomEventsLoad($('#eventsSearch').val());
        });

        $('#eventsAll').on('click', function (event) {
            event.preventDefault();
            $('#eventsSearch').val('');
            mastercomEventsLoad('');
        });

        $('#eventsBody').on('click', '.js-delete-event', function () {
            var eventId = parseInt($(this).data('id'), 10);
            if (!eventId || !confirm('Eliminare evento #' + eventId + '?')) {
                return;
            }
            mastercomEventsShowLoading(true);
            $.post('mastercom_events_data.php', { action: 'delete', id_evento: eventId }, null, 'json')
                .done(function (data) {
                    if (!data || !data.ok) {
                        mastercomEventsShowMessage('danger', (data && data.error) ? data.error : 'Eliminazione evento MasterCom fallita.');
                        return;
                    }
                    mastercomEventsShowMessage('success', data.message || ('Evento #' + eventId + ' eliminato.'));
                    mastercomEventsLoad($('#eventsSearch').val());
                })
                .fail(function () {
                    mastercomEventsShowMessage('danger', 'Errore di comunicazione durante eliminazione evento.');
                })
                .always(function () {
                    mastercomEventsShowLoading(false);
                });
        });
    });
</script>
</body>
</html>
