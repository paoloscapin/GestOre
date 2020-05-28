<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/__Minuti.php';

$docente_id = $__docente_id;

// disegna la tabella
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green"><tbody>
						<tr>
							<th>Tipo</th>
							<th>Dettaglio</th>
							<th class="col-md-1 text-center">Importo</th>
						</tr>';

// azzera il totale
$totale = 0;

// fuis diaria viaggi
$query = "	SELECT fuis_viaggio_diaria.importo AS importo,
                   viaggio.destinazione AS destinazione
            FROM fuis_viaggio_diaria fuis_viaggio_diaria
            INNER JOIN viaggio viaggio
            ON fuis_viaggio_diaria.viaggio_id = viaggio.id
            WHERE viaggio.docente_id = $docente_id
            AND viaggio.anno_scolastico_id = $__anno_scolastico_corrente_id
            ";

foreach(dbGetAll($query) as $row) {

	$totale += $row['importo'];

	$data .= '<tr>
	<td class="col-md-1">'.'Diaria vaiggio'.'</td>
	<td class="col-md-3">'.$row['destinazione'].'</td>
	<td class="col-md-3">'.$row['importo'].'</td>
	';
	$data .='</tr>';
}

// fuis assegnato
$query = "	SELECT fuis_assegnato.importo AS importo,
                   fuis_assegnato_tipo.nome AS nome
            FROM fuis_assegnato fuis_assegnato
            INNER JOIN fuis_assegnato_tipo fuis_assegnato_tipo
            ON fuis_assegnato.fuis_assegnato_tipo_id = fuis_assegnato_tipo.id
            WHERE fuis_assegnato.docente_id = $docente_id
            AND fuis_assegnato.anno_scolastico_id = $__anno_scolastico_corrente_id
            ";

foreach(dbGetAll($query) as $row) {

	$totale += $row['importo'];

	$data .= '<tr>
	<td class="col-md-1">'.'Fuis Assegnato'.'</td>
	<td class="col-md-3">'.$row['nome'].'</td>
	<td class="col-md-3">'.$row['importo'].'</td>
	';
	$data .='</tr>';
}

$data .= '</tbody><tfoot><tr>
<td class="col-md-1"></td>
<td class="col-md-3"><Strong>'.'Totale'.'</Strong></td>
<td class="col-md-3"><Strong>'.$totale.'</Strong></td>
';
$data .='</tr></tfoot>';

$data .= '</table></div>';

echo $data;
?>
