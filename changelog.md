<!-- gestore-git-changelog-1.3.2:start -->
## Version 1.3.2 - 16 mag 2026
##### Sintesi
- Aggiornamento generato automaticamente dai commit Git successivi all'ultimo aggiornamento del changelog.
- Periodo commit: 2024-09-19 - 2026-05-16.
- Commit analizzati: 684.

- Ticket, Telegram e comunicazioni: 101 modifiche, tra cui Editor disabilitato - non compare messaggio sotto editor; Aggiornato log gmail, aggiornato cron e watch per lettura mail.
- Programmi didattici: 45 modifiche, tra cui Aggiunte classi articolate anche sui programmi iniziali; Aggiornato programma materie a nuovo formato word like.
- MasterCom, studenti e NOIRC: 90 modifiche, tra cui Aggiornato sync totale studenti usando sync singola classe; Corretto calcolo attività no irc studenti.
- Biglietti ed eventi: 11 modifiche, tra cui Da MBApp gli eventi vengono sincronizzati in Calendar quando vengono approvati; Corretto calcolo orario attività su pagina eventi.
- ATA, ferie, permessi e orario: 110 modifiche, tra cui Aggiunto filtro esteso tipo ferie su pagina permessi; Aggiunto log front end su operazioni fatte su ferie personale ATA.
- Utenti, ruoli e sessioni: 27 modifiche, tra cui La sessione viene mantenuta chuidendo la scheda di chrome, se poi ritorno al link sul sito joomla ricorda la sessione e va alla pagina richiesta direttamente; Checksession autorizza solo utenti con attivo = 1.
- Interfaccia e mobile: 36 modifiche, tra cui Aggiornati warning e corretto calcolo ore fuis attività fatte; Primo fix calcolo ore fuis.
- Manutenzione e correzioni: 255 modifiche, tra cui Passaggio a release superiore 1.3.0; Corretto format footer e doppio pulsante.

##### Dettaglio

##### 16 maggio 2026 - versioni 1.2.385 - 1.3.1
**Ticket, Telegram e comunicazioni**
- Editor disabilitato - non compare messaggio sotto editor

**Programmi didattici**
- Aggiunte classi articolate anche sui programmi iniziali
- Aggiornato programma materie a nuovo formato word like
- Sistemato import e formato programmi svolti vs minimi

**Manutenzione e correzioni**
- Passaggio a release superiore 1.3.0
- Corretto format footer e doppio pulsante
- Corretto import ora applica il format word like subito dopo import

##### 15 maggio 2026 - versione 1.2.384
**ATA, ferie, permessi e orario**
- Aggiunto filtro esteso tipo ferie su pagina permessi

##### 14 maggio 2026 - versioni 1.2.381 - 1.2.383
**Ticket, Telegram e comunicazioni**
- Aggiornato log gmail, aggiornato cron e watch per lettura mail

**Programmi didattici**
- Corretto bug, quando crei nuovo programma puoi importare subito i moduli senza salvare

**Manutenzione e correzioni**
- Sync da mbapp a google calendar completato

##### 13 maggio 2026 - versioni 1.2.370 - 1.2.380
**Ticket, Telegram e comunicazioni**
- Migliorati messaggi debug
- Migliorato testo mail in caso di approvazione parziale
- Separato invio mail da conferma ferie lato segreteria ATA

**MasterCom, studenti e NOIRC**
- Aggiornato sync totale studenti usando sync singola classe
- Corretto calcolo attività no irc studenti
- Aggiornati tag su scelta studenti con tooltip
- Corretta gestione assegnazione orario docenti noirc

**Biglietti ed eventi**
- Da MBApp gli eventi vengono sincronizzati in Calendar quando vengono approvati

**ATA, ferie, permessi e orario**
- Aggiunto log front end su operazioni fatte su ferie personale ATA

**Manutenzione e correzioni**
- Aggiunte funzionalità drive e caricamento file log su Drive
- Corretto sync di tutte le classi

##### 12 maggio 2026 - versione 1.2.369
**Manutenzione e correzioni**
- Aggiunto sync da Google Calendar a MBApp

##### 11 maggio 2026 - versioni 1.2.355 - 1.2.368
**Programmi didattici**
- Aggiunte classi articolate - integrate nei programmi svolti

**MasterCom, studenti e NOIRC**
- Aggiornate tabelle db per noirc
- Aggiornate chiamate mastercom noirc e assenze
- Aggiornata grafica pagina assegnazione docenti noirc
- Corretto import noirc da mastercom
- Il token mastercom viene salvato in sessione invece che richiederlo ogni volta
- Aggiornata pagina gestione aule noirc
- Altre 1 modifiche minori nella stessa area.

**ATA, ferie, permessi e orario**
- Lato segrata aggiornati permessi con ora rientro non obbligatoria
- Aggiornata pagina appello classi
- Permessi ata, visita medica e permesso breve ora rientro non obbligatoria

**Manutenzione e correzioni**
- Incluso in commit precedente
- Corretta gestione modifica assenze studente
- Aggiunto snapshot classi-docenti

##### 10 maggio 2026 - versioni 1.2.349 - 1.2.354
**Ticket, Telegram e comunicazioni**
- Messaggi successivi su nuovo ticket vengono accodati prima della presa in carico

**Programmi didattici**
- Programmi minimi aggiornati con editor word-like
- Aggiornati programmi iniziaili con editor word-like
- Corretto bug sessione quando impersono docente e torno admin restava un residuo nei programmi svolti
- Minor fix stampa programmi svolti

**Interfaccia e mobile**
- Aggiornati warning e corretto calcolo ore fuis attività fatte

##### 8 maggio 2026 - versioni 1.2.344 - 1.2.348
**Programmi didattici**
- Corretti problemi formattazione testo programmi svolti
- Aggiornata formatatzione e grafica programmi svolti classi quinte secondo nuovo format

**ATA, ferie, permessi e orario**
- Ora anche lato personale ata vede le modifiche rispetto alla prima richiesta ferie estive inviata
- Ferie estive ATA, una richiesta inviata può essere modificata mantenendo lo storico lato segreteria ATA

**Interfaccia e mobile**
- Primo fix calcolo ore fuis

##### 7 maggio 2026 - versione 1.2.343
**Programmi didattici**
- Editor programmi svolti reso compatibile con formato Word e copia incolla diretto

##### 6 maggio 2026 - versioni 1.2.341 - 1.2.342
**Manutenzione e correzioni**
- Aggiunta funzione base notifihce con file di test
- Aggiunti pacchetti composer per gestione noticihe push

##### 5 maggio 2026 - versioni 1.2.336 - 1.2.340
**Ticket, Telegram e comunicazioni**
- Aggiornato comportamento ticket - risposta su ticket chiuso da la possibilità di riaprire ticket lato admin
- Messaggio chiusura ticket ora riporta il tipo di utente specifico

**Biglietti ed eventi**
- Corretto calcolo orario attività su pagina eventi

**ATA, ferie, permessi e orario**
- Se viene ripristinata una sostituzione annullata ora non da errore ma la ripristina

**Manutenzione e correzioni**
- Ora importa sostituzioni anche docenti con doppio nome non uguali a nome gestore

##### 4 maggio 2026 - versioni 1.2.331 - 1.2.335
**Ticket, Telegram e comunicazioni**
- Aggiornata lettura mail ticket, se è una mail di inoltre legge anche la mail inoltrata
- Aggiunta guida gestione notifiche push gmail
- Gestione ticket gestore , passato da polling ogni minuto a notifiy push gmail

**Programmi didattici**
- Programmi svolti import - se non c'è il programma del docente propone i programmi inseriti stessa materia stessa classi del docente che risulta

**Utenti, ruoli e sessioni**
- La sessione viene mantenuta chuidendo la scheda di chrome, se poi ritorno al link sul sito joomla ricorda la sessione e va alla pagina richiesta direttamente

##### 3 maggio 2026 - versioni 1.2.328 - 1.2.330
**Ticket, Telegram e comunicazioni**
- Aggiornata creazione newsletter da chengelog

**Programmi didattici**
- Aggiunta stampa programma singolo docente su classi quinte

**ATA, ferie, permessi e orario**
- Corretto errore sintassi, migliorata forma anteprima e pdf in accordo con formato word per le classi quinte

##### 2 maggio 2026 - versioni 1.2.325 - 1.2.327
**Ticket, Telegram e comunicazioni**
- Migliorata gestione messaggi telegram ticket

**Programmi didattici**
- Migliorata gestione con live preview dei programmi iniziali
- Aggiunta live preview programmi svolti e guida utente laterale

##### 30 aprile 2026 - versioni 1.2.321 - 1.2.324
**Ticket, Telegram e comunicazioni**
- Aggiunta gestione ticket via mail per docenti
- Aggiunto accesso a mail gestore per invio e lettura mail
- Migliorato testo messaggi telegram admin per import

**MasterCom, studenti e NOIRC**
- Corretti bug confronto studenti e genitori

##### 29 aprile 2026 - versioni 1.2.312 - 1.2.320
**Programmi didattici**
- Aggiunto settings json per visibilità programmi svolti al coordinatore
- Minor bug programmi svolti
- Programmi svolti, spostate sezioni metodologie fuori dai moduli
- Corretta generazione e stampa programma svolto quinte

**MasterCom, studenti e NOIRC**
- Corretta visibilità pulsante biglietti da telefono lato studenti
- Gestione base noirc mastercom

**ATA, ferie, permessi e orario**
- Corretto errore calcolo export ferie, rimosso colore intensità permessi ferie, aggiungto js no cache
- Rimosso colore per celle con tanti permessi - reso caricamento js no cache

**Manutenzione e correzioni**
- Caricamento js con datetime per problemi cache

##### 28 aprile 2026 - versioni 1.2.300 - 1.2.311
**Ticket, Telegram e comunicazioni**
- Migliorata visibilità ticket lato utente e corretta gestione orario eventi

**MasterCom, studenti e NOIRC**
- Introdotta gestione noirc
- Corretto aggiornamento noirc studenti
- Aggiunte nuove funzioni integrazione con mastercom
- Completato allineamento dati mastercom - gestore
- Sql creazione tabelle gestione dati da mastercom
- Aggiunta autenticazione docente gestione MasterCom
- Altre 2 modifiche minori nella stessa area.

**Biglietti ed eventi**
- Corretta visualizzazione prenotazioni biglietti
- Minor fix gestione biglietti eventi

**Manutenzione e correzioni**
- Modificati dati visibile singolo evento attivo

##### 27 aprile 2026 - versioni 1.2.295 - 1.2.299
**Programmi didattici**
- Aggiunto setting json per abilitare modifica programmi a coordinatore dipartimento
- Aggiornata sezione obiettivi minimi - coordinatore può modificare i programmi
- Docente coordinatore non può modificare programmia altri docenti
- Aggiornati programmi svolti, aggiunta apposita logica per classi quinte

**Utenti, ruoli e sessioni**
- Checksession autorizza solo utenti con attivo = 1

##### 23 aprile 2026 - versioni 1.2.283 - 1.2.294
**MasterCom, studenti e NOIRC**
- Migliorata UI pagina CLASSE e DOCENTE

**ATA, ferie, permessi e orario**
- Migliorate informazioni riportate nel riepilogo permessi personale ata
- Sistemata possibilità sui permessi di avere piu righe in un unico permesso
- Migliorata ricerca dentro orario aule classi e docenti
- Aggiornata gestione orario lato segreteria ATA
- Migliorata gestione orario permessi lato personale ata
- Migliorata UI pagina AULE
- Altre 1 modifiche minori nella stessa area.

**Utenti, ruoli e sessioni**
- Aggiornati problemi sessione utente quando admin assume ruolo
- Admin interpreta docente, corretta gestione sessione pagine docente
- Corretta gestione sessione scaduta

**Manutenzione e correzioni**
- Lato studente serale, gli sportelli apre di default la categoria preora

##### 21 aprile 2026 - versione 1.2.282
**ATA, ferie, permessi e orario**
- Cambiato formato orario permessi solo a cifre no orologio grafico

##### 20 aprile 2026 - versioni 1.2.275 - 1.2.281
**Ticket, Telegram e comunicazioni**
- Corretto formato ora sportello delle mail

**MasterCom, studenti e NOIRC**
- Corretto calcolo orario permessi genitori

**Biglietti ed eventi**
- Miglioramento gestione biglietti eventi

**ATA, ferie, permessi e orario**
- Migliorata gestione ferie lato segrata

**Manutenzione e correzioni**
- Aggiornato comportamento pulsanti cruscotto, aggiunto pulsanti registrato - da registrare
- Modificato cruscotto ora sono filtri sommativi
- Corretto formato ora legale

##### 19 aprile 2026 - versioni 1.2.264 - 1.2.274
**Ticket, Telegram e comunicazioni**
- Integrata pagina tickets in header admin
- Aggiunto tickets in gestore su menu admin - rivisto menu admin
- Aggiunta gestione ticket trentino volley
- Aggiornato send mail con ok di ritorno ed allega multi allegato

**Biglietti ed eventi**
- Migliorata visibilità eventi dai vari utenti anche con impersona
- Aggiunta gestione eventi lato admin, e prenotazione lato utenti

**ATA, ferie, permessi e orario**
- Sistemata header docente

**Utenti, ruoli e sessioni**
- Migliorate funzioni agisci come

**Interfaccia e mobile**
- Migliorato header didattica

**Manutenzione e correzioni**
- Aggiornato algoritmo e mappe per tutte le tribune del palazzetto
- Aggiunti pacchetti con composer

##### 17 aprile 2026 - versioni 1.2.262 - 1.2.263
**Utenti, ruoli e sessioni**
- Corretta visibilità nome docente modalità admin ore previste e fatte

**Manutenzione e correzioni**
- Corretto nome aula maiuscolo, nomi docenti a capo

##### 16 aprile 2026 - versioni 1.2.255 - 1.2.261
**Biglietti ed eventi**
- Pagina default eventi mobile
- Creata versione mobile orario ed eventi

**ATA, ferie, permessi e orario**
- Migliorata grafica e layout orario mobile
- Abilitato pulsante orario personale ata
- Aggiunto pulsante permessi anche dentro il modale del permesso

**Interfaccia e mobile**
- Corretti colori mobile

**Manutenzione e correzioni**
- Bug fix flag permesso registrato ora viene salvato

##### 14 aprile 2026 - versioni 1.2.251 - 1.2.254
**ATA, ferie, permessi e orario**
- Aggiunto flag reegistrazione permessi lato segreteria

**Manutenzione e correzioni**
- Reso dinamico caricamento css
- Aggiunto dettaglio permesso sotto al tipo
- Rimosso badge Bozza lato segreteria

##### 12 aprile 2026 - versioni 1.2.237 - 1.2.250
**Ticket, Telegram e comunicazioni**
- Rimossa stampa messaggi debug
- Correzioni logica invio mail
- Sistemata invio mail lato segreteria

**ATA, ferie, permessi e orario**
- Minor bug - aggiunto nome approvatore permessi
- Aggiornato export in formato xls personale ata ed aggiornata dashboard con badge parziali
- Aggiornato e migliorata stampa pdf permessi
- Messo link diverso per help segreteria ATA
- Aggiornata visibilita versione come tooltip
- Corretta visibilità approvazione giorni di ferie
- Altre 1 modifiche minori nella stessa area.

**Utenti, ruoli e sessioni**
- Aggiunto ruolo ras

**Manutenzione e correzioni**
- Migliorato css tabella
- Aggiunti export csv lato segreteria
- Rimosso codice non piu necessario

##### 11 aprile 2026 - versioni 1.2.227 - 1.2.236
**Ticket, Telegram e comunicazioni**
- Sistemato sistema invio mail lato personale
- Lato utente invio mail quando fa richiesta permesso

**ATA, ferie, permessi e orario**
- Migliorata grafica tooltip calendario
- Lato utente aggiunta possibilità di una nuova richiesta di ferie
- Creata dashboard ferie e corretti bug
- Migliorata logica approvazione ferie
- Aggiunto file excel esempio import personale ATA

**Manutenzione e correzioni**
- Minor bug fix
- Corretto filtro tabella
- Aggiunta funzione import personale

##### 10 aprile 2026 - versioni 1.2.220 - 1.2.226
**ATA, ferie, permessi e orario**
- Aggiunto export personale ata
- Lato segreteria ata migliorata gestione permessi
- Richiesta ferie migliorata, aggiunta rimetti in bozza
- Migliorati permessi - pulsanti corretti - rimessa in bozza
- Migliorata grafica ferie - colori
- Aggiornati permessi e ferie lato personale ATA sia telefono che desktop
- Altre 1 modifiche minori nella stessa area.

##### 3 aprile 2026 - versioni 1.2.214 - 1.2.219
**Ticket, Telegram e comunicazioni**
- Aggiunte ed aggiornata pagina sostituzione in miniapp telegram
- Spostati messaggi telegram su log telegram
- Conclusa app telegram con sistema ticketing
- Aggiunto file telegram log
- Aggiunto log Telegram

**Manutenzione e correzioni**
- Rimosso file non piu usato

##### 2 aprile 2026 - versioni 1.2.208 - 1.2.213
**Ticket, Telegram e comunicazioni**
- Creata miniapp telegram GestOre
- Aggiornato formato ora nel nome del ticket
- Aggiunti parametri telegram
- Aggiornato e completato sistema di ticketing telegram

**Manutenzione e correzioni**
- Aggiunto log extra
- Script che cancella i topic piu vecchi di tot giorni

##### 1 aprile 2026 - versione 1.2.207
**Utenti, ruoli e sessioni**
- Risolto problema logout sessione dando un nome alla sessione

##### 31 marzo 2026 - versioni 1.2.201 - 1.2.206
**Ticket, Telegram e comunicazioni**
- Migliorata gestione ticket ora accetta anche file
- Creato gruppo admin GestOre, aggiunto sistema ticketing, notifiche sostituzioni ora a gruppo admin con abilitazione
- Inseriti i messaggi di scarto dell'import gestore nel riepilogo telegram
- Corretto formato data nelle mail delle sostituzioni

**ATA, ferie, permessi e orario**
- Lato genitore permessi corretto bug ora rientro null

**Manutenzione e correzioni**
- Aggiornato loggin - nome dei file ed aggiunto rotatelog su import sostituzioni

##### 30 marzo 2026 - versioni 1.2.190 - 1.2.200
**MasterCom, studenti e NOIRC**
- Aggiornata sicurezza ora c'è un controllo che i dati degli studenti richiesti corrispondano al genitore che ha fanno l'accesso, quindi non solo fidandosi del parametro passato alla POST

**ATA, ferie, permessi e orario**
- Corretto login personale ata
- Permessi lato personale ATA aggiornato codice
- Migliorata sicurezza lato studente e genitore per stampa carenza
- Caricata in git cartella error
- Migliorata sicurezza lato studente per carenze e sportelli
- Sportelli lato studente migliorata sicurezza
- Altre 2 modifiche minori nella stessa area.

**Manutenzione e correzioni**
- Corretto import che annulla e modifica solo le sostituzioni di oggi
- Corretto metodo esposizione parametri da GET a POST

##### 28 marzo 2026 - versione 1.2.189
**Manutenzione e correzioni**
- Correzione sostituzioni annullate o modificate

##### 27 marzo 2026 - versioni 1.2.184 - 1.2.188
**Ticket, Telegram e comunicazioni**
- Aggiornato import rimosso testing nelle mail

**MasterCom, studenti e NOIRC**
- Aggiornato agent, importa sostituzioni anche se non c'è abbinata la classe

**ATA, ferie, permessi e orario**
- Corretto login profilo segreteria ata

**Manutenzione e correzioni**
- Lato docente aggiunto pulsante filtra solo le sue sostituzioni
- Bug fix query

##### 24 marzo 2026 - versione 1.2.183
**Manutenzione e correzioni**
- Minor fix bug

##### 23 marzo 2026 - versioni 1.2.180 - 1.2.182
**Ticket, Telegram e comunicazioni**
- Completata integrazione invio messaggi Telegram per sostituzioni
- Impostata variabile dinamica su ora chiusura sportelli nella mail

