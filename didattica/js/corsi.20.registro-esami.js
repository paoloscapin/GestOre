/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// ================================
// Registro Lezione
// ================================
function apriRegistroLezione(corso_id) {

    $("#hidden_corso_id").val(corso_id);
    $('#registroLezioneModal #select_data_corso').empty();
    $('#registroLezioneModal #tabellaStudenti tbody').empty();
    $('#registroLezioneModal #argomentiLezione').val('');

    $('#registroLezioneModal').modal('show');

    $.post("../didattica/get_date_corso.php", { corso_id: corso_id }, function (data) {
        if (data.success) {
            var $selectData = $('#select_data_corso');
            if (data.date.length === 0) {
                $selectData.append('<option value="">Nessuna data disponibile</option>').selectpicker('refresh');
                $('#tabellaStudenti tbody').html('<tr><td colspan="4" class="text-center text-danger">Nessuna data disponibile</td></tr>');
            } else {
                data.date.forEach(function (d) {
                    var dt = new Date(d.data_inizio);
                    var giorno = String(dt.getDate()).padStart(2, '0');
                    var mese = String(dt.getMonth() + 1).padStart(2, '0');
                    var anno = dt.getFullYear();
                    var ore = String(dt.getHours()).padStart(2, '0');
                    var minuti = String(dt.getMinutes()).padStart(2, '0');
                    var formatted = `${giorno}-${mese}-${anno} alle ore ${ore}:${minuti}`;
                    $selectData.append('<option value="' + d.id + '">' + formatted + ' - Aula: ' + d.aula + '</option>');
                });
                $selectData.selectpicker('refresh');

                var firstDataId = data.date[0].id;
                $selectData.val(firstDataId).selectpicker('refresh');
                caricaStudentiEArgomenti(firstDataId);
            }
        } else {
            showToast('Errore nel caricamento delle date del corso', true);
        }
    }, 'json');
}

function caricaStudentiEArgomenti(data_id) {
    if (!data_id) return;

    $('#tabellaStudenti tbody').empty();
    $('#argomentiLezione').val('');
    $('#lezioneFirmata').prop('checked', false);

    // reset firme UI
    $('#firmeLezioneBox').hide().html('');
    $('#firmeDocentiWrap').hide();
    $('#tabellaFirmeDocenti tbody').empty();

    $.post("corsoGetStudentiArgomenti.php", { data_id: data_id }, function (data) {

        try {
            if (typeof data === "string") data = JSON.parse(data);
        } catch (e) { }

        if (!data || !data.success) {
            showToast('Errore nel caricamento studenti/argomenti', true);
            return;
        }

        if (data.studenti.length === 0) {
            $('#tabellaStudenti tbody').html('<tr><td colspan="4" class="text-center">Nessuno studente iscritto</td></tr>');
        } else {
            data.studenti.forEach(function (s) {
                var checkedPresente = s.presente ? 'checked' : '';
                $('#tabellaStudenti tbody').append(
                    '<tr>' +
                    '<td>' + s.nominativo + '</td>' +
                    '<td>' + s.classe + '</td>' +
                    '<td class="text-center"><input type="checkbox" class="chkPresente" data-id="' + s.id + '" ' + checkedPresente + '></td>' +
                    '</tr>'
                );
            });
        }

        if (data.argomento) $('#argomentiLezione').val(data.argomento);

        // ====== FIRME: docente vs segreteria ======
        var ruolo = (window.GESTORE_RUOLO_EFF || window.GESTORE_RUOLO || "").toLowerCase();
        var isSegreteria = (ruolo === "segreteria-didattica" || ruolo === "dirigente" || ruolo === "admin");

        var myDocId = parseInt(window.GESTORE_DOCENTE_ID_EFF || 0, 10) || 0;

        if (isSegreteria) {
            $('#lezioneFirmata').closest('.col-md-4').hide();

            if (data.docenti_firme && data.docenti_firme.length > 0) {
                data.docenti_firme.forEach(function (d) {
                    var chk = (parseInt(d.firmato, 10) === 1) ? 'checked' : '';
                    var when = d.firmato_il ? String(d.firmato_il) : '—';
                    $('#tabellaFirmeDocenti tbody').append(
                        '<tr>' +
                        '<td>' + d.cognome + ' ' + d.nome + (parseInt(d.principale, 10) === 1 ? ' <span class="label label-info">PRINC</span>' : '') + '</td>' +
                        '<td class="text-center"><input type="checkbox" class="chkFirmaDocente" data-id="' + d.id_docente + '" ' + chk + '></td>' +
                        '<td>' + when + '</td>' +
                        '</tr>'
                    );
                });
                $('#firmeDocentiWrap').show();
            } else {
                $('#tabellaFirmeDocenti tbody').html('<tr><td colspan="3" class="text-center text-muted">Nessun docente associato al corso</td></tr>');
                $('#firmeDocentiWrap').show();
            }

        } else {

            $('#lezioneFirmata').closest('.col-md-4').show();

            var firmatoMe = (data.firmato_me !== undefined && data.firmato_me !== null)
                ? (parseInt(data.firmato_me, 10) === 1)
                : null;

            if (firmatoMe === null && myDocId > 0 && data.firme && Array.isArray(data.firme)) {
                firmatoMe = data.firme.some(function (f) {
                    return parseInt(f.id_docente || f.id || 0, 10) === myDocId;
                });
            }

            if (firmatoMe === null) {
                firmatoMe = (parseInt(data.firmato || 0, 10) === 1);
            }

            var $tg = $('#lezioneFirmata');

            $tg.prop('checked', !!firmatoMe);

            if ($.fn.bootstrapToggle) {
                if (!$tg.parent().hasClass('toggle')) {
                    try { $tg.bootstrapToggle(); } catch (e) { }
                }
                $tg.bootstrapToggle(!!firmatoMe ? 'on' : 'off');
            }

            if (data.firme && data.firme.length > 0) {
                var html = "<b>Firmato da:</b><br>";
                data.firme.forEach(function (f) {
                    var when = f.firmato_il ? String(f.firmato_il) : "";
                    html += "• " + f.cognome + " " + f.nome + " — " + when + "<br>";
                });
                $('#firmeLezioneBox').html(html).show();
            } else {
                $('#firmeLezioneBox').html("<b>Firmato da:</b> nessuno").show();
            }
            $('#firmeDocentiWrap').hide();
            $('#tabellaFirmeDocenti tbody').empty();
            $('#tabellaFirmeDocenti').find('input.chkFirmaDocente').prop('disabled', true);

        }

    }, 'json').fail(function () {
        showToast('Errore di comunicazione nel caricamento studenti/argomenti', true);
    });
}

