<?php

function geometriEnsureDefaultExams()
{
    $defaults = [
        ['CAD2D', 'CAD 2D', "Riproduzione di un elaborato progettuale in formato CAD 2D costituito da piante, prospetti e sezione completi di quote e impaginazione.", 3, 10],
        ['CAD3D', 'CAD 3D', "Riproduzione di un modello progettuale in formato CAD 3D partendo da piante, prospetti e sezione completi di quote e impaginazione.", 3, 20],
        ['BIM', 'BIM', "Riproduzione di un modello progettuale in formato Revit e gestione della modellazione in 3D.", 3, 30],
        ['CATASTO', 'CATASTO', "Conoscenze in ambito catastale, sistema catastale Trentino e Nazionale, lettura della mappa e delle visure catastali.", 4, 10],
        ['PREGEO', 'PREGEO', "Conoscenze del software Pregeo, libretto, prospetto di divisione, rilievo di campagna e produzione di documento catastale.", 4, 20],
        ['DOCFA4', 'DOCFA 4', "Riproduzione di un elaborato DOCFA partendo da una pianta CAD suddividendo l'immobile in U.I.U.", 5, 10],
        ['PLATAV', 'PLATAV', "Riproduzione di un elaborato PLATAV partendo da una pianta CAD suddividendo l'immobile in Porzioni Materiali.", 5, 20],
    ];

    foreach ($defaults as $exam) {
        [$codice, $titolo, $descrizione, $anno, $ordine] = $exam;
        dbExec("
            INSERT INTO geometri_esami (codice, titolo, descrizione, anno_corso, ordine, attivo)
            VALUES (" . dbQ($codice) . ", " . dbQ($titolo) . ", " . dbQ($descrizione) . ", " . dbI($anno) . ", " . dbI($ordine) . ", 1)
            ON DUPLICATE KEY UPDATE
                titolo = VALUES(titolo),
                descrizione = VALUES(descrizione),
                anno_corso = VALUES(anno_corso),
                ordine = VALUES(ordine),
                attivo = 1
        ");
    }
}
