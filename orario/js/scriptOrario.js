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

  const AUTO_REFRESH_MS = 5 * 60 * 1000; // 5 minuti
  let autoRefreshTimer = null;

  function dbg(...args) { if (DEBUG.enabled) console.log(...args); }
  function dbgWarn(...args) { if (DEBUG.enabled) console.warn(...args); }
  function dbgErr(...args) { if (DEBUG.enabled) console.error(...args); }
  function dbgGroup(title) {
    if (!DEBUG.enabled) return;
    if (DEBUG.groupCollapsed) console.groupCollapsed(title);
    else console.group(title);
  }
  function dbgGroupEnd() { if (DEBUG.enabled) console.groupEnd(); }

  // =======================
  // GLOBAL ERROR TRACING
  // =======================
  window.addEventListener("error", function (e) {
    console.error("[ORARIO][window.error]", e.message, {
      filename: e.filename,
      lineno: e.lineno,
      colno: e.colno,
      error: e.error
    });
  });

  window.addEventListener("unhandledrejection", function (e) {
    console.error("[ORARIO][unhandledrejection]", e.reason);
  });
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
  const GIORNI_LABEL_BASE = ["LUN", "MAR", "MER", "GIO", "VEN"];
  const GIORNI_LABEL_GIUGNO = ["LUN", "MAR", "MER", "GIO", "VEN", "SAB"];

  function isJuneDate(iso) {
    return String(iso || "").slice(5, 7) === "06";
  }

  function giorniLabelForDate(iso) {
    return isJuneDate(iso) ? GIORNI_LABEL_GIUGNO : GIORNI_LABEL_BASE;
  }

  /** Intervallo di navigazione: include il periodo post lezioni per CDC/scrutini/eventi. */
  const SCHOOL_START = "2025-09-10";
  const SCHOOL_END = "2026-08-31";

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
  let pendingJumpTarget = null;

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

    // l’evento "assenza" non lo marco come "lezione assente"
    if (isTeacherAbsenceType(t)) return false;

    // barrabili: curr/udi/imp/...
    if (!isLessonLikeType(t)) return false;

    const whoLines = uniq(
      (Array.isArray(ev._whoList) && ev._whoList.length) ? ev._whoList : toArrWho(ev.who || ev.sub || "")
    ).map(normTxt).filter(Boolean);

    if (!whoLines.length) return false;

    const absentCount = whoLines.filter(w => teacherAbsMap.has(normPersonName(w))).length;
    return absentCount === whoLines.length;
  }

  function normPersonName(s) {
    return normTxt(s).toUpperCase();
  }

  function isTeacherAbsenceType(t) {
    const x = String(t || "").trim().toLowerCase();
    // copre: uscC/uscF in varie forme + perm/pb + viag
    return ["uscc", "uscf", "viag", "perm", "pb"].includes(x);
  }

  function isLessonLikeType(t) {
    const x = String(t || "").trim().toLowerCase();
    return ["curr", "udi", "imp", "pranzo", "studio"].includes(x);
  }

  function teacherAbsencePriority(t) {
    const x = String(t || "").trim().toLowerCase();
    const p = { viag: 100, uscf: 90, uscc: 80, perm: 60, pb: 50 };
    return p[x] || 0;
  }
  // costruisce: { "NOME COGNOME": {type,title,badge,reasonText,prio} }
  // =====================================================================================
  //  buildTeacherAbsenceMapForSlot (CONSOLE LOG AGGIUNTI, LOGICA INVARIATA)
  // =====================================================================================
  function buildTeacherAbsenceMapForSlot(evs, ora) {
    const map = new Map();

    if (DEBUG.enabled) {
      console.groupCollapsed(`[ORARIO][ABS-MAP][start] ora=${ora} evs=${(evs || []).length}`);
      try {
        const snap = (evs || []).map((e, i) => ({
          i,
          typeRaw: e && e.type,
          typeNorm: normalizeType(e),
          who: e && (e.who || e.sub || ""),
          title: e && (e.title || e.label || ""),
          badge: e && (e.badge || "")
        }));
        console.table(snap);
      } catch (e) {
        console.log("[ORARIO][ABS-MAP] snapshot error", e);
      }
      console.groupEnd();
    }

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

    if (DEBUG.enabled) {
      console.groupCollapsed(`[ORARIO][ABS-MAP][end] ora=${ora} mapSize=${map.size}`);
      try {
        const out = [];
        map.forEach((v, k) => out.push({ teacherKey: k, type: v.type, prio: v.prio, reasonText: v.reasonText }));
        console.table(out);
      } catch (e) {
        console.log("[ORARIO][ABS-MAP] end table error", e);
      }
      console.groupEnd();
    }

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
      const m = cls.match(/\bev-(curr|udi|viag|imp|uscC|uscF|pb|perm|pranzo|studio|sost)\b/i);
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
    const p = { uscF: 100, uscC: 90, viag: 80, sost: 70, imp: 50, pranzo: 35, studio: 35, udi: 20, curr: 10 };
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

    const scopeUp = String(scope || "").trim().toUpperCase();

    evs.forEach(ev => {
      const t = normalizeType(ev);

      // ✅ DOCENTE: ignora who SOLO per curr, NON per udi/imp/...
      const ignoreWhoForThis = (scopeUp === "DOCENTE" && t === "curr");

      const o = {
        t,
        p: priority(t),
        title: normTxt(ev.title || ev.label || ""),
        who: ignoreWhoForThis ? "" : normTxt(ev.who || ev.sub || ""),
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

    // ✅ Caso chiave: se slot contiene blocking + nonBlocking, firma include TUTTO
    if (hasBlocking && hasNonBlocking) {
      canon = canonAll;
    } else if (hasBlocking) {
      canon = canonAll.filter(x => isBlocking(x.t));
    } else {
      canon = canonAll.filter(x => !isBlocking(x.t));
    }

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
      dbg("SIGNATURE slot =", sig, { scope: scopeUp, canon });
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

      const rooms = toArrMaybe(ev.rooms);
      const classi = toArrMaybe(ev.classi);

      const isAbs = isTeacherAbsenceType(type);

      // ✅ DOCENTE: ignora who SOLO per curr (così non fondi le UDI di docenti diversi)
      const ignoreWhoForThis = (isDocenteView && !isAbs && type === "curr");
      // CONSIGLI DI CLASSE:
      // in vista docente mostra comunque gli impegni anche senza who
      if (
        isDocenteView &&
        type === "imp" &&
        (!ev.who || !String(ev.who).trim())
      ) {
        ev.who = "__DOCENTE_IMPLICITO__";
      }
      const key = ignoreWhoForThis
        ? `${type}|||${title}|||${badge}`
        : `${type}|||${title}|||${whoArr.join(",")}|||${badge}`;

      if (!map.has(key)) {
        const copy = Object.assign({}, ev);
        copy.type = type;
        copy.title = title || copy.title;
        copy.badge = badge || copy.badge;

        copy._whoList = uniq(whoArr);
        copy.who = copy._whoList.join("\n");

        copy.rooms = uniq(rooms);
        copy.classi = uniq(classi);

        map.set(key, copy);
      } else {
        const cur = map.get(key);
        cur.rooms = uniq((cur.rooms || []).concat(rooms));
        cur.classi = uniq((cur.classi || []).concat(classi));

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
        <span class="lg"><span class="dot dot-sost"></span> Sostituzione</span>
        <span class="lg"><span class="dot dot-pranzo"></span> Aula pausa pranzo</span>
        <span class="lg"><span class="dot dot-studio"></span> Aula studio</span>
      </div>
    `;
  }

  function roomsHtmlFromEv(ev) {
    const rooms = ev && ev.rooms;
    if (!Array.isArray(rooms) || rooms.length === 0) return "";
    return `<div class="ev-room">${rooms.map(r => jumpChipHtml("AULA", r, r, "room")).join("")}</div>`;
  }

  function classiHtmlFromEv(ev) {
    const c = ev && ev.classi;
    if (!c) return "";
    const arr = Array.isArray(c) ? c : String(c).split(",").map(x => x.trim()).filter(Boolean);
    if (!arr.length) return "";
    return `<div class="ev-classi">${arr.map(cn => jumpChipHtml("CLASSE", cn, cn, "class")).join("")}</div>`;
  }

  function jumpChipHtml(scope, target, label, kind) {
    const targetTxt = normTxt(target);
    const labelTxt = normTxt(label);
    if (!targetTxt || !labelTxt) return "";

    return `<button type="button" class="orario-jump orario-chip orario-chip-${kind}"
      data-scope="${escapeHtml(scope)}"
      data-target="${escapeHtml(targetTxt)}"
      title="Vai all'orario ${escapeHtml(scope.toLowerCase())} ${escapeHtml(labelTxt)}">${escapeHtml(labelTxt)}</button>`;
  }

  function teacherChipHtml(label, username) {
    const labelTxt = normTxt(label);
    const usernameTxt = normTxt(username);
    if (!labelTxt) return "";
    if (!usernameTxt) return `<span class="orario-chip orario-chip-teacher is-static">${escapeHtml(labelTxt)}</span>`;
    return jumpChipHtml("DOCENTE", usernameTxt, labelTxt, "teacher");
  }

  function sostituzioneHtml(ev) {
    const s = ev && ev.sostituzione;
    if (!s) return "";

    const sostituto = normTxt(s.sostituto || "");
    const sostituito = normTxt(s.sostituito || ev.who_originale || "");
    const ora = [normTxt(s.oraInizio || ""), normTxt(s.oraFine || "")].filter(Boolean).join(" - ");
    const originalBadge = normTxt(ev.badge_originale || "");

    return `
      <div class="ev-sost-box">
        <div class="ev-sost-label">Sostituzione${ora ? ` · ${escapeHtml(ora)}` : ""}</div>
        ${sostituto ? `<div class="ev-sost-main">In classe: ${teacherChipHtml(sostituto, s.sostituto_username || "")}</div>` : ""}
        ${sostituito ? `<div class="ev-sost-sub">Sostituisce ${escapeHtml(sostituito)}</div>` : ""}
        ${originalBadge ? `<div class="ev-sost-sub">${escapeHtml(originalBadge)}</div>` : ""}
      </div>
    `;
  }

  function jumpToOrario(scope, target) {
    const scopeUp = String(scope || "").trim().toUpperCase();
    const targetVal = String(target || "").trim();
    if (!scopeUp || !targetVal) return;

    pendingJumpTarget = targetVal;
    $("#v_scope").selectpicker("val", scopeUp);
    syncSegmented($("#v_scope"));
    updateToolbarLayout();
    loadOptions();
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

  function isJuneDate(iso) {
    return String(iso || "").slice(5, 7) === "06";
  }

  function isSkipDayForMobile(isoDate) {
    const d = new Date(isoDate + "T00:00:00");
    const day = d.getDay();

    // Domenica sempre saltata
    if (day === 0) return true;

    // Sabato saltato solo fuori giugno
    if (day === 6 && !isJuneDate(isoDate)) return true;

    return false;
  }

  function addSchoolDay(isoDate, delta) {
    let cur = isoDate;
    const step = delta >= 0 ? 1 : -1;
    let left = Math.abs(delta);

    while (left > 0) {
      cur = addDays(cur, step);
      if (!isSkipDayForMobile(cur)) left--;
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
    const isEventi = (scope === "EVENTI" || scope === "ASSENZE" || scope === "SOSTITUZIONI");
    const isWeek = (period === "SETTIMANA");
    const isDay = (period === "GIORNO");
    const isAula = (scope === "AULA");

    const $tb = $(".orario-toolbar"); // ✅ DEFINITO SUBITO

    if (isEventi) {
      // EVENTI: niente target e niente settimana, solo giorno + data
      $("#wrap_target").hide();
      $("#wrap_week").hide();
      $("#wrap_date").show();

      // EVENTI: forzo giorno e nascondo selezione periodo
      $("#seg_period").hide();
      $("#v_period").val("GIORNO");
      try { $("#v_period").selectpicker("refresh"); } catch (e) { }
      syncSegmented($("#v_period")); // ✅ per coerenza UI

      // nessuna navigazione aula
      $("#btn_prev_aula, #btn_next_aula").hide();

      // classi toolbar coerenti
      $tb.removeClass("scope-AULA scope-CLASSE scope-DOCENTE scope-EVENTI scope-ASSENZE period-GIORNO period-SETTIMANA");
      $tb.addClass("scope-" + scope + " period-GIORNO");
      return;
    }
    $("#wrap_target").show();
    $("#wrap_week").toggle(isWeek);
    $("#wrap_date").toggle(isDay);

    // sempre visibile fuori EVENTI
    $("#seg_period").show();

    // mostra prev/next aula solo in vista giorno aula
    $("#btn_prev_aula, #btn_next_aula").toggle(isDay && isAula);

    // pulizia classi precedenti
    $tb.removeClass("scope-AULA scope-CLASSE scope-DOCENTE scope-EVENTI period-GIORNO period-SETTIMANA");

    // aggiungi classi correnti
    $tb.addClass("scope-" + scope);
    $tb.addClass("period-" + period);

    const $t = $("#v_target");
    let w = "260px";
    if (scope === "CLASSE") w = "170px";
    else if (scope === "DOCENTE") w = "220px";
    else if (scope === "AULA") w = (period === "GIORNO") ? "320px" : "320px";

    $t.attr("data-width", w);

    try { $t.selectpicker("refresh"); } catch (e) { }

    $("#wrap_target").css({ width: "auto", flex: "0 0 auto" });

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
    const endIso = isJuneDate(monIso) ? addDays(monIso, 5) : addDays(monIso, 4);
    return `${isoToIt(monIso)} → ${isoToIt(endIso)}`;
  }

  function fillWeekSelect() {
    const $w = $("#v_week");
    if (!$w.data("selectpicker")) $w.selectpicker();

    $w.empty();

    const startMon = getMonday(SCHOOL_START);
    const endMon = getMonday(SCHOOL_END);

    let cur = startMon;
    while (cur <= endMon) {
      $w.append(`<option value="${cur}">${buildWeekLabel(cur)}</option>`);
      cur = addDays(cur, 7);
    }

    $w.selectpicker("refresh");

    // seleziona la settimana corrente (se presente)
    const todayMon = getMonday(todayIso());
    $w.selectpicker("val", todayMon);

    // ✅ NON toccare v_date qui: la data la decide lo scope/period (oggi per EVENTI)
    // $("#v_date").val(todayMon);  // <-- RIMUOVI
  }

  function clampIsoDate(iso) {
    // clamp su limite scuola, ma se period=SETTIMANA clampiamo sul lunedì
    const d = String(iso || "").trim();
    if (!/^\d{4}-\d{2}-\d{2}$/.test(d)) return todayIso();

    const min = SCHOOL_START;
    const max = SCHOOL_END;

    if (d < min) return min;
    if (d > max) return max;
    return d;
  }

  function clampMonWeek(monIso) {
    const mon = getMonday(monIso);
    const minMon = getMonday(SCHOOL_START);
    const maxMon = getMonday(SCHOOL_END);
    if (mon < minMon) return minMon;
    if (mon > maxMon) return maxMon;
    return mon;
  }

  function setDateAndReload(newIso) {
    const period = ($("#v_period").val() || "SETTIMANA").trim();

    if (period === "SETTIMANA") {
      const mon = clampMonWeek(newIso);
      $("#v_date").val(mon);

      // la week select deve contenerlo: se non c'è, non uscire dal range (quindi non serve aggiungerlo)
      $("#v_week").selectpicker("val", mon);
      loadOrario();
      return;
    }

    // GIORNO
    const d = clampIsoDate(newIso);
    $("#v_date").val(d);
    $("#v_week").selectpicker("val", getMonday(d));
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
    console.log("[ORARIO] prevDayNav", { from: d, to: nd });
    setDateAndReload(nd);
  }

  function nextDayNav() {
    const d = ($("#v_date").val() || todayIso()).trim();
    const nd = addSchoolDay(d, +1);
    console.log("[ORARIO] nextDayNav", { from: d, to: nd });
    setDateAndReload(nd);
  }

  function prevNav() {
    const period = $("#v_period").val();
    if (period === "GIORNO") { prevDayNav(); return; }

    const d = ($("#v_date").val() || todayIso()).trim();
    const nd = addDays(d, -7);
    setDateAndReload(nd); // clamp dentro
  }

  function nextNav() {
    const period = $("#v_period").val();
    if (period === "GIORNO") { nextDayNav(); return; }

    const d = ($("#v_date").val() || todayIso()).trim();
    const nd = addDays(d, +7);
    setDateAndReload(nd); // clamp dentro
  }

  // CSS.escape safe fallback (per browser/embedded dove CSS.escape è undefined)
  const cssEscape = (function () {
    if (window.CSS && typeof window.CSS.escape === "function") return window.CSS.escape;

    // Polyfill minimo ma solido per uso in selettori attribute [value="..."]
    return function (value) {
      return String(value)
        .replace(/\\/g, "\\\\")
        .replace(/"/g, '\\"')
        .replace(/\n/g, "\\n")
        .replace(/\r/g, "\\r")
        .replace(/\f/g, "\\f");
    };
  })();
  // =============================================================================
  //  LOAD OPTIONS (target list)
  // =============================================================================

  function loadOptions() {
    const scope = $("#v_scope").val();

    if (["EVENTI", "ASSENZE", "SOSTITUZIONI"].includes(String(scope).toUpperCase())) {
      $("#v_target").empty().append(`<option value="">(non usato)</option>`);
      try { $("#v_target").selectpicker("refresh"); } catch (e) { }

      $("#v_period").val("GIORNO");
      try { $("#v_period").selectpicker("refresh"); } catch (e) { }
      syncSegmented($("#v_period"));
      updateToolbarLayout();

      loadOrario();
      return;
    }

    const $t = $("#v_target");

    $t.empty().append(`<option value="">Seleziona...</option>`);
    try { $t.selectpicker("refresh"); } catch (e) { }

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

      try { $t.selectpicker("refresh"); } catch (e) { }

      // ----- ripristino target per-scope -----
      const scopeUp = (scope || "").toString().trim().toUpperCase();
      const remembered = getMemTarget(scopeUp);
      const defaultScope = String(window.ORARIO_DEFAULT_SCOPE || "").trim().toUpperCase();
      const defaultTarget = String(window.ORARIO_DEFAULT_TARGET || "").trim();
      const pendingTarget = pendingJumpTarget;

      let restored = false;

      if (pendingTarget) {
        const escPending = cssEscape(pendingTarget);
        const pendingExists = $t.find(`option[value="${escPending}"]`).length > 0;
        if (pendingExists) {
          try { $t.selectpicker("val", pendingTarget); } catch (e) { $t.val(pendingTarget); }
          setMemTarget(scopeUp, pendingTarget);
          pendingJumpTarget = null;
          restored = true;
        }
      }

      if (!restored && defaultTarget && defaultScope === scopeUp) {
        const escDefault = cssEscape(defaultTarget);
        const defaultExists = $t.find(`option[value="${escDefault}"]`).length > 0;
        if (defaultExists) {
          try { $t.selectpicker("val", defaultTarget); } catch (e) { $t.val(defaultTarget); }
          setMemTarget(scopeUp, defaultTarget);
          restored = true;
        }
      }

      if (!restored && remembered) {
        const esc = cssEscape(remembered);
        const exists = $t.find(`option[value="${esc}"]`).length > 0;
        if (exists) {
          try { $t.selectpicker("val", remembered); } catch (e) { $t.val(remembered); }
          restored = true;
        }
      }

      if (!restored) {
        const cur = ($t.val() || "").toString().trim();
        if (cur) {
          const escCur = cssEscape(cur);
          const curExists = $t.find(`option[value="${escCur}"]`).length > 0;
          if (!curExists && items.length > 0) {
            try { $t.selectpicker("val", String(items[0].id)); } catch (e) { $t.val(String(items[0].id)); }
          }
        } else if (items.length > 0) {
          try { $t.selectpicker("val", String(items[0].id)); } catch (e) { $t.val(String(items[0].id)); }
        }
      }

      $("#v_scope").data("prevScope", scopeUp);

      if (($t.val() || "").toString().trim()) loadOrario();
      else showInlineMsg("info", "Seleziona un valore per visualizzare l’orario.");
    })
      .fail(function (xhr) {
        if (DEBUG.enabled) dbgErr("Errore server orarioGetOptions.php", xhr && xhr.status, xhr && xhr.responseText);
        showInlineMsg("danger", "Errore server caricamento opzioni");
        if (DEBUG.enabled && DEBUG.logFetch) dbgGroupEnd();
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

  function isUdienzaLike(txt) {
    const s = (txt || "").toString().trim().toUpperCase();
    // copre: "UD", "UD.", "UDIENZA", "UDIENZE", "UD - ..." ecc.
    return s === "UD" || s.startsWith("UD ") || s.startsWith("UD-") || s.startsWith("UD.") ||
      s.includes("UDIENZA") || s.includes("UDIENZE");
  }

  function keyForMerge(it) {
    const type = (it.type || "").toString().trim().toLowerCase();
    const aula = (it.aula || "").toString().trim().toUpperCase();
    const det = (it.detailKey || "").toString().trim().toUpperCase();

    // docente: normalizza spazi e newline
    const doc = (it.docKey || "").toString().replace(/\s+/g, " ").trim().toUpperCase();

    // classi: normalizza set ordinato
    const cls = (it.classiArr || []).slice().map(x => x.trim().toUpperCase()).filter(Boolean).sort().join(",");

    return `${type}|||${aula}|||${det}|||${doc}|||${cls}`;
  }

  // contiguità: oraIn di B deve essere uguale a oraOut di A
  function isContiguous(a, b) {
    const aOut = (a.oraOut || "").toString().trim();
    const bIn = (b.oraIn || "").toString().trim();
    return aOut && bIn && aOut === bIn;
  }

  function mergeConsecutiveSame(itemsSorted) {
    const out = [];
    for (const it of (itemsSorted || [])) {
      const prev = out[out.length - 1];

      if (!prev) {
        out.push(Object.assign({}, it));
        continue;
      }

      const k1 = keyForMerge(prev);
      const k2 = keyForMerge(it);

      if (k1 === k2 && isContiguous(prev, it)) {
        // ✅ estendi la fascia oraria
        prev.oraOut = it.oraOut || prev.oraOut;

        // (optional) se vuoi unire anche classi (nel caso arrivino in modo non identico)
        const mergedClassi = new Set([...(prev.classiArr || []), ...(it.classiArr || [])].map(x => x.trim()).filter(Boolean));
        prev.classiArr = Array.from(mergedClassi).sort();
        prev.classKey = (prev.classiArr[0] || "");

        // (optional) docente multi-linea: unione
        const mergedDoc = new Set(
          String(prev.docKey || "").split(/\r?\n/).concat(String(it.docKey || "").split(/\r?\n/))
            .map(x => x.trim()).filter(Boolean)
        );
        prev.docKey = Array.from(mergedDoc).join("\n");

        continue;
      }

      out.push(Object.assign({}, it));
    }
    return out;
  }

  function renderEventiList(items, dateIso) {

    function isViewingToday() {
      return String(dateIso || "").trim() === todayIso();
    }
    const $c = $("#orario_content");

    // -----------------------------
    // Normalizzazione dati
    // -----------------------------
    const normItems = (items || [])
      // ✅ filtro di sicurezza: "AULA NON DISPONIBILE" non deve comparire negli imp
      .filter(it => {
        const t = (it.type || "").toString().trim().toLowerCase();
        const det = (it.title || "").toString().trim();

        if (t === "imp") {
          const detUp = det.toUpperCase();

          // ✅ no aula non disponibile
          if (detUp === "AULA NON DISPONIBILE") return false;

          // ✅ no udienze mascherate da imp
          if (isUdienzaLike(det)) return false;
        }

        return true;
      })
      .map(it => {
        const classiArr = Array.isArray(it.classi)
          ? it.classi
          : (it.classi ? String(it.classi).split(",").map(x => x.trim()).filter(Boolean) : []);

        const roomsArr = Array.isArray(it.rooms)
          ? it.rooms
          : (it.rooms ? String(it.rooms).split(",").map(x => x.trim()).filter(Boolean) : []);

        const aula = (it.aula || (roomsArr[0] || "") || "").toString().trim().toUpperCase();

        const oraIn = (it.ora || it.oraInizio || it.oraIn || "").toString().slice(0, 5);

        // ora fine: se il backend la fornisce bene, altrimenti:
        // - per eventi da oralezione (slot singolo) stimiamo fine = slot successivo in ORARI
        let oraOut = (it.oraFine || it.ora_fine || it.fine || it.oraOut || "").toString().slice(0, 5);
        if (!oraOut && oraIn) {
          const idx = ORARI.indexOf(oraIn);
          if (idx >= 0 && idx + 1 < ORARI.length) oraOut = ORARI[idx + 1];
        }

        // chiave "classe" per ordinare: prima classe alfabetica
        const classKey = (classiArr.slice().sort()[0] || "");

        const doc = (it.who || it.docente || "").toString().trim();
        const detail = (it.title || it.detail || "").toString().trim();

        return Object.assign({}, it, {
          classiArr,
          roomsArr,
          aula,
          oraIn,
          oraOut,
          classKey,
          docKey: doc,
          detailKey: detail
        });
      });

    // -----------------------------
    // Permessi/pb overlay (FUORI dalla map)
    // -----------------------------
    function hhmmToMin(h) {
      const s = (h || "").toString().slice(0, 5);
      if (!/^\d{2}:\d{2}$/.test(s)) return null;
      const [H, M] = s.split(":").map(n => parseInt(n, 10));
      return H * 60 + M;
    }

    function rangesOverlap(a1, a2, b1, b2) {
      const A1 = hhmmToMin(a1), A2 = hhmmToMin(a2), B1 = hhmmToMin(b1), B2 = hhmmToMin(b2);
      if (A1 == null || A2 == null || B1 == null || B2 == null) return false;
      return (A1 < B2) && (B1 < A2);
    }

    function whoKeysOfItem(it) {
      return uniq(toArrWho(it.docKey || it.who || ""))
        .map(normTxt)
        .filter(Boolean)
        .map(normPersonName);
    }

    // prendo perm/pb con range
    const permItems = normItems.filter(it => {
      const t = (it.type || "").toString().trim().toLowerCase();
      return (t === "perm" || t === "pb");
    });

    // Map: DOCENTE_KEY -> array di {oraIn, oraOut, reasonText}
    const permByTeacher = new Map();
    permItems.forEach(p => {
      const whoKeys = whoKeysOfItem(p);
      if (!whoKeys.length) return;

      const oraIn = (p.oraIn || p.ora || p.oraInizio || "").toString().slice(0, 5);
      let oraOut = (p.oraOut || p.oraFine || p.ora_fine || p.fine || "").toString().slice(0, 5);
      if (!oraOut && oraIn) {
        const idx = ORARI.indexOf(oraIn);
        if (idx >= 0 && idx + 1 < ORARI.length) oraOut = ORARI[idx + 1];
      }

      const reasonText = (p.badge || p.title || "Permesso").toString().trim();

      whoKeys.forEach(k => {
        if (!permByTeacher.has(k)) permByTeacher.set(k, []);
        permByTeacher.get(k).push({ oraIn, oraOut, reasonText });
      });
    });

    // true se l'item (imp/usc/viag/...) è coperto da un permesso/pb del docente
    function itemCoveredByPermesso(it) {
      const t = (it.type || "").toString().trim().toLowerCase();
      // NB: in questo punto i type nel tuo JSON sono "uscC"/"uscF": dopo lower -> "uscc"/"uscf"
      if (!["imp", "uscc", "uscf", "viag"].includes(t)) return false;

      const whoKeys = whoKeysOfItem(it);
      if (!whoKeys.length) return false;

      const in1 = (it.oraIn || "").toString().slice(0, 5);
      const out1 = (it.oraOut || "").toString().slice(0, 5);
      if (!in1 || !out1) return false;

      return whoKeys.some(k => {
        const lst = permByTeacher.get(k) || [];
        return lst.some(p => rangesOverlap(in1, out1, p.oraIn, p.oraOut));
      });
    }

    // -----------------------------
    // UI
    // -----------------------------
    function typeLabelFromType(t) {
      const m = {
        imp: "Impegno in istituto",
        uscC: "Uscita nel comune",
        uscF: "Uscita fuori comune",
        viag: "Viaggio di istruzione",
        udi: "Udienza",
        curr: "Curricolare",
        pranzo: "Aula pausa pranzo",
        studio: "Aula studio",
        perm: "Permesso",
        pb: "Permesso breve",
        mal: "Malattia",
        lutto: "Lutto",
        ass: "Assenza"
      };
      return m[t] || (t || "");
    }

    function typeLabel(it) {
      const badge = (it && it.badge ? String(it.badge).trim() : "");
      if (badge) return badge;
      return typeLabelFromType((it && it.type) ? it.type : "");
    }

    const topbar = `
    <div class="eventi-topbar" style="display:flex;gap:10px;align-items:center;margin:6px 0 10px 0;flex-wrap:wrap;">
      <input id="ev_q" class="form-control input-sm" style="max-width:420px;" placeholder="Cerca (dettaglio, classe, docente, tipo, aula...)">
      <div style="opacity:.75;font-size:14px;">${escapeHtml(isoToIt(dateIso))} · ${normItems.length} eventi</div>
    </div>
    `;

    const table = `
  <div class="table-responsive">
    <table class="table table-bordered table-hover" id="tbl_eventi"
           style="background:#fff;table-layout:auto;width:100%;">
      <thead>
        <tr>
          <th class="th-sort" data-key="aula"   style="width:60px;cursor:pointer;white-space:nowrap;text-align:center;">Aula <span class="sort-ind"></span></th>
          <th class="th-sort" data-key="oraIn"  style="width:75px;cursor:pointer;white-space:nowrap;text-align:center;">Inizio <span class="sort-ind"></span></th>
          <th class="th-sort" data-key="oraOut" style="width:75px;cursor:pointer;white-space:nowrap;text-align:center;">Fine <span class="sort-ind"></span></th>
          <th class="th-sort" data-key="classe" style="width:70px;cursor:pointer;text-align:center;">Classe <span class="sort-ind"></span></th>
          <th class="th-sort" data-key="doc"    style="width:130px;cursor:pointer;">Docente <span class="sort-ind"></span></th>
          <th class="th-sort" data-key="tipo"   style="width:135px;cursor:pointer;white-space:nowrap;">Tipo <span class="sort-ind"></span></th>
          <th class="th-sort" data-key="dett"   style="cursor:pointer;">Dettaglio <span class="sort-ind"></span></th>
        </tr>
      </thead>
      <tbody id="eventi_tbody"></tbody>
    </table>
  </div>
`;

    $c.html(renderLegend() + topbar + table);

    // -----------------------------
    // Ricerca + Ordinamento
    // -----------------------------
    let sortState = { key: "oraIn", dir: "asc" };

    function norm(s) { return String(s ?? "").toLowerCase(); }

    function typeOrderValue(t) {
      const tt = (t || "").toString().trim().toLowerCase();
      if (tt === "imp") return 0; // imp primi
      return 1;
    }

    function getSortVal(it, key) {
      if (key === "aula") return (it.aula || "");
      if (key === "oraIn") return (it.oraIn || "");
      if (key === "oraOut") return (it.oraOut || "");
      if (key === "classe") return (it.classKey || "");
      if (key === "doc") return (it.docKey || "");
      if (key === "tipo") return (typeLabel(it) || "");
      if (key === "dett") return (it.detailKey || "");
      return "";
    }

    function cmpAula(a, b) {
      const aa = (a.aula || "").toString();
      const bb = (b.aula || "").toString();
      const aEmpty = aa.trim() === "";
      const bEmpty = bb.trim() === "";
      if (aEmpty && !bEmpty) return 1;
      if (!aEmpty && bEmpty) return -1;
      return aa.localeCompare(bb);
    }

    function stableSort(arr, key, dir) {
      const mul = (dir === "desc") ? -1 : 1;

      function tieDefault(a, b) {
        let c = (a.oraIn || "").localeCompare(b.oraIn || "");
        if (c !== 0) return c;

        c = (typeOrderValue(a.type) - typeOrderValue(b.type));
        if (c !== 0) return c;

        c = (typeLabel(a) || "").localeCompare(typeLabel(b) || "");
        if (c !== 0) return c;

        c = (a.detailKey || "").localeCompare(b.detailKey || "");
        if (c !== 0) return c;

        c = (a.classKey || "").localeCompare(b.classKey || "");
        if (c !== 0) return c;

        return (a.docKey || "").localeCompare(b.docKey || "");
      }

      return arr.slice().sort((a, b) => {
        let c = 0;

        if (key === "aula") {
          c = cmpAula(a, b);
          if (c !== 0) return c * mul;
          c = tieDefault(a, b);
          return c * mul;
        }

        const va = getSortVal(a, key).toString();
        const vb = getSortVal(b, key).toString();
        c = va.localeCompare(vb);
        if (c !== 0) return c * mul;

        return tieDefault(a, b) * mul;
      });
    }

    function matchesQ(it, q) {
      if (!q) return true;
      const hay = [
        it.aula,
        it.oraIn,
        it.oraOut,
        (it.classiArr || []).join(","),
        it.docKey,
        typeLabel(it),
        it.detailKey
      ].join(" ").toLowerCase();
      return hay.includes(q);
    }

    function cellEllipsize(html, center = false) {
      return `<div style="
    white-space:normal;
    word-break:break-word;
    overflow-wrap:anywhere;
    line-height:1.25;
    ${center ? 'text-align:center;' : ''}
  ">${html}</div>`;
    }

    function rowHtml(it) {
      const aula = escapeHtml(it.aula || "");
      const oraIn = escapeHtml(it.oraIn || "");
      const oraOut = escapeHtml(it.oraOut || "");
      const classe = escapeHtml((it.classiArr || []).join(", "));
      const docente = escapeHtml(it.docKey || "").replace(/\n/g, "<br>");
      const type = (it.type || "imp").toString().trim();
      const tipo = escapeHtml(typeLabel(it));
      const dettaglio = escapeHtml(it.detailKey || "");

      const covered = itemCoveredByPermesso(it);
      const coveredCls = covered ? " ev-covered-by-perm" : "";

      return `
    <tr class="ev-row ev-row-${type}${coveredCls}" data-orain="${oraIn}" data-oraout="${oraOut}">
      <td style="text-align:center;vertical-align:middle;">
        ${cellEllipsize(aula ? `<b>${aula}</b>` : ``, true)}
      </td>

      <td style="font-weight:800;white-space:nowrap;text-align:center;vertical-align:middle;">
        ${oraIn}
      </td>

      <td style="white-space:nowrap;text-align:center;vertical-align:middle;">
        ${oraOut}
      </td>

      <td style="text-align:center;vertical-align:middle;">
        ${cellEllipsize(classe, true)}
      </td>

      <td style="vertical-align:middle;">
        <div style="
          white-space:normal;
          word-break:break-word;
          overflow-wrap:anywhere;
          line-height:1.25;
        ">${docente}</div>
      </td>

      <td style="vertical-align:middle;">
        <div style="
          white-space:normal;
          word-break:break-word;
          overflow-wrap:anywhere;
          line-height:1.25;
        ">
          <span class="ev-badge badge-${type}">${tipo}</span>
        </div>
      </td>

      <td style="vertical-align:middle;">
        <div style="
          white-space:normal;
          word-break:break-word;
          overflow-wrap:anywhere;
          line-height:1.3;
          min-width:220px;
        ">
          ${dettaglio}
        </div>
      </td>
    </tr>
  `;
    }

    function updateSortIndicators() {
      $("#tbl_eventi thead th.th-sort").each(function () {
        const $th = $(this);
        const k = $th.data("key");
        const $ind = $th.find(".sort-ind");
        if (k === sortState.key) $ind.html(sortState.dir === "asc" ? "▲" : "▼");
        else $ind.html("");
      });
    }

    function getCurrentHHMM() {
      const d = new Date();
      const hh = String(d.getHours()).padStart(2, "0");
      const mm = String(d.getMinutes()).padStart(2, "0");
      return `${hh}:${mm}`;
    }

    function hhmmToMinutes(hhmm) {
      const s = String(hhmm || "").trim().slice(0, 5);
      if (!/^\d{2}:\d{2}$/.test(s)) return null;
      const [h, m] = s.split(":").map(Number);
      return (h * 60) + m;
    }

    function findCurrentRow($rows) {
      if (!isViewingToday()) return $();

      const nowMin = hhmmToMinutes(getCurrentHHMM());
      if (nowMin === null || !$rows.length) return $();

      let $firstInProgress = $();
      let $firstFuture = $();

      $rows.each(function () {
        const $tr = $(this);
        const oraIn = hhmmToMinutes($tr.data("orain"));
        let oraOut = hhmmToMinutes($tr.data("oraout"));

        if (oraIn === null) return;

        if (oraOut === null) oraOut = oraIn + 1;

        if (!$firstInProgress.length && nowMin >= oraIn && nowMin < oraOut) {
          $firstInProgress = $tr;
          return;
        }

        if (!$firstFuture.length && oraIn > nowMin) {
          $firstFuture = $tr;
        }
      });

      if ($firstInProgress.length) return $firstInProgress;
      if ($firstFuture.length) return $firstFuture;

      return $rows.last();
    }

    function getRowTimeState(it) {
      if (!isViewingToday()) return "future";

      const nowMin = hhmmToMinutes(getCurrentHHMM());
      if (nowMin === null) return "future";

      const oraIn = hhmmToMinutes(it.oraIn);
      let oraOut = hhmmToMinutes(it.oraOut);

      if (oraIn === null) return "future";
      if (oraOut === null) oraOut = oraIn + 1;

      if (nowMin >= oraIn && nowMin < oraOut) return "current";
      if (nowMin >= oraOut) return "past";
      return "future";
    }

    function timeStateOrder(state) {
      if (state === "current") return 0;
      if (state === "future") return 1;
      if (state === "past") return 2;
      return 3;
    }

    function markRowsInProgress() {
      const $rows = $("#eventi_tbody tr.ev-row");
      $rows.removeClass("row-in-progress row-ended");

      if (!isViewingToday()) return;

      const nowMin = hhmmToMinutes(getCurrentHHMM());
      if (nowMin === null) return;

      $rows.each(function () {
        const $tr = $(this);
        const oraIn = hhmmToMinutes($tr.data("orain"));
        let oraOut = hhmmToMinutes($tr.data("oraout"));

        if (oraIn === null) return;
        if (oraOut === null) oraOut = oraIn + 1;

        if (nowMin >= oraIn && nowMin < oraOut) {
          $tr.addClass("row-in-progress");
        } else if (nowMin >= oraOut) {
          $tr.addClass("row-ended");
        }
      });
    }

    function scrollToCurrentEventRow() {
      if (!isViewingToday()) return;

      const $rows = $("#eventi_tbody tr.ev-row");
      if (!$rows.length) return;

      markRowsInProgress();

      const $target = findCurrentRow($rows);
      if (!$target.length) return;

      const rect = $target[0].getBoundingClientRect();
      const absoluteTop = window.pageYOffset + rect.top;
      const offset = 140;

      window.scrollTo({
        top: Math.max(0, absoluteTop - offset),
        behavior: "smooth"
      });

      $target.addClass("row-now-flash");
      setTimeout(() => {
        $target.removeClass("row-now-flash");
      }, 2200);
    }

    let reorderPastTimer = null;

    function paint() {
      const q = norm($("#ev_q").val());

      const filtered = normItems.filter(it => matchesQ(it, q));
      let sorted = stableSort(filtered, sortState.key, sortState.dir);

      // SOLO OGGI puoi fare fusioni/accorpamenti speciali
      if (isViewingToday()) {
        const k = (sortState.key || "").toString();
        if (k === "aula" || k === "oraIn" || k === "oraOut") {
          sorted = mergeConsecutiveSame(sorted);
        }
      }

      const $tb = $("#eventi_tbody");

      if (!sorted.length) {
        $tb.html(`<tr><td colspan="7"><div class="alert alert-info" style="margin:0;">Nessun evento trovato.</div></td></tr>`);
        updateSortIndicators();
        return;
      }

      // render BASE sempre
      $tb.html(sorted.map(rowHtml).join(""));
      updateSortIndicators();

      // pulizia timer precedente
      if (reorderPastTimer) {
        clearTimeout(reorderPastTimer);
        reorderPastTimer = null;
      }

      // pulizia classi visuali sempre
      $("#eventi_tbody tr.ev-row").removeClass("row-in-progress row-ended row-now-flash");

      // >>> SE NON È OGGI, ESCO QUI <<<
      if (!isViewingToday()) {
        return;
      }

      // da qui in poi SOLO oggi
      setTimeout(() => {
        markRowsInProgress();

        const $rows = $("#eventi_tbody tr.ev-row");
        const $target = findCurrentRow($rows);
        if ($target.length) {
          $target.addClass("row-now-flash");
          setTimeout(() => {
            $target.removeClass("row-now-flash");
          }, 2200);
        }
      }, 150);

      reorderPastTimer = setTimeout(() => {
        const reSorted = sorted.slice().sort((a, b) => {
          const sa = timeStateOrder(getRowTimeState(a));
          const sb = timeStateOrder(getRowTimeState(b));

          if (sa !== sb) return sa - sb;

          let c = (a.oraIn || "").localeCompare(b.oraIn || "");
          if (c !== 0) return c;

          c = (a.oraOut || "").localeCompare(b.oraOut || "");
          if (c !== 0) return c;

          c = (a.aula || "").localeCompare(b.aula || "");
          if (c !== 0) return c;

          return (a.docKey || "").localeCompare(b.docKey || "");
        });

        $tb.html(reSorted.map(rowHtml).join(""));
        updateSortIndicators();
        markRowsInProgress();
      }, 3500);
    }

    $("#tbl_eventi thead").off("click", "th.th-sort").on("click", "th.th-sort", function () {
      const key = ($(this).data("key") || "").toString();
      if (!key) return;

      if (sortState.key === key) sortState.dir = (sortState.dir === "asc") ? "desc" : "asc";
      else { sortState.key = key; sortState.dir = "asc"; }

      paint();
    });

    $("#ev_q").off("input").on("input", paint);

    paint();
  }

  function loadEventi(dateIso) {
    if (!dateIso) { showInlineMsg("warning", "Seleziona una data."); return; }

    $("#orario_title").text(`Eventi · ${isoToIt(dateIso)}`);
    showInlineMsg("info", "Caricamento eventi...");

    if (DEBUG.enabled && DEBUG.logFetch) dbgGroup("FETCH eventiRead.php");

    $.getJSON("eventiRead.php", { date: dateIso }, function (r) {
      if (DEBUG.enabled && DEBUG.logFetch) {
        dbg("response ok?", r && r.ok, "items", (r && r.items) ? r.items.length : 0);
        dbgGroupEnd();
      }

      if (!r || r.ok !== true) {
        showInlineMsg("danger", (r && r.error) ? r.error : "Errore lettura eventi");
        return;
      }

      const items = r.items || [];
      renderEventiList(items, dateIso);
    }).fail(function (xhr) {
      dbgErr("Errore server eventiRead.php", xhr && xhr.status, xhr && xhr.responseText);
      showInlineMsg("danger", "Errore server lettura eventi");
    });
  }

  function clearAutoRefresh() {
    if (autoRefreshTimer) {
      clearInterval(autoRefreshTimer);
      autoRefreshTimer = null;
    }
  }

  function setupAutoRefreshForCurrentScope() {
    clearAutoRefresh();

    const scope = ($("#v_scope").val() || "").toString().trim().toUpperCase();

    if (scope !== "EVENTI" && scope !== "ASSENZE" && scope !== "SOSTITUZIONI") {
      return;
    }

    autoRefreshTimer = setInterval(function () {
      const currentScope = ($("#v_scope").val() || "").toString().trim().toUpperCase();

      // sicurezza: se nel frattempo l'utente ha cambiato vista, fermo tutto
      if (currentScope !== "EVENTI" && currentScope !== "ASSENZE" && currentScope !== "SOSTITUZIONI") {
        clearAutoRefresh();
        return;
      }

      if (DEBUG.enabled) {
        dbg("[ORARIO][AUTOREFRESH]", {
          scope: currentScope,
          everyMs: AUTO_REFRESH_MS
        });
      }

      loadOrario();
    }, AUTO_REFRESH_MS);
  }

  // =============================================================================
  //  RENDER GRID: GIORNO o SETTIMANA
  // =============================================================================

  // =====================================================================================
  //  renderGridFromGrid (CONSOLE LOG AGGIUNTI SOLO NEL PUNTO "DOCENTE", LOGICA INVARIATA)
  // =====================================================================================
  // =====================================================================================
  //  renderGridFromGrid (FIX: privacy perm/pb in AULA + fix filtro DOCENTE + cleanup vars)
  // =====================================================================================
  function renderGridFromGrid(period, dateIso, grid, scope, blockedMap) {

    console.groupCollapsed("[ORARIO][RENDER enter]");
    console.log("period/dateIso/scope", { period, dateIso, scope });
    console.log("gridKeys", Object.keys(grid || {}).length);
    console.log("grid sample keys", Object.keys(grid || {}).slice(0, 5));
    console.log("container #orario_content exists?", $("#orario_content").length);
    console.groupEnd();

    const $c = $("#orario_content");
    const scopeUp = String(scope || "").trim().toUpperCase();

    // -------------------------------------------------------------------------
    //  Helper: filtra AULA NON DISPONIBILE se coesistono eventi reali
    // -------------------------------------------------------------------------
    function filterAulaNonDisponibile(evs) {
      if (!evs || !evs.length) return evs || [];

      return evs.filter(ev => {
        if (!ev) return false;

        const type = normalizeType(ev);
        const title = String(ev.title || ev.label || "").trim().toUpperCase();
        const badge = String(ev.badge || "").trim().toUpperCase();

        // evento gestionale da NON mostrare mai
        if (type === "imp" && title === "AULA NON DISPONIBILE") return false;
        if (type === "imp" && badge === "AULA NON DISPONIBILE") return false;

        return true;
      });
    }

    function filterDocenteSlotEvents(evsIn, targetKey) {
      let evs = (evsIn || []).slice();

      function sostituzioneContainsTarget(e) {
        const s = e && e.sostituzione;
        if (!s || !targetKey) return false;
        return [s.sostituto, s.sostituito]
          .map(normPersonName)
          .filter(Boolean)
          .includes(targetKey);
      }

      // docenti presenti nello slot (solo eventi non-assenza)
      const lessonTeacherKeys = new Set();
      evs.forEach(e => {
        const t = normalizeType(e);
        if (isTeacherAbsenceType(t)) return;
        if (sostituzioneContainsTarget(e)) lessonTeacherKeys.add(targetKey);

        const wl = uniq(
          (Array.isArray(e._whoList) && e._whoList.length) ? e._whoList : toArrWho(e.who || e.sub || "")
        ).map(normTxt).filter(Boolean);

        wl.forEach(n => lessonTeacherKeys.add(normPersonName(n)));
      });

      const hasTargetInSlot = lessonTeacherKeys.has(targetKey);

      // 1) Eventi "normali" (curr/udi/imp/...) 
      // - curr/udi con docente esplicito: solo se contengono il docente target
      // - imp/pranzo/studio senza who: tienili se il docente ha lezione nello slot
      evs = evs.filter(e => {
        const t = normalizeType(e);
        if (isTeacherAbsenceType(t)) return true; // assenze dopo
        if (sostituzioneContainsTarget(e)) return true;

        const tl = String(t || "").trim().toLowerCase();

        const wl = uniq(
          (Array.isArray(e._whoList) && e._whoList.length) ? e._whoList : toArrWho(e.who || e.sub || "")
        ).map(normTxt).filter(Boolean);

        const keys = wl.map(normPersonName);

        const titleUp = String(e.title || e.label || "").trim().toUpperCase();
        const badgeUp = String(e.badge || "").trim().toUpperCase();

        if (
          tl === "imp" &&
          (
            titleUp.includes("CONSIGLIO DI CLASSE") ||
            badgeUp.includes("CONSIGLIO DI CLASSE") ||
            titleUp.includes("COLLEGIO DOCENTI") ||
            badgeUp.includes("COLLEGIO DOCENTI")
          )
        ) {
          return true;
        }
        // eventi di classe senza docente esplicito
        if (["imp", "pranzo", "studio"].includes(tl) && keys.length === 0) {
          return hasTargetInSlot;
        }

        return keys.includes(targetKey);
      });

      // 2) Assenze => target sempre; colleghi solo se compresenti;
      //    viaggi/uscite "di classe" con who vuoto vanno tenuti se il docente ha lezione nello slot
      evs = evs.filter(e => {
        const t = normalizeType(e);
        if (!isTeacherAbsenceType(t)) return true;

        const tl = String(t || "").trim().toLowerCase();

        const absWho = uniq(
          (Array.isArray(e._whoList) && e._whoList.length) ? e._whoList : toArrWho(e.who || e.sub || "")
        ).map(normTxt).filter(Boolean);

        const absKeys = absWho.map(normPersonName);

        // viaggio/uscita di classe senza docente esplicito
        if (["viag", "uscc", "uscf"].includes(tl) && absKeys.length === 0) {
          return hasTargetInSlot;
        }

        // assenza del docente target
        if (absKeys.includes(targetKey)) return true;

        // se il docente target non è presente nello slot, non mostrare colleghi
        if (!hasTargetInSlot) return false;

        // collega assente solo se compresente nello stesso slot
        return absKeys.some(k => lessonTeacherKeys.has(k));
      });

      // 3) HARD FILTER: per viaggi/uscite con who vuoto (eventi di classe) NON filtrare via
      evs = evs.filter(e => {
        const t = normalizeType(e);
        const tl = String(t || "").trim().toLowerCase();

        if (!["viag", "uscc", "uscf"].includes(tl)) return true;

        const whoLines = uniq(
          (Array.isArray(e._whoList) && e._whoList.length) ? e._whoList : toArrWho(e.who || e.sub || "")
        ).map(normTxt).filter(Boolean);

        // evento di classe senza docente esplicito: tienilo
        if (!whoLines.length) return true;

        const keys = whoLines.map(normPersonName);
        return keys.includes(targetKey);
      });

      return evs;
    }
    // -------------------------------------------------------------------------
    //  cellKey: calcola la firma “per slot”
    // -------------------------------------------------------------------------
    function cellKey(ymd, ora) {
      const key = ymd + "|" + ora;
      let evs = grid[key] || [];
      if (!evs.length) return "";

      evs = filterAulaNonDisponibile(evs);

      // ✅ DOCENTE: filtra PRIMA della firma (rowspan)
      if (scopeUp === "DOCENTE") {
        const targetKey = getTargetDocenteKey();
        evs = filterDocenteSlotEvents(evs, targetKey);
        if (!evs.length) return "";
      }

      const sig = slotSignatureForRowspan(evs, scope);
      if (DEBUG.enabled && DEBUG.logSignature) dbg(`cellKey ${key} => sigLen=${sig.length}`);
      return sig;
    }

    // -------------------------------------------------------------------------
    //  computeRowspansForDay: crea la mappa rowspan per un giorno
    // -------------------------------------------------------------------------
    function computeRowspansForDay(ymd) {
      const spans = {};
      const MAX_SPAN = 18;

      if (DEBUG.enabled && DEBUG.logRowspan) dbgGroup(`ROWSPAN compute for day ${ymd}`);

      for (let i = 0; i < ORARI.length; i++) {
        const ora = ORARI[i];
        if (spans[ora]?.skip) continue;

        const k = cellKey(ymd, ora);

        if (!k) {
          spans[ora] = { span: 1, skip: false };
          if (DEBUG.enabled && DEBUG.logRowspan) dbg(`@${ora} empty -> span=1`);
          continue;
        }

        let span = 1;
        if (DEBUG.enabled && DEBUG.logRowspan) dbg(`@${ora} start span, sigLen=${k.length}`);

        for (let j = i + 1; j < ORARI.length; j++) {
          if (span >= MAX_SPAN) {
            if (DEBUG.enabled && DEBUG.logRowspan) dbg(`  stop: reached MAX_SPAN=${MAX_SPAN}`);
            break;
          }
          const ora2 = ORARI[j];
          const k2 = cellKey(ymd, ora2);
          if (k2 !== k) {
            if (DEBUG.enabled && DEBUG.logRowspan) dbg(`  stop: signature differs at ${ora2}`);
            break;
          }
          span++;
          if (DEBUG.enabled && DEBUG.logRowspan) dbg(`  merge ok with ${ora2} => span=${span}`);
        }

        spans[ora] = { span, skip: false };
        for (let j = i + 1; j < i + span; j++) spans[ORARI[j]] = { span: 0, skip: true };
        i = i + span - 1;
      }

      if (DEBUG.enabled && DEBUG.logRowspan) dbgGroupEnd();
      return spans;
    }

    // -------------------------------------------------------------------------
    //  Helper: target docente key (solo in scope DOCENTE)
    // -------------------------------------------------------------------------
    function getTargetDocenteKey() {
      const t = ($("#v_target option:selected").text() || $("#v_target").val() || "");
      return normPersonName(t);
    }

    // -------------------------------------------------------------------------
    //  cellData: costruisce il contenuto HTML della cella (eventi)
    // -------------------------------------------------------------------------
    function cellData(ymd, ora, span = 1) {
      const key = ymd + "|" + ora;
      let evs = grid[key] || [];
      if (!evs.length) return { html: "", tdClass: "" };

      evs = filterAulaNonDisponibile(evs);

      // normalizzo: unisco duplicati e ordino per importanza (render)
      evs = stableSortSlotEvents(mergeSlotEvents(evs, scope));

      // DOCENTE: usa lo stesso filtro delle signature
      if (scopeUp === "DOCENTE") {
        const targetKey = getTargetDocenteKey();
        evs = filterDocenteSlotEvents(evs, targetKey);
        if (!evs.length) return { html: "", tdClass: "" };
      }

      // mappa assenze (DOPO i filtri, se DOCENTE)
      let teacherAbsMap = buildTeacherAbsenceMapForSlot(evs, ora);

      // nello slot ci sono lezioni (non-assenza)?
      const slotHasLesson = (evs || []).some(e => !isTeacherAbsenceType(normalizeType(e)));

      // domType per td-*
      let domType = "curr", domP = -1;
      evs.forEach(e => {
        const t = normalizeType(e);
        const pr = priority(t);
        if (pr > domP) { domP = pr; domType = t; }
      });
      const tdClass = "td-" + domType;

      const blockedSet = (scopeUp === "AULA") ? slotBlockedSet(blockedMap, ymd, ora) : null;
      const blockingClassSet = classesSetFromEvents(evs, true);

      const spanPx = (span && span > 1) ? (span * SLOT_MIN_PX) : 0;
      const wrapCls = "cell-wrap" + ((span && span > 1) ? " is-tall" : "");
      const wrapStyle = ` style="display:flex;flex-direction:column;height:100%;${spanPx ? `min-height:${spanPx}px;` : ``}"`;

      let filledOnce = false;

      const html =
        `<div class="${wrapCls}"${wrapStyle}>` +
        evs.map((ev) => {

          const type = normalizeType(ev);

          const isPermPb = (type === "perm" || type === "pb");
          const privacyHide = (scopeUp === "AULA" && isPermPb);

          const rawTitle = String(ev.title || ev.label || "");
          const displayTitle = privacyHide
            ? (type === "perm" ? "Permesso" : "Permesso breve")
            : rawTitle;

          const rawBadge = String(ev.badge || "");
          const displayBadge = privacyHide ? "" : rawBadge;

          const titleHtml = escapeHtml(displayTitle);

          let whoLines = uniq(
            (Array.isArray(ev._whoList) && ev._whoList.length)
              ? ev._whoList
              : toArrWho(ev.who || ev.sub || "")
          ).map(normTxt).filter(Boolean);
          let whoUsernames = Array.isArray(ev.who_usernames) ? ev.who_usernames.map(normTxt) : [];

          if (scopeUp === "DOCENTE" && type === "udi") {
            const targetKey = getTargetDocenteKey();
            if (targetKey) {
              const filteredNames = [];
              const filteredUsernames = [];
              whoLines.forEach((w, idx) => {
                if (normPersonName(w) === targetKey) {
                  filteredNames.push(w);
                  filteredUsernames.push(whoUsernames[idx] || "");
                }
              });
              whoLines = filteredNames;
              whoUsernames = filteredUsernames;
            }
          }

          const whoForTitle = whoLines.length ? whoLines.join(" · ") : "";

          const projectedAula = (scopeUp === "AULA")
            ? eventIsOverriddenByBlockedClasses(ev, blockedSet)
            : false;

          const projectedByBlocking = (scopeUp === "CLASSE")
            ? eventIntersectsClassSet(ev, blockingClassSet)
            : false;

          const projected = projectedAula || projectedByBlocking;

          const fullyAbsent = isEventFullyAbsentByTeacher(ev, teacherAbsMap);
          const absentCls = fullyAbsent ? " ev-absent-full" : "";

          const shouldOverride = projected || fullyAbsent;
          const overridden = (shouldOverride && !isBlocking(type) && !isAulaNonDisponibile(ev))
            ? " ev-overridden"
            : "";

          const classiHtml = classiHtmlFromEv(ev);
          const roomsHtml = roomsHtmlFromEv(ev);
          const sostHtml = sostituzioneHtml(ev);

          const tooltipText =
            displayTitle +
            (whoForTitle ? " - " + whoForTitle : "") +
            (displayBadge ? " - " + displayBadge : "");

          let canFill = false;
          if (span && span >= 1) {
            if (scopeUp === "AULA") {
              canFill = true;
            } else if (scopeUp === "DOCENTE") {
              if (slotHasLesson) canFill = !isTeacherAbsenceType(type);
              else canFill = isTeacherAbsenceType(type);
            } else {
              canFill = !isTeacherAbsenceType(type);
            }
          }
          if (canFill && filledOnce) canFill = false;
          if (canFill) filledOnce = true;

          const fillStyle = canFill
            ? ` style="flex:1;display:flex;flex-direction:column;min-height:0;"`
            : "";

          const sostCls = ev.sostituzione ? " ev-with-sost" : "";

          return `
        <div class="ev ev-${type}${sostCls}${overridden}${absentCls}"${fillStyle}
          title="${escapeHtml(tooltipText)}">
          <div class="ev-title">${titleHtml}</div>

          ${whoLines.length ? `
            <div class="ev-who">
              ${whoLines.map((w, idx) => {
            const canStrike = !isTeacherAbsenceType(type);
            const abs = canStrike ? teacherAbsMap.get(normPersonName(w)) : null;
            const teacherHtml = teacherChipHtml(w, whoUsernames[idx] || "");
            if (!abs) return `<div class="ev-who-line">${teacherHtml}</div>`;
            return `<div class="ev-who-line is-absent absent-${abs.type}" title="${escapeHtml(abs.reasonText)}">${teacherHtml}</div>`;
          }).join("")}
            </div>
          ` : ``}

          ${classiHtml}
          ${displayBadge ? `<div><span class="ev-badge badge-${ev.sostituzione ? "sost" : type}">${escapeHtml(displayBadge)}</span></div>` : ``}
          ${sostHtml}
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
        if (sp.skip) { html += `</tr>`; return; }

        const cd = cellData(dateIso, ora, sp.span);
        const rs = (sp.span && sp.span > 1) ? ` rowspan="${sp.span}"` : "";

        const hPx = (sp.span && sp.span > 1) ? (sp.span * SLOT_MIN_PX) : SLOT_MIN_PX;
        const tdStyle = ` style="height:${hPx}px;vertical-align:top;"`;

        html += `<td class="${cd.tdClass}"${rs}${tdStyle}>${cd.html}</td>`;
        html += `</tr>`;
      });

      html += `</tbody></table>`;
      $c.html(renderLegend() + html);

      if (DEBUG.enabled && DEBUG.logRender) {
        const $tbl = $c.find("table.orario-grid");
        dbg("DOM render stats", {
          tdWithRowspan: $tbl.find("td[rowspan]").length,
          evCount: $tbl.find(".ev").length,
          rows: $tbl.find("tbody tr").length
        });
        if (DEBUG.logHtmlPreview) dbg("HTML preview", ($c.html() || "").slice(0, DEBUG.htmlPreviewChars));
      }

      dbgGroupEnd();
      return;
    }

    // -------------------------------------------------------------------------
    //  RENDER: SETTIMANA
    // -------------------------------------------------------------------------
    dbgGroup(`RENDER SETTIMANA dateRef=${dateIso} scope=${scope}`);

    const mon = getMonday(dateIso);
    const today = todayIso();
    const giorniLabel = giorniLabelForDate(dateIso);
    const days = giorniLabel.map((lab, i) => ({ lab, iso: addDays(mon, i) }));
    const spansByDay = {};
    days.forEach(d => { spansByDay[d.iso] = computeRowspansForDay(d.iso); });

    let html = `<table class="orario-grid"><thead><tr>
      <th class="ora-col">Ora</th>
      ${days.map(d => `<th class="${d.iso === today ? "th-today" : ""}">${d.lab}<div style="opacity:.75;font-size:16px;">${isoToIt(d.iso)}</div></th>`).join("")}
    </tr></thead><tbody>`;

    ORARI.forEach(ora => {
      let tdAdded = 0;
      html += `<tr><td class="ora-col">${ora}</td>`;

      days.forEach(d => {
        const sp = spansByDay[d.iso]?.[ora] || { span: 1, skip: false };
        if (sp.skip) return;

        const cd = cellData(d.iso, ora, sp.span);
        const rs = (sp.span && sp.span > 1) ? ` rowspan="${sp.span}"` : "";

        const hPx = (sp.span && sp.span > 1) ? (sp.span * SLOT_MIN_PX) : SLOT_MIN_PX;
        const tdStyle = ` style="height:${hPx}px;vertical-align:top;"`;

        const todayClass = d.iso === today ? " td-today" : "";
        html += `<td class="${cd.tdClass}${todayClass}"${rs}${tdStyle}>${cd.html}</td>`;
        tdAdded++;
      });

      console.log("[ORARIO][ROW]", ora, "tdAdded", tdAdded);
      html += `</tr>`;
    });

    html += `</tbody></table>`;

    $c.html(renderLegend() + html);

    if (DEBUG.enabled && DEBUG.logRender) {
      const $tbl2 = $c.find("table.orario-grid");
      dbg("DOM render stats", {
        tdWithRowspan: $tbl2.find("td[rowspan]").length,
        evCount: $tbl2.find(".ev").length,
        days: days.map(x => x.iso),
        rows: $tbl2.find("tbody tr").length
      });
      if (DEBUG.logHtmlPreview) dbg("HTML preview", ($c.html() || "").slice(0, DEBUG.htmlPreviewChars));
    }

    dbgGroupEnd();
  }


  // =============================================================================
  //  MULTI-AULE (AULA + GIORNO): loadOrarioMultiAuleGiorno
  // =============================================================================

  function loadOrarioMultiAuleGiorno() {
    const scope = "AULA"; // ✅ in multi-aule siamo sempre in vista AULA
    let date = ($("#v_date").val() || "").trim(); // in vista giorno, date è il riferimento per il caricamento di tutte le aule (stesso giorno)
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

    const calls = visible.map(a => {
      return $.when(
        $.getJSON("orarioRead.php", {
          scope: "AULA",
          period: "GIORNO",
          date: date,
          target: a.id
        }),
        $.getJSON("orarioAulaBlocchi.php", {
          scope: "AULA",
          period: "GIORNO",
          date: date,
          target: a.id
        })
      );
    });

    $.when.apply($, calls).done(function () {
      let results = Array.prototype.slice.call(arguments);

      // se c'è una sola aula, jQuery non restituisce un array di risultati omogeneo
      if (visible.length === 1) {
        results = [arguments];
      }

      const cols = [];

      results.forEach(function (pair, idx) {
        const aulaMeta = visible[idx];

        // con $.when(getJSON1, getJSON2):
        // pair[0] = risultato prima ajax
        // pair[1] = risultato seconda ajax
        const readPack = pair[0];
        const blocchiPack = pair[1];

        const readResp = Array.isArray(readPack) ? readPack[0] : null;
        const blocchiResp = Array.isArray(blocchiPack) ? blocchiPack[0] : null;

        if (!readResp || readResp.ok !== true) {
          cols.push({
            id: aulaMeta.id,
            label: aulaMeta.label,
            grid: {},
            blockedMap: {}
          });
          return;
        }

        let grid = readResp.grid || {};
        let blockedMap = {};

        if (blocchiResp && blocchiResp.ok === true) {
          const gridAssenze = blocchiResp.gridAssenze || {};
          blockedMap = blocchiResp.blockedMap || {};
          grid = mergeGrids(grid, gridAssenze);
        }

        cols.push({
          id: aulaMeta.id,
          label: aulaMeta.label,
          grid: grid,
          blockedMap: blockedMap
        });
      });

      // se tutte le colonne sono vuote, messaggio
      const hasAny = cols.some(col => col.grid && Object.keys(col.grid).length > 0);
      if (!hasAny) {
        showInlineMsg("info", "Nessun evento trovato per le aule visibili.");
        return;
      }

      // ==========================================================
      // RENDER MULTI-AULE GIORNO
      // ==========================================================
      const MAX_SPAN = 3;

      function filterAulaNonDisponibile(evs) {
        if (!evs || !evs.length) return evs || [];

        return evs.filter(ev => {
          if (!ev) return false;

          const type = normalizeType(ev);
          const title = String(ev.title || ev.label || "").trim().toUpperCase();
          const badge = String(ev.badge || "").trim().toUpperCase();

          if (type === "imp" && title === "AULA NON DISPONIBILE") return false;
          if (type === "imp" && badge === "AULA NON DISPONIBILE") return false;

          return true;
        });
      }

      function cellKeyFromGrid(g, ora) {
        const key = date + "|" + ora;
        let evs = (g[key] || []).slice();

        evs = filterAulaNonDisponibile(evs);

        // IMPORTANTISSIMO:
        // se dopo il filtro non resta nulla, lo slot è vuoto
        // e NON deve mai avere firma / rowspan
        if (!evs.length) return "";

        return slotSignatureForRowspan(evs, "AULA");
      }

      function computeRowspansForCol(col) {
        const out = {};

        for (let i = 0; i < ORARI.length; i++) {
          const ora = ORARI[i];
          if (out[ora]) continue;

          const key = cellKeyFromGrid(col.grid || {}, ora);

          // slot vuoto: mai rowspan, sempre una riga sola
          if (!key) {
            out[ora] = { span: 1, skip: false };
            continue;
          }

          let span = 1;

          for (let j = i + 1; j < ORARI.length; j++) {
            if (span >= MAX_SPAN) break;

            const nextOra = ORARI[j];
            const nextKey = cellKeyFromGrid(col.grid || {}, nextOra);

            // appena il prossimo slot è vuoto o cambia firma, stop
            if (!nextKey || nextKey !== key) break;

            span++;
          }

          out[ora] = { span: span, skip: false };

          for (let k = 1; k < span; k++) {
            out[ORARI[i + k]] = { span: 1, skip: true };
          }
        }

        return out;
      }

      function cellHtmlForCol(col, ora, span) {
        let evs = ((col.grid || {})[`${date}|${ora}`] || []).slice();
        evs = filterAulaNonDisponibile(evs);

        const blockedSet = slotBlockedSet(col.blockedMap || {}, date, ora);

        if (!evs.length) {
          return {
            tdClass: "",
            html: ""
          };
        }

        const merged = mergeSlotEvents(evs, "AULA");
        const sorted = stableSortSlotEvents(merged);
        const teacherAbsMap = buildTeacherAbsenceMapForSlot(sorted, ora);

        let domType = "curr";
        sorted.forEach(ev => {
          const t = normalizeType(ev);
          if (priority(t) > priority(domType)) domType = t;
        });

        const tdClass = `td-${domType}`;
        const html = sorted.map(ev => {
          const type = normalizeType(ev);
          const isOwnAbsenceEvent = isTeacherAbsenceType(type);

          const cls = ["ev", "ev-" + type];
          if (ev.sostituzione) {
            cls.push("ev-with-sost");
          }
          if (!isBlocking(type) && eventIsOverriddenByBlockedClasses(ev, blockedSet)) {
            cls.push("ev-overridden");
          }

          // barra tutta la card solo per vere lezioni/eventi didattici,
          // NON per la card di assenza stessa
          const fullAbsent = isOwnAbsenceEvent ? false : isEventFullyAbsentByTeacher(ev, teacherAbsMap);
          if (fullAbsent) {
            cls.push("ev-absent-full");
          }

          const badge = ev.badge ? `<div class="ev-badge badge-${ev.sostituzione ? "sost" : type}">${escapeHtml(ev.badge)}</div>` : "";
          const title = ev.title ? `<div class="ev-title">${escapeHtml(ev.title)}</div>` : "";

          const whoArr = uniq(
            (Array.isArray(ev._whoList) && ev._whoList.length)
              ? ev._whoList
              : toArrWho(ev.who || ev.sub || "")
          ).map(normTxt).filter(Boolean);
          const whoUsernames = Array.isArray(ev.who_usernames) ? ev.who_usernames.map(normTxt) : [];

          let whoHtml = "";
          if (whoArr.length) {
            whoHtml = `
      <div class="ev-who">
        ${whoArr.map((w, idx) => {
              const teacherHtml = teacherChipHtml(w, whoUsernames[idx] || "");
              // nella card di assenza NON devo ribarrare il docente
              if (isOwnAbsenceEvent) {
                return `<div class="ev-who-line">${teacherHtml}</div>`;
              }

              const abs = teacherAbsMap.get(normPersonName(w));
              if (!abs) {
                return `<div class="ev-who-line">${teacherHtml}</div>`;
              }

              const showAbsReason = (String(col.visibilityLevel || "PUBLIC").toUpperCase() === "FULL");
              const absNote = showAbsReason
                ? ` <span class="ev-absence-note">(${escapeHtml(abs.reasonText || "Assente")})</span>`
                : "";

              return `<div class="ev-who-line is-absent">${teacherHtml}${absNote}</div>`;
            }).join("")}
      </div>
    `;
          }

          const rooms = roomsHtmlFromEv(ev);
          const classi = classiHtmlFromEv(ev);
          const sost = sostituzioneHtml(ev);

          return `<div class="${cls.join(" ")}">${badge}${title}${whoHtml}${sost}${rooms}${classi}</div>`;
        }).join("");

        return { tdClass, html };
      }

      const spansByCol = {};
      cols.forEach(col => {
        spansByCol[col.id] = computeRowspansForCol(col);
      });

      let html = `<table class="orario-grid"><thead><tr>
    <th class="ora-col">Ora</th>
    ${cols.map(col => `<th>${escapeHtml(col.label || col.id)}</th>`).join("")}
  </tr></thead><tbody>`;

      ORARI.forEach(ora => {
        html += `<tr><td class="ora-col">${ora}</td>`;

        cols.forEach(col => {
          const sp = spansByCol[col.id]?.[ora] || { span: 1, skip: false };
          if (sp.skip) return;

          const cd = cellHtmlForCol(col, ora, sp.span);
          const rs = (sp.span && sp.span > 1) ? ` rowspan="${sp.span}"` : "";

          html += `<td class="${cd.tdClass}"${rs}>${cd.html}</td>`;
        });

        html += `</tr>`;
      });

      html += `</tbody></table>`;
      $("#orario_content").html(renderLegend() + html);

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
    const scope = $("#v_scope").val(); // AULA - DOCENTE - CLASSE - EVENTI - ASSENZE
    const period = $("#v_period").val(); // GIORNO - SETTIMANA
    let date = ($("#v_date").val() || "").trim(); // YYYY-MM-DD 
    const target = ($("#v_target").val() || "").trim(); // id dell’aula/classe/docente

    if (scope === "EVENTI") {
      setupAutoRefreshForCurrentScope();
      if (!date) { showInlineMsg("warning", "Seleziona una data."); return; }
      loadEventi(date);
      return;
    }

    if (scope === "ASSENZE") {
      setupAutoRefreshForCurrentScope();
      if (!date) { showInlineMsg("warning", "Seleziona una data."); return; }

      if (typeof window.loadAssenze !== "function") {
        showInlineMsg("danger", "scriptAssenze.js non caricato.");
        return;
      }

      window.loadAssenze(date);
      return;
    }

    if (scope === "SOSTITUZIONI") {
      setupAutoRefreshForCurrentScope();
      if (!date) { showInlineMsg("warning", "Seleziona una data."); return; }

      if (typeof window.loadSostituzioni !== "function") {
        showInlineMsg("danger", "scriptSostituzioni.js non caricato.");
        return;
      }

      window.loadSostituzioni(date);
      return;
    }

    clearAutoRefresh();

    // ✅ NORMALIZZA DATE PER SETTIMANA: SEMPRE LUNEDÌ
    if (period === "SETTIMANA" && date) {
      const mon = getMonday(date);
      if (mon !== date) {
        date = mon;
        $("#v_date").val(mon);                 // mantiene UI coerente
        $("#v_week").selectpicker("val", mon); // allinea anche il week picker
      }
    }

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
    dbg("REQUEST orarioRead.php", { scope, period, date, target, targetLabel: $("#v_target option:selected").text() });
    $.getJSON("orarioRead.php", { scope, period, date, target }, async function (r) {
      dbg("RESPONSE orarioRead.php", { ok: r?.ok, gridKeys: r?.grid ? Object.keys(r.grid).length : 0, error: r?.error });
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

      try {
        console.groupCollapsed("[ORARIO][CALL renderGridFromGrid]");
        console.log("args", { period, date, scope, blockedMapKeys: blockedMap ? Object.keys(blockedMap).length : 0 });
        console.log("gridKeys", Object.keys(grid || {}).length);
        console.groupEnd();

        renderGridFromGrid(period, date, grid, scope, blockedMap);

        console.log("[ORARIO][AFTER render] #orario_content html len =", ($("#orario_content").html() || "").length);
      } catch (e) {
        console.error("[ORARIO][renderGridFromGrid EXCEPTION]", e);
        showInlineMsg("danger", "Errore JS in render orario (vedi console).");
      }

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

    // ✅ default: scope EVENTI
    const defaultScope = String(window.ORARIO_DEFAULT_SCOPE || "EVENTI").trim().toUpperCase();
    const defaultPeriod = String(window.ORARIO_DEFAULT_PERIOD || "GIORNO").trim().toUpperCase();

    $("#v_scope").selectpicker("val", defaultScope);
    syncSegmented($("#v_scope"));

    // ✅ default: period GIORNO (eventi non ha settimana)
    $("#v_period").selectpicker("val", defaultPeriod);
    syncSegmented($("#v_period"));

    // ✅ default: date oggi
    const today = todayIso();
    $("#v_date").val(today);

    // week select lo puoi comunque popolare e settare al lunedì corrente,
    // ma NON deve cambiare v_date (vedi fix #1)
    fillWeekSelect();
    $("#v_week").selectpicker("val", getMonday(today));

    updateToolbarLayout();   // importante: applica subito le regole EVENTI
    loadOptions();           // con scope EVENTI andrà in loadOrario -> loadEventi(today)
    initSegmented();

    // pulsanti navigazione
    $("#btn_prev_week").off("click").on("click", function (e) { e.preventDefault(); prevNav(); });
    $("#btn_next_week").off("click").on("click", function (e) { e.preventDefault(); nextNav(); });
    $("#btn_prev_day").off("click").on("click", function (e) { e.preventDefault(); prevDayNav(); });
    $("#btn_next_day").off("click").on("click", function (e) { e.preventDefault(); nextDayNav(); });

    // navigazione aule (solo vista giorno aula)
    $("#btn_prev_aula").off("click").on("click", function (e) { e.preventDefault(); shiftAula(-1); });
    $("#btn_next_aula").off("click").on("click", function (e) { e.preventDefault(); shiftAula(+1); });

    $("#orario_content").off("click", ".orario-jump").on("click", ".orario-jump", function (e) {
      e.preventDefault();
      e.stopPropagation();
      jumpToOrario($(this).data("scope"), $(this).data("target"));
    });

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

    // forza stato coerente SOLO se NON sei in EVENTI
    if (($("#v_scope").val() || "").toString().trim().toUpperCase() !== "EVENTI") {
      $("#v_period").selectpicker("val", "SETTIMANA"); // meglio di .val + refresh
      syncSegmented($("#v_period"));
    }
    updateToolbarLayout();
  });

})();
