/**
 * =====================================================================================
 *  ORARIO UI (AULA / CLASSE / DOCENTE) - RENDER GRID CON ROWSPAN + OVERRIDDEN + MULTI-AULE
 * =====================================================================================
 *
 *  SCOPO DEL FILE
 *  --------------
 *  Questo script:
 *   1) legge i filtri scelti dall'utente (scope/period/date/target)
 *   2) richiama gli endpoint PHP che restituiscono gli eventi (orarioRead.php)
 *   3) (solo AULA) richiama un endpoint aggiuntivo con assenze/blocchi (orarioAulaBlocchi.php)
 *   4) costruisce una tabella HTML (giorno o settimana) con:
 *        - righe = slot orari (ORARI)
 *        - colonne = giorni (SETTIMANA) o 1 colonna (GIORNO)
 *        - "rowspan" per unire verticalmente celle consecutive uguali
 *   5) applica classi CSS e markup (ev-*, td-*) per rendere colori, badge, grigio/barrato ecc.
 *
 *
 *  STRUTTURA DATI ATTESA (GRID)
 *  ---------------------------
 *  Dal backend arriva:
 *    r.grid : oggetto { "YYYY-MM-DD|HH:MM": [ {evento1}, {evento2}, ... ], ... }
 *
 *  Ogni evento tipicamente contiene:
 *    - type  : "curr", "udi", "imp", "uscC", "uscF", "viag", ...
 *    - title : testo evento
 *    - who   : docente / persona
 *    - classi: array o stringa csv
 *    - rooms : array o stringa csv
 *    - badge : breve tag/bollino
 *    - class : (opzionale) classe CSS contenente ev-curr / ev-viag ecc.
 *
 *  BLOCCO / OVERRIDE (SOLO AULA)
 *  -----------------------------
 *  orarioAulaBlocchi.php torna:
 *    - gridAssenze: stessa struttura grid (eventi extra da visualizzare)
 *    - blockedMap : { "YYYY-MM-DD|HH:MM": ["3A", "4B", ...], ... }
 *
 *  blockedMap serve per "grigiare e barrare" la lezione curricolare quando
 *  la/le classe/i risultano in uscita/viaggio/assenza su quello slot.
 *
 *
 *  DOVE SI DECIDE IL ROWSPAN (PUNTI CHIAVE)
 *  ---------------------------------------
 *  1) slotSignatureForRowspan(evsRaw)
 *       -> costruisce una "firma" (stringa) per lo slot.
 *       -> se due slot consecutivi hanno la stessa firma, si possono unire.
 *
 *  2) computeRowspansForDay(ymd)
 *       -> scorre ORARI e conta quante ore consecutive hanno la stessa firma.
 *       -> applica il "cap" MAX_SPAN (es. 3).
 *
 *  3) cellKey(ymd, ora)
 *       -> recupera eventi dello slot, li filtra, e poi chiama la firma.
 *
 *
 *  DEBUG CONSOLE (AGGIUNTO)
 *  -----------------------
 *  È presente un oggetto DEBUG che controlla:
 *    - stampa firme/celle e logica rowspan
 *    - log degli eventi normalizzati / dominanza
 *    - "preview" HTML generato (prime N righe) e conteggio elementi
 *    - misure e conteggio rowspans in tabella
 *
 *  Per disattivare/attivare rapidamente:
 *    DEBUG.enabled = true/false
 * =====================================================================================
 */

