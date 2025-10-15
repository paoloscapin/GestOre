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
					programmi_iniziali_moduli.id AS modulo_id,
					programmi_iniziali_moduli.id_programma AS programma_id,
					programmi_iniziali_moduli.ordine AS modulo_ordine,
					programmi_iniziali_moduli.nome AS modulo_nome,
					programmi_iniziali_moduli.conoscenze AS modulo_conoscenze,
					programmi_iniziali_moduli.competenze AS modulo_competenze,
					programmi_iniziali_moduli.abilita AS modulo_abilita,
					programmi_iniziali_moduli.periodo AS modulo_periodo,
					programmi_iniziali_moduli.id_utente AS modulo_id_utente,
					programmi_iniziali_moduli.updated AS modulo_updated
				FROM programmi_iniziali_moduli
				WHERE programmi_iniziali_moduli.id=$modulo_id ";
	
	$query .= "ORDER BY programmi_iniziali_moduli.ordine ASC";

    $modulo = dbGetFirst($query);

    $struct_json = json_encode($modulo);
   echo json_encode($modulo);
}
?>