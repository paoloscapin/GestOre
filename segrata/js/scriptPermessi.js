/**
 * Permessi ATA - Segreteria
 */

function setFiltroStato(stato) {
  $("#f_stato").val(stato);
  $("#f_stato").selectpicker('refresh'); // ✅ perché usi selectpicker

  // evidenzia badge selezionato
  $(".dash-item").removeClass("active");
  $('.dash-item[data-stato="' + stato + '"]').addClass("active");

  permessiReadRecords();
}

$(document).on("click", ".dash-item[data-stato]", function () {
  const stato = ($(this).data("stato") || "").toString().trim();
  if (!stato) return;
  setFiltroStato(stato);
});

function permessiReadRecords() {
  const stato  = ($("#f_stato").val() || "").toString();
  const tipoId = ($("#f_tipo").val() || "").toString();
  const search = ($("#f_search").val() || "").toString();

  $.ajax({
    url: "permessiReadRecords.php",
    method: "GET",
    data: { stato: stato, tipo_id: tipoId, search: search },
    dataType: "html",
    success: function (html) {
      $(".records_content").html(html);
    },
    error: function (xhr) {
      // stampo info utili in console
      console.error("permessiReadRecords ERROR", {
        status: xhr.status,
        statusText: xhr.statusText,
        responseText: (xhr.responseText || "").substring(0, 800)
      });

      // se il checkSession risponde JSON con redirect
      let r = null;
      try { r = JSON.parse(xhr.responseText); } catch(e) {}
      if (xhr.status === 401 && r && r.redirect) {
        window.location.href = r.redirect;
        return;
      }

      $.notify(
        { message: "Errore server (lista) - HTTP " + xhr.status },
        { type: "danger", placement: { from: "top", align: "center" }, delay: 6000 }
      );
    }
  });
}


function permessoOpen(id) {
  $("#hidden_permesso_id").val(id);

  $.ajax({
    url: "permessoReadDetails.php",
    method: "POST",
    dataType: "json",
    data: { id: id },
    success: function (r) {
      if (!r || r.ok !== true) {
        $.notify({ message: (r && r.error) ? r.error : "Errore lettura dettagli" }, { type: 'danger' });
        return;
      }

      $("#d_nome").text(r.dipendente.nome || "");
      $("#d_email").text(r.dipendente.email || "");
      $("#d_matricola").text(r.dipendente.matricola || "");
      $("#d_contratto").text(r.dipendente.tipo_contratto || "");
      $("#d_ruolo").text(r.dipendente.ruolo || "");

      $("#p_tipo").text(r.permesso.tipo || "");
      $("#p_stato").text(r.permesso.stato || "");
      $("#p_created").text(r.permesso.created_at || "");
      $("#p_updated").text(r.permesso.updated_at || "");

      $("#p_note_richiedente").val(r.permesso.note_richiedente || "");
      $("#p_note_segreteria").val(r.permesso.note_segreteria || "");
      $("#p_stato_edit").val(r.permesso.stato || "INVIATO");

      $("#righe_list").load("permessoRigheReadRecords.php?id=" + encodeURIComponent(id));

      $("#permesso_modal").modal("show");
    },
    error: function (xhr) {
      console.error(xhr.responseText);
      $.notify({ message: "Errore server (dettagli)" }, { type: 'danger' });
    }
  });
}

function permessoSave(statoOverride) {
  const id = $("#hidden_permesso_id").val();
  const stato = statoOverride || $("#p_stato_edit").val();
  const note = $("#p_note_segreteria").val();

  $.ajax({
    url: "permessoUpdateSegreteria.php",
    method: "POST",
    dataType: "json",
    data: { id: id, stato: stato, note_segreteria: note },
    success: function (r) {
      if (!r || r.ok !== true) {
        $.notify({ message: (r && r.error) ? r.error : "Salvataggio fallito" }, { type: 'danger' });
        return;
      }

      $.notify({
        icon: 'glyphicon glyphicon-ok',
        title: '<strong>Permessi</strong>&nbsp;',
        message: 'Salvato.'
      }, { type: 'success', placement: { from: "top", align: "center" }, delay: 2500 });

      $("#permesso_modal").modal("hide");
      permessiReadRecords();
    },
    error: function (xhr) {
      console.error(xhr.responseText);
      $.notify({ message: "Errore server (salvataggio)" }, { type: 'danger' });
    }
  });
}

