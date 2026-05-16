/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var $anno_filtro_id = 0;
var $indirizzo_filtro_id = 0;
var $materia_filtro_id = 0;
var programmaMateriaRichTextFields = ['conoscenze', 'abilita', 'competenze', 'periodo'];

function programmaMateriaLooksLikeHtml(text) {
    return /<\/?(p|div|ul|ol|li|h[1-6]|strong|b|em|i|u|blockquote|span)\b/i.test(String(text || ''));
}

function escapeProgrammaMateriaHtml(text) {
    return String(text || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function sanitizeProgrammaMateriaRichHtml(html) {
    var $tmp = $('<div>').html(String(html || ''));
    $tmp.find('script, style, meta, link, object, iframe').remove();
    $tmp.find('*').each(function () {
        var el = this;
        var style = String($(el).attr('style') || '').toLowerCase();
        var inner = $(el).html();
        if (/font-weight\s*:\s*(bold|[6-9]00)/.test(style)) {
            inner = '<strong>' + inner + '</strong>';
        }
        if (/font-style\s*:\s*italic/.test(style)) {
            inner = '<em>' + inner + '</em>';
        }
        if (/text-decoration[^;]*underline/.test(style)) {
            inner = '<u>' + inner + '</u>';
        }
        if (el.tagName === 'OL') {
            if (/list-style-type\s*:\s*lower-alpha/.test(style)) {
                $(el).attr('type', 'a');
            } else if (/list-style-type\s*:\s*upper-alpha/.test(style)) {
                $(el).attr('type', 'A');
            } else if (!$(el).attr('type')) {
                $(el).attr('type', '1');
            }
        }
        $(el).html(inner);
        $.each($.makeArray(el.attributes), function (_, attr) {
            var name = (attr.name || '').toLowerCase();
            if (name.indexOf('on') === 0 || name === 'class' || name === 'id' || name === 'style' || (name !== 'type' && name !== 'start')) {
                el.removeAttribute(attr.name);
            }
        });
    });

    $tmp.find('b').each(function () { $(this).replaceWith($('<strong>').html($(this).html())); });
    $tmp.find('i').each(function () { $(this).replaceWith($('<em>').html($(this).html())); });
    $tmp.find('h1,h2,h3,h5,h6').each(function () { $(this).replaceWith($('<h4>').html($(this).html())); });
    $tmp.find('font').each(function () { $(this).replaceWith($('<span>').html($(this).html())); });

    return $.trim($tmp.html() || '')
        .replace(/&nbsp;/gi, ' ')
        .replace(/__MODULE_TITLE__/g, '')
        .replace(/__SECTION_HEADING__/g, '');
}

function isProgrammaMateriaUppercase(text) {
    var trimmed = $.trim(text);
    return /[\p{L}]/u.test(trimmed) && !/[\p{Ll}]/u.test(trimmed);
}

function programmaMateriaLegacyTextToWordLikeHtml(text) {
    var lines = String(text || '').replace(/\r\n|\r/u, '\n').split('\n');
    var $root = $('<div>');

    lines.forEach(function (line) {
        var rawLine = String(line || '');
        if ($.trim(rawLine) === '') {
            return;
        }

        var literalDotMap = {};
        rawLine = rawLine.replace(/\.{2,}/g, function (match) {
            var token = '__GESTORE_LITERAL_DOTS_' + Object.keys(literalDotMap).length + '__';
            literalDotMap[token] = match;
            return token;
        });

        rawLine.split(/(?<!\.)\.(?!\.)\s*/u).forEach(function (segment) {
            var raw = $.trim(String(segment || '').replace(/__GESTORE_LITERAL_DOTS_\d+__/g, function (token) {
                return literalDotMap[token] || token;
            }));
            if (raw === '') {
                return;
            }

            var titleMatch = raw.match(/^>>\s*(.+)$/u);
            if (titleMatch || (isProgrammaMateriaUppercase(raw) && raw.length <= 90)) {
                $root.append($('<h4>').text(titleMatch ? titleMatch[1] : raw.replace(/[.;:]\s*$/u, '')));
                return;
            }

            raw = $.trim(raw.replace(/;\s*$/u, ''));
            raw = $.trim(raw.replace(/^(?:[\u2022\u00b7\u25cf\u25e6\u2043\uf0b7\uf0a7\uf076]\s+|--\s+|>\s+|-\s+|\*\s+|\d+[\.)]\s+|[a-zA-Z][\.)]\s+)/u, ''));
            if (raw !== '') {
                $root.append($('<p>').text(raw));
            }
        });
    });

    return sanitizeProgrammaMateriaRichHtml($root.html());
}

