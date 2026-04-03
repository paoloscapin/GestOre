<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <title>GestOre - I miei ticket</title>
  <script src="https://telegram.org/js/telegram-web-app.js"></script>
  <style>
    :root{
      --bg:#f5f7fb;
      --card:#ffffff;
      --text:#1f2937;
      --muted:#6b7280;
      --border:#e5e7eb;
      --primary:#2563eb;
      --success:#15803d;
      --warning:#ca8a04;
      --danger:#b91c1c;
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      font-family:Arial, Helvetica, sans-serif;
      background:var(--bg);
      color:var(--text);
    }
    .wrap{
      max-width:900px;
      margin:0 auto;
      padding:16px;
    }
    .header{
      margin-bottom:16px;
    }
    .title{
      font-size:24px;
      font-weight:700;
      margin:0 0 6px 0;
    }
    .subtitle{
      color:var(--muted);
      font-size:14px;
      margin:0;
    }
    .tabs{
      display:flex;
      gap:8px;
      margin:16px 0;
    }
    .tab{
      border:1px solid var(--border);
      background:#fff;
      padding:10px 14px;
      border-radius:10px;
      cursor:pointer;
      font-weight:600;
    }
    .tab.active{
      background:var(--primary);
      color:#fff;
      border-color:var(--primary);
    }
    .panel{
      display:none;
    }
    .panel.active{
      display:block;
    }
    .card{
      background:var(--card);
      border:1px solid var(--border);
      border-radius:14px;
      padding:14px;
      margin-bottom:12px;
      box-shadow:0 1px 2px rgba(0,0,0,.04);
    }
    .row{
      display:flex;
      justify-content:space-between;
      gap:10px;
      align-items:flex-start;
      flex-wrap:wrap;
    }
    .ticket-code{
      font-weight:700;
      font-size:16px;
    }
    .badge{
      padding:5px 10px;
      border-radius:999px;
      font-size:12px;
      font-weight:700;
      color:#fff;
    }
    .badge.aperta{background:var(--warning)}
    .badge.in_gestione{background:var(--primary)}
    .badge.chiusa{background:var(--success)}
    .meta{
      margin-top:10px;
      color:var(--muted);
      font-size:13px;
      line-height:1.5;
    }
    .msg{
      margin-top:12px;
      padding:10px;
      border-radius:10px;
      background:#f9fafb;
      border:1px solid var(--border);
      font-size:14px;
      white-space:pre-wrap;
    }
    .actions{
      margin-top:12px;
    }
    .btn{
      display:inline-block;
      text-decoration:none;
      background:var(--primary);
      color:#fff;
      padding:10px 14px;
      border-radius:10px;
      font-weight:700;
    }
    .empty, .loading, .error{
      background:#fff;
      border:1px solid var(--border);
      border-radius:14px;
      padding:18px;
    }
    .error{
      color:var(--danger);
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="header">
      <h1 class="title">I miei ticket GestOre</h1>
      <p class="subtitle" id="docenteInfo">Caricamento...</p>
    </div>

    <div class="tabs">
      <button class="tab active" data-tab="aperti">Aperti</button>
      <button class="tab" data-tab="chiusi">Chiusi</button>
    </div>

    <div id="loading" class="loading">Caricamento ticket...</div>
    <div id="error" class="error" style="display:none;"></div>

    <div id="panel-aperti" class="panel active"></div>
    <div id="panel-chiusi" class="panel"></div>
  </div>

  <script>
    const tg = window.Telegram?.WebApp;
    if (tg) {
      tg.ready();
      tg.expand();
    }

    const loadingEl = document.getElementById('loading');
    const errorEl = document.getElementById('error');
    const docenteInfoEl = document.getElementById('docenteInfo');
    const panelAperti = document.getElementById('panel-aperti');
    const panelChiusi = document.getElementById('panel-chiusi');

    document.querySelectorAll('.tab').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach(x => x.classList.remove('active'));
        document.querySelectorAll('.panel').forEach(x => x.classList.remove('active'));

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

    function renderTickets(container, tickets, emptyText) {
      if (!tickets || !tickets.length) {
        container.innerHTML = `<div class="empty">${escapeHtml(emptyText)}</div>`;
        return;
      }

      container.innerHTML = tickets.map(t => {
        const lastMessage = t.ultimo_testo_admin || t.ultimo_testo_docente || '';
        const presa = t.preso_in_carico_nome ? `Preso in carico da: ${t.preso_in_carico_nome}` : '';
        const apertura = t.data_apertura_fmt ? `Aperto: ${t.data_apertura_fmt}` : '';
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
              ${t.telegram_link
                ? `<a href="${t.telegram_link}" target="_blank">💬 Apri chat</a>`
                : `<span class="meta">Link Telegram non disponibile</span>`
              }
            </div>
          </div>
        `;
      }).join('');
    }

    async function loadTickets() {
      try {
        const initData = tg?.initData || '';
        if (!initData) {
          throw new Error('Mini App aperta fuori da Telegram o initData assente');
        }

        const res = await fetch('miniapp_ticket_list.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ initData })
        });

        const json = await res.json();

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

    loadTickets();
  </script>
</body>
</html>