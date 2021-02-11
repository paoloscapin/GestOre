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
ruoloRichiesto('dirigente');

if(isset($_POST)) {
	$id = $_POST['id'];
	$tipo_attivita_id = $_POST['tipo_attivita_id'];
	$dettaglio = escapePost('dettaglio');
	$ore = $_POST['ore'];

    if ($id > 0) {
		$query = "UPDATE ore_previste_attivita SET ore_previste_tipo_attivita_id = '$tipo_attivita_id', dettaglio = '$dettaglio', ore = '$ore' WHERE id = '$id'";
		dbExec($query);
		info("aggiornato ore_previste_attivita id=$id ore_previste_tipo_attivita_id=$tipo_attivita_id, dettaglio=$dettaglio, ore=$ore");
	} else {
		$query = "INSERT INTO ore_previste_attivita(ore_previste_tipo_attivita_id, dettaglio, ore, docente_id, anno_scolastico_id) VALUES('$tipo_attivita_id', '$dettaglio', '$ore', '$__docente_id', '$__anno_scolastico_corrente_id')";
		dbExec($query);
		$id = dblastId();
		info("aggiunto ore_previste_attivita id=$id ore_previste_tipo_attivita_id=$tipo_attivita_id dettaglio=$dettaglio ore=$ore docente_id=$__docente_id");
    }
    
    // deve aggiornare i totali delle ore
    require_once '../docente/oreDovuteAggiornaDocente.php';
	orePrevisteAggiornaDocente($__docente_id);

	// le attribuite influenzano anche le ore fatte
	oreFatteAggiornaDocente($__docente_id);
}
?>