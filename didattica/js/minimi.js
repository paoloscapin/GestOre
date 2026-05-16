/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var $anno_filtro_id = 0;
var $indirizzo_filtro_id = 0;
var $materia_filtro_id = 0;
var minimiRichTextFields = ['conoscenze', 'abilita'];

function minimiLooksLikeHtml(text) {
    return /<\/?(p|div|ul|ol|li|h[1-6]|strong|b|em|i|u|blockquote|span)\b/i.test(String(text || ''));
}

function escapeMinimiHtml(text) {
    return String(text || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function sanitizeMinimiRichHtml(html) {
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

function isMinimiUppercase(text) {
    var trimmed = $.trim(text);
    return /[\p{L}]/u.test(trimmed) && !/[\p{Ll}]/u.test(trimmed);
}

function minimiLegacyTextToWordLikeHtml(text) {
    var lines = String(text || '').replace(/\r\n|\r/u, '\n').split('\n');
    var $root = $('<div>');

    lines.forEach(function (line) {
        var raw = String(line || '');
        var trimmed = $.trim(raw);
        if (trimmed === '') {
            return;
        }

        var titleMatch = trimmed.match(/^>>\s*(.+)$/u);
        if (titleMatch || (isMinimiUppercase(trimmed) && trimmed.length <= 90)) {
            $root.append($('<h4>').text(titleMatch ? titleMatch[1] : trimmed.replace(/[.;:]\s*$/u, '')));
            return;
        }

        trimmed = $.trim(trimmed.replace(/^(?:[\u2022\u00b7\u25cf\u25e6\u2043\uf0b7\uf0a7\uf076]\s+|--\s+|>\s+|-\s+|\*\s+|\d+[\.)]\s+|[a-zA-Z][\.)]\s+)/u, ''));
        $root.append($('<p>').text(trimmed));
    });

    return sanitizeMinimiRichHtml($root.html());
}

function minimiPlainTextToHtml(text) {
    return String(text || '').split(/\r\n|\r|\n/u).map(function (line) {
        return $.trim(line) === '' ? '' : '<p>' + escapeMinimiHtml(line) + '</p>';
    }).join('');
}

function minimiNormalizeWordPasteHtml(html, text) {
    var textOnly = String(text || '');
    if (/^[\s\u00a0]*[\u2022\u00b7\u25cf\u25e6\u2043\uf0b7\uf0a7\uf076]\s*/mu.test(textOnly)) {
        var htmlText = String(html || '').toLowerCase();
        var directBold = /<(strong|b)\b/.test(htmlText) || /font-weight\s*:\s*(bold|[6-9]00)/.test(htmlText);
        var directItalic = /<(em|i)\b/.test(htmlText) || /font-style\s*:\s*italic/.test(htmlText);
        var directUnderline = /<u\b/.test(htmlText) || /text-decoration[^;]*underline/.test(htmlText);
        var directList = $('<ul>');
        textOnly.split(/\r\n|\r|\n/u).forEach(function (line) {
            var cleaned = $.trim(String(line || '').replace(/^[\s\u00a0]*[\u2022\u00b7\u25cf\u25e6\u2043\uf0b7\uf0a7\uf076]\s*/u, ''));
            if (cleaned !== '') {
                var $li = $('<li>').text(cleaned);
                if (directBold) $li.wrapInner('<strong></strong>');
                if (directItalic) $li.wrapInner('<em></em>');
                if (directUnderline) $li.wrapInner('<u></u>');
                directList.append($li);
            }
        });
        if (directList.children('li').length >= 2) {
            return sanitizeMinimiRichHtml(directList.prop('outerHTML'));
        }
    }

    if (html) {
        return sanitizeMinimiRichHtml(html);
    }
    return minimiLegacyTextToWordLikeHtml(text) || minimiPlainTextToHtml(text);
}

function renderMinimiPreviewHtml(text) {
    if (minimiLooksLikeHtml(text)) {
        var richHtml = sanitizeMinimiRichHtml(text);
        return richHtml !== '' ? richHtml : '<span class="text-muted">Anteprima non disponibile: inizia a scrivere.</span>';
    }
    return minimiLegacyTextToWordLikeHtml(text) || '<span class="text-muted">Anteprima non disponibile: inizia a scrivere.</span>';
}

function minimiHtmlToPlainText(html) {
    var $tmp = $('<div>').html(sanitizeMinimiRichHtml(html));
    $tmp.find('br').replaceWith('\n');
    $tmp.find('li,p,h4,div').each(function () { $(this).append('\n'); });
    return $.trim($tmp.text().replace(/\n{3,}/g, '\n\n'));
}

function syncMinimiRichEditorToTextarea(fieldId) {
    var $textarea = $('#' + fieldId);
    var $editor = $('#' + fieldId + '_editor');
    if ($textarea.length && $editor.length) {
        $textarea.val(sanitizeMinimiRichHtml($editor.html() || ''));
    }
}

function syncMinimiRichEditorsToTextareas() {
    minimiRichTextFields.forEach(syncMinimiRichEditorToTextarea);
}

function syncMinimiRichEditorFromTextarea(fieldId) {
    var $textarea = $('#' + fieldId);
    var $editor = $('#' + fieldId + '_editor');
    if (!$textarea.length || !$editor.length) return;

    var value = $textarea.val() || '';
    var html = minimiLooksLikeHtml(value) ? sanitizeMinimiRichHtml(value) : (minimiLegacyTextToWordLikeHtml(value) || minimiPlainTextToHtml(value));
    if ($.trim(value) !== '' && !minimiLooksLikeHtml(value)) {
        $textarea.val(html);
    }
    $editor.html(html);
    updateMinimiFieldPreview(fieldId);
}

function syncMinimiRichEditorsFromTextareas() {
    minimiRichTextFields.forEach(syncMinimiRichEditorFromTextarea);
}

function updateMinimiFieldPreview(fieldId) {
    var $editor = $('#' + fieldId + '_editor');
    var value = $editor.length ? sanitizeMinimiRichHtml($editor.html() || '') : ($('#' + fieldId).val() || '');
    $('#' + fieldId + '_preview').html(renderMinimiPreviewHtml(value));
    $('#' + fieldId + '_lines').html('<span class="text-muted">' + escapeMinimiHtml(minimiHtmlToPlainText(value)) + '</span>');
}

function execMinimiEditorCommand(fieldId, command) {
    var $editor = $('#' + fieldId + '_editor');
    if (!$editor.length) return;
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

    syncMinimiRichEditorToTextarea(fieldId);
    updateMinimiFieldPreview(fieldId);
}

function setupMinimiRichEditor(fieldId) {
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
    syncMinimiRichEditorFromTextarea(fieldId);

    $toolbar.on('mousedown', '.programma-rich-btn', function (event) {
        event.preventDefault();
        execMinimiEditorCommand(fieldId, $(this).data('command'));
    });

    $editor
        .on('focus click mouseup keyup input', function () {
            if ($(this).attr('contenteditable') === 'false') {
                $('#' + fieldId + '_preview_row').hide();
                return;
            }
            syncMinimiRichEditorToTextarea(fieldId);
            updateMinimiFieldPreview(fieldId);
            $('#' + fieldId + '_preview_row').show();
        })
        .on('paste', function (event) {
            if ($(this).attr('contenteditable') === 'false') {
                event.preventDefault();
                $('#' + fieldId + '_preview_row').hide();
                return;
            }
            var clipboard = event.originalEvent && event.originalEvent.clipboardData ? event.originalEvent.clipboardData : null;
            if (!clipboard) return;
            event.preventDefault();
            document.execCommand('insertHTML', false, minimiNormalizeWordPasteHtml(clipboard.getData('text/html'), clipboard.getData('text/plain')));
            syncMinimiRichEditorToTextarea(fieldId);
            updateMinimiFieldPreview(fieldId);
        });
}

function setupMinimiRichEditors() {
    minimiRichTextFields.forEach(setupMinimiRichEditor);
}

function programmiReadRecords() {
    $.get("programmaMinimiReadRecords.php?anno_id=" + $anno_filtro_id + "&indirizzo_id=" + $indirizzo_filtro_id + "&materia_id=" + $materia_filtro_id, {}, function (data, status) {
        $(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
    });
}

function moduliReadRecords(programma_id) {
    $.get("../didattica/moduliMinimiReadRecords.php", {
        programma_id: programma_id
    }, function (data, status) {
        $(".moduli_content").html(data);
    });

}

function setModuloModalEditable(canEdit) {
    var $modal = $("#modulo_modal");
    var $fields = $modal.find("input:not([type=hidden]), textarea, select");
    $fields.prop("disabled", !canEdit);
    $modal.find(".programma-rich-editor").attr("contenteditable", canEdit ? "true" : "false").toggleClass("disabled", !canEdit);
    $modal.find(".programma-rich-toolbar").toggle(!!canEdit);
    $modal.find(".programma-rich-toolbar .programma-rich-btn").prop("disabled", !canEdit);
    $modal.find(".programma-edit-help").toggle(!!canEdit);
    if (!canEdit) {
        $modal.find(".programma-preview-row").hide();
    }

    try { $modal.find("select.selectpicker").selectpicker("refresh"); } catch (e) {}

    $("#btnModuloClose").text(canEdit ? "Annulla" : "Chiudi");
    if (canEdit) {
        $("#btnModuloSave").prop("disabled", false).show();
    } else {
        $("#btnModuloSave").prop("disabled", true).hide();
    }
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
        $.post("../didattica/programmaMinimiReadDetails.php", {
            programma_id: programma_id
        }, function (data, status) {
            var programma = (typeof data === "string") ? JSON.parse(data) : data;
            if (!programma || !programma.ok) {
                alert((programma && programma.error) ? programma.error : "Errore lettura programma.");
                return;
            }
            $('#anno').selectpicker('val', programma.programma_anno);
            $('#indirizzo').selectpicker('val', programma.programma_idindirizzo);
            $('#materia').selectpicker('val', programma.programma_idmateria);
            setProgrammaModalEditable(parseInt(programma.can_edit, 10) === 1);
            $("#programma_modal").modal("show");
        });
        moduliReadRecords(programma_id);
    }
    else {
        $('#anno').val("0");
        $('#anno').selectpicker('refresh');
        $('#indirizzo').val("0");
        $('#indirizzo').selectpicker('refresh');
        $('#materia').val("0");
        $('#materia').selectpicker('refresh');
        setProgrammaModalEditable(true);
        $("#programma_modal").modal("show");
    }
    $("#_error-programma-part").hide();
}

function moduloGetDetails(modulo_id) {
    $("#hidden_modulo_id").val(modulo_id);
    nmoduli = parseInt($("#hidden_nmoduli").val(), 10) || 0;
    setModuloModalEditable(false);

    if (modulo_id > 0) {
        $.post("../didattica/moduloMinimiReadDetails.php", {
            modulo_id: modulo_id
        }, function (data, status) {
            var programma = (typeof data === "string") ? JSON.parse(data) : data;
            if (!programma || !programma.ok) {
                alert((programma && programma.error) ? programma.error : "Errore lettura modulo.");
                return;
            }
            $('#titolo').val(programma.modulo_nome);
            $('#ordine').val(programma.modulo_ordine);
            $('#conoscenze').val(programma.modulo_conoscenze);
            $('#abilita').val(programma.modulo_abilita);
            syncMinimiRichEditorsFromTextareas();
            setModuloModalEditable(parseInt(programma.can_edit, 10) === 1);
            $("#modulo_modal").modal("show");
        });
    }
    else {
            $('#titolo').val("");
            $('#ordine').val(nmoduli+1);
            $('#conoscenze').val("");
            $('#abilita').val("");
            syncMinimiRichEditorsFromTextareas();
            setModuloModalEditable(true);
            $("#modulo_modal").modal("show");
    }
    $("#_error-modulo-part").hide();
}

function programmaDelete(id, materia) {
    var conf = confirm("Sei sicuro di volere cancellare la materia di " + materia + " ?");
    if (conf == true) {
        $.post("../didattica/programmaMinimiDelete.php", {
            id: id
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
        $.post("../didattica/moduloMinimiDelete.php", {
            id: id
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

    $.post("programmaMinimiSave.php", {
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
    action: 'stampaProgrammaMinimi.php',
    method: 'POST',
    target: '_black'    // apre in un nuovo tab
  });
  // aggiungo i campi
  form.append($('<input>', {type:'hidden', name:'id',     value:id_programma}));
  form.append($('<input>', {type:'hidden', name:'print',  value:0}));
  form.append($('<input>', {type:'hidden', name:'titolo', value:'Programma obiettivi minimi'}));
  // lo “submitto” e lo rimuovo
  form.appendTo('body').submit().remove();
}

function moduloSave() {
    syncMinimiRichEditorsToTextareas();

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

    $("#_error-modulo-part").hide();
      console.log("salvataggio in corso");
    $.post("moduloMinimiSave.php", {
        id: $("#hidden_modulo_id").val(),
        id_programma: $("#hidden_programma_id").val(),
        ordine: $("#ordine").val(),
        titolo: $("#titolo").val(),
        conoscenze: $("#conoscenze").val(),
        abilita: $("#abilita").val()
    }, function (data, status) {
        $("#modulo_modal").modal("hide");
        $("#programma_modal").modal("show");
        moduliReadRecords($("#hidden_programma_id").val());
    });

}


$(document).ready(function () {

    setupMinimiRichEditors();
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
