<?php
require_once __DIR__ . '/orario_builder_lib.php';

$idPiano = ob_int($_GET['id_piano'] ?? 0);

$piano = dbGetFirst("
    SELECT *
    FROM orario_piano_orario
    WHERE id = $idPiano
");

if (!$piano) {
    die('Piano non trovato');
}

$classi = dbGetAll("
    SELECT *
    FROM classi
    ORDER BY classe
") ?: [];

$associate = dbGetAll("
    SELECT DISTINCT id_classe
    FROM orario_piano_orario_classe_alias
    WHERE id_piano_orario = $idPiano
      AND id_classe IS NOT NULL

    UNION

    SELECT DISTINCT cp.id_classe
    FROM orario_classe_piano_orario cp
    WHERE cp.id_piano_orario = $idPiano
      AND cp.attivo = 1
") ?: [];

$gia = [];
foreach ($associate as $a) {
    $gia[intval($a['id_classe'])] = true;
}
?>
<!doctype html>
<html lang="it">

<head>
    <meta charset="utf-8">
    <title>Associa classi</title>
    <style>
        body {
            font-family: Arial;
            margin: 24px;
            background: #f5f6f8;
        }

        .box {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px #0001;
        }

        button,
        a.btn {
            padding: 10px 14px;
            background: #2563eb;
            color: white;
            border: 0;
            border-radius: 8px;
            text-decoration: none;
        }

        .classes {
            columns: 3;
        }

        label {
            display: block;
            margin-bottom: 8px;
        }
    </style>
</head>

<body>

    <div class="box">
        <h1>Associa classi - <?= ob_h($piano['nome']) ?></h1>
        <a class="btn" href="piani_orario.php">Indietro</a>
    </div>

    <div class="box">
        <form method="post" action="classe_piano_save.php">
            <input type="hidden" name="id_piano" value="<?= $idPiano ?>">
            <input type="hidden" name="id_anno_scolastico" value="<?= intval($piano['id_anno_scolastico']) ?>">

            <div class="classes">
                <?php foreach ($classi as $c): ?>
                    <label>
                        <input type="checkbox" name="classi[]" value="<?= intval($c['id']) ?>"
                            <?= isset($gia[intval($c['id'])]) ? 'checked' : '' ?>>
                        <?= ob_h($c['classe']) ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <button type="submit">Salva associazioni</button>
        </form>
    </div>

</body>

</html>