function salvaRegistroLezione() {
    var corso_id = $('#hidden_corso_id').val();
    var data_id = $('#select_data_corso').val();
    var argomenti = $('#argomentiLezione').val();

    var ruolo = (window.GESTORE_RUOLO_EFF || window.GESTORE_RUOLO || "").toLowerCase();
    var isSegreteria = (ruolo === "segreteria-didattica" || ruolo === "dirigente" || ruolo === "admin");

    var firmato = $('#lezioneFirmata').is(':checked') ? 1 : 0;

    var presenze = [];
    $('#tabellaStudenti tbody tr').each(function () {
        var id_studente = $(this).find('.chkPresente').data('id');
        var presente = $(this).find('.chkPresente').is(':checked') ? 1 : 0;
        presenze.push({ id_studente: id_studente, presente: presente });
    });

    var firme_docenti = null;
    if (isSegreteria) {
        firme_docenti = [];
        $('#tabellaFirmeDocenti tbody tr').each(function () {
            var did = $(this).find('.chkFirmaDocente').data('id');
            if (!did) return;
            var f = $(this).find('.chkFirmaDocente').is(':checked') ? 1 : 0;
            firme_docenti.push({ id_docente: did, firmato: f });
        });
    }

    var payload = {
        data_id: data_id,
        corso_id: corso_id,
        argomenti: argomenti,
        presenze: presenze,
        firmato: firmato
    };

    if (isSegreteria) {
        payload.firme_docenti = firme_docenti;
    }

    $.post("corsiSalvaRegistroLezione.php", payload, function (data) {

        try {
            if (typeof data === "string") data = JSON.parse(data);
        } catch (e) { }

        if (data && data.success) {
            showToast('Registro salvato con successo');
            $('#registroLezioneModal').modal('hide');
            corsiReadRecords();
        } else {
            showToast('Errore nel salvataggio: ' + ((data && data.error) ? data.error : ''), true);
        }
    }, 'json').fail(function (xhr) {
        showToast('Errore di comunicazione (salvataggio)', true);
    });
}

