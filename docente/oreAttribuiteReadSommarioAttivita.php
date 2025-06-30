<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

$docente_id = $__docente_id;
if(isset($_POST['docente_id']) && isset($_POST['docente_id']) != "") {
    $docente_id = $_POST['docente_id'];
}

$data = '';

$data .= '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
						<thead><tr>
							<th class="col-md-2 text-left">Tipo</th>
							<th class="col-md-8 text-left">Nome</th>
							<th class="col-md-2 text-center">Ore</th>
						</tr></thead><tbody>';

// prima il sommario delle attivita' inserite dal docente
$query = "SELECT * FROM ore_previste_tipo_attivita WHERE ore_previste_tipo_attivita.inserito_da_docente = false ORDER BY categoria ASC;";
$resultArray = dbGetAll($query);
foreach($resultArray as $ore_previste_tipo_attivita) {
    $ore_previste_tipo_attivita_id = $ore_previste_tipo_attivita['id'];
    $query = "SELECT SUM(ore_previste_attivita.ore)
				FROM ore_previste_attivita ore_previste_attivita
				INNER JOIN ore_previste_tipo_attivita ore_previste_tipo_attivita
				ON ore_previste_attivita.ore_previste_tipo_attivita_id = ore_previste_tipo_attivita.id
				LEFT JOIN ore_previste_attivita_commento
				on ore_previste_attivita_commento.ore_previste_attivita_id = ore_previste_attivita.id
				WHERE ore_previste_attivita.anno_scolastico_id = $__anno_scolastico_corrente_id
				AND ore_previste_attivita.docente_id = $docente_id
				AND ore_previste_tipo_attivita.inserito_da_docente = 0
				AND ore_previste_tipo_attivita.previsto_da_docente = 0
                AND ore_previste_attivita.ore_previste_tipo_attivita_id = $ore_previste_tipo_attivita_id
				ORDER BY ore_previste_tipo_attivita.orientamento ASC, ore_previste_tipo_attivita.clil ASC, ore_previste_tipo_attivita.categoria, ore_previste_tipo_attivita.nome ASC";
    $ore = dbGetValue($query);
    if (!empty($ore)) {
        $data .= '<tr>
			<td>'.$ore_previste_tipo_attivita['categoria'].'</td>
			<td>'.$ore_previste_tipo_attivita['nome'].'</td>
			<td>'.$ore.'</td>
			</tr>
			';
    }
}

$data .= '</tbody>';

$data .= '</table>
';
$data .= '</div>';

echo $data;

?>
