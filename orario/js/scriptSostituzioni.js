(function () {
    "use strict";

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

    function showInlineMsg(type, msg) {
        const cls = (type === "danger") ? "alert-danger"
            : (type === "warning") ? "alert-warning"
                : (type === "success") ? "alert-success"
                    : "alert-info";

        $("#orario_content").html(
            `<div class="alert ${cls}" style="margin:10px 0;">${escapeHtml(msg)}</div>`
        );
    }

    function renderSostituzioniList(items, dateIso) {
        const $c = $("#orario_content");

        const normItems = (items || []).map(it => {
            const oraIn = (it.ora || it.oraInizio || "").toString().slice(0, 5);
            const oraOut = (it.oraFine || "").toString().slice(0, 5);

            const docenteSostituito = (it.docenteSostituito || "").toString().trim();
            const docenteSostituto = (it.docenteSostituto || "").toString().trim();
            const materia = (it.materia || it.detail || "").toString().trim();
            const classe = (it.classe || "").toString().trim();
            const aula = (it.aula || "").toString().trim();

            return Object.assign({}, it, {
                oraIn,
                oraOut,
                docenteSostituitoKey: docenteSostituito,
                docenteSostitutoKey: docenteSostituto,
                materiaKey: materia,
                classeKey: classe,
                aulaKey: aula
            });
        });

        const topbar = `
            <div class="eventi-topbar" style="display:flex;gap:10px;align-items:center;margin:6px 0 10px 0;flex-wrap:wrap;">
                <input id="sost_q" class="form-control input-sm" style="max-width:420px;" placeholder="Cerca docente, classe, aula o materia...">
                <div style="opacity:.75;font-size:14px;">${escapeHtml(isoToIt(dateIso))} · ${normItems.length} sostituzioni</div>
            </div>
        `;

        const table = `
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="tbl_sostituzioni"
                       style="background:#fff;table-layout:auto;width:100%;">
                    <thead>
                        <tr>
                            <th class="th-sort" data-key="oraIn" style="width:90px;cursor:pointer;white-space:nowrap;text-align:center;">Inizio <span class="sort-ind"></span></th>
                            <th class="th-sort" data-key="oraOut" style="width:90px;cursor:pointer;white-space:nowrap;text-align:center;">Fine <span class="sort-ind"></span></th>
                            <th class="th-sort" data-key="docenteSostituito" style="cursor:pointer;">Docente sostituito <span class="sort-ind"></span></th>
                            <th class="th-sort" data-key="docenteSostituto" style="cursor:pointer;">Docente sostituto <span class="sort-ind"></span></th>
                            <th class="th-sort" data-key="classe" style="width:100px;cursor:pointer;text-align:center;">Classe <span class="sort-ind"></span></th>
                            <th class="th-sort" data-key="aula" style="width:100px;cursor:pointer;text-align:center;">Aula <span class="sort-ind"></span></th>
                            <th class="th-sort" data-key="materia" style="cursor:pointer;">Materia <span class="sort-ind"></span></th>
                        </tr>
                    </thead>
                    <tbody id="sostituzioni_tbody"></tbody>
                </table>
            </div>
        `;

        $c.html(topbar + table);

        let sortState = { key: "oraIn", dir: "asc" };

        function norm(s) {
            return String(s ?? "").toLowerCase();
        }

        function getSortVal(it, key) {
            if (key === "oraIn") return (it.oraIn || "");
            if (key === "oraOut") return (it.oraOut || "");
            if (key === "docenteSostituito") return (it.docenteSostituitoKey || "");
            if (key === "docenteSostituto") return (it.docenteSostitutoKey || "");
            if (key === "classe") return (it.classeKey || "");
            if (key === "aula") return (it.aulaKey || "");
            if (key === "materia") return (it.materiaKey || "");
            return "";
        }

        function stableSort(arr, key, dir) {
            const mul = (dir === "desc") ? -1 : 1;

            return arr.slice().sort((a, b) => {
                const va = getSortVal(a, key).toString();
                const vb = getSortVal(b, key).toString();

                let c = va.localeCompare(vb);
                if (c !== 0) return c * mul;

                c = (a.oraIn || "").localeCompare(b.oraIn || "");
                if (c !== 0) return c * mul;

                return (a.docenteSostituitoKey || "").localeCompare(b.docenteSostituitoKey || "") * mul;
            });
        }

        function matchesQ(it, q) {
            if (!q) return true;

            const hay = [
                it.oraIn,
                it.oraOut,
                it.docenteSostituitoKey,
                it.docenteSostitutoKey,
                it.classeKey,
                it.aulaKey,
                it.materiaKey
            ].join(" ").toLowerCase();

            return hay.includes(q);
        }

        function rowHtml(it) {
            return `
                <tr class="ev-row ev-row-sost">
                    <td style="font-weight:800;white-space:nowrap;text-align:center;vertical-align:middle;">
                        ${escapeHtml(it.oraIn || "")}
                    </td>
                    <td style="white-space:nowrap;text-align:center;vertical-align:middle;">
                        ${escapeHtml(it.oraOut || "")}
                    </td>
                    <td style="vertical-align:middle;">${escapeHtml(it.docenteSostituitoKey || "")}</td>
                    <td style="vertical-align:middle;">${escapeHtml(it.docenteSostitutoKey || "")}</td>
                    <td style="text-align:center;vertical-align:middle;">${escapeHtml(it.classeKey || "")}</td>
                    <td style="text-align:center;vertical-align:middle;">${escapeHtml(it.aulaKey || "")}</td>
                    <td style="vertical-align:middle;">${escapeHtml(it.materiaKey || "")}</td>
                </tr>
            `;
        }

        function updateSortIndicators() {
            $("#tbl_sostituzioni thead th.th-sort").each(function () {
                const $th = $(this);
                const k = $th.data("key");
                const $ind = $th.find(".sort-ind");

                if (k === sortState.key) {
                    $ind.html(sortState.dir === "asc" ? "▲" : "▼");
                } else {
                    $ind.html("");
                }
            });
        }

        function paint() {
            const q = norm($("#sost_q").val());
            const filtered = normItems.filter(it => matchesQ(it, q));
            const sorted = stableSort(filtered, sortState.key, sortState.dir);

            const $tb = $("#sostituzioni_tbody");

            if (!sorted.length) {
                $tb.html(`<tr><td colspan="7"><div class="alert alert-info" style="margin:0;">Nessuna sostituzione trovata.</div></td></tr>`);
                updateSortIndicators();
                return;
            }

            $tb.html(sorted.map(rowHtml).join(""));
            updateSortIndicators();
        }

        $("#tbl_sostituzioni thead")
            .off("click", "th.th-sort")
            .on("click", "th.th-sort", function () {
                const key = ($(this).data("key") || "").toString();
                if (!key) return;

                if (sortState.key === key) {
                    sortState.dir = (sortState.dir === "asc") ? "desc" : "asc";
                } else {
                    sortState.key = key;
                    sortState.dir = "asc";
                }

                paint();
            });

        $("#sost_q").off("input").on("input", paint);

        paint();
    }

    function loadSostituzioni(dateIso) {
        if (!dateIso) {
            showInlineMsg("warning", "Seleziona una data.");
            return;
        }

        $("#orario_title").text(`Sostituzioni docenti · ${isoToIt(dateIso)}`);
        showInlineMsg("info", "Caricamento sostituzioni...");

        $.getJSON("sostituzioniRead.php", { date: dateIso }, function (r) {
            if (!r || r.ok !== true) {
                showInlineMsg("danger", (r && r.error) ? r.error : "Errore lettura sostituzioni");
                return;
            }

            const items = r.items || [];
            renderSostituzioniList(items, dateIso);

        }).fail(function (xhr) {
            console.error("[SOSTITUZIONI] Errore server sostituzioniRead.php", xhr && xhr.status, xhr && xhr.responseText);
            showInlineMsg("danger", "Errore server lettura sostituzioni");
        });
    }

    window.renderSostituzioniList = renderSostituzioniList;
    window.loadSostituzioni = loadSostituzioni;

})();