<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

$idScenario = ob_int($_POST['id_scenario'] ?? 0);
$idImport = ob_int($_POST['id_import'] ?? 0);

$aliases = $_POST['alias_classe'] ?? [];
$classi = $_POST['id_classe'] ?? [];

foreach ($aliases as $i => $alias) {
    $alias = strtoupper(trim((string)$alias));
    $idClasse = ob_int($classi[$i] ?? 0);

    if ($alias === '' || $idClasse <= 0) {
        continue;
    }

    dbExec("
        INSERT INTO orario_import_classe_alias (
            alias_classe,
            id_classe,
            note
        ) VALUES (
            " . dbQNotNull($alias) . ",
            $idClasse,
            'Creato da import CSV orario'
        )
        ON DUPLICATE KEY UPDATE
            id_classe = VALUES(id_classe),
            note = VALUES(note)
    ");
}

unset($_SESSION['orario_import_classi_mancanti']);

ob_redirect("docenti_materie_import_csv_run.php?id_scenario=$idScenario&id_import=$idImport");