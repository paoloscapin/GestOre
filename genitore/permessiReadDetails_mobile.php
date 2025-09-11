<?php
/**
 *  Versione MOBILE di permessiReadDetails.php
 *  Restituisce JSON con dettagli permesso
 */

require_once '../common/checkSession.php';

if(isset($_POST['id']) && $_POST['id'] != "") {
    $permesso_id = $_POST['id'];

    $query = "SELECT
                permessi_uscita.id AS permesso_id,
                permessi_uscita.id_genitore AS permesso_id_genitore,
                permessi_uscita.id_studente AS permesso_id_studente,
                permessi_uscita.data AS permesso_data,
                permessi_uscita.ora_uscita AS permesso_ora_uscita,
                permessi_uscita.ora_rientro AS permesso_ora_rientro,
                permessi_uscita.rientro AS permesso_rientro,
                permessi_uscita.motivo AS permesso_motivo,
                permessi_uscita.stato AS permesso_stato,
                genitori.nome AS genitore_nome,
                genitori.cognome AS genitore_cognome,
                studente.nome AS studente_nome,
                studente.cognome AS studente_cognome
              FROM permessi_uscita
              INNER JOIN genitori ON permessi_uscita.id_genitore = genitori.id
              INNER JOIN studente ON permessi_uscita.id_studente = studente.id
              WHERE permessi_uscita.id = '$permesso_id'";

    $permesso = dbGetFirst($query);
    echo json_encode($permesso);
}
