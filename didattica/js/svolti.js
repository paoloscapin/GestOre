/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var scripts = document.getElementsByTagName('script');
var myScript = scripts[scripts.length - 1];
var url = new URL(myScript.src);
var params = new URLSearchParams(url.search);
var $anni_filtro_id = params.get("a") || "1";

console.log("anno scolastico corrente: " + $anni_filtro_id);

var $classi_filtro_id = 0;
var $materia_filtro_id = 0;
var $docenti_filtro_id = 0;
var $da_completare_filtro_id = 0;
var activeProgrammaPreviewField = null;

function getClasseAnnoCorrente() {
    var annoHidden = parseInt($('#hidden_programma_classe_anno').val(), 10) || 0;
    var annoSelected = parseInt($('#classe option:selected').data('anno'), 10) || 0;
    return annoSelected > 0 ? annoSelected : annoHidden;
}

function isProgrammaQuinta() {
    return getClasseAnnoCorrente() === 5;
}

function pulisciCampiQuinta() {
    $('#competenze_raggiunte').val('');
    $('#contenuti_trattati').val('');
    $('#abilita_quinta').val('');
}

function pulisciCampiProgrammaQuinta() {
    $('#metodologie_programma').val('');
    $('#criteri_valutazione_programma').val('');
    $('#testi_materiali_programma').val('');
}

function escapeProgrammaPreviewHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function isProgrammaPreviewUppercase(text) {
    var trimmed = $.trim(text);
    return /[\p{L}]/u.test(trimmed) && !/[\p{Ll}]/u.test(trimmed);
}

function detectProgrammaPreviewLevel(raw, currentParent) {
    var normalized = String(raw || '').replace(/\t/g, '  ').replace(/\s+$/u, '');
    var trimmed = normalized.replace(/^\s+/u, '');

    var bulletMatch = trimmed.match(/^(?:[•●▪◦◦]\s+|--\s+|>\s+|-\s+|\*\s+)(.+)$/u);
    if (bulletMatch) {
        return {
            level: currentParent !== null ? 1 : 0,
            text: $.trim(bulletMatch[1] || '')
        };
    }

    var indentMatch = normalized.match(/^ {2,}(.+)$/u);
    if (indentMatch) {
        return {
            level: currentParent !== null ? 1 : 0,
            text: $.trim(indentMatch[1] || '')
        };
    }

    return {
        level: 0,
        text: $.trim(trimmed)
    };
}

function buildProgrammaPreviewTree(text) {
    var lines = String(text || '').split(/\r\n|\r|\n/u);
    var tree = [];
    var currentParent = null;
    var nextIsChild = false;

    lines.forEach(function (line) {
        var rawLine = String(line || '').replace(/\s+$/u, '');
        if (rawLine === '') {
            nextIsChild = false;
            return;
        }

        var literalDotMap = {};
        var literalDotIndex = 0;
        rawLine = rawLine.replace(/\.{2,}/g, function (match) {
            var token = '__GESTORE_LITERAL_DOTS_' + literalDotIndex + '__';
            literalDotMap[token] = match === '..' ? '.' : match;
            literalDotIndex++;
            return token;
        });
        var segments = rawLine.split(/(?<!\.)\.(?!\.)\s*/u);
        segments.forEach(function (segment) {
            var restored = String(segment || '');
            Object.keys(literalDotMap).forEach(function (token) {
                restored = restored.split(token).join(literalDotMap[token]);
            });
            var raw = $.trim(restored);
            if (raw === '') {
                return;
            }

            var headingMatch = raw.match(/^>>\s*(.+)$/u);
            if (headingMatch) {
                var headingText = $.trim(String(headingMatch[1] || '').replace(/[.;:]\s*$/u, ''));
                tree.push({ type: 'heading', text: headingText, children: [] });
                currentParent = null;
                nextIsChild = false;
                return;
            }

            if (isProgrammaPreviewUppercase(raw)) {
                tree.push({ type: 'heading', text: raw.replace(/[.;:]\s*$/u, ''), children: [] });
                currentParent = null;
                nextIsChild = false;
                return;
            }

            raw = raw.replace(/;\s*$/u, '');
            var endsWithColon = /:\s*$/u.test(raw);
            var detected = detectProgrammaPreviewLevel(raw, currentParent);
            var textLi = detected.text;
            var level = detected.level;

            if (nextIsChild && level === 0 && currentParent !== null) {
                level = 1;
                nextIsChild = false;
            }

            if (level === 0) {
                tree.push({ type: 'item', text: textLi, children: [] });
                currentParent = tree.length - 1;
            } else {
                if (currentParent === null) {
                    tree.push({ type: 'item', text: '', children: [] });
                    currentParent = tree.length - 1;
                }
                tree[currentParent].children.push({ text: textLi });
            }

            if (endsWithColon) {
                nextIsChild = true;
            }
        });
    });

    return tree;
}