**Manutenzione e correzioni**
- Rimosso file non necessario

##### 22 marzo 2026 - versioni 1.2.174 - 1.2.179
**ATA, ferie, permessi e orario**
- Aggiunte sostituzioni pagina orario
- Aggiornata list gitignore

**Manutenzione e correzioni**
- Aggiunto logging su import sostituzioni
- Allungato timeout import
- Minor bug fixes
- Creato EDT agent per importare le sostituzioni da pdf EDT

##### 19 marzo 2026 - versioni 1.2.162 - 1.2.173
**Ticket, Telegram e comunicazioni**
- La mail di notifica assenza agli studenti arriva solo se lo sportello è stato svolto

**MasterCom, studenti e NOIRC**
- Aggiornata assegnazione sportelli, il docente può cambiare data e classe, previo setting file json

**ATA, ferie, permessi e orario**
- Creata pagina segreteria ata
- Creata pagina personale ata
- Aggiunto ruolo personale ata e portineria

**Utenti, ruoli e sessioni**
- Aggiunto ruolo portineria in checksession

**Interfaccia e mobile**
- Corretto header quando si vede la pagina previste docente lato dirigente
- Aggiornati pulsanti su header vari

**Manutenzione e correzioni**
- Aggiunto totali per capitolo su tabella fatte, sistemato simbolo EURO
- Aggiunto totali per capitolo su tabella previste
- Corretto titolo pagina segreteria didattica
- Corretta denominazione sportello su MBApp

##### 18 marzo 2026 - versione 1.2.161
**ATA, ferie, permessi e orario**
- Orario MBAPP aggiunta sezione

##### 22 febbraio 2026 - versione 1.2.160
**ATA, ferie, permessi e orario**
- Aggiornamento funzioni orario

##### 19 febbraio 2026 - versione 1.2.159
**Manutenzione e correzioni**
- Corretta gestione aule non piu disponibili su sportelli

##### 13 febbraio 2026 - versioni 1.2.147 - 1.2.158
**Ticket, Telegram e comunicazioni**
- Aggiornato template mail inviate per sportelli lato studente
- Aggiunto log CRON e aggiornato invio mail

**ATA, ferie, permessi e orario**
- Corretta funziona salvataggio sportello lato docente
- Aggiornato stile header ata e admin
- Aggiunti nuovi settings cron e ata

**Interfaccia e mobile**
- Corretti require

**Manutenzione e correzioni**
- Corretta funziona cancella prenotazione MBApp
- Corretto funzionamento invio promemoria docente
- Aggiunte nuove funzioni
- Corretto settings MBApp
- Corretto motivo sportello
- In caso di assenza docente lo sportello viene annullato, via cron

##### 6 febbraio 2026 - versioni 1.2.145 - 1.2.146
**Manutenzione e correzioni**
- Corretta gestione apici bonus lato dirigente
- Corretta selezione multipla bonus lato docente

##### 5 febbraio 2026 - versioni 1.2.143 - 1.2.144
**ATA, ferie, permessi e orario**
- Corretto salvatagglio nome sportello in MBApp

**Manutenzione e correzioni**
- Corretto filtro sportelli

##### 4 febbraio 2026 - versioni 1.2.141 - 1.2.142
**Manutenzione e correzioni**
- Quando il docente cancella lo sportello ne viene creato uno gemello in bozza 14gg dopo
- Corretti bug lato studente iscrizione sportelli

##### 3 febbraio 2026 - versioni 1.2.139 - 1.2.140
**Interfaccia e mobile**
- Corretta visibilità dati su sportelli cancellati lato studente

**Manutenzione e correzioni**
- Sistemato bug cancellazione sportello lato docente

##### 2 febbraio 2026 - versione 1.2.138
**Manutenzione e correzioni**
- Minor correction

##### 1 febbraio 2026 - versioni 1.2.131 - 1.2.137
**Ticket, Telegram e comunicazioni**
- Aggiornata grafica mail notifica docente ore previste
- Aggiornata grafica mail sportelli
- Aggiunto file comune per invio mail con grafica uguale
- Aggiornata grafica mail cancellazione sportello docente - correggo bug su mail genitori
- Aggiunto messaggio di conferma su aula prima di creare sportello lato docente

**Manutenzione e correzioni**
- Adesso gli sportelli deserti vengono rimessi in bozza 14 gg in avanti, stessa cosa gli sportelli in bozza passati
- Corretto errore MBAPP su creazione nuovo sportello

##### 29 gennaio 2026 - versione 1.2.130
**Manutenzione e correzioni**
- Aggiunto giorno della settimana sulle date degli sportelli

##### 28 gennaio 2026 - versione 1.2.129
**Programmi didattici**
- Aggiunta possibilità modifica programmi al coordinatore di dipartimento

##### 26 gennaio 2026 - versione 1.2.128
**Manutenzione e correzioni**
- Correzione titolo sportello

##### 25 gennaio 2026 - versioni 1.2.123 - 1.2.127
**Ticket, Telegram e comunicazioni**
- Aggiunto invio mail annullamento prenotazione aula sportello su collaboratori

**Manutenzione e correzioni**
- Aggiornato template promemoria sportello lato studente
- Modale ora si chiude dove save
- Completato aggiornamento gestione sportelli lato didattica
- Corretto sportello delete

##### 22 gennaio 2026 - versioni 1.2.119 - 1.2.121
**Interfaccia e mobile**
- Aggiornato stile header per evitare sovrapposizioni dei menu su schermi piccoli

**Manutenzione e correzioni**
- Corretta query su campo attivo
- Corretto calcolo numero posti disponibili negativi

##### 20 gennaio 2026 - versione 1.2.118
**Manutenzione e correzioni**
- Lo sportello in bozza lato docente ora può essere impostato a due ore, e viene diviso automaticamente

##### 19 gennaio 2026 - versioni 1.2.116 - 1.2.117
**Ticket, Telegram e comunicazioni**
- Aggiornata modalità prenotazione sportelli con invio mail lato docente - corretti bug
- Migliorata grafica email sportelli lato docente

##### 17 gennaio 2026 - versioni 1.2.112 - 1.2.115
**ATA, ferie, permessi e orario**
- Ora gli sportelli in bozza possono essere assegnati dai docenti stessi, scegliendo l'aula che viene prenotata su MBApp

**Manutenzione e correzioni**
- Prenotazione aula su MBApp
- Connessione DB MBApp
- Verifica nel portale MBApp quali aule sono libere per una prenotazione

##### 15 gennaio 2026 - versioni 1.2.108 - 1.2.111
**MasterCom, studenti e NOIRC**
- Aggiunti sportelli-bozza non visibili a genitori e studenti

**ATA, ferie, permessi e orario**
- Aggiornata assegnazione sportello bozza al docente

**Interfaccia e mobile**
- Aggiunta visibilità sportelli-bozza docenti

**Manutenzione e correzioni**
- Aggiunti sportelli-bozza lato didattica

##### 14 gennaio 2026 - versione 1.2.107
**Utenti, ruoli e sessioni**
- Corretta visibilità pulsante esame seconda sessione

##### 11 gennaio 2026 - versioni 1.2.102 - 1.2.106
**Ticket, Telegram e comunicazioni**
- Migliorati messaggi interfaccia nel caso di mail errata

**Utenti, ruoli e sessioni**
- Aggiunti corsi per utenti esterni
- Aggiunta sezione utente esterno
- Aggiunta sezione utenti esterni

**Manutenzione e correzioni**
- Login genitore viene registrato datetime e IP

##### 10 gennaio 2026 - versioni 1.2.85 - 1.2.101
**Ticket, Telegram e comunicazioni**
- Aggiunti messaggi debug sessione - corretti redirect con sessione scaduta

**MasterCom, studenti e NOIRC**
- Aggiunta login google anche ai genitori
- Nuovo filtro sportelli lato genitore in base alla classe dello studente
- Nuovo filtro sportelli lato studente in base alla classe dello studente
- Sportelli, miglioramento logica filtro per gruppo classe lato docente e didattica

**ATA, ferie, permessi e orario**
- Rinnovata grafica index.php
- Aggiornata intestazione copyright su tutti i file modificati finora

**Utenti, ruoli e sessioni**
- Corretta posizione ruolorichiesto per evitare blank page
- Corretti calcoli ed aggiunta gestione sessione

**Interfaccia e mobile**
- Corretta visibilità header

**Manutenzione e correzioni**
- Correzioni su grafica login
- Sportelli lato docente, aggiunta colonna nome docente, corretta non modifica sportelli non di proprietà
- Corretti calcoli ed aggiunto simboli euro
- Corretto formato cifre e simbolo euro
- Rimosso checksession non necessario
- Corretta icona login page

##### 8 gennaio 2026 - versioni 1.2.80 - 1.2.84
**MasterCom, studenti e NOIRC**
- Correzione bug aggiunta studenti a nuovo corso

**ATA, ferie, permessi e orario**
- Migliorata gestione scadenza sessione

**Utenti, ruoli e sessioni**
- Sistemati problemi sessione ed utente con ore previste Dirigente

**Manutenzione e correzioni**
- Aggiunta funzione duplica sportello, migliorate larghezze colonne sportelli
- Corretto duplicazione corsi

##### 7 gennaio 2026 - versioni 1.2.77 - 1.2.79
**Utenti, ruoli e sessioni**
- Sostituito termine tentativo con sessione

**Manutenzione e correzioni**
- Ultime correzione su gestione firme esami
- Sistemati numerosi bug su gestione corsi carenze ed itinere

##### 5 gennaio 2026 - versione 1.2.76
**Manutenzione e correzioni**
- Estesa gestione dei corsi a più docenti, ognuno con la propria firma

##### 4 gennaio 2026 - versioni 1.2.70 - 1.2.75
**ATA, ferie, permessi e orario**
- Aggiornata intestazione file
- Corretta ed ampliata stampa storico bonus
- Corretto salvataggio e modifica indicatori

**Interfaccia e mobile**
- Corretta visibilità allegati bonus lato dirigente

**Manutenzione e correzioni**
- Aggiunta stampa PDF ed export CSV dei criteri lato dirigente
- Completato bonus lato docente con aggiunta del caricamento di allegati

##### 3 gennaio 2026 - versioni 1.2.67 - 1.2.69
**ATA, ferie, permessi e orario**
- Completata gestione bonus lato dirigente

**Manutenzione e correzioni**
- Aggiornato bonus lato docenti con nuove modalità
- Completato lato dirigente aggiunta anno scolastico per il bonus
- Aggiunta selezione anno su bonus docenti lato dirigente

##### 30 dicembre 2025 - versione 1.2.67
**Manutenzione e correzioni**
- Aggiunta stampa carenze incomplete sia csv che pdf

##### 29 dicembre 2025 - versioni 1.2.64 - 1.2.66
**ATA, ferie, permessi e orario**
- Aggiornata visibilità carenze anche lato genitore
- Aggiornata visibilità esito carenze lato studente
- Corretta ed aggiornata struttura gestione corsi carenze e secondo tentativo lato didattica

##### 24 dicembre 2025 - versioni 1.2.58 - 1.2.63
**Programmi didattici**
- Corretto invio sollecito programmi iniziaili ai docenti
- Migliorate regole formattazione testo programmi materie

**MasterCom, studenti e NOIRC**
- Sportelli lato docente, aggiunti filtri categoria , materia e classe

**Manutenzione e correzioni**
- Lato didattica corretta categoria iniziale sportelli su sportelli didattici
- Ora il docente può vedere gli sportelli di tutti i docenti
- Dettaglio studente i campi vengono svuotati all'inizio per evitare falsi valori rimasti in memoria

##### 23 dicembre 2025 - versione 1.2.57
**MasterCom, studenti e NOIRC**
- Corretta visibilità figli nella pagina genitori

##### 18 dicembre 2025 - versioni 1.2.50 - 1.2.56
**MasterCom, studenti e NOIRC**
- Migliorata query elenco genitori
- Cancellazione studente, ora anche i genitori vengono correttamente disabilitati

**Manutenzione e correzioni**
- Feature: ora da dettaglio genitore, se clicco su nome studente passo al dettaglio studente
- Feature: dal dettaglio studente posso collegare un genitore esistente
- Feature: Dal dettaglio genitore ora posso collegare uno studente esistente
- Feature: da scheda studente posso passare direttamente a scheda genitore
- Aggiunto incremento versione automatico

##### 16 dicembre 2025 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Ora la data dei permessi salta il sabato e la domenica

**Utenti, ruoli e sessioni**
- Lato admin ora posso cancellare uno studente iscritto ad uno sportello

**Manutenzione e correzioni**
- Ora posso duplicare un corso esistente

##### 15 dicembre 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Correzioni varie

##### 31 ottobre 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Minor bugs

##### 20 ottobre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Corretto ricerca classe in sql

**Interfaccia e mobile**
- Corretto percorsi file negli header

**Manutenzione e correzioni**
- Corretto stampa carenza con doppio livello liste puntate
- Ora carenze è disabilitato all'inizio

##### 15 ottobre 2025 - versione 1.2.48
**Programmi didattici**
- Aggiunti Programmi Iniziali lato didattica e docenti

**Manutenzione e correzioni**
- Aggiornato la stampa con l'ordine del numero del modulo

##### 14 ottobre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Corretto filtro sportelli lato mobile studenti

**ATA, ferie, permessi e orario**
- Migliorata conferma permessi lato didattica

##### 13 ottobre 2025 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Corretto codice invio mail ai genitori per sportelli

**Interfaccia e mobile**
- Corretta visibilità colonne tabella

**Manutenzione e correzioni**
- Corretto riferimento a studente_id

##### 11 ottobre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Sistemati errori sportelli studenti e genitori

**Interfaccia e mobile**
- Corretto require file errore

**Manutenzione e correzioni**
- Migliorato aspetto modale sportello
- Corretta visualizzazione dettaglio sportelli lato didattica
- Correzioni su import sportelli
- Aggiornato sportelli lato docente
- Completato aggiornamento sportelli lato studente

##### 10 ottobre 2025 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiunta funzione send mail con CC
- Aggiornata data mail promemoria al docente

**MasterCom, studenti e NOIRC**
- Correzioni sportelli lato studenti

**ATA, ferie, permessi e orario**
- Corretto refuso in query permessi

**Manutenzione e correzioni**
- Lavori in corso su sportelli per migliorare codice
- Correzioni su sportelli lato genitore
- Aggiornato codice invio promemoria sportello docente
- Corretta gestione apostrofo nella materie quando cancello sportello

##### 8 ottobre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Lato genitore, si vedevano gli studenti che non frequentano più

**Utenti, ruoli e sessioni**
- Da admin ora il logout ti fa uscire non andare alla pagina admin
- Aggiunto studente id sul nome utente in alto a destra

**Manutenzione e correzioni**
- Logout da impersona chiude la finestra
- Aggiunta voce studente esterno al posto di docente quando la carenza è di uno studente trasferito

##### 4 ottobre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiunto secondo tentativo lato genitori e studenti

**Manutenzione e correzioni**
- Aggiunto secondo tentativo esami corsi

##### 3 ottobre 2025 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Adesso i corsi hanno data-ora inizio e data-ora fine per ogni lezione
- Modificato checksession per maggiore durata sessione

**Manutenzione e correzioni**
- Gli esami dei corsi ora hanno un inizio ed una fine

##### 1 ottobre 2025 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Corretto calcolo classe nell'invio mail cancellazione sportello

**Programmi didattici**
- Corretta visibilità programmi svolti
- Aggiornata visibilità programmi svolti

**MasterCom, studenti e NOIRC**
- Aggiornata visibilità esito carenze lato genitori

**ATA, ferie, permessi e orario**
- La forma esame se non è compilata completamente non te la lascia salvare

**Interfaccia e mobile**
- Visibilità esito esami lato studente
- Corretta visibilità carenze lato studente desktop
- Aggiunta visibilità risultato carenze lato studente

**Manutenzione e correzioni**
- Sempre carenze lato genitore
- Sempre firma esame docente
- Aggiunta firma docente su esami
- Corretta cancellazione di una carenza esistente
- Aggiunte opzioni mancanti nel template
- Aggiunti corsi in itinere

##### 24 settembre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Corretto calcolo classe studente

**Manutenzione e correzioni**
- Corretto bug nome docente con apostrofo

##### 23 settembre 2025 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiornato invio mail con mail google di dominio

**MasterCom, studenti e NOIRC**
- Sportelli studenti visibili solo utente test

**Interfaccia e mobile**
- Rimosso sportelli da header segreteria

##### 21 settembre 2025 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiunto invio mail esiti ai coordinatori - e lista esami incompleto per segreteria

##### 20 settembre 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Aggiunta funzione esporta esito esami
- Aggiunto componente phpSpreadsheet

##### 14 settembre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Key mastercom
- Primi test con mastercom

**Manutenzione e correzioni**
- Aggiunta sezione esami corsi carenze

##### 13 settembre 2025 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Completata gestione permessi lato segreteria didattica

**Manutenzione e correzioni**
- Corretto problema logout

##### 11 settembre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Sistemata visibilità menu genitori con json

**ATA, ferie, permessi e orario**
- Inseriti file permessi lato didattica
- Sistemato permessi.js lato genitore
- Sistemati permessi lato genitore

**Interfaccia e mobile**
- Corretto menu header didattica

**Manutenzione e correzioni**
- Rimossi console log
- Nuovi config nel json

##### 10 settembre 2025 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Corretta funziona modifica data corso

##### 6 settembre 2025 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Migliorata pulizia sessione con logout

**Utenti, ruoli e sessioni**
- Aggiunto CF studente in sessione
- Correzione ruolo con impersona
- Corretto logout in base al ruolo impersonato
- Aggiunta funzione impersonaRuolo

**Manutenzione e correzioni**
- Pulsante logout adeguato ad impersona
- Corretta visibili con impersona docente
- Corretto login genitore senza figli
- Aggiunto campo codice fiscale mancante

##### 4 settembre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiornato figli genitori, visibili solo se figlio è attivo

**ATA, ferie, permessi e orario**
- Corretta query salvataggio modifica studente

**Manutenzione e correzioni**
- Se genitore non ha figli al login viene dato errore

##### 2 settembre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Corretto inserimento manuale nuovi studenti

**Manutenzione e correzioni**
- Correzioni minori su inserimento studente
- Ora studente ha campo Esterno per quando si inserisce uno studente trasferito
- Corretta aggiunta manuale carenze

##### 31 agosto 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Aggiunta firma lezione corso
- Aggiunto config json per abilitare modifica corso ai docenti
- Cambiato nome file
- Aggiornati corsi, ora funziona, possibili miglioramenti ancora

##### 28 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Correzione carenze genitori
- Corretto import genitori
- Aggiornata versione mobile carenze studenti

**ATA, ferie, permessi e orario**
- Correzione gestione permessi

**Manutenzione e correzioni**
- Progressi su gestione corsi
- Aggiornato carenze lato didattica

##### 26 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiunto filtro carenze che seleziona gli studenti con carenze

**ATA, ferie, permessi e orario**
- Sistemata gestione corsi lato didattica

**Manutenzione e correzioni**
- Completato filtro corso per carenze
- Salva nel db il campo carenze dei corsi
- Minor
- Aggiunto file js corsi

##### 25 agosto 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Update
- Sviluppo modale corsi
- Aggiornamento config json

##### 24 agosto 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Sviluppo pagina corsi
- Aggiunta sezione corsi in didattica
- Corretto anno nella generazione del testo della carenza

##### 23 agosto 2025 - versione 1.2.48
**Programmi didattici**
- Aggiunta selezione anno su programmi svolti

**MasterCom, studenti e NOIRC**
- Correzioni su import genitori e studenti

**Manutenzione e correzioni**
- Aggiunta selezione anno su carenze didattica
- Aggiunta selezione anno carenze lato genitore
- Aggiunta selezione anno carenze lato studente
- Ora le carenze si vedono quelle di qs anno e quello precedente

##### 22 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Correzione selezione studenti su carenze genitori

