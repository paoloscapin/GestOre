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

$classi = ob_get_classi_attive();

$slot = dbGetAll("
    SELECT *
    FROM orario_slot
    WHERE id_scenario = $idScenario
    ORDER BY giorno, ora_index
") ?: [];

$vincoli = dbGetAll("
    SELECT v.*, c.classe
    FROM orario_classe_slot_vincolo v
    JOIN classi c ON c.id = v.id_classe
    WHERE v.id_scenario = $idScenario
") ?: [];

$vincoliMap = [];
foreach ($vincoli as $v) {
    $key = intval($v['id_classe']) . '_' . intval($v['id_slot']);
    $vincoliMap[$key] = $v['stato'];
}

$giorni = [
    1 => 'Lunedì',
    2 => 'Martedì',
    3 => 'Mercoledì',
    4 => 'Giovedì',
    5 => 'Venerdì',
    6 => 'Sabato'
];

$slotByOra = [];
foreach ($slot as $s) {
    $ora = intval($s['ora_index']);
    $giorno = intval($s['giorno']);
    $slotByOra[$ora][$giorno] = $s;
}

ksort($slotByOra);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Vincoli classi su slot</title>
    <?php
    require_once __DIR__ . '/../common/header-common.php';
    require_once __DIR__ . '/../common/style.php';
    require_once __DIR__ . '/../common/_include_bootstrap-notify.php';
    require_once __DIR__ . '/../common/_include_bootstrap-select.php';
    ?>

    <style>
        .slot-grid {
            table-layout: fixed;
            user-select: none;
        }

        .slot-cell {
            cursor: pointer;
            text-align: center;
            vertical-align: middle !important;
            height: 54px;
            font-size: 12px;
            border: 1px solid #ccc !important;
        }

        .slot-cell:hover {
            outline: 3px solid #333;
        }

        .slot-default {
            background: #ffffff;
        }

        .slot-DISPONIBILE {
            background: #dff0d8;
        }

        .slot-NON_DISPONIBILE {
            background: #f2dede;
        }

        .slot-OBBLIGATORIO {
            background: #d9edf7;
        }

        .slot-PREFERITO {
            background: #fcf8e3;
        }

        .legend-box {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 1px solid #999;
            vertical-align: middle;
            margin-right: 4px;
        }

        .toolbar-vincoli {
            position: sticky;
            top: 60px;
            z-index: 10;
            background: white;
            border: 1px solid #ddd;
            padding: 12px;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>
<?php require_once __DIR__ . '/../common/header-admin.php'; ?>

<div class="container-fluid">

    <div class="panel panel-teal4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-th"></span>&ensp;
            Vincoli classi su griglia oraria - <?php echo ob_h($scenario['nome']); ?>
        </div>

        <div class="panel-body">

            <p>
                <a class="btn btn-default" href="scenari.php">
                    <span class="glyphicon glyphicon-arrow-left"></span>&ensp;Indietro
                </a>

                <a class="btn btn-info" href="slot.php?id_scenario=<?php echo intval($idScenario); ?>">
                    <span class="glyphicon glyphicon-time"></span>&ensp;Gestisci slot
                </a>
            </p>

            <div class="alert alert-info">
                Seleziona una o più classi, scegli il pennello e clicca sugli slot.
                Il vincolo viene applicato a tutte le classi selezionate.
            </div>

            <div class="toolbar-vincoli">
                <div class="row">
                    <div class="col-md-5">
                        <label>Classi</label>
                        <select id="classi" class="form-control selectpicker" multiple data-live-search="true" data-actions-box="true">
                            <?php foreach ($classi as $c): ?>
                                <option value="<?php echo intval($c['id']); ?>">
                                    <?php echo ob_h($c['classe']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>Pennello</label>
                        <select id="stato" class="form-control">
                            <option value="NON_DISPONIBILE">Non disponibile</option>
                            <option value="DISPONIBILE">Disponibile</option>
                            <option value="OBBLIGATORIO">Obbligatorio</option>
                            <option value="PREFERITO">Preferito</option>
                            <option value="CANCELLA">Cancella vincolo</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label>Legenda</label><br>
                        <span class="legend-box slot-DISPONIBILE"></span> Disponibile
                        <span class="legend-box slot-NON_DISPONIBILE"></span> Non disp.
                        <span class="legend-box slot-OBBLIGATORIO"></span> Obbl.
                        <span class="legend-box slot-PREFERITO"></span> Pref.
                    </div>
                </div>
            </div>

            <?php if (empty($slot)): ?>
                <div class="alert alert-warning">
                    Nessuno slot configurato per questo scenario.
                    Vai in <strong>Gestisci slot</strong> e genera la griglia.
                </div>
            <?php else: ?>

                <table class="table table-bordered slot-grid">
                    <thead>
                    <tr>
                        <th style="width:120px;">Ora</th>
                        <?php foreach ($giorni as $g): ?>
                            <th><?php echo ob_h($g); ?></th>
                        <?php endforeach; ?>
                    </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($slotByOra as $oraIndex => $giorniSlot): ?>
                        <tr>
                            <th>
                                <?php echo intval($oraIndex); ?>ª ora
                                <?php
                                $sample = reset($giorniSlot);
                                if ($sample) {
                                    echo '<br><small>' . ob_h(substr($sample['ora_inizio'], 0, 5)) . ' - ' . ob_h(substr($sample['ora_fine'], 0, 5)) . '</small>';
                                }
                                ?>
                            </th>

                            <?php foreach ($giorni as $giorno => $nomeGiorno): ?>
                                <?php if (isset($giorniSlot[$giorno])): ?>
                                    <?php
                                    $s = $giorniSlot[$giorno];
                                    $idSlot = intval($s['id']);
                                    ?>
                                    <td class="slot-cell slot-default"
                                        data-slot="<?php echo $idSlot; ?>"
                                        data-giorno="<?php echo intval($giorno); ?>"
                                        data-ora="<?php echo intval($oraIndex); ?>">
                                        <?php echo ob_h(substr($s['ora_inizio'], 0, 5)); ?><br>
                                        <?php echo ob_h(substr($s['ora_fine'], 0, 5)); ?>
                                    </td>
                                <?php else: ?>
                                    <td class="text-muted text-center">-</td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

            <?php endif; ?>

        </div>
    </div>

</div>

<script>
var ID_SCENARIO = <?php echo intval($idScenario); ?>;
var vincoliMap = <?php echo json_encode($vincoliMap); ?>;

function statoClasse(stato) {
    if (!stato) return 'slot-default';
    return 'slot-' + stato;
}

function pulisciClassiSlot($cell) {
    $cell.removeClass('slot-default slot-DISPONIBILE slot-NON_DISPONIBILE slot-OBBLIGATORIO slot-PREFERITO');
}

function aggiornaVistaPerClasse() {
    var classi = jQuery('#classi').val() || [];

    jQuery('.slot-cell').each(function () {
        var $cell = jQuery(this);
        var idSlot = $cell.data('slot');

        pulisciClassiSlot($cell);

        if (classi.length !== 1) {
            $cell.addClass('slot-default');
            return;
        }

        var key = classi[0] + '_' + idSlot;
        var stato = vincoliMap[key] || '';
        $cell.addClass(statoClasse(stato));
    });
}

function salvaVincolo(idSlot) {
    var classi = jQuery('#classi').val() || [];
    var stato = jQuery('#stato').val();

    if (classi.length === 0) {
        alert('Seleziona almeno una classe.');
        return;
    }

    jQuery.post('classe_slot_vincoli_save.php', {
        id_scenario: ID_SCENARIO,
        id_slot: idSlot,
        stato: stato,
        classi: classi
    }, function (res) {
        if (!res || !res.ok) {
            alert(res && res.error ? res.error : 'Errore salvataggio');
            return;
        }

        for (var i = 0; i < classi.length; i++) {
            var key = classi[i] + '_' + idSlot;

            if (stato === 'CANCELLA') {
                delete vincoliMap[key];
            } else {
                vincoliMap[key] = stato;
            }
        }

        aggiornaVistaPerClasse();
    }, 'json');
}

jQuery(function () {
    if (typeof jQuery.fn.selectpicker === 'function') {
        jQuery('.selectpicker').selectpicker();
    }

    jQuery('#classi').on('changed.bs.select change', aggiornaVistaPerClasse);

    jQuery('.slot-cell').on('click', function () {
        salvaVincolo(jQuery(this).data('slot'));
    });

    aggiornaVistaPerClasse();
});
</script>

</body>
</html>