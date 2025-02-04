## Version 1.2.54 - 4 feb 2025
##### Bug Fixes
- sportelloChek: lettura data con 'now' non funzionante
- sportello per studente visualizzava gli sportelli sbagliati (mancava un giorno)

## Version 1.2.54 - 30 gen 2025
##### Improvements
- sportello: ora di scadenza iscrizioni

## Version 1.2.53 - 28 gen 2025
##### Improvements
- modulistica: se non richiede l'approvazione, inoltra la richiesta direttamente al destinatario
- lista richieste segreteria: filtro per anche chiuse, solo miei, solo aperte
- lista richieste con visualizzazione in attesa

## Version 1.2.52 - 23 gen 2025
##### Improvements
- lista delle richieste modulistica e gestione
- campo tipo 6 - calendario
- messaggio_respinta in messaggio
- bottone modulistica in header segreteria
- funzione produciTabella per email e lista dettaglio

##### Bug Fixes
- problemi con email con parentesi quadre che vengono ragguppate da gmail
- contatore di parole in sportelloImport
- rimosse dal titolo le parentesi quadre per problemi gmail
- save modulo compilato salva anche anno scolastico
- rivisti i comandi di approva

## Version 1.2.51 - 27 nov 2024
##### Improvements
- migliorata gestione moduli ed email

##### Bug Fixes
- problemi con __MinutiFunction
- sistemati escapePost mancanti

## Version 1.2.50 - 01 ott 2024
##### Improvements
- inserita gestione moduli prima versione
- opzione per correggere anche le previste con le regole
- gestione moduli sotto folder modulistica e ruolo modulistica

##### Bug Fixes
- viaggi diaria in previste

## Version 1.2.49 - 26 sep 2024
##### Bug Fixes
- fix per import corsi di recupero pnrr
- orePrevisteAggiorna controllo su previsioni invece che fatte

## Version 1.2.48 - 29 ago 2024
##### Improvements
- header dirigente e segreteria fuis
- studente import rimozione email
- attivita rimosso specchietto clil extra

##### Bug Fixes
- protezione per ore previste null se docente non ha ancora le ore
- php 8 per google client library

## Version 1.2.47 - 7 ago 2024
##### Bug Fixes
- 2 minor typo

## Version 1.2.46 - 31 lug 2024
##### Improvements
- valori recenti in testa per piano di lavoro
- anno corrente come default per piano di lavoro

##### Bug Fixes
- filtro classi in piano di lavoro
- piano di lavoro calcola e salva nome_classe in automatico

## Version 1.2.45 - 30 lug 2024
##### Improvements
- previsteList con oreFatteAggiorna
- orientamento in previsteList
- carenza delete

##### Bug Fixes
- some minor fixes

## Version 1.2.44 - 6 apr 2024
##### Improvements
- corso di recupero opzione per 'non richiesto'
- fatetListOreRimaste allineato per essere calcolato con il nuovo metodo di oreFatteAggiorna

## Version 1.2.43 - 5 feb 2024
##### Improvements
- header docente e segreteria per report sportelli
- filtro docente per report sportelli effettuati
- sostituzioni effettuate calcolate con le tabelle corrette

## Version 1.2.42 - 1 feb 2024
##### Improvements
- report degli sportelli effettuati fatto da segreteria

## Version 1.2.41 - 28 gen 2024
##### Improvements
- database inseriti in ore_previste_tipo_attivita i flag funzionali, con_studenti, clil, orientamento, aggiornamento
- aggiunto script calcolaOreDocenteEFuis.js per calcolare (riportando o no le tabelle) i totali di dovute previste e fatte
- scriptAttivita, scriptIndex e scriptPreviste adeguati di conseguenza
- rimossi riferimenti a oreDovuteAggiornaDocente.php oreFatteAggiornaDocente (e previste)
- oreFatteAggiorna.php legge tutte le tabelle necessarie per produrre i dati necessari di ore e fuis totali di un docente
- rifatta la lettura di ore fatte read cdr, sportelli, attivita, attribuite, viaggi, diaria, gruppi
- rivista anche la lettura di tutte le ore previste 
- fatteList non ricalcola il fuis docente ma chiama oreFatteAggiorna.php
- gruppo import export incluso orientamento, gruppo gestione incluso orientamento Template per import gruppi 4.0)
- GestOre.template.json aggiunto config gestioneOrientamento
- css aggiunto colore beige
- Start / Stop timer in Util per controllare i tempi impiegati

##### Bug Fixes
- attribuite read per previste

## Version 1.2.40 - 16 gen 2024