**Manutenzione e correzioni**
- Cancellato error log
- Minor bug

##### 18 agosto 2025 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Permessi details

##### 17 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Sistemata versione mobile carenze e sportelli lato genitori

**ATA, ferie, permessi e orario**
- Iniziato sviluppo permessi lato genitore
- Sistemata versione mobile carenze lato studente

**Manutenzione e correzioni**
- Correzioni
- Rinominato file
- Minor corrections
- Corretto nomefile
- Aggiunti campi mancanti nel template

##### 16 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiornamento versione mobile sportelli per studenti
- Aggiornate pagine principali genitori e studenti in versione mobile

**ATA, ferie, permessi e orario**
- Aggiornata pagina errore login in versione mobile

**Interfaccia e mobile**
- Aggiornate pagine di errore alla versione mobile

**Manutenzione e correzioni**
- Aggiornato codice sportello lato didattica
- Removed error log
- Minor correction
- Minor bug

##### 15 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Sistemato carenze lato genitori
- Sistemata variabile user genitori nella sessione
- Aggiunti file sportelli per genitori
- Aggiunta pagina base Genitori
- Aggiunta login MasterCom genitori

**ATA, ferie, permessi e orario**
- Sistemata visibilità corsi di recupero se admin

**Manutenzione e correzioni**
- Aggiornato query con nuovi campi studente
- Minor bug
- Minor update

##### 14 agosto 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Correggo log aggiornamento importi

##### 13 agosto 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Test onesignal

##### 10 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiunto l'insert ed il delete dei genitori
- Aggiornata pagina genitori e pagina dettaglio
- Creata importazione genitori

##### 9 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Creata pagina genitori base
- Aggiunto filtro per classe
- Aggiunto filtro per studenti solo attivi
- Aggiornata importazione studenti

**Utenti, ruoli e sessioni**
- Aggiunto nuovo log solo per la fase di login per tutti gli utenti

**Manutenzione e correzioni**
- Agigunto CF e userId nel dettaglio dello studente
- Aggiunto IP client nel log del login
- Ora nome e cognome sono stampati con la prima lettera maiuscola

##### 8 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiornata gestione studenti con tabella studente_frequenta spostando i campi classe ed anno scolastico

##### 3 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiunto login Mastercom nella form di login

**Manutenzione e correzioni**
- Aggiunto CF ed aggiornato colonne

##### 18 luglio 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Aggiunta possibilità di far vedere tutte le carenze ai docenti

##### 15 luglio 2025 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Aggiornati permessi per visibilità pagina a segreteria didattica

##### 5 luglio 2025 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiornato invio mail con account google

**MasterCom, studenti e NOIRC**
- Sistemato generazione ed invio massivo carenze a studenti

**Interfaccia e mobile**
- Aggiunto FUIS lato docente con pulsante configurazione per visibilità

**Manutenzione e correzioni**
- Creato import da EDT elenco docenti con classi e materie
- Ora i docenti vedono solo le carenze da loro validate
- Aggiornato chiamate carenze lato studente

##### 4 luglio 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Aggiornamenti e correzioni bug

##### 30 giugno 2025 - versione 1.2.48
**Interfaccia e mobile**
- Aggiunto sommario ore fatte attribuite

**Manutenzione e correzioni**
- Aggiunto invio massivo Carenze

##### 29 giugno 2025 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Completato invio via mail della carenza, aggiornata visibilità lato studente e docente

**Programmi didattici**
- Aggiunti obiettivi minimi lato didattica
- Aggiunta stampa programmi anche per i programmi svolti
- Integrata stampa programma nei programmi delle materie

**ATA, ferie, permessi e orario**
- Sistemata stampa carenze lato studente
- Sistemata stampa carenza lato didattica

**Manutenzione e correzioni**
- Installato pacchetto tcpdf per la gestione dei PDF multipagina

##### 27 giugno 2025 - versione 1.2.48
**Programmi didattici**
- Corretta numerazione nuovo modulo programmi svolti

**Interfaccia e mobile**
- Aggiunta colonna TOTALE su ore Fatte Fuis lato Dirigente

##### 26 giugno 2025 - versione 1.2.48
**Programmi didattici**
- Ssitemati errori su share e copia programmi svolti
- Nei programmi svolti, ora creando una nuova classe non serve più salvare subito prima di inserire il programma

**Manutenzione e correzioni**
- Sistemato export carenze, ora esporta secondo il filtro applicato

##### 23 giugno 2025 - versione 1.2.48
**Programmi didattici**
- Correzioni e miglioramenti su programmi svolti

**Manutenzione e correzioni**
- Correzioni e miglioramenti su carenze

##### 22 giugno 2025 - versione 1.2.48
**Programmi didattici**
- Aggiunta la funzionalità duplica programma svolto

**ATA, ferie, permessi e orario**
- Creata bozza pagina carenze lato studente

##### 21 giugno 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Carenze lato docenti completate
- Aggiunta pagina carenze su didattica

##### 20 giugno 2025 - versione 1.2.48
**Programmi didattici**
- Aggiornamento programmi materie e svolti

##### 8 giugno 2025 - versione 1.2.48
**Programmi didattici**
- Aggiornamenti e bug fix programmi e moduli

##### 5 giugno 2025 - versione 1.2.48
**Programmi didattici**
- Primo step gestione programmi materie con moduli

**Interfaccia e mobile**
- Corretta visibilità dettaglio sportello

**Manutenzione e correzioni**
- Corretto bug import sportelli
- Corretta posizione codice intestazione
- Reinserito file mancante
- Corretta visualizzazione tooltip sulle attiivtà

##### 26 aprile 2025 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiunto messaggio errore su controllo ore diaria

**Programmi didattici**
- Aggiunto nel JSON Programmi Materie e PdL estesi
- Aggiunta sezione Programmi Materie

**ATA, ferie, permessi e orario**
- Corretto salvataggio ore diaria su previste

**Manutenzione e correzioni**
- Aggiunto controllo diaria su previste
- Correzione testo

##### 25 aprile 2025 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Aggiornato vincolo data limite sportello

**Manutenzione e correzioni**
- Aggiornato percorso relativo template

##### 18 gennaio 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiunta colonna classe di concorso

**ATA, ferie, permessi e orario**
- Impostata categoria di default

**Manutenzione e correzioni**
- Piccola correzione

##### 16 gennaio 2025 - versione 1.2.48
**Programmi didattici**
- Lato segreteria aggiunta statistica sportelli programmati e fatti

##### 31 dicembre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Corretto invio mail per cancellazione sportello lato didattica

**MasterCom, studenti e NOIRC**
- Aggiunto classe di concorso docente dove mancava, nelle tabelle e del codice
- Correggo bug limite temporale prenotazione sportelli lato studenti

##### 1 dicembre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Updated send mail to new server

**ATA, ferie, permessi e orario**
- Aggiornata selezione criteri bonus

**Interfaccia e mobile**
- Corretto header file

**Manutenzione e correzioni**
- Corretto post controllo bonus
- Update Log library

##### 16 novembre 2024 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiornato testo cron promemoria studenti

**Interfaccia e mobile**
- Corretta visibilità ore riepilogo

**Manutenzione e correzioni**
- Aggiunta possibilità di iscrivere un singolo studente a più sportelli
- Corretto bug lato didattica ora si possono modificare presenti agli sportelli
- Aggiornato messagi cron

##### 10 novembre 2024 - versione 1.2.48
**Manutenzione e correzioni**
- Aggiornamento ore

##### 9 novembre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiornato invio mail sportelli studenti e docenti con nuovo comando send mail
- Creato comando per invio mail con autenticazione Google
- Caricato PHP Mailer

**MasterCom, studenti e NOIRC**
- Corretto controllo studenti assenti agli sportelli

**Utenti, ruoli e sessioni**
- Inserito nel JSON parametri per autenticazione Google

**Interfaccia e mobile**
- Aggiornato previste con visibilità ore previste orientamento nel riepilogo

**Manutenzione e correzioni**
- Aggiornato riepilogo ore orientamento nelle fatte del docente

##### 4 novembre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Manda la mail di notifica assenza SOLO per gli sportelli didattici e non per le altre attività

##### 3 novembre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiornato stile mail promemoria studente

**ATA, ferie, permessi e orario**
- Aggiornata descrizione link google per calendar

**Manutenzione e correzioni**
- Aggiornato link calendar con descrizione in base alla categoria

##### 2 novembre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Creata pagina email notifica assenza a sportello per studente

**MasterCom, studenti e NOIRC**
- Aggiunta possibilità didattica di iscrivere elenco di studenti ad una serie di attività in blocco

**Biglietti ed eventi**
- Lato didattica si possono vedere solo le attività con prenotazioni

**Interfaccia e mobile**
- Aggiunto filtro ISCRITTO per le sole attività a cui lo studente è iscritto

##### 1 novembre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Corretto subject mail sportelli docenti

**MasterCom, studenti e NOIRC**
- Corretto invio promemoria docente per no studenti

**Interfaccia e mobile**
- Aggiunta visibilità e gestione categoria lato docente

**Manutenzione e correzioni**
- Gli sportelli bloccati ora sono apribili in sola lettura del docente
- Lato didattica aggiunta categoria ad import e nuovo sportello

##### 31 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiornati template ed email inviate alla cancellazione sportello
- Migliorato aspetto e subject mail lato studente per sportelli
- Corretta notifica mail al docente

**MasterCom, studenti e NOIRC**
- Aggiunto filtro per categoria a studenti

**Manutenzione e correzioni**
- Rimosso file in piu

##### 29 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiunto messaggio di conferma docente prima di firmare

**MasterCom, studenti e NOIRC**
- La didattica ora può modificare il flag presenza degli studenti agli sportelli
- Corretto calcolo numero studenti iscritti allo sportello nella pagina di dettaglio

##### 21 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Inserito nome istituto mail annullamento sportello

##### 20 ottobre 2024 - versione 1.2.48
**Interfaccia e mobile**
- Correzione bug ore fuis previste

##### 17 ottobre 2024 - versione 1.2.48
**Manutenzione e correzioni**
- Aggiornamento viaggi docenti fatto da Paolo
- Allineamento colonna materia sportello didattica

##### 16 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Rimossi messaggi INFO di debug

**Manutenzione e correzioni**
- Corretta inizializzazione sqlList in caso di errore

##### 15 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Migliorato template mail cancellazione sportello con elenco studenti aggiornato
- Migliorato testo messaggi INFO
- Modificato nel template delle mail il nome dell'istituto ora preso dal json

**ATA, ferie, permessi e orario**
- Corretto ordine stampa della data

**Manutenzione e correzioni**
- Ora i docenti non possono modificare i vecchi sportelli
- Aggiunto invio promemoria studente il giorno prima
- Corretto errore query
- Aggiunta colonna categoria sportelli, argomento lato docente appare solo se scelto dal docente

##### 13 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Corretto messaggio email
- Aggiunto campo BCC email inviate per sportello configurabile da JSON

**Manutenzione e correzioni**
- Creato promemoria DOCENTE da inviare la mattina dello sportello - da usare con CRON

##### 12 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiornati template mail quando studente di cancella dallo sportello
- Aggiunta gestione invio mail quando docente cancella sportello
- Modificato messaggio di conferma per la cancellazione dello sportello
- Rimosso pulsante firma, aggiunto messaggio di alert se si tenta di firmare sportello vuoto
- Adattato codice per campi necessari per invio email
- Creati template email iscrivi e cancella
- Altre 1 modifiche minori nella stessa area.

**Manutenzione e correzioni**
- Aggiornato stile icona lock
- Sportello cancellato o firmato non più modificabile dal docente. Aggiunto tooltips
- Corrette segnalazioni errore session logout Google
- Rimossi file non più necessari
- Rimosso codice info non più necessario
- Completato codice iscrizione studente a sportello
- Altre 4 modifiche minori nella stessa area.

##### 10 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiunto campo email nel JS
- Aggiunto campo email in read records
- Aggiunto DST ora sportello - aggiornato invio mail studenti e docenti
- Aggiunto <br> messaggio di errore

##### 9 ottobre 2024 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Aggiunta possibilità di importare sportelli multipli che hanno lo stesso orario

**Interfaccia e mobile**
- Corretta visibilità tooltip cancella prenotazione

**Manutenzione e correzioni**
- Corretto campo errore e risolto bug form inserimento nuovo sportello
- Migliorate etichette stato sportelli lato docente e didattica

##### 8 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Corretta visibilità messaggio di errore

**MasterCom, studenti e NOIRC**
- Aggiornato codice sportelli con il campo classe_id del database

**ATA, ferie, permessi e orario**
- Aggiornata visibilità campo online in base al valore nel JSON

**Manutenzione e correzioni**
- Aggiunto flag json docente puo modificare dati sportello
- Sistemato pagina dettaglio sportelli docente con lista classi da DB

##### 7 ottobre 2024 - versione 1.2.48
**Manutenzione e correzioni**
- Completati status sportelli lato studente

##### 6 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Corretto formato HTML mail inviata a studente

##### 5 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Creata mail completa quanto studente si iscrive a sportello
- Aggiunto invio mail docente quando si iscrive uno studente

**MasterCom, studenti e NOIRC**
- Aggiunto filtro classe sportelli lato didattica
- Aggiunto filtro classe_id per gli sportelli - nb:aggiungere campo classe_id nella tabella sportelli

**Interfaccia e mobile**
- Dettaglio sportello didattica - inserito flag visibilità online clil orientamento

**Manutenzione e correzioni**
- Nell'elenco degli sportelli ora appare l'argomento se scelto dallo studente
- Aggiunti nuovi status lato studente
- Aggiunto stato "posti disponibilil" e "posti esauriti" lato didattica
- Migliorato stile pannello

##### 4 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Quando il docente vuole cancellare uno sportello appare un messaggio di conferma prima di procedere

**MasterCom, studenti e NOIRC**
- Aggiunta opzione selezione tipo classe

**Biglietti ed eventi**
- Aggiunta colonna max prenotazioni, aggiunto stato "posti diponbiili " e "posti esauriti"

**Interfaccia e mobile**
- Corretta visibilità sportelli cancellati lato docente
- Aggiunti flag json per visibilità sezione sportello docente
- Aggiunti pulsante visibilità sportelli cancellati

**Manutenzione e correzioni**
- Lato studente se lo sportello è cancellato non compaiono gli altri status
- Corretto campo max iscrizioni
- Aggiunto status posti esauriti allo sportello lato studente
- Aggiornamento js per sportelli cancellati
- Modifiche diaria da Paolo Scapin
- Correzione formattazione campo tabella
- Altre 1 modifiche minori nella stessa area.

##### 26 settembre 2024 - versione 1.2.48
**Manutenzione e correzioni**
- Stampa ridotta piano di lavoro

##### 19 settembre 2024 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Lato studente invece che numero di studenti prenotati ora vede numero di posti disponibili allo sportello

**ATA, ferie, permessi e orario**
- Lato studente la riga dello sportello cancellato appare barrata

**Manutenzione e correzioni**
- Lato studente creato toggle per scegliere se vedere o meno gli sportelli cancellati
- Adesso lo studente vede gli sportelli cancellati ed appare una label rossa
- Lato docente migliorato aspetto tabella sportelli
- Lato didattica migliorato aspetto tabella sportelli
- Migliorato aspetto formattazione testo tabella sportelli
- Aggiunto file .htaccess nel gitignore

<!-- gestore-git-changelog-1.3.2:end -->
<!-- gestore-git-changelog-1.2.391:start -->
## Version 1.2.391 - 16 mag 2026
##### Sintesi
- Aggiornamento generato automaticamente dai commit Git successivi all'ultimo aggiornamento del changelog.
- Periodo commit: 2024-09-19 - 2026-05-16.
- Commit analizzati: 683.

- Ticket, Telegram e comunicazioni: 101 modifiche, tra cui Editor disabilitato - non compare messaggio sotto editor; Aggiornato log gmail, aggiornato cron e watch per lettura mail.
- Programmi didattici: 45 modifiche, tra cui Aggiunte classi articolate anche sui programmi iniziali; Aggiornato programma materie a nuovo formato word like.
- MasterCom, studenti e NOIRC: 90 modifiche, tra cui Aggiornato sync totale studenti usando sync singola classe; Corretto calcolo attività no irc studenti.
- Biglietti ed eventi: 11 modifiche, tra cui Da MBApp gli eventi vengono sincronizzati in Calendar quando vengono approvati; Corretto calcolo orario attività su pagina eventi.
- ATA, ferie, permessi e orario: 110 modifiche, tra cui Aggiunto filtro esteso tipo ferie su pagina permessi; Aggiunto log front end su operazioni fatte su ferie personale ATA.
- Utenti, ruoli e sessioni: 27 modifiche, tra cui La sessione viene mantenuta chuidendo la scheda di chrome, se poi ritorno al link sul sito joomla ricorda la sessione e va alla pagina richiesta direttamente; Checksession autorizza solo utenti con attivo = 1.
- Interfaccia e mobile: 36 modifiche, tra cui Aggiornati warning e corretto calcolo ore fuis attività fatte; Primo fix calcolo ore fuis.
- Manutenzione e correzioni: 254 modifiche, tra cui Corretto format footer e doppio pulsante; Corretto import ora applica il format word like subito dopo import.

##### Dettaglio

##### 16 maggio 2026 - versioni 1.2.385 - 1.2.390
**Ticket, Telegram e comunicazioni**
- Editor disabilitato - non compare messaggio sotto editor

**Programmi didattici**
- Aggiunte classi articolate anche sui programmi iniziali
- Aggiornato programma materie a nuovo formato word like
- Sistemato import e formato programmi svolti vs minimi

**Manutenzione e correzioni**
- Corretto format footer e doppio pulsante
- Corretto import ora applica il format word like subito dopo import

##### 15 maggio 2026 - versione 1.2.384
**ATA, ferie, permessi e orario**
- Aggiunto filtro esteso tipo ferie su pagina permessi

##### 14 maggio 2026 - versioni 1.2.381 - 1.2.383
**Ticket, Telegram e comunicazioni**
- Aggiornato log gmail, aggiornato cron e watch per lettura mail

**Programmi didattici**
- Corretto bug, quando crei nuovo programma puoi importare subito i moduli senza salvare

**Manutenzione e correzioni**
- Sync da mbapp a google calendar completato

##### 13 maggio 2026 - versioni 1.2.370 - 1.2.380
**Ticket, Telegram e comunicazioni**
- Migliorati messaggi debug
- Migliorato testo mail in caso di approvazione parziale
- Separato invio mail da conferma ferie lato segreteria ATA

**MasterCom, studenti e NOIRC**
- Aggiornato sync totale studenti usando sync singola classe
- Corretto calcolo attività no irc studenti
- Aggiornati tag su scelta studenti con tooltip
- Corretta gestione assegnazione orario docenti noirc

**Biglietti ed eventi**
- Da MBApp gli eventi vengono sincronizzati in Calendar quando vengono approvati

**ATA, ferie, permessi e orario**
- Aggiunto log front end su operazioni fatte su ferie personale ATA

**Manutenzione e correzioni**
- Aggiunte funzionalità drive e caricamento file log su Drive
- Corretto sync di tutte le classi

##### 12 maggio 2026 - versione 1.2.369
**Manutenzione e correzioni**
- Aggiunto sync da Google Calendar a MBApp

##### 11 maggio 2026 - versioni 1.2.355 - 1.2.368
**Programmi didattici**
- Aggiunte classi articolate - integrate nei programmi svolti

**MasterCom, studenti e NOIRC**
- Aggiornate tabelle db per noirc
- Aggiornate chiamate mastercom noirc e assenze
- Aggiornata grafica pagina assegnazione docenti noirc
- Corretto import noirc da mastercom
- Il token mastercom viene salvato in sessione invece che richiederlo ogni volta
- Aggiornata pagina gestione aule noirc
- Altre 1 modifiche minori nella stessa area.

**ATA, ferie, permessi e orario**
- Lato segrata aggiornati permessi con ora rientro non obbligatoria
- Aggiornata pagina appello classi
- Permessi ata, visita medica e permesso breve ora rientro non obbligatoria

