(function () {
    "use strict";

    const ORARI = [
        "07:50", "08:40", "09:30", "10:30", "11:20", "12:10", "13:00", "13:50",
        "14:40", "15:30", "16:20", "17:10", "18:00", "18:50", "19:40", "20:30", "21:30", "22:20"
    ];

    const SCHOOL_START = "2025-09-10";
    const SCHOOL_END = "2026-06-10";

    const LS_KEY_TARGET_BY_SCOPE = "orario_target_by_scope_mobile_v1";
    const LS_KEY_SCOPE = "orario_mobile_scope_v1";
    const LS_KEY_EVENT_SORT = "orario_mobile_event_sort_v2";
    let state = {
        loading: false,
        options: [],
        grid: {},
        blockedMap: {},
        listItems: [],
        currentScope: "AULA",
        selectedDate: ""
    };
    let pendingJumpTarget = "";

    function getEventSortMode() {
        try {
            const v = localStorage.getItem(LS_KEY_EVENT_SORT) || "AULA";
            return String(v).toUpperCase() === "AULA" ? "AULA" : "ORA";
        } catch (e) {
            return "AULA";
        }
    }

    function setEventSortMode(mode) {
        const v = String(mode || "").toUpperCase() === "AULA" ? "AULA" : "ORA";
        try {
            localStorage.setItem(LS_KEY_EVENT_SORT, v);
        } catch (e) { }
    }

    function syncEventSortButtons() {
        const mode = getEventSortMode();
        $(".mobile-event-sort-btn").each(function () {
            const $b = $(this);
            const v = String($b.data("sort") || "").toUpperCase();
            $b.toggleClass("is-active", v === mode);
        });
    }

    function escapeHtml(s) {
        s = (s == null ? "" : "" + s);
        return s.replace(/[&<>"']/g, c => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[c]));
    }

    function pad2(n) {
        return (n < 10 ? "0" + n : "" + n);
    }

    function todayIso() {
        const d = new Date();
        return d.getFullYear() + "-" + pad2(d.getMonth() + 1) + "-" + pad2(d.getDate());
    }

    function isoToIt(iso) {
        if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return iso || "";
        const [y, m, d] = iso.split("-");
        return d + "/" + m + "/" + y;
    }

    function isoToLongIt(iso) {
        if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return iso || "";
        const d = new Date(iso + "T00:00:00");
        return d.toLocaleDateString("it-IT", {
            weekday: "short",
            day: "2-digit",
            month: "2-digit",
            year: "numeric"
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

    function compareIsoDate(a, b) {
        const aa = String(a || "").trim();
        const bb = String(b || "").trim();
        return aa.localeCompare(bb);
    }

    function getEventTimeStateForMobile(it, selectedDateIso) {
        const sel = String(selectedDateIso || "").trim();
        const today = todayIso();

        // giorno passato / futuro rispetto a oggi
        if (sel < today) return "past";
        if (sel > today) return "future";

        // oggi: confronto sugli orari
        const nowMin = hhmmToMinutes(getCurrentHHMM());
        if (nowMin === null) return "future";

        const oraIn = hhmmToMinutes(it.oraIn || it.ora || "");
        let oraOut = hhmmToMinutes(it.oraOut || it.oraFine || it.fine || "");

        if (oraIn === null) return "future";
        if (oraOut === null) oraOut = oraIn + 1;

        if (nowMin < oraIn) return "future";
        if (nowMin >= oraOut) return "past";
        return "current";
    }

    function eventStateOrderValue(state) {
        if (state === "current") return 0;
        if (state === "future") return 1;
        return 2; // past in fondo
    }

    function cmpAulaMobile(a, b) {
        const aa = String(a.aulaKey || a.aula || "").trim();
        const bb = String(b.aulaKey || b.aula || "").trim();

        const aEmpty = aa === "";
        const bEmpty = bb === "";

        // prima chi ha aula, poi chi non ce l'ha (= fuori scuola)
        if (aEmpty && !bEmpty) return 1;
        if (!aEmpty && bEmpty) return -1;

        return aa.localeCompare(bb, undefined, { numeric: true, sensitivity: "base" });
    }

    function sortEventItemsForMobile(items, selectedDateIso) {
        const mode = getEventSortMode();

        return (items || []).slice().sort((a, b) => {
            const stateA = getEventTimeStateForMobile(a, selectedDateIso);
            const stateB = getEventTimeStateForMobile(b, selectedDateIso);

            let c = eventStateOrderValue(stateA) - eventStateOrderValue(stateB);
            if (c !== 0) return c;

            if (mode === "AULA") {
                c = cmpAulaMobile(a, b);
                if (c !== 0) return c;

                c = String(a.oraIn || "").localeCompare(String(b.oraIn || ""));
                if (c !== 0) return c;
            } else {
                c = String(a.oraIn || "").localeCompare(String(b.oraIn || ""));
                if (c !== 0) return c;

                c = String(a.oraOut || "").localeCompare(String(b.oraOut || ""));
                if (c !== 0) return c;

                c = cmpAulaMobile(a, b);
                if (c !== 0) return c;
            }

            c = String(a.title || a.detail || "").localeCompare(String(b.title || b.detail || ""));
            if (c !== 0) return c;

            return String(a.who || a.docente || "").localeCompare(String(b.who || b.docente || ""));
        });
    }

    function normTxt(s) {
        return String(s ?? "").replace(/\s+/g, " ").trim();
    }

    function up(s) {
        return String(s || "").trim().toUpperCase();
    }

    function uniq(arr) {
        const out = [];
        const seen = new Set();
        (arr || []).forEach(x => {
            const v = String(x == null ? "" : x).trim().toUpperCase();
            if (!v || seen.has(v)) return;
            seen.add(v);
            out.push(v);
        });
        return out;
    }

    function uniqPreserve(arr) {
        const out = [];
        const seen = new Set();
        (arr || []).forEach(x => {
            const raw = String(x == null ? "" : x).trim();
            const key = raw.toUpperCase();
            if (!raw || seen.has(key)) return;
            seen.add(key);
            out.push(raw);
        });
        return out;
    }

    function toArrMaybe(v) {
        if (!v) return [];
        if (Array.isArray(v)) return v;
        return String(v).split(",").map(x => x.trim()).filter(Boolean);
    }

    function toArrWho(v) {
        if (!v) return [];
        if (Array.isArray(v)) return v.map(x => String(x)).map(s => s.trim()).filter(Boolean);
        return String(v)
            .replace(/\s+\u00B7\s+/g, "\n")
            .split(/[\r\n;,|]+/)
            .map(s => s.trim())
            .filter(Boolean);
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

    function clampIsoDate(iso) {
        const d = String(iso || "").trim();
        if (!/^\d{4}-\d{2}-\d{2}$/.test(d)) return todayIso();
        if (d < SCHOOL_START) return SCHOOL_START;
        if (d > SCHOOL_END) return SCHOOL_END;
        return d;
    }

    function normalizeType(ev) {
        let t = (ev && ev.type != null) ? String(ev.type).trim() : "";
        if (!t) {
            const cls = (ev && ev.class != null) ? String(ev.class) : "";
            const m = cls.match(/\bev-(curr|udi|viag|imp|uscC|uscF|pb|perm|pranzo|studio|sost)\b/i);
            if (m) t = m[1];
        }
        return t ? t : "curr";
    }

    function priority(type) {
        const p = { uscF: 100, uscC: 90, viag: 80, sost: 70, imp: 50, pranzo: 35, studio: 35, udi: 20, curr: 10 };
        return p[type] || 0;
    }

    function isBlocking(type) {
        return type === "viag" || type === "uscC" || type === "uscF";
    }

    function isAulaNonDisponibile(ev) {
        const t = (ev && ev.title != null) ? String(ev.title).trim().toUpperCase() : "";
        return (normalizeType(ev) === "imp" && t === "AULA NON DISPONIBILE");
    }

    function evKeyForSort(ev) {
        const type = normalizeType(ev);
        const title = normTxt(ev.title || ev.label || "");
        const who = normTxt(ev.who || ev.sub || "");
        const badge = normTxt(ev.badge || "");
        const rooms = uniq(toArrMaybe(ev.rooms)).sort().join(",");
        const classi = uniq(toArrMaybe(ev.classi)).sort().join(",");
        return `${priority(type).toString().padStart(3, "0")}|${type}|${title}|${who}|${badge}|${rooms}|${classi}`;
    }

    function stableSortSlotEvents(evs) {
        return (evs || []).slice().sort((a, b) => {
            const ka = evKeyForSort(a);
            const kb = evKeyForSort(b);
            if (ka < kb) return 1;
            if (ka > kb) return -1;
            return 0;
        });
    }

    function mergeSlotEvents(evs, scope) {
        const map = new Map();
        const isDocenteView = (up(scope) === "DOCENTE");

        (evs || []).forEach(ev => {
            const type = normalizeType(ev);
            const title = normTxt(ev.title || ev.label || "");
            const badge = normTxt(ev.badge || "");
            const whoArr = uniq(toArrWho(ev.who || ev.sub)).map(normTxt);

            const isAbs = ["uscc", "uscf", "viag", "perm", "pb"].includes(String(type || "").trim().toLowerCase());
            const ignoreWhoForThis = (isDocenteView && !isAbs && type === "curr");
            const titleUp = String(title || ev.title || ev.label || "").trim().toUpperCase();
            const badgeUp = String(badge || ev.badge || "").trim().toUpperCase();

            const isConsiglioClasse =
                isDocenteView &&
                type === "imp" &&
                (
                    titleUp.includes("CONSIGLIO DI CLASSE") ||
                    badgeUp.includes("CONSIGLIO DI CLASSE") ||
                    titleUp.includes("COLLEGIO DOCENTI") ||
                    badgeUp.includes("COLLEGIO DOCENTI")
                );
            const key = (ignoreWhoForThis || isConsiglioClasse)
                ? `${type}|||${title}|||${badge}`
                : `${type}|||${title}|||${whoArr.join(",")}|||${badge}`;

            if (!map.has(key)) {
                const copy = Object.assign({}, ev);
                copy.type = type;
                copy.title = title || copy.title;
                copy.badge = badge || copy.badge;
                copy._whoList = uniq(whoArr);
                copy.who_usernames = uniqPreserve(toArrMaybe(ev.who_usernames));
                copy.who = copy._whoList.join("\n");
                copy.rooms = uniq(toArrMaybe(ev.rooms));
                copy.classi = uniq(toArrMaybe(ev.classi));
                map.set(key, copy);
            } else {
                const cur = map.get(key);
                cur.rooms = uniq((cur.rooms || []).concat(toArrMaybe(ev.rooms)));
                cur.classi = uniq((cur.classi || []).concat(toArrMaybe(ev.classi)));
                cur._whoList = uniq((cur._whoList || []).concat(whoArr));
                cur.who_usernames = uniqPreserve((cur.who_usernames || []).concat(toArrMaybe(ev.who_usernames)));
                cur.who = (cur._whoList || []).join("\n");
            }
        });

        return Array.from(map.values());
    }

    function slotBlockedSet(blockedMap, ymd, ora) {
        if (!blockedMap) return null;
        const key = `${ymd}|${ora}`;
        const arr = blockedMap[key];
        if (!arr || !arr.length) return null;
        return new Set(arr.map(x => String(x).trim()).filter(Boolean));
    }

    function eventIsOverriddenByBlockedClasses(ev, blockedSet) {
        if (!blockedSet || blockedSet.size === 0) return false;
        const cl = toArrMaybe(ev.classi);
        if (!cl.length) return false;
        return cl.some(c => blockedSet.has(String(c).trim()));
    }

    function normPersonName(s) {
        return normTxt(s).toUpperCase();
    }

    function isTeacherAbsenceType(t) {
        const x = String(t || "").trim().toLowerCase();
        return ["uscc", "uscf", "viag", "perm", "pb"].includes(x);
    }

    function isLessonLikeType(t) {
        const x = String(t || "").trim().toLowerCase();
        return ["curr", "udi", "imp", "pranzo", "studio", "sost"].includes(x);
    }

    function teacherAbsencePriority(t) {
        const x = String(t || "").trim().toLowerCase();
        const p = { viag: 100, uscf: 90, uscc: 80, perm: 60, pb: 50 };
        return p[x] || 0;
    }

    function buildTeacherAbsenceMapForSlot(evs, ora) {
        const map = new Map();

        (evs || []).forEach(ev => {
            const t = normalizeType(ev);
            if (!isTeacherAbsenceType(t)) return;

            const whoLines = uniq(
                (Array.isArray(ev._whoList) && ev._whoList.length)
                    ? ev._whoList
                    : toArrWho(ev.who || ev.sub || "")
            ).map(normTxt).filter(Boolean);

            if (!whoLines.length) return;

            const title = normTxt(ev.title || ev.label || "");
            const badge = normTxt(ev.badge || "");
            const reasonText = badge || title || "Assente";
            const pr = teacherAbsencePriority(t);

            whoLines.forEach(w => {
                const key = normPersonName(w);
                const cur = map.get(key);
                if (!cur || pr > cur.prio) {
                    map.set(key, {
                        type: t,
                        title,
                        badge,
                        reasonText,
                        ora,
                        prio: pr
                    });
                }
            });
        });

        return map;
    }

    function isEventFullyAbsentByTeacher(ev, teacherAbsMap) {
        const t = normalizeType(ev);

        if (isTeacherAbsenceType(t)) return false;
        if (!isLessonLikeType(t)) return false;

        const whoLines = uniq(
            (Array.isArray(ev._whoList) && ev._whoList.length)
                ? ev._whoList
                : toArrWho(ev.who || ev.sub || "")
        ).map(normTxt).filter(Boolean);

        if (!whoLines.length) return false;

        const absentCount = whoLines.filter(w => teacherAbsMap.has(normPersonName(w))).length;
        return absentCount === whoLines.length;
    }

    function loadTargetMem() {
        try {
            return JSON.parse(localStorage.getItem(LS_KEY_TARGET_BY_SCOPE) || "{}") || {};
        } catch (e) {
            return {};
        }
    }

    function saveTargetMem(mem) {
        try {
            localStorage.setItem(LS_KEY_TARGET_BY_SCOPE, JSON.stringify(mem || {}));
        } catch (e) { }
    }

    function getMemTarget(scope) {
        const s = up(scope);
        const mem = loadTargetMem();
        return (mem && mem[s]) ? String(mem[s]) : "";
    }

    function setMemTarget(scope, targetVal) {
        const s = up(scope);
        const v = String(targetVal || "").trim();
        if (!s) return;
        const mem = loadTargetMem();
        if (v) mem[s] = v;
        else delete mem[s];
        saveTargetMem(mem);
    }

    function notifyMsg(type, message) {
        if (!$.notify) return;
        $.notify({ message: message }, { type: type || "info", delay: 2500 });
    }

    function showLoading(msg) {
        $("#orario_content_mobile").html(
            `<div class="mobile-loading">${escapeHtml(msg || "Caricamento...")}</div>`
        );
    }

    function showError(msg) {
        $("#orario_content_mobile").html(
            `<div class="mobile-error alert alert-danger">${escapeHtml(msg || "Errore")}</div>`
        );
    }

    function showEmpty(msg) {
        $("#orario_content_mobile").html(
            `<div class="mobile-empty alert alert-info">${escapeHtml(msg || "Nessun elemento trovato")}</div>`
        );
    }

    function currentScope() {
        return up($("#v_scope_mobile").val() || "AULA");
    }

    function currentDate() {
        return clampIsoDate(state.selectedDate || todayIso());
    }
    state.selectedDate = clampIsoDate(todayIso());
    updateDayLabel();

    function currentTarget() {
        return String($("#v_target_mobile").val() || "").trim();
    }

    function isTargetScope(scope) {
        return ["AULA", "CLASSE", "DOCENTE"].includes(up(scope));
    }

    function isListScope(scope) {
        return ["EVENTI", "ASSENZE", "SOSTITUZIONI"].includes(up(scope));
    }

    function updateSearchVisibility() {
        const scope = currentScope();
        $("#mobile_event_sortbar").toggle(scope === "EVENTI");
        closeInlineTargetResults();
        updateSearchPlaceholder();
        if (scope === "EVENTI" || scope === "ASSENZE" || scope === "SOSTITUZIONI") {
            $("#search_block_mobile").show();
        } else {
            $("#search_block_mobile").show();
        }
    }

    function updateTargetBlockVisibility() {
        const scope = currentScope();
        if (isTargetScope(scope)) {
            $("#target_block_mobile").show();
        } else {
            $("#target_block_mobile").hide();
        }
        $("#target_block_mobile .mobile-label-row").toggle(!isTargetScope(scope));
    }

    function syncMobileScopeTabs() {
        const scope = currentScope();

        $(".mobile-scope-tab").each(function () {
            const $b = $(this);
            $b.toggleClass("is-active", String($b.data("scope") || "").toUpperCase() === scope);
        });
    }

    function updateDayLabel() {
        const d = currentDate();
        $("#mobile_day_label").text(isoToLongIt(d));
    }

    function updateTargetCard() {
        const scope = currentScope();
        const target = currentTarget();
        const label = $("#v_target_mobile option:selected").text() || "Seleziona...";
        $("#mobile_target_scope_label").text(scope);
        $("#mobile_target_label").text(target ? label : "Seleziona...");
    }

    function getCurrentTargetOptions() {
        return $("#v_target_mobile option").map(function () {
            const value = String($(this).attr("value") || "").trim();
            const label = String($(this).text() || "").trim();
            return { value, label };
        }).get().filter(x => x.value);
    }

    function targetScopeSingularLabel(scope) {
        scope = up(scope);
        if (scope === "AULA") return "aula";
        if (scope === "CLASSE") return "classe";
        if (scope === "DOCENTE") return "docente";
        return "elemento";
    }

    function updateSearchPlaceholder() {
        const scope = currentScope();
        if (isTargetScope(scope)) {
            $("#mobile_search_input").attr("placeholder", "Cerca " + targetScopeSingularLabel(scope) + "...");
        } else {
            $("#mobile_search_input").attr("placeholder", "Cerca...");
        }
    }

    function closeInlineTargetResults() {
        $("#mobile_inline_target_results").hide().empty();
    }

    function targetSearchMatches(item, query) {
        if (!query) return false;
        const q = normTxt(query).toLowerCase();
        const label = normTxt(item.label || "").toLowerCase();
        const value = normTxt(item.value || "").toLowerCase();
        return label.includes(q) || value.includes(q);
    }

    function renderTargetResultItems(containerSelector, q, itemClass) {
        const query = normTxt(q || "").toLowerCase();
        const current = currentTarget();
        const filtered = getCurrentTargetOptions().filter(it => {
            return query ? targetSearchMatches(it, query) : true;
        });

        if (!filtered.length) {
            $(containerSelector).html(`<div class="mobile-target-empty">Nessun risultato</div>`).show();
            return;
        }

        const visible = filtered.slice(0, 40);
        const html = visible.map(it => `
            <button type="button"
                    class="mobile-target-item ${itemClass || ""} ${it.value === current ? 'is-active' : ''}"
                    data-value="${escapeHtml(it.value)}">
                ${escapeHtml(it.label)}
            </button>
        `).join("");

        const more = filtered.length > visible.length
            ? `<div class="mobile-target-more">Mostrati ${visible.length} di ${filtered.length}: continua a digitare per restringere.</div>`
            : "";

        $(containerSelector).html(html + more).show();
    }

    function renderInlineTargetSearchResults() {
        const scope = currentScope();
        if (!isTargetScope(scope)) {
            closeInlineTargetResults();
            return;
        }

        const q = $("#mobile_search_input").val() || "";
        if (!normTxt(q)) {
            closeInlineTargetResults();
            return;
        }

        renderTargetResultItems("#mobile_inline_target_results", q, "mobile-inline-target-item");
    }

    function openTargetModal() {
        const scope = currentScope();
        if (!isTargetScope(scope)) return;

        $("#mobile_target_modal_scope").text(scope.toLowerCase());
        $("#mobile_target_search").val("");
        renderTargetModalList("");
        $("#mobile_target_modal").show();

        setTimeout(function () {
            $("#mobile_target_search").focus();
        }, 50);
    }

    function closeTargetModal() {
        $("#mobile_target_modal").hide();
    }

    function renderTargetModalList(q) {
        renderTargetResultItems("#mobile_target_list", q, "");
    }

    function selectTargetFromModal(value) {
        const v = String(value || "").trim();
        if (!v) return;

        $("#v_target_mobile").val(v);

        const scope = currentScope();
        if (scope && v) setMemTarget(scope, v);

        updateTargetCard();
        buildTitle();
        closeTargetModal();
        closeInlineTargetResults();
        $("#mobile_search_input").val("");
        loadCurrentView();
    }

    function jumpToOrarioMobile(scope, target) {
        const scopeUp = up(scope);
        const targetVal = String(target || "").trim();
        if (!isTargetScope(scopeUp) || !targetVal) return;

        pendingJumpTarget = targetVal;
        if (currentScope() === scopeUp) {
            applyPendingJumpTarget();
        } else {
            $("#v_scope_mobile").val(scopeUp).trigger("change");
        }
    }

    function applyPendingJumpTarget() {
        const targetVal = String(pendingJumpTarget || "").trim();
        if (!targetVal) return false;

        const $target = $("#v_target_mobile");
        const hasValue = $target.find("option").filter(function () {
            return String($(this).attr("value") || "").trim() === targetVal;
        }).length > 0;

        if (!hasValue) return false;

        pendingJumpTarget = "";
        $target.val(targetVal);
        setMemTarget(currentScope(), targetVal);
        updateTargetCard();
        buildTitle();
        $("#mobile_search_input").val("");
        closeInlineTargetResults();
        loadCurrentView();
        return true;
    }

    function buildTitle() {
        const scope = currentScope();
        const d = currentDate();
        if (scope === "EVENTI" || isTargetScope(scope)) {
            $("#orario_title_mobile").empty();
            return;
        }

        if (isTargetScope(scope)) {
            const lbl = $("#v_target_mobile option:selected").text() || "";
            $("#orario_title_mobile").html(
                `<div>${escapeHtml(scope)}</div><div>${escapeHtml(lbl)}</div><div>${escapeHtml(isoToLongIt(d))}</div>`
            );
        } else {
            $("#orario_title_mobile").html(
                `<div>${escapeHtml(scope)}</div><div>${escapeHtml(isoToLongIt(d))}</div>`
            );
        }
    }

    function shiftTarget(delta) {
        const scope = currentScope();
        if (!isTargetScope(scope)) return;
        const opts = $("#v_target_mobile option").map(function () {
            return $(this).attr("value");
        }).get().filter(Boolean);

        if (!opts.length) return;

        const cur = currentTarget();
        let idx = opts.indexOf(cur);
        if (idx < 0) idx = 0;
        idx += delta;

        if (idx < 0) idx = 0;
        if (idx >= opts.length) idx = opts.length - 1;

        $("#v_target_mobile").val(opts[idx]);
        setMemTarget(scope, opts[idx]);
        updateTargetCard();
        loadCurrentView();
    }

    function setDateAndReload(iso) {
        const d = clampIsoDate(iso);
        state.selectedDate = d;
        updateDayLabel();
        buildTitle();
        loadCurrentView();
    }

    function prevDayNav() {
        const d = currentDate();
        setDateAndReload(addSchoolDay(d, -1));
    }

    function nextDayNav() {
        const d = currentDate();
        setDateAndReload(addSchoolDay(d, +1));
    }

    function openTargetPicker() {
        const sel = document.getElementById("v_target_mobile");
        if (!sel) return;

        try {
            if (typeof sel.showPicker === "function") {
                sel.showPicker();
                return;
            }
        } catch (e) { }

        try {
            sel.focus();
            sel.click();
        } catch (e) { }
    }

    function openDatePicker() {
        const $p = $("#mobile_date_picker");
        if (!$p.length) return;

        $p.val(currentDate());

        const el = $p[0];

        try {
            if (typeof el.showPicker === "function") {
                el.showPicker();
                return;
            }
        } catch (e) { }

        try {
            el.focus();
            el.click();
        } catch (e) { }
    }

    function renderLegend() {
        if (isTargetScope(currentScope())) return "";

        return `
            <div class="mobile-legend">
                <span class="mobile-legend-item"><span class="mobile-legend-dot dot-curr"></span>Curricolare</span>
                <span class="mobile-legend-item"><span class="mobile-legend-dot dot-udi"></span>Udienza</span>
                <span class="mobile-legend-item"><span class="mobile-legend-dot dot-imp"></span>Impegno</span>
                <span class="mobile-legend-item"><span class="mobile-legend-dot dot-uscC"></span>Uscita comune</span>
                <span class="mobile-legend-item"><span class="mobile-legend-dot dot-uscF"></span>Uscita fuori comune</span>
                <span class="mobile-legend-item"><span class="mobile-legend-dot dot-viag"></span>Viaggio</span>
                <span class="mobile-legend-item"><span class="mobile-legend-dot dot-sost"></span>Sostituzione</span>
            </div>
        `;
    }

    function badgeText(ev) {
        return normTxt(ev.badge || "");
    }

    function roomsText(ev) {
        return uniq(toArrMaybe(ev.rooms)).join(", ");
    }

    function classiText(ev) {
        return uniq(toArrMaybe(ev.classi)).join(", ");
    }

    function whoText(ev) {
        return uniq(toArrWho(ev.who || ev.sub || "")).join(", ");
    }

    function jumpChipHtml(scope, target, label, kind) {
        const scopeUp = up(scope);
        const targetTxt = String(target || "").trim();
        const labelTxt = normTxt(label);
        if (!isTargetScope(scopeUp) || !targetTxt || !labelTxt) return "";

        return `<button type="button"
                    class="mobile-orario-jump mobile-orario-chip mobile-orario-chip-${escapeHtml(kind)}"
                    data-scope="${escapeHtml(scopeUp)}"
                    data-target="${escapeHtml(targetTxt)}"
                    title="Vai all'orario ${escapeHtml(scopeUp.toLowerCase())} ${escapeHtml(labelTxt)}">${escapeHtml(labelTxt)}</button>`;
    }

    function chipListHtml(values, kind) {
        const items = uniq(toArrMaybe(values)).map(normTxt).filter(Boolean);
        if (!items.length) return "";
        const scope = (kind === "room") ? "AULA" : (kind === "class" ? "CLASSE" : "");
        return items
            .map(v => scope
                ? jumpChipHtml(scope, v, v, kind)
                : `<span class="mobile-orario-chip mobile-orario-chip-${escapeHtml(kind)}">${escapeHtml(v)}</span>`)
            .join("");
    }

    function teacherChipHtml(name, username) {
        const n = normTxt(name);
        if (!n) return "";
        const target = String(username || "").trim();
        if (!target) return `<span class="mobile-orario-chip mobile-orario-chip-teacher is-static">${escapeHtml(n)}</span>`;
        return jumpChipHtml("DOCENTE", target, n, "teacher");
    }

    function sostituzioneHtml(ev) {
        const s = ev && ev.sostituzione ? ev.sostituzione : null;
        if (!s) return "";

        const sostituto = normTxt(s.sostituto || ev.who || ev.sub || "");
        const sostitutoUsername = String(s.sostituto_username || "").trim();
        const sostituito = normTxt(s.sostituito || ev.who_originale || "");
        const materia = normTxt(s.materia || ev.title || ev.label || "");

        return `
            <div class="mobile-sost-box">
                <div class="mobile-sost-label">Sostituzione</div>
                ${sostituto ? `<div class="mobile-sost-main">Sostituto: ${teacherChipHtml(sostituto, sostitutoUsername)}</div>` : ""}
                ${sostituito ? `<div class="mobile-sost-sub">Al posto di ${escapeHtml(sostituito)}</div>` : ""}
                ${materia ? `<div class="mobile-sost-sub">${escapeHtml(materia)}</div>` : ""}
            </div>
        `;
    }

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

    function eventCardHtml(ev, extraClasses, teacherAbsMap) {
        const type = normalizeType(ev);
        const isOwnAbsenceEvent = isTeacherAbsenceType(type);

        const cls = ["mobile-event-card", "ev-" + type].concat(extraClasses || []);
        if (ev && ev.sostituzione) {
            cls.push("ev-with-sost");
        }
        const badge = badgeText(ev);
        const badgeClass = (ev && ev.sostituzione) ? "sost" : type;
        const title = normTxt(ev.title || ev.label || "");
        const roomsHtml = chipListHtml(ev.rooms, "room");
        const classiHtml = chipListHtml(ev.classi, "class");
        const sostHtml = sostituzioneHtml(ev);

        const whoLines = uniq(
            (Array.isArray(ev._whoList) && ev._whoList.length)
                ? ev._whoList
                : toArrWho(ev.who || ev.sub || "")
        ).map(normTxt).filter(Boolean);
        const whoUsernames = uniqPreserve(toArrMaybe(ev.who_usernames));

        const absentMap = teacherAbsMap || new Map();

        // barra tutta la card solo per vere lezioni/eventi didattici,
        // NON per la card che rappresenta l'assenza stessa
        const fullAbsent = isOwnAbsenceEvent ? false : isEventFullyAbsentByTeacher(ev, absentMap);

        if (fullAbsent) {
            cls.push("ev-absent-full");
        }

        let whoHtml = "";
        if (whoLines.length) {
            whoHtml = `
            <div class="mobile-event-who">
                <strong>Docente/i:</strong><br>
                ${whoLines.map((w, idx) => {
                const username = whoUsernames[idx] || (whoLines.length === 1 ? whoUsernames[0] : "");
                // nella card di assenza NON devo ribarrare il docente
                if (isOwnAbsenceEvent) {
                    return `<div class="mobile-event-who-line">${teacherChipHtml(w, username)}</div>`;
                }

                const abs = absentMap.get(normPersonName(w));
                if (!abs) {
                    return `<div class="mobile-event-who-line">${teacherChipHtml(w, username)}</div>`;
                }

                return `
                        <div class="mobile-event-who-line is-absent">
                            ${teacherChipHtml(w, username)}
                            <span class="mobile-event-absence-note">(${escapeHtml(abs.reasonText || "Assente")})</span>
                        </div>
                    `;
            }).join("")}
            </div>
        `;
        }

        return `
        <div class="${escapeHtml(cls.join(" "))}">
            ${badge ? `<div class="mobile-badge mobile-badge-${escapeHtml(badgeClass)}">${escapeHtml(badge)}</div>` : ""}
            <div class="mobile-event-title">${escapeHtml(title)}</div>
            ${whoHtml}
            ${sostHtml}
            ${roomsHtml ? `<div class="mobile-event-rooms"><strong>Aula/e:</strong><br>${roomsHtml}</div>` : ""}
            ${classiHtml ? `<div class="mobile-event-classi"><strong>Classe/i:</strong><br>${classiHtml}</div>` : ""}
        </div>
    `;
    }

    function renderDayGrid(grid, blockedMap) {
        const dateIso = currentDate();
        let html = renderLegend();
        html += `<div class="mobile-slot-list">`;

        let totalEvents = 0;

        ORARI.forEach(ora => {
            const key = `${dateIso}|${ora}`;
            let evs = (grid[key] || []).slice();
            evs = filterAulaNonDisponibile(evs);

            evs = mergeSlotEvents(evs, currentScope());
            evs = stableSortSlotEvents(evs);

            totalEvents += evs.length;

            const blockedSet = slotBlockedSet(blockedMap, dateIso, ora);
            const teacherAbsMap = buildTeacherAbsenceMapForSlot(evs, ora);
            let body = "";

            if (!evs.length) {
                body = `<div class="mobile-slot-empty">Nessun evento in questo slot</div>`;
            } else {
                body = evs.map(ev => {
                    const extra = [];

                    if (!isBlocking(normalizeType(ev)) && eventIsOverriddenByBlockedClasses(ev, blockedSet)) {
                        extra.push("ev-overridden");
                    }

                    return eventCardHtml(ev, extra, teacherAbsMap);
                }).join("");
            }

            html += `
                <div class="mobile-slot">
                    <div class="mobile-slot-head">
                        <div class="mobile-slot-time">${escapeHtml(ora)}</div>
                        <div class="mobile-slot-count">${evs.length ? evs.length + " evento/i" : ""}</div>
                    </div>
                    <div class="mobile-slot-body">${body}</div>
                </div>
            `;
        });

        html += `</div>`;

        if (totalEvents === 0) {
            showEmpty("Nessun evento trovato per il giorno selezionato.");
            return;
        }

        $("#orario_content_mobile").html(html);
        if (!isTargetScope(currentScope())) {
            applySearchFilter();
        }
    }

    function renderSimpleList(items, scope) {
        const q = normTxt($("#mobile_search_input").val() || "").toLowerCase();

        let filtered = (items || []).filter(it => {
            if (!q) return true;
            const hay = Object.values(it).join(" ").toLowerCase();
            return hay.includes(q);
        });

        // ordinamento speciale solo per EVENTI
        if (scope === "EVENTI") {
            filtered = sortEventItemsForMobile(filtered, currentDate());
        }

        if (!filtered.length) {
            showEmpty("Nessun elemento trovato.");
            return;
        }

        let html = `<div class="mobile-filter-summary">${filtered.length} risultato/i</div>`;
        html += `<div class="mobile-list-grid">`;

        filtered.forEach(it => {
            if (scope === "ASSENZE") {
                const oraIn = (it.ora || it.oraInizio || "").toString().slice(0, 5);
                const oraOut = (it.oraFine || it.fine || "").toString().slice(0, 5);
                const docente = normTxt(it.docente || it.who || "");
                const tipo = normTxt(it.badge || it.type || "");
                const dettaglio = normTxt(it.detail || it.title || "");
                const classi = Array.isArray(it.classi) ? it.classi.join(", ") : normTxt(it.classi || "");

                html += `
                    <div class="mobile-list-card">
                        <div class="mobile-list-topline">
                            <div class="mobile-list-time">${escapeHtml(oraIn)}${oraOut ? " - " + escapeHtml(oraOut) : ""}</div>
                            ${tipo ? `<div class="mobile-badge">${escapeHtml(tipo)}</div>` : ""}
                        </div>
                        <div class="mobile-list-title">${escapeHtml(docente || "Assenza")}</div>
                        ${dettaglio ? `<div class="mobile-list-meta"><strong>Dettaglio:</strong> ${escapeHtml(dettaglio)}</div>` : ""}
                        ${classi ? `<div class="mobile-list-meta"><strong>Classe/i:</strong> ${escapeHtml(classi)}</div>` : ""}
                    </div>
                `;
            } else if (scope === "SOSTITUZIONI") {
                const data = normTxt(it.data || "");
                const oraIn = (it.ora || it.oraInizio || "").toString().slice(0, 5);
                const oraOut = (it.oraFine || "").toString().slice(0, 5);
                const sostituito = normTxt(it.docenteSostituito || "");
                const sostituto = normTxt(it.docenteSostituto || "");
                const materia = normTxt(it.materia || it.detail || "");
                const classe = normTxt(it.classe || "");
                const aula = normTxt(it.aula || "");

                html += `
                    <div class="mobile-list-card mobile-list-card-event ev-sost">
                        <div class="mobile-list-topline">
                            <div class="mobile-list-time">${escapeHtml(oraIn)}${oraOut ? " - " + escapeHtml(oraOut) : ""}</div>
                            ${data ? `<div class="mobile-list-date">${escapeHtml(isoToIt(data))}</div>` : ""}
                        </div>
                        <div class="mobile-badge mobile-badge-sost">Sostituzione</div>
                        <div class="mobile-list-title">${escapeHtml(materia || "Sostituzione")}</div>
                        ${sostituto ? `<div class="mobile-list-meta"><strong>Sostituto:</strong><br>${teacherChipHtml(sostituto)}</div>` : ""}
                        ${sostituito ? `<div class="mobile-list-meta"><strong>Al posto di:</strong> ${escapeHtml(sostituito)}</div>` : ""}
                        ${materia ? `<div class="mobile-list-meta"><strong>Materia:</strong> ${escapeHtml(materia)}</div>` : ""}
                        ${classe ? `<div class="mobile-list-meta"><strong>Classe:</strong><br>${chipListHtml(classe, "class")}</div>` : ""}
                        ${aula ? `<div class="mobile-list-meta"><strong>Aula:</strong><br>${chipListHtml(aula, "room")}</div>` : ""}
                    </div>
                `;
            } else {
                const oraIn = (it.ora || it.oraInizio || "").toString().slice(0, 5);
                const oraOut = (it.oraFine || it.fine || "").toString().slice(0, 5);
                const titolo = normTxt(it.title || it.detail || it.materia || "Evento");

                const whoArr = uniq(
                    toArrWho(it.who || it.docente || "")
                ).map(s => s.trim()).filter(Boolean);

                const whoHtml = whoArr.length
                    ? `<div class="mobile-list-meta"><strong>Docente/i:</strong><br>${whoArr.map(x => escapeHtml(x)).join("<br>")}</div>`
                    : "";

                const classe = Array.isArray(it.classi) ? it.classi.join(", ") : normTxt(it.classi || "");
                const aula = normTxt(it.aulaKey || "");
                const badge = normTxt(it.badge || "");
                const type = normalizeType(it);

                const timeState = getEventTimeStateForMobile(it, currentDate());
                const pastCls = (timeState === "past") ? " is-past" : "";

                html += `
    <div class="mobile-list-card mobile-list-card-event ev-${escapeHtml(type)}${pastCls}">
        <div class="mobile-event-main-row">
            <div class="mobile-event-main-left">
                <div class="mobile-list-time">${escapeHtml(oraIn)}${oraOut ? " - " + escapeHtml(oraOut) : ""}</div>
                <div class="mobile-list-title">${escapeHtml(titolo)}</div>
                ${whoHtml}
                ${classe ? `<div class="mobile-list-meta"><strong>Classe/i:</strong> ${escapeHtml(classe)}</div>` : ""}
            </div>

            ${(badge || aula) ? `
                <div class="mobile-event-main-right">
                    ${badge ? `<div class="mobile-badge mobile-badge-${escapeHtml(type)} mobile-badge-focus">${escapeHtml(badge)}</div>` : ""}
                    ${aula ? `
                        <div class="mobile-event-focus-aula-wrap">
                            <div class="mobile-event-focus-aula-label">Aula</div>
                            <div class="mobile-event-focus-aula">${escapeHtml(aula)}</div>
                        </div>
                    ` : ""}
                </div>
            ` : ""}
        </div>
    </div>
`;
            }
        });

        html += `</div>`;
        $("#orario_content_mobile").html(html);
        if (scope === "EVENTI") {
            syncEventSortButtons();
        }
    }

    function applySearchFilter() {
        const scope = currentScope();
        if (isTargetScope(scope)) {
            renderInlineTargetSearchResults();
            return;
        }

        if (isListScope(scope)) {
            renderSimpleList(state.listItems || [], scope);
            return;
        }

        const q = normTxt($("#mobile_search_input").val() || "").toLowerCase();
        if (!q) {
            $("#orario_content_mobile .mobile-slot").show();
            $("#orario_content_mobile .mobile-event-card").show();
            return;
        }

        $("#orario_content_mobile .mobile-slot").each(function () {
            let visibleCount = 0;
            $(this).find(".mobile-event-card").each(function () {
                const txt = $(this).text().toLowerCase();
                const ok = txt.includes(q);
                $(this).toggle(ok);
                if (ok) visibleCount++;
            });

            const hasNoEvents = $(this).find(".mobile-event-card").length === 0;
            if (hasNoEvents) {
                const slotText = $(this).text().toLowerCase();
                $(this).toggle(slotText.includes(q));
            } else {
                $(this).toggle(visibleCount > 0);
            }
        });
    }

    function fetchOptions() {
        const scope = currentScope();

        if (!isTargetScope(scope)) {
            state.options = [];
            $("#v_target_mobile").empty().append(`<option value="">(non usato)</option>`);
            updateTargetCard();
            closeInlineTargetResults();
            return $.Deferred().resolve().promise();
        }

        const dfd = $.Deferred();

        $.getJSON("orarioGetOptions.php", { scope }, function (r) {
            if (!r || r.ok !== true) {
                state.options = [];
                $("#v_target_mobile").empty().append(`<option value="">Seleziona...</option>`);
                updateTargetCard();
                dfd.reject((r && r.error) ? r.error : "Errore caricamento opzioni");
                return;
            }

            const items = Array.isArray(r.items) ? r.items : [];
            state.options = items;

            const $t = $("#v_target_mobile");
            $t.empty().append(`<option value="">Seleziona...</option>`);

            items.forEach(it => {
                const value = String(it.id || "").trim();      // <-- QUI era il bug
                const label = String(it.label || value).trim();

                if (!value) return;

                $t.append(`<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`);
            });

            const pending = String(pendingJumpTarget || "").trim();
            let chosen = "";
            if (pending && items.some(x => String(x.id || "").trim() === pending)) {
                chosen = pending;
                pendingJumpTarget = "";
            }
            if (!chosen) {
                chosen = getMemTarget(scope);
            }
            if (!chosen || !items.some(x => String(x.id || "").trim() === chosen)) {
                chosen = items.length ? String(items[0].id || "").trim() : "";
            }

            $t.val(chosen);
            if (chosen) setMemTarget(scope, chosen);

            updateTargetCard();
            renderInlineTargetSearchResults();
            dfd.resolve();
        }).fail(function (xhr) {
            dfd.reject((xhr && xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : "Errore server caricamento opzioni");
        });

        return dfd.promise();
    }

    function fetchAulaBlockedMap(scope, date, target) {
        const dfd = $.Deferred();

        if (scope !== "AULA") {
            dfd.resolve({ gridAssenze: {}, blockedMap: {} });
            return dfd.promise();
        }

        $.getJSON("orarioAulaBlocchi.php", {
            scope: scope,
            period: "GIORNO",
            date: date,
            target: target
        }, function (r) {
            if (!r || r.ok !== true) {
                dfd.resolve({ gridAssenze: {}, blockedMap: {} });
                return;
            }
            dfd.resolve({
                gridAssenze: r.gridAssenze || {},
                blockedMap: r.blockedMap || {}
            });
        }).fail(function () {
            dfd.resolve({ gridAssenze: {}, blockedMap: {} });
        });

        return dfd.promise();
    }

    function mergeGrids(gridBase, gridAdd) {
        const out = Object.assign({}, gridBase || {});
        Object.keys(gridAdd || {}).forEach(k => {
            if (!out[k]) out[k] = [];
            out[k] = out[k].concat(gridAdd[k] || []);
        });
        return out;
    }

    function loadGridScope() {
        const scope = currentScope();
        const date = currentDate();
        const target = currentTarget();

        if (!target) {
            showEmpty("Seleziona prima un elemento.");
            return;
        }

        showLoading("Caricamento orario...");

        $.getJSON("orarioRead.php", {
            scope: scope,
            period: "GIORNO",
            date: date,
            target: target
        }, function (r) {
            if (!r || r.ok !== true) {
                showError((r && r.error) ? r.error : "Errore lettura orario");
                return;
            }

            let grid = r.grid || {};

            fetchAulaBlockedMap(scope, date, target).done(function (extra) {
                grid = mergeGrids(grid, extra.gridAssenze || {});
                state.grid = grid;
                state.blockedMap = extra.blockedMap || {};
                renderDayGrid(state.grid, state.blockedMap);
            });
        }).fail(function (xhr) {
            showError((xhr && xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : "Errore server lettura orario");
        });
    }

    function loadAssenze() {
        const date = currentDate();
        showLoading("Caricamento assenze...");

        $.getJSON("assenzeRead.php", { date: date }, function (r) {
            if (!r || r.ok !== true) {
                showError((r && r.error) ? r.error : "Errore lettura assenze");
                return;
            }

            state.listItems = Array.isArray(r.items) ? r.items : [];
            renderSimpleList(state.listItems, "ASSENZE");
        }).fail(function (xhr) {
            showError((xhr && xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : "Errore server lettura assenze");
        });
    }

    function loadEventi() {
        const date = currentDate();
        showLoading("Caricamento eventi...");

        $.getJSON("eventiRead.php", { date: date }, function (r) {
            if (!r || r.ok !== true) {
                showError((r && r.error) ? r.error : "Errore lettura eventi");
                return;
            }

            state.listItems = (Array.isArray(r.items) ? r.items : []).map(it => {
                const oraIn = (it.ora || it.oraInizio || "").toString().slice(0, 5);
                const oraOut = (it.oraFine || it.fine || "").toString().slice(0, 5);
                const aula = Array.isArray(it.rooms)
                    ? it.rooms.join(", ").toUpperCase()
                    : normTxt(it.aula || it.rooms || "").toUpperCase();

                return Object.assign({}, it, {
                    oraIn: oraIn,
                    oraOut: oraOut,
                    aulaKey: aula
                });
            });

            renderSimpleList(state.listItems, "EVENTI");
        }).fail(function (xhr) {
            showError((xhr && xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : "Errore server lettura eventi");
        });
    }

    function loadSostituzioni() {
        const date = currentDate();
        showLoading("Caricamento sostituzioni...");

        $.getJSON("sostituzioniRead.php", { date: date, mode: "all_today" }, function (r) {
            if (!r || r.ok !== true) {
                showError((r && r.error) ? r.error : "Errore lettura sostituzioni");
                return;
            }

            if (window.ORARIO_IS_DOCENTE) {
                $.getJSON("sostituzioniTelegramStatus.php", {}, function (tr) {
                    window._sostituzioniTelegramStatus = tr || null;
                });
            }

            state.listItems = Array.isArray(r.items) ? r.items : [];
            renderSimpleList(state.listItems, "SOSTITUZIONI");
        }).fail(function (xhr) {
            showError((xhr && xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : "Errore server lettura sostituzioni");
        });
    }

    function loadCurrentView() {
        if (state.loading) return;
        state.loading = true;

        const scope = currentScope();
        state.currentScope = scope;

        updateDayLabel();
        updateTargetCard();
        buildTitle();
        updateTargetBlockVisibility();
        updateSearchVisibility();
        syncMobileScopeTabs();

        const done = function () { state.loading = false; };

        if (scope === "ASSENZE") {
            loadAssenze();
            done();
            return;
        }

        if (scope === "EVENTI") {
            loadEventi();
            done();
            return;
        }

        if (scope === "SOSTITUZIONI") {
            loadSostituzioni();
            done();
            return;
        }

        loadGridScope();
        done();
    }

    function bindSwipe(el, onLeft, onRight) {
        if (!el) return;

        let startX = 0;
        let startY = 0;
        let active = false;

        el.addEventListener("touchstart", function (e) {
            if (!e.touches || !e.touches.length) return;
            const t = e.touches[0];
            startX = t.clientX;
            startY = t.clientY;
            active = true;
        }, { passive: true });

        el.addEventListener("touchend", function (e) {
            if (!active || !e.changedTouches || !e.changedTouches.length) return;

            const t = e.changedTouches[0];
            const dx = t.clientX - startX;
            const dy = t.clientY - startY;

            active = false;

            if (Math.abs(dx) < 55) return;
            if (Math.abs(dx) <= Math.abs(dy)) return;

            if (dx < 0) {
                if (typeof onLeft === "function") onLeft();
            } else {
                if (typeof onRight === "function") onRight();
            }
        }, { passive: true });
    }

    function bindEvents() {
        $("#v_scope_mobile").on("change", function () {
            const scope = currentScope();
            try { localStorage.setItem(LS_KEY_SCOPE, scope); } catch (e) { }
            $("#mobile_search_input").val("");
            closeInlineTargetResults();
            updateTargetBlockVisibility();
            updateSearchVisibility();
            fetchOptions().done(function () {
                updateTargetCard();
                buildTitle();
                loadCurrentView();
            }).fail(function (err) {
                showError(err || "Errore caricamento opzioni");
            });
        });

        $(".mobile-scope-tab").on("click", function () {
            const scope = String($(this).data("scope") || "").toUpperCase();
            if (!scope) return;

            $("#v_scope_mobile").val(scope).trigger("change");
        });

        $("#v_target_mobile").on("change", function () {
            const scope = currentScope();
            const target = currentTarget();
            if (scope && target) setMemTarget(scope, target);
            updateTargetCard();
            buildTitle();
            loadCurrentView();
        });

        $("#btn_today_mobile").on("click", function () {
            setDateAndReload(todayIso());
        });

        $("#btn_prev_day_mobile").on("click", function () {
            prevDayNav();
        });

        $(document).off("click", ".mobile-event-sort-btn").on("click", ".mobile-event-sort-btn", function () {
            const mode = String($(this).data("sort") || "").toUpperCase();
            setEventSortMode(mode);
            renderSimpleList(state.listItems || [], "EVENTI");
        });

        $("#btn_next_day_mobile").on("click", function () {
            nextDayNav();
        });

        $("#btn_prev_target_mobile").on("click", function () {
            shiftTarget(-1);
        });

        $("#btn_next_target_mobile").on("click", function () {
            shiftTarget(+1);
        });

        $("#btn_open_target_sheet").on("click", function () {
            openTargetModal();
        });

        $("#mobile_target_card").on("click", function (e) {
            if ($(e.target).closest("#btn_prev_target_mobile, #btn_next_target_mobile").length) {
                return;
            }
            openTargetModal();
        });

        $("#btn_close_target_modal").on("click", function () {
            closeTargetModal();
        });

        $("#mobile_target_modal").on("click", function (e) {
            if (e.target === this) {
                closeTargetModal();
            }
        });

        $("#mobile_target_search").on("input", function () {
            renderTargetModalList($(this).val() || "");
        });

        $(document).on("click", ".mobile-target-item", function () {
            const value = String($(this).data("value") || "").trim();
            selectTargetFromModal(value);
        });

        $(document).off("click", ".mobile-orario-jump").on("click", ".mobile-orario-jump", function (e) {
            e.preventDefault();
            e.stopPropagation();
            jumpToOrarioMobile($(this).data("scope"), $(this).data("target"));
        });

        $("#mobile_day_card").on("click", function (e) {
            if ($(e.target).closest("#btn_prev_day_mobile, #btn_next_day_mobile").length) {
                return;
            }
            openDatePicker();
        });

        $("#mobile_date_picker").on("change", function () {
            const v = String($(this).val() || "").trim();
            if (!v) return;
            setDateAndReload(v);
        });

        $("#mobile_search_input").on("input", function () {
            if (isTargetScope(currentScope())) {
                renderInlineTargetSearchResults();
            } else {
                applySearchFilter();
            }
        });

        $("#mobile_search_input").on("focus", function () {
            if (isTargetScope(currentScope())) {
                renderInlineTargetSearchResults();
            }
        });

        $(document).on("click", function (e) {
            if ($(e.target).closest("#search_block_mobile").length) return;
            closeInlineTargetResults();
        });

        bindSwipe(document.getElementById("mobile_day_card"), nextDayNav, prevDayNav);
        bindSwipe(document.getElementById("orario_content_mobile"), nextDayNav, prevDayNav);
        bindSwipe(document.getElementById("mobile_target_card"), function () { shiftTarget(+1); }, function () { shiftTarget(-1); });
    }

    function initDefaults() {
        let scope = "EVENTI";

        try {
            scope = localStorage.getItem(LS_KEY_SCOPE) || "EVENTI";
        } catch (e) { }

        scope = up(scope);

        const allowed = ["AULA", "CLASSE", "DOCENTE", "EVENTI"].concat(
            window.ORARIO_IS_PUBLIC ? [] : ["ASSENZE", "SOSTITUZIONI"]
        );

        if (!allowed.includes(scope)) scope = "EVENTI";

        $("#v_scope_mobile").val(scope);
        state.selectedDate = clampIsoDate(todayIso());
        updateDayLabel();
        updateTargetBlockVisibility();
        updateSearchVisibility();
    }

    $(function () {
        initDefaults();
        bindEvents();

        fetchOptions().done(function () {
            updateTargetCard();
            buildTitle();
            loadCurrentView();
        }).fail(function (err) {
            showError(err || "Errore inizializzazione orario mobile");
        });
    });

})();
