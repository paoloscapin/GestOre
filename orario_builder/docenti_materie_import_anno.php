<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

$idScenario = ob_int($_POST['id_scenario'] ?? 0);
$idAnnoOrigine = ob_int($_POST['id_anno_origine'] ?? 0);

if ($idScenario <= 0 || $idAnnoOrigine <= 0) {
    die('Dati non validi');
}

dbExec("
    INSERT INTO orario_docente_insegna_scenario (
        id_scenario,
        id_docente,
        docente_temporaneo,
        docente_da_nominare,
        docente_key,
        id_classe,
        id_materia,
        origine
    )
    SELECT
        $idScenario,
        di.id_docente,
        NULL,
        0,
        CONCAT('DOCENTE_', di.id_docente),
        di.id_classe,
        di.id_materia,
        'DA_ANNO_PRECEDENTE'
    FROM docente_insegna di
    JOIN docente d ON d.id = di.id_docente
    JOIN classi c ON c.id = di.id_classe
    JOIN materia m ON m.id = di.id_materia
    WHERE di.id_anno_scolastico = $idAnnoOrigine
      AND d.attivo = 1
      AND c.attiva = 1
    ON DUPLICATE KEY UPDATE
        id_docente = VALUES(id_docente),
        docente_temporaneo = NULL,
        docente_da_nominare = 0,
        docente_key = VALUES(docente_key),
        origine = VALUES(origine),
        updated_at = NOW()
");

ob_redirect("docenti_materie.php?id_scenario=$idScenario");