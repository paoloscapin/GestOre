<?php
require_once __DIR__ . '/__Settings.php';

$BOT_USERNAME = trim((string)($__settings->telegram->bot_username ?? ''));
?>
<!doctype html>
<html lang="it">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <title>GestOre - Mini App</title>
  <script src="https://telegram.org/js/telegram-web-app.js"></script>
  <style>
    :root {
      --bg: #f5f7fb;
      --card: #ffffff;
      --text: #1f2937;
      --muted: #6b7280;
      --border: #e5e7eb;
      --primary: #2563eb;
      --success: #15803d;
      --warning: #ca8a04;
      --danger: #b91c1c;
    }

    * {
      box-sizing: border-box
    }

    body {
      margin: 0;
      font-family: Arial, Helvetica, sans-serif;
      background: var(--bg);
      color: var(--text);
    }

    .wrap {
      max-width: 900px;
      margin: 0 auto;
      padding: 16px;
    }

    .header {
      margin-bottom: 16px;
    }

    .title {
      font-size: 24px;
      font-weight: 700;
      margin: 0 0 6px 0;
    }

    .subtitle {
      color: var(--muted);
      font-size: 14px;
      margin: 0;
    }

    .section-switch {
      display: flex;
      gap: 8px;
      margin: 16px 0 12px 0;
      flex-wrap: wrap;
    }

    .switch-btn {
      border: 1px solid var(--border);
      background: #fff;
      padding: 10px 14px;
      border-radius: 10px;
      cursor: pointer;
      font-weight: 700;
      color: var(--text);
    }

    .switch-btn.active {
      background: var(--primary);
      color: #fff;
      border-color: var(--primary);
    }

    .top-actions {
      margin: 16px 0 10px 0;
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .tabs {
      display: flex;
      gap: 8px;
      margin: 16px 0;
    }

    .tab {
      border: 1px solid var(--border);
      background: #fff;
      padding: 10px 14px;
      border-radius: 10px;
      cursor: pointer;
      font-weight: 600;
    }

    .tab.active {
      background: var(--primary);
      color: #fff;
      border-color: var(--primary);
    }

    .panel {
      display: none;
    }

    .panel.active {
      display: block;
    }

    .section-panel {
      display: none;
    }

    .section-panel.active {
      display: block;
    }

    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 14px;
      margin-bottom: 12px;
      box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
    }

    .row {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      align-items: flex-start;
      flex-wrap: wrap;
    }

    .ticket-code {
      font-weight: 700;
      font-size: 16px;
    }

    .sost-title {
      font-weight: 700;
      font-size: 16px;
    }

    .badge {
      padding: 6px 10px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 800;
      color: #fff;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .badge.aperta {
      background: var(--warning)
    }

    .badge.in_gestione {
      background: var(--primary)
    }

    .badge.chiusa {
      background: var(--success)
    }

    .badge.sostituto {
      background: #dc2626;
    }

    .badge.sostituito {
      background: #f59e0b;
    }

    .meta {
      margin-top: 10px;
      color: var(--muted);
      font-size: 13px;
      line-height: 1.5;
    }

    .msg {
      margin-top: 12px;
      padding: 10px;
      border-radius: 10px;
      background: #f9fafb;
      border: 1px solid var(--border);
      font-size: 14px;
      white-space: pre-wrap;
    }

    .actions {
      margin-top: 12px;
    }

    .btn {
      display: inline-block;
      text-decoration: none;
      background: var(--primary);
      color: #fff;
      padding: 10px 14px;
      border-radius: 10px;
      font-weight: 700;
      border: none;
      cursor: pointer;
      font-size: 14px;
    }

    .btn.secondary {
      background: #fff;
      color: var(--text);
      border: 1px solid var(--border);
    }

    .empty,
    .loading,
    .error {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 18px;
    }

    .error {
      color: var(--danger);
    }

    .modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .35);
      display: none;
      align-items: center;
      justify-content: center;
      padding: 16px;
      z-index: 999;
    }

    .modal-backdrop.show {
      display: flex;
    }

    .modal {
      width: 100%;
      max-width: 520px;
      background: #fff;
      border-radius: 14px;
      padding: 16px;
      box-shadow: 0 8px 30px rgba(0, 0, 0, .18);
    }

    .modal h3 {
      margin: 0 0 10px 0;
      font-size: 20px;
    }

    .modal p {
      margin: 0 0 12px 0;
      color: var(--muted);
      font-size: 14px;
    }

    .textarea {
      width: 100%;
      min-height: 140px;
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 12px;
      font-size: 14px;
      resize: vertical;
      font-family: Arial, Helvetica, sans-serif;
    }

    .modal-actions {
      margin-top: 12px;
      display: flex;
      gap: 10px;
      justify-content: flex-end;
      flex-wrap: wrap;
    }
  </style>
