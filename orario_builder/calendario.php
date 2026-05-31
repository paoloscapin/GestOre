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

$cal = dbGetFirst("
    SELECT *
    FROM orario_calendario_scolastico
    WHERE id_scenario = $idScenario
    LIMIT 1
");

$giorni = dbGetAll("
    SELECT *
    FROM orario_calendario_giorno_speciale
    WHERE id_scenario = $idScenario
    ORDER BY data_giorno
") ?: [];

function valCal($cal, $key, $default = '')
{
    return $cal && isset($cal[$key]) ? $cal[$key] : $default;
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <title>Calendario scolastico</title>
    <?php
    require_once __DIR__ . '/../common/header-common.php';
    require_once __DIR__ . '/../common/style.php';
    require_once __DIR__ . '/../common/_include_bootstrap-notify.php';
    ?>
</head>

<body>
    <?php require_once __DIR__ . '/../common/header-admin.php'; ?>

    <div class="container-fluid">

        <div class="panel panel-teal4">
            <div class="panel-heading">
                <span class="glyphicon glyphicon-calendar"></span>&ensp;
                Calendario scolastico - <?php echo ob_h($scenario['nome']); ?>
            </div>

            <div class="panel-body">

                <p>
                    <a class="btn btn-default" href="scenari.php">
                        <span class="glyphicon glyphicon-arrow-left"></span>&ensp;Indietro
                    </a>
                </p>

                <form method="post" action="calendario_save.php">
                    <input type="hidden" name="azione" value="salva_calendario">
                    <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">

                    <div class="row">
                        <div class="col-md-3">
                            <label>Inizio lezioni</label>
                            <input type="date" name="data_inizio_lezioni" class="form-control" required
                                value="<?php echo ob_h(valCal($cal, 'data_inizio_lezioni')); ?>">
                        </div>

                        <div class="col-md-3">
                            <label>Fine lezioni</label>
                            <input type="date" name="data_fine_lezioni" class="form-control" required
                                value="<?php echo ob_h(valCal($cal, 'data_fine_lezioni')); ?>">
                        </div>

                        <div class="col-md-3">
                            <label>Tipo periodo</label>
                            <select name="tipo_periodo" class="form-control">
                                <option value="TRIMESTRE_PENTAMESTRE" <?php echo valCal($cal, 'tipo_periodo') === 'TRIMESTRE_PENTAMESTRE' ? 'selected' : ''; ?>>
                                    Trimestre / Pentamestre
                                </option>
                                <option value="QUADRIMESTRI" <?php echo valCal($cal, 'tipo_periodo') === 'QUADRIMESTRI' ? 'selected' : ''; ?>>
                                    Quadrimestri
                                </option>
                                <option value="ALTRO" <?php echo valCal($cal, 'tipo_periodo') === 'ALTRO' ? 'selected' : ''; ?>>
                                    Altro
                                </option>
                            </select>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-3">
                            <label>Inizio primo periodo</label>
                            <input type="date" name="data_inizio_primo_periodo" class="form-control" required
                                value="<?php echo ob_h(valCal($cal, 'data_inizio_primo_periodo')); ?>">
                        </div>

                        <div class="col-md-3">
                            <label>Fine primo periodo</label>
                            <input type="date" name="data_fine_primo_periodo" class="form-control" required
                                value="<?php echo ob_h(valCal($cal, 'data_fine_primo_periodo')); ?>">
                        </div>

                        <div class="col-md-3">
                            <label>Inizio secondo periodo</label>
                            <input type="date" name="data_inizio_secondo_periodo" class="form-control" required
                                value="<?php echo ob_h(valCal($cal, 'data_inizio_secondo_periodo')); ?>">
                        </div>

                        <div class="col-md-3">
                            <label>Fine secondo periodo</label>
                            <input type="date" name="data_fine_secondo_periodo" class="form-control" required
                                value="<?php echo ob_h(valCal($cal, 'data_fine_secondo_periodo')); ?>">
                        </div>
                    </div>

                    <br>

                    <div class="form-group">
                        <label>Note</label>
                        <textarea name="note" class="form-control" rows="2"><?php echo ob_h(valCal($cal, 'note')); ?></textarea>
                    </div>

                    <button class="btn btn-success">
                        <span class="glyphicon glyphicon-floppy-disk"></span>&ensp;Salva calendario
                    </button>
                </form>

                <hr>

                <h4>Giorni speciali / vacanze</h4>

                <form method="post" action="calendario_save.php">
                    <input type="hidden" name="azione" value="salva_periodo">
                    <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">

                    <div class="row">
                        <div class="col-md-2">
                            <label>Dal</label>
                            <input type="date" name="data_dal" class="form-control" required>
                        </div>

                        <div class="col-md-2">
                            <label>Al</label>
                            <input type="date" name="data_al" class="form-control" required>
                        </div>

                        <div class="col-md-2">
                            <label>Tipo</label>
                            <select name="tipo" class="form-control">
                                <option value="FESTIVITA">Festività</option>
                                <option value="VACANZA">Vacanza</option>
                                <option value="PONTE">Ponte</option>
                                <option value="SOSPENSIONE_LEZIONI">Sospensione lezioni</option>
                                <option value="GIORNO_SPECIALE">Giorno speciale</option>
                                <option value="ALTRO">Altro</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label>Descrizione</label>
                            <input type="text" name="descrizione" class="form-control"
                                placeholder="Es. Vacanze di Natale">
                        </div>

                        <div class="col-md-1">
                            <label>Sosp.</label>
                            <select name="lezioni_sospese" class="form-control">
                                <option value="1">Sì</option>
                                <option value="0">No</option>
                            </select>
                        </div>

                        <div class="col-md-1">
                            <label>&nbsp;</label>
                            <button class="btn btn-primary btn-block">
                                <span class="glyphicon glyphicon-plus"></span>
                            </button>
                        </div>
                    </div>
                </form>

                <br>

                <table class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Descrizione</th>
                            <th>Lezioni sospese</th>
                            <th style="width:170px;">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($giorni as $g): ?>
                            <tr>
                                <form method="post" action="calendario_save.php">
                                    <input type="hidden" name="azione" value="salva_giorno">
                                    <input type="hidden" name="id_scenario" value="<?php echo intval($idScenario); ?>">
                                    <input type="hidden" name="id_giorno" value="<?php echo intval($g['id']); ?>">

                                    <td>
                                        <input type="date" name="data_giorno" class="form-control input-sm"
                                            value="<?php echo ob_h($g['data_giorno']); ?>" required>
                                    </td>

                                    <td>
                                        <select name="tipo" class="form-control input-sm">
                                            <?php
                                            $tipi = [
                                                'FESTIVITA' => 'Festività',
                                                'VACANZA' => 'Vacanza',
                                                'PONTE' => 'Ponte',
                                                'SOSPENSIONE_LEZIONI' => 'Sospensione lezioni',
                                                'GIORNO_SPECIALE' => 'Giorno speciale',
                                                'ALTRO' => 'Altro'
                                            ];
                                            foreach ($tipi as $k => $label):
                                            ?>
                                                <option value="<?php echo $k; ?>" <?php echo $g['tipo'] === $k ? 'selected' : ''; ?>>
                                                    <?php echo ob_h($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>

                                    <td>
                                        <input type="text" name="descrizione" class="form-control input-sm"
                                            value="<?php echo ob_h($g['descrizione']); ?>">
                                    </td>

                                    <td>
                                        <select name="lezioni_sospese" class="form-control input-sm">
                                            <option value="1" <?php echo intval($g['lezioni_sospese']) === 1 ? 'selected' : ''; ?>>Sì</option>
                                            <option value="0" <?php echo intval($g['lezioni_sospese']) === 0 ? 'selected' : ''; ?>>No</option>
                                        </select>
                                    </td>

                                    <td>
                                        <button class="btn btn-xs btn-success">
                                            <span class="glyphicon glyphicon-floppy-disk"></span>
                                            Salva
                                        </button>

                                        <button class="btn btn-xs btn-danger"
                                            name="azione"
                                            value="elimina_giorno"
                                            onclick="return confirm('Eliminare questo giorno speciale?');">
                                            <span class="glyphicon glyphicon-trash"></span>
                                            Elimina
                                        </button>
                                    </td>
                                </form>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($giorni)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">
                                    Nessun giorno speciale configurato.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

            </div>
        </div>

    </div>

</body>

</html>