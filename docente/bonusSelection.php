<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('segreteria-docenti', 'dirigente', 'docente');

$anno_scolastico_id = isset($_GET['anno_scolastico_id'])
    ? intval($_GET['anno_scolastico_id'])
    : $__anno_scolastico_corrente_id;
?>
<!DOCTYPE html>
<html>

<head>
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    require_once '../common/_include_bootstrap-select.php';
    require_once '../common/_include_bootstrap-notify.php';
    ?>
    <title>Bonus Docente Selezione</title>
</head>

<body>
    <?php
    require_once '../common/header-docente.php';
    ?>

    <div class="container-fluid">

        <div class="row" style="margin-bottom:10px;">
            <div class="col-md-6">
                <button class="btn btn-default btn-sm" onclick="window.location.href='bonus.php?anno_scolastico_id=<?php echo $anno_scolastico_id; ?>'">
                    <span class="glyphicon glyphicon-arrow-left"></span>&ensp;Torna ai Bonus
                </button>
            </div>
            <div class="col-md-6 text-right">
                <select id="anno_scolastico_select" class="form-control" style="display:inline-block; width:auto;">
                    <?php
                    $anni = dbGetAll("SELECT id, anno FROM anno_scolastico ORDER BY anno DESC");
                    foreach ($anni as $a) {
                        $selected = ($a['id'] == $anno_scolastico_id) ? 'selected' : '';
                        echo '<option value="' . $a['id'] . '" ' . $selected . '>' . $a['anno'] . '</option>';
                    }
                    ?>
                </select>
            </div>
        </div>


        <?php
        // lista bonus già selezionati per questo docente e anno scelto
        $adesioniMap = []; // bonus_id => adesione_id

        $query = "SELECT id, bonus_id
          FROM bonus_docente
          WHERE docente_id = $__docente_id
            AND anno_scolastico_id = $anno_scolastico_id";
        $res0 = dbGetAll($query);

        foreach ($res0 as $bd) {
            $adesioniMap[(int)$bd['bonus_id']] = (int)$bd['id'];
        }


        // aree (non hanno anno)
        $data = '';
        $query = "SELECT * FROM bonus_area WHERE (valido IS NULL OR valido = 1) ORDER BY codice;";
        $resultArray = dbGetAll($query);

        foreach ($resultArray as $bonus_area) {
            $data .= '
		<div class="panel panel-lima4">
			<div class="panel-heading">
				<div class="row">
					<div class="col-md-1">
						<span class="glyphicon glyphicon-list-alt"></span>&ensp;' . htmlspecialchars($bonus_area['codice']) . '
					</div>
					<div class="col-md-10 text-left">
						<span style="white-space: pre-line">' . htmlspecialchars($bonus_area['descrizione']) . '</span>
					</div>
					<div class="col-md-1 text-right"></div>
				</div>
			</div>
			<div class="panel-body">
	';

            // indicatori per anno + valido
            $query = 'SELECT * FROM bonus_indicatore
			  WHERE (valido IS NULL OR valido = 1)
			    AND anno_scolastico_id = ' . $anno_scolastico_id . '
			    AND bonus_area_id = ' . $bonus_area['id'] . '
			  ORDER BY codice;';
            $res2 = dbGetAll($query);

            foreach ($res2 as $bonus_indicatore) {
                $data .= '
			<div class="row" style="margin-bottom:14px;">
				<div class="col-md-12">
					<div class="table-wrapper">
						<table class="table table-bordered table-striped table-green bonus_selection_table">
							<thead>
								<tr>
									<th></th><th></th>
									<th colspan="5" class="text-left">
										<span style="white-space: pre-line">' . htmlspecialchars($bonus_indicatore['codice'] . ' - ' . $bonus_indicatore['descrizione']) . '</span>
									</th>
								</tr>
								<tr>
									<th class="text-center col-md-1">bonus_id</th>
									<th class="text-center col-md-1">adesione_id</th>
									<th class="text-center col-md-1">Codice</th>
									<th class="text-center col-md-4">Descrittore</th>
									<th class="text-center col-md-5">Evidenze</th>
									<th class="text-center col-md-1">Valore</th>
									<th class="text-center col-md-1"></th>
								</tr>
							</thead>
							<tbody>
		';

                // bonus per anno + valido
                $query = 'SELECT * FROM bonus
				  WHERE (valido IS NULL OR valido = 1)
				    AND anno_scolastico_id = ' . $anno_scolastico_id . '
				    AND bonus_indicatore_id = ' . $bonus_indicatore['id'] . '
				  ORDER BY codice;';
                $res3 = dbGetAll($query);

                foreach ($res3 as $bonus) {
                    $bonus_id = intval($bonus['id']);
                    $bonus_valore = getSettingsValue('bonus', 'punteggio_variabile', false) ? '0 - ' : '';
                    $bonus_valore .= $bonus['valore_previsto'];

                    $data .= '<tr>';
                    $data .= '  <td>' . $bonus_id . '</td>';

                    $adesione_id = $adesioniMap[$bonus_id] ?? -1;
                    $data .= '  <td>' . (int)$adesione_id . '</td>';


                    $data .= '  <td class="text-center">' . htmlspecialchars($bonus['codice']) . '</td>';
                    $data .= '  <td class="text-left"><span style="white-space: pre-line">' . htmlspecialchars($bonus['descrittori']) . '</span></td>';
                    $data .= '  <td class="text-left"><span style="white-space: pre-line">' . htmlspecialchars($bonus['evidenze']) . '</span></td>';
                    $data .= '  <td class="text-center">' . htmlspecialchars($bonus_valore) . '</td>';

                    $canEdit = $__config->getBonus_adesione_aperto() && ($anno_scolastico_id == $__anno_scolastico_corrente_id);
                    $disabledAttr = $canEdit ? '' : ' disabled ';

                    $checkedAttr = ($adesione_id > 0) ? 'checked' : '';

                    $data .= '<td class="text-center">
                        <input type="checkbox"
                                class="richiesto"
                                data-toggle="toggle"
                                data-onstyle="primary"
                                data-bonus-id="' . (int)$bonus_id . '"
                                data-adesione-id="' . (int)$adesione_id . '"
                                ' . $disabledAttr . ' ' . $checkedAttr . '>
                        </td>';

                    $data .= '</tr>';
                }

                $data .= '
							</tbody>
						</table>
					</div>
				</div>
			</div>
		';
            }

            $data .= '</div></div>';
        }

        echo $data;
        ?>

    </div>

    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
    <script type="text/javascript" src="js/scriptBonus.js"></script>

</body>

</html>