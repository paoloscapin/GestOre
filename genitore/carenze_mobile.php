<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

/**
 *  GestOre - Carenze (genitore) MOBILE
 *  Allineata a studente: cards + padding + filtri studente/anno
 */
require_once '../common/checkSession.php';
ruoloRichiesto('genitore');
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Carenze</title>

    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    require_once '../common/_include_bootstrap-toggle.php';
    require_once '../common/_include_bootstrap-select.php';
    require_once '../common/_include_flatpickr.php';

    if ((!getSettingsValue('config', 'carenzeObiettiviMinimi', false)) ||
        (!getSettingsValue('carenzeObiettiviMinimi', 'visibile_genitori', false))
    ) {
        redirect("/error/unauthorized.php");
    }

    require_once '../common/connect.php';

    // --- studenti ---
    $studenteFiltroOptionList = '';
    $firstId = 0;

    $studenti = dbGetAll("SELECT * FROM studente WHERE attivo=1 AND id IN (
        SELECT id_studente FROM genitori_studenti WHERE id_genitore = " . intval($__genitore_id) . "
    )") ?: [];
    $soloUnFiglio = (count($studenti) === 1);

    foreach ($studenti as $studente) {
        if ($firstId === 0) $firstId = intval($studente['id']);
        $studenteFiltroOptionList .= '<option value="' . intval($studente['id']) . '">'
            . htmlspecialchars($studente['cognome'] . ' ' . $studente['nome']) . '</option>';
    }

    // --- anno default carenze ---
    $query = "SELECT COUNT(id) FROM carenze WHERE id_anno_scolastico=" . intval($__anno_scolastico_corrente_id);
    $count = dbGetValue($query);
    $anno_carenze = ($count == 0) ? intval($__anno_scolastico_scorso_id) : intval($__anno_scolastico_corrente_id);

    // --- anni ---
    $anniFiltroOptionList = '<option value="0">Tutti</option>';
    foreach (dbGetAll("SELECT * FROM anno_scolastico ORDER BY id DESC;") as $anno) {
        $selected = ($anno['id'] == $anno_carenze) ? ' selected' : '';
        $anniFiltroOptionList .= '<option value="' . intval($anno['id']) . '"' . $selected . '>'
            . htmlspecialchars($anno['anno']) . '</option>';
    }
    ?>

    <script type="text/javascript"
        src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">

    <style>
        body {
            background: #f5f8fb;
        }

        .carenze-mobile-panel.panel {
            background: transparent;
            border: 0;
            box-shadow: none;
            margin-bottom: 14px;
        }

        .carenze-mobile-panel .panel-heading {
            background: #fff2d9 !important;
            border: 1px solid #ffd58b;
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(38, 50, 70, .06);
            color: #263246 !important;
            margin: 12px 0;
            padding: 14px;
        }

        .carenze-mobile-title {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 12px;
            text-align: center;
        }

        .carenze-mobile-panel .panel-body {
            padding: 0 !important;
        }

        .filter-label {
            display: block;
            color: #263246;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .2px;
            margin: 10px 0 6px;
            text-transform: uppercase;
        }

        .carenze-mobile-panel .bootstrap-select,
        .carenze-mobile-panel select {
            width: 100% !important;
        }

        .cards-container {
            display: grid;
            gap: 12px;
            padding: 0;
        }

        .carenza-mobile-card {
            background: #fff;
            border: 1px solid #dfe7ef;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(38, 50, 70, .08);
            overflow: hidden;
        }

        .carenza-mobile-card-body {
            display: grid;
            gap: 10px;
            padding: 16px;
        }

        .carenza-mobile-row {
            display: grid;
            gap: 8px;
            grid-template-columns: minmax(82px, 32%) minmax(0, 1fr);
            line-height: 1.35;
        }

        .carenza-mobile-label {
            color: #5f6d7c;
            font-weight: 800;
        }

        .carenza-mobile-actions {
            background: #f6f9fb;
            border-top: 1px solid #e7edf3;
            display: flex;
            gap: 8px;
            justify-content: center;
            padding: 12px;
        }
    </style>
</head>

<body>
    <?php require_once '../common/header-genitore-mobile.php'; ?>

    <div class="container-fluid">
        <div class="panel panel-orange4 carenze-mobile-panel">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-12">
                        <div class="carenze-mobile-title"><span class="glyphicon glyphicon-blackboard"></span>&ensp;Carenze</div>
                    </div>

                    <!-- Filtri -->
                    <div class="col-xs-12">
                        <label class="filter-label" for="studente_filtro">Studente</label>
                        <select id="studente_filtro" name="studente_filtro"
                            class="studente_filtro selectpicker"
                            data-style="btn-yellow4"
                            data-live-search="true"
                            data-noneSelectedText="seleziona..."
                            data-width="100%"
                            <?php echo $soloUnFiglio ? 'disabled' : ''; ?>>
                            <?php echo $studenteFiltroOptionList ?>
                        </select>
                    </div>

                    <div class="col-xs-12">
                        <label class="filter-label" for="anni_filtro">Anno Scolastico</label>
                        <select id="anni_filtro" name="anni_filtro"
                            class="selectpicker"
                            data-style="btn-yellow4"
                            data-live-search="true"
                            data-noneSelectedText="seleziona..."
                            data-width="100%">
                            <?php echo $anniFiltroOptionList; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="panel-body">
                <div class="row">
                    <div class="col-xs-12">
                        <!-- target standard genitore -->
                        <div class="records_content"></div>

                        <!-- fallback/compat (non fa male) -->
                        <div id="carenze_mobile_container" class="cards-container" style="display:none;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script type="text/javascript"
    src="js/carenze.js?v=<?php echo $__software_version; ?>&t=<?php echo time(); ?>&d=mobile&id=<?php echo intval($firstId); ?>&a=<?php echo intval($anno_carenze); ?>">
</script>
</body>

</html>
