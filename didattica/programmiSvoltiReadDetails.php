<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

function programmiSvoltiHasProgramField(string $columnName): bool
{
    static $cache = [];
    if (!array_key_exists($columnName, $cache)) {
        $row = dbGetFirst("SHOW COLUMNS FROM programmi_svolti LIKE '" . dbEscape($columnName) . "'");
        $cache[$columnName] = ($row != null);
    }

    return $cache[$columnName];
}

if (isset($_POST['programma_id']) && isset($_POST['programma_id']) != "") {
    $programma_id = $_POST['programma_id'];

    $metodologieSql = programmiSvoltiHasProgramField('metodologie') ? "programmi_svolti.metodologie AS programma_metodologie," : "'' AS programma_metodologie,";
    $criteriSql = programmiSvoltiHasProgramField('criteri_valutazione') ? "programmi_svolti.criteri_valutazione AS programma_criteri_valutazione," : "'' AS programma_criteri_valutazione,";
    $testiSql = programmiSvoltiHasProgramField('testi_materiali') ? "programmi_svolti.testi_materiali AS programma_testi_materiali," : "'' AS programma_testi_materiali,";

    $query = "SELECT
            programmi_svolti.id as programma_id,
            programmi_svolti.id_classe as programma_classe,
            programmi_svolti.id_docente as programma_iddocente,
            programmi_svolti.id_materia as programma_idmateria,
            programmi_svolti.id_utente as programma_idutente,
            programmi_svolti.updated as programma_updated,
            $metodologieSql
            $criteriSql
            $testiSql
            classi.anno as programma_classe_anno,
    		utente.id,
			utente.nome AS utente_nome,
			utente.cognome AS utente_cognome
        FROM
            programmi_svolti
        INNER JOIN classi classi
        ON programmi_svolti.id_classe = classi.id
		INNER JOIN utente utente
    	ON programmi_svolti.id_utente = utente.id
        WHERE programmi_svolti.id = '$programma_id'";

    $programma = dbGetFirst($query);

    if ($programma != null) {
        $programma['programma_classe_select'] = $programma['programma_classe'];

        $queryArticolata = "
            SELECT ca.id
            FROM classi_articolate ca
            WHERE ca.attiva = 1
              AND ca.id_anno_scolastico = " . intval($__anno_scolastico_corrente_id) . "
              AND NOT EXISTS (
                  SELECT 1
                  FROM classi_articolate_classi cac
                  WHERE cac.id_articolata = ca.id
                    AND cac.id_classe NOT IN (
                        SELECT psc.id_classe
                        FROM programmi_svolti_classi psc
                        WHERE psc.id_programma_svolto = " . intval($programma_id) . "
                    )
              )
              AND NOT EXISTS (
                  SELECT 1
                  FROM programmi_svolti_classi psc
                  WHERE psc.id_programma_svolto = " . intval($programma_id) . "
                    AND psc.id_classe NOT IN (
                        SELECT cac.id_classe
                        FROM classi_articolate_classi cac
                        WHERE cac.id_articolata = ca.id
                    )
              )
            LIMIT 1
        ";

        $articolata = dbGetFirst($queryArticolata);

        if ($articolata != null) {
            $programma['programma_classe_select'] = 'A' . intval($articolata['id']);
        }
    }

    echo json_encode($programma);
}
