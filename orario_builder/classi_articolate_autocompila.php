<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

$idScenario = ob_int($_POST['id_scenario'] ?? 0);
$idGruppo = ob_int($_POST['id_gruppo'] ?? 0);

if ($idScenario <= 0 || $idGruppo <= 0) {
    die('Dati non validi');
}

$classiGruppo = dbGetAll("
    SELECT ac.id_classe, c.classe
    FROM orario_classe_articolata_classe ac
    JOIN classi c ON c.id = ac.id_classe
    WHERE ac.id_gruppo = $idGruppo
    ORDER BY c.classe
") ?: [];

if (count($classiGruppo) < 2) {
    die('Servono almeno due classi nel gruppo articolato');
}

$materiePerClasse = [];
$tutteMaterie = [];

foreach ($classiGruppo as $cg) {
    $idClasse = intval($cg['id_classe']);

    $idPiano = dbGetValue("
        SELECT id_piano_orario
        FROM orario_classe_piano_orario
        WHERE id_classe = $idClasse
          AND attivo = 1
        LIMIT 1
    ");

    if (!$idPiano) {
        $idPiano = dbGetValue("
            SELECT id_piano_orario
            FROM orario_piano_orario_classe_alias
            WHERE id_classe = $idClasse
            LIMIT 1
        ");
    }

    if (!$idPiano) {
        continue;
    }

    $materie = dbGetAll("
        SELECT id_materia
        FROM orario_piano_orario_materia
        WHERE id_piano_orario = " . intval($idPiano) . "
    ") ?: [];

    foreach ($materie as $m) {
        $idMateria = intval($m['id_materia']);
        $materiePerClasse[$idClasse][$idMateria] = true;
        $tutteMaterie[$idMateria] = true;
    }
}

$numeroClassi = count($classiGruppo);

foreach (array_keys($tutteMaterie) as $idMateria) {
    $presenze = 0;

    foreach ($classiGruppo as $cg) {
        $idClasse = intval($cg['id_classe']);
        if (!empty($materiePerClasse[$idClasse][$idMateria])) {
            $presenze++;
        }
    }

    if ($presenze === $numeroClassi) {
        dbExec("
            INSERT INTO orario_classe_articolata_materia (
                id_gruppo,
                id_materia,
                tipo
            ) VALUES (
                $idGruppo,
                $idMateria,
                'COMUNE'
            )
            ON DUPLICATE KEY UPDATE
                tipo = 'COMUNE'
        ");
    }
}

/*
 * Crea gruppi materie di indirizzo per ciascuna classe.
 * Dentro mette le materie NON comuni, cioè quelle presenti solo in alcune classi.
 */
foreach ($classiGruppo as $cg) {
    $idClasse = intval($cg['id_classe']);
    $nomeClasse = dbQNotNull('Indirizzo ' . $cg['classe']);

    $idGruppoMaterie = dbGetValue("
        SELECT id
        FROM orario_classe_articolata_gruppo_materie
        WHERE id_gruppo_articolato = $idGruppo
          AND id_classe = $idClasse
          AND nome = $nomeClasse
        LIMIT 1
    ");

    if (!$idGruppoMaterie) {
        dbExec("
            INSERT INTO orario_classe_articolata_gruppo_materie (
                id_gruppo_articolato,
                id_classe,
                nome,
                attivo
            ) VALUES (
                $idGruppo,
                $idClasse,
                $nomeClasse,
                1
            )
        ");

        $idGruppoMaterie = dbGetValue("SELECT LAST_INSERT_ID()");
    }

    foreach (array_keys($materiePerClasse[$idClasse] ?? []) as $idMateria) {
        $presenze = 0;

        foreach ($classiGruppo as $cg2) {
            $idClasse2 = intval($cg2['id_classe']);
            if (!empty($materiePerClasse[$idClasse2][$idMateria])) {
                $presenze++;
            }
        }

        if ($presenze < $numeroClassi) {
            dbExec("
                INSERT INTO orario_classe_articolata_gruppo_materie_riga (
                    id_gruppo_materie,
                    id_materia
                ) VALUES (
                    " . intval($idGruppoMaterie) . ",
                    " . intval($idMateria) . "
                )
                ON DUPLICATE KEY UPDATE
                    id_materia = VALUES(id_materia)
            ");
        }
    }
}


/*
 * Sincronizzazione automatica dei gruppi materie di indirizzo.
 * Tutti i gruppi di indirizzo vengono sincronizzati tra loro.
 * Per 2 gruppi crea una sincronizzazione diretta.
 * Per 3 o più gruppi usa il primo come gruppo base: il vincolo è transitivo.
 */
$gruppiIndirizzo = dbGetAll("
    SELECT id, nome
    FROM orario_classe_articolata_gruppo_materie
    WHERE id_gruppo_articolato = $idGruppo
      AND attivo = 1
    ORDER BY id
") ?: [];

if (count($gruppiIndirizzo) >= 2) {
    dbExec("
        UPDATE orario_classe_articolata_sincronizzazione
        SET attivo = 0,
            updated_at = NOW()
        WHERE id_gruppo_articolato = $idGruppo
    ");

    $base = $gruppiIndirizzo[0];
    $idBase = intval($base['id']);

    for ($i = 1; $i < count($gruppiIndirizzo); $i++) {
        $altro = $gruppiIndirizzo[$i];
        $idAltro = intval($altro['id']);

        $nomeSync = dbQNotNull('Sync automatico ' . $base['nome'] . ' / ' . $altro['nome']);

        dbExec("
            INSERT INTO orario_classe_articolata_sincronizzazione (
                id_gruppo_articolato,
                nome,
                id_gruppo_materie_a,
                id_gruppo_materie_b,
                ore_settimanali,
                attivo
            ) VALUES (
                $idGruppo,
                $nomeSync,
                $idBase,
                $idAltro,
                NULL,
                1
            )
        ");
    }
}

ob_redirect("classi_articolate.php?id_scenario=$idScenario");