// ================================
// Esami
// ================================
function apriEsameModal(corso_id) {
    $("#hidden_corso_id").val(corso_id);

    $('#hidden_esame_data_id').val('');
    $('#tabellaEsameStudenti tbody').empty();
    $('#argomentiEsame').val('');
    $('#esame_inizio_data').val('');
    $('#esame_inizio_ora').val('');
    $('#esame_fine_data').val('');
    $('#esame_fine_ora').val('');
    $('#esame_aula').val('');
    $('#select_tentativo').empty();
    $('#esameFirmato').prop('checked', false);

    $('#esameFirmato').prop('checked', false);
    $('#firmeEsameBox').hide().html('');
    $('#firmeDocentiEsameWrap').hide();
    $('#tabellaFirmeDocentiEsame tbody').empty();
    $('#esameFirmatoRow').show();

    function buildTentativoLabel(esame) {
        const t = parseInt(esame.tentativo, 10) || 1;

        let stato = '';
        const ruolo = (window.GESTORE_RUOLO || "").toLowerCase();
        const isSegreteria = (ruolo === "segreteria-didattica" || ruolo === "dirigente" || ruolo === "admin");

        const firm = isSegreteria ? parseInt(esame.firmato_any, 10) : parseInt(esame.firmato_mio, 10);

        if (firm === 1) stato = 'FIR';
        else if (esame.data_inizio_esame) stato = 'PRG';
        else stato = 'BOZ';

        let label = `E${t}: ${stato}`;

        if (esame.data_inizio_esame) {
            const dt = new Date(String(esame.data_inizio_esame).replace(' ', 'T'));
            if (!isNaN(dt.getTime())) label += ` (${dt.toLocaleDateString()})`;
        }
        return label;
    }

    $('#esameModal').modal('show');

    $.post("../didattica/corsoEsamiReadDetails.php", { corso_id: corso_id }, function (data) {

        if (!data || !data.success) {
            showToast("Errore nel caricamento dati esame", true);
            return;
        }

        if (!data.esami || !Array.isArray(data.esami) || data.esami.length === 0) {
            $('#select_tentativo').html('<option value="0">Nessun esame programmato</option>');
            $('#tabellaEsameStudenti tbody').html(
                '<tr><td colspan="8" class="text-center text-danger">Nessun esame programmato</td></tr>'
            );
            $('#firmeDocentiEsameWrap').hide();
            $('#tabellaFirmeDocentiEsame tbody').empty();
            $('#firmeEsameBox').hide().html('');
            return;
        }

        data.esami.forEach(function (esame) {
            const label = buildTentativoLabel(esame);
            $('#select_tentativo').append(
                `<option value="${esame.tentativo}" data-id="${esame.id}">${label}</option>`
            );
        });

        const first = data.esami[0];
        $('#select_tentativo').val(first.tentativo);
        $('#hidden_esame_data_id').val(first.id);

        caricaDatiTentativo(data, first.tentativo);

        $('#select_tentativo').off('change').on('change', function () {
            const t = parseInt($(this).val(), 10) || 1;
            const selected = data.esami.find(e => parseInt(e.tentativo, 10) === t);
            if (!selected) return;

            $('#hidden_esame_data_id').val(selected.id);
            caricaDatiTentativo(data, t);
        });

    }, 'json').fail(function () {
        showToast("Errore di comunicazione col server", true);
    });
}

