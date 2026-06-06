<?php

require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';

ruoloRichiesto('studente');

if (!getSettingsValue('config', 'permessi', false) || !getSettingsValue('permessi', 'visibile_studenti', false)) {
    redirect('/error/unauthorized.php');
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Permessi di uscita</title>
    <style>
        .student-permessi {
            padding: 12px;
        }
        .student-permessi-title {
            color: #1f2937;
            font-size: 26px;
            font-weight: 800;
            margin: 16px 0 6px;
            text-align: center;
        }
        .student-permessi-subtitle {
            color: #6b7280;
            font-size: 15px;
            margin-bottom: 16px;
            text-align: center;
        }
        .student-permesso-card {
            background: #fff;
            border: 1px solid #d7e3f0;
            border-radius: 8px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, .08);
            margin-bottom: 12px;
            padding: 14px;
        }
        .student-permesso-card-main {
            align-items: center;
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }
        .student-permesso-date {
            color: #111827;
            font-size: 18px;
            font-weight: 800;
        }
        .student-permesso-time,
        .student-permesso-meta {
            color: #4b5563;
            font-size: 14px;
            margin-top: 6px;
        }
        .student-permesso-reason {
            color: #1f2937;
            font-size: 15px;
            margin-top: 10px;
        }
        .student-permesso-badge {
            border-radius: 999px;
            display: inline-block;
            font-size: 12px;
            font-weight: 800;
            padding: 5px 10px;
            white-space: nowrap;
        }
        .student-permesso-empty {
            background: #eef7fb;
            border: 1px solid #c9e6f2;
            border-radius: 8px;
            color: #22546b;
            padding: 16px;
            text-align: center;
        }
    </style>
</head>
<body>
<?php require_once '../common/header-studente-mobile.php'; ?>

<div class="student-permessi">
    <div class="student-permessi-title">Permessi di uscita</div>
    <div class="student-permessi-subtitle">Sola lettura delle richieste inserite dai genitori</div>
    <div class="records_content"></div>
</div>

<script>
function permessiReadRecords() {
    $.post('permessiReadRecords_mobile.php', {}, function (data) {
        $('.records_content').html(data);
    });
}

$(document).ready(function () {
    permessiReadRecords();
});
</script>
</body>
</html>
