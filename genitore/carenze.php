<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';
ruoloRichiesto('genitore');
?>

<!DOCTYPE html>
<html>

<head>
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    require_once '../common/_include_bootstrap-toggle.php';
    require_once '../common/_include_bootstrap-select.php';
    require_once '../common/_include_flatpickr.php';


    if ((!getSettingsValue('config', 'carenzeObiettiviMinimi', false)) || (!getSettingsValue('carenzeObiettiviMinimi', 'visibile_genitori', false))) {
        redirect("/error/unauthorized.php");
    }
    ?>

    <script type="text/javascript"
        src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">

    <title>Carenze</title>

    <style>
        /* Tooltip */
        .tooltip>.tooltip-inner {
            background-color: #73AD21;
            color: #FFFFFF;
            border: 1px solid green;
            padding: 15px;
            font-size: 20px;
        }

        .tooltip.top>.tooltip-arrow {
            border-top: 5px solid green;
        }

        .tooltip.bottom>.tooltip-arrow {
            border-bottom: 5px solid blue;
        }

        .tooltip.left>.tooltip-arrow {
            border-left: 5px solid red;
        }

        .tooltip.right>.tooltip-arrow {
            border-right: 5px solid black;
        }

        .tooltip-inner {
            max-width: 450px;
            width: 450px;
            text-align: left;
        }

        body.carenze-parent-page {
            background: #f5f7fb;
        }

        .carenze-parent-page .panel-orange4 {
            border: 0;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(31, 45, 61, 0.10);
            overflow: hidden;
        }

        .carenze-parent-page .panel-heading {
            border: 0;
            padding: 14px 16px;
        }

        .carenze-parent-page .panel-body {
            padding: 16px;
            background: #fff;
        }

        .carenze-table-card {
            border: 1px solid #dfe7ef;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
        }

        .carenze-table {
            margin-bottom: 0;
            table-layout: fixed;
            background: #fff;
        }

        .carenze-table > thead > tr > th {
            background: #f8fafc;
            border-bottom: 1px solid #dfe7ef;
            color: #263445;
            font-size: 13px;
            font-weight: 700;
            padding: 14px 10px;
            vertical-align: middle;
        }

        .carenze-table > tbody > tr > td {
            border-color: #e7edf3;
            color: #2f3b48;
            padding: 14px 12px;
            vertical-align: middle;
            line-height: 1.35;
        }

        .carenze-table > tbody > tr:nth-child(even) > td {
            background: #f3fff7;
        }

        .carenze-table > tbody > tr:hover > td {
            background: #eef8ff;
        }

        .carenze-subtle {
            color: #607182;
            font-size: 13px;
        }

        .carenze-materia {
            font-weight: 700;
        }

        .carenze-docente {
            color: #34495e;
        }

        .carenze-icon-btn {
            border-radius: 6px;
            padding: 6px 10px;
        }

        .carenze-status {
            border-radius: 999px;
            display: inline-block;
            font-size: 12px;
            line-height: 1.2;
            padding: 5px 9px;
            white-space: normal;
        }

        .carenze-course-btn {
            border-radius: 6px;
            font-weight: 700;
            padding: 6px 10px;
        }

        .carenze-course-date {
            background: #f8fafc;
            border: 1px solid #dfe7ef;
            border-radius: 6px;
            color: #34495e;
            font-size: 12px;
            margin-top: 7px;
            padding: 5px 7px;
        }

        .swal2-popup.carenze-course-modal {
            border-radius: 8px;
            padding: 20px 22px 18px;
        }

        .swal2-popup.carenze-course-modal .swal2-title {
            color: #263445;
            font-size: 22px;
            line-height: 1.25;
            margin-bottom: 14px;
        }

        .swal2-popup.carenze-course-modal .swal2-html-container {
            margin: 0;
            overflow-x: hidden;
        }

        .carenze-course-detail {
            color: #2f3b48;
            text-align: left;
        }

        .carenze-course-meta {
            background: #f8fafc;
            border: 1px solid #dfe7ef;
            border-radius: 8px;
            display: grid;
            gap: 6px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-bottom: 14px;
            padding: 12px 14px;
        }

        .carenze-course-meta-label {
            color: #607182;
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .carenze-course-meta-value {
            color: #263445;
            font-size: 14px;
            font-weight: 600;
        }

        .carenze-course-detail-table {
            border-collapse: separate;
            border-spacing: 0 8px;
            margin-bottom: 0;
        }

        .carenze-course-detail-table > thead > tr > th {
            background: transparent;
            border: 0;
            color: #263445;
            font-size: 12px;
            font-weight: 700;
            padding: 4px 12px 7px;
            text-transform: uppercase;
        }

        .carenze-course-detail-table > tbody > tr > td {
            background: #fff;
            border-color: #dfe7ef;
            border-style: solid;
            border-width: 1px 0;
            padding: 13px 12px;
            vertical-align: middle;
        }

        .carenze-course-detail-table > tbody > tr > td:first-child {
            border-left-width: 1px;
            border-radius: 8px 0 0 8px;
            box-shadow: inset 4px 0 0 #64b5f6;
            padding-left: 18px;
        }

        .carenze-course-detail-table > tbody > tr > td:last-child {
            border-right-width: 1px;
            border-radius: 0 8px 8px 0;
        }

        .carenze-course-detail-table > tbody > tr:hover > td {
            background: #f8fbff;
        }

        .carenze-course-detail-table .date-main {
            color: #263445;
            font-size: 14px;
            font-weight: 700;
        }

        .carenze-course-detail-table .date-time {
            color: #607182;
            font-size: 12px;
            margin-top: 3px;
        }

        .course-room-pill {
            background: #eef6ff;
            border: 1px solid #cfe3f8;
            border-radius: 999px;
            color: #29506f;
            display: inline-block;
            font-weight: 700;
            min-width: 52px;
            padding: 5px 10px;
            text-align: center;
        }

        .course-topic-cell {
            color: #34495e;
            font-size: 13px;
            line-height: 1.45;
        }

        .course-topic-cell:empty:before {
            color: #8a99a8;
            content: "-";
        }

        @media (max-width: 768px) {
            .carenze-table {
                table-layout: auto;
            }

            .carenze-course-meta {
                grid-template-columns: 1fr;
            }
        }
    </style>

</head>

<?php
require_once '../common/connect.php';

// prepara elenco studenti filtro
$studenteFiltroOptionList = '';
$annoDefaultByStudente = [];
$studenti = dbGetAll("SELECT * FROM studente WHERE attivo=1 AND id IN (
    SELECT id_studente FROM genitori_studenti WHERE id_genitore = " . intval($__genitore_id) . "
)");
$soloUnFiglio = (count($studenti) === 1);

$firstId = null;
foreach ($studenti as $studente) {
    if ($firstId === null) $firstId = $studente['id'];
    $cntCorrenteStudente = (int)dbGetValue("
        SELECT COUNT(id)
        FROM carenze
        WHERE id_anno_scolastico = " . intval($__anno_scolastico_corrente_id) . "
          AND id_studente = " . intval($studente['id']) . "
          AND (stato = 2 OR stato = 3)
    ");
    $annoDefaultByStudente[(string)intval($studente['id'])] = ($cntCorrenteStudente === 0)
        ? intval($__anno_scolastico_scorso_id)
        : intval($__anno_scolastico_corrente_id);
    $studenteFiltroOptionList .= '<option value="' . intval($studente['id']) . '">'
        . htmlspecialchars($studente['cognome'] . ' ' . $studente['nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</option>';
}

// anno default carenze
$anno_carenze = ($firstId !== null && isset($annoDefaultByStudente[(string)intval($firstId)]))
    ? intval($annoDefaultByStudente[(string)intval($firstId)])
    : intval($__anno_scolastico_scorso_id);

// opzioni anni
$anniFiltroOptionList = '<option value="0">Tutti</option>';
foreach (dbGetAll("SELECT * FROM anno_scolastico ORDER BY id DESC;") as $anno) {
    $selected = ($anno['id'] == $anno_carenze) ? ' selected' : '';
    $anniFiltroOptionList .= '<option value="' . intval($anno['id']) . '"' . $selected . '>'
        . htmlspecialchars($anno['anno'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</option>';
}
?>

<body class="carenze-parent-page">
    <?php require_once '../common/header-genitore.php'; ?>

    <div class="container-fluid">
        <div class="panel panel-orange4">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-md-1" style="padding:10px">
                        <span class="glyphicon glyphicon-blackboard"></span>&ensp;Carenze
                    </div>

                    <div class="col-md-5"></div>

                    <!-- Studente -->
                    <div class="col-md-3" style="padding:0px">
                        <div class="text-center">
                            <label class="col-sm-4 control-label" for="studente_filtro"
                                style="margin:10px 0 0 0; text-align:right">Studente</label>
                            <div class="col-sm-8" style="padding:0px;text-align:right">
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
                        </div>
                    </div>

                    <!-- Anno -->
                    <div class="col-md-3" style="padding:0px">
                        <div class="text-center">
                            <label class="col-sm-4 control-label" for="anni_filtro"
                                style="margin:10px 0 0 0; text-align:right">Anno</label>
                            <div class="col-sm-8" style="padding:0px;text-align:right">
                                <select id="anni_filtro" name="anni_filtro"
                                    class="anni_filtro selectpicker"
                                    data-style="btn-yellow4"
                                    data-live-search="true"
                                    data-noneSelectedText="seleziona..."
                                    data-width="100%">
                                    <?php echo $anniFiltroOptionList ?>
                                </select>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="panel-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="records_content"></div>
                    </div>
                </div>
            </div>

        </div>
    </div>

<script type="text/javascript">
    var carenzeAnnoDefaultByStudente = <?php echo json_encode($annoDefaultByStudente); ?>;
</script>
<script type="text/javascript"
    src="js/carenze.js?v=<?php echo $__software_version; ?>&t=<?php echo time(); ?>&d=desktop&id=<?php echo intval($firstId); ?>&a=<?php echo intval($anno_carenze); ?>">
</script></body>

</html>
