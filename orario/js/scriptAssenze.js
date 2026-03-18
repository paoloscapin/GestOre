/**
 * ============================================================================
 *  ASSENZE UI - LISTA ASSENZE DOCENTI
 * ============================================================================
 *  File separato per la sola vista ASSENZE.
 *  Espone globalmente:
 *    - window.loadAssenze(dateIso)
 *    - window.renderAssenzeList(items, dateIso)
 * ============================================================================
 */

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

    function typeLabelFromType(t) {
        const m = {
            pb: "Permesso breve",
            perm: "Permesso giornata",
            mal: "Malattia",
            lutto: "Lutto",
            uscC: "Uscita nel comune",
            uscF: "Uscita fuori comune",
            viag: "Viaggio di istruzione",
            altro: "Assenza",
            ass: "Assenza"
        };
        return m[t] || (t || "");
    }

    function typeLabel(it) {
        const badge = (it && it.badge ? String(it.badge).trim() : "");
        if (badge) return badge;
        return typeLabelFromType((it && it.type) ? it.type : "");
    }

    function renderAssenzeList(items, dateIso) {
        const $c = $("#orario_content");

        const normItems = (items || []).map(it => {
            const oraIn = (it.ora || it.oraInizio || "").toString().slice(0, 5);
            const oraOut = (it.oraFine || it.fine || "").toString().slice(0, 5);
            const docente = (it.docente || it.who || "").toString().trim();
            const detail = (it.detail || it.title || "").toString().trim();

            const classiArr = Array.isArray(it.classi)
                ? it.classi
                : (it.classi ? String(it.classi).split(",").map(x => x.trim()).filter(Boolean) : []);

            return Object.assign({}, it, {
                oraIn,
                oraOut,
                docenteKey: docente,
                detailKey: detail,
                classiArr,
                classiKey: classiArr.join(", ")
            });
        });

        const topbar = `
    <div class="eventi-topbar" style="display:flex;gap:10px;align-items:center;margin:6px 0 10px 0;flex-wrap:wrap;">
      <input id="ass_q" class="form-control input-sm" style="max-width:420px;" placeholder="Cerca docente, classe o dettaglio...">
      <div style="opacity:.75;font-size:14px;">${escapeHtml(isoToIt(dateIso))} · ${normItems.length} assenze</div>
    </div>
  `;

        const table = `
    <div class="table-responsive">
      <table class="table table-bordered table-hover" id="tbl_assenze"
             style="background:#fff;table-layout:auto;width:100%;">
        <thead>
          <tr>
            <th class="th-sort" data-key="oraIn" style="width:90px;cursor:pointer;white-space:nowrap;text-align:center;">Inizio <span class="sort-ind"></span></th>
            <th class="th-sort" data-key="oraOut" style="width:90px;cursor:pointer;white-space:nowrap;text-align:center;">Fine <span class="sort-ind"></span></th>
            <th class="th-sort" data-key="docente" style="width:240px;cursor:pointer;">Docente <span class="sort-ind"></span></th>
            <th class="th-sort" data-key="tipo" style="width:180px;cursor:pointer;white-space:nowrap;">Tipo <span class="sort-ind"></span></th>
            <th class="th-sort" data-key="dett" style="cursor:pointer;">Dettaglio <span class="sort-ind"></span></th>
          </tr>
        </thead>
        <tbody id="assenze_tbody"></tbody>
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
            if (key === "docente") return (it.docenteKey || "");
            if (key === "tipo") return (typeLabel(it) || "");
            if (key === "dett") return (it.detailKey || "");
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

                return (a.docenteKey || "").localeCompare(b.docenteKey || "") * mul;
            });
        }

        function matchesQ(it, q) {
            if (!q) return true;

            const hay = [
                it.oraIn,
                it.oraOut,
                it.docenteKey,
                typeLabel(it),
                it.detailKey,
                it.classiKey
            ].join(" ").toLowerCase();

            return hay.includes(q);
        }

        function rowHtml(it) {
            const oraIn = escapeHtml(it.oraIn || "");
            const oraOut = escapeHtml(it.oraOut || "");
            const docente = escapeHtml(it.docenteKey || "").replace(/\n/g, "<br>");
            const type = (it.type || "ass").toString().trim();
            const tipo = escapeHtml(typeLabel(it));
            const dettaglio = escapeHtml(it.detailKey || "");
            const classi = escapeHtml(it.classiKey || "");

            const showClassi = ["uscC", "uscF", "viag"].includes(type);
            const classiHtml = (showClassi && classi)
                ? `<div class="ass-classi">Classe/i: ${classi}</div>`
                : "";

            return `
      <tr class="ev-row ev-row-${type}" data-orain="${oraIn}" data-oraout="${oraOut}">
        <td style="font-weight:800;white-space:nowrap;text-align:center;vertical-align:middle;">
          ${oraIn}
        </td>

        <td style="white-space:nowrap;text-align:center;vertical-align:middle;">
          ${oraOut}
        </td>

        <td style="vertical-align:middle;">
          <div style="
            white-space:normal;
            word-break:break-word;
            overflow-wrap:anywhere;
            line-height:1.25;
          ">
            ${docente}
          </div>
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
            font-weight:700;
            white-space:normal;
            line-height:1.3;
            word-break:break-word;
            overflow-wrap:anywhere;
          ">
            ${dettaglio}
            ${classiHtml}
          </div>
        </td>
      </tr>
    `;
        }

        function updateSortIndicators() {
            $("#tbl_assenze thead th.th-sort").each(function () {
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
            const q = norm($("#ass_q").val());
            const filtered = normItems.filter(it => matchesQ(it, q));
            const sorted = stableSort(filtered, sortState.key, sortState.dir);

            const $tb = $("#assenze_tbody");

            if (!sorted.length) {
                $tb.html(`<tr><td colspan="5"><div class="alert alert-info" style="margin:0;">Nessuna assenza trovata.</div></td></tr>`);
                updateSortIndicators();
                return;
            }

            $tb.html(sorted.map(rowHtml).join(""));
            updateSortIndicators();
        }

        $("#tbl_assenze thead")
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

        $("#ass_q").off("input").on("input", paint);

        paint();
    }

    function loadAssenze(dateIso) {
        if (!dateIso) {
            showInlineMsg("warning", "Seleziona una data.");
            return;
        }

        $("#orario_title").text(`Assenze docenti · ${isoToIt(dateIso)}`);
        showInlineMsg("info", "Caricamento assenze...");

        $.getJSON("assenzeRead.php", { date: dateIso }, function (r) {
            if (!r || r.ok !== true) {
                showInlineMsg("danger", (r && r.error) ? r.error : "Errore lettura assenze");
                return;
            }

            const items = r.items || [];
            renderAssenzeList(items, dateIso);

        }).fail(function (xhr) {
            console.error("[ASSENZE] Errore server assenzeRead.php", xhr && xhr.status, xhr && xhr.responseText);
            showInlineMsg("danger", "Errore server lettura assenze");
        });
    }

    window.renderAssenzeList = renderAssenzeList;
    window.loadAssenze = loadAssenze;

})();