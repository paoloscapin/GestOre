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

$anni = ob_get_anni_scolastici();
$classiTutte = ob_get_classi_tutte();
$materie = ob_get_materie();
$docenti = ob_get_docenti();

$righe = dbGetAll("
    SELECT
        odi.*,
        c.classe,
        m.nome AS materia,
        m.codice AS codice_materia,
        d.cognome,
        d.nome
    FROM orario_docente_insegna_scenario odi
    JOIN classi c ON c.id = odi.id_classe
    JOIN materia m ON m.id = odi.id_materia
    LEFT JOIN docente d ON d.id = odi.id_docente
    WHERE odi.id_scenario = $idScenario
    ORDER BY c.classe, m.nome, d.cognome, d.nome, odi.docente_temporaneo
") ?: [];

$temporanei = dbGetAll("
    SELECT DISTINCT docente_temporaneo, docente_key
    FROM orario_docente_insegna_scenario
    WHERE id_scenario = $idScenario
      AND docente_da_nominare = 1
      AND docente_temporaneo IS NOT NULL
      AND docente_temporaneo <> ''
    ORDER BY docente_temporaneo
") ?: [];

$aliases = dbGetAll("
    SELECT a.*, c.classe
    FROM orario_import_classe_alias a
    LEFT JOIN classi c ON c.id = a.id_classe
    ORDER BY 
        CASE WHEN a.id_classe IS NULL THEN 0 ELSE 1 END,
        a.alias_classe
") ?: [];
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <title>Docenti / materie scenario</title>
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
            <span class="glyphicon glyphicon-education"></span>&ensp;
            Docenti / materie - <?php echo ob_h($scenario['nome']); ?>
        </div>

        <div class="panel-body">

            <p>
                <a class="btn btn-default" href="scenari.php">
                    <span class="glyphicon glyphicon-arrow-left"></span>&ensp;Indietro
                </a>
            </p>

            <div class="alert alert-info">
                Questa tabella è la base dello scenario orario. Puoi importarla da <code>docente_insegna</code>
                oppure da CSV. I docenti non trovati vengono salvati come temporanei, ad esempio <code>CODCHIMICA1</code>.
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">Importa da anno scolastico esistente</div>
                        <div class="panel-body">
                            <form method="post" action="docenti_materie_import_anno.php">
                                <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">

                                <div class="form-group">
                                    <label>Anno origine</label>
                                    <select name="id_anno_origine" class="form-control selectpicker" data-live-search="true" required>
                                        <?php foreach ($anni as $a): ?>
                                            <option value="<?php echo intval($a['id']); ?>">
                                                <?php echo ob_h(ob_anno_label($a)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <button class="btn btn-primary"
                                        onclick="return confirm('Importare le assegnazioni dall’anno selezionato?');">
                                    <span class="glyphicon glyphicon-import"></span>&ensp;Importa da anno
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">Importa da CSV</div>
                        <div class="panel-body">
                            <form method="post" action="docenti_materie_import_csv.php" enctype="multipart/form-data">
                                <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">

                                <div class="form-group">
                                    <label>File CSV</label>
                                    <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv" required>
                                    <p class="help-block">
                                        Formato atteso: Classe;Codice materia;Nome materia;Ore;Cognome Docente;Nome Docente;
                                    </p>
                                </div>

                                <button class="btn btn-warning">
                                    <span class="glyphicon glyphicon-upload"></span>&ensp;Importa CSV
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <hr>

            <h4>Aggiungi assegnazione manuale</h4>

            <form method="post" action="docenti_materie_save.php">
                <input type="hidden" name="azione" value="salva">
                <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">

                <div class="row">
                    <div class="col-md-3">
                        <label>Classe</label>
                        <select name="id_classe" class="form-control selectpicker" data-live-search="true" required>
                            <?php foreach ($classiTutte as $c): ?>
                                <option value="<?php echo intval($c['id']); ?>">
                                    <?php echo ob_h($c['classe']); ?>
                                    <?php echo intval($c['attiva']) === 1 ? '' : ' (non attiva)'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>Materia</label>
                        <select name="id_materia" class="form-control selectpicker" data-live-search="true" required>
                            <?php foreach ($materie as $m): ?>
                                <option value="<?php echo intval($m['id']); ?>">
                                    <?php echo ob_h($m['nome']); ?>
                                    <?php echo !empty($m['codice']) ? ' (' . ob_h($m['codice']) . ')' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label>Docente reale</label>
                        <select name="id_docente" class="form-control selectpicker" data-live-search="true">
                            <option value="">Docente temporaneo / da nominare</option>
                            <?php foreach ($docenti as $d): ?>
                                <option value="<?php echo intval($d['id']); ?>">
                                    <?php echo ob_h($d['cognome'] . ' ' . $d['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label>Temporaneo</label>
                        <input type="text" name="docente_temporaneo" class="form-control" placeholder="CODCHIMICA1">
                    </div>
                </div>

                <br>

                <button class="btn btn-primary">
                    <span class="glyphicon glyphicon-plus"></span>&ensp;Aggiungi assegnazione
                </button>
            </form>

            <hr>

            <h4>Docenti temporanei da sostituire</h4>

            <?php if (!empty($temporanei)): ?>
                <form method="post" action="docenti_materie_save.php">
                    <input type="hidden" name="azione" value="sostituisci_temporanei_massivo">
                    <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">

                    <table class="table table-bordered table-striped">
                        <thead>
                        <tr>
                            <th>Docente temporaneo</th>
                            <th>Docente reale</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($temporanei as $t): ?>
                            <tr>
                                <td>
                                    <strong><?php echo ob_h($t['docente_temporaneo']); ?></strong>
                                    <input type="hidden"
                                           name="docente_key[]"
                                           value="<?php echo ob_h($t['docente_key']); ?>">
                                </td>

                                <td>
                                    <select name="id_docente[]" class="form-control selectpicker" data-live-search="true">
                                        <option value="">Non sostituire</option>
                                        <?php foreach ($docenti as $d): ?>
                                            <option value="<?php echo intval($d['id']); ?>">
                                                <?php echo ob_h($d['cognome'] . ' ' . $d['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <button class="btn btn-success">
                        <span class="glyphicon glyphicon-refresh"></span>
                        Sostituisci docenti selezionati
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-success">Nessun docente temporaneo presente.</div>
            <?php endif; ?>

            <hr>

            <h4>Assegnazioni scenario</h4>

            <table class="table table-striped table-bordered table-hover">
                <thead>
                <tr>
                    <th>Classe</th>
                    <th>Materia</th>
                    <th>Docente</th>
                    <th>Origine</th>
                    <th style="width:130px;">Azioni</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($righe as $r): ?>
                    <tr>
                        <td><?php echo ob_h($r['classe']); ?></td>

                        <td>
                            <?php echo ob_h($r['materia']); ?>
                            <?php echo !empty($r['codice_materia']) ? '<br><small>' . ob_h($r['codice_materia']) . '</small>' : ''; ?>
                        </td>

                        <td>
                            <?php if (intval($r['docente_da_nominare']) === 1): ?>
                                <span class="label label-warning">Da nominare</span>
                                <?php echo ob_h($r['docente_temporaneo']); ?>
                            <?php else: ?>
                                <?php echo ob_h(trim($r['cognome'] . ' ' . $r['nome'])); ?>
                            <?php endif; ?>
                            <br><small><?php echo ob_h($r['docente_key']); ?></small>
                        </td>

                        <td><?php echo ob_h($r['origine']); ?></td>

                        <td>
                            <form method="post" action="docenti_materie_save.php" onsubmit="return confirm('Eliminare assegnazione?');">
                                <input type="hidden" name="azione" value="elimina">
                                <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">
                                <input type="hidden" name="id" value="<?php echo intval($r['id']); ?>">
                                <button class="btn btn-xs btn-danger">
                                    <span class="glyphicon glyphicon-trash"></span> Elimina
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($righe)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted">Nessuna assegnazione presente.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>

            <hr>

            <h4>Alias classi per import CSV</h4>

            <form method="post" action="docenti_materie_save.php">
                <input type="hidden" name="azione" value="salva_alias_massivo">
                <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">

                <table class="table table-striped table-bordered">
                    <thead>
                    <tr>
                        <th>Alias</th>
                        <th>Classe GestOre</th>
                        <th>Note</th>
                        <th style="width:80px;">Elimina</th>
                    </tr>
                    </thead>
                    <tbody>

                    <?php foreach ($aliases as $a): ?>
                        <tr>
                            <td>
                                <strong><?php echo ob_h($a['alias_classe']); ?></strong>

                                <?php if (empty($a['id_classe'])): ?>
                                    <br><span class="label label-warning">Da abbinare</span>
                                <?php endif; ?>

                                <input type="hidden" name="alias_id[]" value="<?php echo intval($a['id']); ?>">
                                <input type="hidden" name="alias_classe[]" value="<?php echo ob_h($a['alias_classe']); ?>">
                            </td>

                            <td>
                                <select name="id_classe[]" class="form-control input-sm">
                                    <option value="">Seleziona classe...</option>

                                    <?php foreach ($classiTutte as $c): ?>
                                        <option value="<?php echo intval($c['id']); ?>"
                                            <?php echo intval($a['id_classe']) === intval($c['id']) ? 'selected' : ''; ?>>
                                            <?php echo ob_h($c['classe']); ?>
                                            <?php echo intval($c['attiva']) === 1 ? '' : ' (non attiva)'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>

                            <td>
                                <input type="text"
                                       name="note[]"
                                       class="form-control input-sm"
                                       value="<?php echo ob_h($a['note']); ?>">
                            </td>

                            <td class="text-center">
                                <input type="checkbox" name="elimina_alias[]" value="<?php echo intval($a['id']); ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($aliases)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">
                                Nessun alias configurato.
                            </td>
                        </tr>
                    <?php endif; ?>

                    </tbody>
                </table>

                <button class="btn btn-success">
                    <span class="glyphicon glyphicon-floppy-disk"></span>
                    Salva tutti gli abbinamenti
                </button>
            </form>

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