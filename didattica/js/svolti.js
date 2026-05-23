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
var programmaRichTextFields = [
    'contenuto',
    'competenze_raggiunte',
    'contenuti_trattati',
    'abilita_quinta',
    'metodologie_programma',
    'criteri_valutazione_programma',
    'testi_materiali_programma'
];
var programmaEditorSavedRanges = {};

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
    if (typeof syncProgrammaRichEditorsFromTextareas === 'function') {
        syncProgrammaRichEditorsFromTextareas();
    }
}

function pulisciCampiProgrammaQuinta() {
    $('#metodologie_programma').val('');
    $('#criteri_valutazione_programma').val('');
    $('#testi_materiali_programma').val('');
    if (typeof syncProgrammaRichEditorsFromTextareas === 'function') {
        syncProgrammaRichEditorsFromTextareas();
    }
}

function escapeProgrammaPreviewHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function programmaLooksLikeHtml(text) {
    return /<\/?(p|br|ul|ol|li|strong|b|em|i|u|h4|blockquote)\b/i.test(String(text || ''));
}

function sanitizeProgrammaRichHtml(html) {
    var $tmp = $('<div>').html(String(html || ''));
    $tmp.find('script, style, meta, link, object, iframe').remove();
    $tmp.find('*').each(function () {
        var style = String($(this).attr('style') || '').toLowerCase();
        var inner = $(this).html();
        if (/font-weight\s*:\s*(bold|[6-9]00)/.test(style)) {
            inner = '<strong>' + inner + '</strong>';
        }
        if (/font-style\s*:\s*italic/.test(style)) {
            inner = '<em>' + inner + '</em>';
        }
        if (/text-decoration[^;]*underline/.test(style)) {
            inner = '<u>' + inner + '</u>';
        }
        if (this.tagName === 'OL') {
            if (/list-style-type\s*:\s*lower-alpha/.test(style)) {
                $(this).attr('type', 'a');
            } else if (/list-style-type\s*:\s*upper-alpha/.test(style)) {
                $(this).attr('type', 'A');
            } else {
                $(this).attr('type', '1');
            }
        }
        $(this).html(inner);
    });

    function cleanNode(node) {
        var allowed = ['P', 'BR', 'UL', 'OL', 'LI', 'STRONG', 'B', 'EM', 'I', 'U', 'H4', 'BLOCKQUOTE'];
        $(node).contents().each(function () {
            if (this.nodeType === 1) {
                if (allowed.indexOf(this.tagName) === -1) {
                    $(this).replaceWith($(this).contents());
                    return;
                }
                var element = this;
                var keepType = element.tagName === 'OL' && ['1', 'a', 'A'].indexOf(String($(element).attr('type') || '')) !== -1
                    ? String($(element).attr('type'))
                    : '';
                $.each(Array.prototype.slice.call(element.attributes), function () {
                    if (this && this.name) {
                        element.removeAttribute(this.name);
                    }
                });
                if (keepType !== '') {
                    element.setAttribute('type', keepType);
                }
                cleanNode(this);
            }
        });
    }

    cleanNode($tmp.get(0));
    $tmp.find('b').each(function () {
        $(this).replaceWith($('<strong>').html($(this).html()));
    });
    $tmp.find('i').each(function () {
        $(this).replaceWith($('<em>').html($(this).html()));
    });
    $tmp.find('li').each(function () {
        var li = this;
        var removed = false;
        $(li).contents().each(function () {
            if (removed) {
                return false;
            }
            if (this.nodeType === 3) {
                var cleaned = String(this.nodeValue || '').replace(/^[\s\u00a0]*[\u2022\u00b7\u25cf\u25e6\u2043\uf0b7\uf0a7\uf076][\s\u00a0]*/u, '');
                if (cleaned !== this.nodeValue) {
                    this.nodeValue = cleaned;
                    removed = true;
                }
            } else if (this.nodeType === 1) {
                var $child = $(this);
                var cleanedText = String($child.text() || '').replace(/^[\s\u00a0]*[\u2022\u00b7\u25cf\u25e6\u2043\uf0b7\uf0a7\uf076][\s\u00a0]*/u, '');
                if (cleanedText !== $child.text()) {
                    $child.text(cleanedText);
                    removed = true;
                }
            }
        });
    });

    return $.trim($tmp.html());
}

function programmaPlainTextToHtml(text) {
    var lines = String(text || '').split(/\r\n|\r|\n/u);
    var html = [];
    lines.forEach(function (line) {
        if ($.trim(line) === '') {
            return;
        }
        html.push('<p>' + escapeProgrammaPreviewHtml(line) + '</p>');
    });
    return html.join('');
}

function programmaLegacyTextToWordLikeHtml(text) {
    var lines = String(text || '').replace(/\r\n|\r/u, '\n').split('\n');
    var $root = $('<div>');
    var $currentList = null;
    var currentListKey = '';

    function closeList() {
        $currentList = null;
        currentListKey = '';
    }

    lines.forEach(function (line) {
        var raw = String(line || '');
        var trimmed = $.trim(raw);
        if (trimmed === '') {
            closeList();
            return;
        }

        var titleMatch = trimmed.match(/^>>\s*(.+)$/u);
        if (titleMatch) {
            closeList();
            $root.append($('<h4>').text(titleMatch[1]));
            return;
        }

        if (isProgrammaPreviewUppercase(trimmed) && trimmed.length <= 90) {
            closeList();
            $root.append($('<h4>').text(trimmed));
            return;
        }

        var listInfo = getProgrammaPlainListLineInfo(raw);
        if (listInfo) {
            var key = listInfo.list + ':' + (listInfo.type || '');
            if (!$currentList || currentListKey !== key) {
                $currentList = $('<' + listInfo.list + '>');
                if (listInfo.type) {
                    $currentList.attr('type', listInfo.type);
                }
                $root.append($currentList);
                currentListKey = key;
            }
            $currentList.append($('<li>').text(listInfo.text));
            return;
        }

        closeList();
        $root.append($('<p>').text(trimmed));
    });

    return sanitizeProgrammaRichHtml($root.html());
}

function getProgrammaWordListInfo(text) {
    var trimmed = $.trim(String(text || '').replace(/\u00a0/g, ' '));
    var match = trimmed.match(/^[\u2022\u00b7\u25cf\u25e6\u2043\uf0b7\uf0a7\uf076]\s*(.+)$/u);
    if (match) {
        return { list: 'ul', type: '', text: match[1] };
    }

    match = trimmed.match(/^(\d+)[\.\)]\s+(.+)$/u);
    if (match) {
        return { list: 'ol', type: '1', text: match[2] };
    }

    match = trimmed.match(/^([a-zA-Z])[\.\)]\s+(.+)$/u);
    if (match) {
        return { list: 'ol', type: 'a', text: match[2] };
    }

    return null;
}

