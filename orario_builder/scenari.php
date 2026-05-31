<?php
require_once __DIR__ . '/orario_builder_lib.php';

$anni = ob_get_anni_scolastici();
$scenari = ob_get_scenari();
?>
<!doctype html>
<html lang="it">

<head>
    <meta charset="utf-8">
    <title>Scenari orario</title>
    <style>
        body {
            font-family: Arial;
            margin: 24px;
            background: #f5f6f8;
        }

        .box {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px #0001;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 8px;
            margin: 4px 0 12px;
        }

        button,
        a.btn {
            padding: 10px 14px;
            background: #2563eb;
            color: #fff;
            border: 0;
            border-radius: 8px;
            text-decoration: none;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #eef2ff;
        }
    </style>
</head>

<body>

    <div class="box">
        <h1>Scenari orario</h1>
        <a class="btn" href="index.php">Indietro</a>
    </div>

    <div class="box">
        <h2>Nuovo scenario</h2>

        <form method="post" action="scenario_save.php">
            <label>Nome scenario</label>
            <input type="text" name="nome" required placeholder="Esempio: Orario 2026/2027 - bozza 1">

            <label>Anno scolastico</label>
            <select name="id_anno_scolastico" required>
                <?php foreach ($anni as $a): ?>
                    <option value="<?= intval($a['id']) ?>">
                        <?= ob_h($a['descrizione'] ?? $a['id']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Descrizione</label>
            <textarea name="descrizione"></textarea>

            <button type="submit">Crea scenario</button>
        </form>
    </div>

    <div class="box">
        <h2>Scenari esistenti</h2>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Anno</th>
                    <th>Stato</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($scenari as $s): ?>
                    <tr>
                        <td><?= intval($s['id']) ?></td>
                        <td><?= ob_h($s['nome']) ?></td>
                        <td><?= ob_h($s['id_anno_scolastico']) ?></td>
                        <td><?= ob_h($s['stato']) ?></td>
                        <td>
                            <a class="btn btn-xs btn-warning" href="slot.php?id_scenario=<?= intval($s['id']) ?>">Slot</a>
                            <a class="btn btn-xs btn-warning" href="fabbisogno.php?id_scenario=<?= intval($s['id']) ?>">Fabbisogno</a>
                            <a class="btn btn-xs btn-info" href="alternanze.php?id_scenario=<?php echo intval($s['id']); ?>">Alternanze</a>
                            <a class="btn btn-xs btn-warning" href="classe_slot_vincoli.php?id_scenario=<?php echo intval($s['id']); ?>">Vincoli classi</a>
                            <a class="btn btn-xs btn-warning" href="vincoli_pomeriggi.php?id_scenario=<?= intval($s['id']) ?>">Pomeriggi</a>
                            <a class="btn btn-xs btn-primary" href="classi_articolate.php?id_scenario=<?= intval($s['id']) ?>">Classi articolate</a>
                            <a class="btn btn-xs btn-success" href="calendario.php?id_scenario=<?= intval($s['id']) ?>">Calendario</a>
                            <a class="btn btn-xs btn-info" href="docenti_materie.php?id_scenario=<?= intval($s['id']) ?>">Docenti/materie</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</body>

</html>