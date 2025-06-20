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
					programmi_svolti_moduli.id AS modulo_id,
					programmi_svolti_moduli.id_programma AS programma_id,
					programmi_svolti_moduli.ordine AS modulo_ordine,
					programmi_svolti_moduli.nome AS modulo_nome,
					programmi_svolti_moduli.contenuto AS modulo_contenuto,
					programmi_svolti_moduli.id_utente AS modulo_id_utente,
					programmi_svolti_moduli.updated AS modulo_updated
				FROM programmi_svolti_moduli
				WHERE programmi_svolti_moduli.id=$modulo_id ";
	
	$query .= "ORDER BY programmi_svolti_moduli.ordine ASC";

    $modulo = dbGetFirst($query);

    $struct_json = json_encode($modulo);
   echo json_encode($modulo);
}
?>