function programmaBlockHadInlineStyle($block, tagNames) {
    return $block.is(tagNames) || $block.find(tagNames).length > 0;
}

function buildProgrammaListItemFromWordBlock($block, info) {
    var $li = $('<li>');
    var text = info.text;
    if (programmaBlockHadInlineStyle($block, 'strong, b')) {
        text = $('<strong>').text(text);
    } else if (programmaBlockHadInlineStyle($block, 'em, i')) {
        text = $('<em>').text(text);
    } else {
        text = document.createTextNode(text);
    }

    $li.append(text);
    if (programmaBlockHadInlineStyle($block, 'u')) {
        $li.html('<u>' + $li.html() + '</u>');
    }
    return $li;
}

function normalizeProgrammaWordListText(text) {
    return $.trim(String(text || '')
        .replace(/\u00a0/g, ' ')
        .replace(/^[\u2022\u00b7\u25cf\u25e6\u2043\uf0b7\uf0a7\uf076]\s*/u, '')
        .replace(/^\d+[\.\)]\s+/u, '')
        .replace(/^[a-zA-Z][\.\)]\s+/u, ''));
}

function normalizeProgrammaWordMsoLists(html) {
    if (!html || !/mso-list|MsoListParagraph/i.test(html)) {
        return '';
    }

    var $raw = $('<div>').html(String(html));
    var $blocks = $raw.find('p, div').filter(function () {
        var style = String($(this).attr('style') || '');
        var className = String($(this).attr('class') || '');
        return /mso-list/i.test(style) || /MsoListParagraph/i.test(className);
    });
    if (!$blocks.length) {
        return '';
    }

    var $result = $('<div>');
    var $currentList = null;
    var converted = false;

    $blocks.each(function () {
        var $block = $(this).clone();
        $block.find('span').filter(function () {
            return /mso-list\s*:\s*Ignore/i.test(String($(this).attr('style') || ''));
        }).remove();

        var text = normalizeProgrammaWordListText($block.text());
        if (text === '') {
            return;
        }

        if (!$currentList) {
            $currentList = $('<ul>');
            $result.append($currentList);
        }

        var $li = $('<li>');
        var $content = $('<span>').html(sanitizeProgrammaRichHtml($block.html()));
        if ($content.text() !== text) {
            $content.text(text);
            if (programmaBlockHadInlineStyle($block, 'strong, b')) {
                $content.wrapInner('<strong></strong>');
            }
            if (programmaBlockHadInlineStyle($block, 'em, i')) {
                $content.wrapInner('<em></em>');
            }
            if (programmaBlockHadInlineStyle($block, 'u')) {
                $content.wrapInner('<u></u>');
            }
        }
        $li.html($content.html());
        $currentList.append($li);
        converted = true;
    });

    return converted ? sanitizeProgrammaRichHtml($result.html()) : '';
}

function getProgrammaPlainListLineInfo(line) {
    var raw = String(line || '').replace(/\u00a0/g, ' ');
    if ($.trim(raw) === '') {
        return null;
    }

    var indentMatch = raw.match(/^(\s*)/);
    var indent = indentMatch ? indentMatch[1].replace(/\t/g, '    ').length : 0;
    var trimmed = $.trim(raw);
    var match = trimmed.match(/^[\u2022\u00b7\u25cf\u25e6\u2043\uf0b7\uf0a7\uf076]\s*(.+)$/u);
    if (match) {
        return { list: 'ul', type: '', level: indent >= 2 ? 1 : 0, text: $.trim(match[1]) };
    }

    match = trimmed.match(/^(\d+)[\.\)]\s+(.+)$/u);
    if (match) {
        return { list: 'ol', type: '1', level: indent >= 2 ? 1 : 0, text: $.trim(match[2]) };
    }

    match = trimmed.match(/^([a-zA-Z])[\.\)]\s+(.+)$/u);
    if (match) {
        return { list: 'ol', type: 'a', level: indent >= 2 ? 1 : 0, text: $.trim(match[2]) };
    }

    return null;
}

function normalizeProgrammaWordPlainLists(text, html) {
    var lines = String(text || '').split(/\r\n|\r|\n/u);
    var infos = [];
    var listLines = 0;
    lines.forEach(function (line) {
        var info = getProgrammaPlainListLineInfo(line);
        infos.push({ line: line, info: info });
        if (info) {
            listLines++;
        }
    });

    if (listLines < 2) {
        return '';
    }

    var htmlText = String(html || '').toLowerCase();
    var allBold = /<(strong|b)\b/.test(htmlText) || /font-weight\s*:\s*(bold|[6-9]00)/.test(htmlText);
    var allItalic = /<(em|i)\b/.test(htmlText) || /font-style\s*:\s*italic/.test(htmlText);
    var allUnderline = /<u\b/.test(htmlText) || /text-decoration[^;]*underline/.test(htmlText);
    var $root = $('<div>');
    var lists = {};

    function closeDeeper(level) {
        Object.keys(lists).forEach(function (key) {
            if (parseInt(key, 10) > level) {
                delete lists[key];
            }
        });
    }

    infos.forEach(function (entry) {
        var info = entry.info;
        if (!info) {
            closeDeeper(-1);
            var textLine = $.trim(entry.line);
            if (textLine !== '') {
                $root.append($('<p>').text(textLine));
            }
            return;
        }

        closeDeeper(info.level);
        var key = String(info.level);
        var $list = lists[key];
        if (!$list || $list.prop('tagName').toLowerCase() !== info.list || String($list.attr('type') || '') !== info.type) {
            $list = $('<' + info.list + '>');
            if (info.type) {
                $list.attr('type', info.type);
            }
            if (info.level > 0 && lists[String(info.level - 1)] && lists[String(info.level - 1)].children('li').last().length) {
                lists[String(info.level - 1)].children('li').last().append($list);
            } else {
                $root.append($list);
            }
            lists[key] = $list;
        }

        var $li = $('<li>').text(info.text);
        if (allBold) {
            $li.wrapInner('<strong></strong>');
        }
        if (allItalic) {
            $li.wrapInner('<em></em>');
        }
        if (allUnderline) {
            $li.wrapInner('<u></u>');
        }
        $list.append($li);
    });

    return sanitizeProgrammaRichHtml($root.html());
}

