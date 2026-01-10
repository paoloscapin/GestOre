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
    ruoloRichiesto('genitore', 'segreteria-didattica', 'dirigente');

    if ((!getSettingsValue('config', 'carenzeObiettiviMinimi', false)) ||
        (!getSettingsValue('carenzeObiettiviMinimi', 'visibile_studenti', false))
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
        .filter-label {
            display: block;
            margin: 8px 0 4px;
            font-weight: bold;
        }

        /* padding "ok va bene": poco, ma visibile */
        .panel-body {
            padding-left: 10px !important;
            padding-right: 10px !important;
        }

        /* cards spacing */
        .cards-container .card {
            padding: 12px 12px !important;
            margin: 10px 0 !important;
            border-radius: 12px;
        }

        .cards-container .card>div {
            margin: 4px 0;
        }

        .cards-container .label {
            display: inline-block;
            margin: 2px 4px 2px 0;
        }
    </style>
</head>

<body>
    <?php require_once '../common/header-genitore-mobile.php'; ?>

    <div class="container-fluid" style="margin-top:60px">
        <div class="panel panel-orange4">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-12" style="padding:10px">
                        <span class="glyphicon glyphicon-blackboard"></span>&ensp;<b>Carenze</b>
                    </div>

                    <!-- Filtri -->
                    <div class="col-xs-12">
                        <label class="filter-label" for="studente_filtro">Studente</label>
                        <select id="studente_filtro" name="studente_filtro"
                            class="studente_filtro selectpicker"
                            data-style="btn-yellow4"
                            data-live-search="true"
                            data-noneSelectedText="seleziona..."
                            data-width="85%"
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
        src="js/carenze.js?v=<?php echo $__software_version; ?>&d=mobile&id=<?php echo intval($firstId); ?>&a=<?php echo intval($anno_carenze); ?>">
    </script>
</body>

</html>