function renderProgrammaPreviewHtml(text) {
    var tree = buildProgrammaPreviewTree(text);
    if (!tree.length) {
        return '<span class="text-muted">Anteprima non disponibile: inizia a scrivere.</span>';
    }

    var html = '';
    var ulOpen = false;

    tree.forEach(function (node) {
        var type = node.type || 'item';
        if (type === 'heading') {
            if (ulOpen) {
                html += '</ul>';
                ulOpen = false;
            }
            html += '<p><strong>' + escapeProgrammaPreviewHtml(node.text || '') + '</strong></p>';
            return;
        }

        if (!ulOpen) {
            html += '<ul>';
            ulOpen = true;
        }

        html += '<li>' + escapeProgrammaPreviewHtml(node.text || '');
        if (node.children && node.children.length) {
            html += '<ul>';
            node.children.forEach(function (child) {
                html += '<li>' + escapeProgrammaPreviewHtml(child.text || '') + '</li>';
            });
            html += '</ul>';
        }
        html += '</li>';
    });

    if (ulOpen) {
        html += '</ul>';
    }

    return html;
}

function renderProgrammaPreviewLinesHtml(text) {
    var lines = String(text || '').split(/\r\n|\r|\n/u);
    if (!String(text || '').length) {
        return '<span class="text-muted">Le righe finiranno con il simbolo ↵.</span>';
    }

    return lines.map(function (line) {
        if (line === '') {
            return '<div class="programma-preview-line programma-preview-line-empty">riga vuota<span class="programma-preview-crlf">↵</span></div>';
        }
        return '<div class="programma-preview-line">' + escapeProgrammaPreviewHtml(line) + '<span class="programma-preview-crlf">↵</span></div>';
    }).join('');
}

function updateProgrammaFieldPreview(textareaSelector, previewSelector, linesSelector) {
    var value = $(textareaSelector).val() || '';
    $(previewSelector).html(renderProgrammaPreviewHtml(value));
    $(linesSelector).html(renderProgrammaPreviewLinesHtml(value));
}

function refreshProgrammaAllPreviews() {
    updateProgrammaFieldPreview('#contenuto', '#contenuto_preview', '#contenuto_lines');
    updateProgrammaFieldPreview('#competenze_raggiunte', '#competenze_raggiunte_preview', '#competenze_raggiunte_lines');
    updateProgrammaFieldPreview('#contenuti_trattati', '#contenuti_trattati_preview', '#contenuti_trattati_lines');
    updateProgrammaFieldPreview('#abilita_quinta', '#abilita_quinta_preview', '#abilita_quinta_lines');
    updateProgrammaFieldPreview('#metodologie_programma', '#metodologie_programma_preview', '#metodologie_programma_lines');
    updateProgrammaFieldPreview('#criteri_valutazione_programma', '#criteri_valutazione_programma_preview', '#criteri_valutazione_programma_lines');
    updateProgrammaFieldPreview('#testi_materiali_programma', '#testi_materiali_programma_preview', '#testi_materiali_programma_lines');
}

function bindProgrammaPreviewEvents() {
    $('#contenuto, #competenze_raggiunte, #contenuti_trattati, #abilita_quinta, #metodologie_programma, #criteri_valutazione_programma, #testi_materiali_programma')
        .off('input.programmaPreview')
        .on('input.programmaPreview', function () {
            refreshProgrammaAllPreviews();
        });
}