function normalizeProgrammaWordPasteHtml(html, text) {
    var textOnly = String(text || '');
    if (/^[\s\u00a0]*[\u2022\u00b7\u25cf\u25e6\u2043\uf0b7\uf0a7\uf076]\s*/mu.test(textOnly)) {
        var htmlText = String(html || '').toLowerCase();
        var directBold = /<(strong|b)\b/.test(htmlText) || /font-weight\s*:\s*(bold|[6-9]00)/.test(htmlText);
        var directItalic = /<(em|i)\b/.test(htmlText) || /font-style\s*:\s*italic/.test(htmlText);
        var directUnderline = /<u\b/.test(htmlText) || /text-decoration[^;]*underline/.test(htmlText);
        var directList = $('<ul>');
        textOnly.split(/\r\n|\r|\n/u).forEach(function (line) {
            var info = getProgrammaPlainListLineInfo(line);
            if (info && info.list === 'ul') {
                var $li = $('<li>').text(info.text);
                if (directBold) {
                    $li.wrapInner('<strong></strong>');
                }
                if (directItalic) {
                    $li.wrapInner('<em></em>');
                }
                if (directUnderline) {
                    $li.wrapInner('<u></u>');
                }
                directList.append($li);
            }
        });
        if (directList.children('li').length >= 2) {
            return sanitizeProgrammaRichHtml(directList.prop('outerHTML'));
        }
    }

    var plainListHtml = normalizeProgrammaWordPlainLists(text, html);
    if (plainListHtml !== '') {
        return plainListHtml;
    }

    var msoListHtml = normalizeProgrammaWordMsoLists(html);
    if (msoListHtml !== '') {
        return msoListHtml;
    }

    var cleanHtml = html ? sanitizeProgrammaRichHtml(html) : programmaPlainTextToHtml(text);
    var $tmp = $('<div>').html(cleanHtml);
    var $children = $tmp.children('p, div, h4');
    if (!$children.length || $tmp.find('ul, ol').length) {
        return cleanHtml;
    }

    var $result = $('<div>');
    var $currentList = null;
    var currentKey = '';
    var converted = false;

    $children.each(function () {
        var $block = $(this);
        var info = getProgrammaWordListInfo($block.text());
        if (!info) {
            $currentList = null;
            currentKey = '';
            $result.append($block.clone());
            return;
        }

        converted = true;
        var key = info.list + ':' + info.type;
        if (!$currentList || key !== currentKey) {
            $currentList = $('<' + info.list + '>');
            if (info.type) {
                $currentList.attr('type', info.type);
            }
            $result.append($currentList);
            currentKey = key;
        }
        $currentList.append(buildProgrammaListItemFromWordBlock($block, info));
    });

    return converted ? sanitizeProgrammaRichHtml($result.html()) : cleanHtml;
}

function programmaHtmlToPlainText(html) {
    var $tmp = $('<div>').html(sanitizeProgrammaRichHtml(html));
    $tmp.find('br').replaceWith('\n');
    $tmp.find('blockquote').each(function () {
        $(this).prepend('  ');
        $(this).append('\n');
    });
    $tmp.find('p, li, h4').each(function () {
        $(this).append('\n');
    });
    return $.trim($tmp.text().replace(/\n{3,}/g, '\n\n'));
}

function getProgrammaFieldValue(fieldId) {
    var $editor = $('#' + fieldId + '_editor');
    if ($editor.length) {
        var editorHtml = $editor.html() || '';
        if ($editor.data('richMode') === 'html' || programmaLooksLikeHtml(editorHtml)) {
            return sanitizeProgrammaRichHtml(editorHtml);
        }
        return $editor.text();
    }
    return $('#' + fieldId).val() || '';
}

function syncProgrammaRichEditorToTextarea(fieldId) {
    var $textarea = $('#' + fieldId);
    var $editor = $('#' + fieldId + '_editor');
    if (!$textarea.length || !$editor.length) {
        return;
    }

    var editorHtml = $editor.html() || '';
    if ($editor.data('richMode') === 'html' || programmaLooksLikeHtml(editorHtml)) {
        $textarea.val(sanitizeProgrammaRichHtml(editorHtml));
    } else {
        $textarea.val($editor.text());
    }
}

function syncProgrammaRichEditorsToTextareas() {
    programmaRichTextFields.forEach(syncProgrammaRichEditorToTextarea);
}

function syncProgrammaRichEditorFromTextarea(fieldId) {
    var $textarea = $('#' + fieldId);
    var $editor = $('#' + fieldId + '_editor');
    if (!$textarea.length || !$editor.length) {
        return;
    }

    var value = $textarea.val() || '';
    if (programmaLooksLikeHtml(value)) {
        $editor.html(sanitizeProgrammaRichHtml(value));
        $editor.data('richMode', 'html');
    } else {
        var migratedHtml = value !== '' ? programmaLegacyTextToWordLikeHtml(value) : '';
        if (migratedHtml !== '') {
            $editor.html(migratedHtml);
            $textarea.val(migratedHtml);
            $editor.data('richMode', 'html');
        } else {
            $editor.text(value);
            $editor.data('richMode', 'text');
        }
    }
}

function syncProgrammaRichEditorsFromTextareas() {
    programmaRichTextFields.forEach(syncProgrammaRichEditorFromTextarea);
}

function markProgrammaEditorRich(fieldId) {
    $('#' + fieldId + '_editor').data('richMode', 'html');
}

function saveProgrammaEditorSelection(fieldId) {
    var editor = document.getElementById(fieldId + '_editor');
    var selection = window.getSelection ? window.getSelection() : null;
    if (!editor || !selection || selection.rangeCount === 0) {
        return;
    }

    var range = selection.getRangeAt(0);
    if (editor.contains(range.commonAncestorContainer)) {
        programmaEditorSavedRanges[fieldId] = range.cloneRange();
    }
}

function restoreProgrammaEditorSelection(fieldId) {
    var range = programmaEditorSavedRanges[fieldId];
    var selection = window.getSelection ? window.getSelection() : null;
    if (!range || !selection) {
        return;
    }

    selection.removeAllRanges();
    selection.addRange(range);
}

function getProgrammaCurrentList(fieldId) {
    var editor = document.getElementById(fieldId + '_editor');
    var selection = window.getSelection ? window.getSelection() : null;
    if (!editor || !selection || selection.rangeCount === 0) {
        return $();
    }

    var node = selection.getRangeAt(0).startContainer;
    return $(node && node.nodeType === 3 ? node.parentNode : node).closest('ol, ul', editor);
}

function programmaSelectionNode(fieldId) {
    var editor = document.getElementById(fieldId + '_editor');
    var selection = window.getSelection ? window.getSelection() : null;
    if (!editor || !selection || selection.rangeCount === 0) {
        return null;
    }

    var node = selection.getRangeAt(0).startContainer;
    if (node && node.nodeType === 3) {
        node = node.parentNode;
    }
    return editor.contains(node) ? node : null;
}

