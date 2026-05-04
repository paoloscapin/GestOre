# 📬 GestOre – Configurazione Gmail Push (Google Cloud)

Questa guida descrive come configurare Gmail + Google Cloud per ricevere automaticamente le email dei ticket tramite sistema **push**, eliminando il cron ogni minuto.

---

## 🎯 Obiettivo

Passare da:


cron ogni minuto → lettura email → import ticket


a:


Gmail → Pub/Sub → webhook GestOre → import ticket


---

## ☁️ 1. Google Cloud

Progetto utilizzato:


gestorembgest


Abilitare API:

- Gmail API
- Cloud Pub/Sub API

Percorso:


API e servizi → Libreria


---

## 🔐 2. OAuth

Usare l’OAuth già esistente di GestOre.

Aggiungere URI:


https://www.buonarroti.tn.it/GestOre/api/google_gmail_callback.php


Percorso:


API e servizi → Credenziali


---

## 📡 3. Topic Pub/Sub

Creare topic:


gmail.notify


Nome completo:


projects/gestorembgest/topics/gmail.notify


---

## 🔔 4. Subscription

Creare sottoscrizione:

- Nome: `gmail-push-sub`
- Tipo: Push
- Endpoint:


https://www.buonarroti.tn.it/GestOre/api/gmail_webhook.php


- Autenticazione: nessuna

---

## 🔑 5. Permessi Gmail

Aggiungere nel topic:


gmail-api-push@system.gserviceaccount.com


Ruolo:


Pub/Sub Publisher


---

## ⚙️ 6. File GestOre


/GestOre/common/gmail_api_lib.php
/GestOre/api/google_gmail_auth.php
/GestOre/api/google_gmail_callback.php
/GestOre/api/gmail_webhook.php
/GestOre/api/gmail_refresh_watch_cron.php
/GestOre/api/ticket_mail_import_push.php


Log:


/GestOre/log/


---

## 🔑 7. Autorizzazione Gmail

Aprire:


https://www.buonarroti.tn.it/GestOre/api/google_gmail_auth.php


Accedere con:


noreplygestore@buonarroti.tn.it


Genera:


/GestOre/log/gmail_token.json


⚠️ NON cancellare questo file

---

## 🔄 8. Attivazione Watch

Aprire:


https://www.buonarroti.tn.it/GestOre/api/gmail_refresh_watch_cron.php?secret=SECRET


Risposta:

```json
{
  "ok": true,
  "response": {
    "historyId": "...",
    "expiration": "..."
  }
}
⏰ 9. Cron giornaliero
10 3 * * * /usr/bin/curl -s "https://www.buonarroti.tn.it/GestOre/api/gmail_refresh_watch_cron.php?secret=SECRET" >/dev/null 2>&1
⚡ 10. Funzionamento
Email arriva
↓
Gmail
↓
Pub/Sub
↓
gmail_webhook.php
↓
ticket_mail_import_push.php
🔍 11. Debug

Controllare:

/GestOre/log/debug_gmail_webhook.log
/GestOre/log/debug_gmail_messages.log
/GestOre/log/gmail_state.json
❌ Problemi comuni
Access token non disponibile

Causa:

gmail_token.json mancante

Soluzione:

rifare login OAuth
riattivare watch
Webhook non chiamato

Controllare:

endpoint corretto
HTTPS valido
redirect www
permessi Pub/Sub
Watch scaduta

Normale.

Soluzione:

cron giornaliero
🧠 Note
sistema completamente push (no polling)
usare sempre account ticket
non cancellare token
cron ogni minuto NON serve più
✅ Stato finale

✔ sistema realtime
✔ meno carico server
✔ notifiche immediate
✔ architettura moderna

DONE 👍

---

# 🔥 CONSIGLIO PRATICO

Aprilo con:

- VS Code → preview markdown (`CTRL+SHIFT+V`)
- GitHub → render automatico

👉 vedrai che la formattazione è perfetta

---

# 🚀 Se vuoi il top

Posso anche farti:

- versione HTML stampabile
- versione PDF
- versione interna tipo “wiki GestOre”

dimmi 👍