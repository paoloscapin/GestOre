<?php

require_once '../common/path.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function privacyLanguages(): array
{
    return [
        'it' => ['label' => '🇮🇹 Italiano', 'dir' => 'ltr'],
        'en' => ['label' => '🇬🇧 English - Inglese', 'dir' => 'ltr'],
        'fr' => ['label' => '🇫🇷 Français - Francese', 'dir' => 'ltr'],
        'de' => ['label' => '🇩🇪 Deutsch - Tedesco', 'dir' => 'ltr'],
        'es' => ['label' => '🇪🇸 Español - Spagnolo', 'dir' => 'ltr'],
        'pt' => ['label' => '🇧🇷 Português - Portoghese', 'dir' => 'ltr'],
        'ru' => ['label' => '🇷🇺 Русский - Russo', 'dir' => 'ltr'],
        'sq' => ['label' => '🇦🇱 Shqip - Albanese', 'dir' => 'ltr'],
        'ro' => ['label' => '🇷🇴 Română - Rumeno', 'dir' => 'ltr'],
        'hi' => ['label' => '🇮🇳 हिन्दी - Hindi', 'dir' => 'ltr'],
        'pa' => ['label' => '🇮🇳 ਪੰਜਾਬੀ - Punjabi', 'dir' => 'ltr'],
        'bn' => ['label' => '🇧🇩 বাংলা - Bengali', 'dir' => 'ltr'],
        'sr' => ['label' => '🇷🇸 Српски - Serbo', 'dir' => 'ltr'],
        'hr' => ['label' => '🇭🇷 Hrvatski - Croato', 'dir' => 'ltr'],
        'tr' => ['label' => '🇹🇷 Türkçe - Turco', 'dir' => 'ltr'],
        'fa' => ['label' => '🇮🇷 فارسی - Persiano', 'dir' => 'ltr'],
        'ps' => ['label' => '🇦🇫 پښتو - Pashto', 'dir' => 'ltr'],
        'tl' => ['label' => '🇵🇭 Filipino - Tagalog', 'dir' => 'ltr'],
        'pl' => ['label' => '🇵🇱 Polski - Polacco', 'dir' => 'ltr'],
        'uk' => ['label' => '🇺🇦 Українська - Ucraino', 'dir' => 'ltr'],
        'zh' => ['label' => '🇨🇳 中文 - Cinese', 'dir' => 'ltr'],
        'ar' => ['label' => '🇲🇦 العربية - Arabo', 'dir' => 'ltr'],
        'ur' => ['label' => '🇵🇰 اردو - Urdu', 'dir' => 'ltr'],
    ];
}

function privacyVocabulary(): array
{
    static $vocabulary = null;
    if ($vocabulary !== null) {
        return $vocabulary;
    }

    $vocabulary = [];
    $path = __DIR__ . '/traduzioni_vocabolario_it.tsv';
    if (!is_readable($path)) {
        return $vocabulary;
    }

    $handle = fopen($path, 'r');
    if (!$handle) {
        return $vocabulary;
    }

    $headers = fgetcsv($handle, 0, "\t");
    if (!is_array($headers)) {
        fclose($handle);
        return $vocabulary;
    }

    $indexes = array_flip($headers);
    $reservedColumns = ['chiave' => true, 'testo_it' => true, 'nota_contesto' => true];
    $languageColumns = [];
    foreach ($headers as $index => $header) {
        $lang = trim((string)$header);
        if ($lang !== '' && !isset($reservedColumns[$lang])) {
            $languageColumns[$lang] = $index;
        }
    }

    while (($row = fgetcsv($handle, 0, "\t")) !== false) {
        $key = trim((string)($row[$indexes['chiave'] ?? -1] ?? ''));
        if ($key === '') {
            continue;
        }

        $italian = trim((string)($row[$indexes['testo_it'] ?? -1] ?? ''));
        if ($italian !== '') {
            $vocabulary['it'][$key] = $italian;
        }

        foreach ($languageColumns as $lang => $index) {
            $value = trim((string)($row[$index] ?? ''));
            if ($value !== '') {
                $vocabulary[$lang][$key] = $value;
            }
        }
    }
    fclose($handle);

    return $vocabulary;
}

function privacyTr(string $key): string
{
    global $privacyLang;
    $vocabulary = privacyVocabulary();
    return $vocabulary[$privacyLang][$key] ?? $vocabulary['it'][$key] ?? $key;
}

