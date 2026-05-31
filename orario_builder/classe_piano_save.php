<?php
require_once __DIR__ . '/orario_builder_lib.php';

$idPiano = ob_int($_POST['id_piano'] ?? 0);
$idAnno = ob_int($_POST['id_anno_scolastico'] ?? 0);
$classi = $_POST['classi'] ?? [];

if ($idPiano <= 0 || $idAnno <= 0) {
    die('Dati non validi');
}

/*
 * 1) Disattivo associazioni classiche
 */
dbExec("
    UPDATE orario_classe_piano_orario
    SET attivo = 0
    WHERE id_piano_orario = $idPiano
      AND id_anno_scolastico = $idAnno
");

/*
 * 2) Pulisco associazioni alias già legate a classi reali
 *    Le righe importate solo testuali possono restare.
 */
dbExec("
    DELETE FROM orario_piano_orario_classe_alias
    WHERE id_piano_orario = $idPiano
      AND id_classe IS NOT NULL
");

foreach ($classi as $idClasse) {
    $idClasse = intval($idClasse);

    if ($idClasse <= 0) {
        continue;
    }

    $classeNome = dbGetValue("
        SELECT classe
        FROM classi
        WHERE id = $idClasse
        LIMIT 1
    ");

    if (!$classeNome) {
        continue;
    }

    $classeSql = dbQNotNull($classeNome);

    /*
     * 3) Salvo nella tabella storica/operativa
     */
    dbExec("
        INSERT INTO orario_classe_piano_orario (
            id_anno_scolastico,
            id_classe,
            id_piano_orario,
            attivo
        ) VALUES (
            $idAnno,
            $idClasse,
            $idPiano,
            1
        )
        ON DUPLICATE KEY UPDATE
            id_piano_orario = VALUES(id_piano_orario),
            attivo = 1
    ");

    /*
     * 4) Salvo anche nella tabella usata dall'import/lista piani
     */
    dbExec("
        INSERT INTO orario_piano_orario_classe_alias (
            id_piano_orario,
            alias_classe,
            id_classe
        ) VALUES (
            $idPiano,
            $classeSql,
            $idClasse
        )
        ON DUPLICATE KEY UPDATE
            id_classe = VALUES(id_classe)
    ");
}

ob_redirect("classe_piano.php?id_piano=$idPiano");