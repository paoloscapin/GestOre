<?php
/**
 *  Elenco corsi esami non completati (voti mancanti)
 *  Autore: Massimo Saiani
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('segreteria-didattica', 'dirigente');

$query = "
    SELECT DISTINCT
    c.id              AS id_corso,
    m.nome            AS materia,
    d.nome            AS docente_nome,
    d.cognome         AS docente_cognome,
    m.nome           AS nome_materia,
    ced.data_esame    AS data_esame
FROM corso_esiti ce
JOIN corso c               ON c.id = ce.id_corso
JOIN materia m             ON m.id = c.id_materia
JOIN docente d             ON d.id = c.id_docente
JOIN corso_esami_date ced  ON ced.id = ce.id_esame_data
WHERE ce.presente = 1
  AND ce.voto IS NULL
ORDER BY d.cognome, d.nome, m.nome, ced.data_esame;
";

$corsi = dbGetAll($query);

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Corsi esami incompleti</title>
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    ?>
</head>
<body style="padding:20px;">

    <h2>Corsi d’esame non completati</h2>

    <?php if (count($corsi) === 0): ?>
        <div class="alert alert-success">
            ✅ Tutti i corsi hanno i voti inseriti.
        </div>
    <?php else: ?>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Materia</th>
                    <th>Data esame</th>
                    <th>Docente corso</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($corsi as $corso): ?>
                    <tr>
                        <td><?= htmlspecialchars($corso['nome_materia']) ?></td>
                        <td><?= date("d/m/Y H:i", strtotime($corso['data_esame'])) ?></td>
                        <td><?= htmlspecialchars($corso['docente_cognome'] . " " . $corso['docente_nome']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</body>
</html>
