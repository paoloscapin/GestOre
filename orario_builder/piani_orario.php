<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

$pageTitle = 'Piani orario';

$anni = ob_get_anni_scolastici();

$piani = dbGetAll("
    SELECT
        p.*,
            COUNT(DISTINCT COALESCE(a.id_classe, a.alias_classe)) AS numero_classi,
            GROUP_CONCAT(
                DISTINCT COALESCE(c.classe, a.alias_classe)
                ORDER BY COALESCE(c.classe, a.alias_classe)
                SEPARATOR ', '
            ) AS classi_associate
     FROM orario_piano_orario p
    LEFT JOIN orario_piano_orario_classe_alias a
        ON a.id_piano_orario = p.id
    LEFT JOIN classi c
    ON c.id = a.id_classe
    WHERE p.attivo = 1
    GROUP BY p.id
    ORDER BY p.nome
") ?: [];
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <title>Piani orario</title>
    <?php
    require_once __DIR__ . '/../common/header-common.php';
    require_once __DIR__ . '/../common/style.php';
    require_once __DIR__ . '/../common/_include_bootstrap-notify.php';
    require_once __DIR__ . '/../common/_include_bootstrap-select.php';
    ?>
</head>

<body>
    <?php require_once __DIR__ . '/../common/header-admin.php'; ?>


    <div class="container-fluid">

        <div class="row">
            <div class="col-md-12">
                <h2>
                    <span class="glyphicon glyphicon-list-alt"></span>
                    Piani / quadri orario
                </h2>

                <p>
                    <a class="btn btn-default" href="index.php">
                        <span class="glyphicon glyphicon-arrow-left"></span>
                        Indietro
                    </a>
                </p>
            </div>
        </div>

        <div class="panel panel-primary">
            <div class="panel-heading">
                Nuovo piano orario
            </div>

            <div class="panel-body">
                <form method="post" action="piano_save.php">

                    <div class="form-group">
                        <label>Nome</label>
                        <input type="text"
                            name="nome"
                            class="form-control"
                            required
                            placeholder="Esempio: Biennio informatica - prima">
                    </div>

                    <div class="form-group">
                        <label>Anno scolastico</label>
                        <select name="id_anno_scolastico" class="form-control" required>
                            <?php foreach ($anni as $a): ?>
                                <option value="<?php echo intval($a['id']); ?>">
                                    <?php echo ob_h(ob_anno_label($a)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Anno classe</label>
                        <select name="anno_classe" class="form-control">
                            <option value="">Non specificato</option>
                            <option value="1">Prima</option>
                            <option value="2">Seconda</option>
                            <option value="3">Terza</option>
                            <option value="4">Quarta</option>
                            <option value="5">Quinta</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Descrizione</label>
                        <textarea name="descrizione" class="form-control" rows="3"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <span class="glyphicon glyphicon-floppy-disk"></span>
                        Crea piano
                    </button>

                </form>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading">
                Piani esistenti
            </div>

            <table class="table table-striped table-bordered table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Anno scolastico</th>
                        <th>Anno classe</th>
                        <th>Numero classi</th>
                        <th>Classi associate</th>
                        <th>Azioni</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($piani as $p): ?>
                        <tr>
                            <td><?php echo intval($p['id']); ?></td>
                            <td><?php echo ob_h($p['nome']); ?></td>
                            <td><?php echo intval($p['id_anno_scolastico']); ?></td>
                            <td><?php echo ob_h($p['anno_classe']); ?></td>
                            <td><?php echo intval($p['numero_classi']); ?></td>
                            <td>
                                <?php echo !empty($p['classi_associate'])
                                    ? ob_h($p['classi_associate'])
                                    : '<span class="text-muted">Nessuna</span>'; ?>
                            </td>
                            <td>
                                <a class="btn btn-xs btn-info"
                                    href="piano_materie.php?id_piano=<?php echo intval($p['id']); ?>">
                                    Materie/ore
                                </a>

                                <a class="btn btn-xs btn-warning"
                                    href="classe_piano.php?id_piano=<?php echo intval($p['id']); ?>">
                                    Associa classi
                                </a>

                                <a class="btn btn-xs btn-success"
                                    href="piano_edit.php?id_piano=<?php echo intval($p['id']); ?>">
                                    <span class="glyphicon glyphicon-pencil"></span>
                                    Modifica
                                </a>

                                <form method="post"
                                    action="piano_delete.php"
                                    style="display:inline;"
                                    onsubmit="return confirm('Eliminare questo piano orario? Le associazioni e le materie collegate verranno eliminate o disattivate.');">
                                    <input type="hidden" name="id_piano" value="<?php echo intval($p['id']); ?>">
                                    <button type="submit" class="btn btn-xs btn-danger">
                                        <span class="glyphicon glyphicon-trash"></span>
                                        Elimina
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($piani)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                Nessun piano orario configurato.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

</body>

</html>