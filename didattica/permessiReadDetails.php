<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST['id']) && isset($_POST['id']) != "") {
	$permesso_id = $_POST['id'];

    $query = "SELECT
            permessi_uscita.id as permesso_id,
            permessi_uscita.id_genitore as permesso_id_genitore,
            permessi_uscita.id_studente as permesso_id_studente,
            permessi_uscita.data as permesso_data,
            permessi_uscita.ora_uscita as permesso_ora_uscita,
            permessi_uscita.ora_rientro as permesso_ora_rientro,
            permessi_uscita.rientro as permesso_rientro,
            permessi_uscita.motivo as permesso_motivo,
            permessi_uscita.stato as permesso_stato,
            permessi_uscita.note_segreteria as permesso_note_segreteria,
            classi.classe as studente_classe,
            genitori.id as genitore_id,
            genitori.nome AS genitore_nome,
            genitori.cognome AS genitore_cognome,
            studente.id AS studente_id,
            studente.nome AS studente_nome,
            studente.cognome AS studente_cognome
        FROM
            permessi_uscita
        INNER JOIN genitori genitori
        ON permessi_uscita.id_genitore = genitori.id
        INNER JOIN studente studente
        ON permessi_uscita.id_studente = studente.id
        INNER JOIN studente_frequenta
        ON studente_frequenta.id_studente = studente.id AND studente_frequenta.id_anno_scolastico = '$__anno_scolastico_corrente_id'
        INNER JOIN classi classi
        ON classi.id = studente_frequenta.id_classe
        WHERE permessi_uscita.id = '$permesso_id'";

    $permesso = dbGetFirst($query);

    $struct_json = json_encode($permesso);
   echo json_encode($permesso);
}
?>