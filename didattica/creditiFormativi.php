<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-select.php';
require_once '../common/crediti_formativi_mbapp_lib.php';

ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

$yearOptions = cfm_year_options();
$selectedYear = cfm_normalize_year_label($_GET['anno'] ?? cfm_current_year_label());
if ($selectedYear === '' || !cfm_year_enabled($selectedYear)) {
    foreach ($yearOptions as $option) {
        if (!empty($option['enabled'])) {
            $selectedYear = $option['value'];
            break;
        }
    }
}

$classes = cfm_classes($selectedYear);
$selectedClass = trim((string)($_GET['classe'] ?? ''));
if ($selectedClass === '' || !in_array($selectedClass, $classes, true)) {
    $selectedClass = $classes[0] ?? '';
}

$rows = $selectedClass !== '' ? cfm_rows($selectedClass, $selectedYear) : [];
$columns = cfm_all_columns();

$yearOptionsHtml = '';
foreach ($yearOptions as $option) {
    $selected = $option['value'] === $selectedYear ? ' selected' : '';
    $disabled = empty($option['enabled']) ? ' disabled' : '';
    $yearOptionsHtml .= '<option value="' . cfm_h($option['value']) . '"' . $selected . $disabled . '>' . cfm_h($option['label']) . '</option>';
}

$classOptions = '';
foreach ($classes as $class) {
    $selected = $class === $selectedClass ? ' selected' : '';
    $classOptions .= '<option value="' . cfm_h($class) . '"' . $selected . '>' . cfm_h($class) . '</option>';
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Crediti formativi</title>
    <style>
        .crediti-toolbar {
            padding: 14px 16px;
        }

        .crediti-toolbar .control-label {
            display: block;
            text-align: center;
        }

        .crediti-actions {
            padding-top: 24px;
            white-space: nowrap;
        }

        .crediti-summary {
            color: #0b4f71;
            font-weight: 600;
            padding-top: 31px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .crediti-title {
            padding-top: 4px;
            line-height: 1.25;
        }

        .crediti-title b {
            font-size: 16px;
        }

        .crediti-toolbar .bootstrap-select > .dropdown-toggle {
            height: 38px;
        }

        @media (max-width: 991px) {
            .crediti-toolbar .control-label {
                text-align: left;
                margin-top: 10px;
            }

            .crediti-summary,
            .crediti-actions {
                padding-top: 12px;
                text-align: left;
            }

            .crediti-title {
                text-align: left;
            }
        }

        .crediti-table-wrap {
            overflow-x: auto;
            border: 1px solid #d8e2ea;
            border-radius: 4px;
            background: #fff;
        }

        .crediti-table {
            margin-bottom: 0;
            min-width: 1150px;
            font-size: 12px;
        }

        .crediti-table > thead > tr > th {
            background: #0b79a5;
            color: #fff;
            border-color: #075d7f;
            vertical-align: middle;
            white-space: nowrap;
        }

        .crediti-table > tbody > tr:nth-child(even) {
            background: #eefaf1;
        }

        .crediti-table > tbody > tr:nth-child(odd) {
            background: #fffdf0;
        }

        .crediti-table > tbody > tr > td {
            vertical-align: top;
            border-color: #d8e2ea;
            max-width: 260px;
            white-space: normal;
        }

        .crediti-table .num-cell,
        .crediti-table .short-cell {
            text-align: center;
            white-space: nowrap;
        }

        .crediti-empty {
            margin-top: 16px;
        }
    </style>
</head>

<body>
    <?php require_once '../common/header-didattica.php'; ?>

    <div class="container-fluid">
        <div class="panel panel-orange4">
            <div class="panel-heading crediti-toolbar">
                <form id="crediti_form" method="get" action="creditiFormativi.php">
                    <div class="row">
                        <div class="col-md-1 text-center crediti-title">
                            <span class="glyphicon glyphicon-list-alt" style="margin:5px"></span><br>
                            <b>Crediti<br>formativi</b>
                        </div>
                        <div class="col-md-3">
                            <label class="control-label" for="anno">Anno scolastico</label>
                            <select id="anno" name="anno" class="selectpicker" data-style="btn-yellow4" data-width="100%" data-size="10">
                                <?php echo $yearOptionsHtml; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="control-label" for="classe">Classe triennio</label>
                            <select id="classe" name="classe" class="selectpicker" data-style="btn-yellow4" data-width="100%" data-live-search="true" data-live-search-style="contains" data-live-search-placeholder="Cerca classe..." data-size="10">
                                <?php echo $classOptions; ?>
                            </select>
                        </div>
                        <div class="col-md-3 crediti-summary" title="<?php echo $selectedClass !== '' ? cfm_h($selectedClass) . ' - ' . cfm_h($selectedYear) . ' - ' . count($rows) . ' studenti' : 'Nessuna classe'; ?>">
                            <?php echo $selectedClass !== '' ? cfm_h($selectedClass) . ' - ' . cfm_h($selectedYear) . ' - ' . count($rows) . ' studenti' : 'Nessuna classe'; ?>
                        </div>
                        <div class="col-md-2 crediti-actions text-right">
                            <a class="btn btn-danger<?php echo $selectedClass === '' ? ' disabled' : ''; ?>" href="creditiFormativiExport.php?format=pdf&classe=<?php echo urlencode($selectedClass); ?>&anno=<?php echo urlencode($selectedYear); ?>">
                                <span class="glyphicon glyphicon-file"></span> PDF
                            </a>
                            <a class="btn btn-success<?php echo $selectedClass === '' ? ' disabled' : ''; ?>" href="creditiFormativiExport.php?format=xlsx&classe=<?php echo urlencode($selectedClass); ?>&anno=<?php echo urlencode($selectedYear); ?>">
                                <span class="glyphicon glyphicon-list-alt"></span> XLS
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            <div class="panel-body">
                <?php if ($selectedClass === '') : ?>
                    <div class="alert alert-warning crediti-empty">Nessuna classe del triennio trovata in MBApp per l'anno scolastico selezionato.</div>
                <?php elseif (empty($rows)) : ?>
                    <div class="alert alert-info crediti-empty">Nessuno studente trovato in MBApp per la classe <?php echo cfm_h($selectedClass); ?> nell'anno <?php echo cfm_h($selectedYear); ?>.</div>
                <?php else : ?>
                    <div class="crediti-table-wrap">
                        <table class="table table-bordered table-condensed crediti-table">
                            <thead>
                                <tr>
                                    <th class="short-cell">N.</th>
                                    <?php foreach ($columns as $label) : ?>
                                        <th><?php echo cfm_h($label); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $n = 1; ?>
                                <?php foreach ($rows as $row) : ?>
                                    <tr>
                                        <td class="num-cell"><?php echo $n++; ?></td>
                                        <?php foreach (array_keys($columns) as $key) : ?>
                                            <?php $short = in_array($key, ['esito', 'media', 'assenze', 'interesse', 'IRC', 'ASL_positivo', 'credito', 'credito_precedente', 'integrazione'], true); ?>
                                            <td class="<?php echo $short ? 'short-cell' : ''; ?>"><?php echo nl2br(cfm_h(cfm_row_value($row, $key))); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        $(function() {
            $('.selectpicker').selectpicker();
            $('#anno, #classe').on('changed.bs.select change', function() {
                $('#crediti_form').submit();
            });
        });
    </script>
</body>

</html>
