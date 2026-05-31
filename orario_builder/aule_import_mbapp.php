<?php
require_once __DIR__ . '/orario_builder_lib.php';
require_once __DIR__ . '/../common/connectMBApp.php';

$importate = 0;
$aggiornate = 0;
$disattivate = 0;
$saltate = 0;

$auleMb = mb_dbGetAll("
    SELECT
        nroAula,
        tipo,
        dislocazione,
        descrizione,
        piano,
        nroPosti,
        prenotabile
    FROM aula
    WHERE nroAula IS NOT NULL
      AND nroAula <> ''
    ORDER BY nroAula
") ?: [];

$codiciImportati = [];

foreach ($auleMb as $a) {
    $codice = trim((string)($a['nroAula'] ?? ''));

    if ($codice === '') {
        $saltate++;
        continue;
    }

    $codiciImportati[] = $codice;

    $tipoMb = trim((string)($a['tipo'] ?? ''));
    $descrizione = trim((string)($a['descrizione'] ?? ''));
    $dislocazione = trim((string)($a['dislocazione'] ?? ''));
    $pianoMb = trim((string)($a['piano'] ?? ''));
    $prenotabile = strtoupper(trim((string)($a['prenotabile'] ?? 'SI')));

    $nome = $descrizione !== '' ? $descrizione : $codice;
    $piano = normalizzaPianoMbApp($pianoMb, $codice);
    $tipo = trim((string)$tipoMb);
    $ala = trim((string)$dislocazione);

    $capienza = intval($a['nroPosti'] ?? 0);
    $capienzaSql = $capienza > 0 ? (string)$capienza : "NULL";

    $attiva = ($prenotabile === 'SI') ? 1 : 0;

    $note = trim(
        "Importata da MBApp\n" .
            "Tipo MBApp: " . $tipoMb . "\n" .
            "Dislocazione MBApp: " . $dislocazione . "\n" .
            "Piano MBApp: " . $pianoMb . "\n" .
            "Prenotabile: " . $prenotabile
    );

    $codiceSql = dbQNotNull($codice);
    $nomeSql = dbQNotNull($nome);
    $pianoSql = dbQNotNull($piano);
    $tipoSql = dbQNotNull($tipo);
    $noteSql = dbQ($note);
    $alaSql = dbQ($ala);
    $exists = dbGetValue("
        SELECT id
        FROM aule
        WHERE codice = $codiceSql
        LIMIT 1
    ");

    if ($exists) {
        dbExec("
            UPDATE aule
            SET
                nome = $nomeSql,
                piano = $pianoSql,
                ala = $alaSql,
                capienza = $capienzaSql,
                tipo = $tipoSql,
                attiva = $attiva,
                note = $noteSql,
                updated_at = NOW()
            WHERE codice = $codiceSql
        ");

        $aggiornate++;
    } else {
        dbExec("
            INSERT INTO aule (
                codice,
                nome,
                piano,
                ala,
                capienza,
                tipo,
                attiva,
                note
            ) VALUES (
                $codiceSql,
                $nomeSql,
                $pianoSql,
                $alaSql,
                $capienzaSql,
                $tipoSql,
                $attiva,
                $noteSql
            )
        ");

        $importate++;
    }
}

/*
 * Disattiva in GestOre le aule non più presenti in MBApp.
 * Non le cancella, così non rompiamo eventuali riferimenti futuri.
 */
if (!empty($codiciImportati)) {
    $quoted = [];
    foreach ($codiciImportati as $c) {
        $quoted[] = dbQNotNull($c);
    }

    $listaCodici = implode(',', $quoted);

    dbExec("
        UPDATE aule
        SET attiva = 0,
            updated_at = NOW()
        WHERE codice NOT IN ($listaCodici)
    ");

    $disattivate = dbGetValue("
        SELECT COUNT(*)
        FROM aule
        WHERE codice NOT IN ($listaCodici)
          AND attiva = 0
    ");
}

?>
<!doctype html>
<html lang="it">

<head>
    <meta charset="utf-8">
    <title>Import aule MBApp</title>
</head>

<body>
    <h1>Import aule completato</h1>
    <p>Importate: <strong><?php echo intval($importate); ?></strong></p>
    <p>Aggiornate: <strong><?php echo intval($aggiornate); ?></strong></p>
    <p>Disattivate perché non presenti in MBApp: <strong><?php echo intval($disattivate); ?></strong></p>
    <p>Saltate: <strong><?php echo intval($saltate); ?></strong></p>
    <p><a href="aule.php">Torna alle aule</a></p>
</body>

</html>
<?php

function normalizzaPianoMbApp($pianoMb, $codice)
{
    $p = strtoupper(trim((string)$pianoMb));

    if (strpos($p, 'SEMINTERRATO') !== false || strpos($p, 'INTERRATO') !== false) {
        return 'S';
    }

    if (strpos($p, 'RIALZATO') !== false || strpos($p, 'TERRA') !== false) {
        return 'R';
    }

    if (strpos($p, 'PRIMO') !== false) {
        return '1';
    }

    if (strpos($p, 'SECONDO') !== false) {
        return '2';
    }

    if (strpos($p, 'TERZO') !== false) {
        return '3';
    }

    $codice = strtoupper(trim((string)$codice));

    if (preg_match('/^S/', $codice)) return 'S';
    if (preg_match('/^R/', $codice)) return 'R';
    if (preg_match('/^[123]/', $codice, $m)) return $m[0];

    return 'R';
}