**Manutenzione e correzioni**
- Incluso in commit precedente
- Corretta gestione modifica assenze studente
- Aggiunto snapshot classi-docenti

##### 10 maggio 2026 - versioni 1.2.349 - 1.2.354
**Ticket, Telegram e comunicazioni**
- Messaggi successivi su nuovo ticket vengono accodati prima della presa in carico

**Programmi didattici**
- Programmi minimi aggiornati con editor word-like
- Aggiornati programmi iniziaili con editor word-like
- Corretto bug sessione quando impersono docente e torno admin restava un residuo nei programmi svolti
- Minor fix stampa programmi svolti

**Interfaccia e mobile**
- Aggiornati warning e corretto calcolo ore fuis attività fatte

##### 8 maggio 2026 - versioni 1.2.344 - 1.2.348
**Programmi didattici**
- Corretti problemi formattazione testo programmi svolti
- Aggiornata formatatzione e grafica programmi svolti classi quinte secondo nuovo format

**ATA, ferie, permessi e orario**
- Ora anche lato personale ata vede le modifiche rispetto alla prima richiesta ferie estive inviata
- Ferie estive ATA, una richiesta inviata può essere modificata mantenendo lo storico lato segreteria ATA

**Interfaccia e mobile**
- Primo fix calcolo ore fuis

##### 7 maggio 2026 - versione 1.2.343
**Programmi didattici**
- Editor programmi svolti reso compatibile con formato Word e copia incolla diretto

##### 6 maggio 2026 - versioni 1.2.341 - 1.2.342
**Manutenzione e correzioni**
- Aggiunta funzione base notifihce con file di test
- Aggiunti pacchetti composer per gestione noticihe push

##### 5 maggio 2026 - versioni 1.2.336 - 1.2.340
**Ticket, Telegram e comunicazioni**
- Aggiornato comportamento ticket - risposta su ticket chiuso da la possibilità di riaprire ticket lato admin
- Messaggio chiusura ticket ora riporta il tipo di utente specifico

**Biglietti ed eventi**
- Corretto calcolo orario attività su pagina eventi

**ATA, ferie, permessi e orario**
- Se viene ripristinata una sostituzione annullata ora non da errore ma la ripristina

**Manutenzione e correzioni**
- Ora importa sostituzioni anche docenti con doppio nome non uguali a nome gestore

##### 4 maggio 2026 - versioni 1.2.331 - 1.2.335
**Ticket, Telegram e comunicazioni**
- Aggiornata lettura mail ticket, se è una mail di inoltre legge anche la mail inoltrata
- Aggiunta guida gestione notifiche push gmail
- Gestione ticket gestore , passato da polling ogni minuto a notifiy push gmail

**Programmi didattici**
- Programmi svolti import - se non c'è il programma del docente propone i programmi inseriti stessa materia stessa classi del docente che risulta

**Utenti, ruoli e sessioni**
- La sessione viene mantenuta chuidendo la scheda di chrome, se poi ritorno al link sul sito joomla ricorda la sessione e va alla pagina richiesta direttamente

##### 3 maggio 2026 - versioni 1.2.328 - 1.2.330
**Ticket, Telegram e comunicazioni**
- Aggiornata creazione newsletter da chengelog

**Programmi didattici**
- Aggiunta stampa programma singolo docente su classi quinte

**ATA, ferie, permessi e orario**
- Corretto errore sintassi, migliorata forma anteprima e pdf in accordo con formato word per le classi quinte

##### 2 maggio 2026 - versioni 1.2.325 - 1.2.327
**Ticket, Telegram e comunicazioni**
- Migliorata gestione messaggi telegram ticket

**Programmi didattici**
- Migliorata gestione con live preview dei programmi iniziali
- Aggiunta live preview programmi svolti e guida utente laterale

##### 30 aprile 2026 - versioni 1.2.321 - 1.2.324
**Ticket, Telegram e comunicazioni**
- Aggiunta gestione ticket via mail per docenti
- Aggiunto accesso a mail gestore per invio e lettura mail
- Migliorato testo messaggi telegram admin per import

**MasterCom, studenti e NOIRC**
- Corretti bug confronto studenti e genitori

##### 29 aprile 2026 - versioni 1.2.312 - 1.2.320
**Programmi didattici**
- Aggiunto settings json per visibilità programmi svolti al coordinatore
- Minor bug programmi svolti
- Programmi svolti, spostate sezioni metodologie fuori dai moduli
- Corretta generazione e stampa programma svolto quinte

**MasterCom, studenti e NOIRC**
- Corretta visibilità pulsante biglietti da telefono lato studenti
- Gestione base noirc mastercom

**ATA, ferie, permessi e orario**
- Corretto errore calcolo export ferie, rimosso colore intensità permessi ferie, aggiungto js no cache
- Rimosso colore per celle con tanti permessi - reso caricamento js no cache

**Manutenzione e correzioni**
- Caricamento js con datetime per problemi cache

##### 28 aprile 2026 - versioni 1.2.300 - 1.2.311
**Ticket, Telegram e comunicazioni**
- Migliorata visibilità ticket lato utente e corretta gestione orario eventi

**MasterCom, studenti e NOIRC**
- Introdotta gestione noirc
- Corretto aggiornamento noirc studenti
- Aggiunte nuove funzioni integrazione con mastercom
- Completato allineamento dati mastercom - gestore
- Sql creazione tabelle gestione dati da mastercom
- Aggiunta autenticazione docente gestione MasterCom
- Altre 2 modifiche minori nella stessa area.

**Biglietti ed eventi**
- Corretta visualizzazione prenotazioni biglietti
- Minor fix gestione biglietti eventi

**Manutenzione e correzioni**
- Modificati dati visibile singolo evento attivo

##### 27 aprile 2026 - versioni 1.2.295 - 1.2.299
**Programmi didattici**
- Aggiunto setting json per abilitare modifica programmi a coordinatore dipartimento
- Aggiornata sezione obiettivi minimi - coordinatore può modificare i programmi
- Docente coordinatore non può modificare programmia altri docenti
- Aggiornati programmi svolti, aggiunta apposita logica per classi quinte

**Utenti, ruoli e sessioni**
- Checksession autorizza solo utenti con attivo = 1

##### 23 aprile 2026 - versioni 1.2.283 - 1.2.294
**MasterCom, studenti e NOIRC**
- Migliorata UI pagina CLASSE e DOCENTE

**ATA, ferie, permessi e orario**
- Migliorate informazioni riportate nel riepilogo permessi personale ata
- Sistemata possibilità sui permessi di avere piu righe in un unico permesso
- Migliorata ricerca dentro orario aule classi e docenti
- Aggiornata gestione orario lato segreteria ATA
- Migliorata gestione orario permessi lato personale ata
- Migliorata UI pagina AULE
- Altre 1 modifiche minori nella stessa area.

**Utenti, ruoli e sessioni**
- Aggiornati problemi sessione utente quando admin assume ruolo
- Admin interpreta docente, corretta gestione sessione pagine docente
- Corretta gestione sessione scaduta

**Manutenzione e correzioni**
- Lato studente serale, gli sportelli apre di default la categoria preora

##### 21 aprile 2026 - versione 1.2.282
**ATA, ferie, permessi e orario**
- Cambiato formato orario permessi solo a cifre no orologio grafico

##### 20 aprile 2026 - versioni 1.2.275 - 1.2.281
**Ticket, Telegram e comunicazioni**
- Corretto formato ora sportello delle mail

**MasterCom, studenti e NOIRC**
- Corretto calcolo orario permessi genitori

**Biglietti ed eventi**
- Miglioramento gestione biglietti eventi

**ATA, ferie, permessi e orario**
- Migliorata gestione ferie lato segrata

**Manutenzione e correzioni**
- Aggiornato comportamento pulsanti cruscotto, aggiunto pulsanti registrato - da registrare
- Modificato cruscotto ora sono filtri sommativi
- Corretto formato ora legale

##### 19 aprile 2026 - versioni 1.2.264 - 1.2.274
**Ticket, Telegram e comunicazioni**
- Integrata pagina tickets in header admin
- Aggiunto tickets in gestore su menu admin - rivisto menu admin
- Aggiunta gestione ticket trentino volley
- Aggiornato send mail con ok di ritorno ed allega multi allegato

**Biglietti ed eventi**
- Migliorata visibilità eventi dai vari utenti anche con impersona
- Aggiunta gestione eventi lato admin, e prenotazione lato utenti

**ATA, ferie, permessi e orario**
- Sistemata header docente

**Utenti, ruoli e sessioni**
- Migliorate funzioni agisci come

**Interfaccia e mobile**
- Migliorato header didattica

**Manutenzione e correzioni**
- Aggiornato algoritmo e mappe per tutte le tribune del palazzetto
- Aggiunti pacchetti con composer

##### 17 aprile 2026 - versioni 1.2.262 - 1.2.263
**Utenti, ruoli e sessioni**
- Corretta visibilità nome docente modalità admin ore previste e fatte

**Manutenzione e correzioni**
- Corretto nome aula maiuscolo, nomi docenti a capo

##### 16 aprile 2026 - versioni 1.2.255 - 1.2.261
**Biglietti ed eventi**
- Pagina default eventi mobile
- Creata versione mobile orario ed eventi

**ATA, ferie, permessi e orario**
- Migliorata grafica e layout orario mobile
- Abilitato pulsante orario personale ata
- Aggiunto pulsante permessi anche dentro il modale del permesso

**Interfaccia e mobile**
- Corretti colori mobile

**Manutenzione e correzioni**
- Bug fix flag permesso registrato ora viene salvato

##### 14 aprile 2026 - versioni 1.2.251 - 1.2.254
**ATA, ferie, permessi e orario**
- Aggiunto flag reegistrazione permessi lato segreteria

**Manutenzione e correzioni**
- Reso dinamico caricamento css
- Aggiunto dettaglio permesso sotto al tipo
- Rimosso badge Bozza lato segreteria

##### 12 aprile 2026 - versioni 1.2.237 - 1.2.250
**Ticket, Telegram e comunicazioni**
- Rimossa stampa messaggi debug
- Correzioni logica invio mail
- Sistemata invio mail lato segreteria

**ATA, ferie, permessi e orario**
- Minor bug - aggiunto nome approvatore permessi
- Aggiornato export in formato xls personale ata ed aggiornata dashboard con badge parziali
- Aggiornato e migliorata stampa pdf permessi
- Messo link diverso per help segreteria ATA
- Aggiornata visibilita versione come tooltip
- Corretta visibilità approvazione giorni di ferie
- Altre 1 modifiche minori nella stessa area.

**Utenti, ruoli e sessioni**
- Aggiunto ruolo ras

**Manutenzione e correzioni**
- Migliorato css tabella
- Aggiunti export csv lato segreteria
- Rimosso codice non piu necessario

##### 11 aprile 2026 - versioni 1.2.227 - 1.2.236
**Ticket, Telegram e comunicazioni**
- Sistemato sistema invio mail lato personale
- Lato utente invio mail quando fa richiesta permesso

**ATA, ferie, permessi e orario**
- Migliorata grafica tooltip calendario
- Lato utente aggiunta possibilità di una nuova richiesta di ferie
- Creata dashboard ferie e corretti bug
- Migliorata logica approvazione ferie
- Aggiunto file excel esempio import personale ATA

**Manutenzione e correzioni**
- Minor bug fix
- Corretto filtro tabella
- Aggiunta funzione import personale

##### 10 aprile 2026 - versioni 1.2.220 - 1.2.226
**ATA, ferie, permessi e orario**
- Aggiunto export personale ata
- Lato segreteria ata migliorata gestione permessi
- Richiesta ferie migliorata, aggiunta rimetti in bozza
- Migliorati permessi - pulsanti corretti - rimessa in bozza
- Migliorata grafica ferie - colori
- Aggiornati permessi e ferie lato personale ATA sia telefono che desktop
- Altre 1 modifiche minori nella stessa area.

##### 3 aprile 2026 - versioni 1.2.214 - 1.2.219
**Ticket, Telegram e comunicazioni**
- Aggiunte ed aggiornata pagina sostituzione in miniapp telegram
- Spostati messaggi telegram su log telegram
- Conclusa app telegram con sistema ticketing
- Aggiunto file telegram log
- Aggiunto log Telegram

**Manutenzione e correzioni**
- Rimosso file non piu usato

##### 2 aprile 2026 - versioni 1.2.208 - 1.2.213
**Ticket, Telegram e comunicazioni**
- Creata miniapp telegram GestOre
- Aggiornato formato ora nel nome del ticket
- Aggiunti parametri telegram
- Aggiornato e completato sistema di ticketing telegram

**Manutenzione e correzioni**
- Aggiunto log extra
- Script che cancella i topic piu vecchi di tot giorni

##### 1 aprile 2026 - versione 1.2.207
**Utenti, ruoli e sessioni**
- Risolto problema logout sessione dando un nome alla sessione

##### 31 marzo 2026 - versioni 1.2.201 - 1.2.206
**Ticket, Telegram e comunicazioni**
- Migliorata gestione ticket ora accetta anche file
- Creato gruppo admin GestOre, aggiunto sistema ticketing, notifiche sostituzioni ora a gruppo admin con abilitazione
- Inseriti i messaggi di scarto dell'import gestore nel riepilogo telegram
- Corretto formato data nelle mail delle sostituzioni

**ATA, ferie, permessi e orario**
- Lato genitore permessi corretto bug ora rientro null

**Manutenzione e correzioni**
- Aggiornato loggin - nome dei file ed aggiunto rotatelog su import sostituzioni

##### 30 marzo 2026 - versioni 1.2.190 - 1.2.200
**MasterCom, studenti e NOIRC**
- Aggiornata sicurezza ora c'è un controllo che i dati degli studenti richiesti corrispondano al genitore che ha fanno l'accesso, quindi non solo fidandosi del parametro passato alla POST

**ATA, ferie, permessi e orario**
- Corretto login personale ata
- Permessi lato personale ATA aggiornato codice
- Migliorata sicurezza lato studente e genitore per stampa carenza
- Caricata in git cartella error
- Migliorata sicurezza lato studente per carenze e sportelli
- Sportelli lato studente migliorata sicurezza
- Altre 2 modifiche minori nella stessa area.

**Manutenzione e correzioni**
- Corretto import che annulla e modifica solo le sostituzioni di oggi
- Corretto metodo esposizione parametri da GET a POST

##### 28 marzo 2026 - versione 1.2.189
**Manutenzione e correzioni**
- Correzione sostituzioni annullate o modificate

##### 27 marzo 2026 - versioni 1.2.184 - 1.2.188
**Ticket, Telegram e comunicazioni**
- Aggiornato import rimosso testing nelle mail

**MasterCom, studenti e NOIRC**
- Aggiornato agent, importa sostituzioni anche se non c'è abbinata la classe

**ATA, ferie, permessi e orario**
- Corretto login profilo segreteria ata

**Manutenzione e correzioni**
- Lato docente aggiunto pulsante filtra solo le sue sostituzioni
- Bug fix query

##### 24 marzo 2026 - versione 1.2.183
**Manutenzione e correzioni**
- Minor fix bug

##### 23 marzo 2026 - versioni 1.2.180 - 1.2.182
**Ticket, Telegram e comunicazioni**
- Completata integrazione invio messaggi Telegram per sostituzioni
- Impostata variabile dinamica su ora chiusura sportelli nella mail

**Manutenzione e correzioni**
- Rimosso file non necessario

##### 22 marzo 2026 - versioni 1.2.174 - 1.2.179
**ATA, ferie, permessi e orario**
- Aggiunte sostituzioni pagina orario
- Aggiornata list gitignore

**Manutenzione e correzioni**
- Aggiunto logging su import sostituzioni
- Allungato timeout import
- Minor bug fixes
- Creato EDT agent per importare le sostituzioni da pdf EDT

##### 19 marzo 2026 - versioni 1.2.162 - 1.2.173
**Ticket, Telegram e comunicazioni**
- La mail di notifica assenza agli studenti arriva solo se lo sportello è stato svolto

**MasterCom, studenti e NOIRC**
- Aggiornata assegnazione sportelli, il docente può cambiare data e classe, previo setting file json

**ATA, ferie, permessi e orario**
- Creata pagina segreteria ata
- Creata pagina personale ata
- Aggiunto ruolo personale ata e portineria

**Utenti, ruoli e sessioni**
- Aggiunto ruolo portineria in checksession

**Interfaccia e mobile**
- Corretto header quando si vede la pagina previste docente lato dirigente
- Aggiornati pulsanti su header vari

**Manutenzione e correzioni**
- Aggiunto totali per capitolo su tabella fatte, sistemato simbolo EURO
- Aggiunto totali per capitolo su tabella previste
- Corretto titolo pagina segreteria didattica
- Corretta denominazione sportello su MBApp

##### 18 marzo 2026 - versione 1.2.161
**ATA, ferie, permessi e orario**
- Orario MBAPP aggiunta sezione

##### 22 febbraio 2026 - versione 1.2.160
**ATA, ferie, permessi e orario**
- Aggiornamento funzioni orario

##### 19 febbraio 2026 - versione 1.2.159
**Manutenzione e correzioni**
- Corretta gestione aule non piu disponibili su sportelli

##### 13 febbraio 2026 - versioni 1.2.147 - 1.2.158
**Ticket, Telegram e comunicazioni**
- Aggiornato template mail inviate per sportelli lato studente
- Aggiunto log CRON e aggiornato invio mail

**ATA, ferie, permessi e orario**
- Corretta funziona salvataggio sportello lato docente
- Aggiornato stile header ata e admin
- Aggiunti nuovi settings cron e ata

**Interfaccia e mobile**
- Corretti require

**Manutenzione e correzioni**
- Corretta funziona cancella prenotazione MBApp
- Corretto funzionamento invio promemoria docente
- Aggiunte nuove funzioni
- Corretto settings MBApp
- Corretto motivo sportello
- In caso di assenza docente lo sportello viene annullato, via cron

##### 6 febbraio 2026 - versioni 1.2.145 - 1.2.146
**Manutenzione e correzioni**
- Corretta gestione apici bonus lato dirigente
- Corretta selezione multipla bonus lato docente

##### 5 febbraio 2026 - versioni 1.2.143 - 1.2.144
**ATA, ferie, permessi e orario**
- Corretto salvatagglio nome sportello in MBApp

**Manutenzione e correzioni**
- Corretto filtro sportelli

##### 4 febbraio 2026 - versioni 1.2.141 - 1.2.142
**Manutenzione e correzioni**
- Quando il docente cancella lo sportello ne viene creato uno gemello in bozza 14gg dopo
- Corretti bug lato studente iscrizione sportelli

##### 3 febbraio 2026 - versioni 1.2.139 - 1.2.140
**Interfaccia e mobile**
- Corretta visibilità dati su sportelli cancellati lato studente

**Manutenzione e correzioni**
- Sistemato bug cancellazione sportello lato docente

##### 2 febbraio 2026 - versione 1.2.138
**Manutenzione e correzioni**
- Minor correction

##### 1 febbraio 2026 - versioni 1.2.131 - 1.2.137
**Ticket, Telegram e comunicazioni**
- Aggiornata grafica mail notifica docente ore previste
- Aggiornata grafica mail sportelli
- Aggiunto file comune per invio mail con grafica uguale
- Aggiornata grafica mail cancellazione sportello docente - correggo bug su mail genitori
- Aggiunto messaggio di conferma su aula prima di creare sportello lato docente

**Manutenzione e correzioni**
- Adesso gli sportelli deserti vengono rimessi in bozza 14 gg in avanti, stessa cosa gli sportelli in bozza passati
- Corretto errore MBAPP su creazione nuovo sportello

##### 29 gennaio 2026 - versione 1.2.130
**Manutenzione e correzioni**
- Aggiunto giorno della settimana sulle date degli sportelli

##### 28 gennaio 2026 - versione 1.2.129
**Programmi didattici**
- Aggiunta possibilità modifica programmi al coordinatore di dipartimento