function programmaMateriaPlainTextToHtml(text) {
    return String(text || '').split(/\r\n|\r|\n/u).map(function (line) {
        return $.trim(line) === '' ? '' : '<p>' + escapeProgrammaMateriaHtml(line) + '</p>';
    }).join('');
}

function programmaMateriaNormalizeWordPasteHtml(html, text) {
    if (html) {
        return sanitizeProgrammaMateriaRichHtml(html);
    }
    return programmaMateriaLegacyTextToWordLikeHtml(text) || programmaMateriaPlainTextToHtml(text);
}

function programmaMateriaHtmlToPlainText(html) {
    var $tmp = $('<div>').html(sanitizeProgrammaMateriaRichHtml(html));
    $tmp.find('br').replaceWith('\n');
    $tmp.find('li,p,h4,div').each(function () { $(this).append('\n'); });
    return $.trim($tmp.text().replace(/\n{3,}/g, '\n\n'));
}

function updateProgrammaMateriaFieldPreview(fieldId) {
    var $editor = $('#' + fieldId + '_editor');
    var value = $editor.length ? sanitizeProgrammaMateriaRichHtml($editor.html() || '') : ($('#' + fieldId).val() || '');
    var preview = programmaMateriaLooksLikeHtml(value)
        ? sanitizeProgrammaMateriaRichHtml(value)
        : programmaMateriaLegacyTextToWordLikeHtml(value);
    $('#' + fieldId + '_preview').html(preview || '<span class="text-muted">Anteprima non disponibile: inizia a scrivere.</span>');
    $('#' + fieldId + '_lines').html('<span class="text-muted">' + escapeProgrammaMateriaHtml(programmaMateriaHtmlToPlainText(value)) + '</span>');
}

function syncProgrammaMateriaRichEditorToTextarea(fieldId) {
    var $textarea = $('#' + fieldId);
    var $editor = $('#' + fieldId + '_editor');
    if ($textarea.length && $editor.length) {
        $textarea.val(sanitizeProgrammaMateriaRichHtml($editor.html() || ''));
    }
}

function syncProgrammaMateriaRichEditorsToTextareas() {
    programmaMateriaRichTextFields.forEach(syncProgrammaMateriaRichEditorToTextarea);
}

function syncProgrammaMateriaRichEditorFromTextarea(fieldId) {
    var $textarea = $('#' + fieldId);
    var $editor = $('#' + fieldId + '_editor');
    if (!$textarea.length || !$editor.length) return;

    var value = $textarea.val() || '';
    var html = programmaMateriaLooksLikeHtml(value) ? sanitizeProgrammaMateriaRichHtml(value) : (programmaMateriaLegacyTextToWordLikeHtml(value) || programmaMateriaPlainTextToHtml(value));
    if ($.trim(value) !== '' && !programmaMateriaLooksLikeHtml(value)) {
        $textarea.val(html);
    }
    $editor.html(html);
    updateProgrammaMateriaFieldPreview(fieldId);
}

function syncProgrammaMateriaRichEditorsFromTextareas() {
    programmaMateriaRichTextFields.forEach(syncProgrammaMateriaRichEditorFromTextarea);
}

