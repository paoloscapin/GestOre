<?php

require_once '../common/checkSession.php';
require_once '../common/programmiSvoltiVerificheDigitaliLib.php';

ruoloRichiesto('docente', 'segreteria-didattica', 'dirigente');
header('Content-Type: application/json; charset=utf-8');

function programmiSvoltiVerificheDigitaliJson(bool $ok, array $data = []): void
{
    echo json_encode(array_merge(['success' => $ok], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $programmaId = intval($_GET['programma_id'] ?? 0);
    if ($programmaId <= 0) {
        programmiSvoltiVerificheDigitaliJson(false, ['message' => 'Programma non indicato']);
    }

    $programma = programmiSvoltiCopertinaLoadProgramma($programmaId);
    if (!$programma) {
        programmiSvoltiVerificheDigitaliJson(false, ['message' => 'Programma svolto non trovato']);
    }

    if (!programmiSvoltiVerificheDigitaliCanManage($programma)) {
        programmiSvoltiVerificheDigitaliJson(false, ['message' => 'Non autorizzato']);
    }

    $rows = programmiSvoltiVerificheDigitaliList($programmaId);
    ob_start();
    echo '<ul class="list-group" style="margin:0;">';
    if (empty($rows)) {
        echo '<li class="list-group-item text-muted">Nessun file ZIP caricato.</li>';
    } else {
        foreach ($rows as $row) {
            $id = intval($row['id']);
            $name = htmlspecialchars((string)$row['original_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $link = 'programmiSvoltiVerificheDigitaliDownload.php?id=' . $id;
            $sizeKb = round(intval($row['file_size'] ?? 0) / 1024);
            $uploadedAt = htmlspecialchars((string)($row['uploaded_at'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            echo '<li class="list-group-item">';
            echo '<span class="glyphicon glyphicon-compressed"></span> ';
            echo '<a href="' . htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" target="_blank">' . $name . '</a>';
            echo ' <span class="label label-warning">Drive</span>';
            echo ' <span class="text-muted">(' . $sizeKb . ' KB, ' . $uploadedAt . ')</span>';
            echo ' <a class="btn btn-default btn-xs pull-right" style="margin-left:6px;" href="' . htmlspecialchars($link . '&download=1', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"><span class="glyphicon glyphicon-download-alt"></span></a>';
            echo ' <button type="button" class="btn btn-danger btn-xs pull-right" onclick="programmiSvoltiVerificheDigitaliDelete(' . $id . ')"><span class="glyphicon glyphicon-trash"></span></button>';
            echo '</li>';
        }
    }
    echo '</ul>';
    $html = ob_get_clean();

    programmiSvoltiVerificheDigitaliJson(true, [
        'html' => $html,
        'title' => trim((string)$programma['classe_label'] . ' - ' . (string)$programma['materia_nome']),
        'folderName' => programmiSvoltiVerificheDigitaliProgramFolderName($programma),
    ]);
} catch (Throwable $e) {
    programmiSvoltiVerificheDigitaliJson(false, ['message' => $e->getMessage()]);
}