##### 26 gennaio 2026 - versione 1.2.128
**Manutenzione e correzioni**
- Correzione titolo sportello

##### 25 gennaio 2026 - versioni 1.2.123 - 1.2.127
**Ticket, Telegram e comunicazioni**
- Aggiunto invio mail annullamento prenotazione aula sportello su collaboratori

**Manutenzione e correzioni**
- Aggiornato template promemoria sportello lato studente
- Modale ora si chiude dove save
- Completato aggiornamento gestione sportelli lato didattica
- Corretto sportello delete

##### 22 gennaio 2026 - versioni 1.2.119 - 1.2.121
**Interfaccia e mobile**
- Aggiornato stile header per evitare sovrapposizioni dei menu su schermi piccoli

**Manutenzione e correzioni**
- Corretta query su campo attivo
- Corretto calcolo numero posti disponibili negativi

##### 20 gennaio 2026 - versione 1.2.118
**Manutenzione e correzioni**
- Lo sportello in bozza lato docente ora può essere impostato a due ore, e viene diviso automaticamente

##### 19 gennaio 2026 - versioni 1.2.116 - 1.2.117
**Ticket, Telegram e comunicazioni**
- Aggiornata modalità prenotazione sportelli con invio mail lato docente - corretti bug
- Migliorata grafica email sportelli lato docente

##### 17 gennaio 2026 - versioni 1.2.112 - 1.2.115
**ATA, ferie, permessi e orario**
- Ora gli sportelli in bozza possono essere assegnati dai docenti stessi, scegliendo l'aula che viene prenotata su MBApp

**Manutenzione e correzioni**
- Prenotazione aula su MBApp
- Connessione DB MBApp
- Verifica nel portale MBApp quali aule sono libere per una prenotazione

##### 15 gennaio 2026 - versioni 1.2.108 - 1.2.111
**MasterCom, studenti e NOIRC**
- Aggiunti sportelli-bozza non visibili a genitori e studenti

**ATA, ferie, permessi e orario**
- Aggiornata assegnazione sportello bozza al docente

**Interfaccia e mobile**
- Aggiunta visibilità sportelli-bozza docenti

**Manutenzione e correzioni**
- Aggiunti sportelli-bozza lato didattica

##### 14 gennaio 2026 - versione 1.2.107
**Utenti, ruoli e sessioni**
- Corretta visibilità pulsante esame seconda sessione

##### 11 gennaio 2026 - versioni 1.2.102 - 1.2.106
**Ticket, Telegram e comunicazioni**
- Migliorati messaggi interfaccia nel caso di mail errata

**Utenti, ruoli e sessioni**
- Aggiunti corsi per utenti esterni
- Aggiunta sezione utente esterno
- Aggiunta sezione utenti esterni

**Manutenzione e correzioni**
- Login genitore viene registrato datetime e IP

##### 10 gennaio 2026 - versioni 1.2.85 - 1.2.101
**Ticket, Telegram e comunicazioni**
- Aggiunti messaggi debug sessione - corretti redirect con sessione scaduta

**MasterCom, studenti e NOIRC**
- Aggiunta login google anche ai genitori
- Nuovo filtro sportelli lato genitore in base alla classe dello studente
- Nuovo filtro sportelli lato studente in base alla classe dello studente
- Sportelli, miglioramento logica filtro per gruppo classe lato docente e didattica

**ATA, ferie, permessi e orario**
- Rinnovata grafica index.php
- Aggiornata intestazione copyright su tutti i file modificati finora

**Utenti, ruoli e sessioni**
- Corretta posizione ruolorichiesto per evitare blank page
- Corretti calcoli ed aggiunta gestione sessione

**Interfaccia e mobile**
- Corretta visibilità header

**Manutenzione e correzioni**
- Correzioni su grafica login
- Sportelli lato docente, aggiunta colonna nome docente, corretta non modifica sportelli non di proprietà
- Corretti calcoli ed aggiunto simboli euro
- Corretto formato cifre e simbolo euro
- Rimosso checksession non necessario
- Corretta icona login page

##### 8 gennaio 2026 - versioni 1.2.80 - 1.2.84
**MasterCom, studenti e NOIRC**
- Correzione bug aggiunta studenti a nuovo corso

**ATA, ferie, permessi e orario**
- Migliorata gestione scadenza sessione

**Utenti, ruoli e sessioni**
- Sistemati problemi sessione ed utente con ore previste Dirigente

**Manutenzione e correzioni**
- Aggiunta funzione duplica sportello, migliorate larghezze colonne sportelli
- Corretto duplicazione corsi

##### 7 gennaio 2026 - versioni 1.2.77 - 1.2.79
**Utenti, ruoli e sessioni**
- Sostituito termine tentativo con sessione

**Manutenzione e correzioni**
- Ultime correzione su gestione firme esami
- Sistemati numerosi bug su gestione corsi carenze ed itinere

##### 5 gennaio 2026 - versione 1.2.76
**Manutenzione e correzioni**
- Estesa gestione dei corsi a più docenti, ognuno con la propria firma

##### 4 gennaio 2026 - versioni 1.2.70 - 1.2.75
**ATA, ferie, permessi e orario**
- Aggiornata intestazione file
- Corretta ed ampliata stampa storico bonus
- Corretto salvataggio e modifica indicatori

**Interfaccia e mobile**
- Corretta visibilità allegati bonus lato dirigente

**Manutenzione e correzioni**
- Aggiunta stampa PDF ed export CSV dei criteri lato dirigente
- Completato bonus lato docente con aggiunta del caricamento di allegati

##### 3 gennaio 2026 - versioni 1.2.67 - 1.2.69
**ATA, ferie, permessi e orario**
- Completata gestione bonus lato dirigente

**Manutenzione e correzioni**
- Aggiornato bonus lato docenti con nuove modalità
- Completato lato dirigente aggiunta anno scolastico per il bonus
- Aggiunta selezione anno su bonus docenti lato dirigente

##### 30 dicembre 2025 - versione 1.2.67
**Manutenzione e correzioni**
- Aggiunta stampa carenze incomplete sia csv che pdf

##### 29 dicembre 2025 - versioni 1.2.64 - 1.2.66
**ATA, ferie, permessi e orario**
- Aggiornata visibilità carenze anche lato genitore
- Aggiornata visibilità esito carenze lato studente
- Corretta ed aggiornata struttura gestione corsi carenze e secondo tentativo lato didattica

##### 24 dicembre 2025 - versioni 1.2.58 - 1.2.63
**Programmi didattici**
- Corretto invio sollecito programmi iniziaili ai docenti
- Migliorate regole formattazione testo programmi materie

**MasterCom, studenti e NOIRC**
- Sportelli lato docente, aggiunti filtri categoria , materia e classe

**Manutenzione e correzioni**
- Lato didattica corretta categoria iniziale sportelli su sportelli didattici
- Ora il docente può vedere gli sportelli di tutti i docenti
- Dettaglio studente i campi vengono svuotati all'inizio per evitare falsi valori rimasti in memoria

##### 23 dicembre 2025 - versione 1.2.57
**MasterCom, studenti e NOIRC**
- Corretta visibilità figli nella pagina genitori

##### 18 dicembre 2025 - versioni 1.2.50 - 1.2.56
**MasterCom, studenti e NOIRC**
- Migliorata query elenco genitori
- Cancellazione studente, ora anche i genitori vengono correttamente disabilitati

**Manutenzione e correzioni**
- Feature: ora da dettaglio genitore, se clicco su nome studente passo al dettaglio studente
- Feature: dal dettaglio studente posso collegare un genitore esistente
- Feature: Dal dettaglio genitore ora posso collegare uno studente esistente
- Feature: da scheda studente posso passare direttamente a scheda genitore
- Aggiunto incremento versione automatico

##### 16 dicembre 2025 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Ora la data dei permessi salta il sabato e la domenica

**Utenti, ruoli e sessioni**
- Lato admin ora posso cancellare uno studente iscritto ad uno sportello

**Manutenzione e correzioni**
- Ora posso duplicare un corso esistente

##### 15 dicembre 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Correzioni varie

##### 31 ottobre 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Minor bugs

##### 20 ottobre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Corretto ricerca classe in sql

**Interfaccia e mobile**
- Corretto percorsi file negli header

**Manutenzione e correzioni**
- Corretto stampa carenza con doppio livello liste puntate
- Ora carenze è disabilitato all'inizio

##### 15 ottobre 2025 - versione 1.2.48
**Programmi didattici**
- Aggiunti Programmi Iniziali lato didattica e docenti

**Manutenzione e correzioni**
- Aggiornato la stampa con l'ordine del numero del modulo

##### 14 ottobre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Corretto filtro sportelli lato mobile studenti

**ATA, ferie, permessi e orario**
- Migliorata conferma permessi lato didattica

##### 13 ottobre 2025 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Corretto codice invio mail ai genitori per sportelli

**Interfaccia e mobile**
- Corretta visibilità colonne tabella

**Manutenzione e correzioni**
- Corretto riferimento a studente_id

##### 11 ottobre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Sistemati errori sportelli studenti e genitori

**Interfaccia e mobile**
- Corretto require file errore

**Manutenzione e correzioni**
- Migliorato aspetto modale sportello
- Corretta visualizzazione dettaglio sportelli lato didattica
- Correzioni su import sportelli
- Aggiornato sportelli lato docente
- Completato aggiornamento sportelli lato studente

##### 10 ottobre 2025 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiunta funzione send mail con CC
- Aggiornata data mail promemoria al docente

**MasterCom, studenti e NOIRC**
- Correzioni sportelli lato studenti

**ATA, ferie, permessi e orario**
- Corretto refuso in query permessi

**Manutenzione e correzioni**
- Lavori in corso su sportelli per migliorare codice
- Correzioni su sportelli lato genitore
- Aggiornato codice invio promemoria sportello docente
- Corretta gestione apostrofo nella materie quando cancello sportello

##### 8 ottobre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Lato genitore, si vedevano gli studenti che non frequentano più

**Utenti, ruoli e sessioni**
- Da admin ora il logout ti fa uscire non andare alla pagina admin
- Aggiunto studente id sul nome utente in alto a destra

**Manutenzione e correzioni**
- Logout da impersona chiude la finestra
- Aggiunta voce studente esterno al posto di docente quando la carenza è di uno studente trasferito

##### 4 ottobre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiunto secondo tentativo lato genitori e studenti

**Manutenzione e correzioni**
- Aggiunto secondo tentativo esami corsi

##### 3 ottobre 2025 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Adesso i corsi hanno data-ora inizio e data-ora fine per ogni lezione
- Modificato checksession per maggiore durata sessione

**Manutenzione e correzioni**
- Gli esami dei corsi ora hanno un inizio ed una fine

##### 1 ottobre 2025 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Corretto calcolo classe nell'invio mail cancellazione sportello

**Programmi didattici**
- Corretta visibilità programmi svolti
- Aggiornata visibilità programmi svolti

**MasterCom, studenti e NOIRC**
- Aggiornata visibilità esito carenze lato genitori

**ATA, ferie, permessi e orario**
- La forma esame se non è compilata completamente non te la lascia salvare

**Interfaccia e mobile**
- Visibilità esito esami lato studente
- Corretta visibilità carenze lato studente desktop
- Aggiunta visibilità risultato carenze lato studente

**Manutenzione e correzioni**
- Sempre carenze lato genitore
- Sempre firma esame docente
- Aggiunta firma docente su esami
- Corretta cancellazione di una carenza esistente
- Aggiunte opzioni mancanti nel template
- Aggiunti corsi in itinere

##### 24 settembre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Corretto calcolo classe studente

**Manutenzione e correzioni**
- Corretto bug nome docente con apostrofo

##### 23 settembre 2025 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiornato invio mail con mail google di dominio

**MasterCom, studenti e NOIRC**
- Sportelli studenti visibili solo utente test

**Interfaccia e mobile**
- Rimosso sportelli da header segreteria

##### 21 settembre 2025 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiunto invio mail esiti ai coordinatori - e lista esami incompleto per segreteria

##### 20 settembre 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Aggiunta funzione esporta esito esami
- Aggiunto componente phpSpreadsheet

##### 14 settembre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Key mastercom
- Primi test con mastercom

**Manutenzione e correzioni**
- Aggiunta sezione esami corsi carenze

##### 13 settembre 2025 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Completata gestione permessi lato segreteria didattica

**Manutenzione e correzioni**
- Corretto problema logout

##### 11 settembre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Sistemata visibilità menu genitori con json

**ATA, ferie, permessi e orario**
- Inseriti file permessi lato didattica
- Sistemato permessi.js lato genitore
- Sistemati permessi lato genitore

**Interfaccia e mobile**
- Corretto menu header didattica

**Manutenzione e correzioni**
- Rimossi console log
- Nuovi config nel json

##### 10 settembre 2025 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Corretta funziona modifica data corso

##### 6 settembre 2025 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Migliorata pulizia sessione con logout

**Utenti, ruoli e sessioni**
- Aggiunto CF studente in sessione
- Correzione ruolo con impersona
- Corretto logout in base al ruolo impersonato
- Aggiunta funzione impersonaRuolo

**Manutenzione e correzioni**
- Pulsante logout adeguato ad impersona
- Corretta visibili con impersona docente
- Corretto login genitore senza figli
- Aggiunto campo codice fiscale mancante

##### 4 settembre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiornato figli genitori, visibili solo se figlio è attivo

**ATA, ferie, permessi e orario**
- Corretta query salvataggio modifica studente

**Manutenzione e correzioni**
- Se genitore non ha figli al login viene dato errore

##### 2 settembre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Corretto inserimento manuale nuovi studenti

**Manutenzione e correzioni**
- Correzioni minori su inserimento studente
- Ora studente ha campo Esterno per quando si inserisce uno studente trasferito
- Corretta aggiunta manuale carenze

##### 31 agosto 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Aggiunta firma lezione corso
- Aggiunto config json per abilitare modifica corso ai docenti
- Cambiato nome file
- Aggiornati corsi, ora funziona, possibili miglioramenti ancora

##### 28 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Correzione carenze genitori
- Corretto import genitori
- Aggiornata versione mobile carenze studenti

**ATA, ferie, permessi e orario**
- Correzione gestione permessi

**Manutenzione e correzioni**
- Progressi su gestione corsi
- Aggiornato carenze lato didattica

##### 26 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiunto filtro carenze che seleziona gli studenti con carenze

**ATA, ferie, permessi e orario**
- Sistemata gestione corsi lato didattica

**Manutenzione e correzioni**
- Completato filtro corso per carenze
- Salva nel db il campo carenze dei corsi
- Minor
- Aggiunto file js corsi

##### 25 agosto 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Update
- Sviluppo modale corsi
- Aggiornamento config json

##### 24 agosto 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Sviluppo pagina corsi
- Aggiunta sezione corsi in didattica
- Corretto anno nella generazione del testo della carenza

##### 23 agosto 2025 - versione 1.2.48
**Programmi didattici**
- Aggiunta selezione anno su programmi svolti

**MasterCom, studenti e NOIRC**
- Correzioni su import genitori e studenti

**Manutenzione e correzioni**
- Aggiunta selezione anno su carenze didattica
- Aggiunta selezione anno carenze lato genitore
- Aggiunta selezione anno carenze lato studente
- Ora le carenze si vedono quelle di qs anno e quello precedente

##### 22 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Correzione selezione studenti su carenze genitori

**Manutenzione e correzioni**
- Cancellato error log
- Minor bug

##### 18 agosto 2025 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Permessi details

##### 17 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Sistemata versione mobile carenze e sportelli lato genitori

**ATA, ferie, permessi e orario**
- Iniziato sviluppo permessi lato genitore
- Sistemata versione mobile carenze lato studente

**Manutenzione e correzioni**
- Correzioni
- Rinominato file
- Minor corrections
- Corretto nomefile
- Aggiunti campi mancanti nel template

##### 16 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiornamento versione mobile sportelli per studenti
- Aggiornate pagine principali genitori e studenti in versione mobile

**ATA, ferie, permessi e orario**
- Aggiornata pagina errore login in versione mobile

**Interfaccia e mobile**
- Aggiornate pagine di errore alla versione mobile

**Manutenzione e correzioni**
- Aggiornato codice sportello lato didattica
- Removed error log
- Minor correction
- Minor bug

##### 15 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Sistemato carenze lato genitori
- Sistemata variabile user genitori nella sessione
- Aggiunti file sportelli per genitori
- Aggiunta pagina base Genitori
- Aggiunta login MasterCom genitori

**ATA, ferie, permessi e orario**
- Sistemata visibilità corsi di recupero se admin

**Manutenzione e correzioni**
- Aggiornato query con nuovi campi studente
- Minor bug
- Minor update

##### 14 agosto 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Correggo log aggiornamento importi

##### 13 agosto 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Test onesignal

##### 10 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiunto l'insert ed il delete dei genitori
- Aggiornata pagina genitori e pagina dettaglio
- Creata importazione genitori

##### 9 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Creata pagina genitori base
- Aggiunto filtro per classe
- Aggiunto filtro per studenti solo attivi
- Aggiornata importazione studenti

**Utenti, ruoli e sessioni**
- Aggiunto nuovo log solo per la fase di login per tutti gli utenti

**Manutenzione e correzioni**
- Agigunto CF e userId nel dettaglio dello studente
- Aggiunto IP client nel log del login
- Ora nome e cognome sono stampati con la prima lettera maiuscola

##### 8 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiornata gestione studenti con tabella studente_frequenta spostando i campi classe ed anno scolastico

##### 3 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiunto login Mastercom nella form di login

**Manutenzione e correzioni**
- Aggiunto CF ed aggiornato colonne

##### 18 luglio 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Aggiunta possibilità di far vedere tutte le carenze ai docenti

##### 15 luglio 2025 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Aggiornati permessi per visibilità pagina a segreteria didattica

##### 5 luglio 2025 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiornato invio mail con account google

**MasterCom, studenti e NOIRC**
- Sistemato generazione ed invio massivo carenze a studenti

**Interfaccia e mobile**
- Aggiunto FUIS lato docente con pulsante configurazione per visibilità

**Manutenzione e correzioni**
- Creato import da EDT elenco docenti con classi e materie
- Ora i docenti vedono solo le carenze da loro validate
- Aggiornato chiamate carenze lato studente

##### 4 luglio 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Aggiornamenti e correzioni bug

##### 30 giugno 2025 - versione 1.2.48
**Interfaccia e mobile**
- Aggiunto sommario ore fatte attribuite

**Manutenzione e correzioni**
- Aggiunto invio massivo Carenze

##### 29 giugno 2025 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Completato invio via mail della carenza, aggiornata visibilità lato studente e docente

**Programmi didattici**
- Aggiunti obiettivi minimi lato didattica
- Aggiunta stampa programmi anche per i programmi svolti
- Integrata stampa programma nei programmi delle materie

**ATA, ferie, permessi e orario**
- Sistemata stampa carenze lato studente
- Sistemata stampa carenza lato didattica

**Manutenzione e correzioni**
- Installato pacchetto tcpdf per la gestione dei PDF multipagina

##### 27 giugno 2025 - versione 1.2.48
**Programmi didattici**
- Corretta numerazione nuovo modulo programmi svolti

**Interfaccia e mobile**
- Aggiunta colonna TOTALE su ore Fatte Fuis lato Dirigente

##### 26 giugno 2025 - versione 1.2.48
**Programmi didattici**
- Ssitemati errori su share e copia programmi svolti
- Nei programmi svolti, ora creando una nuova classe non serve più salvare subito prima di inserire il programma

**Manutenzione e correzioni**
- Sistemato export carenze, ora esporta secondo il filtro applicato

##### 23 giugno 2025 - versione 1.2.48
**Programmi didattici**
- Correzioni e miglioramenti su programmi svolti

**Manutenzione e correzioni**
- Correzioni e miglioramenti su carenze

##### 22 giugno 2025 - versione 1.2.48
**Programmi didattici**
- Aggiunta la funzionalità duplica programma svolto

