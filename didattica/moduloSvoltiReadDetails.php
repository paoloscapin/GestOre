<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
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
					programmi_svolti_moduli.updated AS modulo_updated,
					classi.anno AS programma_classe_anno
				FROM programmi_svolti_moduli
				INNER JOIN programmi_svolti
				ON programmi_svolti.id = programmi_svolti_moduli.id_programma
				INNER JOIN classi
				ON classi.id = programmi_svolti.id_classe
				WHERE programmi_svolti_moduli.id=$modulo_id ";
	
	$query .= "ORDER BY programmi_svolti_moduli.ordine ASC";

    $modulo = dbGetFirst($query);
    $modulo['modulo_is_quinta_structured'] = 0;
    $modulo['modulo_competenze_raggiunte'] = '';
    $modulo['modulo_contenuti_trattati'] = '';
    $modulo['modulo_abilita_quinta'] = '';

    $contenuto = (string)($modulo['modulo_contenuto'] ?? '');
    $decoded = json_decode($contenuto, true);
    if (is_array($decoded) && (($decoded['schema'] ?? '') === 'programma_svolto_quinta_v1')) {
        $modulo['modulo_is_quinta_structured'] = 1;
        $modulo['modulo_competenze_raggiunte'] = (string)($decoded['competenze_raggiunte'] ?? '');
        $modulo['modulo_contenuti_trattati'] = (string)($decoded['contenuti_trattati'] ?? '');
        $modulo['modulo_abilita_quinta'] = (string)($decoded['abilita'] ?? '');
    }

    $struct_json = json_encode($modulo);
   echo json_encode($modulo);
}
?>
