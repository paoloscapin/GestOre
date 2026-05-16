<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST['programma_id']) && isset($_POST['programma_id']) != "") {
	$programma_id = $_POST['programma_id'];

    $query = "SELECT
            programmi_iniziali.id as programma_id,
            programmi_iniziali.id_classe as programma_classe,
            programmi_iniziali.id_docente as programma_iddocente,
            programmi_iniziali.id_materia as programma_idmateria,
            programmi_iniziali.id_utente as programma_idutente,
            programmi_iniziali.updated as programma_updated,
    		utente.id,
			utente.nome AS utente_nome,
			utente.cognome AS utente_cognome
        FROM
            programmi_iniziali
		INNER JOIN utente utente
    	ON programmi_iniziali.id_utente = utente.id
        WHERE programmi_iniziali.id = '$programma_id'";

    $programma = dbGetFirst($query);
    if ($programma != null) {
        dbExec("
            CREATE TABLE IF NOT EXISTS programmi_iniziali_classi (
                id INT NOT NULL AUTO_INCREMENT,
                id_programma_iniziale INT NOT NULL,
                id_classe INT NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_programma_classe (id_programma_iniziale, id_classe)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8
        ");

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
                        SELECT pic.id_classe
                        FROM programmi_iniziali_classi pic
                        WHERE pic.id_programma_iniziale = " . intval($programma_id) . "
                    )
              )
              AND NOT EXISTS (
                  SELECT 1
                  FROM programmi_iniziali_classi pic
                  WHERE pic.id_programma_iniziale = " . intval($programma_id) . "
                    AND pic.id_classe NOT IN (
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

    $struct_json = json_encode($programma);
   echo json_encode($programma);
}
?>
