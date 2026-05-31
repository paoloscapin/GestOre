<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

$idPiano = ob_int($_GET['id_piano'] ?? 0);

$piano = dbGetFirst("
    SELECT *
    FROM orario_piano_orario
    WHERE id = $idPiano
    LIMIT 1
");

if (!$piano) {
    die('Piano non trovato');
}

$materie = ob_get_materie();

$righe = dbGetAll("
    SELECT
        pm.*,
        m.nome AS materia,
        m.codice,
        bt.sequenza AS sequenza_teoria,
        bl.sequenza AS sequenza_laboratorio
    FROM orario_piano_orario_materia pm
    JOIN materia m ON m.id = pm.id_materia
    LEFT JOIN orario_piano_orario_materia_blocco bt
        ON bt.id_piano_orario_materia = pm.id
       AND bt.tipo_ora = 'TEORIA'
    LEFT JOIN orario_piano_orario_materia_blocco bl
        ON bl.id_piano_orario_materia = pm.id
       AND bl.tipo_ora = 'LABORATORIO'
    WHERE pm.id_piano_orario = $idPiano
    ORDER BY m.nome
") ?: [];

$pianiOrigine = dbGetAll("
    SELECT id, nome
    FROM orario_piano_orario
    WHERE id <> $idPiano
      AND attivo = 1
    ORDER BY nome
") ?: [];
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <title>Materie piano orario</title>
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

    <div class="panel panel-teal4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-list-alt"></span>&ensp;
            Materie/ore - <?php echo ob_h($piano['nome']); ?>
        </div>

        <div class="panel-body">

            <p>
                <a class="btn btn-default" href="piani_orario.php">
                    <span class="glyphicon glyphicon-arrow-left"></span>&ensp;Indietro
                </a>
            </p>

            <form method="post" action="piano_importa_da.php" style="margin-bottom:20px;">
                <input type="hidden" name="id_piano_destinazione" value="<?php echo intval($idPiano); ?>">

                <div class="row">
                    <div class="col-md-8">
                        <label>Copia materie e vincoli da un piano già configurato</label>
                        <select name="id_piano_origine" class="form-control selectpicker" data-live-search="true" required>
                            <option value="">Seleziona piano origine...</option>
                            <?php foreach ($pianiOrigine as $po): ?>
                                <option value="<?php echo intval($po['id']); ?>">
                                    <?php echo ob_h($po['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label>&nbsp;</label>
                        <button type="submit"
                                class="btn btn-warning btn-block"
                                onclick="return confirm('Copiare le materie dal piano selezionato? Le materie già presenti nello stesso piano saranno aggiornate.');">
                            <span class="glyphicon glyphicon-duplicate"></span>
                            Copia da piano esistente
                        </button>
                    </div>
                </div>
            </form>

            <h4>Aggiungi materia al piano</h4>

            <form method="post" action="piano_materia_save.php" class="js-sequenze-box">
                <input type="hidden" name="id_piano" value="<?php echo intval($idPiano); ?>">
                <input type="hidden" name="azione" value="salva">

                <div class="row">
                    <div class="col-md-4">
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

                    <div class="col-md-2">
                        <label>Ore totali materia</label>
                        <input type="number" step="0.5" min="0" name="ore_teoria" value="0" class="form-control">
                    </div>

                    <div class="col-md-2">
                        <label>Sequenza teoria</label>
                        <select name="sequenza_teoria" class="form-control">
                            <option value="">Nessuna</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label>Ore laboratorio/compresenza</label>
                        <input type="number" step="0.5" min="0" name="ore_laboratorio" value="0" class="form-control">
                    </div>

                    <div class="col-md-2">
                        <label>Sequenza laboratorio</label>
                        <select name="sequenza_laboratorio" class="form-control">
                            <option value="">Nessuna</option>
                        </select>
                    </div>
                </div>

                <br>

                <button type="submit" class="btn btn-primary">
                    <span class="glyphicon glyphicon-plus"></span>&ensp;Aggiungi / aggiorna materia
                </button>
            </form>

            <hr>

            <h4>Materie configurate</h4>

            <form method="post" action="piano_materie_mass_save.php">
                <input type="hidden" name="id_piano" value="<?php echo intval($idPiano); ?>">

                <table class="table table-striped table-bordered table-hover">
                    <thead>
                    <tr>
                        <th>Materia</th>
                        <th style="width:120px;">Ore totali materia</th>
                        <th style="width:220px;">Sequenza teoria</th>
                        <th style="width:150px;">Ore laboratorio/compresenza</th>
                        <th style="width:220px;">Sequenza laboratorio</th>
                        <th style="width:80px;">Ore classe</th>
                        <th style="width:110px;">Aule</th>
                        <th style="width:90px;">Elimina</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($righe as $r): ?>
                        <tr class="js-sequenze-box">
                            <td>
                                <strong><?php echo ob_h($r['materia']); ?></strong>
                                <?php echo !empty($r['codice']) ? '<br><small>' . ob_h($r['codice']) . '</small>' : ''; ?>

                                <input type="hidden" name="id_piano_materia[]" value="<?php echo intval($r['id']); ?>">
                            </td>

                            <td>
                                <input type="number"
                                       step="0.5"
                                       min="0"
                                       name="ore_teoria[]"
                                       value="<?php echo ob_h($r['ore_teoria']); ?>"
                                       class="form-control input-sm">
                            </td>

                            <td>
                                <select name="sequenza_teoria[]"
                                        class="form-control input-sm"
                                        data-valore="<?php echo ob_h($r['sequenza_teoria'] ?? ''); ?>">
                                    <option value="<?php echo ob_h($r['sequenza_teoria'] ?? ''); ?>">
                                        <?php echo ob_h($r['sequenza_teoria'] ?? ''); ?>
                                    </option>
                                </select>
                            </td>

                            <td>
                                <input type="number"
                                       step="0.5"
                                       min="0"
                                       name="ore_laboratorio[]"
                                       value="<?php echo ob_h($r['ore_laboratorio']); ?>"
                                       class="form-control input-sm">
                            </td>

                            <td>
                                <select name="sequenza_laboratorio[]"
                                        class="form-control input-sm"
                                        data-valore="<?php echo ob_h($r['sequenza_laboratorio'] ?? ''); ?>">
                                    <option value="<?php echo ob_h($r['sequenza_laboratorio'] ?? ''); ?>">
                                        <?php echo ob_h($r['sequenza_laboratorio'] ?? ''); ?>
                                    </option>
                                </select>
                            </td>

                            <td class="text-center">
                                <?php echo ob_h(floatval($r['ore_teoria'])); ?>
                            </td>

                            <td>
                                <a class="btn btn-xs btn-info"
                                   href="materia_aule.php?id_piano_materia=<?php echo intval($r['id']); ?>">
                                    <span class="glyphicon glyphicon-blackboard"></span>
                                    Aule
                                </a>
                            </td>

                            <td class="text-center">
                                <input type="checkbox"
                                       name="elimina[]"
                                       value="<?php echo intval($r['id']); ?>"
                                       title="Spunta e salva per eliminare">
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($righe)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">
                                Nessuna materia configurata per questo piano.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <?php if (!empty($righe)): ?>
                    <button type="submit"
                            class="btn btn-success"
                            onclick="return confirm('Salvare tutte le modifiche? Le righe spuntate in Elimina saranno rimosse.');">
                        <span class="glyphicon glyphicon-floppy-disk"></span>
                        Salva tutte le materie
                    </button>
                <?php endif; ?>
            </form>

        </div>
    </div>

</div>

<script>
if (typeof jQuery !== 'undefined' && typeof jQuery.fn.selectpicker === 'function') {
    jQuery('.selectpicker').selectpicker();
}
</script>

<script>
function normalizzaOre(valore) {
    const n = parseFloat(String(valore || '0').replace(',', '.'));
    if (Number.isNaN(n) || n <= 0) return 0;
    return Math.round(n);
}

function generaSequenze(totale) {
    totale = normalizzaOre(totale);

    if (!totale || totale <= 0) {
        return [];
    }

    const risultati = [];

    function backtrack(restante, corrente) {
        if (restante === 0) {
            risultati.push(corrente.join('+'));
            return;
        }

        for (let blocco = Math.min(3, restante); blocco >= 1; blocco--) {
            backtrack(restante - blocco, corrente.concat(blocco));
        }
    }

    backtrack(totale, []);

    return risultati;
}

function trovaCampo(box, baseName) {
    return box.querySelector('[name="' + baseName + '"], [name="' + baseName + '[]"]');
}

function aggiornaSelectSequenze(box) {
    const oreTotaliInput = trovaCampo(box, 'ore_teoria');
    const oreLabInput = trovaCampo(box, 'ore_laboratorio');

    const selectTeoria = trovaCampo(box, 'sequenza_teoria');
    const selectLab = trovaCampo(box, 'sequenza_laboratorio');

    if (!oreTotaliInput || !oreLabInput || !selectTeoria || !selectLab) {
        return;
    }

    const oreTotali = normalizzaOre(oreTotaliInput.value);
    const oreLab = normalizzaOre(oreLabInput.value);

    const oreSoloTeoria = Math.max(0, oreTotali - oreLab);

    riempiSelectSequenze(selectTeoria, oreSoloTeoria);
    riempiSelectSequenze(selectLab, oreLab);
}

function riempiSelectSequenze(select, ore) {
    if (!select) return;

    const valoreCorrente =
        select.value ||
        select.getAttribute('data-valore') ||
        '';

    const sequenze = generaSequenze(ore);

    select.innerHTML = '';

    const optVuota = document.createElement('option');
    optVuota.value = '';
    optVuota.textContent = ore > 0 ? 'Seleziona sequenza...' : 'Nessuna';
    select.appendChild(optVuota);

    sequenze.forEach(function(seq) {
        const opt = document.createElement('option');
        opt.value = seq;
        opt.textContent = seq;

        if (seq === valoreCorrente) {
            opt.selected = true;
        }

        select.appendChild(opt);
    });

    if (typeof jQuery !== 'undefined' && typeof jQuery.fn.selectpicker === 'function') {
        try {
            jQuery(select).selectpicker('refresh');
        } catch (e) {
            // select non bootstrap-select: nessuna azione necessaria
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.js-sequenze-box').forEach(function(box) {
        const oreTeoria = trovaCampo(box, 'ore_teoria');
        const oreLab = trovaCampo(box, 'ore_laboratorio');

        if (!oreTeoria || !oreLab) {
            return;
        }

        aggiornaSelectSequenze(box);

        oreTeoria.addEventListener('input', function() {
            aggiornaSelectSequenze(box);
        });

        oreLab.addEventListener('input', function() {
            aggiornaSelectSequenze(box);
        });
    });
});
</script>

</body>
</html>
