<?php
require_once __DIR__ . '/orario_builder_lib.php';

$idScenario = ob_int($_POST['id_scenario'] ?? 0);
$scenario = ob_get_scenario($idScenario);

if (!$scenario) {
    die('Scenario non trovato');
}

$idAnno = intval($scenario['id_anno_scolastico']);

dbExec("
    DELETE FROM orario_fabbisogno_classe
    WHERE id_scenario = $idScenario
");

dbExec("
    INSERT INTO orario_fabbisogno_classe (
        id_scenario,
        id_classe,
        id_docente,
        id_materia,
        ore_settimanali,
        ore_laboratorio,
        ore_compresenza,
        ore_blocco_preferito,
        richiede_aula_specifica,
        id_aula_preferita,
        gruppo_classe,
        compresenza
    )
    SELECT
        $idScenario,
        di.id_classe,
        di.id_docente,
        di.id_materia,

        COALESCE(o.ore_settimanali, pom.ore_settimanali, 0),
        COALESCE(o.ore_laboratorio, pom.ore_laboratorio, 0),
        COALESCE(o.ore_compresenza, pom.ore_compresenza, 0),

        COALESCE(o.blocco_preferito, pom.blocco_preferito),
        CASE
            WHEN COALESCE(o.ore_laboratorio, pom.ore_laboratorio, 0) > 0 THEN 1
            ELSE 0
        END,

        NULL,
        NULL,

        CASE
            WHEN COALESCE(o.ore_compresenza, pom.ore_compresenza, 0) > 0 THEN 1
            ELSE 0
        END
    FROM docente_insegna di
    JOIN classi c ON c.id = di.id_classe
    LEFT JOIN orario_classe_piano_orario cp
        ON cp.id_anno_scolastico = di.id_anno_scolastico
       AND cp.id_classe = di.id_classe
       AND cp.attivo = 1
    LEFT JOIN orario_piano_orario_materia pom
        ON pom.id_piano_orario = cp.id_piano_orario
       AND pom.id_materia = di.id_materia
    LEFT JOIN orario_monteore_classe_override o
        ON o.id_anno_scolastico = di.id_anno_scolastico
       AND o.id_classe = di.id_classe
       AND o.id_materia = di.id_materia
    WHERE di.id_anno_scolastico = $idAnno
      AND c.attiva = 1
");

ob_redirect("fabbisogno.php?id_scenario=$idScenario");