function __spark(vals){
  // mini sparkline unicode (8 livelli)
  const blocks = ["▁","▂","▃","▄","▅","▆","▇","█"];
  if(!vals || !vals.length) return "";
  const max = Math.max.apply(null, vals);
  if(max <= 0) return blocks[0].repeat(vals.length);
  return vals.map(v => blocks[Math.max(0, Math.min(7, Math.round((v/max)*7))) ]).join("");
}

function renderTrend(vals, labels) {
  const max = Math.max.apply(null, vals.concat([1])); // evita max=0

  return vals.map((v, i) => {
    const ratio = v / max; // 0..1
    let cls = "trend-low";
    if (ratio > 0.80) cls = "trend-high";
    else if (ratio > 0.50) cls = "trend-mid";
    else if (ratio > 0.20) cls = "trend-low";

    const title = (labels && labels[i] ? labels[i] : "") + ": " + v;

    return `<span class="trend-dot ${cls}" title="${title}">▇</span>`;
  }).join("");
}

function dashboardLoad() {
  $.getJSON("permessiDashboard.php", {}, function (r) {
    if (!r || r.ok !== true) return;

    // conteggi per stato
    const s = { INVIATO:0, APPROVATO:0, RESPINTO:0, ANNULLATO:0, BOZZA:0 };
    (r.byStato || []).forEach(x => {
      if (s.hasOwnProperty(x.stato)) s[x.stato] = parseInt(x.n || 0, 10);
    });

    $("#d_inviato .badge").text(s.INVIATO);
    $("#d_approvato .badge").text(s.APPROVATO);
    $("#d_respinto .badge").text(s.RESPINTO);
    $("#d_annullato .badge").text(s.ANNULLATO);
    $("#d_bozza .badge").text(s.BOZZA);

    // trend ultimi 6 mesi: somma inviati+approvati+respinti
    const mesi = (r.byMese || []).slice(-6);

    const vals = mesi.map(m =>
      (parseInt(m.inviati || 0, 10) +
       parseInt(m.approvati || 0, 10) +
       parseInt(m.respinti || 0, 10))
    );

    // label mesi tipo "FEB 26"
    const mesiLabel = ["GEN","FEB","MAR","APR","MAG","GIU","LUG","AGO","SET","OTT","NOV","DIC"];
    const lab = mesi.map(m => {
      if (!m.ym) return "";
      const parts = m.ym.split("-");
      const y = parts[0];
      const mo = parts[1];
      const idx = parseInt(mo, 10) - 1;
      const yy = y ? y.slice(2) : "";
      return (mesiLabel[idx] || mo) + " " + yy;
    });

    if (mesi.length) {
      // trend colorato + label mesi
      const htmlTrend = renderTrend(vals, lab);
      $("#d_trend").html(`Trend: <span class="trend-wrap">${htmlTrend}</span> <span class="trend-labels">(${lab.join(" ")})</span>`);
    } else {
      $("#d_trend").html("");
    }
  });
}

$(document).ready(function () {

  // se usi bootstrap-select, inizializzalo
  if ($.fn.selectpicker) {
    $("#f_stato, #f_tipo").selectpicker({
      liveSearch: false
    });
  }
  dashboardLoad();
  permessiReadRecords();

  $("#btn_refresh").on("click", function (e) {
    e.preventDefault();
    permessiReadRecords();
  });

  // CHANGE: standard + bootstrap-select
  $("#f_stato, #f_tipo").on("change", function () {
    permessiReadRecords();
  });

  $("#f_stato").on("change", function () {
    const stato = ($("#f_stato").val() || "").toString();
    $(".dash-item").removeClass("active");
    if (stato) $('.dash-item[data-stato="' + stato + '"]').addClass("active");
  });

  // bootstrap-select triggera 'changed.bs.select'
  $(document).on("changed.bs.select", "#f_stato, #f_tipo", function () {
    permessiReadRecords();
  });

  // ricerca: debounce + invio
  let t = null;
  $("#f_search").on("keyup", function (e) {
    if (e.key === "Enter") {
      clearTimeout(t);
      permessiReadRecords();
      return;
    }
    clearTimeout(t);
    t = setTimeout(function () {
      permessiReadRecords();
    }, 300);
  });

  $("#btn_save_permesso").on("click", function () { permessoSave(null); });
  $("#btn_approve").on("click", function () { permessoSave("APPROVATO"); });
  $("#btn_reject").on("click", function () { permessoSave("RESPINTO"); });
});
