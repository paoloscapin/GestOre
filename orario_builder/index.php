<?php
require_once __DIR__ . '/orario_builder_lib.php';

$scenari = ob_get_scenari();
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>GestOre - Creazione orario</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; background:#f5f6f8; }
        .box { background:white; border-radius:12px; padding:20px; margin-bottom:20px; box-shadow:0 2px 8px #0001; }
        .grid { display:grid; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); gap:16px; }
        a.btn, button { display:inline-block; padding:10px 14px; border-radius:8px; background:#2563eb; color:white; text-decoration:none; border:0; cursor:pointer; }
        a.btn.secondary { background:#64748b; }
        table { width:100%; border-collapse:collapse; background:white; }
        th,td { padding:10px; border-bottom:1px solid #ddd; text-align:left; }
        th { background:#eef2ff; }
        .badge { padding:4px 8px; border-radius:20px; background:#e2e8f0; font-size:12px; }
    </style>
</head>
<body>

<div class="box">
    <h1>Creazione orario scolastico</h1>
    <p>Gestione scenari, piani orario, fabbisogni, aule e vincoli.</p>

    <div class="grid">
        <a class="btn" href="scenari.php">Scenari</a>
        <a class="btn" href="piani_orario.php">Piani orario</a>
        <a class="btn" href="aule.php">Aule</a>
        <a class="btn btn-info btn-block" href="alternanze.php">
    <span class="glyphicon glyphicon-transfer"></span>
    Alternanze materie
</a>
        <a class="btn secondary" href="../index.php">Torna a GestOre</a>
    </div>
</div>

<div class="box">
    <h2>Scenari recenti</h2>

    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Anno scolastico</th>
            <th>Stato</th>
            <th>Azioni</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($scenari as $s): ?>
            <tr>
                <td><?= ob_h($s['id']) ?></td>
                <td><?= ob_h($s['nome']) ?></td>
                <td><?= ob_h($s['id_anno_scolastico']) ?></td>
                <td><span class="badge"><?= ob_h($s['stato']) ?></span></td>
                <td>
                    <a href="fabbisogno.php?id_scenario=<?= intval($s['id']) ?>">Fabbisogno</a> |
                    <a href="slot.php?id_scenario=<?= intval($s['id']) ?>">Slot</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>