$token = trim((string)($_GET['t'] ?? ''));
$privacyLanguages = privacyLanguages();
$privacyLang = strtolower(trim((string)($_GET['lang'] ?? 'it')));
if (!isset($privacyLanguages[$privacyLang])) {
    $privacyLang = 'it';
}
$privacyDir = $privacyLanguages[$privacyLang]['dir'];
$backUrl = $token !== ''
    ? 'conferma.php?t=' . rawurlencode($token) . '&lang=' . rawurlencode($privacyLang)
    : 'conferma.php';

?>
<!DOCTYPE html>
<html lang="<?php echo h($privacyLang); ?>" dir="<?php echo h($privacyDir); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo h(privacyTr('privacy_page_title')); ?></title>
    <link rel="icon" href="<?php echo h($__application_base_path); ?>/ore-32.png" type="image/png">
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f5f7fb; color: #172033; line-height: 1.55; }
        .page { max-width: 900px; margin: 0 auto; padding: 18px; }
        .card { background: #fff; border: 1px solid #d9e0ea; border-radius: 8px; box-shadow: 0 8px 28px rgba(23,32,51,.08); padding: 20px; margin: 14px 0; }
        h1 { font-size: 26px; margin: 0 0 8px; }
        h2 { font-size: 18px; margin: 22px 0 8px; }
        p { margin: 8px 0; }
        ul { margin: 8px 0 8px 22px; padding: 0; }
        li { margin: 5px 0; }
        .muted { color: #64748b; }
        .notice { border-left: 5px solid #0ea5e9; background: #eaf6fc; padding: 12px; border-radius: 6px; }
        .back { display: inline-block; margin-top: 12px; color: #0369a1; font-weight: 700; }
        .language-switch { display: flex; justify-content: flex-end; margin-bottom: 10px; }
        .language-switch label { display: flex; gap: 8px; align-items: center; font-size: 13px; color: #475569; }
        .language-switch select { border: 1px solid #cbd5e1; border-radius: 6px; padding: 7px 9px; font: inherit; background: #fff; color: #172033; }
    </style>
</head>
<body>
<main class="page">
    <form class="language-switch" method="get">
        <input type="hidden" name="t" value="<?php echo h($token); ?>">
        <label>
            <span><?php echo h(privacyTr('language')); ?></span>
            <select name="lang" onchange="this.form.submit()">
                <?php foreach ($privacyLanguages as $code => $info) : ?>
                    <option value="<?php echo h($code); ?>" <?php echo $privacyLang === $code ? 'selected' : ''; ?>><?php echo h($info['label']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
    <article class="card">
        <h1><?php echo h(privacyTr('privacy_page_title')); ?></h1>
        <p class="muted"><?php echo h(privacyTr('subtitle')); ?> - ITT Buonarroti</p>

        <div class="notice">
            <?php echo h(privacyTr('privacy_intro')); ?>
        </div>

        <h2><?php echo h(privacyTr('privacy_why_title')); ?></h2>
        <p><?php echo h(privacyTr('privacy_why_text')); ?></p>

        <h2><?php echo h(privacyTr('privacy_docs_title')); ?></h2>
        <ul>
            <li><?php echo h(privacyTr('privacy_doc_1')); ?></li>
            <li><?php echo h(privacyTr('privacy_doc_2')); ?></li>
            <li><?php echo h(privacyTr('privacy_doc_3')); ?></li>
            <li><?php echo h(privacyTr('privacy_doc_4')); ?></li>
            <li><?php echo h(privacyTr('privacy_doc_5')); ?></li>
            <li><?php echo h(privacyTr('privacy_doc_6')); ?></li>
            <li><?php echo h(privacyTr('privacy_doc_7')); ?></li>
            <li><?php echo h(privacyTr('privacy_doc_8')); ?></li>
        </ul>

        <h2><?php echo h(privacyTr('privacy_use_title')); ?></h2>
        <p><?php echo h(privacyTr('privacy_use_text')); ?></p>

        <h2><?php echo h(privacyTr('privacy_access_title')); ?></h2>
        <p><?php echo h(privacyTr('privacy_access_text')); ?></p>

        <h2><?php echo h(privacyTr('privacy_delivery_title')); ?></h2>
        <p><?php echo h(privacyTr('privacy_delivery_text')); ?></p>

        <h2><?php echo h(privacyTr('privacy_storage_title')); ?></h2>
        <p><?php echo h(privacyTr('privacy_storage_text')); ?></p>

        <h2><?php echo h(privacyTr('privacy_contacts_title')); ?></h2>
        <p><?php echo h(privacyTr('privacy_contacts_text')); ?></p>

        <a class="back" href="<?php echo h($backUrl); ?>"><?php echo h(privacyTr('privacy_back')); ?></a>
    </article>
</main>
</body>
</html>
