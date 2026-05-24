<?php

require_once '../common/checkSession.php';

ruoloRichiesto('admin');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Configurazione GestOre</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <style>
        .config-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-bottom: 12px;
        }
        .config-meta {
            color: #566;
            font-size: 12px;
            margin-left: auto;
        }
        .config-tabs {
            margin-bottom: 15px;
        }
        .json-editor {
            width: 100%;
            min-height: 520px;
            font-family: Consolas, Monaco, monospace;
            font-size: 13px;
            line-height: 1.35;
            resize: vertical;
        }
        .tree-root {
            border: 1px solid #d8e2ea;
            border-radius: 4px;
            background: #f7fbfd;
            padding: 12px;
        }
        .tree-node {
            border-left: 2px solid #d9edf7;
            margin: 6px 0 6px 16px;
            padding-left: 10px;
        }
        .tree-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
            padding: 5px 0;
        }
        .tree-section {
            border-left-width: 8px;
            border-radius: 6px;
            margin: 12px 0;
            padding: 0 0 8px 0;
            box-shadow: 0 1px 5px rgba(0,0,0,.08);
            overflow: hidden;
        }
        .tree-section > .tree-row {
            padding: 10px 12px;
            border-bottom: 1px solid rgba(0,0,0,.08);
        }
        .tree-section > .tree-children {
            padding: 6px 10px 2px 6px;
        }
        .tree-section-0 { background: #fff8e6; border-left-color: #f0ad4e; }
        .tree-section-1 { background: #eaf7ff; border-left-color: #5bc0de; }
        .tree-section-2 { background: #edf8ed; border-left-color: #5cb85c; }
        .tree-section-3 { background: #f4effc; border-left-color: #8e6cc9; }
        .tree-section-4 { background: #fff0f0; border-left-color: #d9534f; }
        .tree-section-5 { background: #eef7f4; border-left-color: #20a486; }
        .tree-section-6 { background: #f5f5f5; border-left-color: #777; }
        .tree-section-7 { background: #f0f6ff; border-left-color: #337ab7; }
        .tree-section > .tree-row .tree-key {
            font-size: 18px;
            color: #1f3f5b;
        }
        .tree-key {
            min-width: 210px;
            max-width: 360px;
            font-weight: 700;
            color: #23527c;
            word-break: break-word;
        }
        .tree-type {
            min-width: 70px;
            color: #777;
            font-size: 12px;
            text-transform: uppercase;
        }
        .tree-value {
            flex: 1 1 360px;
        }
        .tree-value input[type="text"],
        .tree-value input[type="number"],
        .tree-value textarea {
            width: 100%;
        }
        .tree-value textarea {
            min-height: 58px;
            resize: vertical;
        }
        .tree-actions {
            white-space: nowrap;
        }
        .tree-children {
            margin-top: 4px;
        }
        .add-inline {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
            margin: 8px 0 8px 16px;
            padding: 8px;
            border-radius: 4px;
            background: #f7fbfd;
        }
        .add-inline input {
            max-width: 260px;
        }
        .empty-node {
            color: #777;
            font-style: italic;
            margin-left: 16px;
        }
        .config-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(255, 255, 255, 0.78);
            align-items: center;
            justify-content: center;
        }
        .config-overlay-box {
            min-width: 280px;
            max-width: 520px;
            border-radius: 6px;
            background: #fff;
            border: 1px solid #bce8f1;
            box-shadow: 0 8px 28px rgba(0,0,0,.18);
            padding: 22px;
            text-align: center;
            color: #245269;
            font-size: 18px;
        }
        .config-search {
            max-width: 360px;
        }
        .path-muted {
            color: #999;
            font-weight: normal;
            font-size: 11px;
        }
        .hidden-by-filter {
            display: none;
        }
    </style>
</head>
<body>
<?php require_once '../common/header-admin.php'; ?>

<div class="container-fluid">
    <div class="panel panel-lima4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-cog"></span>&emsp;Configurazione GestOre
        </div>
        <div class="panel-body">
            <div id="configAlert" class="alert" style="display:none;"></div>

            <div class="alert alert-warning">
                Questa pagina modifica direttamente <code>GestOre.json</code>. Ogni salvataggio valida il JSON e crea un backup automatico in <code>log/config_backups</code>.
                Le modifiche diventano effettive alle richieste successive.
            </div>

            <div class="config-toolbar">
                <button type="button" id="btnReloadConfig" class="btn btn-default">
                    <span class="glyphicon glyphicon-refresh"></span> Ricarica
                </button>
                <button type="button" id="btnSaveConfig" class="btn btn-success">
                    <span class="glyphicon glyphicon-floppy-disk"></span> Salva configurazione
                </button>
                <input type="text" id="configSearch" class="form-control config-search" placeholder="Filtra per nome campo o percorso...">
                <span id="configMeta" class="config-meta"></span>
            </div>

            <ul class="nav nav-tabs config-tabs">
                <li class="active"><a href="#tabTree" data-toggle="tab">Editor guidato</a></li>
                <li><a href="#tabRaw" data-toggle="tab">JSON completo</a></li>
            </ul>

            <div class="tab-content">
                <div id="tabTree" class="tab-pane active">
                    <div id="configTree" class="tree-root"></div>
                </div>
                <div id="tabRaw" class="tab-pane">
                    <textarea id="rawJson" class="form-control json-editor" spellcheck="false"></textarea>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="configOverlay" class="config-overlay">
    <div class="config-overlay-box">
        <span class="glyphicon glyphicon-refresh"></span>
        <div id="configOverlayText" style="margin-top:10px;">Operazione in corso...</div>
    </div>
</div>

<script>
(function () {
    var apiUrl = 'gestore_config_api.php';
    var configData = {};
    var checksum = '';
    var syncingRaw = false;

    function showOverlay(text) {
        $('#configOverlayText').text(text || 'Operazione in corso...');
        $('#configOverlay').css('display', 'flex');
    }

    function hideOverlay() {
        $('#configOverlay').hide();
    }

    function showAlert(type, message) {
        $('#configAlert')
            .removeClass('alert-success alert-danger alert-warning alert-info')
            .addClass('alert-' + type)
            .html(message)
            .show();
    }

    function clearAlert() {
        $('#configAlert').hide().empty();
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function typeOf(value) {
        if (value === null) return 'null';
        if (Array.isArray(value)) return 'array';
        return typeof value;
    }

    function makeDefault(type) {
        if (type === 'number') return 0;
        if (type === 'boolean') return false;
        if (type === 'object') return {};
        if (type === 'array') return [];
        if (type === 'null') return null;
        return '';
    }

    function pathLabel(path) {
        return path.length ? path.join('.') : 'root';
    }

    function getByPath(path) {
        var node = configData;
        for (var i = 0; i < path.length; i++) {
            node = node[path[i]];
        }
        return node;
    }

    function setByPath(path, value) {
        if (path.length === 0) {
            configData = value;
            return;
        }
        var parent = getByPath(path.slice(0, -1));
        parent[path[path.length - 1]] = value;
    }

    function deleteByPath(path) {
        var parent = getByPath(path.slice(0, -1));
        var key = path[path.length - 1];
        if (Array.isArray(parent)) {
            parent.splice(parseInt(key, 10), 1);
        } else {
            delete parent[key];
        }
    }

    function syncRawFromTree() {
        syncingRaw = true;
        $('#rawJson').val(JSON.stringify(configData, null, 2));
        syncingRaw = false;
    }

    function syncTreeFromRaw() {
        if (syncingRaw) return true;
        try {
            configData = JSON.parse($('#rawJson').val());
            renderTree();
            clearAlert();
            return true;
        } catch (e) {
            showAlert('danger', 'JSON completo non valido: ' + escapeHtml(e.message));
            return false;
        }
    }

    function renderValueControl(value, path) {
        var t = typeOf(value);
        var pathAttr = escapeHtml(JSON.stringify(path));
        if (t === 'boolean') {
            return '<label class="checkbox-inline"><input type="checkbox" class="cfg-value" data-path="' + pathAttr + '"' + (value ? ' checked' : '') + '> vero</label>';
        }
        if (t === 'number') {
            return '<input type="number" step="any" class="form-control input-sm cfg-value" data-path="' + pathAttr + '" value="' + escapeHtml(value) + '">';
        }
        if (t === 'string') {
            if (value.length > 90 || value.indexOf('\n') !== -1) {
                return '<textarea class="form-control input-sm cfg-value" data-path="' + pathAttr + '">' + escapeHtml(value) + '</textarea>';
            }
            return '<input type="text" class="form-control input-sm cfg-value" data-path="' + pathAttr + '" value="' + escapeHtml(value) + '">';
        }
        if (t === 'null') {
            return '<span class="text-muted">null</span>';
        }
        return '<span class="text-muted">' + (t === 'array' ? value.length + ' elementi' : Object.keys(value).length + ' campi') + '</span>';
    }

    function renderAddControls(value, path) {
        var t = typeOf(value);
        if (t !== 'object' && t !== 'array') return '';
        var pathAttr = escapeHtml(JSON.stringify(path));
        var keyInput = t === 'object'
            ? '<input type="text" class="form-control input-sm cfg-new-key" placeholder="nuovo campo">'
            : '';
        return '' +
            '<div class="add-inline" data-path="' + pathAttr + '">' +
                keyInput +
                '<select class="form-control input-sm cfg-new-type" style="width:auto;">' +
                    '<option value="string">testo</option>' +
                    '<option value="number">numero</option>' +
                    '<option value="boolean">booleano</option>' +
                    '<option value="object">sezione</option>' +
                    '<option value="array">lista</option>' +
                    '<option value="null">null</option>' +
                '</select>' +
                '<button type="button" class="btn btn-default btn-xs cfg-add"><span class="glyphicon glyphicon-plus"></span> aggiungi</button>' +
            '</div>';
    }

    function renderNode(key, value, path, canDelete, sectionIndex) {
        var t = typeOf(value);
        var currentPath = pathLabel(path);
        var sectionClass = path.length === 1 ? ' tree-section tree-section-' + (sectionIndex % 8) : '';
        var html = '<div class="tree-node' + sectionClass + '" data-filter-text="' + escapeHtml((key + ' ' + currentPath).toLowerCase()) + '">';
        html += '<div class="tree-row">';
        html += '<div class="tree-key">' + escapeHtml(key) + ' <span class="path-muted">' + escapeHtml(currentPath) + '</span></div>';
        html += '<div class="tree-type">' + escapeHtml(t) + '</div>';
        html += '<div class="tree-value">' + renderValueControl(value, path) + '</div>';
        html += '<div class="tree-actions">';
        if (t === 'object' || t === 'array') {
            html += ' <button type="button" class="btn btn-default btn-xs cfg-collapse"><span class="glyphicon glyphicon-chevron-up"></span></button>';
        }
        if (canDelete) {
            html += ' <button type="button" class="btn btn-danger btn-xs cfg-delete" data-path="' + escapeHtml(JSON.stringify(path)) + '"><span class="glyphicon glyphicon-trash"></span></button>';
        }
        html += '</div></div>';

        if (t === 'object' || t === 'array') {
            html += '<div class="tree-children">';
            var keys = t === 'array' ? value.map(function (_, index) { return index; }) : Object.keys(value);
            if (keys.length === 0) {
                html += '<div class="empty-node">Nessun elemento.</div>';
            }
            keys.forEach(function (childKey, index) {
                html += renderNode(String(childKey), value[childKey], path.concat([childKey]), true, index);
            });
            html += renderAddControls(value, path);
            html += '</div>';
        }

        html += '</div>';
        return html;
    }

    function renderTree() {
        var html = renderNode('GestOre.json', configData, [], false);
        $('#configTree').html(html);
        syncRawFromTree();
        applyFilter();
    }

    function loadConfig() {
        clearAlert();
        showOverlay('Caricamento configurazione...');
        $.getJSON(apiUrl, {action: 'load'})
            .done(function (res) {
                if (!res.ok) {
                    showAlert('danger', escapeHtml(res.error || 'Errore di caricamento.'));
                    return;
                }
                configData = res.config || {};
                checksum = res.checksum || '';
                $('#configMeta').text('Ultima modifica: ' + (res.modifiedAt || '-') + ' - checksum ' + checksum.substring(0, 10));
                renderTree();
            })
            .fail(function (xhr) {
                showAlert('danger', 'Errore di caricamento configurazione: ' + escapeHtml(xhr.responseText || xhr.status));
            })
            .always(hideOverlay);
    }

    function saveConfig(force) {
        if ($('#tabRaw').hasClass('active') && !syncTreeFromRaw()) return;

        var json = JSON.stringify(configData, null, 2);
        showOverlay('Salvataggio configurazione...');
        $.ajax({
            url: apiUrl + '?action=save',
            method: 'POST',
            dataType: 'json',
            data: {
                json: json,
                checksum: checksum,
                force: force ? '1' : '0'
            }
        }).done(function (res) {
            if (!res.ok) {
                showAlert('danger', escapeHtml(res.error || 'Salvataggio non riuscito.'));
                return;
            }
            checksum = res.checksum || checksum;
            $('#configMeta').text('Ultima modifica: ' + (res.modifiedAt || '-') + ' - checksum ' + checksum.substring(0, 10));
            showAlert('success', escapeHtml(res.message || 'Configurazione salvata.') + '<br>Backup creato: <code>' + escapeHtml(res.backup || '-') + '</code>');
            syncRawFromTree();
        }).fail(function (xhr) {
            var res = xhr.responseJSON || {};
            if (xhr.status === 409 && res.conflict) {
                var forceSave = confirm((res.error || 'Configurazione modificata da altri.') + '\n\nVuoi forzare il salvataggio usando il contenuto attuale della pagina?');
                if (forceSave) {
                    saveConfig(true);
                    return;
                }
            }
            showAlert('danger', 'Errore di salvataggio: ' + escapeHtml(res.error || xhr.responseText || xhr.status));
        }).always(hideOverlay);
    }

    function parsePath($el) {
        return JSON.parse($el.attr('data-path') || '[]');
    }

    function applyFilter() {
        var q = ($('#configSearch').val() || '').toLowerCase().trim();
        $('.tree-node').removeClass('hidden-by-filter');
        if (!q) return;

        $('.tree-node').each(function () {
            var $node = $(this);
            var own = ($node.attr('data-filter-text') || '').indexOf(q) !== -1;
            var child = $node.find('.tree-node').filter(function () {
                return (($(this).attr('data-filter-text') || '').indexOf(q) !== -1);
            }).length > 0;
            if (!own && !child) {
                $node.addClass('hidden-by-filter');
            }
        });
    }

    $('#btnReloadConfig').on('click', function () {
        if (confirm('Ricaricare GestOre.json? Le modifiche non salvate verranno perse.')) {
            loadConfig();
        }
    });

    $('#btnSaveConfig').on('click', function () {
        saveConfig(false);
    });

    $('#configSearch').on('input', applyFilter);

    $('#rawJson').on('change', syncTreeFromRaw);

    $('#configTree')
        .on('input change', '.cfg-value', function () {
            var $el = $(this);
            var path = parsePath($el);
            var current = getByPath(path);
            var t = typeOf(current);
            var value;
            if (t === 'boolean') {
                value = $el.is(':checked');
            } else if (t === 'number') {
                value = Number($el.val());
                if (isNaN(value)) value = 0;
            } else {
                value = $el.val();
            }
            setByPath(path, value);
            syncRawFromTree();
        })
        .on('click', '.cfg-delete', function () {
            var path = parsePath($(this));
            if (!confirm('Eliminare ' + pathLabel(path) + '?')) return;
            deleteByPath(path);
            renderTree();
        })
        .on('click', '.cfg-add', function () {
            var $box = $(this).closest('.add-inline');
            var path = parsePath($box);
            var parent = getByPath(path);
            var type = $box.find('.cfg-new-type').val();
            var value = makeDefault(type);
            if (Array.isArray(parent)) {
                parent.push(value);
            } else {
                var key = ($box.find('.cfg-new-key').val() || '').trim();
                if (key === '') {
                    alert('Inserisci il nome del nuovo campo.');
                    return;
                }
                if (Object.prototype.hasOwnProperty.call(parent, key)) {
                    alert('Questo campo esiste gia.');
                    return;
                }
                parent[key] = value;
            }
            renderTree();
        })
        .on('click', '.cfg-collapse', function () {
            var $btn = $(this);
            var $children = $btn.closest('.tree-node').children('.tree-children');
            $children.toggle();
            $btn.find('.glyphicon').toggleClass('glyphicon-chevron-up glyphicon-chevron-down');
        });

    loadConfig();
})();
</script>
</body>
</html>
