<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';
ruoloRichiesto('dirigente','segreteria-didattica','docente');

function printableVoto($voto) {
	if ($voto != 0) {
		if ($voto == 1) {
			return 0;
		}
		return $voto;
	}
	return null;
}

function printableDate($data) {
	if ($data != null) {
		return strftime("%d/%m/%Y", strtotime($data));
	}
	return null;
}

$nome_anno_scolastico = dbGetValue("SELECT anno FROM anno_scolastico WHERE id=$__anno_scolastico_corrente_id");

// crea l'header per il file da scaricare
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=Corsi di Recupero - '.$nome_anno_scolastico.'.csv');

// recupera i gruppi dell'anno specificato
$query = "
SELECT
	studente_per_corso_di_recupero.cognome AS studente_per_corso_di_recupero_cognome,
	studente_per_corso_di_recupero.nome AS studente_per_corso_di_recupero_nome,
	studente_per_corso_di_recupero.classe AS studente_per_corso_di_recupero_classe,
	studente_per_corso_di_recupero.voto_settembre AS studente_per_corso_di_recupero_voto_settembre,
	studente_per_corso_di_recupero.data_voto_settembre AS studente_per_corso_di_recupero_data_voto_settembre,
	studente_per_corso_di_recupero.voto_novembre AS studente_per_corso_di_recupero_voto_novembre,
	studente_per_corso_di_recupero.data_voto_novembre AS studente_per_corso_di_recupero_data_voto_novembre,
	studente_per_corso_di_recupero.passato AS studente_per_corso_di_recupero_passato,
	corso_di_recupero.codice AS corso_di_recupero_codice,
	materia.nome AS materia_nome,
	docente_set.nome AS docente_set_nome,
	docente_set.cognome AS docente_set_cognome,
	docente_nov.nome AS docente_nov_nome,
	docente_nov.cognome AS docente_nov_cognome
FROM
	studente_per_corso_di_recupero
INNER JOIN corso_di_recupero corso_di_recupero
ON studente_per_corso_di_recupero.corso_di_recupero_id = corso_di_recupero.id
INNER JOIN materia materia
ON corso_di_recupero.materia_id = materia.id
LEFT JOIN docente docente_set
ON studente_per_corso_di_recupero.docente_voto_settembre_id = docente_set.id
LEFT JOIN docente docente_nov
ON studente_per_corso_di_recupero.docente_voto_novembre_id = docente_nov.id
WHERE
	corso_di_recupero.anno_scolastico_id = $__anno_scolastico_corrente_id
AND
	studente_per_corso_di_recupero.serve_voto is true
ORDER BY
	studente_per_corso_di_recupero.classe ASC,
	corso_di_recupero.codice ASC,
	studente_per_corso_di_recupero.cognome ASC,
	studente_per_corso_di_recupero.nome ASC;";


// prepara il file con le intestazioni
ob_clean();
$output = fopen("php://output", "w");
fputcsv($output, array('classe (anno precedente)', 'codice corso', 'cognome studente', 'nome studente', 'voto settembre', 'data settembre', 'cognome docente settembre', 'nome docente settembre', 'voto novembre', 'data novembre', 'cognome docente novembre', 'nome docente novembre', 'passato'));

foreach(dbGetAll($query) as $esame) {
    fputcsv($output, array(
        $esame['studente_per_corso_di_recupero_classe'],
        $esame['corso_di_recupero_codice'],
        $esame['studente_per_corso_di_recupero_cognome'],
        $esame['studente_per_corso_di_recupero_nome'],
        printableVoto($esame['studente_per_corso_di_recupero_voto_settembre']),
        printableDate($esame['studente_per_corso_di_recupero_data_voto_settembre']),
        $esame['docente_set_cognome'],
        $esame['docente_set_nome'],
        printableVoto($esame['studente_per_corso_di_recupero_voto_novembre']),
        printableDate($esame['studente_per_corso_di_recupero_data_voto_novembre']),
        $esame['docente_nov_cognome'],
        $esame['docente_nov_nome'],
        ($esame['studente_per_corso_di_recupero_passato'])? 'si' : 'no'
    ));
}
fclose($output);
?>