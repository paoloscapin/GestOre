<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

$idPianoMateria = ob_int($_GET['id_piano_materia'] ?? 0);

$pm = dbGetFirst("
    SELECT
        pm.*,
        p.nome AS piano_nome,
        m.nome AS materia_nome
    FROM orario_piano_orario_materia pm
    JOIN orario_piano_orario p ON p.id = pm.id_piano_orario
    JOIN materia m ON m.id = pm.id_materia
    WHERE pm.id = $idPianoMateria
    LIMIT 1
");

if (!$pm) {
    die('Materia del piano non trovata');
}

$aule = ob_get_aule();

$gruppi = dbGetAll("
    SELECT *
    FROM orario_aula_gruppo
    WHERE attivo = 1
    ORDER BY ordine, nome
") ?: [];

$richieste = dbGetAll("
    SELECT
        r.*,
        a.codice AS aula_codice,
        a.nome AS aula_nome,
        g.nome AS gruppo_nome
    FROM orario_piano_materia_aula_richiesta r
    LEFT JOIN aule a ON a.id = r.id_aula
    LEFT JOIN orario_aula_gruppo g ON g.id = r.id_gruppo_aula
    WHERE r.id_piano_orario_materia = $idPianoMateria
    ORDER BY r.tipo_ora, r.progressivo
") ?: [];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Vincoli aule materia</title>
    <?php
    require_once __DIR__ . '/../common/header-common.php';
    require_once __DIR__ . '/../common/style.php';
    require_once __DIR__ . '/../common/_include_bootstrap-select.php';
    ?>
</head>
<body>
<?php require_once __DIR__ . '/../common/header-admin.php'; ?>

<div class="container-fluid">

    <div class="panel panel-teal4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-blackboard"></span>&ensp;
            Aule/Vincoli - <?php echo ob_h($pm['materia_nome']); ?>
        </div>

        <div class="panel-body">

            <p>
                <a class="btn btn-default" href="piano_materie.php?id_piano=<?php echo intval($pm['id_piano_orario']); ?>">
                    <span class="glyphicon glyphicon-arrow-left"></span>&ensp;Indietro
                </a>

                <a class="btn btn-info" href="aula_gruppi.php">
                    <span class="glyphicon glyphicon-blackboard"></span>&ensp;Gestisci gruppi aule
                </a>
            </p>

            <div class="alert alert-info">
                Piano: <strong><?php echo ob_h($pm['piano_nome']); ?></strong>.
                Per ogni tipo ora puoi aggiungere una o più richieste aula.
            </div>

            <h4>Nuova richiesta aula</h4>

            <form method="post" action="materia_aule_save.php">
                <input type="hidden" name="azione" value="salva">
                <input type="hidden" name="id_piano_materia" value="<?php echo $idPianoMateria; ?>">

                <div class="row">
                    <div class="col-md-2">
                        <label>Tipo ora</label>
                        <select name="tipo_ora" class="form-control">
                            <option value="TEORIA">Teoria</option>
                            <option value="LABORATORIO">Laboratorio</option>
                        </select>
                    </div>

                    <div class="col-md-1">
                        <label>N.</label>
                        <input type="number" name="progressivo" class="form-control" value="1" min="1">
                    </div>

                    <div class="col-md-2">
                        <label>Modalità</label>
                        <select name="modalita" class="form-control">
                            <option value="AULA_FISSA">Aula fissa</option>
                            <option value="GRUPPO_AULE">Gruppo aule</option>
                            <option value="NESSUNA">Nessuna</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>Aula fissa</label>
                        <select name="id_aula" class="form-control selectpicker" data-live-search="true">
                            <option value="">Nessuna</option>
                            <?php foreach ($aule as $a): ?>
                                <option value="<?php echo intval($a['id']); ?>">
                                    <?php echo ob_h($a['codice'] . ' - ' . $a['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>Gruppo aule</label>
                        <select name="id_gruppo_aula" class="form-control selectpicker" data-live-search="true">
                            <option value="">Nessuno</option>
                            <?php foreach ($gruppi as $g): ?>
                                <option value="<?php echo intval($g['id']); ?>">
                                    <?php echo ob_h($g['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-1">
                        <label>Obbl.</label>
                        <select name="obbligatoria" class="form-control">
                            <option value="1">Sì</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                </div>

                <br>

                <button class="btn btn-primary">
                    <span class="glyphicon glyphicon-plus"></span>&ensp;Aggiungi richiesta
                </button>
            </form>

            <hr>

            <h4>Richieste configurate</h4>

            <table class="table table-striped table-bordered">
                <thead>
                <tr>
                    <th>Tipo ora</th>
                    <th>N.</th>
                    <th>Modalità</th>
                    <th>Aula</th>
                    <th>Gruppo</th>
                    <th>Obbligatoria</th>
                    <th style="width:170px;">Azioni</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($richieste as $r): ?>
                    <tr>
                        <form method="post" action="materia_aule_save.php">
                            <input type="hidden" name="azione" value="salva">
                            <input type="hidden" name="id_piano_materia" value="<?php echo $idPianoMateria; ?>">
                            <input type="hidden" name="id_richiesta" value="<?php echo intval($r['id']); ?>">

                            <td>
                                <select name="tipo_ora" class="form-control input-sm">
                                    <option value="TEORIA" <?php echo $r['tipo_ora'] === 'TEORIA' ? 'selected' : ''; ?>>Teoria</option>
                                    <option value="LABORATORIO" <?php echo $r['tipo_ora'] === 'LABORATORIO' ? 'selected' : ''; ?>>Laboratorio</option>
                                </select>
                            </td>

                            <td>
                                <input type="number" name="progressivo" class="form-control input-sm" value="<?php echo intval($r['progressivo']); ?>">
                            </td>

                            <td>
                                <select name="modalita" class="form-control input-sm">
                                    <option value="AULA_FISSA" <?php echo $r['modalita'] === 'AULA_FISSA' ? 'selected' : ''; ?>>Aula fissa</option>
                                    <option value="GRUPPO_AULE" <?php echo $r['modalita'] === 'GRUPPO_AULE' ? 'selected' : ''; ?>>Gruppo aule</option>
                                    <option value="NESSUNA" <?php echo $r['modalita'] === 'NESSUNA' ? 'selected' : ''; ?>>Nessuna</option>
                                </select>
                            </td>

                            <td>
                                <select name="id_aula" class="form-control input-sm">
                                    <option value="">Nessuna</option>
                                    <?php foreach ($aule as $a): ?>
                                        <option value="<?php echo intval($a['id']); ?>"
                                            <?php echo intval($r['id_aula']) === intval($a['id']) ? 'selected' : ''; ?>>
                                            <?php echo ob_h($a['codice']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>

                            <td>
                                <select name="id_gruppo_aula" class="form-control input-sm">
                                    <option value="">Nessuno</option>
                                    <?php foreach ($gruppi as $g): ?>
                                        <option value="<?php echo intval($g['id']); ?>"
                                            <?php echo intval($r['id_gruppo_aula']) === intval($g['id']) ? 'selected' : ''; ?>>
                                            <?php echo ob_h($g['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>

                            <td>
                                <select name="obbligatoria" class="form-control input-sm">
                                    <option value="1" <?php echo intval($r['obbligatoria']) === 1 ? 'selected' : ''; ?>>Sì</option>
                                    <option value="0" <?php echo intval($r['obbligatoria']) === 0 ? 'selected' : ''; ?>>No</option>
                                </select>
                            </td>

                            <td>
                                <button class="btn btn-xs btn-success">
                                    <span class="glyphicon glyphicon-floppy-disk"></span>
                                    Salva
                                </button>

                                <button class="btn btn-xs btn-danger"
                                        name="azione"
                                        value="elimina"
                                        onclick="return confirm('Eliminare richiesta aula?');">
                                    <span class="glyphicon glyphicon-trash"></span>
                                    Elimina
                                </button>
                            </td>
                        </form>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($richieste)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">Nessuna richiesta configurata.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>

        </div>
    </div>

</div>

<script>
if (typeof jQuery !== 'undefined' && typeof jQuery.fn.selectpicker === 'function') {
    jQuery('.selectpicker').selectpicker();
}
</script>

</body>
</html>