**ATA, ferie, permessi e orario**
- Creata bozza pagina carenze lato studente

##### 21 giugno 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Carenze lato docenti completate
- Aggiunta pagina carenze su didattica

##### 20 giugno 2025 - versione 1.2.48
**Programmi didattici**
- Aggiornamento programmi materie e svolti

##### 8 giugno 2025 - versione 1.2.48
**Programmi didattici**
- Aggiornamenti e bug fix programmi e moduli

##### 5 giugno 2025 - versione 1.2.48
**Programmi didattici**
- Primo step gestione programmi materie con moduli

**Interfaccia e mobile**
- Corretta visibilità dettaglio sportello

**Manutenzione e correzioni**
- Corretto bug import sportelli
- Corretta posizione codice intestazione
- Reinserito file mancante
- Corretta visualizzazione tooltip sulle attiivtà

##### 26 aprile 2025 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiunto messaggio errore su controllo ore diaria

**Programmi didattici**
- Aggiunto nel JSON Programmi Materie e PdL estesi
- Aggiunta sezione Programmi Materie

**ATA, ferie, permessi e orario**
- Corretto salvataggio ore diaria su previste

**Manutenzione e correzioni**
- Aggiunto controllo diaria su previste
- Correzione testo

##### 25 aprile 2025 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Aggiornato vincolo data limite sportello

**Manutenzione e correzioni**
- Aggiornato percorso relativo template

##### 18 gennaio 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiunta colonna classe di concorso

**ATA, ferie, permessi e orario**
- Impostata categoria di default

**Manutenzione e correzioni**
- Piccola correzione

##### 16 gennaio 2025 - versione 1.2.48
**Programmi didattici**
- Lato segreteria aggiunta statistica sportelli programmati e fatti

##### 31 dicembre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Corretto invio mail per cancellazione sportello lato didattica

**MasterCom, studenti e NOIRC**
- Aggiunto classe di concorso docente dove mancava, nelle tabelle e del codice
- Correggo bug limite temporale prenotazione sportelli lato studenti

##### 1 dicembre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Updated send mail to new server

**ATA, ferie, permessi e orario**
- Aggiornata selezione criteri bonus

**Interfaccia e mobile**
- Corretto header file

**Manutenzione e correzioni**
- Corretto post controllo bonus
- Update Log library

##### 16 novembre 2024 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiornato testo cron promemoria studenti

**Interfaccia e mobile**
- Corretta visibilità ore riepilogo

**Manutenzione e correzioni**
- Aggiunta possibilità di iscrivere un singolo studente a più sportelli
- Corretto bug lato didattica ora si possono modificare presenti agli sportelli
- Aggiornato messagi cron

##### 10 novembre 2024 - versione 1.2.48
**Manutenzione e correzioni**
- Aggiornamento ore

##### 9 novembre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiornato invio mail sportelli studenti e docenti con nuovo comando send mail
- Creato comando per invio mail con autenticazione Google
- Caricato PHP Mailer

**MasterCom, studenti e NOIRC**
- Corretto controllo studenti assenti agli sportelli

**Utenti, ruoli e sessioni**
- Inserito nel JSON parametri per autenticazione Google

**Interfaccia e mobile**
- Aggiornato previste con visibilità ore previste orientamento nel riepilogo

**Manutenzione e correzioni**
- Aggiornato riepilogo ore orientamento nelle fatte del docente

##### 4 novembre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Manda la mail di notifica assenza SOLO per gli sportelli didattici e non per le altre attività

##### 3 novembre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiornato stile mail promemoria studente

**ATA, ferie, permessi e orario**
- Aggiornata descrizione link google per calendar

**Manutenzione e correzioni**
- Aggiornato link calendar con descrizione in base alla categoria

##### 2 novembre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Creata pagina email notifica assenza a sportello per studente

**MasterCom, studenti e NOIRC**
- Aggiunta possibilità didattica di iscrivere elenco di studenti ad una serie di attività in blocco

**Biglietti ed eventi**
- Lato didattica si possono vedere solo le attività con prenotazioni

**Interfaccia e mobile**
- Aggiunto filtro ISCRITTO per le sole attività a cui lo studente è iscritto

##### 1 novembre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Corretto subject mail sportelli docenti

**MasterCom, studenti e NOIRC**
- Corretto invio promemoria docente per no studenti

**Interfaccia e mobile**
- Aggiunta visibilità e gestione categoria lato docente

**Manutenzione e correzioni**
- Gli sportelli bloccati ora sono apribili in sola lettura del docente
- Lato didattica aggiunta categoria ad import e nuovo sportello

##### 31 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiornati template ed email inviate alla cancellazione sportello
- Migliorato aspetto e subject mail lato studente per sportelli
- Corretta notifica mail al docente

**MasterCom, studenti e NOIRC**
- Aggiunto filtro per categoria a studenti

**Manutenzione e correzioni**
- Rimosso file in piu

##### 29 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiunto messaggio di conferma docente prima di firmare

**MasterCom, studenti e NOIRC**
- La didattica ora può modificare il flag presenza degli studenti agli sportelli
- Corretto calcolo numero studenti iscritti allo sportello nella pagina di dettaglio

##### 21 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Inserito nome istituto mail annullamento sportello

##### 20 ottobre 2024 - versione 1.2.48
**Interfaccia e mobile**
- Correzione bug ore fuis previste

##### 17 ottobre 2024 - versione 1.2.48
**Manutenzione e correzioni**
- Aggiornamento viaggi docenti fatto da Paolo
- Allineamento colonna materia sportello didattica

##### 16 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Rimossi messaggi INFO di debug

**Manutenzione e correzioni**
- Corretta inizializzazione sqlList in caso di errore

##### 15 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Migliorato template mail cancellazione sportello con elenco studenti aggiornato
- Migliorato testo messaggi INFO
- Modificato nel template delle mail il nome dell'istituto ora preso dal json

**ATA, ferie, permessi e orario**
- Corretto ordine stampa della data

**Manutenzione e correzioni**
- Ora i docenti non possono modificare i vecchi sportelli
- Aggiunto invio promemoria studente il giorno prima
- Corretto errore query
- Aggiunta colonna categoria sportelli, argomento lato docente appare solo se scelto dal docente

##### 13 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Corretto messaggio email
- Aggiunto campo BCC email inviate per sportello configurabile da JSON

**Manutenzione e correzioni**
- Creato promemoria DOCENTE da inviare la mattina dello sportello - da usare con CRON

##### 12 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiornati template mail quando studente di cancella dallo sportello
- Aggiunta gestione invio mail quando docente cancella sportello
- Modificato messaggio di conferma per la cancellazione dello sportello
- Rimosso pulsante firma, aggiunto messaggio di alert se si tenta di firmare sportello vuoto
- Adattato codice per campi necessari per invio email
- Creati template email iscrivi e cancella
- Altre 1 modifiche minori nella stessa area.

**Manutenzione e correzioni**
- Aggiornato stile icona lock
- Sportello cancellato o firmato non più modificabile dal docente. Aggiunto tooltips
- Corrette segnalazioni errore session logout Google
- Rimossi file non più necessari
- Rimosso codice info non più necessario
- Completato codice iscrizione studente a sportello
- Altre 4 modifiche minori nella stessa area.

##### 10 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiunto campo email nel JS
- Aggiunto campo email in read records
- Aggiunto DST ora sportello - aggiornato invio mail studenti e docenti
- Aggiunto <br> messaggio di errore

##### 9 ottobre 2024 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Aggiunta possibilità di importare sportelli multipli che hanno lo stesso orario

**Interfaccia e mobile**
- Corretta visibilità tooltip cancella prenotazione

**Manutenzione e correzioni**
- Corretto campo errore e risolto bug form inserimento nuovo sportello
- Migliorate etichette stato sportelli lato docente e didattica

##### 8 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Corretta visibilità messaggio di errore

**MasterCom, studenti e NOIRC**
- Aggiornato codice sportelli con il campo classe_id del database

**ATA, ferie, permessi e orario**
- Aggiornata visibilità campo online in base al valore nel JSON

**Manutenzione e correzioni**
- Aggiunto flag json docente puo modificare dati sportello
- Sistemato pagina dettaglio sportelli docente con lista classi da DB

##### 7 ottobre 2024 - versione 1.2.48
**Manutenzione e correzioni**
- Completati status sportelli lato studente

##### 6 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Corretto formato HTML mail inviata a studente

##### 5 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Creata mail completa quanto studente si iscrive a sportello
- Aggiunto invio mail docente quando si iscrive uno studente

**MasterCom, studenti e NOIRC**
- Aggiunto filtro classe sportelli lato didattica
- Aggiunto filtro classe_id per gli sportelli - nb:aggiungere campo classe_id nella tabella sportelli

**Interfaccia e mobile**
- Dettaglio sportello didattica - inserito flag visibilità online clil orientamento

**Manutenzione e correzioni**
- Nell'elenco degli sportelli ora appare l'argomento se scelto dallo studente
- Aggiunti nuovi status lato studente
- Aggiunto stato "posti disponibilil" e "posti esauriti" lato didattica
- Migliorato stile pannello

##### 4 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Quando il docente vuole cancellare uno sportello appare un messaggio di conferma prima di procedere

**MasterCom, studenti e NOIRC**
- Aggiunta opzione selezione tipo classe

**Biglietti ed eventi**
- Aggiunta colonna max prenotazioni, aggiunto stato "posti diponbiili " e "posti esauriti"

**Interfaccia e mobile**
- Corretta visibilità sportelli cancellati lato docente
- Aggiunti flag json per visibilità sezione sportello docente
- Aggiunti pulsante visibilità sportelli cancellati

**Manutenzione e correzioni**
- Lato studente se lo sportello è cancellato non compaiono gli altri status
- Corretto campo max iscrizioni
- Aggiunto status posti esauriti allo sportello lato studente
- Aggiornamento js per sportelli cancellati
- Modifiche diaria da Paolo Scapin
- Correzione formattazione campo tabella
- Altre 1 modifiche minori nella stessa area.

##### 26 settembre 2024 - versione 1.2.48
**Manutenzione e correzioni**
- Stampa ridotta piano di lavoro

##### 19 settembre 2024 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Lato studente invece che numero di studenti prenotati ora vede numero di posti disponibili allo sportello

**ATA, ferie, permessi e orario**
- Lato studente la riga dello sportello cancellato appare barrata

**Manutenzione e correzioni**
- Lato studente creato toggle per scegliere se vedere o meno gli sportelli cancellati
- Adesso lo studente vede gli sportelli cancellati ed appare una label rossa
- Lato docente migliorato aspetto tabella sportelli
- Lato didattica migliorato aspetto tabella sportelli
- Migliorato aspetto formattazione testo tabella sportelli
- Aggiunto file .htaccess nel gitignore

<!-- gestore-git-changelog-1.2.391:end -->
<!-- gestore-git-changelog-1.2.328:start -->
## Version 1.2.328 - 2 mag 2026
##### Sintesi
- Aggiornamento generato automaticamente dai commit Git successivi all'ultimo aggiornamento del changelog.
- Periodo commit: 2024-09-19 - 2026-05-02.
- Commit analizzati: 620.

- Ticket, Telegram e comunicazioni: 89 modifiche, tra cui Migliorata gestione messaggi telegram ticket; Aggiunta gestione ticket via mail per docenti.
- Programmi didattici: 31 modifiche, tra cui Migliorata gestione con live preview dei programmi iniziali; Aggiunta live preview programmi svolti e guida utente laterale.
- MasterCom, studenti e NOIRC: 79 modifiche, tra cui Corretti bug confronto studenti e genitori; Corretta visibilità pulsante biglietti da telefono lato studenti.
- Biglietti ed eventi: 9 modifiche, tra cui Corretta visualizzazione prenotazioni biglietti; Minor fix gestione biglietti eventi.
- ATA, ferie, permessi e orario: 101 modifiche, tra cui Corretto errore calcolo export ferie, rimosso colore intensità permessi ferie, aggiungto js no cache; Rimosso colore per celle con tanti permessi - reso caricamento js no cache.
- Utenti, ruoli e sessioni: 26 modifiche, tra cui Checksession autorizza solo utenti con attivo = 1; Aggiornati problemi sessione utente quando admin assume ruolo.
- Interfaccia e mobile: 34 modifiche, tra cui Migliorato header didattica; Corretti colori mobile.
- Manutenzione e correzioni: 242 modifiche, tra cui Caricamento js con datetime per problemi cache; Modificati dati visibile singolo evento attivo.

##### Dettaglio

##### 2 maggio 2026 - versioni 1.2.325 - 1.2.327
**Ticket, Telegram e comunicazioni**
- Migliorata gestione messaggi telegram ticket

**Programmi didattici**
- Migliorata gestione con live preview dei programmi iniziali
- Aggiunta live preview programmi svolti e guida utente laterale

##### 30 aprile 2026 - versioni 1.2.321 - 1.2.324
**Ticket, Telegram e comunicazioni**
- Aggiunta gestione ticket via mail per docenti
- Aggiunto accesso a mail gestore per invio e lettura mail
- Migliorato testo messaggi telegram admin per import

**MasterCom, studenti e NOIRC**
- Corretti bug confronto studenti e genitori

##### 29 aprile 2026 - versioni 1.2.312 - 1.2.320
**Programmi didattici**
- Aggiunto settings json per visibilità programmi svolti al coordinatore
- Minor bug programmi svolti
- Programmi svolti, spostate sezioni metodologie fuori dai moduli
- Corretta generazione e stampa programma svolto quinte

**MasterCom, studenti e NOIRC**
- Corretta visibilità pulsante biglietti da telefono lato studenti
- Gestione base noirc mastercom

**ATA, ferie, permessi e orario**
- Corretto errore calcolo export ferie, rimosso colore intensità permessi ferie, aggiungto js no cache
- Rimosso colore per celle con tanti permessi - reso caricamento js no cache

**Manutenzione e correzioni**
- Caricamento js con datetime per problemi cache

##### 28 aprile 2026 - versioni 1.2.300 - 1.2.311
**Ticket, Telegram e comunicazioni**
- Migliorata visibilità ticket lato utente e corretta gestione orario eventi

**MasterCom, studenti e NOIRC**
- Introdotta gestione noirc
- Corretto aggiornamento noirc studenti
- Aggiunte nuove funzioni integrazione con mastercom
- Completato allineamento dati mastercom - gestore
- Sql creazione tabelle gestione dati da mastercom
- Aggiunta autenticazione docente gestione MasterCom
- Altre 2 modifiche minori nella stessa area.

**Biglietti ed eventi**
- Corretta visualizzazione prenotazioni biglietti
- Minor fix gestione biglietti eventi

**Manutenzione e correzioni**
- Modificati dati visibile singolo evento attivo

##### 27 aprile 2026 - versioni 1.2.295 - 1.2.299
**Programmi didattici**
- Aggiunto setting json per abilitare modifica programmi a coordinatore dipartimento
- Aggiornata sezione obiettivi minimi - coordinatore può modificare i programmi
- Docente coordinatore non può modificare programmia altri docenti
- Aggiornati programmi svolti, aggiunta apposita logica per classi quinte

**Utenti, ruoli e sessioni**
- Checksession autorizza solo utenti con attivo = 1

##### 23 aprile 2026 - versioni 1.2.283 - 1.2.294
**MasterCom, studenti e NOIRC**
- Migliorata UI pagina CLASSE e DOCENTE

**ATA, ferie, permessi e orario**
- Migliorate informazioni riportate nel riepilogo permessi personale ata
- Sistemata possibilità sui permessi di avere piu righe in un unico permesso
- Migliorata ricerca dentro orario aule classi e docenti
- Aggiornata gestione orario lato segreteria ATA
- Migliorata gestione orario permessi lato personale ata
- Migliorata UI pagina AULE
- Altre 1 modifiche minori nella stessa area.

**Utenti, ruoli e sessioni**
- Aggiornati problemi sessione utente quando admin assume ruolo
- Admin interpreta docente, corretta gestione sessione pagine docente
- Corretta gestione sessione scaduta

**Manutenzione e correzioni**
- Lato studente serale, gli sportelli apre di default la categoria preora

##### 21 aprile 2026 - versione 1.2.282
**ATA, ferie, permessi e orario**
- Cambiato formato orario permessi solo a cifre no orologio grafico

##### 20 aprile 2026 - versioni 1.2.275 - 1.2.281
**Ticket, Telegram e comunicazioni**
- Corretto formato ora sportello delle mail

**MasterCom, studenti e NOIRC**
- Corretto calcolo orario permessi genitori

**Biglietti ed eventi**
- Miglioramento gestione biglietti eventi

**ATA, ferie, permessi e orario**
- Migliorata gestione ferie lato segrata

**Manutenzione e correzioni**
- Aggiornato comportamento pulsanti cruscotto, aggiunto pulsanti registrato - da registrare
- Modificato cruscotto ora sono filtri sommativi
- Corretto formato ora legale

##### 19 aprile 2026 - versioni 1.2.264 - 1.2.274
**Ticket, Telegram e comunicazioni**
- Integrata pagina tickets in header admin
- Aggiunto tickets in gestore su menu admin - rivisto menu admin
- Aggiunta gestione ticket trentino volley
- Aggiornato send mail con ok di ritorno ed allega multi allegato

**Biglietti ed eventi**
- Migliorata visibilità eventi dai vari utenti anche con impersona
- Aggiunta gestione eventi lato admin, e prenotazione lato utenti

**ATA, ferie, permessi e orario**
- Sistemata header docente

**Utenti, ruoli e sessioni**
- Migliorate funzioni agisci come

**Interfaccia e mobile**
- Migliorato header didattica

**Manutenzione e correzioni**
- Aggiornato algoritmo e mappe per tutte le tribune del palazzetto
- Aggiunti pacchetti con composer

##### 17 aprile 2026 - versioni 1.2.262 - 1.2.263
**Utenti, ruoli e sessioni**
- Corretta visibilità nome docente modalità admin ore previste e fatte

**Manutenzione e correzioni**
- Corretto nome aula maiuscolo, nomi docenti a capo

##### 16 aprile 2026 - versioni 1.2.255 - 1.2.261
**Biglietti ed eventi**
- Pagina default eventi mobile
- Creata versione mobile orario ed eventi

**ATA, ferie, permessi e orario**
- Migliorata grafica e layout orario mobile
- Abilitato pulsante orario personale ata
- Aggiunto pulsante permessi anche dentro il modale del permesso

**Interfaccia e mobile**
- Corretti colori mobile

**Manutenzione e correzioni**
- Bug fix flag permesso registrato ora viene salvato

##### 14 aprile 2026 - versioni 1.2.251 - 1.2.254
**ATA, ferie, permessi e orario**
- Aggiunto flag reegistrazione permessi lato segreteria

**Manutenzione e correzioni**
- Reso dinamico caricamento css
- Aggiunto dettaglio permesso sotto al tipo
- Rimosso badge Bozza lato segreteria

##### 12 aprile 2026 - versioni 1.2.237 - 1.2.250
**Ticket, Telegram e comunicazioni**
- Rimossa stampa messaggi debug
- Correzioni logica invio mail
- Sistemata invio mail lato segreteria

**ATA, ferie, permessi e orario**
- Minor bug - aggiunto nome approvatore permessi
- Aggiornato export in formato xls personale ata ed aggiornata dashboard con badge parziali
- Aggiornato e migliorata stampa pdf permessi
- Messo link diverso per help segreteria ATA
- Aggiornata visibilita versione come tooltip
- Corretta visibilità approvazione giorni di ferie
- Altre 1 modifiche minori nella stessa area.

**Utenti, ruoli e sessioni**
- Aggiunto ruolo ras

**Manutenzione e correzioni**
- Migliorato css tabella
- Aggiunti export csv lato segreteria
- Rimosso codice non piu necessario

##### 11 aprile 2026 - versioni 1.2.227 - 1.2.236
**Ticket, Telegram e comunicazioni**
- Sistemato sistema invio mail lato personale
- Lato utente invio mail quando fa richiesta permesso