##### Improvements
- scriptAttivita.js aggiorna i valori di ore e fuis nello script
- scriptAttivita.js utilizza ora $.when per garantire l'ordine di esecuzione delle operazioni in sequenza
- readImporti.php e readSettings.php per essere usati da js come in scriptAttivita.js
- fuisAssegnatoGetImportoPerDocente.php per essere usato da js come in scriptAttivita.js
- attivita.php fatto spazio per fuis orientamento
- corsoDiRecuperoPrevisteReadRecords.php ora torna i dati calcolati oltre al pezzo di html per la tabella
- coreFatteClilReadAttivita.php ora torna i dati calcolati oltre al pezzo di html per la tabella
- oreFatteReadAttivita.php ora torna i dati calcolati oltre al pezzo di html per la tabella
- oreFatteReadAttribuite.php ora torna i dati calcolati oltre al pezzo di html per la tabella
- oreFatteReadGruppi ora torna i dati calcolati oltre al pezzo di html per la tabella
- oreFatteReadSostituzioni ora torna i dati calcolati oltre al pezzo di html per la tabella
- oreFatteReadSportelli.php ora torna i dati calcolati oltre al pezzo di html per la tabella
- oreFatteReadViaggi.php ora torna i dati calcolati oltre al pezzo di html per la tabella
- viaggioDiariaFattaReadRecords.php ora torna i dati calcolati oltre al pezzo di html per la tabella
- sportello import migliorato controllo errori
- NB: le ore previste per ora non sono considerate nel calcolo del fuis

## Version 1.2.39 - 13 gen 2024

##### Improvements
- sportello clil e orientamento

##### Bug Fixes
- minor fixes

## Version 1.2.38 - 10 dic 2023

##### Improvements
- aggiunto flag gruppo orientamento in db
- aggiunto flag funzionali e con_studenti in ore_previste_tipo_attivita
- gestione ore orientamento nelle fatte
- importo orientamanto per configurazione

## Version 1.2.37 - 19 ott 2023

##### Bug Fixes
- minor fixes

## Version 1.2.36 -  2023

##### Bug Fixes
- fix per prenotazione sportello con argomento deciso dal docente

## Version 1.2.35 -  2023

##### Bug Fixes
- corsi di recupero import var pnrr fix

## Version 1.2.34 - 18 set 2023

##### Improvements
- corsi di recupero lettere settembre con dompdf
- apertura lettere con bottone specifico

## Version 1.2.33 - 13 set 2023

##### Bug Fixes
- corsi di recupero voti aperti e chiusi fix

## Version 1.2.32 - 12 set 2023

##### Improvements
- Corsi di Recupero import tipo pnrr
##### Bug Fixes
- carenza e piano di lavoro filtro docenti con docenti vecchi

## Version 1.2.31 - 29 ago 2023

##### Improvements
- storico: ricostruiti allineati storico fuis e bonus
- utilizzo di dompdf per storico fuis e bonus

## Version 1.2.30 - 25 ago 2023

##### Improvements
- storico: aggiornato storico fuis e bonus, default anno corrente

## Version 1.2.29 - 30 mag 2023

##### Improvements
- json: sistemato config corsi_di_recupero
- gestione corsi di recupero pagati da provincia o da fuis
- importo stampabile vuoto se il valore è zero

## Version 1.2.28 - 24 mag 2023

##### Improvements
- template piano di lavoro rinominabile
- blocca template se finale e piani di lavoro se pubblicati
- duplica piano mette in draft e non template

## Version 1.2.27 - 7 mag 2023

##### Improvements
- indicazioni di studio
- dirigente abilita email carenza
- bottoni disabilitati in carenza

##### Bug Fixes
- salvataggio piano quando studente_id null

## Version 1.2.26 - 21 apr 2023

##### Improvements
- lettera carenze ed email

## Version 1.2.25 - 11 dic 2022

##### Improvements
- piano di lavoro

## Version 1.2.24 - 23 nov 2022

##### Improvements
- gestione gruppo completata
- inseriti gruppi clil con import ed export e template
- cacella argomento sportelli se cancello l'ultimo iscritto

## Version 1.2.23 - 25 ott 2022

##### Improvements
- gestione gruppo completata
- inseriti gruppi clil con import ed export e template
- cacella argomento sportelli se cancello l'ultimo iscritto

## Version 1.2.22 - 13 ott 2022

##### Improvements
- sportelli inseriti da docente
- gestione errori di cancellazione sportello
- inseriti i notify in _util

## Version 1.2.21 - 4 ott 2022

##### Improvements
- import delle ore assegnate

## Version 1.2.20 - 23 set 2022

##### Improvements
- corso di recupero Import - Studio Individuale
- corsiDiRecuperoVotoSettembreTuttiIDocenti nel json, se true i docenti possono inserire voti anche per gli studenti che non hanno fatto il corso con loro
- gruppi export ed import

## Version 1.2.19 - 11 set 2022

##### Improvements
- corso di recupero Studente aggiunto commento
- fuis assegnato tipo codice_citrix ed abilita