function clearProgrammaEditorFormatting(fieldId) {
    var editor = document.getElementById(fieldId + '_editor');
    var selection = window.getSelection ? window.getSelection() : null;
    if (!editor || !selection || selection.rangeCount === 0) {
        return;
    }

    var range = selection.getRangeAt(0);
    if (!editor.contains(range.commonAncestorContainer)) {
        return;
    }

    var $blocks = $();
    if (!range.collapsed) {
        $(editor).find('li, h4, p, blockquote').each(function () {
            try {
                if (range.intersectsNode(this)) {
                    $blocks = $blocks.add(this);
                }
            } catch (e) {
                // Browser vecchi: se intersectsNode non è disponibile, uso il blocco corrente.
            }
        });
    }

    if (!$blocks.length) {
        var node = programmaSelectionNode(fieldId);
        $blocks = $(node).closest('li, h4, p, blockquote', editor);
    }

    $blocks.each(function () {
        var $block = $(this);
        var text = $.trim($block.text());
        var $p = $('<p>').text(text);

        if ($block.is('li')) {
            var $list = $block.closest('ol, ul');
            $list.before($p);
            $block.remove();
            if (!$list.children('li').length) {
                $list.remove();
            }
        } else {
            $block.replaceWith($p);
        }
    });

    if (!$blocks.length && !range.collapsed) {
        document.execCommand('removeFormat', false, null);
    }
}

function updateProgrammaToolbarState(fieldId) {
    var $toolbar = $('.programma-rich-toolbar[data-field="' + fieldId + '"]');
    var node = programmaSelectionNode(fieldId);
    if (!$toolbar.length || !node) {
        return;
    }

    var $node = $(node);
    var $list = $node.closest('ol, ul');
    var listType = $list.is('ol') ? String($list.attr('type') || '1').toLowerCase() : '';
    var states = {
        bold: $node.closest('strong, b').length || document.queryCommandState('bold'),
        italic: $node.closest('em, i').length || document.queryCommandState('italic'),
        underline: $node.closest('u').length || document.queryCommandState('underline'),
        h4: $node.closest('h4').length > 0,
        insertUnorderedList: $list.is('ul'),
        orderedDecimal: $list.is('ol') && listType !== 'a',
        orderedAlpha: $list.is('ol') && listType === 'a'
    };

    $toolbar.find('.programma-rich-btn').removeClass('active');
    $.each(states, function (command, isActive) {
        $toolbar.find('[data-command="' + command + '"]').toggleClass('active', !!isActive);
    });
}

function placeProgrammaCursorInside(element) {
    if (!element || !window.getSelection || !document.createRange) {
        return;
    }

    var range = document.createRange();
    range.selectNodeContents(element);
    range.collapse(false);
    var selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
}

function toggleProgrammaEditorTitle(fieldId) {
    var editor = document.getElementById(fieldId + '_editor');
    var node = programmaSelectionNode(fieldId);
    if (!editor || !node) {
        return;
    }

    var $node = $(node);
    var $title = $node.closest('h4', editor);
    if ($title.length) {
        var $pTitle = $('<p>').html($title.html());
        $title.replaceWith($pTitle);
        placeProgrammaCursorInside($pTitle.get(0));
        return;
    }

    var $li = $node.closest('li', editor);
    if ($li.length) {
        var $list = $li.closest('ol, ul');
        var listTag = $list.prop('tagName').toLowerCase();
        var listType = $list.attr('type');
        var $beforeList = $('<' + listTag + '>');
        var $afterList = $('<' + listTag + '>');
        if (listType) {
            $beforeList.attr('type', listType);
            $afterList.attr('type', listType);
        }
        var $h4 = $('<h4>').html($li.html());
        var found = false;

        $list.children('li').each(function () {
            var $item = $(this);
            if ($item.is($li)) {
                found = true;
                return;
            }
            if (found) {
                $afterList.append($item.clone());
            } else {
                $beforeList.append($item.clone());
            }
        });

        if ($beforeList.children('li').length) {
            $list.before($beforeList);
        }
        $list.before($h4);
        if ($afterList.children('li').length) {
            $list.before($afterList);
        }
        $list.remove();
        placeProgrammaCursorInside($h4.get(0));
        return;
    }

    var $block = $node.closest('p, div, blockquote', editor);
    if ($block.length && !$block.is(editor)) {
        var $h4Block = $('<h4>').html($block.html());
        $block.replaceWith($h4Block);
        placeProgrammaCursorInside($h4Block.get(0));
        return;
    }

    document.execCommand('formatBlock', false, 'h4');
}

function execProgrammaEditorCommand(fieldId, command) {
    var editor = document.getElementById(fieldId + '_editor');
    if (!editor) {
        return;
    }
    editor.focus();
    restoreProgrammaEditorSelection(fieldId);
    markProgrammaEditorRich(fieldId);

    if (command === 'h4') {
        toggleProgrammaEditorTitle(fieldId);
    } else if (command === 'orderedDecimal' || command === 'orderedAlpha') {
        document.execCommand('insertOrderedList', false, null);
        var $list = getProgrammaCurrentList(fieldId);
        if ($list.length && $list.is('ol')) {
            $list.attr('type', command === 'orderedAlpha' ? 'a' : '1');
        }
    } else if (command === 'clear') {
        clearProgrammaEditorFormatting(fieldId);
    } else {
        document.execCommand(command, false, null);
    }

    setTimeout(function () {
        syncProgrammaRichEditorToTextarea(fieldId);
        syncProgrammaFieldPreview(fieldId);
        updateProgrammaToolbarState(fieldId);
    }, 0);
    saveProgrammaEditorSelection(fieldId);
}

function pasteProgrammaWordLikeContent(fieldId, event) {
    var clipboard = event.originalEvent && event.originalEvent.clipboardData ? event.originalEvent.clipboardData : null;
    if (!clipboard) {
        return;
    }

    var html = clipboard.getData('text/html');
    var text = clipboard.getData('text/plain');
    if (!html && !text) {
        return;
    }

    event.preventDefault();
    markProgrammaEditorRich(fieldId);
    var cleanHtml = normalizeProgrammaWordPasteHtml(html, text);
    document.execCommand('insertHTML', false, cleanHtml);
    syncProgrammaRichEditorToTextarea(fieldId);
    syncProgrammaFieldPreview(fieldId);
}