function renderProgrammaPreviewLinesHtml(text, activeLine) {
    var lines = String(text || '').split(/\r\n|\r|\n/u);
    if (!String(text || '').length) {
        return '<span class="text-muted">Qui vedi la riga corrente e quelle vicine, con `↵` a fine riga.</span>';
    }

    var safeActiveLine = parseInt(activeLine, 10);
    if (!safeActiveLine || safeActiveLine < 1) {
        safeActiveLine = 1;
    }

    var html = [];

    for (var i = 1; i <= lines.length; i++) {
        var line = lines[i - 1];
        var classes = 'programma-preview-line';
        if (i === safeActiveLine) {
            classes += ' programma-preview-line-active';
        }
        if (line === '') {
            classes += ' programma-preview-line-empty';
            html.push('<div class="' + classes + '">riga vuota<span class="programma-preview-crlf">↵</span></div>');
            continue;
        }
        html.push('<div class="' + classes + '">' + escapeProgrammaPreviewHtml(line) + '<span class="programma-preview-crlf">↵</span></div>');
    }

    return html.join('');
}

function getProgrammaTextareaLine(textarea) {
    if (!textarea) {
        return 1;
    }

    var caret = typeof textarea.selectionStart === 'number' ? textarea.selectionStart : String(textarea.value || '').length;
    return String(textarea.value || '').slice(0, caret).split(/\r\n|\r|\n/u).length;
}

function updateProgrammaFieldPreview(textareaSelector, previewSelector, linesSelector, activeLine) {
    var value = $(textareaSelector).val() || '';
    $(previewSelector).html(renderProgrammaPreviewHtml(value));
    $(linesSelector).html(renderProgrammaPreviewLinesHtml(value, activeLine));
    syncProgrammaPreviewScroll(textareaSelector, previewSelector, activeLine);
    syncProgrammaPreviewScroll(textareaSelector, linesSelector, activeLine);
}

function refreshProgrammaAllPreviews() {
    updateProgrammaFieldPreview('#contenuto', '#contenuto_preview', '#contenuto_lines', 1);
    updateProgrammaFieldPreview('#competenze_raggiunte', '#competenze_raggiunte_preview', '#competenze_raggiunte_lines', 1);
    updateProgrammaFieldPreview('#contenuti_trattati', '#contenuti_trattati_preview', '#contenuti_trattati_lines', 1);
    updateProgrammaFieldPreview('#abilita_quinta', '#abilita_quinta_preview', '#abilita_quinta_lines', 1);
    updateProgrammaFieldPreview('#metodologie_programma', '#metodologie_programma_preview', '#metodologie_programma_lines', 1);
    updateProgrammaFieldPreview('#criteri_valutazione_programma', '#criteri_valutazione_programma_preview', '#criteri_valutazione_programma_lines', 1);
    updateProgrammaFieldPreview('#testi_materiali_programma', '#testi_materiali_programma_preview', '#testi_materiali_programma_lines', 1);
}

function hideProgrammaFieldPreview() {
    activeProgrammaPreviewField = null;
    $('.programma-preview-row').removeClass('is-active');
    $('.programma-active-edit-group').removeClass('programma-active-edit-group');
    $('[id$="_preview_top_actions"]').hide();
    $('#programma_modal, #modulo_modal').removeClass('programma-editing-mode');
}

function showProgrammaFieldPreview(fieldId) {
    activeProgrammaPreviewField = fieldId;
    $('.programma-preview-row').removeClass('is-active');
    $('.programma-active-edit-group').removeClass('programma-active-edit-group');
    $('[id$="_preview_top_actions"]').hide();
    $('#' + fieldId + '_preview_top_actions').show();
    $('#' + fieldId + '_preview_row').addClass('is-active');
    $('#' + fieldId).closest('.form-group').addClass('programma-active-edit-group');
    if ($('#' + fieldId).closest('#programma_modal').length) {
        $('#programma_modal').addClass('programma-editing-mode');
        $('#modulo_modal').removeClass('programma-editing-mode');
    }
    if ($('#' + fieldId).closest('#modulo_modal').length) {
        $('#modulo_modal').addClass('programma-editing-mode');
        $('#programma_modal').removeClass('programma-editing-mode');
    }
}

function syncProgrammaFieldPreview(fieldId) {
    var $textarea = $('#' + fieldId);
    if (!$textarea.length) {
        return;
    }
    updateProgrammaFieldPreview('#' + fieldId, '#' + fieldId + '_preview', '#' + fieldId + '_lines', getProgrammaTextareaLine($textarea.get(0)));
}

