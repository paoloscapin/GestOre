<?php

require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/../common/connectMBApp.php';

ruoloRichiesto('admin');

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function existsDocenteInsegna($idDocente, $idClasse, $idMateria, $idAnno) {
    $q = "
        SELECT id
        FROM docente_insegna
        WHERE id_docente = " . intval($idDocente) . "
          AND id_classe = " . intval($idClasse) . "
          AND id_materia = " . intval($idMateria) . "
          AND id_anno_scolastico = " . intval($idAnno) . "
        LIMIT 1
    ";

    return dbGetFirst($q) !== null;
}

function insertDocenteInsegna($idDocente, $idClasse, $idMateria, $idAnno) {
    $q = "
        INSERT INTO docente_insegna
        (
            id_docente,
            id_classe,
            id_materia,
            id_anno_scolastico
        )
        VALUES
        (
            " . intval($idDocente) . ",
            " . intval($idClasse) . ",
            " . intval($idMateria) . ",
            " . intval($idAnno) . "
        )
    ";

    dbExec($q);
}

$ID_ANNO_SCOLASTICO = (int)$__anno_scolastico_corrente_id;

if ($ID_ANNO_SCOLASTICO <= 0) {
    exit('Errore: anno scolastico corrente non disponibile');
}

$SOLO_PREVIEW = ($_SERVER['REQUEST_METHOD'] !== 'POST');

$CLASSI_DA_ESCLUDERE = [
    '',
    'UDIENZE',
    'NOIRC',
    'SORVNOIRC',
    'LABMAT',
    'L2',
    'PRANZO',
    'ROBOTI',
    'SOSTEGNO'
];

$sqlMbapp = "
    SELECT DISTINCT
        u.username,
        c.classe AS classe,
        o.siglaMateria AS sigla_materia
    FROM utente u
    JOIN utilizza utz
        ON u.username = utz.username
    JOIN oralezione o
        ON utz.idCalendario = o.idCalendario
    JOIN occupa oc
        ON o.idCalendario = oc.idCalendario
    JOIN classe c
        ON oc.classe = c.classe
    WHERE (u.tipo = 'Admin' OR u.tipo = 'Docente')
      AND u.username NOT LIKE 'test%'
      AND u.username <> '.'
      AND u.username <> '.alternativa'
      AND c.classe IS NOT NULL
      AND c.classe <> ''
      AND o.siglaMateria IS NOT NULL
      AND o.siglaMateria <> ''
    ORDER BY u.username, c.classe, o.siglaMateria
";

$righeRaw = mb_dbGetAll($sqlMbapp);

$righeMbapp = [];
$daInserire = [];
$giaPresenti = [];
$errori = [];

foreach ($righeRaw as $r) {
    $username = trim($r['username'] ?? '');
    $classe = trim($r['classe'] ?? '');
    $materia = trim($r['sigla_materia'] ?? '');

    if ($username === '' || $classe === '' || $materia === '') {
        continue;
    }

    if (in_array($classe, $CLASSI_DA_ESCLUDERE, true)) {
        continue;
    }

    $righeMbapp[] = [
        'username' => $username,
        'classe' => $classe,
        'materia' => $materia
    ];
}

