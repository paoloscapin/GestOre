let ferieDashboardChart = null;

function fdSafeRefresh(sel) {
    if ($.fn.selectpicker && $(sel).length) {
        $(sel).selectpicker("refresh");
    }
}

function fdFmtDateIt(iso) {
    if (!iso) return "";
    const p = iso.split("-");
    if (p.length !== 3) return iso;
    return `${p[2]}/${p[1]}/${p[0]}`;
}

function ferieDashboardOpenDay(iso) {
    $.ajax({
        url: "ferieDashboardDayRead.php",
        method: "GET",
        dataType: "json",
        data: {
            data: iso,
            finestra: $("#fd_finestra").val(),
            mode: $("#fd_mode").val()
        },
        success: function (r) {
            if (!r || r.ok !== true) {
                $.notify({ message: (r && r.error) ? r.error : "Errore dettaglio giorno" }, { type: "danger" });
                return;
            }

            $("#fdd_date").text(fdFmtDateIt(r.data));
            $("#fdd_total").text(r.totale || 0);

            let html = "";
            const uffici = r.uffici || {};
            const names = Object.keys(uffici);

            if (!names.length) {
                html = '<div class="alert alert-info">Nessuna persona in ferie per il filtro corrente.</div>';
            } else {
                names.forEach(ufficio => {
                    html += `
            <div class="panel panel-default">
              <div class="panel-heading">
                <strong>${ufficio}</strong> <span class="badge">${uffici[ufficio].length}</span>
              </div>
              <div class="panel-body" style="padding:0;">
                <table class="table table-bordered table-condensed" style="margin-bottom:0;">
                  <thead>
                    <tr>
                      <th>Nome</th>
                      <th>Profilo</th>
                      <th>Stato giorno</th>
                    </tr>
                  </thead>
                  <tbody>`;
                    uffici[ufficio].forEach(p => {
                        html += `
              <tr>
                <td>${p.nome}</td>
                <td>${p.profilo || ""}</td>
                <td>${p.stato_giorno || ""}</td>
              </tr>`;
                    });
                    html += `
                  </tbody>
                </table>
              </div>
            </div>`;
                });
            }

            $("#fdd_content").html(html);
            $("#ferieDayModal").modal("show");
        },
        error: function (xhr) {
            console.error("ferieDashboardDayRead ERROR", xhr.responseText);
            $.notify({ message: "Errore lettura dettaglio giorno" }, { type: "danger" });
        }
    });
}

function fdHeatColor(value, max) {
    if (!value || value <= 0 || max <= 0) return "#ffffff";

    const ratio = value / max;

    if (ratio >= 0.85) return "#1d4ed8";  // picco
    if (ratio >= 0.60) return "#3b82f6";  // alto
    if (ratio >= 0.30) return "#93c5fd";  // medio
    return "#dbeafe";                     // basso
}

function fdFmtMode(mode) {
    if (mode === "APPROVATI_ONLY") return "Solo approvati";
    if (mode === "RICHIESTI_ONLY") return "Solo richiesti";
    return "Approvati + richiesti";
}

function fdBuildSummary(data) {
    const s = data.summary || {};
    const f = data.finestra || {};

    $("#fd_meta").html(
        `<strong>${f.codice || "-"}</strong> · periodo ${f.data_inizio_fmt || "-"} - ${f.data_fine_fmt || "-"} · vista ${fdFmtMode(data.mode || "")}`
    );

    $("#fd_summary").html(`
    <div class="summary-card">
      <div class="k">Periodo</div>
<div class="v" style="font-size:20px;">${f.data_inizio_fmt || "-"} → ${f.data_fine_fmt || "-"}</div>        </div>
    <div class="summary-card">
      <div class="k">Picco giornaliero</div>
      <div class="v">${s.picco_giornaliero || 0}</div>
      <div class="s">${s.data_picco_fmt || ""}</div>
    </div>
    <div class="summary-card">
      <div class="k">Totale giorni/persona</div>
      <div class="v">${s.totale_giorni_persona || 0}</div>
    </div>
    <div class="summary-card">
      <div class="k">Uffici</div>
      <div class="v">${s.uffici_presenti || 0}</div>
    </div>
  `);
}

function fdColorPalette(n) {
    const base = [
        "#1d4ed8", "#059669", "#dc2626", "#7c3aed", "#ea580c",
        "#0891b2", "#65a30d", "#be123c"
    ];
    const out = [];
    for (let i = 0; i < n; i++) out.push(base[i % base.length]);
    return out;
}