function syncProgrammaPreviewScroll(textareaSelector, previewSelector, activeLine) {
    var textarea = $(textareaSelector).get(0);
    var preview = $(previewSelector).get(0);
    if (!textarea || !preview) {
        return;
    }

    var lines = String($(textareaSelector).val() || '').split(/\r\n|\r|\n/u);
    var totalLines = Math.max(lines.length, 1);
    var safeActiveLine = Math.max(1, Math.min(parseInt(activeLine, 10) || 1, totalLines));

    if (preview.scrollHeight <= preview.clientHeight) {
        preview.scrollTop = 0;
        return;
    }

    var ratio = totalLines <= 1 ? 0 : ((safeActiveLine - 1) / (totalLines - 1));
    var maxScroll = preview.scrollHeight - preview.clientHeight;
    preview.scrollTop = Math.max(0, Math.round(maxScroll * ratio) - 40);
}

function bindProgrammaPreviewEvents() {
    $('#contenuto, #competenze_raggiunte, #contenuti_trattati, #abilita_quinta, #metodologie_programma, #criteri_valutazione_programma, #testi_materiali_programma')
        .off('.programmaPreview')
        .on('focus.programmaPreview', function () {
            showProgrammaFieldPreview(this.id);
            syncProgrammaFieldPreview(this.id);
        })
        .on('input.programmaPreview keyup.programmaPreview click.programmaPreview', function () {
            showProgrammaFieldPreview(this.id);
            syncProgrammaFieldPreview(this.id);
        });

    $('.programma-preview-done')
        .off('click.programmaPreview')
        .on('click.programmaPreview', function () {
            var fieldId = $(this).data('preview-field');
            hideProgrammaFieldPreview();
            if (fieldId) {
                $('#' + fieldId).blur();
            }
        });
}

function aggiornaCampiModuloPerClasse() {
    var quinta = isProgrammaQuinta();
    var $contenutoGroup = $('#contenuto').closest('.form-group');

    if (quinta) {
        $contenutoGroup.hide();
        $('#quinta_fields_wrap').show();
    } else {
        $contenutoGroup.show();
        $('#quinta_fields_wrap').hide();
    }

    if (quinta) {
        $('#quinta_programma_fields_wrap').show();
    } else {
        $('#quinta_programma_fields_wrap').hide();
        pulisciCampiProgrammaQuinta();
    }

    refreshProgrammaAllPreviews();
}

function programmaSvoltoReadonly() {
    return $("#hidden_readonly").val() === 'true';
}

function applicaReadonlyProgrammaSvolto() {
    var readonly = programmaSvoltoReadonly();
    $("#btn-programma-save").toggle(!readonly);
    $("#btn-modulo-add").toggle(!readonly);
    $("#btn-modulo-import").toggle(!readonly);
    $("#btn-modulo-save").toggle(!readonly);
    $("#ordine, #titolo, #contenuto, #competenze_raggiunte, #contenuti_trattati, #abilita_quinta, #metodologie_programma, #criteri_valutazione_programma, #testi_materiali_programma").prop('disabled', readonly);
    if (readonly) {
        $("#classe, #docente, #materia").prop('disabled', true);
    }
    $('#classe').selectpicker('refresh');
    $('#docente').selectpicker('refresh');
    $('#materia').selectpicker('refresh');
    if (readonly) {
        $(".moduli_content button").hide();
    }
}

function buildProgrammaSvoltoForm(id_programma, format, viewScope) {
    var form = $('<form>', {
        action: 'stampaProgrammiSvolti.php',
        method: 'POST',
        target: '_blank'
    });
    viewScope = viewScope || 'full';

    form.append($('<input>', { type: 'hidden', name: 'id', value: id_programma }));
    form.append($('<input>', { type: 'hidden', name: 'print', value: 0 }));
    form.append($('<input>', { type: 'hidden', name: 'format', value: format }));
    form.append($('<input>', { type: 'hidden', name: 'view_scope', value: viewScope }));
    form.append($('<input>', { type: 'hidden', name: 'titolo', value: 'Programma svolto' }));

    return form;
}

