<?php
require_once __DIR__ . '/orario_builder_lib.php';

$idScenario = ob_int($_GET['id_scenario'] ?? 0);
$scenario = ob_get_scenario($idScenario);

if (!$scenario) {
    die('Scenario non trovato');
}

$righe = dbGetAll("
    SELECT
        f.*,
        c.classe,
        m.nome AS materia,
        d.cognome,
        d.nome,
        a.codice AS aula
    FROM orario_fabbisogno_classe f
    JOIN classi c ON c.id = f.id_classe
    JOIN materia m ON m.id = f.id_materia
    JOIN docente d ON d.id = f.id_docente
    LEFT JOIN aule a ON a.id = f.id_aula_preferita
    WHERE f.id_scenario = $idScenario
    ORDER BY c.classe, m.nome, d.cognome, d.nome
") ?: [];
?>
<!doctype html>
<html lang="it">

<head>
    <meta charset="utf-8">
    <title>Fabbisogno</title>
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

        a.btn.gray {
            background: #64748b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th,
        td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #eef2ff;
            position: sticky;
            top: 0;
        }
    </style>
</head>

<body>

    <div class="box">
        <h1>Fabbisogno - <?= ob_h($scenario['nome']) ?></h1>
        <a class="btn btn-info"
            href="alternanze.php?id_scenario=<?php echo intval($idScenario); ?>">
            <span class="glyphicon glyphicon-transfer"></span>
            Alternanze materie
        </a>
        <a class="btn gray" href="index.php">Indietro</a>

        <form method="post" action="fabbisogno_generate.php" style="display:inline">
            <input type="hidden" name="id_scenario" value="<?= $idScenario ?>">
            <button type="submit">Genera da docente_insegna + piani orario</button>
        </form>
    </div>

    <div class="box">
        <h2>Righe fabbisogno</h2>

        <table>
            <thead>
                <tr>
                    <th>Classe</th>
                    <th>Materia</th>
                    <th>Docente</th>
                    <th>Ore</th>
                    <th>Lab</th>
                    <th>Compresenza</th>
                    <th>Blocco</th>
                    <th>Aula preferita</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($righe as $r): ?>
                    <tr>
                        <td><?= ob_h($r['classe']) ?></td>
                        <td><?= ob_h($r['materia']) ?></td>
                        <td><?= ob_h($r['cognome'] . ' ' . $r['nome']) ?></td>
                        <td><?= ob_h($r['ore_settimanali']) ?></td>
                        <td><?= ob_h($r['ore_laboratorio']) ?></td>
                        <td><?= ob_h($r['ore_compresenza']) ?></td>
                        <td><?= ob_h($r['ore_blocco_preferito']) ?></td>
                        <td><?= ob_h($r['aula']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</body>

</html>