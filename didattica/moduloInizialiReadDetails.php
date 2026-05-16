<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once __DIR__ . '/programmiInizialiWordLikeUtils.php';

if(isset($_POST['modulo_id']) && isset($_POST['modulo_id']) != "") {
	$modulo_id = intval($_POST['modulo_id']);

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
    if ($modulo != null) {
        $fields = [
            'modulo_conoscenze' => 'conoscenze',
            'modulo_competenze' => 'competenze',
            'modulo_abilita' => 'abilita',
            'modulo_periodo' => 'periodo',
        ];
        $updates = [];
        foreach ($fields as $jsonKey => $dbColumn) {
            $original = (string)($modulo[$jsonKey] ?? '');
            $normalized = programmaInizialeWordLikeEnsureHtml($original);
            $modulo[$jsonKey] = $normalized;
            if ($normalized !== trim($original)) {
                $updates[] = $dbColumn . " = '" . dbEscape($normalized) . "'";
            }
        }

        if (!empty($updates)) {
            $updates[] = "updated = updated";
            dbExec("UPDATE programmi_iniziali_moduli SET " . implode(', ', $updates) . " WHERE id = $modulo_id LIMIT 1");
        }
    }

    $struct_json = json_encode($modulo);
   echo json_encode($modulo);
}
?>
