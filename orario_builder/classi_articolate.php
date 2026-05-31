<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

$idScenario = ob_int($_GET['id_scenario'] ?? 0);
$scenario = ob_get_scenario($idScenario);

if (!$scenario) {
    die('Scenario non trovato');
}

$classi = dbGetAll("
    SELECT id, classe, anno, attiva
    FROM classi
    ORDER BY classe
") ?: [];
$materie = ob_get_materie();

$gruppi = dbGetAll("
    SELECT *
    FROM orario_classe_articolata_gruppo
    WHERE id_scenario = $idScenario
      AND attivo = 1
    ORDER BY nome
") ?: [];
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <title>Classi articolate</title>
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
                <span class="glyphicon glyphicon-random"></span>&ensp;
                Classi articolate - <?php echo ob_h($scenario['nome']); ?>
            </div>

            <div class="panel-body">

                <p>
                    <a class="btn btn-default" href="scenari.php">
                        <span class="glyphicon glyphicon-arrow-left"></span>&ensp;Indietro
                    </a>
                </p>

                <div class="alert alert-info">
                    Configura le classi articolate: classi coinvolte, materie comuni e gruppi di materie di indirizzo da sincronizzare nello stesso slot.
                </div>

                <h4>Nuovo gruppo articolato</h4>

                <form method="post" action="classi_articolate_save.php">
                    <input type="hidden" name="azione" value="crea_gruppo">
                    <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">

                    <div class="row">
                        <div class="col-md-4">
                            <label>Nome</label>
                            <input type="text" name="nome" class="form-control" required placeholder="Esempio: 3A/3B articolata">
                        </div>

                        <div class="col-md-8">
                            <label>Descrizione</label>
                            <input type="text" name="descrizione" class="form-control">
                        </div>
                    </div>

                    <br>

                    <button class="btn btn-primary">
                        <span class="glyphicon glyphicon-plus"></span>&ensp;Crea gruppo
                    </button>
                </form>

                <hr>

                <?php foreach ($gruppi as $g): ?>
                    <?php
                    $idGruppo = intval($g['id']);

                    $classiGruppo = dbGetAll("
        SELECT ac.id, c.id AS id_classe, c.classe
        FROM orario_classe_articolata_classe ac
        JOIN classi c ON c.id = ac.id_classe
        WHERE ac.id_gruppo = $idGruppo
        ORDER BY c.classe
    ") ?: [];

                    $materieComuni = dbGetAll("
        SELECT am.id, m.id AS id_materia, m.nome AS materia, m.codice
        FROM orario_classe_articolata_materia am
        JOIN materia m ON m.id = am.id_materia
        WHERE am.id_gruppo = $idGruppo
          AND am.tipo = 'COMUNE'
        ORDER BY m.nome
    ") ?: [];

                    $gruppiMaterie = dbGetAll("
        SELECT gm.*, c.classe
        FROM orario_classe_articolata_gruppo_materie gm
        JOIN classi c ON c.id = gm.id_classe
        WHERE gm.id_gruppo_articolato = $idGruppo
          AND gm.attivo = 1
        ORDER BY c.classe, gm.nome
    ") ?: [];

                    $sync = dbGetAll("
        SELECT
            s.*,
            ga.nome AS nome_gruppo_a,
            gb.nome AS nome_gruppo_b
        FROM orario_classe_articolata_sincronizzazione s
        JOIN orario_classe_articolata_gruppo_materie ga ON ga.id = s.id_gruppo_materie_a
        JOIN orario_classe_articolata_gruppo_materie gb ON gb.id = s.id_gruppo_materie_b
        WHERE s.id_gruppo_articolato = $idGruppo
          AND s.attivo = 1
        ORDER BY s.nome
    ") ?: [];
                    ?>

                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <strong><?php echo ob_h($g['nome']); ?></strong>

                            <form method="post" action="classi_articolate_save.php" style="float:right;"
                                onsubmit="return confirm('Eliminare questo gruppo articolato?');">
                                <input type="hidden" name="azione" value="elimina_gruppo">
                                <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">
                                <input type="hidden" name="id_gruppo" value="<?php echo $idGruppo; ?>">
                                <button class="btn btn-xs btn-danger">
                                    <span class="glyphicon glyphicon-trash"></span> Elimina gruppo
                                </button>
                            </form>
                        </div>

                        <div class="panel-body">

                            <form method="post" action="classi_articolate_save.php">
                                <input type="hidden" name="azione" value="salva_gruppo">
                                <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">
                                <input type="hidden" name="id_gruppo" value="<?php echo $idGruppo; ?>">

                                <div class="row">
                                    <div class="col-md-4">
                                        <label>Nome</label>
                                        <input type="text" name="nome" class="form-control" value="<?php echo ob_h($g['nome']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Descrizione</label>
                                        <input type="text" name="descrizione" class="form-control" value="<?php echo ob_h($g['descrizione']); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label>&nbsp;</label>
                                        <button class="btn btn-success btn-block">
                                            <span class="glyphicon glyphicon-floppy-disk"></span> Salva
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <hr>

                            <h4>Classi del gruppo</h4>

                            <form method="post" action="classi_articolate_save.php">
                                <input type="hidden" name="azione" value="aggiungi_classe">
                                <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">
                                <input type="hidden" name="id_gruppo" value="<?php echo $idGruppo; ?>">

                                <div class="row">
                                    <div class="col-md-10">
                                        <select name="id_classe" class="form-control selectpicker" data-live-search="true" required>
                                            <?php foreach ($classi as $c): ?>
                                                <option value="<?php echo intval($c['id']); ?>">
                                                    <?php echo ob_h($c['classe']); ?>
                                                    <?php echo intval($c['attiva']) === 1 ? '' : ' (non attiva)'; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button class="btn btn-primary btn-block">
                                            <span class="glyphicon glyphicon-plus"></span> Aggiungi classe
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <br>

                            <?php foreach ($classiGruppo as $cg): ?>
                                <form method="post" action="classi_articolate_save.php" style="display:inline-block; margin:3px;">
                                    <input type="hidden" name="azione" value="rimuovi_classe">
                                    <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">
                                    <input type="hidden" name="id_riga" value="<?php echo intval($cg['id']); ?>">
                                    <button class="btn btn-default btn-sm">
                                        <?php echo ob_h($cg['classe']); ?>
                                        <span class="glyphicon glyphicon-remove text-danger"></span>
                                    </button>
                                </form>
                            <?php endforeach; ?>

                            <br>

                            <form method="post" action="classi_articolate_autocompila.php"
                                onsubmit="return confirm('Autocompilare materie comuni e gruppi indirizzo dai piani orario associati alle classi?');">
                                <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">
                                <input type="hidden" name="id_gruppo" value="<?php echo intval($idGruppo); ?>">

                                <button class="btn btn-warning">
                                    <span class="glyphicon glyphicon-flash"></span>
                                    Autocompila materie da piani orario
                                </button>
                            </form>

                            <hr>

                            <h4>Materie comuni</h4>

                            <form method="post" action="classi_articolate_save.php">
                                <input type="hidden" name="azione" value="aggiungi_materia_comune">
                                <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">
                                <input type="hidden" name="id_gruppo" value="<?php echo $idGruppo; ?>">

                                <div class="row">
                                    <div class="col-md-10">
                                        <select name="id_materia" class="form-control selectpicker" data-live-search="true" required>
                                            <?php foreach ($materie as $m): ?>
                                                <option value="<?php echo intval($m['id']); ?>">
                                                    <?php echo ob_h($m['nome']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button class="btn btn-primary btn-block">
                                            <span class="glyphicon glyphicon-plus"></span> Aggiungi comune
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <br>

                            <?php foreach ($materieComuni as $mc): ?>
                                <form method="post" action="classi_articolate_save.php" style="display:inline-block; margin:3px;">
                                    <input type="hidden" name="azione" value="rimuovi_materia_comune">
                                    <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">
                                    <input type="hidden" name="id_riga" value="<?php echo intval($mc['id']); ?>">
                                    <button class="btn btn-info btn-sm">
                                        <?php echo ob_h($mc['materia']); ?>
                                        <span class="glyphicon glyphicon-remove text-danger"></span>
                                    </button>
                                </form>
                            <?php endforeach; ?>

                            <hr>

                            <h4>Gruppi materie di indirizzo</h4>

                            <form method="post" action="classi_articolate_save.php">
                                <input type="hidden" name="azione" value="crea_gruppo_materie">
                                <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">
                                <input type="hidden" name="id_gruppo" value="<?php echo $idGruppo; ?>">

                                <div class="row">
                                    <div class="col-md-3">
                                        <label>Classe</label>
                                        <select name="id_classe" class="form-control selectpicker" data-live-search="true" required>
                                            <?php foreach ($classiGruppo as $cg): ?>
                                                <option value="<?php echo intval($cg['id_classe']); ?>">
                                                    <?php echo ob_h($cg['classe']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-7">
                                        <label>Nome gruppo materie</label>
                                        <input type="text" name="nome" class="form-control" required
                                            placeholder="Esempio: Indirizzo Informatica">
                                    </div>

                                    <div class="col-md-2">
                                        <label>&nbsp;</label>
                                        <button class="btn btn-primary btn-block">
                                            <span class="glyphicon glyphicon-plus"></span> Crea gruppo
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <br>

                            <?php foreach ($gruppiMaterie as $gm): ?>
                                <?php
                                $idGm = intval($gm['id']);
                                $materieGm = dbGetAll("
                    SELECT r.id, m.nome AS materia
                    FROM orario_classe_articolata_gruppo_materie_riga r
                    JOIN materia m ON m.id = r.id_materia
                    WHERE r.id_gruppo_materie = $idGm
                    ORDER BY m.nome
                ") ?: [];
                                ?>

                                <div class="well">
                                    <form method="post" action="classi_articolate_save.php" style="float:right;"
                                        onsubmit="return confirm('Eliminare questo gruppo materie?');">
                                        <input type="hidden" name="azione" value="elimina_gruppo_materie">
                                        <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">
                                        <input type="hidden" name="id_gruppo_materie" value="<?php echo $idGm; ?>">
                                        <button class="btn btn-xs btn-danger">
                                            <span class="glyphicon glyphicon-trash"></span>
                                        </button>
                                    </form>

                                    <strong><?php echo ob_h($gm['classe']); ?> — <?php echo ob_h($gm['nome']); ?></strong>

                                    <form method="post" action="classi_articolate_save.php" style="margin-top:10px;">
                                        <input type="hidden" name="azione" value="aggiungi_materia_gruppo">
                                        <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">
                                        <input type="hidden" name="id_gruppo_materie" value="<?php echo $idGm; ?>">

                                        <div class="row">
                                            <div class="col-md-10">
                                                <select name="id_materia" class="form-control selectpicker" data-live-search="true" required>
                                                    <?php foreach ($materie as $m): ?>
                                                        <option value="<?php echo intval($m['id']); ?>">
                                                            <?php echo ob_h($m['nome']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <button class="btn btn-primary btn-block btn-sm">
                                                    <span class="glyphicon glyphicon-plus"></span> Materia
                                                </button>
                                            </div>
                                        </div>
                                    </form>

                                    <br>

                                    <?php foreach ($materieGm as $mgm): ?>
                                        <form method="post" action="classi_articolate_save.php" style="display:inline-block; margin:3px;">
                                            <input type="hidden" name="azione" value="rimuovi_materia_gruppo">
                                            <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">
                                            <input type="hidden" name="id_riga" value="<?php echo intval($mgm['id']); ?>">
                                            <button class="btn btn-warning btn-sm">
                                                <?php echo ob_h($mgm['materia']); ?>
                                                <span class="glyphicon glyphicon-remove text-danger"></span>
                                            </button>
                                        </form>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>

                            <hr>

                            <div class="alert alert-info">
                                Le sincronizzazioni tra i gruppi di indirizzo vengono create automaticamente con il pulsante
                                <strong>Autocompila materie da piani orario</strong>.
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($gruppi)): ?>
                    <div class="alert alert-warning">Nessun gruppo articolato configurato.</div>
                <?php endif; ?>

            </div>
        </div>

        <script>
            if (typeof jQuery !== 'undefined' && typeof jQuery.fn.selectpicker === 'function') {
                jQuery('.selectpicker').selectpicker();
            }
        </script>

</body>

</html>