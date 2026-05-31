<?php
require_once __DIR__ . '/orario_builder_lib.php';

$aule = ob_get_aule();
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Aule</title>
    <style>
        body { font-family:Arial; margin:24px; background:#f5f6f8; }
        .box { background:white; padding:20px; border-radius:12px; margin-bottom:20px; box-shadow:0 2px 8px #0001; }
        input,select { padding:8px; width:100%; margin-bottom:12px; }
        button,a.btn { padding:10px 14px; background:#2563eb; color:white; border:0; border-radius:8px; text-decoration:none; }
        table { width:100%; border-collapse:collapse; }
        th,td { padding:8px; border-bottom:1px solid #ddd; }
        th { background:#eef2ff; }
    </style>
</head>
<body>

<div class="box">
    <h1>Aule</h1>
    <a class="btn" href="index.php">Indietro</a>

    <form method="post" action="aule_import_mbapp.php" style="display:inline">
        <button type="submit">Importa / allinea da MBApp</button>
    </form>
</div>

<div class="box">
    <h2>Nuova aula manuale</h2>

    <form method="post" action="aula_save.php">
        <label>Codice</label>
        <input name="codice" required>

        <label>Nome</label>
        <input name="nome">

        <label>Piano</label>
        <select name="piano">
            <option value="S">S</option>
            <option value="R">R</option>
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
        </select>

        <label>Ala</label>
        <select name="ala">
            <option value="">Non specificata</option>
            <option value="NORD">Nord</option>
            <option value="EST">Est</option>
            <option value="SUD">Sud</option>
            <option value="OVEST">Ovest</option>
        </select>

        <label>Capienza</label>
        <input type="number" name="capienza">

        <label>Tipo</label>
        <select name="tipo">
            <option value="AULA">Aula</option>
            <option value="LABORATORIO">Laboratorio</option>
            <option value="PALESTRA">Palestra</option>
            <option value="AULA_SPECIALE">Aula speciale</option>
            <option value="ALTRO">Altro</option>
        </select>

        <button type="submit">Salva aula</button>
    </form>
</div>

<div class="box">
    <h2>Aule presenti</h2>

    <table>
        <thead>
        <tr>
            <th>Codice</th>
            <th>Nome</th>
            <th>Piano</th>
            <th>Ala</th>
            <th>Capienza</th>
            <th>Tipo</th>
            <th>Attiva</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($aule as $a): ?>
            <tr>
                <td><?= ob_h($a['codice']) ?></td>
                <td><?= ob_h($a['nome']) ?></td>
                <td><?= ob_h($a['piano']) ?></td>
                <td><?= ob_h($a['ala']) ?></td>
                <td><?= ob_h($a['capienza']) ?></td>
                <td><?= ob_h($a['tipo']) ?></td>
                <td><?= intval($a['attiva']) ? 'Sì' : 'No' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>