// Carica i dati di un tentativo (con assenza giustificata + motivo)
function caricaDatiTentativo(data, tentativo) {
    $('#tabellaEsameStudenti tbody').empty();
    $('#argomentiEsame').val('');
    $('#esame_inizio_data').val('');
    $('#esame_inizio_ora').val('');
    $('#esame_fine_data').val('');
    $('#esame_fine_ora').val('');
    $('#esame_aula').val('');

    $('#esameFirmato').prop('checked', false);
    $('#firmeEsameBox').hide().html('');
    $('#firmeDocentiEsameWrap').hide();
    $('#tabellaFirmeDocentiEsame tbody').empty();

    var ruolo = (window.GESTORE_RUOLO_EFF || window.GESTORE_RUOLO || "").toLowerCase();
    var isSegreteria = (ruolo === "segreteria-didattica" || ruolo === "dirigente" || ruolo === "admin");
    var myDocId = parseInt(window.GESTORE_DOCENTE_ID_EFF || 0, 10) || 0;

    let esame = (data.esami || []).find(e => String(e.tentativo) === String(tentativo));
    if (esame) {
        if (esame.data_inizio_esame) {
            let parts = esame.data_inizio_esame.split(/[- :]/);
            let dt = new Date(parts[0], parts[1] - 1, parts[2], parts[3], parts[4], parts[5] || 0);
            if (!isNaN(dt.getTime())) {
                $('#esame_inizio_data').val(
                    dt.getFullYear() + '-' + String(dt.getMonth() + 1).padStart(2, '0') + '-' + String(dt.getDate()).padStart(2, '0')
                );
                $('#esame_inizio_ora').val(
                    String(dt.getHours()).padStart(2, '0') + ':' + String(dt.getMinutes()).padStart(2, '0')
                );
            }
        }

        if (esame.data_fine_esame) {
            let parts = esame.data_fine_esame.split(/[- :]/);
            let dt = new Date(parts[0], parts[1] - 1, parts[2], parts[3], parts[4], parts[5] || 0);
            if (!isNaN(dt.getTime())) {
                $('#esame_fine_data').val(
                    dt.getFullYear() + '-' + String(dt.getMonth() + 1).padStart(2, '0') + '-' + String(dt.getDate()).padStart(2, '0')
                );
                $('#esame_fine_ora').val(
                    String(dt.getHours()).padStart(2, '0') + ':' + String(dt.getMinutes()).padStart(2, '0')
                );
            }
        }

        if (esame.aula) $('#esame_aula').val(esame.aula);

        if (isSegreteria) {
            $('#esameFirmatoRow').hide();

            if (esame.docenti_firme && Array.isArray(esame.docenti_firme) && esame.docenti_firme.length > 0) {
                esame.docenti_firme.forEach(function (d) {
                    var chk = (parseInt(d.firmato, 10) === 1) ? 'checked' : '';
                    var when = d.firmato_il ? String(d.firmato_il) : '—';
                    $('#tabellaFirmeDocentiEsame tbody').append(
                        '<tr>' +
                        '<td>' + d.cognome + ' ' + d.nome + (parseInt(d.principale, 10) === 1 ? ' <span class="label label-info">PRINC</span>' : '') + '</td>' +
                        '<td class="text-center"><input type="checkbox" class="chkFirmaDocenteEsame" data-id="' + d.id_docente + '" ' + chk + '></td>' +
                        '<td>' + when + '</td>' +
                        '</tr>'
                    );
                });
                $('#firmeDocentiEsameWrap').show();
            } else {
                $('#tabellaFirmeDocentiEsame tbody').html('<tr><td colspan="3" class="text-center text-muted">Nessun docente associato</td></tr>');
                $('#firmeDocentiEsameWrap').show();
            }

        } else {
            $('#esameFirmatoRow').show();

            var firmatoMio = (esame.firmato_mio !== undefined && esame.firmato_mio !== null)
                ? (parseInt(esame.firmato_mio, 10) === 1)
                : null;

            if (firmatoMio === null && myDocId > 0 && esame.firme && Array.isArray(esame.firme)) {
                firmatoMio = esame.firme.some(function (f) {
                    return parseInt(f.id_docente || f.id || 0, 10) === myDocId;
                });
            }

            if (firmatoMio === null) {
                firmatoMio = (parseInt(esame.firmato || 0, 10) === 1);
            }

            var $tgE = $('#esameFirmato');

            $tgE.prop('checked', !!firmatoMio);

            if ($.fn.bootstrapToggle) {
                if (!$tgE.parent().hasClass('toggle')) {
                    try { $tgE.bootstrapToggle(); } catch (e) { }
                }
                $tgE.bootstrapToggle(!!firmatoMio ? 'on' : 'off');
            }

            if (esame.firme && Array.isArray(esame.firme) && esame.firme.length > 0) {
                var html = "<b>Firmato da:</b><br>";
                esame.firme.forEach(function (f) {
                    var when = f.firmato_il ? String(f.firmato_il) : "";
                    html += "• " + f.cognome + " " + f.nome + " — " + when + "<br>";
                });
                $('#firmeEsameBox').html(html).show();
            } else {
                $('#firmeEsameBox').html("<b>Firmato da:</b> nessuno").show();
            }
        }
    }

    let allStud = (data.studenti || []);

    let hasTentativo = allStud.some(s =>
        s.tentativo !== undefined && s.tentativo !== null && String(s.tentativo) !== ""
    );

    let idEsameSel = parseInt($("#hidden_esame_data_id").val(), 10) || 0;
    let hasIdEsame = allStud.some(s =>
        s.id_esame_data !== undefined && s.id_esame_data !== null && String(s.id_esame_data) !== ""
    );

    let primoConArg = null;

    if (hasTentativo) {
        primoConArg = allStud.find(s =>
            String(s.tentativo) === String(tentativo) &&
            s.argomenti && String(s.argomenti).trim() !== ""
        );
    } else if (hasIdEsame && idEsameSel > 0) {
        primoConArg = allStud.find(s =>
            parseInt(s.id_esame_data, 10) === idEsameSel &&
            s.argomenti && String(s.argomenti).trim() !== ""
        );
    } else {
        primoConArg = allStud.find(s =>
            s.argomenti && String(s.argomenti).trim() !== ""
        );
    }

    if (primoConArg) $('#argomentiEsame').val(primoConArg.argomenti);

    let studenti = [];

    if (hasTentativo) {
        studenti = allStud.filter(s => String(s.tentativo) === String(tentativo));
    } else if (hasIdEsame && idEsameSel > 0) {
        studenti = allStud.filter(s => parseInt(s.id_esame_data, 10) === idEsameSel);
    } else {
        studenti = allStud;
    }

    if (studenti.length === 0) {
        $('#tabellaEsameStudenti tbody').html(
            '<tr><td colspan="8" class="text-center text-danger">Nessuno studente iscritto</td></tr>'
        );
        return;
    }

    studenti.forEach(function (s) {
        var presenteVal = parseInt(s.presente || 0, 10);
        var assG = parseInt(s.assenza_giustificata || 0, 10);
        var assNote = (s.assenza_note || "");
        var tipo = (s.tipo_prova || "");

        if (presenteVal === 1) {
            assG = 0;
            assNote = "";
        }

        let votoVal = (s.voto !== null && s.voto !== undefined) ? s.voto : '';

        let row = `
            <tr>
                <td>${s.cognome} ${s.nome}</td>
                <td>${s.classe}</td>

                <td class="text-center">
                    <input type="checkbox" class="chk-presente" data-id="${s.stud_id}" ${presenteVal === 1 ? 'checked' : ''}>
                </td>

                <td class="text-center">
                    <input type="checkbox" class="chk-assenza-giust" data-id="${s.stud_id}" ${assG === 1 ? 'checked' : ''}>
                </td>

                <td>
                    <input type="text" class="form-control input-assenza-note" data-id="${s.stud_id}"
                           value="${escapeHtml(assNote)}"
                           placeholder="Es. certificato medico">
                </td>

                <td>
                    <select class="form-control tipo-prova" data-id="${s.stud_id}">
                        <option value="" ${tipo === '' ? 'selected' : ''}>—</option>
                        <option value="scritto" ${tipo === 'scritto' ? 'selected' : ''}>Scritto</option>
                        <option value="orale"   ${tipo === 'orale' ? 'selected' : ''}>Orale</option>
                        <option value="pratico" ${tipo === 'pratico' ? 'selected' : ''}>Pratico</option>
                    </select>
                </td>

                <td>
                    <input type="number" class="form-control voto-studente" data-id="${s.stud_id}"
                           min="0" max="10" step="0.5" value="${votoVal}">
                </td>

                <td class="recuperata text-center">
                    ${(parseFloat(s.voto) >= 6) ? '✅' : (s.voto ? '❌' : '-')}
                </td>
            </tr>
        `;

        $('#tabellaEsameStudenti tbody').append(row);
        syncAssenzaUI($('#tabellaEsameStudenti tbody tr').last());
    });

    $("#tabellaEsameStudenti")
        .off("change", ".chk-presente")
        .on("change", ".chk-presente", function () {
            syncAssenzaUI($(this).closest("tr"));
        });

    $("#tabellaEsameStudenti")
        .off("change", ".chk-assenza-giust")
        .on("change", ".chk-assenza-giust", function () {
            syncAssenzaUI($(this).closest("tr"));
        });

    $("#tabellaEsameStudenti")
        .off("input", ".voto-studente")
        .on("input", ".voto-studente", function () {
            let voto = parseFloat($(this).val());
            let cell = $(this).closest("tr").find(".recuperata");
            if (!isNaN(voto)) cell.text(voto >= 6 ? "✅" : "❌");
            else cell.text("-");
        });
}

