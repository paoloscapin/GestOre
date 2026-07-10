<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

iscrizioniPrimeEnsureSchema();

function ipd_crop_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$praticaId = intval($_GET['pratica_id'] ?? 0);
$tipo = trim((string)($_GET['tipo'] ?? ''));
$error = '';
$data = [];
$pageCount = 1;
$tipoIscrizione = 'prime';
$temporaryFiles = [];

try {
    if ($praticaId <= 0 || $tipo === '') {
        throw new RuntimeException('Richiesta non valida.');
    }
    $data = iscrizioniPrimeDocumentForSecretaryEdit($praticaId, $tipo);
    $tipoIscrizione = iscrizioniPrimeTipoIscrizioneFromPratica($data['pratica']);
    $path = iscrizioniPrimeDocumentPathForAppend($data['document'], $temporaryFiles);
    if (!$path) {
        throw new RuntimeException('PDF non recuperabile.');
    }
    $pageCount = iscrizioniPrimePdfPageCount($path);
} catch (Throwable $e) {
    $error = $e->getMessage();
} finally {
    foreach ($temporaryFiles as $temporaryFile) {
        if (is_file($temporaryFile)) {
            @unlink($temporaryFile);
        }
    }
}

$backUrl = 'iscrizioniPrimeDomande.php?tipo_iscrizione=' . rawurlencode($tipoIscrizione) . '#pratica-' . intval($praticaId);
$documentLabel = (string)($data['label'] ?? $tipo);
$studentName = trim((string)($data['pratica']['cognome'] ?? '') . ' ' . (string)($data['pratica']['nome'] ?? ''));

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <title>Ritaglia PDF iscrizione</title>
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    ?>
    <link rel="stylesheet" href="../common/cropperjs/cropper.min.css">
    <style>
        body { background: #f3f6fb; }
        .crop-page { max-width: 1280px; margin: 0 auto; padding: 18px 14px 30px; }
        .crop-head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px; align-items: flex-start; margin-bottom: 12px; }
        .crop-title h1 { margin: 0; font-size: 26px; font-weight: 850; color: #0f172a; }
        .crop-title .meta { margin-top: 4px; color: #64748b; font-weight: 700; }
        .crop-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 10px; border: 1px solid #d7e1ef; border-radius: 8px; background: #fff; margin-bottom: 12px; }
        .crop-toolbar label { margin: 0; color: #475569; font-weight: 700; }
        .crop-toolbar select { width: auto; min-width: 76px; }
        .crop-workspace { border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; padding: 10px; box-shadow: 0 8px 24px rgba(15, 23, 42, .08); }
        .crop-image-wrap { min-height: 560px; max-height: calc(100vh - 250px); overflow: hidden; display: flex; align-items: center; justify-content: center; background: #e5edf7; border-radius: 6px; }
        .crop-image-wrap img { display: block; max-width: 100%; max-height: calc(100vh - 270px); }
        .crop-status { margin-top: 9px; color: #475569; font-weight: 700; }
        .crop-status.error { color: #b91c1c; }
        .crop-error { margin: 20px 0; padding: 14px; border: 1px solid #fecaca; border-radius: 8px; background: #fef2f2; color: #991b1b; font-weight: 750; }
        .cropper-container { max-width: 100%; }
        @media (max-width: 760px) {
            .crop-image-wrap { min-height: 360px; max-height: none; }
            .crop-image-wrap img { max-height: none; }
            .crop-toolbar .btn { width: 100%; }
        }
    </style>
</head>
<body>
<div class="crop-page">
    <div class="crop-head">
        <div class="crop-title">
            <h1>Ritaglia PDF</h1>
            <div class="meta">
                <?php echo ipd_crop_h($studentName ?: ('Pratica #' . $praticaId)); ?> &middot;
                <?php echo ipd_crop_h($documentLabel); ?>
            </div>
        </div>
        <a class="btn btn-default" href="<?php echo ipd_crop_h($backUrl); ?>">
            <span class="glyphicon glyphicon-chevron-left"></span> Torna alla pratica
        </a>
    </div>

    <?php if ($error !== '') : ?>
        <div class="crop-error"><?php echo ipd_crop_h($error); ?></div>
    <?php else : ?>
        <div class="crop-toolbar">
            <label for="cropPageSelect">Pagina</label>
            <select id="cropPageSelect" class="form-control">
                <?php for ($page = 1; $page <= $pageCount; $page++) : ?>
                    <option value="<?php echo intval($page); ?>"><?php echo intval($page); ?> / <?php echo intval($pageCount); ?></option>
                <?php endfor; ?>
            </select>
            <button type="button" class="btn btn-default" id="cropReset"><span class="glyphicon glyphicon-refresh"></span> Reimposta</button>
            <button type="button" class="btn btn-primary" id="cropSave"><span class="glyphicon glyphicon-floppy-disk"></span> Salva ritaglio</button>
            <a class="btn btn-info" target="_blank" rel="noopener" href="iscrizioniPrimeDocumento.php?pratica_id=<?php echo intval($praticaId); ?>&tipo=<?php echo rawurlencode($tipo); ?>">
                <span class="glyphicon glyphicon-file"></span> Apri originale corrente
            </a>
        </div>

        <div class="crop-workspace">
            <div class="crop-image-wrap">
                <img id="cropImage" alt="Pagina PDF da ritagliare">
            </div>
            <div id="cropStatus" class="crop-status">Caricamento anteprima ad alta risoluzione...</div>
        </div>
    <?php endif; ?>
</div>

<?php if ($error === '') : ?>
<script src="../common/cropperjs/cropper.min.js"></script>
<script>
(function () {
    const praticaId = <?php echo intval($praticaId); ?>;
    const tipo = <?php echo json_encode($tipo, JSON_UNESCAPED_UNICODE); ?>;
    const backUrl = <?php echo json_encode($backUrl, JSON_UNESCAPED_UNICODE); ?>;
    const image = document.getElementById('cropImage');
    const pageSelect = document.getElementById('cropPageSelect');
    const status = document.getElementById('cropStatus');
    let cropper = null;

    function setStatus(message, isError) {
        status.textContent = message;
        status.classList.toggle('error', Boolean(isError));
    }

    function imageUrl(page) {
        const params = new URLSearchParams();
        params.set('pratica_id', String(praticaId));
        params.set('tipo', tipo);
        params.set('page', String(page || 1));
        params.set('dpi', '300');
        params.set('_', String(Date.now()));
        return 'iscrizioniPrimeDocumentoRitagliaPreview.php?' + params.toString();
    }

    function loadPage(page) {
        setStatus('Caricamento anteprima ad alta risoluzione...', false);
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        image.onload = function () {
            cropper = new Cropper(image, {
                viewMode: 1,
                autoCropArea: 0.82,
                background: false,
                responsive: true,
                movable: true,
                zoomable: true,
                rotatable: false,
                scalable: false,
                imageSmoothingEnabled: false,
                ready: function () {
                    setStatus('Anteprima 300 DPI pronta.', false);
                }
            });
        };
        image.onerror = function () {
            setStatus('Impossibile caricare l anteprima della pagina PDF.', true);
        };
        image.src = imageUrl(page);
    }

    pageSelect.addEventListener('change', function () {
        loadPage(Number(pageSelect.value || 1));
    });
    document.getElementById('cropReset').addEventListener('click', function () {
        if (cropper) cropper.reset();
    });
    document.getElementById('cropSave').addEventListener('click', function () {
        if (!cropper) {
            return;
        }
        const button = this;
        const crop = cropper.getData(true);
        if (!crop.width || !crop.height || crop.width < 80 || crop.height < 80) {
            setStatus('Ritaglio troppo piccolo.', true);
            return;
        }
        const data = new FormData();
        data.append('pratica_id', String(praticaId));
        data.append('tipo', tipo);
        data.append('page', String(Number(pageSelect.value || 1)));
        data.append('dpi', '300');
        data.append('x', String(crop.x));
        data.append('y', String(crop.y));
        data.append('width', String(crop.width));
        data.append('height', String(crop.height));

        button.disabled = true;
        setStatus('Salvataggio PDF ritagliato in corso...', false);
        fetch('iscrizioniPrimeDocumentoRitagliaSave.php', {
            method: 'POST',
            body: data,
            credentials: 'same-origin'
        })
        .then(response => response.text().then(text => {
            let payload = {};
            try {
                payload = text ? JSON.parse(text) : {};
            } catch (e) {
                throw new Error('Risposta non valida dal server.');
            }
            return {ok: response.ok, payload};
        }))
        .then(result => {
            if (!result.ok || !result.payload.ok) {
                throw new Error(result.payload.message || 'Salvataggio non riuscito.');
            }
            setStatus(result.payload.message || 'PDF ritagliato salvato.', false);
            window.location.href = backUrl;
        })
        .catch(error => {
            button.disabled = false;
            setStatus(error.message, true);
        });
    });

    loadPage(1);
})();
</script>
<?php endif; ?>
</body>
</html>
