<?php
require_once __DIR__ . '/orario_builder_lib.php';

$idScenario = ob_int($_GET['id_scenario'] ?? 0);
$scenario = ob_get_scenario($idScenario);

if (!$scenario) {
    die('Scenario non trovato');
}

$slot = dbGetAll("
    SELECT *
    FROM orario_slot
    WHERE id_scenario = $idScenario
    ORDER BY giorno, ora_index
") ?: [];

$giorni = [
    1 => 'Lunedì',
    2 => 'Martedì',
    3 => 'Mercoledì',
    4 => 'Giovedì',
    5 => 'Venerdì',
    6 => 'Sabato'
];
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Slot orario</title>
    <style>
        body { font-family:Arial; margin:24px; background:#f5f6f8; }
        .box { background:white; padding:20px; border-radius:12px; margin-bottom:20px; box-shadow:0 2px 8px #0001; }
        input,select { padding:8px; }
        button,a.btn { padding:10px 14px; border:0; border-radius:8px; background:#2563eb; color:white; text-decoration:none; }
        table { width:100%; border-collapse:collapse; }
        th,td { padding:8px; border-bottom:1px solid #ddd; }
        th { background:#eef2ff; }
    </style>
</head>
<body>

<div class="box">
    <h1>Slot orario - <?= ob_h($scenario['nome']) ?></h1>
    <a class="btn" href="index.php">Indietro</a>
</div>

<div class="box">
    <h2>Genera slot standard</h2>

    <form method="post" action="slot_generate.php">
        <input type="hidden" name="id_scenario" value="<?= $idScenario ?>">

        <label>Giorni</label>
        <select name="giorni">
            <option value="5">Lunedì - Venerdì</option>
            <option value="6" selected>Lunedì - Sabato</option>
        </select>

        <label>Numero ore al giorno</label>
        <input type="number" name="ore_giorno" value="6" min="1" max="10">

        <button type="submit">Genera slot</button>
    </form>
</div>

<div class="box">
    <h2>Slot configurati</h2>

    <table>
        <thead>
        <tr>
            <th>Giorno</th>
            <th>Ora</th>
            <th>Inizio</th>
            <th>Fine</th>
            <th>Attivo</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($slot as $s): ?>
            <tr>
                <td><?= ob_h($giorni[intval($s['giorno'])] ?? $s['giorno']) ?></td>
                <td><?= intval($s['ora_index']) ?></td>
                <td><?= ob_h(substr($s['ora_inizio'], 0, 5)) ?></td>
                <td><?= ob_h(substr($s['ora_fine'], 0, 5)) ?></td>
                <td><?= intval($s['attivo']) ? 'Sì' : 'No' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>