function hideProgrammaMateriaFieldPreview() {
    $('.programma-preview-row').removeClass('is-active').hide();
}

function showProgrammaMateriaFieldPreview(fieldId) {
    if ($('#' + fieldId + '_editor').attr('contenteditable') === 'false') {
        hideProgrammaMateriaFieldPreview();
        return;
    }
    $('.programma-preview-row').removeClass('is-active').hide();
    $('#' + fieldId + '_preview_row').addClass('is-active').show();
}

function execProgrammaMateriaEditorCommand(fieldId, command) {
    var $editor = $('#' + fieldId + '_editor');
    if (!$editor.length || $editor.attr('contenteditable') === 'false') return;
    $editor.focus();

    if (command === 'h4') {
        var block = window.getSelection && window.getSelection().anchorNode ? $(window.getSelection().anchorNode).closest('h4,p,div,li', $editor)[0] : null;
        document.execCommand('formatBlock', false, block && block.tagName && block.tagName.toLowerCase() === 'h4' ? 'p' : 'h4');
    } else if (command === 'orderedAlpha') {
        document.execCommand('insertOrderedList', false, null);
        var $ol = $(window.getSelection().anchorNode).closest('ol');
        if ($ol.length) $ol.attr('type', 'a');
    } else if (command === 'clear') {
        document.execCommand('removeFormat', false, null);
        document.execCommand('formatBlock', false, 'p');
    } else {
        document.execCommand(command, false, null);
    }

    syncProgrammaMateriaRichEditorToTextarea(fieldId);
    updateProgrammaMateriaFieldPreview(fieldId);
}

function setupProgrammaMateriaRichEditor(fieldId) {
    var $textarea = $('#' + fieldId);
    if (!$textarea.length || $('#' + fieldId + '_editor').length) return;

    var $toolbar = $('<div>', { class: 'programma-rich-toolbar word-like-toolbar', 'data-field': fieldId });
    [
        { cmd: 'bold', icon: '<span class="word-icon word-icon-bold">B</span>', title: 'Grassetto' },
        { cmd: 'italic', icon: '<span class="word-icon word-icon-italic">I</span>', title: 'Corsivo' },
        { cmd: 'underline', icon: '<span class="word-icon word-icon-underline">U</span>', title: 'Sottolineato' },
        { cmd: 'insertUnorderedList', icon: '<span class="word-icon word-icon-list">&bull;</span>', title: 'Elenco puntato' },
        { cmd: 'insertOrderedList', icon: '<span class="word-icon word-icon-list">1<br>2</span>', title: 'Elenco numerato' },
        { cmd: 'orderedAlpha', icon: '<span class="word-icon word-icon-list">a<br>b</span>', title: 'Elenco con lettere' },
        { cmd: 'outdent', icon: '<span class="glyphicon glyphicon-indent-right"></span>', title: 'Riduci rientro' },
        { cmd: 'indent', icon: '<span class="glyphicon glyphicon-indent-left"></span>', title: 'Aumenta rientro' },
        { cmd: 'h4', icon: '<span class="word-icon word-icon-title">T</span>', title: 'Titolo sezione' },
        { cmd: 'clear', icon: '<span class="glyphicon glyphicon-erase"></span>', title: 'Pulisci formattazione' }
    ].forEach(function (button) {
        $('<button>', {
            type: 'button',
            class: 'btn btn-default btn-xs programma-rich-btn',
            title: button.title,
            'data-command': button.cmd,
            html: button.icon
        }).appendTo($toolbar);
    });

    var $editor = $('<div>', {
        id: fieldId + '_editor',
        class: 'form-control programma-rich-editor',
        contenteditable: 'true',
        'data-field': fieldId
    });

    $textarea.after($editor);
    $editor.before($toolbar);
    $textarea.addClass('programma-rich-source').hide();
    syncProgrammaMateriaRichEditorFromTextarea(fieldId);

    $toolbar.on('mousedown', '.programma-rich-btn', function (event) {
        event.preventDefault();
        execProgrammaMateriaEditorCommand(fieldId, $(this).data('command'));
    });

    $editor
        .on('focus click mouseup keyup input', function () {
            if ($(this).attr('contenteditable') === 'false') {
                hideProgrammaMateriaFieldPreview();
                return;
            }
            syncProgrammaMateriaRichEditorToTextarea(fieldId);
            updateProgrammaMateriaFieldPreview(fieldId);
            showProgrammaMateriaFieldPreview(fieldId);
        })
        .on('paste', function (event) {
            if ($(this).attr('contenteditable') === 'false') {
                event.preventDefault();
                hideProgrammaMateriaFieldPreview();
                return;
            }
            var clipboard = event.originalEvent && event.originalEvent.clipboardData ? event.originalEvent.clipboardData : null;
            if (!clipboard) return;
            event.preventDefault();
            document.execCommand('insertHTML', false, programmaMateriaNormalizeWordPasteHtml(clipboard.getData('text/html'), clipboard.getData('text/plain')));
            syncProgrammaMateriaRichEditorToTextarea(fieldId);
            updateProgrammaMateriaFieldPreview(fieldId);
        });
}