$('#daCompletareCheckBox').change(function () {
    if (this.checked) {
        $da_completare_filtro_id = 1;
        $('#send_btn').show();
    } else {
        $da_completare_filtro_id = 0;
        $('#send_btn').hide();
    }
    programmiSvoltiReadRecords();
});

function programmiSvoltiReadRecords() {
    if ($("#hidden_docente_id").val() > 0) {
        $docenti_filtro_id = $("#hidden_docente_id").val();
    }
    $.get("programmiSvoltiReadRecords.php?classi_id=" + $classi_filtro_id + "&materia_id=" + $materia_filtro_id + "&docenti_id=" + $docenti_filtro_id + "&da_completare_id=" + $da_completare_filtro_id + "&anni_id=" + $anni_filtro_id, {}, function (data, status) {
        $(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
    });
}

function mostraOverlay() {
    $('#progressOverlay').show();
}

function nascondiOverlay() {
    $('#progressOverlay').hide();
}

function aggiornaProgressBar() {
    completati++;
    const percentuale = Math.round((completati / totale) * 100);
    $('#progressBar').css('width', percentuale + '%').text(percentuale + '%');

    if (completati === totale) {
        setTimeout(() => {
            nascondiOverlay();
            alert("Tutte le email sono state inviate!");
        }, 500);
    }
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function inviaSollecito(single_id) {
    if (single_id > 0) {
        totale = 1;
        completati = 0;
        await $.post("invioSollecito.php", {
            id: single_id
        }).then(response => {
            if (response.trim() !== 'sent') {
                console.error(`Errore per programma ID ${single_id}: ${response}`);
            }
        }).catch(err => {
            console.error(`Errore AJAX per studente ID ${single_id}:`, err);
        });
        aggiornaProgressBar();
        await sleep(Math.floor(Math.random() * 5000) + 1000);
    } else {
        const sollecito = $('#hidden_sollecito').val();
        const sollecito_array = sollecito.split(',');
        totale = sollecito_array.length;
        completati = 0;

        if (totale > 0) {
            mostraOverlay();

            for (const soll of sollecito_array) {
                await $.post("invioSollecito.php", {
                    id: soll
                }).then(response => {
                    if (response.trim() !== 'sent') {
                        console.error(`Errore per programma ID ${soll}: ${response}`);
                    }
                }).catch(err => {
                    console.error(`Errore AJAX per studente ID ${soll}:`, err);
                });

                aggiornaProgressBar();
                await sleep(Math.floor(Math.random() * 5000) + 1000);
            }
        } else {
            alert("Nessun sollecito da inviare!");
        }
    }
}

function moduliSvoltiReadRecords(programma_id) {
    $.get("../didattica/moduliSvoltiReadRecords.php", {
        programma_id: programma_id
    }, function (data, status) {
        $(".moduli_content").val("");
        $(".moduli_content").html(data);
        applicaReadonlyProgrammaSvolto();
    });
}

function programmiSvoltiGetDetails(programma_id, duplica, share, readonly) {
    if (typeof readonly === 'undefined') {
        readonly = 'false';
    }
    $("#hidden_programma_id").val(programma_id);
    $("#hidden_duplica").val(duplica);
    $("#hidden_share").val(share);
    $("#hidden_readonly").val(readonly);
    id_docente = $('#docente').val();
    if (duplica == 'true') {
        $("#myModalLabel1").html("Duplica il programma per un altra classe");
    } else if (share == 'true') {
        $("#myModalLabel1").html("Invia una copia del programma al codocente della classe");
    } else {
        $("#myModalLabel1").html("Programma svolto");
    }

    if (programma_id > 0) {
        $.post("../didattica/programmiSvoltiReadDetails.php", {
            programma_id: programma_id
        }, function (data, status) {
            var programma = JSON.parse(data);
            $('#hidden_programma_classe_anno').val(programma.programma_classe_anno || 0);

            if (duplica == 'true') {
                $('#classe').selectpicker('val', 0);
            } else {
                $('#classe').selectpicker('val', programma.programma_classe);
            }
            if (share == 'true') {
                $('#docente').selectpicker('val', 0);
            } else {
                $('#docente').selectpicker('val', programma.programma_iddocente);
            }

            $('#materia').selectpicker('val', programma.programma_idmateria);
            $('#metodologie_programma').val(programma.programma_metodologie || '');
            $('#criteri_valutazione_programma').val(programma.programma_criteri_valutazione || '');
            $('#testi_materiali_programma').val(programma.programma_testi_materiali || '');

            if (duplica == 'false') {
                $('#classe').attr('disabled', true);
            } else {
                $('#classe').attr('disabled', false);
            }
            if (share == 'false') {
                $('#docente').attr('disabled', true);
            } else {
                $('#docente').attr('disabled', false);
            }
            $('#materia').attr('disabled', true);
            $('#classe').selectpicker('refresh');
            $('#materia').selectpicker('refresh');
            $('#docente').selectpicker('refresh');
            aggiornaCampiModuloPerClasse();
            applicaReadonlyProgrammaSvolto();
            hideProgrammaFieldPreview();
        });
        moduliSvoltiReadRecords(programma_id);
    } else {
        $("#hidden_readonly").val('false');
        $('#hidden_programma_classe_anno').val(0);
        $('#classe').attr('disabled', false);
        if (id_docente != 0) {
            $('#docente').attr('disabled', true);
        } else {
            $('#docente').attr('disabled', false);
        }
        $('#materia').attr('disabled', false);
        $('#classe').val("0");
        $('#classe').selectpicker('refresh');
        $('#docente').val(id_docente);
        $('#docente').selectpicker('refresh');
        $('#materia').val("0");
        $('#materia').selectpicker('refresh');
        $(".moduli_content").html("");
        pulisciCampiProgrammaQuinta();
        aggiornaCampiModuloPerClasse();
        applicaReadonlyProgrammaSvolto();
        hideProgrammaFieldPreview();
    }
    $("#_error-programma-part").hide();
    $("#programma_modal").modal("show");
}

async function moduliSvoltiImport() {
    let programma_id = $("#hidden_programma_id").val();

    if (programma_id < 0) {
        programma_id = await new Promise((resolve, reject) => {
            $.post("programmiSvoltiSave.php", {
                id: '-1',
                docente_id: $("#docente").val(),
                classe_id: $("#classe").val(),
                materia_id: $("#materia").val(),
                duplica: 'false',
                share: 'false',
                overwrite: 'false'
            }, function (data, status) {
                $("#hidden_programma_id").val(data);
                resolve(data);
            }).fail(function (jqXHR, textStatus, errorThrown) {
                console.error("Errore nel salvataggio:", textStatus, errorThrown);
                reject(errorThrown);
            });
        });
    }

    if (programma_id > 0) {
        var conf = confirm("Sei sicuro di volere importare il programma iniziale? Verranno usati prima i moduli dello stesso docente, altrimenti quelli della stessa classe e materia. Eventuali moduli gia presenti saranno sovrascritti.");

        if (conf == true) {
            await new Promise((resolve, reject) => {
                $.post("../didattica/moduliSvoltiImport.php", {
                    programma_modulo_id: programma_id
                }, function (data, status) {
                    console.log("Importazione completata");
                    moduliSvoltiReadRecords($("#hidden_programma_id").val());
                    resolve();
                }).fail(function (jqXHR, textStatus, errorThrown) {
                    console.error("Errore nell'importazione:", textStatus, errorThrown);
                    alert(jqXHR.responseText || "Errore durante l'importazione dei moduli");
                    reject(errorThrown);
                });
            });
        }
    }
}

async function moduloSvoltiGetDetails(modulo_id) {
    if (programmaSvoltoReadonly()) {
        return;
    }
    let programma_id = $("#hidden_programma_id").val();

    if (programma_id < 0) {
        programma_id = await new Promise((resolve, reject) => {
            $.post("programmiSvoltiSave.php", {
                id: '-1',
                docente_id: $("#docente").val(),
                classe_id: $("#classe").val(),
                materia_id: $("#materia").val(),
                duplica: 'false',
                share: 'false',
                overwrite: 'false'
            }, function (data, status) {
                $("#hidden_programma_id").val(data);
                resolve(data);
            }).fail(function (jqXHR, textStatus, errorThrown) {
                console.error("Errore nel salvataggio:", textStatus, errorThrown);
                reject(errorThrown);
            });
        });
    }

    programma_id = $("#hidden_programma_id").val();
    $("#hidden_modulo_id").val(modulo_id);
    let nmoduli = $("#hidden_nmoduli").val();

    if (modulo_id > 0) {
        const data = await new Promise((resolve, reject) => {
            $.post("../didattica/moduloSvoltiReadDetails.php", {
                modulo_id: modulo_id
            }, function (data, status) {
                resolve(data);
            }).fail(function (jqXHR, textStatus, errorThrown) {
                console.error("Errore nel recupero dettagli modulo:", textStatus, errorThrown);
                reject(errorThrown);
            });
        });

        const programma = JSON.parse(data);
        $('#titolo').val(programma.modulo_nome);
        $('#ordine').val(programma.modulo_ordine);
        $('#contenuto').val(programma.modulo_contenuto);
        $('#hidden_programma_classe_anno').val(programma.programma_classe_anno || $('#hidden_programma_classe_anno').val() || 0);

        if (parseInt(programma.modulo_is_quinta_structured, 10) === 1) {
            $('#competenze_raggiunte').val(programma.modulo_competenze_raggiunte || '');
            $('#contenuti_trattati').val(programma.modulo_contenuti_trattati || '');
            $('#abilita_quinta').val(programma.modulo_abilita_quinta || '');
        } else {
            pulisciCampiQuinta();
        }
    } else {
        $('#titolo').val("");
        $('#ordine').val(parseInt(nmoduli, 10) + 1);
        $('#contenuto').val("");
        pulisciCampiQuinta();
        $("#moduli_content").html("");
    }

    aggiornaCampiModuloPerClasse();
    hideProgrammaFieldPreview();
    $("#_error-modulo-part").hide();
    $("#modulo_modal").modal("show");
}

function programmiSvoltiDelete(id, materia) {
    var conf = confirm("Sei sicuro di volere cancellare il programma di " + materia + " ?");
    if (conf == true) {
        $.post("../didattica/moduliElimina.php", {
            id: id
        });
        $.post("../common/deleteRecord.php", {
            id: id,
            table: 'programmi_svolti',
            name: "materia" + materia
        }, function (data, status) {
            programmiSvoltiReadRecords();
        });
    }
}

function programmiSvoltiPrint(id_programma) {
    buildProgrammaSvoltoForm(id_programma, 'pdf').appendTo('body').submit().remove();
}

function programmiSvoltiPrintSolo(id_programma) {
    buildProgrammaSvoltoForm(id_programma, 'pdf', 'own').appendTo('body').submit().remove();
}

function programmiSvoltiWord(id_programma) {
    buildProgrammaSvoltoForm(id_programma, 'docx').appendTo('body').submit().remove();
}

function programmiSvoltiWordClasse(id_classe, id_anno_scolastico) {
    var form = $('<form>', {
        action: 'stampaProgrammiSvolti.php',
        method: 'POST',
        target: '_blank'
    });

    form.append($('<input>', { type: 'hidden', name: 'class_id', value: id_classe }));
    form.append($('<input>', { type: 'hidden', name: 'anno_scolastico_id', value: id_anno_scolastico }));
    form.append($('<input>', { type: 'hidden', name: 'format', value: 'docx_classe' }));
    form.append($('<input>', { type: 'hidden', name: 'titolo', value: 'Programmi svolti classe quinta' }));

    form.appendTo('body').submit().remove();
}

function moduloSvoltiDelete(id, id_programma, titolo) {
    var conf = confirm("Sei sicuro di volere cancellare il modulo  " + titolo + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
            id: id,
            table: 'programmi_svolti_moduli',
            name: "nome" + titolo
        }, function (data, status) {
            moduliSvoltiReadRecords(id_programma);
        });
    }
}