function setupProgrammaRichEditor(fieldId) {
    var $textarea = $('#' + fieldId);
    if (!$textarea.length || $('#' + fieldId + '_editor').length) {
        return;
    }

    var $toolbar = $('<div>', { class: 'programma-rich-toolbar', 'data-field': fieldId });
    [
        { cmd: 'bold', label: 'B', title: 'Grassetto' },
        { cmd: 'italic', label: 'I', title: 'Corsivo' },
        { cmd: 'underline', label: 'U', title: 'Sottolineato' },
        { cmd: 'insertUnorderedList', label: '• Lista', title: 'Lista puntata' },
        { cmd: 'indent', label: '↳', title: 'Sottopunto / aumenta rientro' },
        { cmd: 'outdent', label: '↰', title: 'Riduci rientro' },
        { cmd: 'h4', label: 'Titolo', title: 'Titolo sezione' },
        { cmd: 'clear', label: 'Pulisci', title: 'Rimuovi formattazione' }
    ].forEach(function (button) {
        $('<button>', {
            type: 'button',
            class: 'btn btn-default btn-xs programma-rich-btn',
            text: button.label,
            title: button.title,
            'data-command': button.cmd
        }).appendTo($toolbar);
    });

    var $listButton = $toolbar.find('[data-command="insertUnorderedList"]');
    [
        { cmd: 'orderedDecimal', label: 'Lista 1.', title: 'Elenco numerato: crea una lista 1, 2, 3' },
        { cmd: 'orderedAlpha', label: 'Lista a.', title: 'Elenco alfabetico: crea una lista a, b, c' }
    ].forEach(function (button) {
        $listButton = $('<button>', {
            type: 'button',
            class: 'btn btn-default btn-xs programma-rich-btn',
            text: button.label,
            title: button.title,
            'data-command': button.cmd
        }).insertAfter($listButton);
    });

    $toolbar.find('[data-command="bold"]').attr('title', 'Grassetto: rende in grassetto il testo selezionato');
    $toolbar.find('[data-command="italic"]').attr('title', 'Corsivo: rende in corsivo il testo selezionato');
    $toolbar.find('[data-command="underline"]').attr('title', 'Sottolineato: sottolinea il testo selezionato');
    $toolbar.find('[data-command="insertUnorderedList"]').text('Lista puntata').attr('title', 'Elenco puntato: crea o rimuove una lista con pallini');
    $toolbar.find('[data-command="indent"]').text('Aumenta rientro').attr('title', 'Aumenta rientro: trasforma la voce in sottopunto o aumenta il margine');
    $toolbar.find('[data-command="outdent"]').text('Riduci rientro').attr('title', 'Riduci rientro: riporta la voce al livello precedente');
    $toolbar.find('[data-command="h4"]').attr('title', 'Titolo: trasforma la riga corrente in titolo o la riporta a testo normale');
    $toolbar.find('[data-command="clear"]').attr('title', 'Pulisci formattazione: elimina grassetto, corsivo, sottolineato e formato titolo');
    if ($.fn.tooltip) {
        $toolbar.find('[title]').tooltip({ container: 'body' });
    }

    $toolbar
        .empty()
        .addClass('word-like-toolbar');
    [
        [
            { cmd: 'bold', icon: '<span class="word-icon word-icon-bold">B</span>', title: 'Grassetto: rende in grassetto il testo selezionato' },
            { cmd: 'italic', icon: '<span class="word-icon word-icon-italic">I</span>', title: 'Corsivo: rende in corsivo il testo selezionato' },
            { cmd: 'underline', icon: '<span class="word-icon word-icon-underline">U</span>', title: 'Sottolineato: sottolinea il testo selezionato' }
        ],
        [
            { cmd: 'insertUnorderedList', icon: '<span class="word-icon word-icon-list">&bull;<br>&bull;<br>&bull;</span>', title: 'Elenco puntato: crea o rimuove una lista con pallini' },
            { cmd: 'orderedDecimal', icon: '<span class="word-icon word-icon-list">1<br>2<br>3</span>', title: 'Elenco numerato: crea una lista 1, 2, 3' },
            { cmd: 'orderedAlpha', icon: '<span class="word-icon word-icon-list">a<br>b<br>c</span>', title: 'Elenco alfabetico: crea una lista a, b, c' }
        ],
        [
            { cmd: 'outdent', icon: '<span class="glyphicon glyphicon-indent-right"></span>', title: 'Riduci rientro: riporta la voce al livello precedente' },
            { cmd: 'indent', icon: '<span class="glyphicon glyphicon-indent-left"></span>', title: 'Aumenta rientro: trasforma la voce in sottopunto o aumenta il margine' }
        ],
        [
            { cmd: 'h4', icon: '<span class="word-icon word-icon-title">T</span>', title: 'Titolo: trasforma la riga corrente in titolo o la riporta a testo normale' },
            { cmd: 'clear', icon: '<span class="glyphicon glyphicon-erase"></span>', title: 'Pulisci formattazione: elimina grassetto, corsivo, sottolineato e formato titolo' }
        ]
    ].forEach(function (group) {
        var $group = $('<div>', { class: 'btn-group programma-rich-group', role: 'group' }).appendTo($toolbar);
        group.forEach(function (button) {
            $('<button>', {
                type: 'button',
                class: 'btn btn-default btn-xs programma-rich-btn',
                title: button.title,
                'aria-label': button.title,
                'data-command': button.cmd
            }).html(button.icon).appendTo($group);
        });
    });
    if ($.fn.tooltip) {
        $toolbar.find('[title]').tooltip({ container: 'body' });
    }

    var $editor = $('<div>', {
        id: fieldId + '_editor',
        class: 'form-control programma-rich-editor',
        contenteditable: 'true',
        'data-field': fieldId
    });

    $textarea.after($editor);
    $textarea.after($toolbar);
    $textarea.addClass('programma-rich-source').hide();
    syncProgrammaRichEditorFromTextarea(fieldId);

    $toolbar.on('mousedown', '.programma-rich-btn', function (event) {
        event.preventDefault();
        execProgrammaEditorCommand(fieldId, $(this).data('command'));
    });

    $editor
        .on('focus.programmaRich click.programmaRich mouseup.programmaRich keyup.programmaRich', function () {
            if ($(this).attr('contenteditable') === 'false') {
                hideProgrammaFieldPreview();
                return;
            }
            saveProgrammaEditorSelection(fieldId);
            showProgrammaFieldPreview(fieldId);
            syncProgrammaFieldPreview(fieldId);
            updateProgrammaToolbarState(fieldId);
        })
        .on('input.programmaRich keyup.programmaRich', function () {
            if ($(this).attr('contenteditable') === 'false') {
                hideProgrammaFieldPreview();
                return;
            }
            saveProgrammaEditorSelection(fieldId);
            if (programmaLooksLikeHtml($(this).html() || '')) {
                markProgrammaEditorRich(fieldId);
            }
            syncProgrammaRichEditorToTextarea(fieldId);
            syncProgrammaFieldPreview(fieldId);
            updateProgrammaToolbarState(fieldId);
        })
        .on('paste.programmaRich', function (event) {
            if ($(this).attr('contenteditable') === 'false') {
                event.preventDefault();
                hideProgrammaFieldPreview();
                return;
            }
            pasteProgrammaWordLikeContent(fieldId, event);
        });
}

