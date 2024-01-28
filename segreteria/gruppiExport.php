<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>

<?php
require_once '../common/checkSession.php';
ruoloRichiesto('dirigente','segreteria-docenti');

if(! isset($_GET)) {
	return;
} else {
	$anno_id = $_GET['anno_id'];
}

$nome_anno_scolastico = dbGetValue("SELECT anno FROM anno_scolastico WHERE id=$anno_id");

// crea l'header per il file da scaricare
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=gruppi-'.$nome_anno_scolastico.'.csv');

// prepara il file con le intestazioni
ob_clean();
$output = fopen("php://output", "w");
fputcsv($output, array('# nome gruppo', 'commento', 'ore max', 'cognome responsabile', 'nome responsabile', 'clil', 'orientamento'));

// recupera i gruppi dell'anno specificato
$query = "	SELECT gruppo.id AS gruppo_id,
				gruppo.nome AS gruppo_nome,
				gruppo.commento AS gruppo_commento,
				gruppo.max_ore AS gruppo_max_ore,
				gruppo.clil AS gruppo_clil,
				gruppo.orientamento AS gruppo_orientamento,
				docente.nome AS docente_nome,
				docente.cognome AS docente_cognome
			FROM gruppo
            INNER JOIN docente
            ON gruppo.responsabile_docente_id = docente.id
			WHERE anno_scolastico_id = $anno_id
			";

$query .= "order by gruppo.nome";

foreach(dbGetAll($query) as $gruppo) {
    fputcsv($output, array($gruppo['gruppo_nome'], $gruppo['gruppo_commento'], $gruppo['gruppo_max_ore'], $gruppo['docente_cognome'], $gruppo['docente_nome'], ($gruppo['gruppo_clil'])? 'si' : 'no', ($gruppo['gruppo_orientamento'])? 'si' : 'no'));
}
fclose($output);

?>