function setupProgrammaMateriaRichEditors() {
    programmaMateriaRichTextFields.forEach(setupProgrammaMateriaRichEditor);
}

function programmiReadRecords() {
    $.get("programmiReadRecords.php?anno_id=" + $anno_filtro_id + "&indirizzo_id=" + $indirizzo_filtro_id + "&materia_id=" + $materia_filtro_id, {}, function (data, status) {
        $(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
    });
}

function moduliReadRecords(programma_id) {
    $.get("../didattica/moduliReadRecords.php", {
        programma_id: programma_id
    }, function (data, status) {
        $(".moduli_content").html(data);
    });

}

function setProgrammaModalEditable(canEdit) {
    var $modal = $("#programma_modal");
    var $fields = $modal.find("#anno, #indirizzo, #materia");
    $fields.prop("disabled", !canEdit);

    try { $modal.find("select.selectpicker").selectpicker("refresh"); } catch (e) {}

    $("#btnProgrammaClose").text(canEdit ? "Annulla" : "Chiudi");
    if (canEdit) {
        $("#btnProgrammaSave").prop("disabled", false).show();
    } else {
        $("#btnProgrammaSave").prop("disabled", true).hide();
    }
}

function programmaGetDetails(programma_id) {
    $("#hidden_programma_id").val(programma_id);
    setProgrammaModalEditable(false);

    if (programma_id > 0) {
        $.post("../didattica/programmaReadDetails.php", { programma_id: programma_id }, function (data, status) {
            var programma = (typeof data === "string") ? JSON.parse(data) : data;
            if (!programma || !programma.ok) {
                alert((programma && programma.error) ? programma.error : "Errore lettura programma.");
                return;
            }

            $('#anno').selectpicker('val', programma.programma_anno).selectpicker('refresh');
            $('#indirizzo').selectpicker('val', programma.programma_idindirizzo).selectpicker('refresh');
            $('#materia').selectpicker('val', programma.programma_idmateria).selectpicker('refresh');
            setProgrammaModalEditable(parseInt(programma.can_edit, 10) === 1);
            $("#programma_modal").modal("show");
        });

        moduliReadRecords(programma_id);
    } else {
        $('#anno').val("0").selectpicker('refresh');
        $('#indirizzo').val("0").selectpicker('refresh');
        $('#materia').val("0").selectpicker('refresh');
        setProgrammaModalEditable(true);
        $("#programma_modal").modal("show");
    }

    $("#_error-programma-part").hide();
}

function setModuloModalEditable(canEdit) {
    var $modal = $("#modulo_modal");

    // campi
    var $fields = $modal.find("input:not([type=hidden]), textarea, select");
    $fields.prop("disabled", !canEdit);
    $modal.find(".programma-rich-editor").attr("contenteditable", canEdit ? "true" : "false").toggleClass("disabled", !canEdit);
    $modal.find(".programma-rich-toolbar").toggle(!!canEdit);
    $modal.find(".programma-rich-btn").prop("disabled", !canEdit);
    $modal.find(".programma-edit-help").toggle(!!canEdit);
    if (!canEdit) {
        hideProgrammaMateriaFieldPreview();
    }

    // selectpicker refresh se presenti
    try { $modal.find("select.selectpicker").selectpicker("refresh"); } catch (e) {}

    // testo chiudi
    var $closeButtons = $("#btnModuloClose");
    if (!$closeButtons.length) {
        $closeButtons = $modal.find(".panel-footer .btn-default");
    }
    $closeButtons.text(canEdit ? "Annulla" : "Chiudi");

    // ✅ bottone salva: esiste sempre (grazie alla patch PHP)
    var $saveButtons = $("#btnModuloSave");
    if (!$saveButtons.length) {
        $saveButtons = $modal.find(".panel-footer .btn-primary, .panel-footer button[onclick='moduloSave()']");
    }
    if (canEdit) {
        $saveButtons.prop("disabled", false).show();
    } else {
        $saveButtons.prop("disabled", true).hide();
    }
}


function moduloGetDetails(modulo_id) {
    $("#hidden_modulo_id").val(modulo_id);
    var nmoduli = parseInt($("#hidden_nmoduli").val(), 10) || 0;

    // default: apro in read-only e poi eventualmente abilito
    setModuloModalEditable(false);

    $("#_error-modulo-part").hide();

    if (modulo_id > 0) {

        $.ajax({
            url: "../didattica/moduloReadDetails.php",
            type: "POST",
            dataType: "json",
            data: { modulo_id: modulo_id },
            success: function (programma) {

                if (!programma || !programma.ok) {
                    var msg = (programma && programma.error) ? programma.error : "Errore lettura modulo.";
                    alert(msg);
                    return;
                }

                $('#titolo').val(programma.modulo_nome || "");
                $('#ordine').val(programma.modulo_ordine || "");
                $('#conoscenze').val(programma.modulo_conoscenze || "");
                $('#abilita').val(programma.modulo_abilita || "");
                $('#competenze').val(programma.modulo_competenze || "");
                $('#periodo').val(programma.modulo_periodo || "");
                syncProgrammaMateriaRichEditorsFromTextareas();
                // ✅ qui la parte chiave: abilito se coordinatore (o segreteria/dirigente)
                setModuloModalEditable(parseInt(programma.can_edit, 10) === 1);

                $("#modulo_modal").modal("show");
            },
            error: function (xhr, st, err) {
                console.error("moduloReadDetails FAIL", st, err, xhr && xhr.responseText);
                alert("Errore lettura modulo (vedi console).");
            }
        });

    } else {
        // nuovo modulo
        $('#titolo').val("");
        $('#ordine').val(nmoduli + 1);
        $('#conoscenze').val("");
        $('#abilita').val("");
        $('#competenze').val("");
        $('#periodo').val("");
        syncProgrammaMateriaRichEditorsFromTextareas();
        setModuloModalEditable(true);
        // ⚠️ per il nuovo modulo serve comunque sapere se può editare:
        // se il tuo contesto “programma corrente” è noto (es. hidden_programma_id),
        // allora fai una chiamata a un endpoint che restituisce can_edit per quel programma.
        // Per ora (patch minima): lo lasciamo in read-only finché non mi dici come recuperi programma_id.

        $("#modulo_modal").modal("show");
    }
}

function programmaDelete(id, materia) {
    var conf = confirm("Sei sicuro di volere cancellare la materia di " + materia + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
            id: id,
            table: 'programma_materie',
            name: "materia" + materia
        },
            function (data, status) {
                programmiReadRecords();
            }
        );
    }
}