function setupProgrammaRichEditors() {
    programmaRichTextFields.forEach(setupProgrammaRichEditor);
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
    if (programmaLooksLikeHtml(text)) {
        var html = sanitizeProgrammaRichHtml(text);
        return html !== '' ? html : '<span class="text-muted">Anteprima non disponibile: inizia a scrivere.</span>';
    }

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
    var plainText = programmaLooksLikeHtml(text) ? programmaHtmlToPlainText(text) : String(text || '');
    var lines = plainText.split(/\r\n|\r|\n/u);
    if (!String(plainText || '').length) {
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
    var fieldId = String(textareaSelector || '').replace(/^#/, '');
    var value = getProgrammaFieldValue(fieldId);
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
    if ($('#' + fieldId + '_editor').attr('contenteditable') === 'false') {
        hideProgrammaFieldPreview();
        return;
    }

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
    syncProgrammaRichEditorToTextarea(fieldId);
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
    $('.programma-rich-editor').attr('contenteditable', readonly ? 'false' : 'true').toggleClass('disabled', readonly);
    $('.programma-rich-toolbar').toggle(!readonly);
    $('.programma-rich-btn').prop('disabled', readonly);
    if (readonly) {
        hideProgrammaFieldPreview();
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
                $('#classe').selectpicker('val', programma.programma_classe_select || programma.programma_classe);
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
            syncProgrammaRichEditorsFromTextareas();

            if (duplica == 'false') {
                if ($("#hidden_admin_programmi").val() === "1") {
                    $('#classe').attr('disabled', false);
                } else {
                    $('#classe').attr('disabled', true);
                }
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
        syncProgrammaRichEditorsFromTextareas();
        aggiornaCampiModuloPerClasse();
        applicaReadonlyProgrammaSvolto();
        hideProgrammaFieldPreview();
    }
    $("#_error-programma-part").hide();
    $("#programma_modal").modal("show");
}

function moduliSvoltiImportRequest(programma_id, programma_iniziale_id) {
    var requestData = {
        programma_modulo_id: programma_id,
        response_format: 'json'
    };

    if (programma_iniziale_id > 0) {
        requestData.programma_iniziale_id = programma_iniziale_id;
    }

    return $.ajax({
        url: "../didattica/moduliSvoltiImport.php",
        method: "POST",
        dataType: "json",
        data: requestData
    });
}

function moduliSvoltiChooseProgrammaIniziale(programmi) {
    if (!Array.isArray(programmi) || programmi.length === 0) {
        return 0;
    }

    var message = "Non ho trovato un programma iniziale intestato a questo docente.\n";
    message += "Esistono pero programmi iniziali della stessa classe e materia creati da altri docenti.\n\n";
    message += "Scegli quale importare inserendo il numero:\n\n";

    programmi.forEach(function (programma, index) {
        var updated = programma.updated ? (" - aggiornato " + programma.updated) : "";
        var moduli = typeof programma.numero_moduli !== 'undefined' ? (" - moduli " + programma.numero_moduli) : "";
        message += (index + 1) + ") " + programma.docente + updated + moduli + "\n";
    });

    var scelta = prompt(message, "1");

    if (scelta === null) {
        return 0;
    }

    scelta = parseInt(scelta, 10);

    if (isNaN(scelta) || scelta < 1 || scelta > programmi.length) {
        alert("Scelta non valida. Importazione annullata.");
        return 0;
    }

    return parseInt(programmi[scelta - 1].id, 10);
}

function verificaCampiProgrammaSvoltoObbligatori() {
    if ($("#docente").val() <= 0) {
        $("#_error-programma").text("Devi selezionare un docente");
        $("#_error-programma-part").show();
        return false;
    }
    if ($("#classe").val() <= 0) {
        $("#_error-programma").text("Devi selezionare una classe");
        $("#_error-programma-part").show();
        return false;
    }
    if ($("#materia").val() <= 0) {
        $("#_error-programma").text("Devi selezionare una materia");
        $("#_error-programma-part").show();
        return false;
    }

    $("#_error-programma-part").hide();
    return true;
}

async function assicuratiProgrammaSvoltoSalvato() {
    if (programmaSvoltoReadonly()) {
        return 0;
    }
    if (!verificaCampiProgrammaSvoltoObbligatori()) {
        return 0;
    }

    syncProgrammaRichEditorsToTextareas();
    $('#hidden_programma_classe_anno').val(parseInt($('#classe option:selected').data('anno'), 10) || $('#hidden_programma_classe_anno').val() || 0);

    let programma_id = parseInt($("#hidden_programma_id").val(), 10);
    if (!isNaN(programma_id) && programma_id > 0) {
        return programma_id;
    }

    const saveResp = await $.ajax({
        url: "programmiSvoltiSave.php",
        type: "POST",
        data: {
            id: '-1',
            docente_id: $("#docente").val(),
            classe_id: $("#classe").val(),
            classe_tipo: $("#classe option:selected").data("tipo") || "classe",
            articolata_id: $("#classe option:selected").data("articolata-id") || 0,
            classi_collegate: $("#classe option:selected").data("classi") || "",
            materia_id: $("#materia").val(),
            duplica: 'false',
            share: 'false',
            overwrite: 'false',
            metodologie_programma: $("#metodologie_programma").val(),
            criteri_valutazione_programma: $("#criteri_valutazione_programma").val(),
            testi_materiali_programma: $("#testi_materiali_programma").val()
        }
    });

    programma_id = parseInt($.trim(String(saveResp)), 10);
    if (isNaN(programma_id) || programma_id <= 0) {
        alert("Non riesco a salvare il programma prima di procedere: " + saveResp);
        return 0;
    }

    $("#hidden_programma_id").val(programma_id);
    return programma_id;
}

async function moduliSvoltiImport() {
    let programma_id = await assicuratiProgrammaSvoltoSalvato();

    if (programma_id > 0) {
        var conf = confirm("Sei sicuro di volere importare il programma iniziale? Verranno usati prima i moduli dello stesso docente. Se esistono solo programmi di altri docenti, potrai scegliere quale importare. Eventuali moduli gia presenti saranno sovrascritti.");

        if (conf == true) {
            try {
                var importResponse = await moduliSvoltiImportRequest(programma_id, 0);

                if (importResponse && importResponse.needs_choice) {
                    var programma_iniziale_id = moduliSvoltiChooseProgrammaIniziale(importResponse.programmi);

                    if (programma_iniziale_id <= 0) {
                        return;
                    }

                    importResponse = await moduliSvoltiImportRequest(programma_id, programma_iniziale_id);
                }

                console.log("Importazione completata", importResponse);
                moduliSvoltiReadRecords($("#hidden_programma_id").val());
            } catch (jqXHR) {
                var responseText = jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.message
                    ? jqXHR.responseJSON.message
                    : (jqXHR && jqXHR.responseText ? jqXHR.responseText : "Errore durante l'importazione dei moduli");

                console.error("Errore nell'importazione:", jqXHR);
                alert(responseText);
            }
        }
    }
}

async function moduloSvoltiGetDetails(modulo_id) {
    if (programmaSvoltoReadonly()) {
        return;
    }
    let programma_id = await assicuratiProgrammaSvoltoSalvato();
    if (programma_id <= 0) {
        return;
    }

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
            $('#competenze_raggiunte').val(programma.modulo_competenze_raggiunte_html || programma.modulo_competenze_raggiunte || '');
            $('#contenuti_trattati').val(programma.modulo_contenuti_trattati_html || programma.modulo_contenuti_trattati || '');
            $('#abilita_quinta').val(programma.modulo_abilita_quinta_html || programma.modulo_abilita_quinta || '');
        } else {
            pulisciCampiQuinta();
        }
        syncProgrammaRichEditorsFromTextareas();
    } else {
        $('#titolo').val("");
        $('#ordine').val(parseInt(nmoduli, 10) + 1);
        $('#contenuto').val("");
        pulisciCampiQuinta();
        syncProgrammaRichEditorsFromTextareas();
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
    buildProgrammaSvoltoForm(id_programma, 'docx', 'own').appendTo('body').submit().remove();
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

function programmiSvoltiRichiediCopertina(id_programma) {
    if (!confirm("Vuoi richiedere la copertina per il fascicolo verifiche di questo programma svolto?")) {
        return;
    }

    $.post("programmiSvoltiCopertinaRequest.php", {
        programma_id: id_programma
    }, function (response) {
        var result = typeof response === 'string' ? JSON.parse(response) : response;
        if (!result || result.ok === false) {
            alert((result && result.message) ? result.message : "Errore durante la richiesta della copertina.");
            return;
        }
        if ($.notify) {
            $.notify({ message: result.message || "Copertina richiesta." }, { type: 'success' });
        } else {
            alert(result.message || "Copertina richiesta.");
        }
        programmiSvoltiReadRecords();
    }).fail(function (xhr) {
        var message = xhr.responseJSON && xhr.responseJSON.message
            ? xhr.responseJSON.message
            : "Errore di connessione durante la richiesta della copertina.";
        alert(message);
    });
}

function programmiSvoltiVerificheDigitaliNotify(message, type) {
    type = type || 'info';
    if ($.notify) {
        $.notify({ message: message }, { type: type });
    } else {
        alert(message);
    }
}

function programmiSvoltiVerificheDigitaliOpen(id_programma) {
    $("#programmi_svolti_verifiche_programma_id").val(id_programma);
    $("#programmi_svolti_verifiche_files").val("");
    programmiSvoltiVerificheDigitaliSetProgress(0, false);
    $("#programmi_svolti_verifiche_folder_name").text("caricamento...");
    $("#programmi_svolti_verifiche_list").html('<div class="text-muted">Caricamento...</div>');
    $("#programmi_svolti_verifiche_modal").modal("show");
    programmiSvoltiVerificheDigitaliLoad();
}

function programmiSvoltiVerificheDigitaliLoad() {
    var idProgramma = parseInt($("#programmi_svolti_verifiche_programma_id").val(), 10) || 0;
    if (idProgramma <= 0) {
        return;
    }

    $.getJSON("programmiSvoltiVerificheDigitaliRead.php", {
        programma_id: idProgramma
    }, function (response) {
        if (!response || response.success === false) {
            $("#programmi_svolti_verifiche_list").html('<div class="alert alert-danger">' + ((response && response.message) ? response.message : 'Errore nel caricamento dei file.') + '</div>');
            return;
        }
        $("#programmi_svolti_verifiche_folder_name").text(response.folderName || "");
        $("#programmi_svolti_verifiche_list").html(response.html || "");
        if (response.title) {
            $("#programmiSvoltiVerificheLabel").text("Verifiche digitali - " + response.title);
        }
    }).fail(function () {
        $("#programmi_svolti_verifiche_list").html('<div class="alert alert-danger">Errore di connessione durante il caricamento dei file.</div>');
    });
}

function programmiSvoltiVerificheDigitaliSetProgress(percent, visible) {
    percent = Math.max(0, Math.min(100, Math.round(percent || 0)));
    $("#programmi_svolti_verifiche_progress_box").toggle(!!visible);
    $("#programmi_svolti_verifiche_progress").css("width", percent + "%").text(percent + "%");
}

function programmiSvoltiVerificheDigitaliUpload() {
    var idProgramma = parseInt($("#programmi_svolti_verifiche_programma_id").val(), 10) || 0;
    var files = Array.prototype.slice.call($("#programmi_svolti_verifiche_files")[0].files || []);

    if (idProgramma <= 0) {
        programmiSvoltiVerificheDigitaliNotify("Programma svolto non indicato.", "danger");
        return;
    }
    if (files.length === 0) {
        programmiSvoltiVerificheDigitaliNotify("Seleziona almeno un file ZIP da caricare.", "warning");
        return;
    }
    for (var i = 0; i < files.length; i++) {
        if (!/\.zip$/i.test(files[i].name || "")) {
            programmiSvoltiVerificheDigitaliNotify("Puoi caricare solo file ZIP. Controlla: " + files[i].name, "warning");
            return;
        }
    }

    $("#programmi_svolti_verifiche_upload_btn").prop("disabled", true);
    programmiSvoltiVerificheDigitaliSetProgress(0, true);
    programmiSvoltiVerificheDigitaliUploadSequenziale(files, 0, 0);
}

function programmiSvoltiVerificheDigitaliUploadSequenziale(files, index, completedPercent) {
    if (index >= files.length) {
        $("#programmi_svolti_verifiche_upload_btn").prop("disabled", false);
        $("#programmi_svolti_verifiche_files").val("");
        programmiSvoltiVerificheDigitaliSetProgress(100, true);
        programmiSvoltiVerificheDigitaliNotify("File ZIP caricati su Drive.", "success");
        programmiSvoltiVerificheDigitaliLoad();
        programmiSvoltiReadRecords();
        setTimeout(function () {
            programmiSvoltiVerificheDigitaliSetProgress(0, false);
        }, 1200);
        return;
    }

    var file = files[index];
    var basePercent = completedPercent || 0;
    var fileQuota = 100 / files.length;
    programmiSvoltiVerificheDigitaliUploadFile(file, function (filePercent) {
        programmiSvoltiVerificheDigitaliSetProgress(basePercent + (filePercent * fileQuota / 100), true);
    }, function () {
        programmiSvoltiVerificheDigitaliUploadSequenziale(files, index + 1, basePercent + fileQuota);
    }, function (message) {
        $("#programmi_svolti_verifiche_upload_btn").prop("disabled", false);
        programmiSvoltiVerificheDigitaliNotify(message || ("Errore nel caricamento di " + file.name), "danger");
    });
}

function programmiSvoltiVerificheDigitaliUploadFile(file, onProgress, onDone, onError) {
    var idProgramma = parseInt($("#programmi_svolti_verifiche_programma_id").val(), 10) || 0;
    $.ajax({
        url: "programmiSvoltiVerificheDigitaliUploadStart.php",
        method: "POST",
        dataType: "json",
        data: {
            programma_id: idProgramma,
            name: file.name,
            mime: file.type || "application/zip",
            size: file.size
        }
    }).done(function (startResponse) {
        if (!startResponse || startResponse.success === false || !startResponse.uploadUrl) {
            onError((startResponse && startResponse.message) ? startResponse.message : "Non riesco ad avviare il caricamento su Drive.");
            return;
        }
        programmiSvoltiVerificheDigitaliUploadChunks(file, startResponse, onProgress, function (driveFile) {
            programmiSvoltiVerificheDigitaliComplete(file, startResponse, driveFile, onDone, onError);
        }, onError);
    }).fail(function (xhr) {
        var response = xhr.responseJSON || {};
        onError(response.message || "Errore durante l'avvio del caricamento su Drive.");
    });
}

function programmiSvoltiVerificheDigitaliUploadChunks(file, startResponse, onProgress, onDone, onError) {
    var chunkSize = 8 * 1024 * 1024;
    var offset = 0;

    function sendNextChunk() {
        var end = Math.min(offset + chunkSize, file.size);
        var chunk = file.slice(offset, end);

        var xhr = new XMLHttpRequest();
        xhr.open("PUT", "programmiSvoltiVerificheDigitaliUploadChunk.php", true);
        xhr.setRequestHeader("X-Drive-Upload-Url", startResponse.uploadUrl);
        xhr.setRequestHeader("X-File-Size", file.size);
        xhr.setRequestHeader("Content-Type", file.type || "application/zip");
        xhr.setRequestHeader("Content-Range", "bytes " + offset + "-" + (end - 1) + "/" + file.size);

        xhr.onload = function () {
            if (xhr.status === 308) {
                offset = end;
                onProgress(file.size > 0 ? (offset / file.size) * 95 : 95);
                sendNextChunk();
                return;
            }

            if (xhr.status !== 200 && xhr.status !== 201) {
                onError("Errore upload Drive HTTP " + xhr.status + ": " + xhr.responseText);
                return;
            }

            var parsed = {};
            try {
                parsed = JSON.parse(xhr.responseText || "{}");
            } catch (e) { }
            offset = end;
            onProgress(file.size > 0 ? (offset / file.size) * 95 : 95);
            onDone(parsed);
        };

        xhr.onerror = function () {
            onError("Errore di rete durante il trasferimento del file a Drive.");
        };

        xhr.upload.onprogress = function (e) {
            if (e.lengthComputable) {
                onProgress(file.size > 0 ? ((offset + e.loaded) / file.size) * 95 : 95);
            }
        };

        xhr.send(chunk);
    }

    sendNextChunk();
}

function programmiSvoltiVerificheDigitaliComplete(file, startResponse, driveFile, onDone, onError) {
    var idProgramma = parseInt($("#programmi_svolti_verifiche_programma_id").val(), 10) || 0;
    $.ajax({
        url: "programmiSvoltiVerificheDigitaliUploadComplete.php",
        method: "POST",
        dataType: "json",
        data: {
            programma_id: idProgramma,
            folder_id: startResponse.folderId || "",
            file_id: driveFile.id || "",
            web_view_link: driveFile.webViewLink || "",
            name: file.name,
            drive_name: startResponse.driveName || file.name,
            mime: driveFile.mimeType || file.type || "application/zip",
            size: driveFile.size || file.size
        }
    }).done(function (response) {
        if (!response || response.success === false) {
            onError((response && response.message) ? response.message : "File caricato, ma non registrato in GestOre.");
            return;
        }
        onDone();
    }).fail(function (xhr) {
        var response = xhr.responseJSON || {};
        onError(response.message || "Errore durante la registrazione del file in GestOre.");
    });
}

function programmiSvoltiVerificheDigitaliDelete(id) {
    if (!confirm("Vuoi eliminare questo file ZIP da Drive?")) {
        return;
    }

    $.post("programmiSvoltiVerificheDigitaliDelete.php", {
        id: id
    }, function (response) {
        var result = typeof response === "string" ? JSON.parse(response || "{}") : response;
        if (!result || result.success === false) {
            programmiSvoltiVerificheDigitaliNotify((result && result.message) ? result.message : "Errore durante l'eliminazione.", "danger");
            return;
        }
        programmiSvoltiVerificheDigitaliNotify("File eliminato.", "success");
        programmiSvoltiVerificheDigitaliLoad();
        programmiSvoltiReadRecords();
    }, "json").fail(function (xhr) {
        var response = xhr.responseJSON || {};
        programmiSvoltiVerificheDigitaliNotify(response.message || "Errore durante l'eliminazione.", "danger");
    });
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
    if (!verificaCampiProgrammaSvoltoObbligatori()) {
        $("#_error-programma-part").show();
        return;
    }

    $('#hidden_programma_classe_anno').val(parseInt($('#classe option:selected').data('anno'), 10) || $('#hidden_programma_classe_anno').val() || 0);
    syncProgrammaRichEditorsToTextareas();

    $.post("programmiSvoltiSave.php", {
        id: $("#hidden_programma_id").val(),
        docente_id: $("#docente").val(),
        classe_id: $("#classe").val(),
        classe_tipo: $("#classe option:selected").data("tipo") || "classe",
        articolata_id: $("#classe option:selected").data("articolata-id") || 0,
        classi_collegate: $("#classe option:selected").data("classi") || "",
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
    syncProgrammaRichEditorsToTextareas();

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
    setupProgrammaRichEditors();
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