// Alias per compatibilità se il bottone chiama "apriEsame(...)"
function apriEsame(corso_id) {
    return apriEsameModal(corso_id);
}

function salvaEsame() {
    var corso_id = parseInt($("#hidden_corso_id").val(), 10) || 0;

    var id_esame_data_raw = $("#hidden_esame_data_id").val();
    var id_esame_data = parseInt(id_esame_data_raw, 10);
    if (isNaN(id_esame_data) || id_esame_data <= 0) id_esame_data = -1;

    var argomenti = $('#argomentiEsame').val().trim();

    var ruolo = (window.GESTORE_RUOLO_EFF || window.GESTORE_RUOLO || "").toLowerCase();
    var vistaDocente = (parseInt(window.GESTORE_VISTA_DOCENTE || 0, 10) === 1);
    var isSegreteria = (!vistaDocente) && (ruolo === "segreteria-didattica" || ruolo === "dirigente" || ruolo === "admin");

    var firmato = $('#esameFirmato').is(':checked') ? 1 : 0;

    var firme_docenti = null;
    if (isSegreteria) {
        firme_docenti = [];
        $('#tabellaFirmeDocentiEsame tbody tr').each(function () {
            var $chk = $(this).find('.chkFirmaDocenteEsame');
            var did = $chk.data('id');
            if (!did) return;

            var f = $chk.is(':checked') ? 1 : 0;
            firme_docenti.push({ id_docente: did, firmato: f });
        });
    }

    if (corso_id <= 0) {
        showToast("Corso non valido", true);
        return;
    }

    var studenti = [];
    $('#tabellaEsameStudenti tbody tr').each(function () {
        let id = $(this).find('.chk-presente').data('id');
        id = parseInt(id, 10);
        if (!id) return;

        let presente = $(this).find('.chk-presente').is(':checked') ? 1 : 0;

        let assenza_giustificata = $(this).find('.chk-assenza-giust').is(':checked') ? 1 : 0;
        let assenza_note = $(this).find('.input-assenza-note').val();

        if (presente === 1) {
            assenza_giustificata = 0;
            assenza_note = null;
        } else {
            if (assenza_giustificata !== 1) {
                assenza_note = null;
            } else {
                assenza_note = (assenza_note && assenza_note.trim() !== "") ? assenza_note.trim() : null;
            }
        }

        let tipo = $(this).find('.tipo-prova').val();
        if (tipo === "") tipo = null;

        let votoRaw = $(this).find('.voto-studente').val();
        let voto = (votoRaw !== '' && votoRaw !== null && votoRaw !== undefined) ? votoRaw : null;

        if (presente === 0) {
            tipo = null;
            voto = null;
        }

        studenti.push({
            id_studente: id,
            presente: presente,
            assenza_giustificata: assenza_giustificata,
            assenza_note: assenza_note,
            tipo: tipo,
            voto: voto
        });
    });

    var data_inizio_esame = $('#esame_inizio_data').val();
    var ora_inizio_esame = $('#esame_inizio_ora').val();
    var data_fine_esame = $('#esame_fine_data').val();
    var ora_fine_esame = $('#esame_fine_ora').val();
    var aula_esame = $('#esame_aula').val().trim();

    var datetime_inizio_esame = null;
    var datetime_fine_esame = null;

    if (data_inizio_esame && ora_inizio_esame) datetime_inizio_esame = data_inizio_esame + " " + ora_inizio_esame + ":00";
    if (data_fine_esame && ora_fine_esame) datetime_fine_esame = data_fine_esame + " " + ora_fine_esame + ":00";

    if (!data_inizio_esame || !ora_inizio_esame) {
        showToast("Inserisci data e ora di inizio dell'esame", true);
        return;
    }
    if (!data_fine_esame || !ora_fine_esame) {
        showToast("Inserisci data e ora di fine dell'esame", true);
        return;
    }
    if (!aula_esame) {
        showToast("Inserisci l'aula dell'esame", true);
        return;
    }

    if (datetime_inizio_esame && datetime_fine_esame) {
        var di = new Date(datetime_inizio_esame.replace(' ', 'T'));
        var df = new Date(datetime_fine_esame.replace(' ', 'T'));
        if (!isNaN(di.getTime()) && !isNaN(df.getTime()) && df < di) {
            showToast("La data/ora di fine non può essere precedente all'inizio", true);
            return;
        }
    }

    if (!isSegreteria) {
        if (firmato === 1 && !argomenti) {
            showToast("Inserisci gli argomenti della prova prima di firmare", true);
            return;
        }
    } else {
        var anyFirma = false;
        if (Array.isArray(firme_docenti)) {
            anyFirma = firme_docenti.some(x => parseInt(x.firmato, 10) === 1);
        }
        if (anyFirma && !argomenti) {
            showToast("Inserisci gli argomenti della prova prima di firmare", true);
            return;
        }
    }

    let bad = studenti.find(x =>
        x.presente === 0 &&
        x.assenza_giustificata === 1 &&
        (!x.assenza_note || String(x.assenza_note).trim() === '')
    );
    if (bad) {
        showToast("Per le assenze giustificate inserisci anche il motivo", true);
        return;
    }

    var payload = {
        corso_id: corso_id,
        id_esame_data: id_esame_data,
        argomenti: argomenti,
        data_inizio: datetime_inizio_esame,
        data_fine: datetime_fine_esame,
        aula: aula_esame,
        firmato: firmato,
        studenti: studenti
    };

    if (isSegreteria) {
        payload.firme_docenti = JSON.stringify(firme_docenti || []);
        payload.force_segreteria = 1;
    }

    if (window.console) {
        console.log("salvaEsame payload:", payload);
    }

    $.post("../didattica/corsoEsamiSave.php", payload, function (data) {
        if (data && data.success) {
            showToast('Esame salvato con successo');
            $('#esameModal').modal('hide');
            corsiReadRecords();
        } else {
            showToast('Errore nel salvataggio esame: ' + (data && data.error ? data.error : ''), true);
        }
    }, 'json').fail(function () {
        showToast("Errore di comunicazione col server", true);
    });
}

