<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/noirc_lib.php';

ruoloRichiesto('admin');

$weekOf = trim((string)($_GET['week_of'] ?? $_POST['week_of'] ?? ''));
$context = mastercomNoIrcBuildWeekSlots($weekOf);
$weekContext = $context['week'];
$periodStart = mastercomNoIrcNormalizeDate((string)($_GET['data_inizio'] ?? $_POST['data_inizio'] ?? $weekContext['week_start']));
$periodEnd = mastercomNoIrcNormalizeDate((string)($_GET['data_fine'] ?? $_POST['data_fine'] ?? $weekContext['week_end']));
$rooms = mastercomNoIrcDefaultRooms();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && trim((string)($_POST['action'] ?? '')) === 'save_room_setup') {
    $saveResult = mastercomNoIrcSaveRoomSetup($_POST);
    if ($saveResult['ok']) {
        $message = 'Setup aule salvato per ' . intval($saveResult['count'] ?? 0) . ' classi';
        $context = mastercomNoIrcBuildWeekSlots($weekContext['reference_date']);
    } else {
        $error = trim((string)($saveResult['error'] ?? 'Errore salvataggio setup aule'));
    }
}

$missingTables = mastercomAdminMissingTables(['mastercom_noirc_aula_classi']);
$weekdayLabels = mastercomNoIrcWeekdayLabels();
$slotsByWeekday = [];
for ($weekday = 1; $weekday <= 5; $weekday++) {
    $slotsByWeekday[$weekday] = [];
}
foreach (($context['slots'] ?? []) as $slot) {
    $weekday = intval($slot['weekday'] ?? 0);
    if ($weekday >= 1 && $weekday <= 5) {
        $slotsByWeekday[$weekday][] = $slot;
    }
}
$activeWeekday = 1;
foreach ($slotsByWeekday as $weekday => $daySlots) {
    if (!empty($daySlots)) {
        $activeWeekday = intval($weekday);
        break;
    }
}

function mastercomNoIrcRoomsStudentCount(array $students): int
{
    return count($students);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>MasterCom NO IRC - Setup Aule</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <style>
        .noirc-room-slot {
            border: 1px solid #d9edf7;
            border-radius: 4px;
            margin-bottom: 16px;
            background: #fff;
        }
        .noirc-room-slot-heading {
            padding: 10px 12px;
            background: #eef8fb;
            border-bottom: 1px solid #d9edf7;
            font-weight: 600;
        }
        .noirc-room-total {
            display: inline-block;
            min-width: 95px;
            margin-right: 8px;
        }
        .noirc-room-tabs {
            margin-top: 15px;
            margin-bottom: 15px;
        }
        .noirc-room-tabs > li > a {
            font-weight: 700;
        }
        .noirc-room-tab-pane {
            padding-top: 4px;
        }
        #noirc_room_saving_overlay {
            display: none;
            position: fixed;
            z-index: 99999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, .45);
        }
        .noirc-room-saving-card {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            min-width: 320px;
            max-width: 90%;
            padding: 24px 28px;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 16px 45px rgba(15, 23, 42, .28);
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            color: #24324a;
        }
        .noirc-room-saving-card .glyphicon {
            display: block;
            margin-bottom: 12px;
            font-size: 30px;
            color: #0b72b9;
        }
    </style>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
<div id="noirc_room_saving_overlay" aria-live="polite" aria-busy="true">
    <div class="noirc-room-saving-card">
        <span class="glyphicon glyphicon-refresh"></span>
        Salvataggio in corso...
        <div style="font-size: 13px; font-weight: 400; margin-top: 8px; color: #6b7280;">
            Attendi la risposta di MasterCom.
        </div>
    </div>
