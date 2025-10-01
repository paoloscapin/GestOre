<?php
/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani
 *  @license    GPL-3.0+
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

    if ($__genitore_cognome == 'GENITORE') {
        // accesso generico per tutti gli studenti
    } else {
        if ((!getSettingsValue('config', 'carenzeObiettiviMinimi', false)) ||
            (!getSettingsValue('carenzeObiettiviMinimi', 'visibile_studenti', false))) {
            redirect("/error/unauthorized.php");
        }
    }

    // --- PREPARAZIONE OPZIONI STUDENTE (come desktop) ---
    $studenteFiltroOptionList = '';
    $firstId = 0;

    $studenti = dbGetAll("SELECT * FROM studente WHERE id IN (
        SELECT id_studente FROM genitori_studenti WHERE id_genitore = " . intval($__genitore_id) . "
    )");

    if ($studenti) {
        foreach ($studenti as $studente) {
            if ($firstId === 0) {
                $firstId = intval($studente['id']);
            }
            $studenteFiltroOptionList .= '<option value="' . intval($studente['id']) . '">'
                . htmlspecialchars($studente['cognome'] . ' ' . $studente['nome']) . '</option>';
        }
    }

    // --- PREPARAZIONE OPZIONI ANNI (come desktop) ---
    $query = "SELECT COUNT(id) FROM carenze WHERE id_anno_scolastico=" . intval($__anno_scolastico_corrente_id);
    $count = dbGetValue($query);
    $anno_carenze = ($count == 0) ? intval($__anno_scolastico_scorso_id) : intval($__anno_scolastico_corrente_id);

    $anniFiltroOptionList = '<option value="0">Tutti</option>';
    foreach (dbGetAll("SELECT * FROM anno_scolastico ORDER BY id DESC;") as $anno) {
        $selected = ($anno['id'] == $anno_carenze) ? ' selected' : '';
        $anniFiltroOptionList .= '<option value="' . htmlspecialchars($anno['id']) . '"' . $selected . '>'
                              . htmlspecialchars($anno['anno']) . '</option>';
    }
    ?>

    <!-- bootbox notificator -->
    <script type="text/javascript"
        src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">

    <style>
        /* migliora compattezza su mobile */
        .filter-label { display:block; margin:8px 0 4px; font-weight:bold; }
    </style>
</head>

<body>
<?php
require_once '../common/header-genitore-mobile.php';
require_once '../common/connect.php';
?>

<div class="container-fluid" style="margin-top:60px">
    <div class="panel panel-orange4">
        <div class="panel-heading">
            <div class="row">
                <div class="col-xs-12" style="padding:10px">
                    <span class="glyphicon glyphicon-blackboard"></span>&ensp;<b>Carenze</b>
                </div>

                <!-- 🔽 Filtri mobile: Studente + Anno -->
                <div class="col-xs-12">
                    <label class="filter-label" for="studente_filtro">Studente</label>
                    <select id="studente_filtro" name="studente_filtro"
                            class="selectpicker"
                            data-style="btn-yellow4"
                            data-live-search="true"
                            data-noneSelectedText="seleziona..."
                            data-width="100%">
                        <?php echo $studenteFiltroOptionList; ?>
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
                <!-- 🔼 Fine filtri -->
            </div>
        </div>

        <div class="panel-body">
            <div class="row">
                <div class="col-xs-12">
                    <div class="records_content"></div>
                </div>
            </div>
        </div>
        <!-- <div class="panel-footer"></div> -->
    </div>
</div>

<!-- Passo anche id studente iniziale e anno come in desktop -->
<script type="text/javascript"
        src="js/carenze.js?v=<?php echo $__software_version; ?>&d=mobile&id=<?php echo $firstId; ?>&a=<?php echo $anno_carenze; ?>">
</script>
</body>
</html>