##### Bug Fixes
- corso di recupero serve voto era ignorato

## Version 1.2.18 - 30 ago 2022

##### Improvements
- aula in docente-Corso di Recupero

##### Bug Fixes
- email 200 caratteri in studente e utente

## Version 1.2.17 - 12 giu 2022

##### Improvements
- report sportelli in didattica

## Version 1.2.16 - 7 giu 2022

##### Improvements
- totale ore in filtro fatte e filtro previste

##### Bug Fixes
- attività fatte: sportelli visibile solo se configurato
- bonus: escape testo descrittori ed evidenze per passarlo al js

## Version 1.2.15 - 1 may 2022

##### Improvements
- bonus con valore variabile

## Version 1.2.14 - 22 gen 2022

##### Bug Fixes
- sportelli erano online sempre in vista docente
- segreteria sostituzioni read records nomi con gli apostrofi

## Version 1.2.13 - 6 gen 2022

##### Improvements
- import bonus csv con newlines

##### Bug Fixes
- decimali importi in previste e fatte

## Version 1.2.12 - 28 nov 2021

##### Improvements
- modifica attribuite con commento del dirigente
- fatte permette a dirigente di modificare attribuite
- corso di recupero in itinere

## Version 1.2.11 - 30 ott 2021

##### Improvements
- didattica impersona studente
- didattica dettaglio sportello con lista studenti
- miglioramento interfaccia

## Version 1.2.10 - 26 ott 2021

##### Improvements
- icone
- sportello firme

##### Bug Fixes
- corsi di recupero in previste

## Version 1.2.8 - 10 ott 2021

##### Improvements
- sportello import
- template xlsx per import sportello
- categoria sportello
- aggiornato sql

## Version 1.2.7 - 4 ott 2021

##### Improvements
- sportello online (flag)
- studente login separato (non utente)
- sportelloCheck per controllare se ci sono iscritti (incluso invio email)
- aggiunta cartella template per i modelli per import di docenti, studenti, gruppi e corsi di recupero

## Version 1.2.6 - 25 set 2021

##### Improvements
- corso di recupero per didattica in menu
- lettera carenze opzione senza firma docente
- import corsi di recupero senza lezioni per studio individuale (solo verifica)
- import di gruppi

## Version 1.2.5 - 8 ago 2021

##### Improvements
- allineato funzionamento storico con modifiche ultime versioni
- calcolo sostituzioni in calcolaFuisDocente()
- controllo flag rimuovi_fatte_eccedenti_previsione in calcolaFuisDocente()
- ruota il file di log al nuovo anno

## Version 1.2.4 - 9 mar 2021

##### Improvements
- aggiunta folder doc e inserito sql per setup database

## Version 1.2.3 - 11 feb 2021

##### Improvements
- aggiornata gestione CLIL in fatte per calcolo fuis
- gestione commenti dirigente nelle ore fatte
- ultimo controllo calcolato e segnalato in ore fatte

##### Bug Fixes
- controllo data sportello > oggi

## Version 1.2.2 - 4 feb 2021

##### Improvements
- opzione gestione semplificata diaria
- calcolo differenze fuis rimanente in fatte

##### Note
- necessita aggiornamento del database

## Version 1.2.1 - 30 gen 2021

##### Improvements
- aggiunto opzione accetta_funzionali_per_con_studenti default false
- aggiunto opzione segnala_fatte_eccedenti_previsione default false
- aggiunto opzione rimuovi_fatte_eccedenti_previsione default false

## Version 1.2.0 - 7 gen 2021 - Panda - Import

##### Improvements
- ore attirbuite separate, inserite in fatte e previste, con aggiornamento possibile da dirigente
- segreteria: import docenti da file excel
- dirigente: import criteri bonus da file excel
- didattica: list, gestione studenti e import da file excel
- merge gestione sportelli

## Version 1.1.2

##### Improvements
- corsi di recupero firmati default on
- corsi di recupero primo totale anche maggiore di 10 se serve

## Version 1.1.1 - 24 set 2020

##### Improvements
- gruppi modificabili da responsabile gruppo
- importi totali per dirigente sulla lista previste
- changelog (questo file)

##### Bug Fixes
- versione se non trovata
- error page

## Version 1.1.0 - 20 set 2020 - Pythia - Gestione Previste

##### Improvements
- Storico Bonus e Fuis
- Import e gestione Corsi di Recupero da segreteria
- Completa revisione delle ore previste (docente + dirigente)
- Gestione separata previsione diaria viaggi
- Corsi di Recupero ore assegnate in automatico e scelta per eccedenti le 10 ore
- Importi separati per ciascun anno sul database
- Inserito Version

## Version 1.0.0 - 13 ago 2020 - Prima release ufficiale

##### Improvements
- Anno Scolastico: aggiunto cambio anno
- Corsi di Recupero: import e gestione da segreteria