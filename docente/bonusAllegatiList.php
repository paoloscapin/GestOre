<?php
/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('segreteria-docenti', 'dirigente', 'docente');

$bonus_docente_id = isset($_GET['bonus_docente_id']) ? intval($_GET['bonus_docente_id']) : 0;
$anno = isset($_GET['anno_scolastico_id']) ? intval($_GET['anno_scolastico_id']) : $__anno_scolastico_corrente_id;

if ($bonus_docente_id <= 0) {
    exit;
}

// sicurezza: il docente può vedere solo i suoi record (dirigente vede tutto)
$whereOwner = "";
if (!haRuolo('dirigente')) {
    $whereOwner = " AND bd.docente_id = " . intval($__docente_id) . " ";
}

$query = "
SELECT a.id, a.original_name, a.file_size, a.uploaded_at
FROM bonus_docente_allegato a
JOIN bonus_docente bd ON bd.id = a.bonus_docente_id
WHERE a.bonus_docente_id = $bonus_docente_id
  AND a.anno_scolastico_id = $anno
  $whereOwner
ORDER BY a.uploaded_at DESC
";
$rows = dbGetAll($query);

$canEdit = $__config->getBonus_rendiconto_aperto() && ($anno == $__anno_scolastico_corrente_id) && haRuolo('docente');

echo '<ul class="list-group" style="margin:0;">';
if (empty($rows)) {
    echo '<li class="list-group-item text-muted">Nessun allegato</li>';
} else {
    foreach ($rows as $r) {
        $id = intval($r['id']);
        $name = htmlspecialchars($r['original_name']);
        $sizeKb = round(intval($r['file_size']) / 1024);
        $dt = htmlspecialchars($r['uploaded_at']);

        echo '<li class="list-group-item">';
        $viewUrl = $__application_base_path . '/docente/bonusAllegatoDownload.php?id=' . $id;
        $downloadUrl = $viewUrl . '&download=1';

        echo '<li class="list-group-item">';
        echo '<a href="' . $viewUrl . '" target="_blank">' . $name . '</a>';
        echo ' <span class="text-muted">(' . $sizeKb . ' KB, ' . $dt . ')</span>';
        echo ' &nbsp; <a class="btn btn-xs btn-default" href="' . $downloadUrl . '"><span class="glyphicon glyphicon-download"></span></a>';
        echo '</li>';

        echo ' <span class="text-muted">(' . $sizeKb . ' KB, ' . $dt . ')</span>';

        if ($canEdit) {
            echo ' <button class="btn btn-danger btn-xs pull-right btn-del-allegato" data-id="' . $id . '"><span class="glyphicon glyphicon-trash"></span></button>';
        }
        echo '</li>';
    }
}
echo '</ul>';