// ================================
// Secondo tentativo (funzioni già nel tuo file originario)
// ================================
function cancellaSecondoTentativo(id_studente, iscrizione_id, id_corso) {
    bootbox.confirm({
        title: "Conferma",
        message: "Vuoi rimuovere lo studente dal secondo tentativo?",
        buttons: {
            cancel: { label: "Annulla", className: "btn-default" },
            confirm: { label: "Conferma", className: "btn-danger" }
        },
        callback: function (result) {
            if (result) {
                $.post("../didattica/corsiCancellaSecondoTentativo.php",
                    { id_corso: id_corso, id_studente: id_studente },
                    function (data) {
                        if (data.success) {
                            showToast("Studente rimosso dal secondo tentativo");
                            corsiGetDetails(id_corso);
                        } else {
                            showToast("Errore: " + data.error, true);
                        }
                    }, "json"
                );
            }
        }
    });
}

function iscriviSecondoTentativo(stud_id, iscrizione_id, corso_id, opts) {
    opts = opts || {};
    var recuperoAssenza = (parseInt(opts.recupero_assenza, 10) === 1) ? 1 : 0;

    var req = $.getJSON("../didattica/corsiSecondaSessioneList.php", {
        id_corso: corso_id,
        recupero_assenza: recuperoAssenza
    });

    req.done(function (resp) {
        if (!resp || !resp.success) {
            showToast("Errore caricamento corsi", true);
            return;
        }

        var lista = resp.corsi || [];

        var labelCorsi = recuperoAssenza
            ? "Usa un corso di recupero assenza esistente"
            : "Usa un corso 2ª sessione esistente";

        var labelNew = recuperoAssenza
            ? "Oppure crea un nuovo corso di recupero assenza scegliendo il docente"
            : "Oppure crea un nuovo corso 2ª sessione scegliendo il docente";

        var titleDlg = recuperoAssenza
            ? "Recupero assenza giustificata: scegli corso o crea"
            : "2ª sessione: scegli corso o crea";

        var optCorsi = '<option value="0">-- Seleziona corso --</option>';
        (lista || []).forEach(function (c) {
            var badge = recuperoAssenza ? " [RECUPERO ASSENZA]" : " [2ª SESSIONE]";

            var when = "";
            if (c.esame_inizio) {
                var dt = new Date(String(c.esame_inizio).replace(' ', 'T'));
                if (!isNaN(dt.getTime())) {
                    var gg = String(dt.getDate()).padStart(2, '0');
                    var mm = String(dt.getMonth() + 1).padStart(2, '0');
                    var aa = dt.getFullYear();
                    var hh = String(dt.getHours()).padStart(2, '0');
                    var mi = String(dt.getMinutes()).padStart(2, '0');
                    when = ` — ${gg}/${mm}/${aa} ${hh}:${mi}`;
                } else {
                    when = " — " + String(c.esame_inizio);
                }
            } else {
                when = " — (senza esame)";
            }

            optCorsi +=
                '<option value="' + c.id + '">' +
                (c.cognome || '') + ' ' + (c.nome || '') + ' - ' + (c.titolo || '') +
                badge + when + ' (ID:' + c.id + ')</option>';
        });

        var docOptions = $("#docente").html();

        var html = `
          <div class="form-group">
            <label>${labelCorsi}</label>
            <select id="choose_corso2" class="form-control">${optCorsi}</select>
          </div>
          <hr>
          <div class="form-group">
            <label>${labelNew}</label>
            <select id="choose_newdoc" class="form-control">${docOptions}</select>
            <small class="text-muted">Il corso sarà creato per la stessa materia/anno del corso di partenza.</small>
          </div>
        `;

        function closeCorso1AndOpenCorso2(idCorso2) {
            idCorso2 = parseInt(idCorso2, 10) || 0;

            bootbox.hideAll();

            $("#corsi_modal")
                .off("hidden.bs.modal.openCorso2")
                .on("hidden.bs.modal.openCorso2", function () {
                    $("#corsi_modal").off("hidden.bs.modal.openCorso2");

                    corsiReadRecords();

                    if (idCorso2 > 0) {
                        corsiGetDetails(idCorso2);
                    }
                })
                .modal("hide");
        }

        bootbox.dialog({
            title: titleDlg,
            message: html,
            buttons: {
                cancel: { label: "Annulla", className: "btn-default" },

                attach: {
                    label: "Aggancia a corso esistente",
                    className: "btn-info",
                    callback: function () {
                        var id_corso2 = parseInt($("#choose_corso2").val(), 10) || 0;
                        if (id_corso2 <= 0) {
                            showToast("Seleziona un corso valido", true);
                            return false;
                        }

                        $.post("../didattica/corsiIscriviSecondoTentativo.php", {
                            id_corso: corso_id,
                            id_studente: stud_id,
                            id_corso_secondo: id_corso2,
                            recupero_assenza: recuperoAssenza
                        }, function (data) {
                            if (data && data.success) {
                                showToast(recuperoAssenza ? "Studente assegnato al recupero assenza" : "Studente assegnato alla 2ª sessione");
                                closeCorso1AndOpenCorso2(data.id_corso_secondo || id_corso2);
                            } else {
                                showToast("Errore: " + (data && data.error ? data.error : ""), true);
                            }
                        }, "json").fail(function () {
                            showToast("Errore di comunicazione (aggancio corso esistente)", true);
                        });

                        return false;
                    }
                },

                create: {
                    label: "Crea nuovo corso + aggancia",
                    className: "btn-primary",
                    callback: function () {
                        var newDoc = parseInt($("#choose_newdoc").val(), 10) || 0;
                        if (newDoc <= 0) {
                            showToast("Seleziona un docente valido", true);
                            return false;
                        }

                        $.post("../didattica/corsiIscriviSecondoTentativo.php", {
                            id_corso: corso_id,
                            id_studente: stud_id,
                            new_docente_id: newDoc,
                            recupero_assenza: recuperoAssenza
                        }, function (data) {
                            if (data && data.success) {
                                showToast(recuperoAssenza ? "Creato/agganciato recupero assenza" : "Creato/agganciato corso 2ª sessione");
                                closeCorso1AndOpenCorso2(data.id_corso_secondo);
                            } else {
                                showToast("Errore: " + (data && data.error ? data.error : ""), true);
                            }
                        }, "json").fail(function () {
                            showToast("Errore di comunicazione (creazione corso)", true);
                        });

                        return false;
                    }
                }
            }
        });
    });

    req.fail(function (xhr) {
        showToast("Errore caricamento corsi (HTTP " + (xhr && xhr.status ? xhr.status : "?") + ")", true);
    });
}

