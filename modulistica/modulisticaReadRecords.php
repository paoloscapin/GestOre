<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

$soloValidi = $_GET["soloValidi"];
$data = '';
$primaRiga = true;

foreach(dbGetAll("SELECT * FROM modulistica_categoria ORDER BY posizione;") as $categoria) {
    $categoriaId = $categoria["id"];
    $categoriaNome = $categoria["nome"];
    $categoriaColore = $categoria["colore"];

    $data .= '<div class="table-wrapper"><table id="modulistica_docenti_table" class="table table-bordered table-striped table-green">
        <thead> <tr style="'.$categoriaColore.'" >';
    if ($primaRiga) {
        $primaRiga = false;
        $data .= '
            <th class="text-center col-md-9">'.$categoriaNome.'</th>
            <th class="text-center col-md-1">Valido</th>
            <th class="text-center col-md-1">Modifica</th>
            <th class="text-center col-md-1"></th>';
    } else {
        $data .= '
            <th class="text-center col-md-9">'.$categoriaNome.'</th>
            <th class="text-center col-md-1"></th>
            <th class="text-center col-md-1"></th>
            <th class="text-center col-md-1"></th>';
    }
    $data .= '</tr> </thead> <tbody>';

    $query = "SELECT modulistica_template.id AS local_id, modulistica_template.* FROM modulistica_template WHERE modulistica_template.modulistica_categoria_id = $categoriaId ";
    if( $soloValidi ) {
        $query .= "AND modulistica_template.valido is true ";
    }
    
    $query .= "ORDER BY valido DESC, posizione ASC;";


    foreach(dbGetAll($query) as $template) {
        $statoMarker = '';
        if (! $template['valido']) {
            $statoMarker = '<span class="label label-danger">disattivato</span>';
        } else {
            $statoMarker = '<span class="label label-success">si</span>';
        }

        $data .= '
                <tr>
                    <td>'.$template['nome'].'</td>
                    <td class="text-center">'.$statoMarker.'</td>
                    <td class="text-center">
                        <button onclick="modulisticaOpenTemplate('.$template['local_id'].')" class="btn btn-teal4 btn-xs"><span class="glyphicon glyphicon-file">&nbsp;Contenuto</span></button>
                    </td>
                    <td>
                        <button onclick="modulisticaGetDetails('.$template['local_id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></button>
                        <button onclick="modulisticaDelete('.$template['local_id'].', \''.$template['nome'].'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
                    </td>
                </tr>';
    }

    $data .= '</table></div>';
}
    echo $data;
?>