function moduloDelete(id, id_programma, titolo) {
    var conf = confirm("Sei sicuro di volere cancellare il modulo  " + titolo + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
            id: id,
            table: 'programma_moduli',
            name: "nome" + titolo
        },
            function (data, status) {
                moduliReadRecords(id_programma);
                 //$("#programma_modal").modal("hide");
            }
        );
    }
}

function programmaSave() {

    if ($("#anno").val() <= 0) {
        $("#_error-programma").text("Devi selezionare un anno");
        $("#_error-programma-part").show();
        return;
    }
    if ($("#indirizzo").val() <= 0) {
        $("#_error-programma").text("Devi selezionare un indirizzo");
        $("#_error-programma-part").show();
        return;
    }
    if ($("#materia").val() <= 0) {
        $("#_error-programma").text("Devi selezionare una materia");
        $("#_error-programma-part").show();
        return;
    }

    $("#_error-programma-part").hide();

    $.post("programmaSave.php", {
        id: $("#hidden_programma_id").val(),
        anno_id: $("#anno").val(),
        indirizzo_id: $("#indirizzo").val(),
        materia_id: $("#materia").val(),
    }, function (data, status) {
        $("#programma_modal").modal("hide");
        programmiReadRecords();
    });

}

function programmaPrint(id_programma) {
  // creo form nascosto
  var form = $('<form>', {
    action: 'stampaProgramma.php',
    method: 'POST',
    target: '_black'    // apre in un nuovo tab
  });
  // aggiungo i campi
  form.append($('<input>', {type:'hidden', name:'id',     value:id_programma}));
  form.append($('<input>', {type:'hidden', name:'print',  value:0}));
  form.append($('<input>', {type:'hidden', name:'titolo', value:'Programma didattico'}));
  // lo “submitto” e lo rimuovo
  form.appendTo('body').submit().remove();
}

