<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('dirigente');
require_once '../common/connect.php';

$anno = isset($_GET['anno_scolastico_id']) ? intval($_GET['anno_scolastico_id']) : $__anno_scolastico_corrente_id;

$query = "
SELECT
  ba.id AS area_id,
  ba.codice AS area_codice,
  ba.descrizione AS area_descrizione,
  ba.valore_massimo AS area_valore_massimo,
  ba.peso_percentuale AS area_peso_percentuale,
  ba.valido AS area_valido,

  bi.id AS indicatore_id,
  bi.codice AS indicatore_codice,
  bi.descrizione AS indicatore_descrizione,
  bi.valore_massimo AS indicatore_valore_massimo,
  bi.valido AS indicatore_valido,

  b.id AS bonus_id,
  b.codice AS bonus_codice,
  b.descrittori AS bonus_descrittori,
  b.evidenze AS bonus_evidenze,
  b.valore_previsto AS bonus_valore_previsto,
  b.valido AS bonus_valido
FROM bonus_area ba
LEFT JOIN bonus_indicatore bi
  ON bi.bonus_area_id = ba.id
 AND bi.anno_scolastico_id = $anno
 AND (bi.valido IS NULL OR bi.valido = 1)

LEFT JOIN bonus b
  ON b.bonus_indicatore_id = bi.id
 AND b.anno_scolastico_id = $anno
 AND (b.valido IS NULL OR b.valido = 1)

WHERE (ba.valido IS NULL OR ba.valido = 1)
ORDER BY ba.codice, bi.codice, b.codice;
";

$rows = dbGetAll($query);

// Raggruppa: area -> indicatore -> bonus
$tree = [];
foreach ($rows as $r) {
    $areaId = $r['area_id'];
    if (!isset($tree[$areaId])) {
        $tree[$areaId] = [
            'id' => $areaId,
            'codice' => $r['area_codice'],
            'descrizione' => $r['area_descrizione'],
            'valore_massimo' => $r['area_valore_massimo'],
            'peso_percentuale' => $r['area_peso_percentuale'],
            'indicatori' => []
        ];
    }

    if (!empty($r['indicatore_id'])) {
        $indId = $r['indicatore_id'];
        if (!isset($tree[$areaId]['indicatori'][$indId])) {
            $tree[$areaId]['indicatori'][$indId] = [
                'id' => $indId,
                'codice' => $r['indicatore_codice'],
                'descrizione' => $r['indicatore_descrizione'],
                'valore_massimo' => $r['indicatore_valore_massimo'],
                'valido' => $r['indicatore_valido'],
                'bonus' => []
            ];
        }

        if (!empty($r['bonus_id'])) {
            $tree[$areaId]['indicatori'][$indId]['bonus'][] = [
                'id' => $r['bonus_id'],
                'codice' => $r['bonus_codice'],
                'descrittori' => $r['bonus_descrittori'],
                'evidenze' => $r['bonus_evidenze'],
                'valore_previsto' => $r['bonus_valore_previsto'],
                'valido' => $r['bonus_valido']
            ];
        }
    }
}

// Render HTML
$html = "";

