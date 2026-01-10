<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// ✅ evita che warning/HTML rompano la risposta JSON
ob_start();

require_once '../common/checkSession.php';
ruoloRichiesto('dirigente');
require_once '../common/connect.php';

// pulisci qualunque output prodotto fin qui (warning, spazi, html)
ob_clean();

header('Content-Type: application/json; charset=utf-8');

function json_out($arr)
{
    echo json_encode($arr);
    exit;
}

$anno_new = isset($_POST['anno_scolastico_id']) ? intval($_POST['anno_scolastico_id']) : 0;
$force = isset($_POST['force']) ? intval($_POST['force']) : 0;

if ($anno_new <= 0) {
    json_out(['success' => false, 'message' => 'Anno scolastico non valido']);
}

// trova anno precedente (id max < anno_new)
$anno_prev = dbGetValue("SELECT id FROM anno_scolastico WHERE id < $anno_new ORDER BY id DESC LIMIT 1;");
$anno_prev = intval($anno_prev);
$anno_prev_label = dbGetValue("SELECT anno FROM anno_scolastico WHERE id = $anno_prev");
$anno_new_label = dbGetValue("SELECT anno FROM anno_scolastico WHERE id = $anno_new");

if ($anno_prev <= 0) {
    json_out(['success' => false, 'message' => "Non esiste un anno precedente da cui copiare"]);
}

// controlla se esistono già indicatori per anno_new
$cnt = dbGetValue("SELECT COUNT(*) FROM bonus_indicatore WHERE anno_scolastico_id = $anno_new;");
$cnt = intval($cnt);

if ($cnt > 0 && $force !== 1) {
    json_out([
        'success' => false,
        'code' => 'already_exists',
        'message' => "Esistono già criteri per l'anno selezionato (indicatori: $cnt)."
    ]);
}

// ✅ facciamo tutto in transazione (se supportata)
dbExec("START TRANSACTION;");

try {
    // se force, cancella fisicamente i criteri dell'anno selezionato prima di copiare
    if ($cnt > 0 && $force === 1) {
        // prima i figli
        dbExec("DELETE FROM bonus WHERE anno_scolastico_id = $anno_new;");
        dbExec("DELETE FROM bonus_indicatore WHERE anno_scolastico_id = $anno_new;");
    }

    // Copia indicatori (solo validi)
    $sqlIndic = "
		INSERT INTO bonus_indicatore
		(valido, codice, descrizione, valore_massimo, bonus_area_id, anno_scolastico_id)
		SELECT
		  COALESCE(valido,1),
		  codice,
		  descrizione,
		  valore_massimo,
		  bonus_area_id,
		  $anno_new
		FROM bonus_indicatore
		WHERE anno_scolastico_id = $anno_prev
		  AND (valido IS NULL OR valido = 1);
	";
    dbExec($sqlIndic);

    // Copia bonus (solo validi) rimappando indicatore tramite codice + area + anno_new
    $sqlBonus = "
		INSERT INTO bonus
		(valido, codice, descrittori, evidenze, valore_previsto, bonus_indicatore_id, anno_scolastico_id)
		SELECT
		  COALESCE(b.valido,1),
		  b.codice,
		  b.descrittori,
		  b.evidenze,
		  b.valore_previsto,
		  bi_new.id,
		  $anno_new
		FROM bonus b
		JOIN bonus_indicatore bi_old
		  ON b.bonus_indicatore_id = bi_old.id
		JOIN bonus_indicatore bi_new
		  ON bi_new.codice = bi_old.codice
		 AND bi_new.bonus_area_id = bi_old.bonus_area_id
		 AND bi_new.anno_scolastico_id = $anno_new
		WHERE b.anno_scolastico_id = $anno_prev
		  AND (b.valido IS NULL OR b.valido = 1);
	";
    dbExec($sqlBonus);

    dbExec("COMMIT;");

    // ⚠️ NON chiamare info()/debug() qui se stampano output!
    json_out([
        'success' => true,
        'message' => "Copia completata: anno $anno_prev_label → anno $anno_new_label"
    ]);
} catch (Exception $e) {
    dbExec("ROLLBACK;");
    json_out(['success' => false, 'message' => 'Errore durante la copia']);
}
