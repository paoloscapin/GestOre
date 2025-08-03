<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST['modulo_id']) && isset($_POST['modulo_id']) != "") {
	$modulo_id = $_POST['modulo_id'];

					$query = "	SELECT
					programma_moduli.id AS modulo_id,
					programma_moduli.id_programma AS programma_id,
					programma_moduli.ordine AS modulo_ordine,
					programma_moduli.nome AS modulo_nome,
					programma_moduli.conoscenze AS modulo_conoscenze,
					programma_moduli.abilita AS modulo_abilita,
					programma_moduli.competenze AS modulo_competenze,
					programma_moduli.periodo AS modulo_periodo,
					programma_moduli.updated AS modulo_updated
				FROM programma_moduli
				WHERE programma_moduli.id=$modulo_id ";
	
	$query .= "ORDER BY programma_moduli.ordine ASC";

    $modulo = dbGetFirst($query);

    $struct_json = json_encode($modulo);
   echo json_encode($modulo);
}
?>