**ATA, ferie, permessi e orario**
- Migliorata grafica tooltip calendario
- Lato utente aggiunta possibilità di una nuova richiesta di ferie
- Creata dashboard ferie e corretti bug
- Migliorata logica approvazione ferie
- Aggiunto file excel esempio import personale ATA

**Manutenzione e correzioni**
- Minor bug fix
- Corretto filtro tabella
- Aggiunta funzione import personale

##### 10 aprile 2026 - versioni 1.2.220 - 1.2.226
**ATA, ferie, permessi e orario**
- Aggiunto export personale ata
- Lato segreteria ata migliorata gestione permessi
- Richiesta ferie migliorata, aggiunta rimetti in bozza
- Migliorati permessi - pulsanti corretti - rimessa in bozza
- Migliorata grafica ferie - colori
- Aggiornati permessi e ferie lato personale ATA sia telefono che desktop
- Altre 1 modifiche minori nella stessa area.

##### 3 aprile 2026 - versioni 1.2.214 - 1.2.219
**Ticket, Telegram e comunicazioni**
- Aggiunte ed aggiornata pagina sostituzione in miniapp telegram
- Spostati messaggi telegram su log telegram
- Conclusa app telegram con sistema ticketing
- Aggiunto file telegram log
- Aggiunto log Telegram

**Manutenzione e correzioni**
- Rimosso file non piu usato

##### 2 aprile 2026 - versioni 1.2.208 - 1.2.213
**Ticket, Telegram e comunicazioni**
- Creata miniapp telegram GestOre
- Aggiornato formato ora nel nome del ticket
- Aggiunti parametri telegram
- Aggiornato e completato sistema di ticketing telegram

**Manutenzione e correzioni**
- Aggiunto log extra
- Script che cancella i topic piu vecchi di tot giorni

##### 1 aprile 2026 - versione 1.2.207
**Utenti, ruoli e sessioni**
- Risolto problema logout sessione dando un nome alla sessione

##### 31 marzo 2026 - versioni 1.2.201 - 1.2.206
**Ticket, Telegram e comunicazioni**
- Migliorata gestione ticket ora accetta anche file
- Creato gruppo admin GestOre, aggiunto sistema ticketing, notifiche sostituzioni ora a gruppo admin con abilitazione
- Inseriti i messaggi di scarto dell'import gestore nel riepilogo telegram
- Corretto formato data nelle mail delle sostituzioni

**ATA, ferie, permessi e orario**
- Lato genitore permessi corretto bug ora rientro null

**Manutenzione e correzioni**
- Aggiornato loggin - nome dei file ed aggiunto rotatelog su import sostituzioni

##### 30 marzo 2026 - versioni 1.2.190 - 1.2.200
**MasterCom, studenti e NOIRC**
- Aggiornata sicurezza ora c'è un controllo che i dati degli studenti richiesti corrispondano al genitore che ha fanno l'accesso, quindi non solo fidandosi del parametro passato alla POST

**ATA, ferie, permessi e orario**
- Corretto login personale ata
- Permessi lato personale ATA aggiornato codice
- Migliorata sicurezza lato studente e genitore per stampa carenza
- Caricata in git cartella error
- Migliorata sicurezza lato studente per carenze e sportelli
- Sportelli lato studente migliorata sicurezza
- Altre 2 modifiche minori nella stessa area.

**Manutenzione e correzioni**
- Corretto import che annulla e modifica solo le sostituzioni di oggi
- Corretto metodo esposizione parametri da GET a POST

##### 28 marzo 2026 - versione 1.2.189
**Manutenzione e correzioni**
- Correzione sostituzioni annullate o modificate

##### 27 marzo 2026 - versioni 1.2.184 - 1.2.188
**Ticket, Telegram e comunicazioni**
- Aggiornato import rimosso testing nelle mail

**MasterCom, studenti e NOIRC**
- Aggiornato agent, importa sostituzioni anche se non c'è abbinata la classe

**ATA, ferie, permessi e orario**
- Corretto login profilo segreteria ata

**Manutenzione e correzioni**
- Lato docente aggiunto pulsante filtra solo le sue sostituzioni
- Bug fix query

##### 24 marzo 2026 - versione 1.2.183
**Manutenzione e correzioni**
- Minor fix bug

##### 23 marzo 2026 - versioni 1.2.180 - 1.2.182
**Ticket, Telegram e comunicazioni**
- Completata integrazione invio messaggi Telegram per sostituzioni
- Impostata variabile dinamica su ora chiusura sportelli nella mail

**Manutenzione e correzioni**
- Rimosso file non necessario

##### 22 marzo 2026 - versioni 1.2.174 - 1.2.179
**ATA, ferie, permessi e orario**
- Aggiunte sostituzioni pagina orario
- Aggiornata list gitignore

**Manutenzione e correzioni**
- Aggiunto logging su import sostituzioni
- Allungato timeout import
- Minor bug fixes
- Creato EDT agent per importare le sostituzioni da pdf EDT

##### 19 marzo 2026 - versioni 1.2.162 - 1.2.173
**Ticket, Telegram e comunicazioni**
- La mail di notifica assenza agli studenti arriva solo se lo sportello è stato svolto

**MasterCom, studenti e NOIRC**
- Aggiornata assegnazione sportelli, il docente può cambiare data e classe, previo setting file json

**ATA, ferie, permessi e orario**
- Creata pagina segreteria ata
- Creata pagina personale ata
- Aggiunto ruolo personale ata e portineria

**Utenti, ruoli e sessioni**
- Aggiunto ruolo portineria in checksession

**Interfaccia e mobile**
- Corretto header quando si vede la pagina previste docente lato dirigente
- Aggiornati pulsanti su header vari

**Manutenzione e correzioni**
- Aggiunto totali per capitolo su tabella fatte, sistemato simbolo EURO
- Aggiunto totali per capitolo su tabella previste
- Corretto titolo pagina segreteria didattica
- Corretta denominazione sportello su MBApp

##### 18 marzo 2026 - versione 1.2.161
**ATA, ferie, permessi e orario**
- Orario MBAPP aggiunta sezione

##### 22 febbraio 2026 - versione 1.2.160
**ATA, ferie, permessi e orario**
- Aggiornamento funzioni orario

##### 19 febbraio 2026 - versione 1.2.159
**Manutenzione e correzioni**
- Corretta gestione aule non piu disponibili su sportelli

##### 13 febbraio 2026 - versioni 1.2.147 - 1.2.158
**Ticket, Telegram e comunicazioni**
- Aggiornato template mail inviate per sportelli lato studente
- Aggiunto log CRON e aggiornato invio mail

**ATA, ferie, permessi e orario**
- Corretta funziona salvataggio sportello lato docente
- Aggiornato stile header ata e admin
- Aggiunti nuovi settings cron e ata

**Interfaccia e mobile**
- Corretti require

**Manutenzione e correzioni**
- Corretta funziona cancella prenotazione MBApp
- Corretto funzionamento invio promemoria docente
- Aggiunte nuove funzioni
- Corretto settings MBApp
- Corretto motivo sportello
- In caso di assenza docente lo sportello viene annullato, via cron

##### 6 febbraio 2026 - versioni 1.2.145 - 1.2.146
**Manutenzione e correzioni**
- Corretta gestione apici bonus lato dirigente
- Corretta selezione multipla bonus lato docente

##### 5 febbraio 2026 - versioni 1.2.143 - 1.2.144
**ATA, ferie, permessi e orario**
- Corretto salvatagglio nome sportello in MBApp

**Manutenzione e correzioni**
- Corretto filtro sportelli

##### 4 febbraio 2026 - versioni 1.2.141 - 1.2.142
**Manutenzione e correzioni**
- Quando il docente cancella lo sportello ne viene creato uno gemello in bozza 14gg dopo
- Corretti bug lato studente iscrizione sportelli

##### 3 febbraio 2026 - versioni 1.2.139 - 1.2.140
**Interfaccia e mobile**
- Corretta visibilità dati su sportelli cancellati lato studente

**Manutenzione e correzioni**
- Sistemato bug cancellazione sportello lato docente

##### 2 febbraio 2026 - versione 1.2.138
**Manutenzione e correzioni**
- Minor correction

##### 1 febbraio 2026 - versioni 1.2.131 - 1.2.137
**Ticket, Telegram e comunicazioni**
- Aggiornata grafica mail notifica docente ore previste
- Aggiornata grafica mail sportelli
- Aggiunto file comune per invio mail con grafica uguale
- Aggiornata grafica mail cancellazione sportello docente - correggo bug su mail genitori
- Aggiunto messaggio di conferma su aula prima di creare sportello lato docente

**Manutenzione e correzioni**
- Adesso gli sportelli deserti vengono rimessi in bozza 14 gg in avanti, stessa cosa gli sportelli in bozza passati
- Corretto errore MBAPP su creazione nuovo sportello

##### 29 gennaio 2026 - versione 1.2.130
**Manutenzione e correzioni**
- Aggiunto giorno della settimana sulle date degli sportelli

##### 28 gennaio 2026 - versione 1.2.129
**Programmi didattici**
- Aggiunta possibilità modifica programmi al coordinatore di dipartimento

##### 26 gennaio 2026 - versione 1.2.128
**Manutenzione e correzioni**
- Correzione titolo sportello

##### 25 gennaio 2026 - versioni 1.2.123 - 1.2.127
**Ticket, Telegram e comunicazioni**
- Aggiunto invio mail annullamento prenotazione aula sportello su collaboratori

**Manutenzione e correzioni**
- Aggiornato template promemoria sportello lato studente
- Modale ora si chiude dove save
- Completato aggiornamento gestione sportelli lato didattica
- Corretto sportello delete

##### 22 gennaio 2026 - versioni 1.2.119 - 1.2.121
**Interfaccia e mobile**
- Aggiornato stile header per evitare sovrapposizioni dei menu su schermi piccoli

**Manutenzione e correzioni**
- Corretta query su campo attivo
- Corretto calcolo numero posti disponibili negativi

##### 20 gennaio 2026 - versione 1.2.118
**Manutenzione e correzioni**
- Lo sportello in bozza lato docente ora può essere impostato a due ore, e viene diviso automaticamente

##### 19 gennaio 2026 - versioni 1.2.116 - 1.2.117
**Ticket, Telegram e comunicazioni**
- Aggiornata modalità prenotazione sportelli con invio mail lato docente - corretti bug
- Migliorata grafica email sportelli lato docente

##### 17 gennaio 2026 - versioni 1.2.112 - 1.2.115
**ATA, ferie, permessi e orario**
- Ora gli sportelli in bozza possono essere assegnati dai docenti stessi, scegliendo l'aula che viene prenotata su MBApp

**Manutenzione e correzioni**
- Prenotazione aula su MBApp
- Connessione DB MBApp
- Verifica nel portale MBApp quali aule sono libere per una prenotazione

##### 15 gennaio 2026 - versioni 1.2.108 - 1.2.111
**MasterCom, studenti e NOIRC**
- Aggiunti sportelli-bozza non visibili a genitori e studenti

**ATA, ferie, permessi e orario**
- Aggiornata assegnazione sportello bozza al docente

**Interfaccia e mobile**
- Aggiunta visibilità sportelli-bozza docenti

**Manutenzione e correzioni**
- Aggiunti sportelli-bozza lato didattica

##### 14 gennaio 2026 - versione 1.2.107
**Utenti, ruoli e sessioni**
- Corretta visibilità pulsante esame seconda sessione

##### 11 gennaio 2026 - versioni 1.2.102 - 1.2.106
**Ticket, Telegram e comunicazioni**
- Migliorati messaggi interfaccia nel caso di mail errata

**Utenti, ruoli e sessioni**
- Aggiunti corsi per utenti esterni
- Aggiunta sezione utente esterno
- Aggiunta sezione utenti esterni

**Manutenzione e correzioni**
- Login genitore viene registrato datetime e IP

##### 10 gennaio 2026 - versioni 1.2.85 - 1.2.101
**Ticket, Telegram e comunicazioni**
- Aggiunti messaggi debug sessione - corretti redirect con sessione scaduta

**MasterCom, studenti e NOIRC**
- Aggiunta login google anche ai genitori
- Nuovo filtro sportelli lato genitore in base alla classe dello studente
- Nuovo filtro sportelli lato studente in base alla classe dello studente
- Sportelli, miglioramento logica filtro per gruppo classe lato docente e didattica

**ATA, ferie, permessi e orario**
- Rinnovata grafica index.php
- Aggiornata intestazione copyright su tutti i file modificati finora

**Utenti, ruoli e sessioni**
- Corretta posizione ruolorichiesto per evitare blank page
- Corretti calcoli ed aggiunta gestione sessione

**Interfaccia e mobile**
- Corretta visibilità header

**Manutenzione e correzioni**
- Correzioni su grafica login
- Sportelli lato docente, aggiunta colonna nome docente, corretta non modifica sportelli non di proprietà
- Corretti calcoli ed aggiunto simboli euro
- Corretto formato cifre e simbolo euro
- Rimosso checksession non necessario
- Corretta icona login page

##### 8 gennaio 2026 - versioni 1.2.80 - 1.2.84
**MasterCom, studenti e NOIRC**
- Correzione bug aggiunta studenti a nuovo corso

**ATA, ferie, permessi e orario**
- Migliorata gestione scadenza sessione

**Utenti, ruoli e sessioni**
- Sistemati problemi sessione ed utente con ore previste Dirigente

**Manutenzione e correzioni**
- Aggiunta funzione duplica sportello, migliorate larghezze colonne sportelli
- Corretto duplicazione corsi

##### 7 gennaio 2026 - versioni 1.2.77 - 1.2.79
**Utenti, ruoli e sessioni**
- Sostituito termine tentativo con sessione

**Manutenzione e correzioni**
- Ultime correzione su gestione firme esami
- Sistemati numerosi bug su gestione corsi carenze ed itinere

##### 5 gennaio 2026 - versione 1.2.76
**Manutenzione e correzioni**
- Estesa gestione dei corsi a più docenti, ognuno con la propria firma

##### 4 gennaio 2026 - versioni 1.2.70 - 1.2.75
**ATA, ferie, permessi e orario**
- Aggiornata intestazione file
- Corretta ed ampliata stampa storico bonus
- Corretto salvataggio e modifica indicatori

**Interfaccia e mobile**
- Corretta visibilità allegati bonus lato dirigente

**Manutenzione e correzioni**
- Aggiunta stampa PDF ed export CSV dei criteri lato dirigente
- Completato bonus lato docente con aggiunta del caricamento di allegati

##### 3 gennaio 2026 - versioni 1.2.67 - 1.2.69
**ATA, ferie, permessi e orario**
- Completata gestione bonus lato dirigente

**Manutenzione e correzioni**
- Aggiornato bonus lato docenti con nuove modalità
- Completato lato dirigente aggiunta anno scolastico per il bonus
- Aggiunta selezione anno su bonus docenti lato dirigente

##### 30 dicembre 2025 - versione 1.2.67
**Manutenzione e correzioni**
- Aggiunta stampa carenze incomplete sia csv che pdf

##### 29 dicembre 2025 - versioni 1.2.64 - 1.2.66
**ATA, ferie, permessi e orario**
- Aggiornata visibilità carenze anche lato genitore
- Aggiornata visibilità esito carenze lato studente
- Corretta ed aggiornata struttura gestione corsi carenze e secondo tentativo lato didattica

##### 24 dicembre 2025 - versioni 1.2.58 - 1.2.63
**Programmi didattici**
- Corretto invio sollecito programmi iniziaili ai docenti
- Migliorate regole formattazione testo programmi materie

**MasterCom, studenti e NOIRC**
- Sportelli lato docente, aggiunti filtri categoria , materia e classe

**Manutenzione e correzioni**
- Lato didattica corretta categoria iniziale sportelli su sportelli didattici
- Ora il docente può vedere gli sportelli di tutti i docenti
- Dettaglio studente i campi vengono svuotati all'inizio per evitare falsi valori rimasti in memoria

##### 23 dicembre 2025 - versione 1.2.57
**MasterCom, studenti e NOIRC**
- Corretta visibilità figli nella pagina genitori

##### 18 dicembre 2025 - versioni 1.2.50 - 1.2.56
**MasterCom, studenti e NOIRC**
- Migliorata query elenco genitori
- Cancellazione studente, ora anche i genitori vengono correttamente disabilitati

**Manutenzione e correzioni**
- Feature: ora da dettaglio genitore, se clicco su nome studente passo al dettaglio studente
- Feature: dal dettaglio studente posso collegare un genitore esistente
- Feature: Dal dettaglio genitore ora posso collegare uno studente esistente
- Feature: da scheda studente posso passare direttamente a scheda genitore
- Aggiunto incremento versione automatico

##### 16 dicembre 2025 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Ora la data dei permessi salta il sabato e la domenica

**Utenti, ruoli e sessioni**
- Lato admin ora posso cancellare uno studente iscritto ad uno sportello

**Manutenzione e correzioni**
- Ora posso duplicare un corso esistente

##### 15 dicembre 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Correzioni varie

##### 31 ottobre 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Minor bugs

##### 20 ottobre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Corretto ricerca classe in sql

**Interfaccia e mobile**
- Corretto percorsi file negli header

**Manutenzione e correzioni**
- Corretto stampa carenza con doppio livello liste puntate
- Ora carenze è disabilitato all'inizio

##### 15 ottobre 2025 - versione 1.2.48
**Programmi didattici**
- Aggiunti Programmi Iniziali lato didattica e docenti

**Manutenzione e correzioni**
- Aggiornato la stampa con l'ordine del numero del modulo

##### 14 ottobre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Corretto filtro sportelli lato mobile studenti

**ATA, ferie, permessi e orario**
- Migliorata conferma permessi lato didattica

##### 13 ottobre 2025 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Corretto codice invio mail ai genitori per sportelli

**Interfaccia e mobile**
- Corretta visibilità colonne tabella

**Manutenzione e correzioni**
- Corretto riferimento a studente_id

##### 11 ottobre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Sistemati errori sportelli studenti e genitori

**Interfaccia e mobile**
- Corretto require file errore

**Manutenzione e correzioni**
- Migliorato aspetto modale sportello
- Corretta visualizzazione dettaglio sportelli lato didattica
- Correzioni su import sportelli
- Aggiornato sportelli lato docente
- Completato aggiornamento sportelli lato studente

##### 10 ottobre 2025 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiunta funzione send mail con CC
- Aggiornata data mail promemoria al docente

**MasterCom, studenti e NOIRC**
- Correzioni sportelli lato studenti

**ATA, ferie, permessi e orario**
- Corretto refuso in query permessi

**Manutenzione e correzioni**
- Lavori in corso su sportelli per migliorare codice
- Correzioni su sportelli lato genitore
- Aggiornato codice invio promemoria sportello docente
- Corretta gestione apostrofo nella materie quando cancello sportello

##### 8 ottobre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Lato genitore, si vedevano gli studenti che non frequentano più

**Utenti, ruoli e sessioni**
- Da admin ora il logout ti fa uscire non andare alla pagina admin
- Aggiunto studente id sul nome utente in alto a destra

**Manutenzione e correzioni**
- Logout da impersona chiude la finestra
- Aggiunta voce studente esterno al posto di docente quando la carenza è di uno studente trasferito

##### 4 ottobre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiunto secondo tentativo lato genitori e studenti

**Manutenzione e correzioni**
- Aggiunto secondo tentativo esami corsi

##### 3 ottobre 2025 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Adesso i corsi hanno data-ora inizio e data-ora fine per ogni lezione
- Modificato checksession per maggiore durata sessione

**Manutenzione e correzioni**
- Gli esami dei corsi ora hanno un inizio ed una fine

##### 1 ottobre 2025 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Corretto calcolo classe nell'invio mail cancellazione sportello

**Programmi didattici**
- Corretta visibilità programmi svolti
- Aggiornata visibilità programmi svolti

**MasterCom, studenti e NOIRC**
- Aggiornata visibilità esito carenze lato genitori

**ATA, ferie, permessi e orario**
- La forma esame se non è compilata completamente non te la lascia salvare

