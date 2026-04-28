<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/admin_lib.php';

ruoloRichiesto('admin');

@ignore_user_abort(true);
@set_time_limit(0);
@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '512M');

function mastercomSyncRenderProgress(string $title, string $message, int $current = 0, int $total = 0): void
{
    $percent = ($total > 0) ? intval(round(($current / $total) * 100)) : 0;
    echo '<script>';
    echo 'document.getElementById("mc-stage").textContent = ' . json_encode($title) . ';';
    echo 'document.getElementById("mc-message").textContent = ' . json_encode($message) . ';';
    echo 'document.getElementById("mc-count").textContent = ' . json_encode($current . ($total > 0 ? ' / ' . $total : '')) . ';';
    echo 'document.getElementById("mc-bar").style.width = ' . json_encode($percent . '%') . ';';
    echo 'document.getElementById("mc-bar").textContent = ' . json_encode($total > 0 ? ($percent . '%') : '') . ';';
    echo '</script>' . PHP_EOL;
    echo str_repeat(' ', 2048);
    @ob_flush();
    @flush();
}

function mastercomSyncAutoPost(array $fields): void
{
    echo '<form id="mc-next-form" method="post" action="mastercom_sync.php">';
    foreach ($fields as $name => $value) {
        echo '<input type="hidden" name="' . htmlspecialchars((string)$name) . '" value="' . htmlspecialchars((string)$value) . '">';
    }
    echo '</form>';
    echo '<script>setTimeout(function(){ document.getElementById("mc-next-form").submit(); }, 300);</script>';
    echo str_repeat(' ', 2048);
    @flush();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>MasterCom Sync</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>
<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading"><span class="glyphicon glyphicon-transfer"></span>&emsp;Sincronizzazione MasterCom in corso</div>
        <div class="panel-body">
            <p id="mc-stage"><strong>Avvio...</strong></p>
            <p id="mc-message">Preparazione sincronizzazione</p>
            <p id="mc-count"></p>
            <div class="progress">
                <div id="mc-bar" class="progress-bar progress-bar-info" role="progressbar" style="width:0%">0%</div>
            </div>
            <div id="mc-result"></div>
            <p><a href="mastercom.php" class="btn btn-default">Torna alla dashboard</a></p>
        </div>
    </div>
</div>
</body>
</html>
<?php

@ob_implicit_flush(true);
while (ob_get_level() > 0) {
    @ob_end_flush();
}

$missingTables = mastercomAdminMissingTables();
if (!empty($missingTables)) {
    mastercomSyncRenderProgress('Errore', 'Tabelle MasterCom mancanti: ' . implode(', ', $missingTables));
    exit;
}

$entity = trim((string)($_POST['entity'] ?? ''));
$result = ['ok' => false, 'message' => 'Operazione non riconosciuta'];
$progress = function (string $stage, int $current, int $total, string $message): void {
    $titles = [
        'teachers' => 'Sincronizzazione docenti',
        'classes' => 'Sincronizzazione classi',
        'students_class' => 'Sincronizzazione studenti classe',
        'students_all' => 'Sincronizzazione studenti tutte le classi',
        'parents' => 'Sincronizzazione genitori',
    ];
    mastercomSyncRenderProgress($titles[$stage] ?? 'Sincronizzazione MasterCom', $message, $current, $total);
};

if ($entity === 'teachers') {
    $result = mastercomAdminSyncTeachers($progress);
} elseif ($entity === 'classes') {
    $result = mastercomAdminSyncClasses($progress);
} elseif ($entity === 'parents') {
    $token = trim((string)($_POST['token'] ?? ''));
    $offset = intval($_POST['offset'] ?? 0);
    $limit = intval($_POST['limit'] ?? 25);
    if ($limit <= 0) {
        $limit = 25;
    }

    if ($token === '') {
        $listResult = mastercomAdminLoadParentsList();
        if (!$listResult['ok']) {
            $result = $listResult;
        } else {
            $token = uniqid('parents_', true);
            $file = mastercomAdminParentsSyncFile($token);
            file_put_contents($file, json_encode(array_values($listResult['records']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $total = count($listResult['records']);
            mastercomSyncRenderProgress('Sincronizzazione genitori', 'Elenco genitori caricato', 0, $total);
            mastercomSyncAutoPost([
                'entity' => 'parents',
                'token' => $token,
                'offset' => 0,
                'limit' => $limit,
            ]);
            exit;
        }
    } else {
        $file = mastercomAdminParentsSyncFile($token);
        if (!is_file($file)) {
            $result = ['ok' => false, 'message' => 'Stato sincronizzazione genitori non trovato'];
        } else {
            $allParents = json_decode((string)file_get_contents($file), true);
            if (!is_array($allParents)) {
                @unlink($file);
                $result = ['ok' => false, 'message' => 'Coda sincronizzazione genitori non valida'];
            } else {
                $total = count($allParents);
                $chunk = array_slice($allParents, $offset, $limit);
                $result = mastercomAdminSyncParentsChunk($chunk, $offset, $total, $progress);
                if ($result['ok']) {
                    $nextOffset = $offset + count($chunk);
                    if ($nextOffset < $total) {
                        mastercomSyncRenderProgress('Sincronizzazione genitori', 'Blocco completato', $nextOffset, $total);
                        mastercomSyncAutoPost([
                            'entity' => 'parents',
                            'token' => $token,
                            'offset' => $nextOffset,
                            'limit' => $limit,
                        ]);
                        exit;
                    }
                    @unlink($file);
                    $result['message'] = 'Genitori sincronizzati: ' . $total;
                } else {
                    @unlink($file);
                }
            }
        }
    }
} elseif ($entity === 'students') {
    $classId = intval($_POST['class_id'] ?? 0);
    $token = trim((string)($_POST['token'] ?? ''));
    $offset = intval($_POST['offset'] ?? 0);
    $limit = intval($_POST['limit'] ?? 25);
    if ($limit <= 0) {
        $limit = 25;
    }

    if ($token === '') {
        $listResult = mastercomAdminLoadStudentsListForClass($classId);
        if (!$listResult['ok']) {
            $result = $listResult;
        } else {
            $token = uniqid('students_', true);
            $file = mastercomAdminStudentsSyncFile($token);
            file_put_contents($file, json_encode(array_values($listResult['records']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $total = count($listResult['records']);
            mastercomSyncRenderProgress('Sincronizzazione studenti classe', 'Elenco studenti caricato', 0, $total);
            mastercomSyncAutoPost([
                'entity' => 'students',
                'class_id' => $classId,
                'token' => $token,
                'offset' => 0,
                'limit' => $limit,
            ]);
            exit;
        }
    } else {
        $file = mastercomAdminStudentsSyncFile($token);
        if (!is_file($file)) {
            $result = ['ok' => false, 'message' => 'Stato sincronizzazione studenti non trovato'];
        } else {
            $allStudents = json_decode((string)file_get_contents($file), true);
            if (!is_array($allStudents)) {
                @unlink($file);
                $result = ['ok' => false, 'message' => 'Coda sincronizzazione studenti non valida'];
            } else {
                $total = count($allStudents);
                $chunk = array_slice($allStudents, $offset, $limit);
                mastercomSyncRenderProgress('Sincronizzazione studenti classe', 'Elaborazione blocco studenti', $offset, $total);
                $result = mastercomAdminSyncStudentsChunk($classId, $chunk, $offset, $total, null);
                if ($result['ok']) {
                    $nextOffset = $offset + count($chunk);
                    if ($nextOffset < $total) {
                        mastercomSyncRenderProgress('Sincronizzazione studenti classe', 'Blocco completato', $nextOffset, $total);
                        mastercomSyncAutoPost([
                            'entity' => 'students',
                            'class_id' => $classId,
                            'token' => $token,
                            'offset' => $nextOffset,
                            'limit' => $limit,
                        ]);
                        exit;
                    }
                    @unlink($file);
                    $result['message'] = 'Studenti sincronizzati per classe ' . $classId . ': ' . $total;
                } else {
                    @unlink($file);
                }
            }
        }
    }
} elseif ($entity === 'students_all') {
    $token = trim((string)($_POST['token'] ?? ''));
    $offset = intval($_POST['offset'] ?? 0);

    if ($token === '') {
        $classIds = dbGetAllValues("SELECT mastercom_id_classe FROM mastercom_classi ORDER BY nome ASC");
        $classIds = array_values(array_filter(array_map('intval', is_array($classIds) ? $classIds : []), function ($id) {
            return $id > 0;
        }));

        if (empty($classIds)) {
            $result = ['ok' => false, 'message' => 'Nessuna classe MasterCom disponibile. Sincronizza prima le classi.'];
        } else {
            $token = uniqid('students_all_', true);
            $file = mastercomAdminStudentsAllSyncFile($token);
            file_put_contents($file, json_encode($classIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            mastercomSyncRenderProgress('Sincronizzazione studenti tutte le classi', 'Elenco classi caricato', 0, count($classIds));
            mastercomSyncAutoPost([
                'entity' => 'students_all',
                'token' => $token,
                'offset' => 0,
            ]);
            exit;
        }
    } else {
        $file = mastercomAdminStudentsAllSyncFile($token);
        if (!is_file($file)) {
            $result = ['ok' => false, 'message' => 'Stato sincronizzazione studenti tutte le classi non trovato'];
        } else {
            $classIds = json_decode((string)file_get_contents($file), true);
            if (!is_array($classIds) || empty($classIds)) {
                @unlink($file);
                $result = ['ok' => false, 'message' => 'Coda sincronizzazione classi non valida'];
            } else {
                $total = count($classIds);
                if ($offset >= $total) {
                    @unlink($file);
                    $result = ['ok' => true, 'message' => 'Studenti sincronizzati per tutte le classi: ' . $total];
                } else {
                    $classId = intval($classIds[$offset] ?? 0);
                    $className = dbGetValue("SELECT nome FROM mastercom_classi WHERE mastercom_id_classe = " . $classId . " LIMIT 1");
                    mastercomSyncRenderProgress(
                        'Sincronizzazione studenti tutte le classi',
                        'Sincronizzazione classe ' . ($className ?: $classId),
                        $offset,
                        $total
                    );

                    $classResult = mastercomAdminSyncStudentsForClass($classId, null);
                    if (!$classResult['ok']) {
                        @unlink($file);
                        $result = ['ok' => false, 'message' => 'Errore sulla classe ' . $classId . ': ' . ($classResult['message'] ?? 'SYNC_FAILED')];
                    } else {
                        $nextOffset = $offset + 1;
                        if ($nextOffset < $total) {
                            mastercomSyncRenderProgress(
                                'Sincronizzazione studenti tutte le classi',
                                'Classe completata: ' . ($className ?: $classId),
                                $nextOffset,
                                $total
                            );
                            mastercomSyncAutoPost([
                                'entity' => 'students_all',
                                'token' => $token,
                                'offset' => $nextOffset,
                            ]);
                            exit;
                        }

                        @unlink($file);
                        $result = ['ok' => true, 'message' => 'Studenti sincronizzati per tutte le classi: ' . $total];
                    }
                }
            }
        }
    }
}

if ($result['ok']) {
    echo '<script>document.getElementById("mc-result").innerHTML = ' . json_encode('<div class="alert alert-success">' . htmlspecialchars($result['message']) . '</div>') . ';</script>';
} else {
    echo '<script>document.getElementById("mc-result").innerHTML = ' . json_encode('<div class="alert alert-danger">' . htmlspecialchars($result['message']) . '</div>') . ';</script>';
}
echo str_repeat(' ', 2048);
@flush();
exit;