foreach ($righeMbapp as $r) {
    $username = $r['username'];
    $classe = $r['classe'];
    $materia = $r['materia'];

    $docente = dbGetFirst("
        SELECT id
        FROM docente
        WHERE username = '" . dbEscape($username) . "'
        LIMIT 1
    ");

    if (!$docente) {
        $errori[] = [
            'tipo' => 'DOCENTE_NON_TROVATO',
            'username' => $username,
            'classe' => $classe,
            'materia' => $materia
        ];
        continue;
    }

    $classeGestore = dbGetFirst("
        SELECT id
        FROM classi
        WHERE classe = '" . dbEscape($classe) . "'
          AND attiva = 1
        LIMIT 1
    ");

    if (!$classeGestore) {
        $errori[] = [
            'tipo' => 'CLASSE_NON_TROVATA',
            'username' => $username,
            'classe' => $classe,
            'materia' => $materia
        ];
        continue;
    }

    $materiaGestore = dbGetFirst("
        SELECT id
        FROM materia
        WHERE codice = '" . dbEscape($materia) . "'
        LIMIT 1
    ");

    if (!$materiaGestore) {
        $errori[] = [
            'tipo' => 'MATERIA_NON_TROVATA',
            'username' => $username,
            'classe' => $classe,
            'materia' => $materia
        ];
        continue;
    }

    $idDocente = intval($docente['id']);
    $idClasse = intval($classeGestore['id']);
    $idMateria = intval($materiaGestore['id']);

    if (existsDocenteInsegna($idDocente, $idClasse, $idMateria, $ID_ANNO_SCOLASTICO)) {
        $giaPresenti[] = [
            'username' => $username,
            'classe' => $classe,
            'materia' => $materia,
            'id_docente' => $idDocente,
            'id_classe' => $idClasse,
            'id_materia' => $idMateria
        ];
        continue;
    }

    $item = [
        'username' => $username,
        'classe' => $classe,
        'materia' => $materia,
        'id_docente' => $idDocente,
        'id_classe' => $idClasse,
        'id_materia' => $idMateria
    ];

    $daInserire[] = $item;

    if (!$SOLO_PREVIEW) {
        insertDocenteInsegna($idDocente, $idClasse, $idMateria, $ID_ANNO_SCOLASTICO);
    }
}

?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Sync docente_insegna da MBApp</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 24px;
            background: #f5f5f5;
            color: #222;
        }

        h1 {
            margin-top: 0;
        }

        .box {
            background: #fff;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 18px;
            box-shadow: 0 1px 4px rgba(0,0,0,.12);
        }

        .ok {
            color: #198754;
            font-weight: bold;
        }

        .warn {
            color: #f39c12;
            font-weight: bold;
        }

        .err {
            color: #dc3545;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            font-size: 14px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            text-align: left;
        }

        th {
            background: #eee;
        }

        button {
            padding: 10px 16px;
            font-size: 15px;
            cursor: pointer;
        }

        .small {
            color: #666;
            font-size: 13px;
        }
    </style>
</head>
<body>

<h1>Sync docente_insegna da MBApp</h1>

<div class="box">
    <p><strong>Anno scolastico ID:</strong> <?= h($ID_ANNO_SCOLASTICO) ?></p>

    <p><strong>Modalità:</strong>
        <?php if ($SOLO_PREVIEW): ?>
            <span class="warn">PREVIEW - nessun dato inserito</span>
        <?php else: ?>
            <span class="ok">SYNC ESEGUITO</span>
        <?php endif; ?>
    </p>

    <p>
        Righe lette da MBApp dopo filtri:
        <strong><?= count($righeMbapp) ?></strong><br>

        Già presenti in docente_insegna:
        <strong><?= count($giaPresenti) ?></strong><br>

        Da inserire<?= $SOLO_PREVIEW ? '' : ' / inseriti' ?>:
        <strong><?= count($daInserire) ?></strong><br>

        Anomalie:
        <strong><?= count($errori) ?></strong>
    </p>

    <?php if ($SOLO_PREVIEW): ?>
        <form method="post">
            <button type="submit">Esegui sincronizzazione</button>
        </form>
    <p><a href="sync_docente_insegna_mbapp.php">Torna alla preview</a></p>
        <p class="ok">Sincronizzazione completata.</p>
        <p><a href="?id_anno_scolastico=<?= h($ID_ANNO_SCOLASTICO) ?>">Torna alla preview</a></p>
    <?php endif; ?>
</div>

<div class="box">
    <h2>Record da inserire<?= $SOLO_PREVIEW ? '' : ' / inseriti' ?></h2>

    <?php if (!$daInserire): ?>
        <p>Nessun nuovo record da inserire.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Username</th>
                <th>Classe</th>
                <th>Materia</th>
                <th>ID docente</th>
                <th>ID classe</th>
                <th>ID materia</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($daInserire as $r): ?>
                <tr>
                    <td><?= h($r['username']) ?></td>
                    <td><?= h($r['classe']) ?></td>
                    <td><?= h($r['materia']) ?></td>
                    <td><?= h($r['id_docente']) ?></td>
                    <td><?= h($r['id_classe']) ?></td>
                    <td><?= h($r['id_materia']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="box">
    <h2>Anomalie</h2>

    <?php if (!$errori): ?>
        <p class="ok">Nessuna anomalia trovata.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Tipo</th>
                <th>Username</th>
                <th>Classe MBApp</th>
                <th>Materia MBApp</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($errori as $e): ?>
                <tr>
                    <td class="err"><?= h($e['tipo']) ?></td>
                    <td><?= h($e['username']) ?></td>
                    <td><?= h($e['classe']) ?></td>
                    <td><?= h($e['materia']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="box">
    <h2>Già presenti</h2>

    <?php if (!$giaPresenti): ?>
        <p>Nessun record già presente.</p>
    <?php else: ?>
        <p class="small">Mostro massimo 200 righe.</p>
        <table>
            <thead>
            <tr>
                <th>Username</th>
                <th>Classe</th>
                <th>Materia</th>
                <th>ID docente</th>
                <th>ID classe</th>
                <th>ID materia</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach (array_slice($giaPresenti, 0, 200) as $r): ?>
                <tr>
                    <td><?= h($r['username']) ?></td>
                    <td><?= h($r['classe']) ?></td>
                    <td><?= h($r['materia']) ?></td>
                    <td><?= h($r['id_docente']) ?></td>
                    <td><?= h($r['id_classe']) ?></td>
                    <td><?= h($r['id_materia']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>