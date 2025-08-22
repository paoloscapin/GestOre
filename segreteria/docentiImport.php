<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('dirigente','segreteria-didattica');

/* -------------------------------------------------------------------------- */
/*  Utility                                                                   */
/* -------------------------------------------------------------------------- */

function startsWith(string $haystack, string $needle): bool {
    return substr($haystack, 0, strlen($needle)) === $needle;
}

function erroreDiImport(string $msg): void {
    global $dataHtml, $linePos, $terminatoCorrettamente;
    $terminatoCorrettamente = false;
    warning("Errore linea $linePos: $msg");
    $dataHtml .= "<strong>Errore linea $linePos:</strong> $msg<br>";
}

function esc(string $v): string {        // alias più corto di escapeString()
    return escapeString(trim($v));
}

/* -------------------------------------------------------------------------- */
/*  Lettura input                                                             */
/* -------------------------------------------------------------------------- */

$dataHtml  = '';
$linePos   = 0;
$inseriti  = 0;
$giaPres   = 0;
$terminatoCorrettamente = true;

$src = isset($_POST['contenuto']) ? trim($_POST['contenuto']) : '';
$lines = array_filter(explode("\n", $src), 'trim');   // rimuove righe vuote

foreach ($lines as $line) {
    $linePos++;

    // salta intestazione (prima riga) o righe‑commento
    if ($linePos === 1 || startsWith($line, '#')) {
        continue;
    }

    /* ---------------------------------------------------------------------- */
    /*  Parsing riga                                                          */
    /* ---------------------------------------------------------------------- */

    $f = str_getcsv($line, ';');          // separatore “;”
    if (count($f) < 9) {
        erroreDiImport('Numero colonne insufficiente');
        continue;
    }

    $matCod   = esc($f[4]);               // MAT_COD
    $matNome  = esc($f[5]);               // MAT_NOME
    $rawCogni = $f[6];                    // DOC_COGN  (potrebbe contenere + docenti)
    $rawNomi  = $f[7];                    // DOC_NOME
    $classi_raw = array_map('trim', explode(',', esc($f[8])));// esempio: ["4CBA", "4MEA"]  // CLASSE

    /* ---------------------------------------------------------------------- */
    /*  Id materia                                                            */
    /* ---------------------------------------------------------------------- */

    $id_materia = dbGetValue("
        SELECT id
        FROM materia
        WHERE codice = '$matCod' OR nome = '$matNome'
        LIMIT 1");
    if (!$id_materia) {
        erroreDiImport("Materia non trovata: $matCod / $matNome");
        continue;
    }


    /* ---------------------------------------------------------------------- */
    /*  Gestione (co‑)docenti                                                */
    /* ---------------------------------------------------------------------- */

    $cognomi = array_map('trim', explode(',', $rawCogni));
    $nomi    = array_map('trim', explode(',', $rawNomi));

    if (count($cognomi) !== count($nomi)) {
        erroreDiImport('N. cognomi ≠ N. nomi per i docenti');
        continue;
    }

    foreach ($cognomi as $idx => $cogn) {
    $cogn = esc($cogn);
    $nome = esc($nomi[$idx]);

    // recupera id_docente
    $docente_id = dbGetValue("SELECT id FROM docente WHERE cognome = '$cogn' AND nome = '$nome'");
    if ($docente_id == null) {
        erroreDiImport("Docente non trovato: -$cogn- -$nome-");
        continue;
    }

    // ora gestiamo ogni classe separatamente
    foreach ($classi_raw as $classeSingola) {
        $classeSingola = trim($classeSingola);
        $classeSingola = preg_replace('/\s+/', '', $classeSingola); // rimuove spazi interni e laterali
        $classe_id = dbGetValue("SELECT id FROM classi WHERE classe = '$classeSingola'");
        if ($classe_id == null) {
            erroreDiImport("Classe non trovata: $classeSingola");
            continue;
        }

        // controlla se già esiste
        $esiste = dbGetFirst("SELECT * FROM docente_insegna WHERE id_docente = $docente_id AND id_materia = $id_materia AND id_classe = $classe_id");
        if ($esiste != null) {
            $giaPres++;
            info("Associazione già presente: $cogn $nome - $classeSingola");
            continue;
        }

        // INSERT
        $sql = "INSERT INTO docente_insegna(id_docente, id_materia, id_classe) VALUES ($docente_id, $id_materia, $classe_id)";
        $sqlList[] = $sql;
        $inseriti++;
    }
}
}

/* -------------------------------------------------------------------------- */
/*  Report                                                                    */
/* -------------------------------------------------------------------------- */

echo $dataHtml;
if ($giaPres) {
    echo "<strong>Record duplicati ignorati: $giaPres</strong><br>";
}
echo "<strong>Nuovi inserimenti eseguiti: $inseriti</strong>";
?>