</div>
<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-th-large"></span>&emsp;NO IRC - Setup aule per classi
        </div>
        <div class="panel-body">
            <?php if ($message !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($missingTables)): ?>
                <div class="alert alert-warning">
                    Manca la tabella <code>mastercom_noirc_aula_classi</code>. Esegui lo script <code>doc/mastercom_noirc_schema.sql</code>.
                </div>
            <?php endif; ?>

            <form method="get" action="mastercom_noirc_rooms.php" class="form-inline" style="margin-bottom: 15px;">
                <div class="form-group">
                    <label for="week_of">Settimana di riferimento&nbsp;</label>
                    <input type="date" class="form-control" name="week_of" id="week_of" value="<?php echo htmlspecialchars($weekContext['reference_date']); ?>">
                </div>
                <div class="form-group" style="margin-left: 10px;">
                    <label for="data_inizio">Applica dal&nbsp;</label>
                    <input type="date" class="form-control" name="data_inizio" id="data_inizio" value="<?php echo htmlspecialchars($periodStart); ?>">
                </div>
                <div class="form-group" style="margin-left: 10px;">
                    <label for="data_fine">al&nbsp;</label>
                    <input type="date" class="form-control" name="data_fine" id="data_fine" value="<?php echo htmlspecialchars($periodEnd); ?>">
                </div>
                <button type="submit" class="btn btn-default" style="margin-left: 10px;">Aggiorna</button>
                <a href="mastercom_noirc.php?week_of=<?php echo urlencode($weekContext['reference_date']); ?>" class="btn btn-primary" style="margin-left: 10px;">Torna alla settimana NO IRC</a>
            </form>

            <div class="alert alert-info">
                Per ogni slot vedi le classi che hanno IRC e il numero di studenti NO IRC. Lascia in <strong>aula 246</strong> le classi del primo gruppo e sposta in <strong>aula 128</strong> quelle del secondo gruppo.
                Il docente verra poi associato all'aula se nella pagina docenti ha la stessa aula impostata.
            </div>

            <?php if (empty($context['slots'])): ?>
                <div class="alert alert-info">Nella settimana selezionata non risultano slot IRC nell'orario caricato.</div>
            <?php else: ?>
                <ul class="nav nav-tabs noirc-room-tabs" role="tablist">
                    <?php foreach ($slotsByWeekday as $weekday => $daySlots): ?>
                        <li role="presentation" class="<?php echo intval($weekday) === $activeWeekday ? 'active' : ''; ?>">
                            <a href="#noirc-room-day-<?php echo intval($weekday); ?>" aria-controls="noirc-room-day-<?php echo intval($weekday); ?>" role="tab" data-toggle="tab">
                                <?php echo htmlspecialchars($weekdayLabels[intval($weekday)] ?? ('Giorno ' . intval($weekday))); ?>
                                <span class="badge"><?php echo count($daySlots); ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="tab-content">
                    <?php foreach ($slotsByWeekday as $weekday => $daySlots): ?>
                        <div role="tabpanel" class="tab-pane noirc-room-tab-pane <?php echo intval($weekday) === $activeWeekday ? 'active' : ''; ?>" id="noirc-room-day-<?php echo intval($weekday); ?>">
                            <?php if (empty($daySlots)): ?>
                                <div class="alert alert-info">Nessuno slot IRC per <?php echo htmlspecialchars($weekdayLabels[intval($weekday)] ?? 'questo giorno'); ?>.</div>
                            <?php endif; ?>

                            <?php foreach ($daySlots as $slot): ?>
                                <?php
                                $roomTotals = array_fill_keys($rooms, 0);
                                foreach ($slot['students_by_class'] as $classLabel => $students) {
                                    $currentRoom = trim((string)($slot['room_setup'][$classLabel]['aula'] ?? '246'));
                                    if (!isset($roomTotals[$currentRoom])) {
                                        $roomTotals[$currentRoom] = 0;
                                    }
                                    $roomTotals[$currentRoom] += mastercomNoIrcRoomsStudentCount($students);
                                }
                                ?>
                                <div class="noirc-room-slot">
                                    <div class="noirc-room-slot-heading">
                                        <?php echo htmlspecialchars($weekdayLabels[intval($slot['weekday'])] ?? ''); ?>
                                        <?php echo htmlspecialchars((new DateTime((string)$slot['date']))->format('d/m/Y')); ?>
                                        - ora <?php echo htmlspecialchars((string)$slot['hour']); ?>
                                        <span class="text-muted">| Classi: <?php echo htmlspecialchars(implode(', ', $slot['classi_irc'])); ?></span>
                                    </div>
                                    <div style="padding: 12px;">
                                        <div style="margin-bottom: 10px;">
                                            <?php foreach ($roomTotals as $room => $total): ?>
                                                <span class="label label-<?php echo $room === '246' ? 'primary' : 'warning'; ?> noirc-room-total">
                                                    Aula <?php echo htmlspecialchars((string)$room); ?>: <?php echo intval($total); ?> studenti
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                        <form method="post" action="mastercom_noirc_rooms.php">
                                            <input type="hidden" name="action" value="save_room_setup">
                                            <input type="hidden" name="week_of" value="<?php echo htmlspecialchars($weekContext['reference_date']); ?>">
                                            <input type="hidden" name="data_inizio" value="<?php echo htmlspecialchars($periodStart); ?>">
                                            <input type="hidden" name="data_fine" value="<?php echo htmlspecialchars($periodEnd); ?>">
                                            <input type="hidden" name="giorno_settimana" value="<?php echo intval($slot['weekday']); ?>">
                                            <input type="hidden" name="ora" value="<?php echo htmlspecialchars((string)$slot['hour']); ?>">

                                            <table class="table table-bordered table-condensed" style="margin-bottom: 10px;">
                                                <thead>
                                                    <tr>
                                                        <th>Classe</th>
                                                        <th style="text-align: center; width: 130px;">Studenti NO IRC</th>
                                                        <th style="text-align: center; width: 180px;">Aula</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($slot['students_by_class'] as $classLabel => $students): ?>
                                                        <?php $currentRoom = trim((string)($slot['room_setup'][$classLabel]['aula'] ?? '246')); ?>
                                                        <tr>
                                                            <td><strong><?php echo htmlspecialchars((string)$classLabel); ?></strong></td>
                                                            <td style="text-align: center;"><?php echo count($students); ?></td>
                                                            <td>
                                                                <select class="form-control input-sm" name="room_assignments[<?php echo htmlspecialchars(rawurlencode((string)$classLabel)); ?>]">
                                                                    <?php foreach ($rooms as $room): ?>
                                                                        <option value="<?php echo htmlspecialchars($room); ?>" <?php echo $currentRoom === $room ? 'selected' : ''; ?>>
                                                                            Aula <?php echo htmlspecialchars($room); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>

                                <button type="submit" class="btn btn-success" <?php echo !empty($missingTables) ? 'disabled' : ''; ?>>
                                    <span class="glyphicon glyphicon-floppy-disk"></span> Salva setup per questo slot
                                </button>
                            </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
    $(document).on('submit', 'form[action="mastercom_noirc_rooms.php"]', function () {
        var $form = $(this);
        if ($form.find('input[name="action"]').val() !== 'save_room_setup') {
            return true;
        }

        $('#noirc_room_saving_overlay').fadeIn(120);
        $form.find('button[type="submit"]').prop('disabled', true);
        return true;
    });
</script>
</body>
</html>
