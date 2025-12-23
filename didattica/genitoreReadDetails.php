<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

if (isset($_POST['id']) && $_POST['id'] != "") {
    $genitore_id = (int)$_POST['id'];

    // Recupero genitore
    $query = "SELECT * FROM genitori WHERE id = '$genitore_id'";
    $genitore = dbGetFirst($query);

    if (!$genitore) {
        echo json_encode(['error' => 'Genitore non trovato']);
        exit;
    }

    // Recupero studenti associati (UNICA QUERY)
    $query = "
        SELECT
            s.id AS id_studente,
            s.cognome,
            s.nome,
            c.classe,
            gr.relazione
        FROM genitori_studenti gs
        JOIN studente s
            ON s.id = gs.id_studente
        JOIN studente_frequenta sf
            ON sf.id_studente = s.id
            AND sf.id_anno_scolastico = '$__anno_scolastico_corrente_id'
        JOIN classi c
            ON c.id = sf.id_classe
            AND sf.id_classe <> 0
        LEFT JOIN genitori_relazioni gr
            ON gr.id = gs.id_relazione
        WHERE gs.id_genitore = '$genitore_id'
        ORDER BY s.cognome, s.nome
    ";

    $rows = dbGetAll($query);

    $studenti = [];
    $genitoriDi = [];
    $relazioni = [];

    foreach ($rows as $r) {
        $label = $r['cognome'] . ' ' . $r['nome'] . ' (' . strtoupper($r['classe']) . ')';
        $rel = $r['relazione'] ? ucfirst($r['relazione']) : 'Nessuna';

        $studenti[] = [
            'id' => (int)$r['id_studente'],
            'label' => $label,
            'relazione' => $rel
        ];

        // compatibilità con il tuo JS attuale (array paralleli)
        $genitoriDi[] = $label;
        $relazioni[] = $rel;
    }

    if (empty($studenti)) {
        $genitoriDi = ['Nessuno'];
        $relazioni = ['Nessuna'];
        $studenti = []; // nessun figlio
    }

    // Nuovo formato consigliato
    $genitore['studenti'] = $studenti;

    // Mantengo anche i vecchi campi (così non rompi nulla finché aggiorni il JS)
    $genitore['genitori_di'] = $genitoriDi;
    $genitore['relazioni'] = $relazioni;

    echo json_encode($genitore);
}
