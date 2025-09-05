<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('segreteria-didattica');

if (isset($_POST)) {
    $id = $_POST['id'];
    $cognome = escapePost('cognome');
    $nome = escapePost('nome');
    $email = escapePost('email');
    $id_classe = escapePost('id_classe');
    $id_anno = escapePost('id_anno');
    $codice_fiscale = escapePost('codice_fiscale');
    $userId = escapePost('userid');
    $attivo = escapePost('attivo');
    $esterno = escapePost('esterno');
    $era_attivo = escapePost('era_attivo');

    $query = "SELECT id from classi where classe='EE'";
    $id_classe_esterno = dbGetValue($query);
    // devo aggiornare tabella frequenza
    if ($id > 0) {
        $query = "UPDATE studente SET cognome = '$cognome', nome = '$nome', email = '$email', username = '$userId', codice_fiscale = '$codice_fiscale', attivo = '$attivo' WHERE id = '$id'";
        dbExec($query);
        if ($era_attivo == 0 && $attivo == 1 && $id_anno != $__anno_scolastico_corrente_id) {
            // se era disattivato e ora lo attivo, devo inserire la frequenza per l'anno scolastico corrente
            $query = "INSERT INTO studente_frequenta(id_studente,id_anno_scolastico,id_classe) VALUES ('$id', '$__anno_scolastico_corrente_id', '$id_classe')";
            dbExec($query);
            info("attivato studente per corrente anno scolastico id=$id cognome=$cognome nome=$nome email=$email id_classe=$id_classe id_anno_scolastico=$id_anno");
        } else {
            $query = "UPDATE studente_frequenta SET id_classe = '$id_classe' WHERE id_studente = '$id' AND id_anno_scolastico = '$id_anno'";
            dbExec($query);
            if ($esterno) {
                $id_anno_esterno = $id_anno - 1;
                $query = "
                INSERT INTO studente_frequenta(id_studente,id_anno_scolastico,id_classe) VALUES('$id', '$__anno_scolastico_scorso_id', '$id_classe_esterno')
                ON DUPLICATE KEY UPDATE id_studente = VALUES(id_studente), id_anno_scolastico = VALUES(id_anno_Scolastico), id_classe = VALUES(id_classe)";
                dbExec($query);
            }
            info("aggiornato studente id=$id cognome=$cognome nome=$nome email=$email username=$userId codice_fiscale=$codice_fiscale id_classe=$id_classe id_anno_scolastico=$id_anno");
        }
    } else {
        $id_anno = $__anno_scolastico_corrente_id; // se non specificato, uso l'anno scolastico corrente
        // devo inserire un nuovo studente
        $query = "INSERT INTO studente(cognome, nome, email, username, codice_fiscale, attivo) VALUES('$cognome', '$nome', '$email', '$userId', '$codice_fiscale', '$attivo')";
        dbExec($query);
        $studenteId = dblastId();
        $query = "INSERT INTO studente_frequenta(id_studente,id_anno_scolastico,id_classe) VALUES('$studenteId', '$__anno_scolastico_corrente_id', '$id_classe')";
        dbExec($query);
        if ($esterno) {
            $query = "INSERT INTO studente_frequenta(id_studente,id_anno_scolastico,id_classe) VALUES('$studenteId', '$__anno_scolastico_scorso_id', '$id_classe_esterno')";
            dbExec($query);
        }
        info("aggiunto studente id=$studenteId cognome=$cognome nome=$nome email=$email username=$username codice_fiscale=$codice_fiscale id_classe=$id_classe id_anno=$id_anno attivo=$attivo");
    }
}
