<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST['modulistica_richiesta_id']) && isset($_POST['modulistica_richiesta_id']) != "") {
	$modulistica_richiesta_id = $_POST['modulistica_richiesta_id'];

	$modulistica_richiesta = dbGetFirst("SELECT * FROM modulistica_richiesta WHERE id = $modulistica_richiesta_id;");

	$template_id = $modulistica_richiesta['modulistica_template_id'];
	$modulistica_richiesta['template'] = dbGetFirst("SELECT * FROM modulistica_template WHERE id = ". $template_id .";");

	$docente_id = $modulistica_richiesta['docente_id'];
	$modulistica_richiesta['docente'] = dbGetValue("SELECT CONCAT(cognome, ' ', nome) FROM docente WHERE id = ". $docente_id .";");

	$anno_scolastico_id = $modulistica_richiesta['anno_scolastico_id'];
	$modulistica_richiesta['anno'] = dbGetValue("SELECT anno FROM anno_scolastico WHERE id = ". $anno_scolastico_id .";");

	// valori per produci tabella
	$listaEtichette = [];
	$listaTipi = [];
	$listaValoriSelezionabili = [];
	$listaValori = [];

	foreach(dbGetAll("SELECT * FROM modulistica_template_campo WHERE modulistica_template_id = $template_id ORDER BY posizione;") as $campo) {
		$etichetta = $campo['etichetta'];
		$listaEtichette[] = $etichetta;
		$listaValoriSelezionabili[] = $campo['lista_valori'];
		$listaTipi[] = $campo['tipo'];
	
		$template_campo_id = $campo['id'];
		$richiesta_campo = dbGetFirst("SELECT * FROM modulistica_richiesta_campo WHERE modulistica_richiesta_id = $modulistica_richiesta_id AND modulistica_template_campo_id = $template_campo_id;");
		$listaValori[] = $richiesta_campo['valore'];
	}
		
	require_once '../docente/modulisticaProduciTabella.php';
	$modulistica_richiesta['tabella'] = produciTabella($listaEtichette, $listaValori, $listaTipi, $listaValoriSelezionabili);

	echo json_encode($modulistica_richiesta);
}
?>