foreach ($tree as $area) {
    $areaTitle = htmlspecialchars($area['codice']." - ".$area['descrizione']);

    $html .= '<div class="panel panel-info">';
    $html .= '  <div class="panel-heading container-fluid">';
    $html .= '    <div class="row">';
    $html .= '      <div class="col-md-8"><strong>'.$areaTitle.'</strong></div>';
    $html .= '      <div class="col-md-4 text-right">';
    $html .= '        <button class="btn btn-success btn-xs btn-add-indicatore" data-area-id="'.$area['id'].'"><span class="glyphicon glyphicon-plus"></span>&ensp;Indicatore</button>';
    $html .= '      </div>';
    $html .= '    </div>';
    $html .= '  </div>';

    $html .= '  <div class="panel-body">';
    $html .= '    <div class="table-wrapper">';
    $html .= '    <table class="table table-bordered table-striped table-green">';
    $html .= '      <thead>';
    $html .= '        <tr>';
    $html .= '          <th style="width:120px">Codice</th>';
    $html .= '          <th>Indicatore</th>';
    $html .= '          <th style="width:120px" class="text-center">Max</th>';
    $html .= '          <th style="width:220px"></th>';
    $html .= '        </tr>';
    $html .= '      </thead>';
    $html .= '      <tbody>';

    if (empty($area['indicatori'])) {
        $html .= '<tr><td colspan="4" class="text-center text-muted">Nessun indicatore per questo anno</td></tr>';
    } else {
        foreach ($area['indicatori'] as $ind) {
            $indObj = [
                'id' => $ind['id'],
                'codice' => $ind['codice'],
                'descrizione' => $ind['descrizione'],
                'valore_massimo' => $ind['valore_massimo'],
                'valido' => $ind['valido']
            ];
            $indJson = htmlspecialchars(json_encode($indObj), ENT_QUOTES, 'UTF-8');

            // ✅ Riga indicatore evidenziata (giallo più visibile)
            // (se vuoi ancora più acceso: #ffe08a)
            $html .= '<tr class="bonus-indicatore-row">';
            $html .= '  <td><strong>'.htmlspecialchars($ind['codice']).'</strong></td>';
            $html .= '  <td><strong>'.htmlspecialchars($ind['descrizione']).'</strong></td>';
            $html .= '  <td class="text-center"><strong>'.htmlspecialchars($ind['valore_massimo']).'</strong></td>';
            $html .= '  <td class="text-right">';
            $html .= '    <button class="btn btn-primary btn-xs btn-edit-indicatore" data-area-id="'.$area['id'].'" data-indicatore="'.$indJson.'"><span class="glyphicon glyphicon-pencil"></span></button> ';
            $html .= '    <button class="btn btn-danger btn-xs btn-del-indicatore" data-id="'.$ind['id'].'"><span class="glyphicon glyphicon-trash"></span></button> ';
            $html .= '    <button class="btn btn-success btn-xs btn-add-bonus" data-indicatore-id="'.$ind['id'].'"><span class="glyphicon glyphicon-plus"></span>&ensp;Bonus</button>';
            $html .= '  </td>';
            $html .= '</tr>';

            // ✅ Bonus table under indicator + spazio sotto (padding-bottom)
            $html .= '<tr><td colspan="4" style="padding:0 0 12px 0; background:#fafafa;">';
            $html .= '<div style="padding:8px 8px 12px 8px;">';
            $html .= '<table class="table table-condensed table-bordered" style="margin:0; background:white;">';
            $html .= '  <thead>';
            $html .= '    <tr>';
            $html .= '      <th style="width:120px">Codice</th>';
            $html .= '      <th>Descrittori</th>';
            $html .= '      <th style="width:120px" class="text-center">Valore</th>';
            $html .= '      <th style="width:120px"></th>';
            $html .= '    </tr>';
            $html .= '  </thead>';
            $html .= '  <tbody>';

            if (empty($ind['bonus'])) {
                $html .= '<tr><td colspan="4" class="text-center text-muted">Nessun bonus</td></tr>';
            } else {
                foreach ($ind['bonus'] as $b) {
                    $bObj = [
                        'id' => $b['id'],
                        'codice' => $b['codice'],
                        'descrittori' => $b['descrittori'],
                        'evidenze' => $b['evidenze'],
                        'valore_previsto' => $b['valore_previsto'],
                        'valido' => $b['valido']
                    ];
                    $bJson = htmlspecialchars(json_encode($bObj), ENT_QUOTES, 'UTF-8');

                    $html .= '<tr>';
                    $html .= '  <td>'.htmlspecialchars($b['codice']).'</td>';
                    $html .= '  <td>'.htmlspecialchars($b['descrittori']).'</td>';
                    $html .= '  <td class="text-center">'.htmlspecialchars($b['valore_previsto']).'</td>';
                    $html .= '  <td class="text-right">';
                    $html .= '    <button class="btn btn-primary btn-xs btn-edit-bonus" data-indicatore-id="'.$ind['id'].'" data-bonus="'.$bJson.'"><span class="glyphicon glyphicon-pencil"></span></button> ';
                    $html .= '    <button class="btn btn-danger btn-xs btn-del-bonus" data-id="'.$b['id'].'"><span class="glyphicon glyphicon-trash"></span></button>';
                    $html .= '  </td>';
                    $html .= '</tr>';
                }
            }

            $html .= '  </tbody>';
            $html .= '</table>';
            $html .= '</div>';
            $html .= '</td></tr>';
        }
    }

    $html .= '      </tbody>';
    $html .= '    </table>';
    $html .= '    </div>';
    $html .= '  </div>';
    $html .= '</div>';
}

echo $html;