function moduloSave() {
    syncProgrammaMateriaRichEditorsToTextareas();

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
    if ($.trim($("#conoscenze").val()).length <= 0) {
        $("#_error-modulo").text("Devi indicare tutte le conoscenze");
        $("#_error-modulo-part").show();
        return;
    }
    if ($.trim($("#abilita").val()).length <= 0) {
        $("#_error-modulo").text("Devi indicare almeno una abilità");
        $("#_error-modulo-part").show();
        return;
    }
    if ($.trim($("#competenze").val()).length <= 0) {
        $("#_error-modulo").text("Devi indicare almeno una competenza");
        $("#_error-modulo-part").show();
        return;
    }
    if ($.trim($("#periodo").val()).length <= 0) {
        $("#_error-modulo").text("Devi indicare il periodo di svolgimento");
        $("#_error-modulo-part").show();
        return;
    }
    $("#_error-modulo-part").hide();
    $.post("moduloSave.php", {
        id: $("#hidden_modulo_id").val(),
        id_programma: $("#hidden_programma_id").val(),
        ordine: $("#ordine").val(),
        titolo: $("#titolo").val(),
        conoscenze: $("#conoscenze").val(),
        abilita: $("#abilita").val(),
        competenze: $("#competenze").val(),
        periodo: $("#periodo").val()
    }, function (data, status) {
        $("#modulo_modal").modal("hide");
        $("#programma_modal").modal("show");
        moduliReadRecords($("#hidden_programma_id").val());
    });

}


$(document).ready(function () {

    setupProgrammaMateriaRichEditors();
    hideProgrammaMateriaFieldPreview();
    programmiReadRecords();

    $("#annoCorso_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $anno_filtro_id = this.value;
            programmiReadRecords();
        });

    $("#indirizzoCorso_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $indirizzo_filtro_id = this.value;
            programmiReadRecords();
        });

    $("#materia_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $materia_filtro_id = this.value;
            programmiReadRecords();
        });

});     