function fdRenderChart(data) {
    const canvas = document.getElementById("ferieChart");
    if (!canvas) return;

    if (typeof Chart === "undefined") {
        $("#ferieChartWrap").html('<div class="empty-box">Chart.js non disponibile.</div>');
        return;
    }

    const labelsIso = data.labels || [];
    const labelsFmt = data.labels_fmt || [];
    const series = Array.isArray(data.series) ? data.series : [];
    const colors = fdColorPalette(series.length);
    const closedDays = data.closed_days || [];
    const closedDayReasons = data.closed_day_reasons || {};
    const totalDays = labelsIso.length;

    const datasets = series.map((s, idx) => ({
        label: s.label,
        data: s.data || [],
        backgroundColor: colors[idx],
        borderColor: colors[idx],
        borderWidth: 1,
        stack: "ferie",
        barPercentage: totalDays <= 20 ? 0.55 : 0.95,
        categoryPercentage: totalDays <= 20 ? 0.7 : 1.0,
        maxBarThickness: totalDays <= 20 ? 36 : 18
    }));

    /* dataset invisibile per rendere "hoverabili" i giorni chiusi */
    datasets.push({
        label: "__closed_days__",
        data: labelsIso.map(iso => closedDays.includes(iso) ? 0.001 : null),
        backgroundColor: "rgba(0,0,0,0)",
        borderColor: "rgba(0,0,0,0)",
        hoverBackgroundColor: "rgba(0,0,0,0)",
        hoverBorderColor: "rgba(0,0,0,0)",
        borderWidth: 0,
        stack: "ferie",
        barPercentage: totalDays <= 20 ? 0.55 : 0.95,
        categoryPercentage: totalDays <= 20 ? 0.7 : 1.0,
        maxBarThickness: totalDays <= 20 ? 36 : 18
    });

    const monthBandsPlugin = {
        id: "monthBandsPlugin",
        beforeDraw(chart) {
            const { ctx, chartArea, scales } = chart;
            if (!chartArea || !scales.x) return;

            const xScale = scales.x;
            const top = chartArea.top;
            const bottom = chartArea.bottom;

            const closedDays = data.closed_days || [];

            // --- 1) SFONDO MESE ---
            const monthRanges = [];
            let startIdx = 0;

            for (let i = 1; i <= labelsIso.length; i++) {
                const prevMonth = labelsIso[i - 1]?.split("-")[1];
                const curMonth = labelsIso[i]?.split("-")[1];

                if (i === labelsIso.length || curMonth !== prevMonth) {
                    monthRanges.push({
                        month: prevMonth,
                        start: startIdx,
                        end: i - 1
                    });
                    startIdx = i;
                }
            }

            const bandColors = [
                "rgba(59, 130, 246, 0.10)",
                "rgba(16, 185, 129, 0.10)",
                "rgba(245, 158, 11, 0.10)"
            ];

            ctx.save();

            monthRanges.forEach((r, idx) => {
                const xStart = xScale.getPixelForValue(r.start) - (xScale.getPixelForValue(r.start + 1) - xScale.getPixelForValue(r.start)) / 2;
                const xEnd = xScale.getPixelForValue(r.end) + (xScale.getPixelForValue(r.end) - xScale.getPixelForValue(Math.max(r.end - 1, 0))) / 2;

                ctx.fillStyle = bandColors[idx % bandColors.length];
                ctx.fillRect(xStart, top, xEnd - xStart, bottom - top);
            });

            // --- 2) GIORNI CHIUSI (SOPRA LE BANDE) ---
            closedDays.forEach((iso) => {
                const idx = labelsIso.indexOf(iso);
                if (idx === -1) return;

                const xCenter = xScale.getPixelForValue(idx);
                const width = (xScale.getPixelForValue(idx + 1) - xScale.getPixelForValue(idx)) || 10;

                ctx.fillStyle = "rgba(80, 80, 80, 0.25)"; // grigio evidente
                ctx.fillRect(xCenter - width / 2, top, width, bottom - top);
            });

            ctx.restore();
        }
    };

    const monthLabelsPlugin = {
        id: "monthLabelsPlugin",
        afterDraw(chart) {
            const { ctx, chartArea, scales } = chart;
            if (!chartArea || !scales.x) return;

            const xScale = scales.x;
            const monthNames = {
                "06": "Giugno",
                "07": "Luglio",
                "08": "Agosto",
                "09": "Settembre",
                "10": "Ottobre",
                "11": "Novembre",
                "12": "Dicembre",
                "01": "Gennaio",
                "02": "Febbraio",
                "03": "Marzo",
                "04": "Aprile",
                "05": "Maggio"
            };

            const monthRanges = [];
            let startIdx = 0;

            for (let i = 1; i <= labelsIso.length; i++) {
                const prevMonth = labelsIso[i - 1] ? labelsIso[i - 1].split("-")[1] : null;
                const curMonth = labelsIso[i] ? labelsIso[i].split("-")[1] : null;

                if (i === labelsIso.length || curMonth !== prevMonth) {
                    monthRanges.push({
                        month: prevMonth,
                        start: startIdx,
                        end: i - 1
                    });
                    startIdx = i;
                }
            }

            ctx.save();
            ctx.fillStyle = "#4b5563";
            ctx.font = "600 12px sans-serif";
            ctx.textAlign = "center";
            ctx.textBaseline = "top";

            monthRanges.forEach((r) => {
                const x1 = xScale.getPixelForValue(r.start);
                const x2 = xScale.getPixelForValue(r.end);
                const centerX = (x1 + x2) / 2;
                const y = chartArea.bottom + 40;

                ctx.fillText(monthNames[r.month] || r.month, centerX, y);
            });

            ctx.restore();
        }
    };

    if (ferieDashboardChart) {
        ferieDashboardChart.destroy();
    }

    ferieDashboardChart = new Chart(canvas.getContext("2d"), {
        type: "bar",
        data: {
            labels: labelsIso,
            datasets
        },
        plugins: [monthBandsPlugin, monthLabelsPlugin],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: {
                    top: 8,
                    right: 12,
                    bottom: 54,
                    left: 8
                }
            },
            interaction: {
                mode: "index",
                intersect: false
            },
            onClick: function (evt, elements) {
                if (!elements || !elements.length) return;
                const idx = elements[0].index;
                const iso = labelsIso[idx];
                if (iso) ferieDashboardOpenDay(iso);
            },
            plugins: {
                legend: {
                    position: "top",
                    align: "center",
                    labels: {
                        boxWidth: 18,
                        padding: 16,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        title: function (items) {
                            if (!items || !items.length) return "";
                            const idx = items[0].dataIndex;
                            return labelsFmt[idx] || labelsIso[idx] || "";
                        },
                        label: function (context) {
                            const idx = context.dataIndex;
                            const iso = labelsIso[idx];
                            const dsLabel = context.dataset.label || "";

                            if (closedDays.includes(iso)) {
                                return null;
                            }

                            if (dsLabel === "__closed_days__") {
                                return null;
                            }

                            const value = Number(context.raw || 0);
                            if (value <= 0) return null;

                            return dsLabel + ": " + value;
                        },
                        footer: function (items) {
                            if (!items || !items.length) return "";

                            const idx = items[0].dataIndex;
                            const iso = labelsIso[idx];

                            if (closedDays.includes(iso)) {
                                const motivo = closedDayReasons[iso] || "Giorno di chiusura";
                                return "Giornata di chiusura: " + motivo;
                            }

                            let total = 0;
                            items.forEach(it => {
                                const dsLabel = it.dataset.label || "";
                                if (dsLabel === "__closed_days__") return;
                                total += Number(it.raw || 0);
                            });

                            return "Totale giorno: " + total;
                        }
                    },
                    filter: function (context) {
                        const dsLabel = context.dataset.label || "";
                        const idx = context.dataIndex;
                        const iso = labelsIso[idx];

                        /* lascia passare il dataset invisibile solo per i giorni chiusi */
                        if (dsLabel === "__closed_days__") {
                            return closedDays.includes(iso);
                        }

                        /* nei giorni chiusi non mostrare dataset normali */
                        if (closedDays.includes(iso)) {
                            return false;
                        }

                        return Number(context.raw || 0) > 0;
                    }
                }
            },
            scales: {
                x: {
                    stacked: true,
                    offset: true,
                    ticks: {
                        autoSkip: false,
                        maxRotation: 0,
                        minRotation: 0,
                        font: {
                            size: 10
                        },
                        callback: function (value, index) {
                            const iso = labelsIso[index];
                            if (!iso) return "";

                            const parts = iso.split("-");
                            const day = parts[2];
                            const totalDays = labelsIso.length;

                            // Periodi brevi: mostra tutti i giorni
                            if (totalDays <= 20) {
                                return parseInt(day, 10);
                            }

                            // Periodi medi: mostra un giorno sì e uno no
                            if (totalDays <= 45) {
                                if (index % 2 !== 0) return "";
                                return parseInt(day, 10);
                            }

                            // Periodi lunghi: mostra un giorno ogni 2
                            if (index % 2 !== 0) return "";
                            return parseInt(day, 10);
                        }
                    },
                    grid: {
                        drawBorder: true,
                        color: function (ctx) {
                            const totalDays = labelsIso.length;
                            return totalDays <= 20
                                ? "rgba(0,0,0,0.08)"
                                : "rgba(0,0,0,0.05)";
                        }
                    }
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    grace: 1,
                    ticks: {
                        precision: 0,
                        stepSize: 1
                    },
                    title: {
                        display: true,
                        text: "Persone in ferie"
                    }
                }
            }
        }
    });
}