(function () {

  // =============================================================================
  //  DEBUG CONFIG
  // =============================================================================
  const DEBUG = {
    enabled: true,          // master switch
    logFetch: true,         // log chiamate e payload (sintetico)
    logSignature: true,     // log delle firme slot
    logRowspan: true,       // log calcolo rowspans
    logRender: true,        // log generazione HTML / conteggi elementi
    logDomType: false,      // log tipo dominante per cella (molto verboso)
    logHtmlPreview: false,  // stampa un pezzo di HTML (può essere enorme)
    htmlPreviewChars: 1200, // quanti caratteri stampare come preview
    groupCollapsed: true,   // usa console.groupCollapsed invece di group
  };

  function dbg(...args) { if (DEBUG.enabled) console.log(...args); }
  function dbgWarn(...args) { if (DEBUG.enabled) console.warn(...args); }
  function dbgErr(...args) { if (DEBUG.enabled) console.error(...args); }
  function dbgGroup(title) {
    if (!DEBUG.enabled) return;
    if (DEBUG.groupCollapsed) console.groupCollapsed(title);
    else console.group(title);
  }
  function dbgGroupEnd() { if (DEBUG.enabled) console.groupEnd(); }

  // =============================================================================
  //  COSTANTI BASE
  // =============================================================================

  /**
   * Lista degli slot orari (righe della tabella).
   * L'ordine è fondamentale perché:
   *  - il rowspan viene calcolato scorrendo questa lista in sequenza
   *  - "consecutivo" significa: ORARI[i], ORARI[i+1], ORARI[i+2] ...
   */
  const ORARI = [
    "07:50", "08:40", "09:30", "10:30", "11:20", "12:10", "13:00", "13:50",
    "14:40", "15:30", "16:20", "17:10", "18:00", "18:50", "19:40", "20:30", "21:30", "22:20"
  ];

  /**
   * Etichette colonne per vista settimanale.
   * Nella tabella settimanale avrai 5 colonne (lun..ven).
   */
  const GIORNI_LABEL = ["LUN", "MAR", "MER", "GIO", "VEN"];

  /** Fine anno scolastico: usato per popolare l'elenco di settimane nel select */
  const SCHOOL_END = "2026-06-10";

  /**
   * Altezza minima "per slot" in px.
   * Quando una cella ha rowspan>1, ne calcoliamo una min-height = span * SLOT_MIN_PX.
   * Questo serve a evitare celle alte ma contenuto “collassato”.
   */
  const SLOT_MIN_PX = 180;

  // =============================================================================
  //  MEMORIA TARGET PER SCOPE (persistente)
  // =============================================================================
  const LS_KEY_TARGET_BY_SCOPE = "orario_target_by_scope_v1";

  function loadTargetMem() {
    try { return JSON.parse(localStorage.getItem(LS_KEY_TARGET_BY_SCOPE) || "{}") || {}; }
    catch (e) { return {}; }
  }
  function saveTargetMem(mem) {
    try { localStorage.setItem(LS_KEY_TARGET_BY_SCOPE, JSON.stringify(mem || {})); } catch (e) { }
  }

  function getMemTarget(scope) {
    const s = String(scope || "").trim().toUpperCase();
    const mem = loadTargetMem();
    return (mem && mem[s]) ? String(mem[s]) : "";
  }

  function setMemTarget(scope, targetVal) {
    const s = String(scope || "").trim().toUpperCase();
    const v = String(targetVal || "").trim();
    if (!s) return;
    const mem = loadTargetMem();
    if (v) mem[s] = v;
    else delete mem[s];
    saveTargetMem(mem);
  }

  function isEventFullyAbsentByTeacher(ev, teacherAbsMap) {
    const t = normalizeType(ev);
    if (isTeacherAbsenceType(t)) return false; // l'evento "assenza" non lo marchio come lezione

    const whoLines = uniq(
      (Array.isArray(ev._whoList) && ev._whoList.length) ? ev._whoList : toArrWho(ev.who || ev.sub || "")
    ).map(normTxt).filter(Boolean);

    if (!whoLines.length) return false;

    const absentCount = whoLines.filter(w => teacherAbsMap.has(normPersonName(w))).length;
    return absentCount === whoLines.length; // ✅ tutti assenti => lezione pienamente assente
  }
  function normPersonName(s) {
    return normTxt(s).toUpperCase();
  }

  // tipi che indicano "assenza docente" (aggiusta se i tuoi type sono diversi)
  function isTeacherAbsenceType(t) {
    return ["uscC", "uscF", "viag", "perm", "pb", "imp"].includes(t);
  }

  // priorità del motivo (se uno ha più motivi nello stesso slot)
  function teacherAbsencePriority(t) {
    const p = { viag: 100, uscF: 90, uscC: 80, perm: 60, pb: 50, imp: 40 };
    return p[t] || 0;
  }

  // costruisce: { "NOME COGNOME": {type,title,badge,reasonText,prio} }
  function buildTeacherAbsenceMapForSlot(evs, ora) {
    const map = new Map();

    (evs || []).forEach(ev => {
      const t = normalizeType(ev);
      if (!isTeacherAbsenceType(t)) return;

      const whoLines = uniq(
        (Array.isArray(ev._whoList) && ev._whoList.length) ? ev._whoList : toArrWho(ev.who || ev.sub || "")
      ).map(normTxt).filter(Boolean);

      if (!whoLines.length) return;

      const title = normTxt(ev.title || ev.label || "");
      const badge = normTxt(ev.badge || "");

      // testo motivo: preferisci badge, altrimenti title
      const reasonText = badge || title || "Assente";

      const pr = teacherAbsencePriority(t);

      whoLines.forEach(w => {
        const key = normPersonName(w);
        const cur = map.get(key);
        if (!cur || pr > cur.prio) {
          map.set(key, { type: t, title, badge, reasonText, ora, prio: pr });
        }
      });
    });

    return map; // Map
  }
  // =============================================================================
  //  NORMALIZZAZIONE TESTI / ARRAY (per confronti e firme stabili)
  // =============================================================================

  /**
   * normTxt(s)
   * ----------
   * Normalizza un testo per rendere confronti e firme più stabili:
   *  - cast a string (null/undefined => "")
   *  - sostituisce sequenze di whitespace con singolo spazio
   *  - trim
   *
   * Così "Prof. Rossi" e "  Prof.\nRossi " diventano uguali.
   */
  function normTxt(s) {
    return String(s ?? "").replace(/\s+/g, " ").trim();
  }

  function classesSetFromEvents(evs, onlyBlocking = false) {
    const set = new Set();
    (evs || []).forEach(ev => {
      const t = normalizeType(ev);
      if (onlyBlocking && !isBlocking(t)) return;
      toArrMaybe(ev.classi).forEach(c => {
        const v = String(c).trim();
        if (v) set.add(v);
      });
    });
    return set;
  }

  function eventIntersectsClassSet(ev, classSet) {
    if (!classSet || classSet.size === 0) return false;
    const cl = toArrMaybe(ev.classi);
    if (!cl.length) return false;
    return cl.some(c => classSet.has(String(c).trim()));
  }
  /**
   * toArrMaybe(v)
   * -------------
   * Converte:
   *  - array => array
   *  - stringa CSV => array splittato e trim
   *  - null => []
   *
   * In pratica uniforma i campi "classi" / "rooms" che possono arrivare in varie forme.
   */
  function toArrMaybe(v) {
    if (!v) return [];
    if (Array.isArray(v)) return v;
    return String(v).split(",").map(x => x.trim()).filter(Boolean);
  }

  function toArrWho(v) {
    if (!v) return [];
    if (Array.isArray(v)) return v.map(x => String(x)).map(s => s.trim()).filter(Boolean);

    return String(v)
      .replace(/\s+\u00B7\s+/g, "\n")     // " · " -> newline (se lo usi)
      .split(/[\r\n;,|]+/)               // newline, ; , |
      .map(s => s.trim())
      .filter(Boolean);
  }
  /**
   * uniq(arr)
   * ---------
   * Deduplica array di stringhe (trim, scarta vuoti).
   * Serve per non ripetere la stessa classe/aula.
   */
  function uniq(arr) {
    const out = [];
    const seen = new Set();
    (arr || []).forEach(x => {
      const v = (x == null ? "" : String(x)).trim();
      if (!v) return;
      if (seen.has(v)) return;
      seen.add(v);
      out.push(v);
    });
    return out;
  }

  /**
   * arrSortedCsv(v)
   * ---------------
   * Converte v (array o csv) in:
   *   - array pulito
   *   - dedup
   *   - normalizzazione testi
   *   - ordinamento alfabetico
   *   - join con virgola
   *
   * Risultato stabile e indipendente dall'ordine originale.
   */
  function arrSortedCsv(v) {
    return uniq(toArrMaybe(v)).map(x => normTxt(x)).sort().join(",");
  }

  // =============================================================================
  //  NORMALIZZAZIONE EVENTI (tipo, priorità, ecc.)
  // =============================================================================

  /**
   * normalizeType(ev)
   * -----------------
   * Determina il "tipo" di evento.
   * Priorità di lettura:
   *  1) ev.type (campo dati)
   *  2) parsing da ev.class (classe CSS tipo "ev-curr", "ev-viag")
   *  3) fallback "curr"
   *
   * Questo consente di gestire eventi anche se uno dei due canali non è presente.
   */
  function normalizeType(ev) {
    let t = (ev && ev.type != null) ? String(ev.type).trim() : "";
    if (!t) {
      const cls = (ev && ev.class != null) ? String(ev.class) : "";
      const m = cls.match(/\bev-(curr|udi|viag|imp|uscC|uscF|pb|perm|pranzo|studio)\b/i);
      if (m) t = m[1];
    }
    return t ? t : "curr";
  }

  /**
   * priority(type)
   * --------------
   * Mappa i tipi ad una priorità numerica.
   * Utilizzi principali:
   *  1) decidere il "domType" della cella (colore td-*)
   *     -> il tipo con priorità più alta "domina" visivamente
   *  2) ordinare gli eventi in uno slot (prima eventi più importanti)
   */
  function priority(type) {
    const p = { uscF: 100, uscC: 90, viag: 80, imp: 50, pranzo: 35, studio: 35, udi: 20, curr: 10 };
    return p[type] || 0;
  }

  /**
   * isBlocking(type)
   * ----------------
   * Definisce eventi "bloccanti" (uscite/viaggi) che di solito:
   *  - NON devono essere barrati/overridden
   *  - possono avere logiche diverse nel rowspan (dipende dalla tua regola)
   */
  function isBlocking(type) {
    return (type === "viag" || type === "uscC" || type === "uscF");
  }

  /**
   * isAulaNonDisponibile(ev)
   * ------------------------
   * Caso speciale: evento imp con title "AULA NON DISPONIBILE".
   * Se coesiste con eventi reali, lo filtriamo per non “sporcare” la vista.
   */
  function isAulaNonDisponibile(ev) {
    const t = (ev && ev.title != null) ? String(ev.title).trim().toUpperCase() : "";
    return (normalizeType(ev) === "imp" && t === "AULA NON DISPONIBILE");
  }

  // =============================================================================
  //  FIRMA PER ROWSPAN (il "cuore" dell'unione celle)
  // =============================================================================

  /**
   * slotSignatureForRowspan(evsRaw)
   * -------------------------------
   * Prende gli eventi dello slot e produce una stringa "firma".
   * Se la firma di due slot consecutivi è uguale => slot unibili.
   *
   * Step dettagliati:
   *   A) Clona array (evs) per non alterare input.
   *   B) Crea una rappresentazione canonica di ciascun evento:
   *        - tipo normalizzato
   *        - campi testo normalizzati (title, who, badge)
   *        - rooms/classi convertiti in CSV ordinati (stabili)
   *      Questo evita che differenze di formattazione o ordine rompano i confronti.
   *   C) Deduplica eventi uguali tramite Map (chiave k).
   *   D) Seleziona quali eventi contano per la firma (nel tuo codice: escludi blocking).
   *   E) Ordina deterministico (così JSON.stringify è stabile).
   *   F) Restituisce JSON.stringify(canon) come firma.
   *
   * Nota: La "firma" non è usata per renderizzare, solo per confrontare uguaglianza tra slot.
   */
  function slotSignatureForRowspan(evsRaw, scope) {
    const evs = (evsRaw || []).slice();
    const m = new Map();

    const ignoreWho = (String(scope || "").trim().toUpperCase() === "DOCENTE");

    evs.forEach(ev => {
      const t = normalizeType(ev);

      const o = {
        t,
        p: priority(t),
        title: normTxt(ev.title || ev.label || ""),
        // DOCENTE: ignoriamo who per rendere la firma stabile anche in compresenza
        who: ignoreWho ? "" : normTxt(ev.who || ev.sub || ""),
        badge: normTxt(ev.badge || ""),
        rooms: arrSortedCsv(ev.rooms),
        classi: arrSortedCsv(ev.classi)
      };

      const k = `${o.t}|${o.title}|${o.who}|${o.badge}|${o.rooms}|${o.classi}`;
      if (!m.has(k)) m.set(k, o);
    });

    const canonAll = Array.from(m.values());

    const hasBlocking = canonAll.some(x => isBlocking(x.t));
    const hasNonBlocking = canonAll.some(x => !isBlocking(x.t));

    let canon;

    // ✅ Caso chiave:
    // Se nello slot ci sono blocking + altri eventi (es. UD + USCITA),
    // NON devo unire con gli slot successivi "solo blocking".
    // Quindi la firma deve includere TUTTO.
    if (hasBlocking && hasNonBlocking) {
      canon = canonAll;
    } else if (hasBlocking) {
      // slot "solo blocking" => firma basata sui blocking (così si uniscono tra loro)
      canon = canonAll.filter(x => isBlocking(x.t));
    } else {
      // comportamento normale: firma sui non-blocking (come prima)
      canon = canonAll.filter(x => !isBlocking(x.t));
    }

    // fallback
    if (!canon.length) canon = canonAll;

    canon.sort((a, b) => {
      if (a.p !== b.p) return b.p - a.p;
      if (a.t !== b.t) return a.t.localeCompare(b.t);
      if (a.title !== b.title) return a.title.localeCompare(b.title);
      if (a.who !== b.who) return a.who.localeCompare(b.who);
      if (a.badge !== b.badge) return a.badge.localeCompare(b.badge);
      if (a.rooms !== b.rooms) return a.rooms.localeCompare(b.rooms);
      return a.classi.localeCompare(b.classi);
    });

    const sig = JSON.stringify(canon);

    if (DEBUG.enabled && DEBUG.logSignature) {
      dbg("SIGNATURE slot =", sig, { scope, ignoreWho, canon });
    }

    return sig;
  }

  // =============================================================================
  //  ORDINAMENTO / MERGE EVENTI DENTRO UNO SLOT (per RENDER, non per rowspan)
  // =============================================================================

  /**
   * evKeyForSort(ev)
   * ----------------
   * Genera una stringa chiave per ordinare eventi nello slot:
   *  - priority (padded) + type + title + who + badge + rooms + classi
   * Serve per ottenere un ordine coerente e ripetibile.
   */
  function evKeyForSort(ev) {
    const type = normalizeType(ev);
    const title = (ev.title || ev.label || "").trim();
    const who = (ev.who || ev.sub || "").trim();
    const badge = (ev.badge || "").trim();
    const rooms = Array.isArray(ev.rooms) ? ev.rooms.slice().sort().join(",") : "";
    const classi = Array.isArray(ev.classi) ? ev.classi.slice().sort().join(",") : (ev.classi ? String(ev.classi) : "");
    return `${priority(type).toString().padStart(3, "0")}|${type}|${title}|${who}|${badge}|${rooms}|${classi}`;
  }

  /**
   * stableSortSlotEvents(evs)
   * -------------------------
   * Ordina eventi per importanza (desc) e poi per contenuto.
   * Nota sul confronto:
   *   - qui usi il confronto testuale della chiave.
   *   - inverti (return 1 quando ka<kb) per ottenere "desc".
   */
  function stableSortSlotEvents(evs) {
    return (evs || []).slice().sort((a, b) => {
      const ka = evKeyForSort(a);
      const kb = evKeyForSort(b);
      if (ka < kb) return 1;
      if (ka > kb) return -1;
      return 0;
    });
  }

  /**
   * mergeSlotEvents(evs)
   * --------------------
   * Unisce eventi uguali nello stesso slot (stesso type/title/who/badge),
   * concatenando classi e aule senza duplicati.
   *
   * Vantaggi:
   *  - riduci “doppioni”
   *  - se backend produce righe separate per classi diverse, qui le compatti
   */
  function mergeSlotEvents(evs, scope) {
    const map = new Map();
    const isDocenteView = (String(scope || "").trim().toUpperCase() === "DOCENTE");

    (evs || []).forEach(ev => {
      const type = normalizeType(ev);
      const title = (ev.title || ev.label || "").trim();
      const badge = (ev.badge || "").trim();

      // who può essere string o array/csv
      const whoArr = uniq(toArrWho(ev.who || ev.sub)).map(normTxt);

      // ✅ In vista DOCENTE: NON distinguere per who (così unisci compresenze)
      // In altre viste: chiave include who (comportamento attuale)
      const key = isDocenteView
        ? `${type}|||${title}|||${badge}`
        : `${type}|||${title}|||${whoArr.join(",")}|||${badge}`;

      const rooms = toArrMaybe(ev.rooms);
      const classi = toArrMaybe(ev.classi);

      if (!map.has(key)) {
        const copy = Object.assign({}, ev);
        copy.type = type;
        copy.title = title || copy.title;
        copy.badge = badge || copy.badge;

        // salviamo sempre lista docenti (anche se uno)
        copy._whoList = uniq(whoArr);

        // manteniamo who come stringa “visuale”
        copy.who = copy._whoList.join("\n");

        copy.rooms = uniq(rooms);
        copy.classi = uniq(classi);
        map.set(key, copy);
      } else {
        const cur = map.get(key);
        cur.rooms = uniq((cur.rooms || []).concat(rooms));
        cur.classi = uniq((cur.classi || []).concat(classi));

        // ✅ concat docenti
        cur._whoList = uniq((cur._whoList || []).concat(whoArr));
        cur.who = (cur._whoList || []).join("\n");
      }
    });

    return Array.from(map.values());
  }

  /**
   * mergeGrids(gridBase, gridAdd)
   * -----------------------------
   * Unisce due oggetti slot->eventi concatenando gli array per ogni chiave.
   * Usato per aggiungere gridAssenze alla grid principale.
   */
  function mergeGrids(gridBase, gridAdd) {
    const out = Object.assign({}, gridBase || {});
    Object.keys(gridAdd || {}).forEach(k => {
      if (!out[k]) out[k] = [];
      out[k] = out[k].concat(gridAdd[k] || []);
    });
    return out;
  }

  // =============================================================================
  //  OVERRIDDEN: GRIGIO/BARRATO LEZIONI QUANDO CLASSE È BLOCCATA (solo AULA)
  // =============================================================================

  /**
   * slotBlockedSet(blockedMap, ymd, ora)
   * -----------------------------------
   * Converte blockedMap["YYYY-MM-DD|HH:MM"] in Set per lookup O(1).
   */
  function slotBlockedSet(blockedMap, ymd, ora) {
    if (!blockedMap) return null;
    const key = `${ymd}|${ora}`;
    const arr = blockedMap[key];
    if (!arr || !arr.length) return null;
    return new Set(arr.map(x => String(x).trim()).filter(Boolean));
  }

  /**
   * eventIsOverriddenByBlockedClasses(ev, blockedSet)
   * -------------------------------------------------
   * Ritorna true se l'evento contiene almeno una classe presente in blockedSet.
   * Se true e l'evento NON è blocking, allora lo marchiamo con class "ev-overridden".
   */
  function eventIsOverriddenByBlockedClasses(ev, blockedSet) {
    if (!blockedSet || blockedSet.size === 0) return false;
    const cl = toArrMaybe(ev.classi);
    if (!cl.length) return false;
    return cl.some(c => blockedSet.has(c));
  }

  function classesSetFromEvents(evs, onlyBlocking = false) {
    const set = new Set();
    (evs || []).forEach(ev => {
      const t = normalizeType(ev);
      if (onlyBlocking && !isBlocking(t)) return;
      toArrMaybe(ev.classi).forEach(c => {
        const v = String(c).trim();
        if (v) set.add(v);
      });
    });
    return set;
  }

  function eventIntersectsClassSet(ev, classSet) {
    if (!classSet || classSet.size === 0) return false;
    const cl = toArrMaybe(ev.classi);
    if (!cl.length) return false;
    return cl.some(c => classSet.has(String(c).trim()));
  }

  // =============================================================================
  //  HTML UTILS
  // =============================================================================

  function escapeHtml(s) {
    s = (s == null ? "" : "" + s);
    return s.replace(/[&<>"']/g, c => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
  }

  function showInlineMsg(type, msg) {
    const cls = (type === "danger") ? "alert-danger"
      : (type === "warning") ? "alert-warning"
        : (type === "success") ? "alert-success"
          : "alert-info";
    $("#orario_content").html(
      `<div class="alert ${cls}" style="margin:10px 0;">${escapeHtml(msg)}</div>`
    );
  }

  function renderLegend() {
    return `
      <div class="legend" style="margin:6px 0 10px 0;">
        <span class="lg"><span class="dot dot-curr"></span> Curricolare</span>
        <span class="lg"><span class="dot dot-udi"></span> Udienza</span>
        <span class="lg"><span class="dot dot-imp"></span> Impegno in istituto</span>
        <span class="lg"><span class="dot dot-uscC"></span> Uscita nel comune</span>
        <span class="lg"><span class="dot dot-uscF"></span> Uscita fuori comune</span>
        <span class="lg"><span class="dot dot-viag"></span> Viaggio di istruzione</span>
        <span class="lg"><span class="dot dot-pranzo"></span> Aula pausa pranzo</span>
        <span class="lg"><span class="dot dot-studio"></span> Aula studio</span>
      </div>
    `;
  }

  function roomsHtmlFromEv(ev) {
    const rooms = ev && ev.rooms;
    if (!Array.isArray(rooms) || rooms.length === 0) return "";
    const txt = rooms.map(r => escapeHtml(r)).join(", ");
    return `<div class="ev-room">${txt}</div>`;
  }

  function classiHtmlFromEv(ev) {
    const c = ev && ev.classi;
    if (!c) return "";
    const arr = Array.isArray(c) ? c : String(c).split(",").map(x => x.trim()).filter(Boolean);
    if (!arr.length) return "";
    return `<div class="ev-classi">${escapeHtml(arr.join(", "))}</div>`;
  }

  // =============================================================================
  //  DATE UTILS
  // =============================================================================

  function pad2(n) { return (n < 10 ? "0" + n : "" + n); }

  function todayIso() {
    const d = new Date();
    return d.getFullYear() + "-" + pad2(d.getMonth() + 1) + "-" + pad2(d.getDate());
  }

  function isoToIt(iso) {
    if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return iso || "";
    const [y, m, d] = iso.split("-");
    return d + "/" + m + "/" + y;
  }

  function getMonday(isoDate) {
    const d = new Date(isoDate + "T00:00:00");
    const day = d.getDay();
    const diff = (day === 0 ? -6 : 1 - day);
    d.setDate(d.getDate() + diff);
    return d.getFullYear() + "-" + pad2(d.getMonth() + 1) + "-" + pad2(d.getDate());
  }

  function addDays(isoDate, n) {
    const d = new Date(isoDate + "T00:00:00");
    d.setDate(d.getDate() + n);
    return d.getFullYear() + "-" + pad2(d.getMonth() + 1) + "-" + pad2(d.getDate());
  }

  function isWeekend(isoDate) {
    const d = new Date(isoDate + "T00:00:00");
    const day = d.getDay();
    return (day === 0 || day === 6);
  }

  function addSchoolDay(isoDate, delta) {
    let cur = isoDate;
    const step = delta >= 0 ? 1 : -1;
    let left = Math.abs(delta);
    while (left > 0) {
      cur = addDays(cur, step);
      if (!isWeekend(cur)) left--;
    }
    return cur;
  }

  // =============================================================================
  //  UI: SEGMENTED / TOOLBAR
  // =============================================================================

  function syncSegmented($select) {
    const id = "#" + $select.attr("id");
    const val = $select.val();
    $(`.seg-btn[data-target="${id}"]`).each(function () {
      const $b = $(this);
      $b.toggleClass("is-active", $b.data("value") === val);
    });
  }

  function updateToolbarLayout() {
    const scope = ($("#v_scope").val() || "").trim();
    const period = ($("#v_period").val() || "").trim();

    const isWeek = (period === "SETTIMANA");
    const isDay = (period === "GIORNO");
    const isAula = (scope === "AULA");

    $("#wrap_week").toggle(isWeek);
    $("#wrap_date").toggle(isDay);

    // FIX: sempre visibile (evita UI bloccata)
    $("#seg_period").show();

    // mostra prev/next aula solo in vista giorno aula
    $("#btn_prev_aula, #btn_next_aula").toggle(isDay && isAula);
    const $tb = $(".orario-toolbar");

    // pulizia classi precedenti
    $tb.removeClass("scope-AULA scope-CLASSE scope-DOCENTE period-GIORNO period-SETTIMANA");

    // aggiungi classi correnti (servono anche per CSS già scritto)
    $tb.addClass("scope-" + scope);
    $tb.addClass("period-" + period);

    const $t = $("#v_target");
    let w = "260px";
    if (scope === "CLASSE") w = "170px";
    else if (scope === "DOCENTE") w = "220px";
    else if (scope === "AULA") w = (period === "GIORNO") ? "320px" : "320px";

    $t.attr("data-width", w);

    // ✅ refresh bootstrap-select (serve)
    try { $t.selectpicker("refresh"); } catch (e) { }

    // ✅ NON far "crescere" il contenitore target: deve stare largo quanto serve
    $("#wrap_target").css({
      width: "auto",
      flex: "0 0 auto"
    });

    // ✅ Forza la larghezza SOLO del selectpicker dentro wrap_target
    const $bsTarget = $("#wrap_target .bootstrap-select");
    $bsTarget.css("width", w);
    $bsTarget.find("> .dropdown-toggle").css("width", w);
    if (DEBUG.enabled) dbg("ToolbarLayout", { scope, period, isWeek, isDay, isAula });
  }

  function initSegmented() {
    $(".seg-btn").off("click").on("click", function (e) {
      e.preventDefault();
      const $btn = $(this);
      const targetSel = $btn.data("target");
      const value = $btn.data("value");

      // Imposta il valore sul select nascosto/corrispondente
      $(targetSel).val(value);

      // Aggiorna classe visiva is-active sui bottoni
      syncSegmented($(targetSel));

      // Aggiorna elementi toolbar (mostra/nasconde date/week ecc.)
      updateToolbarLayout();

      if (DEBUG.enabled) dbg("SegmentedClick", { targetSel, value });

      // Se cambio scope, devo ricaricare elenco target (aule/classi/docenti)
      if (targetSel === "#v_scope") {
        loadOptions();
        return;
      }

      // Se cambio periodo, aggiorno week picker e ricarico
      if (targetSel === "#v_period") {
        const p = $("#v_period").val();
        if (p === "SETTIMANA") {
          $("#v_week").selectpicker("val", getMonday($("#v_date").val() || todayIso()));
        }
        loadOrario();
      }
    });

    syncSegmented($("#v_scope"));
    syncSegmented($("#v_period"));
    updateToolbarLayout();
  }

  // =============================================================================
  //  UI: WEEK SELECT
  // =============================================================================

  function buildWeekLabel(monIso) {
    const venIso = addDays(monIso, 4);
    return `${isoToIt(monIso)} → ${isoToIt(venIso)}`;
  }

  function fillWeekSelect() {
    const $w = $("#v_week");
    if (!$w.data("selectpicker")) $w.selectpicker();

    $w.empty();
    const startMon = getMonday(todayIso());
    let cur = startMon;

    while (cur <= SCHOOL_END) {
      $w.append(`<option value="${cur}">${buildWeekLabel(cur)}</option>`);
      cur = addDays(cur, 7);
    }

    $w.selectpicker("refresh");
    $w.selectpicker("val", startMon);

    if (DEBUG.enabled) dbg("fillWeekSelect", { startMon, end: SCHOOL_END });
  }

  function setDateAndReload(newIso) {
    $("#v_date").val(newIso);
    $("#v_week").selectpicker("val", getMonday(newIso));
    loadOrario();
  }

  // =============================================================================
  //  UI: NAVIGAZIONE
  // =============================================================================

  function shiftAula(delta) {
    const $t = $("#v_target");
    const opts = $t.find("option").map(function () { return $(this).val(); }).get().filter(v => v);
    if (!opts.length) return;

    const cur = $t.val();
    let idx = opts.indexOf(cur);
    if (idx < 0) idx = 0;

    idx = idx + delta;
    if (idx < 0) idx = 0;
    if (idx >= opts.length) idx = opts.length - 1;

    $t.selectpicker("val", opts[idx]);
    if (DEBUG.enabled) dbg("shiftAula", { delta, from: cur, to: opts[idx] });
    loadOrario();
  }

  function prevDayNav() {
    const d = ($("#v_date").val() || todayIso()).trim();
    const nd = addSchoolDay(d, -1);
    if (DEBUG.enabled) dbg("prevDayNav", { from: d, to: nd });
    setDateAndReload(nd);
  }

  function nextDayNav() {
    const d = ($("#v_date").val() || todayIso()).trim();
    const nd = addSchoolDay(d, +1);
    if (DEBUG.enabled) dbg("nextDayNav", { from: d, to: nd });
    setDateAndReload(nd);
  }

  function prevNav() {
    const period = $("#v_period").val();
    if (period === "GIORNO") { prevDayNav(); return; }

    const d = ($("#v_date").val() || todayIso()).trim();
    const nd = addDays(d, -7);
    if (DEBUG.enabled) dbg("prevWeekNav", { from: d, to: nd });
    setDateAndReload(nd);
  }

  function nextNav() {
    const period = $("#v_period").val();
    if (period === "GIORNO") { nextDayNav(); return; }

    const d = ($("#v_date").val() || todayIso()).trim();
    const nd = addDays(d, +7);
    if (DEBUG.enabled) dbg("nextWeekNav", { from: d, to: nd });
    setDateAndReload(nd);
  }

  // =============================================================================
  //  LOAD OPTIONS (target list)
  // =============================================================================

  function loadOptions() {
    const scope = $("#v_scope").val();
    const $t = $("#v_target");

    $t.empty().append(`<option value="">Seleziona...</option>`);
    $t.selectpicker("refresh");

    if (DEBUG.enabled && DEBUG.logFetch) dbgGroup("FETCH options orarioGetOptions.php");

    $.getJSON("orarioGetOptions.php", { scope }, function (r) {
      if (DEBUG.enabled && DEBUG.logFetch) {
        dbg("response ok?", r && r.ok, "items", (r && r.items) ? r.items.length : 0);
        dbgGroupEnd();
      }

      if (!r || r.ok !== true) {
        showInlineMsg("danger", (r && r.error) ? r.error : "Errore caricamento opzioni");
        return;
      }

      const items = (r.items || []);
      items.forEach(it => {
        $t.append(`<option value="${escapeHtml(it.id)}">${escapeHtml(it.label)}</option>`);
      });
      $t.selectpicker("refresh");

      // auto-select prima opzione e carica subito
      // ----- ripristino target per-scope -----
      const scopeUp = (scope || "").toString().trim().toUpperCase();
      const remembered = getMemTarget(scopeUp);

      // se esiste un valore ricordato e sta nelle opzioni, lo ripristino
      let restored = false;
      if (remembered) {
        const exists = $t.find(`option[value="${CSS.escape(remembered)}"]`).length > 0;
        if (exists) {
          $t.selectpicker("val", remembered);
          restored = true;
        }
      }

      // se non ho ripristinato e non c'è selezione valida, prendo la prima opzione
      if (!restored) {
        const cur = ($t.val() || "").toString().trim();
        const curExists = cur && ($t.find(`option[value="${CSS.escape(cur)}"]`).length > 0);
        if (!curExists && items.length > 0) {
          $t.selectpicker("val", String(items[0].id));
        }
      }

      // salva lo scope corrente come "prevScope" (così al prossimo cambio lo memorizzi bene)
      $("#v_scope").data("prevScope", scopeUp);

      // se ho un target selezionato, carico; altrimenti messaggio
      if (($t.val() || "").toString().trim()) loadOrario();
      else showInlineMsg("info", "Seleziona un valore per visualizzare l’orario.");
    });
  }

  // =============================================================================
  //  FETCH BLOCCCHI/ASSENZE (solo AULA)
  // =============================================================================

  async function fetchAulaBlocchi(period, date, aulaId) {
    try {
      if (DEBUG.enabled && DEBUG.logFetch) dbgGroup("FETCH blocchi orarioAulaBlocchi.php");
      const r = await $.getJSON("orarioAulaBlocchi.php", { scope: "AULA", period, date, target: aulaId });
      if (DEBUG.enabled && DEBUG.logFetch) {
        dbg("response ok?", r && r.ok, {
          gridAssenzeKeys: r && r.gridAssenze ? Object.keys(r.gridAssenze).length : 0,
          blockedMapKeys: r && r.blockedMap ? Object.keys(r.blockedMap).length : 0
        });
        dbgGroupEnd();
      }
      if (!r || !r.ok) return { gridAssenze: {}, blockedMap: {} };
      return { gridAssenze: (r.gridAssenze || {}), blockedMap: (r.blockedMap || {}) };
    } catch (e) {
      if (DEBUG.enabled) dbgWarn("fetchAulaBlocchi error", e);
      return { gridAssenze: {}, blockedMap: {} };
    }
  }

  // =============================================================================
  //  RENDER GRID: GIORNO o SETTIMANA
  // =============================================================================

  function renderGridFromGrid(period, dateIso, grid, scope, blockedMap) {
    const $c = $("#orario_content");

    // -------------------------------------------------------------------------
    //  Helper: filtra AULA NON DISPONIBILE se coesistono eventi reali
    // -------------------------------------------------------------------------
    function filterAulaNonDisponibile(evs) {
      if (!evs || !evs.length) return evs || [];
      const hasReal = evs.some(ev => (ev.title || "").toUpperCase().trim() !== "AULA NON DISPONIBILE");
      if (!hasReal) return evs;
      return evs.filter(ev => (ev.title || "").toUpperCase().trim() !== "AULA NON DISPONIBILE");
    }

    // -------------------------------------------------------------------------
    //  cellKey: calcola la firma “per slot”
    // -------------------------------------------------------------------------
    function cellKey(ymd, ora) {
      const key = ymd + "|" + ora;
      let evs = grid[key] || [];
      if (!evs.length) return "";

      evs = filterAulaNonDisponibile(evs);

      // firma usata SOLO per comparare slot consecutivi
      const sig = slotSignatureForRowspan(evs, scope);

      if (DEBUG.enabled && DEBUG.logSignature) {
        dbg(`cellKey ${key} => sigLen=${sig.length}`);
      }

      return sig;
    }

    // -------------------------------------------------------------------------
    //  computeRowspansForDay: crea la mappa rowspan per un giorno
    // -------------------------------------------------------------------------
    function computeRowspansForDay(ymd) {
      const spans = {};
      const MAX_SPAN = 18; // limite massimo di unione (3 ore)

      if (DEBUG.enabled && DEBUG.logRowspan) dbgGroup(`ROWSPAN compute for day ${ymd}`);

      for (let i = 0; i < ORARI.length; i++) {
        const ora = ORARI[i];

        // se questa ora è già stata “assorbita” da una cella precedente, salto
        if (spans[ora]?.skip) continue;

        const k = cellKey(ymd, ora);

        // slot vuoto: niente unione
        if (!k) {
          spans[ora] = { span: 1, skip: false };
          if (DEBUG.enabled && DEBUG.logRowspan) dbg(`@${ora} empty -> span=1`);
          continue;
        }

        // provo ad allungare la cella finché:
        //  - non supero MAX_SPAN
        //  - la firma rimane uguale
        let span = 1;

        if (DEBUG.enabled && DEBUG.logRowspan) dbg(`@${ora} start span, sigLen=${k.length}`);

        for (let j = i + 1; j < ORARI.length; j++) {
          if (span >= MAX_SPAN) {
            if (DEBUG.enabled && DEBUG.logRowspan) dbg(`  stop: reached MAX_SPAN=${MAX_SPAN}`);
            break;
          }

          const ora2 = ORARI[j];
          const k2 = cellKey(ymd, ora2);

          // se cambia firma, interrompo: da qui in poi è un'altra cella
          if (k2 !== k) {
            if (DEBUG.enabled && DEBUG.logRowspan) dbg(`  stop: signature differs at ${ora2}`);
            break;
          }

          // altrimenti posso unire questa ora
          span++;
          if (DEBUG.enabled && DEBUG.logRowspan) dbg(`  merge ok with ${ora2} => span=${span}`);
        }

        // memorizzo span sulla prima ora del blocco
        spans[ora] = { span, skip: false };

        // tutte le ore successive del blocco diventano skip=true
        for (let j = i + 1; j < i + span; j++) {
          spans[ORARI[j]] = { span: 0, skip: true };
        }

        // salto direttamente alla fine del blocco
        i = i + span - 1;
      }

      if (DEBUG.enabled && DEBUG.logRowspan) dbgGroupEnd();
      return spans;
    }

    // -------------------------------------------------------------------------
    //  cellData: costruisce il contenuto HTML della cella (eventi)
    // -------------------------------------------------------------------------
    function cellData(ymd, ora, span = 1) {
      const key = ymd + "|" + ora;
      let evs = grid[key] || [];
      if (!evs.length) return { html: "", tdClass: "" };

      // filtro eventi “di disturbo”
      evs = filterAulaNonDisponibile(evs);

      // normalizzo: unisco duplicati e ordino per importanza (render)
      evs = stableSortSlotEvents(mergeSlotEvents(evs, scope));
      let teacherAbsMap = buildTeacherAbsenceMapForSlot(evs, ora);

      const scopeUp = String(scope || "").trim().toUpperCase();

      function getTargetDocenteKey() {
        // label del select (meglio del value)
        const t = ($("#v_target option:selected").text() || $("#v_target").val() || "");
        return normPersonName(t);
      }

      if (scopeUp === "DOCENTE") {
        const targetKey = getTargetDocenteKey();

        // docenti presenti nelle lezioni (curr/imp/udi...) dello slot
        const lessonTeacherKeys = new Set();
        const lessonClassKeys = new Set();

        (evs || []).forEach(e => {
          const t = normalizeType(e);
          if (isTeacherAbsenceType(t)) return; // solo lezioni, non assenze

          // docenti della lezione
          const wl = uniq((Array.isArray(e._whoList) && e._whoList.length) ? e._whoList : toArrWho(e.who || e.sub || ""))
            .map(normTxt).filter(Boolean);
          wl.forEach(n => lessonTeacherKeys.add(normPersonName(n)));

          // classi della lezione (opzionale ma aiuta a evitare falsi match)
          toArrMaybe(e.classi).forEach(c => lessonClassKeys.add(String(c).trim()));
        });

        const hasTargetInSlot = lessonTeacherKeys.has(targetKey);

        // Filtra assenze: tieni
        // - assenze del docente stesso (target)
        // - assenze di colleghi SOLO se nello slot c'è una lezione in compresenza (target + collega)
        evs = (evs || []).filter(e => {
          const t = normalizeType(e);
          if (!isTeacherAbsenceType(t)) return true; // lezioni sempre ok

          const absWho = uniq((Array.isArray(e._whoList) && e._whoList.length) ? e._whoList : toArrWho(e.who || e.sub || ""))
            .map(normTxt).filter(Boolean);
          const absKeys = absWho.map(normPersonName);

          // se è un'assenza del docente selezionato → mostra sempre
          if (absKeys.includes(targetKey)) return true;

          // altrimenti deve essere un collega IN COMPRESENZA nello slot
          if (!hasTargetInSlot) return false;

          // collega assente deve comparire tra i docenti della lezione nello slot
          const colleagueInLesson = absKeys.some(k => lessonTeacherKeys.has(k));
          if (!colleagueInLesson) return false;

          // opzionale: se assenza ha classi valorizzate, deve intersecare le classi della lezione nello slot
          const absClassi = toArrMaybe(e.classi);
          if (absClassi.length && lessonClassKeys.size) {
            const okClass = absClassi.some(c => lessonClassKeys.has(String(c).trim()));
            if (!okClass) return false;
          }

          return true;
        });

        // ricostruisci la mappa assenze DOPO il filtro (fondamentale)
        // (sovrascrive la const? -> quindi sopra NON deve essere const)
      }
      teacherAbsMap = buildTeacherAbsenceMapForSlot(evs, ora);
      const blockingClassSet = classesSetFromEvents(evs, true);
      // In scope CLASSE: se nello slot c'è un evento bloccante (viaggio/uscita),
      // allora le lezioni "non bloccanti" devono diventare overridden (grigie/barrate).
      const slotHasBlocking = (String(scope || "").trim().toUpperCase() === "CLASSE")
        ? evs.some(e => isBlocking(normalizeType(e)))
        : false;
      // domType = tipo con priorità più alta -> classe CSS td-*
      let domType = "curr", domP = -1;
      evs.forEach(e => {
        const t = normalizeType(e);
        const pr = priority(t);
        if (pr > domP) { domP = pr; domType = t; }
      });
      const tdClass = "td-" + domType;

      if (DEBUG.enabled && DEBUG.logDomType) dbg("domType", { key, domType, domP });

      // set di classi bloccate (solo AULA)
      const blockedSet = (scope === "AULA") ? slotBlockedSet(blockedMap, ymd, ora) : null;


      /**
       * Layout:
       *  - wrapper cell-wrap con flex column: eventi uno sotto l'altro
       *  - se rowspan>1 metto min-height, così la cella è davvero “alta”
       */

      // calcolo altezza cella in caso di rowspan
      const spanPx = (span && span > 1) ? (span * SLOT_MIN_PX) : 0;

      // wrapper SEMPRE flex + min-height se rowspan>1 (niente abs)
      const wrapCls = "cell-wrap" + ((span && span > 1) ? " is-tall" : "");
      const wrapStyle = ` style="display:flex;flex-direction:column;height:100%;${spanPx ? `min-height:${spanPx}px;` : ``}"`; /* play: flex; flex - direction: column; height: 100 %; " */
      const html =
        `<div class="${wrapCls}"${wrapStyle}>` +

        evs.map((ev) => {
          const type = normalizeType(ev);
          const title = escapeHtml(ev.title || ev.label || "");
          const whoRaw = String(ev.who || ev.sub || "");

          // split su newline OPPURE su separatori comuni: , ; / | (con spazi opzionali)
          const whoLines = uniq(
            (Array.isArray(ev._whoList) && ev._whoList.length)
              ? ev._whoList
              : toArrWho(ev.who || ev.sub || "")
          ).map(normTxt);
          const badge = escapeHtml(ev.badge || "");

          /**
           * OVERRIDDEN:
           *  - projected: l'evento riguarda classi che risultano bloccate nello slot
           *  - overridden SOLO se:
           *      a) scope === AULA
           *      b) projected === true
           *      c) NON blocking (viaggi/uscite)
           *      d) NON "AULA NON DISPONIBILE"
           *
           * Il risultato è una classe CSS "ev-overridden" che tu stile (grigio + barrato).
           */

          // CLASSE: se nello slot c'è un blocking, barrami i non-blocking
          const projectedClasse = (String(scope || "").trim().toUpperCase() === "CLASSE") ? slotHasBlocking : false;

          const scopeUp = String(scope || "").trim().toUpperCase();

          const projectedAula = (scopeUp === "AULA")
            ? eventIsOverriddenByBlockedClasses(ev, blockedSet)
            : false;

          // CLASSE e DOCENTE: overridden se interseca le classi di un blocking nello slot
          const projectedByBlocking = (scopeUp === "CLASSE")
            ? eventIntersectsClassSet(ev, blockingClassSet)
            : false;

          // ✅ DOCENTE: non fare overridden per logiche "classe bloccata"
          const projected = projectedAula || projectedByBlocking;
          const fullyAbsent = isEventFullyAbsentByTeacher(ev, teacherAbsMap);
          const absentCls = fullyAbsent ? " ev-absent-full" : "";
          const overridden = (projected && !isBlocking(type) && !isAulaNonDisponibile(ev))
            ? " ev-overridden"
            : "";
          const isOver = overridden.includes("ev-overridden");

          const classiHtml = classiHtmlFromEv(ev);
          const roomsHtml = roomsHtmlFromEv(ev);

          /**
           * fillStyle:
           *  - quando la cella è alta (rowspan>1)
           *  - e questo evento è overridden
           *  -> gli diamo flex:1 così “riempie” verticalmente lo spazio disponibile
           */
          const whoForTitle = whoLines.length ? whoLines.join(" · ") : "";
          // riempi verticalmente SOLO l'evento principale quando c'è rowspan>1
          // regola: se nello slot ci sono più eventi, fai crescere solo i "non-assenza" (curr/udi/...) e non pb/perm/usc/viag/imp
          // ✅ In rowspan voglio far "riempire" anche USCITA/VIAGGIO (blocking),
          // ma NON permessi/pb ecc.

          // ✅ In vista AULA: possono “riempire” anche curr/udi/imp (compreso AULA NON DISPONIBILE)
          // ✅ In vista DOCENTE/CLASSE: evita di stirare eventi che tu tratti come “assenza” (pb/perm/usc/viag/imp)
          const canFill =
            (span && span > 1) &&
            (
              scopeUp === "AULA"
                ? true
                : !isTeacherAbsenceType(type)
            );

          const fillStyle = canFill
            ? ` style="flex:1;display:flex;flex-direction:column;min-height:0;"`
            : "";

          return `
            <div class="ev ev-${type}${overridden}${absentCls}"${fillStyle}
              title="${escapeHtml(title + (whoForTitle ? " - " + whoForTitle : "") + (badge ? " - " + badge : ""))}">
              <div class="ev-title">${title}</div>
            ${whoLines.length ? `
              <div class="ev-who">
                ${whoLines.map(w => {
            const k = normPersonName(w);

            // ✅ barra SOLO dentro eventi "non assenza"
            const canStrike = !isTeacherAbsenceType(type);
            const abs = canStrike ? teacherAbsMap.get(normPersonName(w)) : null;

            if (!abs) return `<div class="ev-who-line">${escapeHtml(w)}</div>`;

            return `<div class="ev-who-line is-absent absent-${abs.type}" title="${escapeHtml(abs.reasonText)}">${escapeHtml(w)}</div>`;
          }).join("")}
            </div>
          ` : ``}

              ${classiHtml}
              ${badge ? `<div><span class="ev-badge badge-${type}">${badge}</span></div>` : ``}
              ${roomsHtml}
            </div>
          `;
        }).join("") +

        `</div>`;

      return { html, tdClass };
    }

    // -------------------------------------------------------------------------
    //  RENDER: GIORNO
    // -------------------------------------------------------------------------
    if (period === "GIORNO") {
      dbgGroup(`RENDER GIORNO ${dateIso} scope=${scope}`);
      const spans = computeRowspansForDay(dateIso);

      let html = `<table class="orario-grid"><thead><tr>
        <th class="ora-col">Ora</th>
        <th>${isoToIt(dateIso)}</th>
      </tr></thead><tbody>`;

      ORARI.forEach(ora => {
        html += `<tr><td class="ora-col">${ora}</td>`;

        const sp = spans[ora] || { span: 1, skip: false };

        // se skip, non stampo la cella (è assorbita dal rowspan precedente)
        if (sp.skip) {
          html += `</tr>`;
          return;
        }

        // calcolo contenuto cella con il suo span (per min-height e fillStyle)
        const cd = cellData(dateIso, ora, sp.span);

        // se span>1 aggiungo attributo rowspan
        const rs = (sp.span && sp.span > 1) ? ` rowspan="${sp.span}"` : "";

        const tdStyle = (sp.span && sp.span > 1)
          ? ` style="height:${sp.span * SLOT_MIN_PX}px;vertical-align:top;"`
          : ` style="vertical-align:top;"`;
        html += `<td class="${cd.tdClass}"${rs}${tdStyle}>${cd.html}</td>`;
        html += `</tr>`;
      });

      html += `</tbody></table>`;

      // Inserisco legenda + tabella nel DOM
      $c.html(renderLegend() + html);

      // DEBUG: analisi DOM risultante (rowspan count, ev count, ecc.)
      if (DEBUG.enabled && DEBUG.logRender) {
        const $tbl = $c.find("table.orario-grid");
        const tdWithRowspan = $tbl.find("td[rowspan]").length;
        const evCount = $tbl.find(".ev").length;
        dbg("DOM render stats", { tdWithRowspan, evCount, rows: $tbl.find("tbody tr").length });

        if (DEBUG.logHtmlPreview) {
          dbg("HTML preview", ($c.html() || "").slice(0, DEBUG.htmlPreviewChars));
        }
      }

      dbgGroupEnd();
      return;
    }

    // -------------------------------------------------------------------------
    //  RENDER: SETTIMANA
    // -------------------------------------------------------------------------
    dbgGroup(`RENDER SETTIMANA dateRef=${dateIso} scope=${scope}`);

    const mon = getMonday(dateIso);
    const days = GIORNI_LABEL.map((lab, i) => ({ lab, iso: addDays(mon, i) }));

    // calcolo rowspans per ogni giorno (colonna)
    const spansByDay = {};
    days.forEach(d => { spansByDay[d.iso] = computeRowspansForDay(d.iso); });

    let html = `<table class="orario-grid"><thead><tr>
      <th class="ora-col">Ora</th>
      ${days.map(d => `<th>${d.lab}<div style="opacity:.75;font-size:16px;">${isoToIt(d.iso)}</div></th>`).join("")}
    </tr></thead><tbody>`;

    ORARI.forEach(ora => {
      html += `<tr><td class="ora-col">${ora}</td>`;

      days.forEach(d => {
        const sp = spansByDay[d.iso]?.[ora] || { span: 1, skip: false };
        if (sp.skip) return;

        const cd = cellData(d.iso, ora, sp.span);
        const rs = (sp.span && sp.span > 1) ? ` rowspan="${sp.span}"` : "";
        const tdStyle = (sp.span && sp.span > 1)
          ? ` style="height:${sp.span * SLOT_MIN_PX}px;vertical-align:top;"`
          : ` style="vertical-align:top;"`;
        html += `<td class="${cd.tdClass}"${rs}${tdStyle}>${cd.html}</td>`;
      });

      html += `</tr>`;
    });

    html += `</tbody></table>`;
    $c.html(renderLegend() + html);

    if (DEBUG.enabled && DEBUG.logRender) {
      const $tbl = $c.find("table.orario-grid");
      const tdWithRowspan = $tbl.find("td[rowspan]").length;
      const evCount = $tbl.find(".ev").length;
      dbg("DOM render stats", { tdWithRowspan, evCount, days: days.map(x => x.iso), rows: $tbl.find("tbody tr").length });

      if (DEBUG.logHtmlPreview) {
        dbg("HTML preview", ($c.html() || "").slice(0, DEBUG.htmlPreviewChars));
      }
    }

    dbgGroupEnd();
  }


  // =============================================================================
  //  MULTI-AULE (AULA + GIORNO): loadOrarioMultiAuleGiorno
  // =============================================================================

  function loadOrarioMultiAuleGiorno() {
    const scope = "AULA"; // ✅ in multi-aule siamo sempre in vista AULA
    const date = ($("#v_date").val() || "").trim(); // in vista giorno, date è il riferimento per il caricamento di tutte le aule (stesso giorno)
    if (!date) { showInlineMsg("warning", "Seleziona una data."); return; }

    const $t = $("#v_target"); // select aule 
    const all = $t.find("option").map(function () // costruisco array di {id, label} per tutte le opzioni (aule) disponibili
    {
      return {
        id: ($(this).val() || "").trim(), label: ($(this).text() || "").trim() // per debug: id e label 
      };
    }).get().filter(x => x.id);

    if (!all.length) { showInlineMsg("warning", "Nessuna aula disponibile."); return; }

    const selected = ($t.val() || "").trim(); // aula selezionata (riferimento per le altre, che saranno quelle “vicine” in elenco)
    let startIdx = all.findIndex(x => x.id === selected); // indice dell'aula selezionata nell'elenco di tutte le aule
    if (startIdx < 0) startIdx = 0; // se aula selezionata non trovata (edge case), parto dalla prima aula

    const visible = all.slice(startIdx, startIdx + 5); // prendo fino a 5 aule consecutive a partire da quella selezionata (inclusa), per mostrare più aule contemporaneamente
    const firstLabel = visible[0] ? (visible[0].label || visible[0].id) : selected; // per titolo: prendo label della prima aula visibile (che dovrebbe essere quella selezion

    $("#orario_title").text(`Vista AULA · ${firstLabel} (+4) · giorno · ${isoToIt(date)}`);
    showInlineMsg("info", "Caricamento orario aule...");

    if (DEBUG.enabled) dbg("multi-aule", { date, selected, visible });

    const calls = visible.map(a => $.getJSON("orarioRead.php", {
      // stesso endpoint di lettura, ma con target=aulaId e scope=GIORNO per caricare tutte le aule contemporaneamente
      scope: "AULA", period: "GIORNO", date: date, target: a.id
    }));

    $.when.apply($, calls).done(async function () {
      // quando tutte le chiamate sono complete, questa callback riceve i risultati in arguments (uno per ogni chiamata)
      const args = (calls.length === 1) ? [arguments] : Array.from(arguments);
      // se c'è una sola chiamata, arguments è già l'array dei risultati, altrimenti è un array di array [result, status, xhr] per ogni chiamata

      const cols = args.map((pack, i) => {
        // per ogni risultato, prendo il primo elemento (response JSON) e lo associo all'aula corrispondente
        const data = pack && pack[0] ? pack[0] : null;
        // estraggo response JSON (o null se formato inatteso)
        return { aula: visible[i], res: data };
        // ritorno array di {aula, res} dove res è il risultato della chiamata per quell'aula
      }).filter(x => x.res && x.res.ok);
      // filtro solo quelli con risposta valida e ok=true

      if (!cols.length) { showInlineMsg("danger", "Errore lettura orario aule."); return; }

      // aggiungo blocchi per ogni colonna
      const blocchi = await Promise.all(cols.map(c => fetchAulaBlocchi("GIORNO", date, c.aula.id)));
      // per ogni colonna, chiedo i blocchi/assenze specifici per quell'aula e quel giorno
      cols.forEach((c, idx) => {
        // aggiungo i blocchi alla risposta di ogni colonna
        c.res.grid = mergeGrids(c.res.grid || {}, blocchi[idx].gridAssenze || {});
        // unisco la griglia principale con quella delle assenze (che contiene gli slot bloccati per assenza docenti)
        c.blockedMap = blocchi[idx].blockedMap || {};
        // memorizzo la mappa dei blocchi (per evidenziare gli eventi overridden in render)
      });

      // filtro "aula non disponibile" come in render principale
      function filterAulaNonDisponibile(evs) {
        if (!evs || !evs.length) return evs || [];
        // se non ci sono eventi, ritorno array vuoto
        const hasReal = evs.some(ev => (ev.title || "").toUpperCase().trim() !== "AULA NON DISPONIBILE");
        // controllo se ci sono eventi reali nello slot (diversi da "aula non disponibile")
        if (!hasReal) return evs;
        // se non ci sono eventi reali, mantengo tutto (anche "aula non disponibile")
        return evs.filter(ev => (ev.title || "").toUpperCase().trim() !== "AULA NON DISPONIBILE");
        // se ci sono eventi reali, filtro via "aula non disponibile" per evitare di mostrarlo insieme ad altri eventi 
        // (come in render principale)
      }

      // firma per slot nella colonna
      function cellKeyFromGrid(g, ora)
      // costruisce la firma dello slot per questa ora, usata per calcolare i rowspan 
      // (se slot consecutivi hanno stessa firma, possono essere uniti) 
      {
        const key = date + "|" + ora;
        // chiave per accedere agli eventi di questo slot nella griglia
        let evs = g[key] || [];
        // prendo eventi per questo slot (o array vuoto se non ci sono)
        if (!evs.length) return "";
        // se non ci sono eventi, la firma è vuota (slot vuoto)
        evs = filterAulaNonDisponibile(evs);
        // filtro eventi "aula non disponibile" se coesistono con altri eventi reali (come in render)
        return slotSignatureForRowspan(evs, "AULA");
        // calcolo la firma dello slot, che è una stringa che rappresenta il contenuto dello slot 
        // (usata per confrontare slot consecutivi e decidere se unire con rowspan)
      }

      // rowspans per colonna
      function computeSpansForColumn(g) {
        const spans = {}; // mappa ora => {span: N, skip: bool} per questa colonna (aula)
        const MAX_SPAN = 18 // limite massimo di unione (5 ore)

        if (DEBUG.enabled && DEBUG.logRowspan) dbgGroup(`ROWSPAN compute column (multi) ${date}`);

        for (let i = 0; i < ORARI.length; i++) {
          // ciclo sulle ore della giornata
          const ora = ORARI[i];
          // ora corrente per cui calcolare rowspan
          if (spans[ora]?.skip) continue;
          // se questa ora è già stata assorbita da un rowspan precedente, salto

          const k = cellKeyFromGrid(g, ora);
          // calcolo la firma dello slot per questa ora (usata per decidere se unire con le ore successive)
          if (!k) { spans[ora] = { span: 1, skip: false }; continue; }
          // se slot vuoto, span=1 e non skip (non unisco con le ore successive)

          let span = 1;
          for (let j = i + 1; j < ORARI.length; j++) {
            // provo ad allungare la cella finché posso unire con le ore successive
            if (span >= MAX_SPAN) break;
            // se supero MAX_SPAN, non unisco più
            const ora2 = ORARI[j];
            // ora successiva con cui provo a unire
            const k2 = cellKeyFromGrid(g, ora2);
            // firma dello slot dell'ora successiva
            if (k2 !== k) break;
            // se cambia firma, non posso unire più (è un altro slot), interrompo
            span++;
            // altrimenti posso unire questa ora, incremento span e continuo a provare con le ore successive
          }

          spans[ora] = { span, skip: false };
          // memorizzo lo span calcolato per questa ora
          for (let j = i + 1; j < i + span; j++) spans[ORARI[j]] = { span: 0, skip: true };
          // tutte le ore successive che fanno parte di questo blocco diventano skip=true (non stampo la cella, è assorbita dallo rowspan)
          i = i + span - 1;
          // salto direttamente alla fine del blocco di ore unite
        }

        if (DEBUG.enabled && DEBUG.logRowspan) dbgGroupEnd();
        return spans;
        // ritorno la mappa degli span per questa colonna (aula)
      }

      const spansByCol = cols.map(c => computeSpansForColumn(c.res.grid || {}));
      // calcolo i rowspans per ogni colonna (aula) in base alla sua griglia di eventi

      // costruzione HTML tabella
      let html = `<table class="orario-grid"><thead><tr> 
        <th class="ora-col">Ora</th>
        ${cols.map(c => `<th>${escapeHtml(c.aula.label || c.aula.id)}</th>`).join("")} 
      </tr></thead><tbody>`;
      // header con nome aule

      ORARI.forEach(ora => {
        // ciclo sulle ore per costruire le righe della tabella
        html += `<tr><td class="ora-col">${ora}</td>`;

        cols.forEach((c, colIdx) => {
          // per ogni colonna (aula) costruisco la cella corrispondente a questa ora
          const g = c.res.grid || {};
          // griglia di eventi per questa colonna (aula)
          const spans = spansByCol[colIdx] || {};
          // mappa degli span per questa colonna (aula)
          const sp = spans[ora] || { span: 1, skip: false };
          // span e skip per questa ora nella colonna
          if (sp.skip) return;
          // se skip=true, non stampo la cella (è assorbita dallo rowspan precedente), passo alla colonna successiva

          const rs = (sp.span && sp.span > 1) ? ` rowspan="${sp.span}"` : "";
          // se span>1 aggiungo attributo rowspan

          const key = date + "|" + ora;
          // chiave per accedere agli eventi di questo slot nella griglia
          let evs = g[key] || [];
          // prendo eventi per questo slot (o array vuoto se non ci sono)
          evs = filterAulaNonDisponibile(evs);
          // filtro eventi "aula non disponibile" se coesistono con altri eventi reali (come in render principale)
          evs = stableSortSlotEvents(mergeSlotEvents(evs, scope));
          // normalizzo eventi: unisco duplicati e ordino per importanza (render)

          if (!evs.length) { html += `<td${rs}></td>`; return; }
          // se non ci sono eventi, cella vuota (ma con eventuale rowspan), passo alla colonna successiva

          let domType = "curr", domP = -1;
          // domType = tipo con priorità più alta tra gli eventi dello slot, usato per la classe CSS della cella (td-*)
          evs.forEach(e => {
            // calcolo domType e domP (priorità) per questo slot, iterando su tutti gli eventi e prendendo quello con priorità più alta
            const t = normalizeType(e);
            // normalizzo il tipo dell'evento (es. "lezione", "viaggio", ecc.)
            const pr = priority(t);
            // calcolo la priorità di questo tipo (funzione che assegna un numero a ogni tipo, più alto = più importante)
            if (pr > domP) { domP = pr; domType = t; }
            // se questa priorità è più alta di quella finora trovata, aggiorno domType e domP
          });
          const tdClass = "td-" + domType;
          // classe CSS della cella basata sul tipo dell'evento più importante nello slot

          const blockedSet = slotBlockedSet(c.blockedMap, date, ora);
          // set di classi bloccate per questo slot (usato per evidenziare eventi overridden)
          const spanPx = (sp.span && sp.span > 1) ? (sp.span * SLOT_MIN_PX) : 0;
          // calcolo altezza in pixel della cella in caso di rowspan (usato per dare min-height al wrapper degli eventi)
          const wrapStyle = spanPx
            ? ` style="min-height:${spanPx}px;min-height:${spanPx}px;display:flex;flex-direction:column;"`
            : ` style="display:flex;flex-direction:column;"`;
          // stile del wrapper degli eventi: se c'è rowspan, do min-height per farlo "alto", altrimenti è solo un flex column normale
          const wrapCls = "cell-wrap" + ((sp.span && sp.span > 1) ? " is-tall" : "");
          const htmlEv =
            `<div class="${wrapCls}"${wrapStyle}>` +
            // wrapper con flex column per mettere gli eventi uno sotto l'altro, e min-height se c'è rowspan
            evs.map((ev) => {
              const type = normalizeType(ev);
              // normalizzo tipo evento
              const title = escapeHtml(ev.title || ev.label || "");
              // titolo dell'evento (o label, o stringa vuota)
              const whoRaw = String(ev.who || ev.sub || "");

              // split su newline OPPURE su separatori comuni: , ; / | (con spazi opzionali)
              const whoLines = whoRaw
                .split(/\r?\n|[;,/|]\s*/)
                .map(s => s.trim())
                .filter(Boolean);
              // chi è coinvolto nell'evento (es. docente, classe), usato come sottotitolo
              const badge = escapeHtml(ev.badge || "");
              // eventuale badge da mostrare (es. "sostegno", "laboratorio", ecc.)

              const projected = eventIsOverriddenByBlockedClasses(ev, blockedSet);
              // controllo se questo evento è "proiettato" (riguarda classi che risultano bloccate nello slot)
              const overridden = (projected && !isBlocking(type) && !isAulaNonDisponibile(ev)) ? " ev-overridden" : "";
              // se è projected, non è un evento di tipo blocking, e non è "aula non disponibile", 
              const fillStyle = (sp.span > 1) ? ` style="flex:1 1 0;min-height:0;overflow:hidden;"` : "";
              const classi = ev.classi ? `<div class="ev-classi">${escapeHtml(Array.isArray(ev.classi) ? ev.classi.join(", ") : String(ev.classi))}</div>` : "";
              const rooms = (Array.isArray(ev.rooms) && ev.rooms.length) ? `<div class="ev-room">${escapeHtml(ev.rooms.join(", "))}</div>` : "";

              return `
                <div class="ev ev-${type}${overridden}"${fillStyle}>
                  <div class="ev-title">${title}</div>
${whoLines.length ? `
  <div class="ev-who">
    ${whoLines.map(w => escapeHtml(w)).join("<br>")}
  </div>
` : ``}
                  ${classi}
                  ${badge ? `<div><span class="ev-badge badge-${type}">${badge}</span></div>` : ``}
                  ${rooms}
                </div>
              `;
            }).join("") +
            `</div>`;

          html += `<td class="${tdClass}"${rs}>${htmlEv}</td>`;
        });

        html += `</tr>`;
      });

      html += `</tbody></table>`;
      $("#orario_content").html(renderLegend() + html);

      // DEBUG: analisi tabella multi-aule
      if (DEBUG.enabled && DEBUG.logRender) {
        const $tbl = $("#orario_content").find("table.orario-grid");
        dbg("DOM render stats (multi)", {
          cols: cols.length,
          tdWithRowspan: $tbl.find("td[rowspan]").length,
          evCount: $tbl.find(".ev").length
        });
      }

    }).fail(function (xhr) {
      dbgErr("multi-aule fail", xhr && xhr.status, xhr && xhr.responseText);
      showInlineMsg("danger", "Errore server lettura orario aule (multi).");
    });
  }

  // =============================================================================
  //  LOAD ORARIO (principale)
  // =============================================================================

  function loadOrario() {
    const scope = $("#v_scope").val(); // AULA - DOCENTE - CLASSE
    const period = $("#v_period").val(); // GIORNO - SETTIMANA
    const date = ($("#v_date").val() || "").trim(); // YYYY-MM-DD 
    const target = ($("#v_target").val() || "").trim(); // id dell’aula/classe/docente

    if (DEBUG.enabled && DEBUG.logFetch) dbg("loadOrario()", { scope, period, date, target });

    // Caso speciale: multi-aule solo per AULA+GIORNO
    if (scope === "AULA" && period === "GIORNO") {
      loadOrarioMultiAuleGiorno();
      return;
    }

    if (!date || !target) {
      showInlineMsg("warning", "Seleziona data e valore (AULA/CLASSE/DOCENTE).");
      return;
    }

    const targetLabel = ($("#v_target option:selected").text() || target).trim();

    let periodLabel = period.toLowerCase();
    let dateLabel = isoToIt(date);
    if (period === "SETTIMANA") {
      const mon = getMonday(date);
      const ven = addDays(mon, 4);
      dateLabel = `${isoToIt(mon)} → ${isoToIt(ven)}`;
    }

    $("#orario_title").text(`Vista ${scope} · ${targetLabel} · ${periodLabel} · ${dateLabel}`);
    showInlineMsg("info", "Caricamento orario...");

    if (DEBUG.enabled && DEBUG.logFetch) dbgGroup("FETCH orarioRead.php");

    $.getJSON("orarioRead.php", { scope, period, date, target }, async function (r) {
      if (DEBUG.enabled && DEBUG.logFetch) {
        dbg("response ok?", r && r.ok, "gridKeys", r && r.grid ? Object.keys(r.grid).length : 0);
        dbgGroupEnd();
      }

      if (!r || r.ok !== true) {
        showInlineMsg("danger", (r && r.error) ? r.error : "Errore lettura orario");
        return;
      }

      let grid = r.grid || {};

      // ✅ DOCENTE: aggiungo assenze dei colleghi sulle classi che il docente ha in quell'intervallo
      // if (scope === "DOCENTE") {
      //   const classSet = new Set();

      //   Object.keys(grid).forEach(k => {
      //     (grid[k] || []).forEach(ev => {
      //       const t = normalizeType(ev);
      //       if (isTeacherAbsenceType(t)) return; // non usare assenze per costruire classSet
      //       toArrMaybe(ev.classi).forEach(c => { if (c) classSet.add(String(c).trim()); });
      //     });
      //   });

      //   const classiCsv = Array.from(classSet).filter(Boolean).join(",");
      //   if (classiCsv) {
      //     const gridAss = await fetchAssenzeByClassi(period, date, classiCsv);
      //     grid = mergeGrids(grid, gridAss);
      //   }
      // }

      if (!Object.keys(grid).length) {
        showInlineMsg("info", "Nessun evento trovato per i filtri selezionati.");
        return;
      }

      let blockedMap = null;

      // SOLO AULA: aggiungo assenze + blockedMap dal DB
      if (scope === "AULA") {
        const blk = await fetchAulaBlocchi(period, date, target); // blocchi e assenze per questa aula+periodo+data
        grid = mergeGrids(grid, blk.gridAssenze || {});
        blockedMap = blk.blockedMap || {};
      }

      // DEBUG: log di sintesi grid
      if (DEBUG.enabled && DEBUG.logFetch) {
        const sampleKey = Object.keys(grid)[0];
        dbg("grid sampleKey", sampleKey, "sampleEvents", grid[sampleKey]);
      }

      renderGridFromGrid(period, date, grid, scope, blockedMap);

    }).fail(function (xhr) {
      dbgErr("Errore server lettura orario", xhr && xhr.responseText);
      showInlineMsg("danger", "Errore server lettura orario");
    });
  }

  // =============================================================================
  //  DOCUMENT READY
  // =============================================================================

  $(document).ready(function () {

    dbg("ORARIO JS ready", { DEBUG });

    $(".selectpicker").selectpicker();

    // default period settimana
    $("#v_period").selectpicker("val", "SETTIMANA");

    // default date oggi
    const today = todayIso();
    $("#v_date").val(today);

    fillWeekSelect();

    loadOptions();
    initSegmented();

    // pulsanti navigazione
    $("#btn_prev_week").off("click").on("click", function (e) { e.preventDefault(); prevNav(); });
    $("#btn_next_week").off("click").on("click", function (e) { e.preventDefault(); nextNav(); });
    $("#btn_prev_day").off("click").on("click", function (e) { e.preventDefault(); prevDayNav(); });
    $("#btn_next_day").off("click").on("click", function (e) { e.preventDefault(); nextDayNav(); });

    // navigazione aule (solo vista giorno aula)
    $("#btn_prev_aula").off("click").on("click", function (e) { e.preventDefault(); shiftAula(-1); });
    $("#btn_next_aula").off("click").on("click", function (e) { e.preventDefault(); shiftAula(+1); });

    // cambio settimana
    $("#v_week").on("changed.bs.select", function () {
      const mon = ($("#v_week").val() || "").trim();
      if (mon) setDateAndReload(mon);
    });

    // cambio scope
    $("#v_scope").on("changed.bs.select", function () {
      // salva target per lo scope PRECEDENTE (prima che venga cambiato l'elenco)
      const prevScope = ($(this).data("prevScope") || "").toString().trim().toUpperCase();
      const curTarget = ($("#v_target").val() || "").toString().trim();
      if (prevScope && curTarget) setMemTarget(prevScope, curTarget);

      updateToolbarLayout();
      loadOptions();
    });

    $("#v_scope").data("prevScope", ($("#v_scope").val() || "").toString().trim().toUpperCase());

    // cambio periodo
    $("#v_period").on("changed.bs.select", function () {
      const p = $("#v_period").val();
      if (p === "SETTIMANA") {
        $("#v_week").selectpicker("val", getMonday($("#v_date").val() || todayIso()));
      }
      loadOrario();
      syncSegmented($("#v_period"));
      updateToolbarLayout();
    });

    // cambio target
    $("#v_target").on("changed.bs.select", function () {
      const scope = ($("#v_scope").val() || "").toString().trim().toUpperCase();
      const target = ($("#v_target").val() || "").toString().trim();
      if (scope && target) setMemTarget(scope, target);
      loadOrario();
    });

    // cambio data
    $("#v_date").on("change", function () {
      const d = ($("#v_date").val() || todayIso()).trim();
      $("#v_week").selectpicker("val", getMonday(d));
      loadOrario();
    });

    // forza stato coerente
    $("#v_period").val("SETTIMANA");
    syncSegmented($("#v_period"));
    updateToolbarLayout();
  });

})();
