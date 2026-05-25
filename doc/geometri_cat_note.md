# Modulo esami CAT / Collegio Geometri

Modulo separato dalla gestione corsi di `didattica/corsi.php`.

## Obiettivo

Gestire il ciclo pluriennale di esami CAT svolti dagli studenti delle classi terze, quarte e quinte in collaborazione con il Collegio dei Geometri.

## Ruoli

- `segreteria-didattica` / `dirigente`: configurano catalogo esami, sessioni, classi coinvolte, docenti interni e utenti esterni abilitati.
- `docente`: vede e compila solo le sessioni a cui e abbinato.
- `esterno`: vede e compila solo le sessioni a cui e abbinato.

## Concetti

- Esame: voce stabile del catalogo, associata all'anno di corso 3, 4 o 5.
- Sessione: data/aula di svolgimento di uno specifico esame in un anno scolastico.
- Classi sessione: classi che svolgono l'esame in quella sessione.
- Esiti: risultato dello studente nella sessione.
- Ciclo studente: stato complessivo dello studente nel percorso CAT, anche se ripete un anno o si ritira.

## Stati studente ciclo

- `attivo`: studente nel percorso.
- `ritirato`: non completa il ciclo per ritiro.
- `trasferito`: non completa il ciclo per trasferimento.
- `concluso`: ciclo completato.

## Stati esito

- `da_valutare`
- `superato`
- `non_superato`
- `assente`
- `ritirato`

## Prossimi passi implementativi

1. Pagina `didattica/geometri.php` con filtro anno scolastico e lista sessioni.
2. CRUD sessioni: esame, data, aula, classi, docenti, utenti esterni.
3. Maschera inserimento esiti per sessione.
4. Dashboard studente con avanzamento sugli 8-9 esami del ciclo.
5. Stampa certificato finale su template fornito.