function fdRenderOfficeSummary(data) {
    const rows = data.office_summary || [];
    if (!rows.length) {
        $("#fd_office_summary").html("Nessun dato");
        return;
    }

    let html = `<table class="office-summary-table">
    <tr>
      <th>Ufficio</th>
      <th>Totale</th>
      <th>Picco</th>
    </tr>`;

    rows.forEach(r => {
        html += `<tr>
      <td>${r.ufficio}</td>
      <td>${r.totale_giorni_persona}</td>
      <td>${r.picco_giornaliero}</td>
    </tr>`;
    });

    html += "</table>";
    $("#fd_office_summary").html(html);
}

function fdRenderHeatmap(data) {
    const labels = data.labels || [];
    const labelsFmt = data.labels_fmt || [];
    const series = Array.isArray(data.series) ? data.series : [];
    const namesMap = data.names_by_office_date || {};

    if (!series.length || !labels.length) {
        $("#fd_heatmap").html('<div class="empty-box">Nessun dato disponibile.</div>');
        return;
    }

    let max = 0;
    series.forEach(s => {
        (s.data || []).forEach(v => {
            if (Number(v) > max) max = Number(v);
        });
    });

    let html = '<table class="heatmap-table"><thead><tr><th>Ufficio</th>';
    labelsFmt.forEach((l, idx) => {
        const iso = labels[idx];
        html += `<th title="${iso}">${l.substring(0, 5)}</th>`;
    });
    html += '</tr></thead><tbody>';

    series.forEach(s => {
        html += `<tr><td title="${s.label}">${s.label}</td>`;

        labels.forEach((iso, idx) => {
            const v = Number((s.data || [])[idx] || 0);
            const bg = fdHeatColor(v, max);
            const color = (bg === "#1d4ed8") ? "#ffffff" : "#1f2937";
            const names = ((namesMap[s.label] || {})[iso] || []).join(", ");
            const title = names
                ? `${iso} - ${s.label}: ${v}\n${names}`
                : `${iso} - ${s.label}: ${v}`;

            html += `<td class="heat-cell fd-day-cell"
            data-iso="${iso}"
            title="${title.replace(/"/g, '&quot;')}"
            style="background:${bg}; color:${color}; cursor:pointer;">${v > 0 ? v : ""}</td>`;
        });

        html += '</tr>';
    });

    html += '</tbody></table>';
    $("#fd_heatmap").html(html);
    $("#fd_heatmap .fd-day-cell").off("click").on("click", function () {
        const iso = $(this).data("iso");
        if (iso) {
            ferieDashboardOpenDay(iso);
        }
    });
}

function ferieDashboardLoad() {
    $.ajax({
        url: "ferieDashboardRead.php",
        dataType: "json",
        data: {
            finestra: $("#fd_finestra").val(),
            mode: $("#fd_mode").val()
        },
        success: function (r) {
            if (!r.ok) return;

            fdBuildSummary(r);
            fdRenderChart(r);
            fdRenderOfficeSummary(r);
            fdRenderHeatmap(r);
        }
    });
}

$(document).ready(function () {
    $("#fd_refresh").on("click", ferieDashboardLoad);
    $("#fd_finestra, #fd_mode").on("change", ferieDashboardLoad);
    ferieDashboardLoad();
});