// ================================
// Bind UI (document ready + filtri)
// ================================
$(document).ready(function () {
    corsiReadRecords();

    if ($('#docenti_multi').length) {
        $('#docenti_multi').selectpicker();
        $('#docenti_multi').selectpicker('refresh');
    }

    if ($('#lezioneFirmata').length && $.fn.bootstrapToggle) {
        $('#lezioneFirmata').bootstrapToggle();
    }

    if ($('#esameFirmato').length && $.fn.bootstrapToggle) {
        $('#esameFirmato').bootstrapToggle();
    }

    // filtri in lista corsi
    $('#futuri').change(function () {
        $futuri = this.checked ? 1 : 0;
        corsiReadRecords();
    });

    $('#carenze').change(function () {
        if (this.checked) {
            $carenze_toggle = 1;
            $("#carenza_sessione_box").show();
            $("#carenza_sessione").prop("disabled", false).selectpicker("refresh");
        } else {
            $carenze_toggle = 0;
            $carenza_sessione = 0;
            $("#carenza_sessione").selectpicker("val", "0");
            $("#carenza_sessione_box").hide();
            $("#carenza_sessione").prop("disabled", true).selectpicker("refresh");
        }
        corsiReadRecords();
    });

    $("#carenza_sessione").on("changed.bs.select", function () {
        $carenza_sessione = parseInt($(this).val(), 10) || 0;
        corsiReadRecords();
    });

    $('#filtro_itinere').change(function () {
        $in_itinere_toggle = this.checked ? 1 : 0;
        corsiReadRecords();
    });

    $('#select_data_corso').on('change', function () {
        var data_id = $(this).val();
        caricaStudentiEArgomenti(data_id);
    });

    $("#btn-invia-esiti").on("click", function () {
        showToast("Invio in corso...", false, 10000);
        $.post("inviaEsitiCoordinatori.php", {}, function (res) {
            let risposta = JSON.parse(res);
            if (risposta.success) showToast("Invio completato!");
            else showToast("Errore: " + risposta.msg);
        }).fail(function () {
            showToast("Errore nella comunicazione col server");
        });
    });

    $('#in_itinere').change(function () {
        if ($(this).prop('checked')) $('#date_section').hide();
        else $('#date_section').show();
    });

    $("#incompleti").on("click", function (e) {
        e.preventDefault();
        window.location.href = "corsiIncompleti.php";
    });

    $("#export_btn").on("click", function (e) {
        e.preventDefault();
        window.location.href = "exportEsami.php";
    });

    $("#docente_filtro").on("changed.bs.select", function () {
        $docente_filtro_id = this.value;
        corsiReadRecords();
    });

    $("#materia_filtro").on("changed.bs.select", function () {
        $materia_filtro_id = this.value;
        corsiReadRecords();
    });

    $("#anni_filtro").on("changed.bs.select", function () {
        $anni_filtro_id = this.value;
        corsiReadRecords();
    });

    $('#file_select_id').change(function (e) {
        importFile(e.target.files[0]);
    });

    $("#corsi_modal").on("hidden.bs.modal", function () {
        corsiReadRecords();
    });

    $("#carenza_sessione").selectpicker();
    $("#carenza_sessione_box").hide();
    $("#carenza_sessione").prop("disabled", true).selectpicker("refresh");
});