function programmiSvoltiSave() {
    if (programmaSvoltoReadonly()) {
        return;
    }
    if ($("#docente").val() <= 0) {
        $("#_error-programma").text("Devi selezionare un docente");
        $("#_error-programma-part").show();
        return;
    }
    if ($("#classe").val() <= 0) {
        $("#_error-programma").text("Devi selezionare una classe");
        $("#_error-programma-part").show();
        return;
    }
    if ($("#materia").val() <= 0) {
        $("#_error-programma").text("Devi selezionare una materia");
        $("#_error-programma-part").show();
        return;
    }

    $("#_error-programma-part").hide();
    $('#hidden_programma_classe_anno').val(parseInt($('#classe option:selected').data('anno'), 10) || $('#hidden_programma_classe_anno').val() || 0);

    $.post("programmiSvoltiSave.php", {
        id: $("#hidden_programma_id").val(),
        docente_id: $("#docente").val(),
        classe_id: $("#classe").val(),
        materia_id: $("#materia").val(),
        duplica: $("#hidden_duplica").val(),
        share: $("#hidden_share").val(),
        metodologie_programma: $("#metodologie_programma").val(),
        criteri_valutazione_programma: $("#criteri_valutazione_programma").val(),
        testi_materiali_programma: $("#testi_materiali_programma").val()
    }, function (data, status) {
        if (String(data).indexOf('Programma') !== -1 && String(data).indexOf('esistente') !== -1) {
            if ($("#hidden_share").val() == 'true') {
                alert("Non puoi condividere il programma con il docente, perche ha gia un programma presente!");
            } else {
                alert("Esiste gia il programma nella classe di destinazione!");
            }
        } else {
            $("#programma_modal").modal("hide");
            programmiSvoltiReadRecords();
        }
    });
}