**Interfaccia e mobile**
- Visibilità esito esami lato studente
- Corretta visibilità carenze lato studente desktop
- Aggiunta visibilità risultato carenze lato studente

**Manutenzione e correzioni**
- Sempre carenze lato genitore
- Sempre firma esame docente
- Aggiunta firma docente su esami
- Corretta cancellazione di una carenza esistente
- Aggiunte opzioni mancanti nel template
- Aggiunti corsi in itinere

##### 24 settembre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Corretto calcolo classe studente

**Manutenzione e correzioni**
- Corretto bug nome docente con apostrofo

##### 23 settembre 2025 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiornato invio mail con mail google di dominio

**MasterCom, studenti e NOIRC**
- Sportelli studenti visibili solo utente test

**Interfaccia e mobile**
- Rimosso sportelli da header segreteria

##### 21 settembre 2025 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiunto invio mail esiti ai coordinatori - e lista esami incompleto per segreteria

##### 20 settembre 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Aggiunta funzione esporta esito esami
- Aggiunto componente phpSpreadsheet

##### 14 settembre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Key mastercom
- Primi test con mastercom

**Manutenzione e correzioni**
- Aggiunta sezione esami corsi carenze

##### 13 settembre 2025 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Completata gestione permessi lato segreteria didattica

**Manutenzione e correzioni**
- Corretto problema logout

##### 11 settembre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Sistemata visibilità menu genitori con json

**ATA, ferie, permessi e orario**
- Inseriti file permessi lato didattica
- Sistemato permessi.js lato genitore
- Sistemati permessi lato genitore

**Interfaccia e mobile**
- Corretto menu header didattica

**Manutenzione e correzioni**
- Rimossi console log
- Nuovi config nel json

##### 10 settembre 2025 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Corretta funziona modifica data corso

##### 6 settembre 2025 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Migliorata pulizia sessione con logout

**Utenti, ruoli e sessioni**
- Aggiunto CF studente in sessione
- Correzione ruolo con impersona
- Corretto logout in base al ruolo impersonato
- Aggiunta funzione impersonaRuolo

**Manutenzione e correzioni**
- Pulsante logout adeguato ad impersona
- Corretta visibili con impersona docente
- Corretto login genitore senza figli
- Aggiunto campo codice fiscale mancante

##### 4 settembre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiornato figli genitori, visibili solo se figlio è attivo

**ATA, ferie, permessi e orario**
- Corretta query salvataggio modifica studente

**Manutenzione e correzioni**
- Se genitore non ha figli al login viene dato errore

##### 2 settembre 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Corretto inserimento manuale nuovi studenti

**Manutenzione e correzioni**
- Correzioni minori su inserimento studente
- Ora studente ha campo Esterno per quando si inserisce uno studente trasferito
- Corretta aggiunta manuale carenze

##### 31 agosto 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Aggiunta firma lezione corso
- Aggiunto config json per abilitare modifica corso ai docenti
- Cambiato nome file
- Aggiornati corsi, ora funziona, possibili miglioramenti ancora

##### 28 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Correzione carenze genitori
- Corretto import genitori
- Aggiornata versione mobile carenze studenti

**ATA, ferie, permessi e orario**
- Correzione gestione permessi

**Manutenzione e correzioni**
- Progressi su gestione corsi
- Aggiornato carenze lato didattica

##### 26 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiunto filtro carenze che seleziona gli studenti con carenze

**ATA, ferie, permessi e orario**
- Sistemata gestione corsi lato didattica

**Manutenzione e correzioni**
- Completato filtro corso per carenze
- Salva nel db il campo carenze dei corsi
- Minor
- Aggiunto file js corsi

##### 25 agosto 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Update
- Sviluppo modale corsi
- Aggiornamento config json

##### 24 agosto 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Sviluppo pagina corsi
- Aggiunta sezione corsi in didattica
- Corretto anno nella generazione del testo della carenza

##### 23 agosto 2025 - versione 1.2.48
**Programmi didattici**
- Aggiunta selezione anno su programmi svolti

**MasterCom, studenti e NOIRC**
- Correzioni su import genitori e studenti

**Manutenzione e correzioni**
- Aggiunta selezione anno su carenze didattica
- Aggiunta selezione anno carenze lato genitore
- Aggiunta selezione anno carenze lato studente
- Ora le carenze si vedono quelle di qs anno e quello precedente

##### 22 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Correzione selezione studenti su carenze genitori

**Manutenzione e correzioni**
- Cancellato error log
- Minor bug

##### 18 agosto 2025 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Permessi details

##### 17 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Sistemata versione mobile carenze e sportelli lato genitori

**ATA, ferie, permessi e orario**
- Iniziato sviluppo permessi lato genitore
- Sistemata versione mobile carenze lato studente

**Manutenzione e correzioni**
- Correzioni
- Rinominato file
- Minor corrections
- Corretto nomefile
- Aggiunti campi mancanti nel template

##### 16 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiornamento versione mobile sportelli per studenti
- Aggiornate pagine principali genitori e studenti in versione mobile

**ATA, ferie, permessi e orario**
- Aggiornata pagina errore login in versione mobile

**Interfaccia e mobile**
- Aggiornate pagine di errore alla versione mobile

**Manutenzione e correzioni**
- Aggiornato codice sportello lato didattica
- Removed error log
- Minor correction
- Minor bug

##### 15 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Sistemato carenze lato genitori
- Sistemata variabile user genitori nella sessione
- Aggiunti file sportelli per genitori
- Aggiunta pagina base Genitori
- Aggiunta login MasterCom genitori

**ATA, ferie, permessi e orario**
- Sistemata visibilità corsi di recupero se admin

**Manutenzione e correzioni**
- Aggiornato query con nuovi campi studente
- Minor bug
- Minor update

##### 14 agosto 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Correggo log aggiornamento importi

##### 13 agosto 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Test onesignal

##### 10 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiunto l'insert ed il delete dei genitori
- Aggiornata pagina genitori e pagina dettaglio
- Creata importazione genitori

##### 9 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Creata pagina genitori base
- Aggiunto filtro per classe
- Aggiunto filtro per studenti solo attivi
- Aggiornata importazione studenti

**Utenti, ruoli e sessioni**
- Aggiunto nuovo log solo per la fase di login per tutti gli utenti

**Manutenzione e correzioni**
- Agigunto CF e userId nel dettaglio dello studente
- Aggiunto IP client nel log del login
- Ora nome e cognome sono stampati con la prima lettera maiuscola

##### 8 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiornata gestione studenti con tabella studente_frequenta spostando i campi classe ed anno scolastico

##### 3 agosto 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiunto login Mastercom nella form di login

**Manutenzione e correzioni**
- Aggiunto CF ed aggiornato colonne

##### 18 luglio 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Aggiunta possibilità di far vedere tutte le carenze ai docenti

##### 15 luglio 2025 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Aggiornati permessi per visibilità pagina a segreteria didattica

##### 5 luglio 2025 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiornato invio mail con account google

**MasterCom, studenti e NOIRC**
- Sistemato generazione ed invio massivo carenze a studenti

**Interfaccia e mobile**
- Aggiunto FUIS lato docente con pulsante configurazione per visibilità

**Manutenzione e correzioni**
- Creato import da EDT elenco docenti con classi e materie
- Ora i docenti vedono solo le carenze da loro validate
- Aggiornato chiamate carenze lato studente

##### 4 luglio 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Aggiornamenti e correzioni bug

##### 30 giugno 2025 - versione 1.2.48
**Interfaccia e mobile**
- Aggiunto sommario ore fatte attribuite

**Manutenzione e correzioni**
- Aggiunto invio massivo Carenze

##### 29 giugno 2025 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Completato invio via mail della carenza, aggiornata visibilità lato studente e docente

**Programmi didattici**
- Aggiunti obiettivi minimi lato didattica
- Aggiunta stampa programmi anche per i programmi svolti
- Integrata stampa programma nei programmi delle materie

**ATA, ferie, permessi e orario**
- Sistemata stampa carenze lato studente
- Sistemata stampa carenza lato didattica

**Manutenzione e correzioni**
- Installato pacchetto tcpdf per la gestione dei PDF multipagina

##### 27 giugno 2025 - versione 1.2.48
**Programmi didattici**
- Corretta numerazione nuovo modulo programmi svolti

**Interfaccia e mobile**
- Aggiunta colonna TOTALE su ore Fatte Fuis lato Dirigente

##### 26 giugno 2025 - versione 1.2.48
**Programmi didattici**
- Ssitemati errori su share e copia programmi svolti
- Nei programmi svolti, ora creando una nuova classe non serve più salvare subito prima di inserire il programma

**Manutenzione e correzioni**
- Sistemato export carenze, ora esporta secondo il filtro applicato

##### 23 giugno 2025 - versione 1.2.48
**Programmi didattici**
- Correzioni e miglioramenti su programmi svolti

**Manutenzione e correzioni**
- Correzioni e miglioramenti su carenze

##### 22 giugno 2025 - versione 1.2.48
**Programmi didattici**
- Aggiunta la funzionalità duplica programma svolto

**ATA, ferie, permessi e orario**
- Creata bozza pagina carenze lato studente

##### 21 giugno 2025 - versione 1.2.48
**Manutenzione e correzioni**
- Carenze lato docenti completate
- Aggiunta pagina carenze su didattica

##### 20 giugno 2025 - versione 1.2.48
**Programmi didattici**
- Aggiornamento programmi materie e svolti

##### 8 giugno 2025 - versione 1.2.48
**Programmi didattici**
- Aggiornamenti e bug fix programmi e moduli

##### 5 giugno 2025 - versione 1.2.48
**Programmi didattici**
- Primo step gestione programmi materie con moduli

**Interfaccia e mobile**
- Corretta visibilità dettaglio sportello

**Manutenzione e correzioni**
- Corretto bug import sportelli
- Corretta posizione codice intestazione
- Reinserito file mancante
- Corretta visualizzazione tooltip sulle attiivtà

##### 26 aprile 2025 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiunto messaggio errore su controllo ore diaria

**Programmi didattici**
- Aggiunto nel JSON Programmi Materie e PdL estesi
- Aggiunta sezione Programmi Materie

**ATA, ferie, permessi e orario**
- Corretto salvataggio ore diaria su previste

**Manutenzione e correzioni**
- Aggiunto controllo diaria su previste
- Correzione testo

##### 25 aprile 2025 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Aggiornato vincolo data limite sportello

**Manutenzione e correzioni**
- Aggiornato percorso relativo template

##### 18 gennaio 2025 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiunta colonna classe di concorso

**ATA, ferie, permessi e orario**
- Impostata categoria di default

**Manutenzione e correzioni**
- Piccola correzione

##### 16 gennaio 2025 - versione 1.2.48
**Programmi didattici**
- Lato segreteria aggiunta statistica sportelli programmati e fatti

##### 31 dicembre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Corretto invio mail per cancellazione sportello lato didattica

**MasterCom, studenti e NOIRC**
- Aggiunto classe di concorso docente dove mancava, nelle tabelle e del codice
- Correggo bug limite temporale prenotazione sportelli lato studenti

##### 1 dicembre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Updated send mail to new server

**ATA, ferie, permessi e orario**
- Aggiornata selezione criteri bonus

**Interfaccia e mobile**
- Corretto header file

**Manutenzione e correzioni**
- Corretto post controllo bonus
- Update Log library

##### 16 novembre 2024 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Aggiornato testo cron promemoria studenti

**Interfaccia e mobile**
- Corretta visibilità ore riepilogo

**Manutenzione e correzioni**
- Aggiunta possibilità di iscrivere un singolo studente a più sportelli
- Corretto bug lato didattica ora si possono modificare presenti agli sportelli
- Aggiornato messagi cron

##### 10 novembre 2024 - versione 1.2.48
**Manutenzione e correzioni**
- Aggiornamento ore

##### 9 novembre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiornato invio mail sportelli studenti e docenti con nuovo comando send mail
- Creato comando per invio mail con autenticazione Google
- Caricato PHP Mailer

**MasterCom, studenti e NOIRC**
- Corretto controllo studenti assenti agli sportelli

**Utenti, ruoli e sessioni**
- Inserito nel JSON parametri per autenticazione Google

**Interfaccia e mobile**
- Aggiornato previste con visibilità ore previste orientamento nel riepilogo

**Manutenzione e correzioni**
- Aggiornato riepilogo ore orientamento nelle fatte del docente

##### 4 novembre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Manda la mail di notifica assenza SOLO per gli sportelli didattici e non per le altre attività

##### 3 novembre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiornato stile mail promemoria studente

**ATA, ferie, permessi e orario**
- Aggiornata descrizione link google per calendar

**Manutenzione e correzioni**
- Aggiornato link calendar con descrizione in base alla categoria

##### 2 novembre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Creata pagina email notifica assenza a sportello per studente

**MasterCom, studenti e NOIRC**
- Aggiunta possibilità didattica di iscrivere elenco di studenti ad una serie di attività in blocco

**Biglietti ed eventi**
- Lato didattica si possono vedere solo le attività con prenotazioni

**Interfaccia e mobile**
- Aggiunto filtro ISCRITTO per le sole attività a cui lo studente è iscritto

##### 1 novembre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Corretto subject mail sportelli docenti

**MasterCom, studenti e NOIRC**
- Corretto invio promemoria docente per no studenti

**Interfaccia e mobile**
- Aggiunta visibilità e gestione categoria lato docente

**Manutenzione e correzioni**
- Gli sportelli bloccati ora sono apribili in sola lettura del docente
- Lato didattica aggiunta categoria ad import e nuovo sportello

##### 31 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiornati template ed email inviate alla cancellazione sportello
- Migliorato aspetto e subject mail lato studente per sportelli
- Corretta notifica mail al docente

**MasterCom, studenti e NOIRC**
- Aggiunto filtro per categoria a studenti

**Manutenzione e correzioni**
- Rimosso file in piu

##### 29 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiunto messaggio di conferma docente prima di firmare

**MasterCom, studenti e NOIRC**
- La didattica ora può modificare il flag presenza degli studenti agli sportelli
- Corretto calcolo numero studenti iscritti allo sportello nella pagina di dettaglio

##### 21 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Inserito nome istituto mail annullamento sportello

##### 20 ottobre 2024 - versione 1.2.48
**Interfaccia e mobile**
- Correzione bug ore fuis previste

##### 17 ottobre 2024 - versione 1.2.48
**Manutenzione e correzioni**
- Aggiornamento viaggi docenti fatto da Paolo
- Allineamento colonna materia sportello didattica

##### 16 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Rimossi messaggi INFO di debug

**Manutenzione e correzioni**
- Corretta inizializzazione sqlList in caso di errore

##### 15 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Migliorato template mail cancellazione sportello con elenco studenti aggiornato
- Migliorato testo messaggi INFO
- Modificato nel template delle mail il nome dell'istituto ora preso dal json

**ATA, ferie, permessi e orario**
- Corretto ordine stampa della data

**Manutenzione e correzioni**
- Ora i docenti non possono modificare i vecchi sportelli
- Aggiunto invio promemoria studente il giorno prima
- Corretto errore query
- Aggiunta colonna categoria sportelli, argomento lato docente appare solo se scelto dal docente

##### 13 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Corretto messaggio email
- Aggiunto campo BCC email inviate per sportello configurabile da JSON

**Manutenzione e correzioni**
- Creato promemoria DOCENTE da inviare la mattina dello sportello - da usare con CRON

##### 12 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiornati template mail quando studente di cancella dallo sportello
- Aggiunta gestione invio mail quando docente cancella sportello
- Modificato messaggio di conferma per la cancellazione dello sportello
- Rimosso pulsante firma, aggiunto messaggio di alert se si tenta di firmare sportello vuoto
- Adattato codice per campi necessari per invio email
- Creati template email iscrivi e cancella
- Altre 1 modifiche minori nella stessa area.

**Manutenzione e correzioni**
- Aggiornato stile icona lock
- Sportello cancellato o firmato non più modificabile dal docente. Aggiunto tooltips
- Corrette segnalazioni errore session logout Google
- Rimossi file non più necessari
- Rimosso codice info non più necessario
- Completato codice iscrizione studente a sportello
- Altre 4 modifiche minori nella stessa area.

##### 10 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Aggiunto campo email nel JS
- Aggiunto campo email in read records
- Aggiunto DST ora sportello - aggiornato invio mail studenti e docenti
- Aggiunto <br> messaggio di errore

##### 9 ottobre 2024 - versione 1.2.48
**ATA, ferie, permessi e orario**
- Aggiunta possibilità di importare sportelli multipli che hanno lo stesso orario

**Interfaccia e mobile**
- Corretta visibilità tooltip cancella prenotazione

**Manutenzione e correzioni**
- Corretto campo errore e risolto bug form inserimento nuovo sportello
- Migliorate etichette stato sportelli lato docente e didattica

##### 8 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Corretta visibilità messaggio di errore

**MasterCom, studenti e NOIRC**
- Aggiornato codice sportelli con il campo classe_id del database

**ATA, ferie, permessi e orario**
- Aggiornata visibilità campo online in base al valore nel JSON

**Manutenzione e correzioni**
- Aggiunto flag json docente puo modificare dati sportello
- Sistemato pagina dettaglio sportelli docente con lista classi da DB

##### 7 ottobre 2024 - versione 1.2.48
**Manutenzione e correzioni**
- Completati status sportelli lato studente

##### 6 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Corretto formato HTML mail inviata a studente

##### 5 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Creata mail completa quanto studente si iscrive a sportello
- Aggiunto invio mail docente quando si iscrive uno studente

**MasterCom, studenti e NOIRC**
- Aggiunto filtro classe sportelli lato didattica
- Aggiunto filtro classe_id per gli sportelli - nb:aggiungere campo classe_id nella tabella sportelli

**Interfaccia e mobile**
- Dettaglio sportello didattica - inserito flag visibilità online clil orientamento

**Manutenzione e correzioni**
- Nell'elenco degli sportelli ora appare l'argomento se scelto dallo studente
- Aggiunti nuovi status lato studente
- Aggiunto stato "posti disponibilil" e "posti esauriti" lato didattica
- Migliorato stile pannello

##### 4 ottobre 2024 - versione 1.2.48
**Ticket, Telegram e comunicazioni**
- Quando il docente vuole cancellare uno sportello appare un messaggio di conferma prima di procedere

**MasterCom, studenti e NOIRC**
- Aggiunta opzione selezione tipo classe

**Biglietti ed eventi**
- Aggiunta colonna max prenotazioni, aggiunto stato "posti diponbiili " e "posti esauriti"

**Interfaccia e mobile**
- Corretta visibilità sportelli cancellati lato docente
- Aggiunti flag json per visibilità sezione sportello docente
- Aggiunti pulsante visibilità sportelli cancellati

**Manutenzione e correzioni**
- Lato studente se lo sportello è cancellato non compaiono gli altri status
- Corretto campo max iscrizioni
- Aggiunto status posti esauriti allo sportello lato studente
- Aggiornamento js per sportelli cancellati
- Modifiche diaria da Paolo Scapin
- Correzione formattazione campo tabella
- Altre 1 modifiche minori nella stessa area.

##### 26 settembre 2024 - versione 1.2.48
**Manutenzione e correzioni**
- Stampa ridotta piano di lavoro

##### 19 settembre 2024 - versione 1.2.48
**MasterCom, studenti e NOIRC**
- Lato studente invece che numero di studenti prenotati ora vede numero di posti disponibili allo sportello

**ATA, ferie, permessi e orario**
- Lato studente la riga dello sportello cancellato appare barrata

**Manutenzione e correzioni**
- Lato studente creato toggle per scegliere se vedere o meno gli sportelli cancellati
- Adesso lo studente vede gli sportelli cancellati ed appare una label rossa
- Lato docente migliorato aspetto tabella sportelli
- Lato didattica migliorato aspetto tabella sportelli
- Migliorato aspetto formattazione testo tabella sportelli
- Aggiunto file .htaccess nel gitignore

<!-- gestore-git-changelog-1.2.328:end -->
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