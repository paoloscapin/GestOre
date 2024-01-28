<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('dirigente');

if(isset($_POST['docente_id']) && $_POST['docente_id'] != "") {
	$docente_id = $_POST['docente_id'];
}

// quante sostituzioni sono richieste per questo docente
$sostituzioni_dovute = dbGetValue("SELECT ore_40_sostituzioni_di_ufficio FROM ore_dovute WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docente_id;");
// quante ne ha fatte fino ad ora
$sostituzioni_fatte = dbGetValue("SELECT COALESCE(SUM(ora), 0) FROM sostituzione_docente WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docente_id;");

// ne resterebbero
$sostituzioni_da_fare = $sostituzioni_dovute - $sostituzioni_fatte;
debug("sostituzioni_dovute=$sostituzioni_dovute sostituzioni_fatte=$sostituzioni_fatte sostituzioni_da_fare=$sostituzioni_da_fare");

// se ne restano > 0, le metto a zero e le tolgo di ufficio
if ($sostituzioni_da_fare > 0) {
    // ricupera l'id delle sostituzioni nelle ore_previste_tipo_attivita
    $id_sostituzioni = dbGetValue("SELECT id FROM ore_previste_tipo_attivita WHERE nome LIKE 'Altro con Studenti';");

    // azzera nelle dovute
    dbExec("UPDATE ore_dovute SET ore_40_sostituzioni_di_ufficio = $sostituzioni_fatte WHERE anno_scolastico_id = $__anno_scolastico_corrente_id AND docente_id = $docente_id;");

    // mette in negativo nelle assegnate
    dbExec("INSERT INTO `ore_previste_attivita`(`dettaglio`, `ore`, `ultima_modifica`, `ore_previste_tipo_attivita_id`, `docente_id`, `anno_scolastico_id`) VALUES ('Sostituzioni rimosse', -$sostituzioni_da_fare, now(), $id_sostituzioni, $docente_id, $__anno_scolastico_corrente_id);");
    info("azzerate le sostituzioni docente_id=$docente_id rimosse=$sostituzioni_da_fare");
}
?>
