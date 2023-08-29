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