</head>

<body>
  <div class="wrap">
    <div class="header">
      <h1 class="title" id="pageTitle">I miei ticket GestOre</h1>
      <p class="subtitle" id="docenteInfo">Caricamento...</p>
    </div>

  <div class="section-switch">
    <button type="button" class="switch-btn active" id="btnSectionSostituzioniOggi" data-section="sostituzioni_oggi">🔄 Sostituzioni di oggi</button>
    <button type="button" class="switch-btn" id="btnSectionSostituzioniAnno" data-section="sostituzioni_anno">📚 Sostituzioni anno</button>
    <button type="button" class="switch-btn" id="btnSectionTickets" data-section="tickets">🎫 Ticket</button>
  </div>

    <div class="top-actions">
      <button type="button" class="btn" id="btnPrimaryAction">➕ Nuovo ticket</button>
      <button type="button" class="btn secondary" id="btnApriChat">💬 Apri chat</button>
    </div>

    <div id="loading" class="loading">Caricamento...</div>
    <div id="error" class="error" style="display:none;"></div>

    <div id="section-tickets" class="section-panel active">
      <div class="tabs" id="ticketTabs">
        <button class="tab active" data-tab="aperti">Aperti</button>
        <button class="tab" data-tab="chiusi">Chiusi</button>
      </div>

      <div id="panel-aperti" class="panel active"></div>
      <div id="panel-chiusi" class="panel"></div>
    </div>

    <div id="section-sostituzioni" class="section-panel">
      <div id="panel-sostituzioni"></div>
    </div>
  </div>

  <div id="ticketModal" class="modal-backdrop">
    <div class="modal">
      <h3>Nuovo ticket</h3>
      <p>Scrivi qui la tua richiesta per il servizio GestOre.</p>
      <textarea id="ticketText" class="textarea" placeholder="Descrivi il problema o la richiesta..."></textarea>
      <div class="modal-actions">
        <button type="button" class="btn secondary" id="btnChiudiModal">Annulla</button>
        <button type="button" class="btn" id="btnInviaTicket">Invia ticket</button>
      </div>
    </div>
  </div>

  <script>
    const tg = window.Telegram?.WebApp;
    const BOT_USERNAME = <?= json_encode($BOT_USERNAME, JSON_UNESCAPED_UNICODE) ?>;

    if (tg) {
      tg.ready();
      tg.expand();
    }

    let currentSection = 'sostituzioni_oggi';

    const pageTitleEl = document.getElementById('pageTitle');
    const loadingEl = document.getElementById('loading');
    const errorEl = document.getElementById('error');
    const docenteInfoEl = document.getElementById('docenteInfo');

    const sectionTicketsEl = document.getElementById('section-tickets');
    const sectionSostituzioniEl = document.getElementById('section-sostituzioni');

    const panelAperti = document.getElementById('panel-aperti');
    const panelChiusi = document.getElementById('panel-chiusi');
    const panelSostituzioni = document.getElementById('panel-sostituzioni');

    const btnSectionSostituzioniOggi = document.getElementById('btnSectionSostituzioniOggi');
    const btnSectionSostituzioniAnno = document.getElementById('btnSectionSostituzioniAnno');
    const btnSectionTickets = document.getElementById('btnSectionTickets');

    const btnPrimaryAction = document.getElementById('btnPrimaryAction');
    const btnApriChat = document.getElementById('btnApriChat');

    const ticketModal = document.getElementById('ticketModal');
    const ticketText = document.getElementById('ticketText');
    const btnChiudiModal = document.getElementById('btnChiudiModal');
    const btnInviaTicket = document.getElementById('btnInviaTicket');

    document.querySelectorAll('.tab').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach(x => x.classList.remove('active'));
        document.querySelectorAll('#section-tickets .panel').forEach(x => x.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('panel-' + btn.dataset.tab).classList.add('active');
      });
    });

    function escapeHtml(str) {
      return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function badgeClass(stato) {
      const s = (stato || '').toUpperCase();
      if (s === 'APERTA') return 'aperta';
      if (s === 'IN_GESTIONE') return 'in_gestione';
      if (s === 'CHIUSA') return 'chiusa';
      return 'aperta';
    }

    function sostBadgeClass(ruolo) {
      const r = (ruolo || '').toLowerCase();
      if (r === 'sostituito') return 'sostituito';
      return 'sostituto';
    }

    function openBotChat() {
      if (!BOT_USERNAME) {
        alert('Bot Telegram non configurato');
        return;
      }

      const url = 'https://t.me/' + BOT_USERNAME;

      if (tg && typeof tg.openTelegramLink === 'function') {
        tg.openTelegramLink(url);
      } else {
        window.open(url, '_blank', 'noopener,noreferrer');
      }
    }

    function openModal() {
      ticketText.value = '';
      ticketModal.classList.add('show');
      setTimeout(() => ticketText.focus(), 50);
    }

    function closeModal() {
      ticketModal.classList.remove('show');
    }

    function updateHeaderForSection() {
      if (currentSection === 'tickets') {
        pageTitleEl.textContent = 'I miei ticket GestOre';
        btnPrimaryAction.textContent = '➕ Nuovo ticket';
        btnPrimaryAction.onclick = openModal;
      } else if (currentSection === 'sostituzioni_oggi') {
        pageTitleEl.textContent = 'Le mie sostituzioni di oggi';
        btnPrimaryAction.textContent = '🔄 Aggiorna';
        btnPrimaryAction.onclick = () => loadSostituzioni('today');
      } else {
        pageTitleEl.textContent = 'Le mie sostituzioni dell’anno';
        btnPrimaryAction.textContent = '🔄 Aggiorna';
        btnPrimaryAction.onclick = () => loadSostituzioni('year');
      }
    }

    function switchSection(section) {
      currentSection = section;

      btnSectionSostituzioniOggi.classList.toggle('active', section === 'sostituzioni_oggi');
      btnSectionSostituzioniAnno.classList.toggle('active', section === 'sostituzioni_anno');
      btnSectionTickets.classList.toggle('active', section === 'tickets');

      sectionTicketsEl.classList.toggle('active', section === 'tickets');
      sectionSostituzioniEl.classList.toggle('active', section === 'sostituzioni_oggi' || section === 'sostituzioni_anno');

      errorEl.style.display = 'none';
      loadingEl.style.display = 'block';
      loadingEl.textContent = 'Caricamento...';

      updateHeaderForSection();

      if (section === 'tickets') {
        loadTickets();
      } else if (section === 'sostituzioni_oggi') {
        loadSostituzioni('today');
      } else {
        loadSostituzioni('year');
      }
    }

    btnSectionSostituzioniOggi.addEventListener('click', () => switchSection('sostituzioni_oggi'));
    btnSectionSostituzioniAnno.addEventListener('click', () => switchSection('sostituzioni_anno'));
    btnSectionTickets.addEventListener('click', () => switchSection('tickets'));

    btnChiudiModal.addEventListener('click', closeModal);
    btnApriChat.addEventListener('click', openBotChat);

    ticketModal.addEventListener('click', (e) => {
      if (e.target === ticketModal) closeModal();
    });

    async function createTicketFromMiniApp() {
      const text = ticketText.value.trim();

      if (!text) {
        alert('Scrivi il testo del ticket');
        return;
      }

      const initData = tg?.initData || '';
      if (!initData) {
        alert('Mini App aperta fuori da Telegram o initData assente');
        return;
      }

      btnInviaTicket.disabled = true;
      btnInviaTicket.textContent = 'Invio...';

      try {
        const res = await fetch('miniapp_ticket_create.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            initData,
            text
          })
        });

        const rawText = await res.text();
        if (!rawText) {
          throw new Error('Risposta vuota dal server');
        }

        let json;
        try {
          json = JSON.parse(rawText);
        } catch (e) {
          throw new Error('Risposta non JSON: ' + rawText.substring(0, 300));
        }

        if (!json.ok) {
          throw new Error(json.error || 'Errore creazione ticket');
        }

        closeModal();
        await loadTickets();

        const msg = json.mode === 'append'
          ? `Messaggio aggiunto al ticket ${json.ticket_code || ''}`
          : `Ticket creato: ${json.ticket_code || ''}`;

        alert(msg.trim());

      } catch (err) {
        alert(err.message || 'Errore imprevisto');
      } finally {
        btnInviaTicket.disabled = false;
        btnInviaTicket.textContent = 'Invia ticket';
      }
    }

    btnInviaTicket.addEventListener('click', createTicketFromMiniApp);

    function renderTickets(container, tickets, emptyText) {
      if (!tickets || !tickets.length) {
        container.innerHTML = `<div class="empty">${escapeHtml(emptyText)}</div>`;
        return;
      }

      container.innerHTML = tickets.map(t => {
        const lastMessage = t.ultimo_testo_admin || t.ultimo_testo_docente || '';
        const presa = t.preso_in_carico_nome ? `Preso in carico da: ${t.preso_in_carico_nome}` : '';
        const apertura = t.data_creazione_fmt ? `Aperto: ${t.data_creazione_fmt}` : '';
        const chiusura = t.data_chiusura_fmt ? `Chiuso: ${t.data_chiusura_fmt}` : '';
        const topic = t.thread_topic_name ? `Topic: ${t.thread_topic_name}` : '';

        return `
          <div class="card">
            <div class="row">
              <div class="ticket-code">${escapeHtml(t.ticket_code || 'Ticket')}</div>
              <div class="badge ${badgeClass(t.stato)}">${escapeHtml(t.stato || '')}</div>
            </div>

            <div class="meta">
              ${apertura ? `<div>${escapeHtml(apertura)}</div>` : ''}
              ${chiusura ? `<div>${escapeHtml(chiusura)}</div>` : ''}
              ${presa ? `<div>${escapeHtml(presa)}</div>` : ''}
              ${topic ? `<div>${escapeHtml(topic)}</div>` : ''}
            </div>

            ${lastMessage ? `<div class="msg">${escapeHtml(lastMessage)}</div>` : ''}

            <div class="actions">
              <button type="button" class="btn" onclick="openBotChat()">Apri chat</button>
            </div>
          </div>
        `;
      }).join('');
    }

    function renderSostituzioni(container, items, emptyText, scope = 'today') {
      if (!items || !items.length) {
        container.innerHTML = `<div class="empty">${escapeHtml(emptyText)}</div>`;
        return;
      }

      container.innerHTML = items.map(s => {
        const ruolo = s.ruolo_docente || 'sostituto';
        const badgeText = ruolo === 'sostituito' ? 'SEI SOSTITUITO' : 'DEVI SOSTITUIRE';
        const ora = s.ora_range_fmt || '';
        const dataSost = s.data_fmt ? `Data: ${s.data_fmt}` : (s.data ? `Data: ${s.data}` : '');
        const classe = s.classe ? `Classe: ${s.classe}` : '';
        const aula = s.aula ? `Aula: ${s.aula}` : '';
        const materia = s.materia ? `Materia: ${s.materia}` : '';
        const collega = ruolo === 'sostituito'
          ? (s.docente_sostituto ? `Ti sostituisce: ${s.docente_sostituto}` : '')
          : (s.docente_sostituito ? `Docente sostituito: ${s.docente_sostituito}` : '');

        return `
          <div class="card">
            <div class="row">
              <div class="sost-title">${escapeHtml(ora || 'Sostituzione')}</div>
              <div class="badge ${sostBadgeClass(ruolo)}">${escapeHtml(badgeText)}</div>
            </div>

            <div class="meta">
              ${scope === 'year' && dataSost ? `<div>${escapeHtml(dataSost)}</div>` : ''}
              ${classe ? `<div>${escapeHtml(classe)}</div>` : ''}
              ${aula ? `<div>${escapeHtml(aula)}</div>` : ''}
              ${materia ? `<div>${escapeHtml(materia)}</div>` : ''}
              ${collega ? `<div>${escapeHtml(collega)}</div>` : ''}
            </div>
          </div>
        `;
      }).join('');
    }

    async function fetchJson(url, payload) {
      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
      });

      const rawText = await res.text();
      console.log(url + ' raw response:', rawText);

      if (!rawText) {
        throw new Error('Risposta vuota dal server');
      }

      let json;
      try {
        json = JSON.parse(rawText);
      } catch (e) {
        throw new Error('Risposta non JSON: ' + rawText.substring(0, 300));
      }

      return json;
    }

    async function loadTickets() {
      try {
        const initData = tg?.initData || '';
        if (!initData) {
          throw new Error('Mini App aperta fuori da Telegram o initData assente');
        }

        errorEl.style.display = 'none';
        loadingEl.style.display = 'block';
        loadingEl.textContent = 'Caricamento ticket...';

        const json = await fetchJson('miniapp_ticket_list.php', { initData });

        loadingEl.style.display = 'none';

        if (!json.ok) {
          throw new Error(json.error || 'Errore nel caricamento ticket');
        }

        const docente = json.docente || {};
        const counts = json.counts || {};
        const tickets = json.tickets || {};

        docenteInfoEl.textContent =
          `${docente.nome || ''} — Aperti: ${counts.aperti || 0} • Chiusi: ${counts.chiusi || 0}`;

        renderTickets(panelAperti, tickets.aperti || [], 'Nessun ticket aperto.');
        renderTickets(panelChiusi, tickets.chiusi || [], 'Nessun ticket chiuso.');

      } catch (err) {
        loadingEl.style.display = 'none';
        errorEl.style.display = 'block';
        errorEl.textContent = err.message || 'Errore imprevisto';
      }
    }

    async function loadSostituzioni(scope = 'today') {
      try {
        const initData = tg?.initData || '';
        if (!initData) {
          throw new Error('Mini App aperta fuori da Telegram o initData assente');
        }

        errorEl.style.display = 'none';
        loadingEl.style.display = 'block';
        loadingEl.textContent = scope === 'year'
          ? 'Caricamento sostituzioni dell’anno...'
          : 'Caricamento sostituzioni di oggi...';

        const json = await fetchJson('miniapp_sostituzioni_list.php', {
          initData,
          scope
        });

        loadingEl.style.display = 'none';

        if (!json.ok) {
          throw new Error(json.error || 'Errore nel caricamento sostituzioni');
        }

        const docente = json.docente || {};
        const counts = json.counts || {};
        const items = json.sostituzioni || {};

        if (scope === 'year') {
          docenteInfoEl.textContent =
            `${docente.nome || ''} — Sostituzioni anno: ${counts.totale || 0}`;
          renderSostituzioni(panelSostituzioni, items, 'Nessuna sostituzione nell’anno scolastico.', scope);
        } else {
          docenteInfoEl.textContent =
            `${docente.nome || ''} — Sostituzioni di oggi: ${counts.totale || 0}`;
          renderSostituzioni(panelSostituzioni, items, 'Nessuna sostituzione per oggi.', scope);
        }

      } catch (err) {
        loadingEl.style.display = 'none';
        errorEl.style.display = 'block';
        errorEl.textContent = err.message || 'Errore imprevisto';
      }
    }

    updateHeaderForSection();
    loadSostituzioni('today');

    setInterval(() => {
      if (document.visibilityState === 'visible') {
        if (currentSection === 'tickets') {
          loadTickets();
        } else if (currentSection === 'sostituzioni_oggi') {
          loadSostituzioni('today');
        } else {
          loadSostituzioni('year');
        }
      }
    }, 30000);

    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') {
        if (currentSection === 'tickets') {
          loadTickets();
        } else if (currentSection === 'sostituzioni_oggi') {
          loadSostituzioni('today');
        } else {
          loadSostituzioni('year');
        }
      }
    });
  </script>
</body>

</html>