<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/admin_lib.php';

ruoloRichiesto('admin');

@ignore_user_abort(true);
@set_time_limit(0);
@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '512M');
@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', 'off');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Accel-Buffering: no');

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

function mastercomSyncStudentsAllMessage(int $completedClasses, int $totalClasses, array $failedClasses): string
{
    $message = 'Studenti sincronizzati per tutte le classi: ' . $completedClasses . ' / ' . $totalClasses;
    if (!empty($failedClasses)) {
        $labels = [];
        foreach ($failedClasses as $failedClass) {
            if (!is_array($failedClass)) {
                continue;
            }
            $label = trim((string)($failedClass['name'] ?? ''));
            if ($label === '') {
                $label = 'classe ' . intval($failedClass['class_id'] ?? 0);
            }
            $error = trim((string)($failedClass['message'] ?? 'errore non specificato'));
            $labels[] = $label . ' (' . $error . ')';
        }
        $message .= ' | classi non sincronizzate: ' . implode('; ', $labels);
    }
    return $message;
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
} elseif ($entity === 'rebuild_parent_student_links') {
    $result = mastercomAdminRebuildParentStudentLinks($progress);
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
    $limit = intval($_POST['limit'] ?? 5);
    if ($limit <= 0) {
        $limit = 5;
    }

    if ($token === '') {
        $listResult = mastercomAdminLoadStudentsListForClass($classId);
        if (!$listResult['ok']) {
            $result = $listResult;
        } else {
            mastercomSyncRenderProgress('Sincronizzazione studenti classe', 'Caricamento CSV dati religione / attività alternativa', 0, count($listResult['records']));
            $supplementalResult = mastercomAdminBuildStudentSupplementalMapForClass($classId);
            $token = uniqid('students_', true);
            $file = mastercomAdminStudentsSyncFile($token);
            file_put_contents($file, json_encode([
                'students' => array_values($listResult['records']),
                'supplemental_map' => $supplementalResult['ok'] ? ($supplementalResult['map'] ?? []) : [],
                'supplemental_debug' => [
                    'ok' => $supplementalResult['ok'] ?? false,
                    'message' => $supplementalResult['message'] ?? '',
                    'rows_count' => intval($supplementalResult['rows_count'] ?? 0),
                    'elapsed_seconds' => $supplementalResult['elapsed_seconds'] ?? 0,
                    'http_code' => intval($supplementalResult['http_code'] ?? 0),
                    'content_type' => (string)($supplementalResult['content_type'] ?? ''),
                    'preview' => (string)($supplementalResult['preview'] ?? ''),
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $total = count($listResult['records']);
            $csvMessage = ($supplementalResult['ok'] ?? false)
                ? ('CSV extra caricato: ' . intval($supplementalResult['rows_count'] ?? 0) . ' righe in ' . ($supplementalResult['elapsed_seconds'] ?? 0) . 's')
                : ('CSV extra non disponibile: ' . trim((string)($supplementalResult['message'] ?? 'errore sconosciuto')));
            mastercomSyncRenderProgress('Sincronizzazione studenti classe', $csvMessage, 0, $total);
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
            $payload = json_decode((string)file_get_contents($file), true);
            if (!is_array($payload)) {
                @unlink($file);
                $result = ['ok' => false, 'message' => 'Coda sincronizzazione studenti non valida'];
            } else {
                $allStudents = $payload['students'] ?? $payload;
                $supplementalMap = is_array($payload['supplemental_map'] ?? null) ? $payload['supplemental_map'] : [];
                $supplementalDebug = is_array($payload['supplemental_debug'] ?? null) ? $payload['supplemental_debug'] : [];
                if (!is_array($allStudents)) {
                    @unlink($file);
                    $result = ['ok' => false, 'message' => 'Coda sincronizzazione studenti non valida'];
                } else {
                    $total = count($allStudents);
                    $chunk = array_slice($allStudents, $offset, $limit);
                    $csvDebugText = '';
                    if (!empty($supplementalDebug)) {
                        if (!empty($supplementalDebug['ok'])) {
                            $csvDebugText = ' | CSV extra: ' . intval($supplementalDebug['rows_count'] ?? 0) . ' righe';
                        } else {
                            $csvDebugText = ' | CSV extra KO';
                        }
                    }
                    mastercomSyncRenderProgress('Sincronizzazione studenti classe', 'Elaborazione blocco studenti' . $csvDebugText, $offset, $total);
                    $result = mastercomAdminSyncStudentsChunk($classId, $chunk, $offset, $total, null, $supplementalMap);
                    if ($result['ok']) {
                        $nextOffset = $offset + count($chunk);
                        if ($nextOffset < $total) {
                            mastercomSyncRenderProgress('Sincronizzazione studenti classe', 'Blocco completato' . $csvDebugText, $nextOffset, $total);
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
                        if (!empty($supplementalDebug)) {
                            $result['message'] .= ' | CSV extra '
                                . (!empty($supplementalDebug['ok']) ? 'OK' : 'KO')
                                . ' | righe=' . intval($supplementalDebug['rows_count'] ?? 0)
                                . ' | tempo=' . ($supplementalDebug['elapsed_seconds'] ?? 0) . 's'
                                . ' | http=' . intval($supplementalDebug['http_code'] ?? 0)
                                . ($supplementalDebug['content_type'] !== '' ? ' | type=' . $supplementalDebug['content_type'] : '')
                                . (!empty($supplementalDebug['preview']) ? ' | preview=' . $supplementalDebug['preview'] : '');
                        }
                    } else {
                        @unlink($file);
                    }
                }
            }
        }
    }
} elseif ($entity === 'students_all') {
    $token = trim((string)($_POST['token'] ?? ''));
    $limit = intval($_POST['limit'] ?? 5);
    if ($limit <= 0) {
        $limit = 5;
    }

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
            file_put_contents($file, json_encode([
                'class_ids' => $classIds,
                'class_index' => 0,
                'current_class_id' => 0,
                'current_students' => [],
                'current_student_offset' => 0,
                'supplemental_map' => [],
                'supplemental_debug' => [],
                'completed_classes' => 0,
                'failed_classes' => [],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            mastercomSyncRenderProgress('Sincronizzazione studenti tutte le classi', 'Elenco classi caricato', 0, count($classIds));
            mastercomSyncAutoPost([
                'entity' => 'students_all',
                'token' => $token,
                'limit' => $limit,
            ]);
            exit;
        }
    } else {
        $file = mastercomAdminStudentsAllSyncFile($token);
        if (!is_file($file)) {
            $result = ['ok' => false, 'message' => 'Stato sincronizzazione studenti tutte le classi non trovato'];
        } else {
            $state = json_decode((string)file_get_contents($file), true);
            $classIds = is_array($state['class_ids'] ?? null) ? $state['class_ids'] : null;
            if (!is_array($classIds) || empty($classIds)) {
                @unlink($file);
                $result = ['ok' => false, 'message' => 'Coda sincronizzazione classi non valida'];
            } else {
                $total = count($classIds);
                $classIndex = intval($state['class_index'] ?? 0);
                $completedClasses = intval($state['completed_classes'] ?? 0);
                $failedClasses = is_array($state['failed_classes'] ?? null) ? $state['failed_classes'] : [];
                if ($classIndex >= $total) {
                    @unlink($file);
                    $result = [
                        'ok' => empty($failedClasses),
                        'message' => mastercomSyncStudentsAllMessage($completedClasses, $total, $failedClasses),
                    ];
                } else {
                    $classId = intval($state['current_class_id'] ?? 0);
                    $currentStudents = is_array($state['current_students'] ?? null) ? $state['current_students'] : [];
                    $studentOffset = intval($state['current_student_offset'] ?? 0);
                    $supplementalMap = is_array($state['supplemental_map'] ?? null) ? $state['supplemental_map'] : [];
                    $supplementalDebug = is_array($state['supplemental_debug'] ?? null) ? $state['supplemental_debug'] : [];

                    if ($classId <= 0 || empty($currentStudents)) {
                        $classId = intval($classIds[$classIndex] ?? 0);
                        if ($classId <= 0) {
                            @unlink($file);
                            $result = ['ok' => false, 'message' => 'Classe non valida nello stato di sincronizzazione'];
                            goto mastercom_students_all_done;
                        }

                        $className = dbGetValue("SELECT nome FROM mastercom_classi WHERE mastercom_id_classe = " . $classId . " LIMIT 1");
                        mastercomSyncRenderProgress(
                            'Sincronizzazione studenti tutte le classi',
                            'Caricamento classe ' . ($className ?: $classId),
                            $classIndex,
                            $total
                        );

                        $listResult = mastercomAdminLoadStudentsListForClass($classId);
                        if (!$listResult['ok']) {
                            $failedClasses[] = [
                                'class_id' => $classId,
                                'name' => (string)($className ?: $classId),
                                'message' => 'caricamento studenti fallito: ' . ($listResult['message'] ?? 'LOAD_FAILED'),
                            ];
                            $classIndex++;
                            $state = [
                                'class_ids' => $classIds,
                                'class_index' => $classIndex,
                                'current_class_id' => 0,
                                'current_students' => [],
                                'current_student_offset' => 0,
                                'supplemental_map' => [],
                                'supplemental_debug' => [],
                                'completed_classes' => $completedClasses,
                                'failed_classes' => $failedClasses,
                            ];
                            file_put_contents($file, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                            mastercomSyncRenderProgress(
                                'Sincronizzazione studenti tutte le classi',
                                'Classe saltata per errore MasterCom: ' . ($className ?: $classId),
                                min($classIndex, $total),
                                $total
                            );
                            if ($classIndex < $total) {
                                mastercomSyncAutoPost([
                                    'entity' => 'students_all',
                                    'token' => $token,
                                    'limit' => $limit,
                                ]);
                                exit;
                            }
                            @unlink($file);
                            $result = [
                                'ok' => false,
                                'message' => mastercomSyncStudentsAllMessage($completedClasses, $total, $failedClasses),
                            ];
                            goto mastercom_students_all_done;
                        }

                        $supplementalResult = mastercomAdminBuildStudentSupplementalMapForClass($classId);
                        $currentStudents = array_values($listResult['records'] ?? []);
                        $studentOffset = 0;
                        $supplementalMap = $supplementalResult['ok'] ? ($supplementalResult['map'] ?? []) : [];
                        $supplementalDebug = [
                            'ok' => $supplementalResult['ok'] ?? false,
                            'message' => $supplementalResult['message'] ?? '',
                            'rows_count' => intval($supplementalResult['rows_count'] ?? 0),
                            'elapsed_seconds' => $supplementalResult['elapsed_seconds'] ?? 0,
                            'http_code' => intval($supplementalResult['http_code'] ?? 0),
                            'content_type' => (string)($supplementalResult['content_type'] ?? ''),
                            'preview' => (string)($supplementalResult['preview'] ?? ''),
                        ];
                        $state = [
                            'class_ids' => $classIds,
                            'class_index' => $classIndex,
                            'current_class_id' => $classId,
                            'current_students' => $currentStudents,
                            'current_student_offset' => $studentOffset,
                            'supplemental_map' => $supplementalMap,
                            'supplemental_debug' => $supplementalDebug,
                            'completed_classes' => $completedClasses,
                            'failed_classes' => $failedClasses,
                        ];
                        file_put_contents($file, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    }

                    $className = dbGetValue("SELECT nome FROM mastercom_classi WHERE mastercom_id_classe = " . $classId . " LIMIT 1");
                    $classStudentsTotal = count($currentStudents);
                    $chunk = array_slice($currentStudents, $studentOffset, $limit);
                    $csvDebugText = '';
                    if (!empty($supplementalDebug)) {
                        $csvDebugText = !empty($supplementalDebug['ok'])
                            ? (' | CSV extra: ' . intval($supplementalDebug['rows_count'] ?? 0) . ' righe')
                            : ' | CSV extra KO';
                    }
                    mastercomSyncRenderProgress(
                        'Sincronizzazione studenti tutte le classi',
                        'Classe ' . ($className ?: $classId) . ' | studenti ' . $studentOffset . '/' . $classStudentsTotal . $csvDebugText,
                        $classIndex,
                        $total
                    );

                    $classResult = mastercomAdminSyncStudentsChunk($classId, $chunk, $studentOffset, $classStudentsTotal, null, $supplementalMap);
                    if (!$classResult['ok']) {
                        $failedClasses[] = [
                            'class_id' => $classId,
                            'name' => (string)($className ?: $classId),
                            'message' => $classResult['message'] ?? 'SYNC_FAILED',
                        ];
                        $classIndex++;
                        if ($classIndex < $total) {
                            $state = [
                                'class_ids' => $classIds,
                                'class_index' => $classIndex,
                                'current_class_id' => 0,
                                'current_students' => [],
                                'current_student_offset' => 0,
                                'supplemental_map' => [],
                                'supplemental_debug' => [],
                                'completed_classes' => $completedClasses,
                                'failed_classes' => $failedClasses,
                            ];
                            file_put_contents($file, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                            mastercomSyncRenderProgress(
                                'Sincronizzazione studenti tutte le classi',
                                'Classe saltata per errore sincronizzazione: ' . ($className ?: $classId),
                                $classIndex,
                                $total
                            );
                            mastercomSyncAutoPost([
                                'entity' => 'students_all',
                                'token' => $token,
                                'limit' => $limit,
                            ]);
                            exit;
                        }

                        @unlink($file);
                        $result = [
                            'ok' => false,
                            'message' => mastercomSyncStudentsAllMessage($completedClasses, $total, $failedClasses),
                        ];
                    } else {
                        $nextStudentOffset = $studentOffset + count($chunk);
                        if ($nextStudentOffset < $classStudentsTotal) {
                            $state['current_student_offset'] = $nextStudentOffset;
                            file_put_contents($file, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                            mastercomSyncRenderProgress(
                                'Sincronizzazione studenti tutte le classi',
                                'Classe ' . ($className ?: $classId) . ' | blocco completato ' . $nextStudentOffset . '/' . $classStudentsTotal . $csvDebugText,
                                $classIndex,
                                $total
                            );
                            mastercomSyncAutoPost([
                                'entity' => 'students_all',
                                'token' => $token,
                                'limit' => $limit,
                            ]);
                            exit;
                        }

                        $classIndex++;
                        $completedClasses++;
                        if ($classIndex < $total) {
                            $state = [
                                'class_ids' => $classIds,
                                'class_index' => $classIndex,
                                'current_class_id' => 0,
                                'current_students' => [],
                                'current_student_offset' => 0,
                                'supplemental_map' => [],
                                'supplemental_debug' => [],
                                'completed_classes' => $completedClasses,
                                'failed_classes' => $failedClasses,
                            ];
                            file_put_contents($file, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                            mastercomSyncRenderProgress(
                                'Sincronizzazione studenti tutte le classi',
                                'Classe completata: ' . ($className ?: $classId),
                                $classIndex,
                                $total
                            );
                            mastercomSyncAutoPost([
                                'entity' => 'students_all',
                                'token' => $token,
                                'limit' => $limit,
                            ]);
                            exit;
                        }

                        @unlink($file);
                        $result = [
                            'ok' => empty($failedClasses),
                            'message' => mastercomSyncStudentsAllMessage($completedClasses, $total, $failedClasses),
                        ];
                    }
                }
            }
        }
    }
    mastercom_students_all_done:
}

if ($result['ok']) {
    echo '<script>document.getElementById("mc-result").innerHTML = ' . json_encode('<div class="alert alert-success">' . htmlspecialchars($result['message']) . '</div>') . ';</script>';
} else {
    echo '<script>document.getElementById("mc-result").innerHTML = ' . json_encode('<div class="alert alert-danger">' . htmlspecialchars($result['message']) . '</div>') . ';</script>';
}
echo str_repeat(' ', 2048);
@flush();
exit;