function moduloSvoltiSave() {
    if (programmaSvoltoReadonly()) {
        return;
    }
    var quinta = isProgrammaQuinta();

    if ($.trim($("#ordine").val()).length <= 0) {
        $("#_error-modulo").text("Devi indicare l'ordine del modulo, ad es. 1");
        $("#_error-modulo-part").show();
        return;
    }
    if ($.trim($("#titolo").val()).length <= 0) {
        $("#_error-modulo").text("Devi indicare il titolo del modulo");
        $("#_error-modulo-part").show();
        return;
    }
    if (!quinta && $.trim($("#contenuto").val()).length <= 0) {
        $("#_error-modulo").text("Devi indicare il contenuto");
        $("#_error-modulo-part").show();
        return;
    }
    if (quinta && $.trim($("#contenuti_trattati").val()).length <= 0) {
        $("#_error-modulo").text("Devi indicare almeno le conoscenze o contenuti trattati");
        $("#_error-modulo-part").show();
        return;
    }

    $("#_error-modulo-part").hide();

    $.post("moduloSvoltiSave.php", {
        id: $("#hidden_modulo_id").val(),
        id_programma: $("#hidden_programma_id").val(),
        ordine: $("#ordine").val(),
        titolo: $("#titolo").val(),
        contenuto: $("#contenuto").val(),
        competenze_raggiunte: $("#competenze_raggiunte").val(),
        contenuti_trattati: $("#contenuti_trattati").val(),
        abilita_quinta: $("#abilita_quinta").val(),
    }, function (data, status) {
        $("#modulo_modal").modal("hide");
        moduliSvoltiReadRecords($("#hidden_programma_id").val());
    });
}

$(document).ready(function () {
    bindProgrammaPreviewEvents();
    hideProgrammaFieldPreview();
    programmiSvoltiReadRecords();

    $("#classi_filtro").on("changed.bs.select", function () {
        $classi_filtro_id = this.value;
        programmiSvoltiReadRecords();
    });

    $("#anni_filtro").on("changed.bs.select", function () {
        $anni_filtro_id = this.value;
        programmiSvoltiReadRecords();
    });

    $('#send_btn').on('click', function () {
        inviaSollecito(-1);
    });

    $("#materia_filtro").on("changed.bs.select", function () {
        $materia_filtro_id = this.value;
        programmiSvoltiReadRecords();
    });

    $("#docente_filtro").on("changed.bs.select", function () {
        $docenti_filtro_id = this.value;
        programmiSvoltiReadRecords();
    });

    $("#classe").on("changed.bs.select", function () {
        $('#hidden_programma_classe_anno').val(parseInt($('#classe option:selected').data('anno'), 10) || 0);
        aggiornaCampiModuloPerClasse();
    });

    aggiornaCampiModuloPerClasse();
    applicaReadonlyProgrammaSvolto();
    hideProgrammaFieldPreview();
    $('#send_btn').hide();
});
