<?php

require_once '../common/path.php';
require_once '../common/__Settings.php';

function im_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function im_languages(): array
{
    return [
        'it' => ['native' => 'Italiano', 'italian' => 'Italiano', 'dir' => 'ltr'],
        'en' => ['native' => 'English', 'italian' => 'Inglese', 'dir' => 'ltr'],
        'fr' => ['native' => 'Francais', 'italian' => 'Francese', 'dir' => 'ltr'],
        'de' => ['native' => 'Deutsch', 'italian' => 'Tedesco', 'dir' => 'ltr'],
        'es' => ['native' => 'Espanol', 'italian' => 'Spagnolo', 'dir' => 'ltr'],
        'pt' => ['native' => 'Portugues', 'italian' => 'Portoghese', 'dir' => 'ltr'],
        'ru' => ['native' => 'Русский', 'italian' => 'Russo', 'dir' => 'ltr'],
        'sq' => ['native' => 'Shqip', 'italian' => 'Albanese', 'dir' => 'ltr'],
        'ro' => ['native' => 'Romana', 'italian' => 'Rumeno', 'dir' => 'ltr'],
        'hi' => ['native' => 'हिन्दी', 'italian' => 'Hindi', 'dir' => 'ltr'],
        'pa' => ['native' => 'ਪੰਜਾਬੀ', 'italian' => 'Punjabi', 'dir' => 'ltr'],
        'bn' => ['native' => 'বাংলা', 'italian' => 'Bengalese', 'dir' => 'ltr'],
        'sr' => ['native' => 'Српски', 'italian' => 'Serbo', 'dir' => 'ltr'],
        'hr' => ['native' => 'Hrvatski', 'italian' => 'Croato', 'dir' => 'ltr'],
        'tr' => ['native' => 'Turkce', 'italian' => 'Turco', 'dir' => 'ltr'],
        'fa' => ['native' => 'فارسی', 'italian' => 'Persiano', 'dir' => 'rtl'],
        'ps' => ['native' => 'پښتو', 'italian' => 'Pashtu', 'dir' => 'rtl'],
        'tl' => ['native' => 'Tagalog', 'italian' => 'Tagalog', 'dir' => 'ltr'],
        'pl' => ['native' => 'Polski', 'italian' => 'Polacco', 'dir' => 'ltr'],
        'uk' => ['native' => 'Українська', 'italian' => 'Ucraino', 'dir' => 'ltr'],
        'zh' => ['native' => '中文', 'italian' => 'Cinese', 'dir' => 'ltr'],
        'ar' => ['native' => 'العربية', 'italian' => 'Arabo', 'dir' => 'rtl'],
        'ur' => ['native' => 'اردو', 'italian' => 'Urdu', 'dir' => 'rtl'],
    ];
}

function im_language_flag_html(string $lang): string
{
    $flags = [
        'it' => '&#127470;&#127481;',
        'en' => '&#127468;&#127463;',
        'fr' => '&#127467;&#127479;',
        'de' => '&#127465;&#127466;',
        'es' => '&#127466;&#127480;',
        'pt' => '&#127477;&#127481;',
        'ru' => '&#127479;&#127482;',
        'sq' => '&#127462;&#127473;',
        'ro' => '&#127479;&#127476;',
        'hi' => '&#127470;&#127475;',
        'pa' => '&#127470;&#127475;',
        'bn' => '&#127463;&#127465;',
        'sr' => '&#127479;&#127480;',
        'hr' => '&#127469;&#127479;',
        'tr' => '&#127481;&#127479;',
        'fa' => '&#127470;&#127479;',
        'ps' => '&#127462;&#127467;',
        'tl' => '&#127477;&#127469;',
        'pl' => '&#127477;&#127473;',
        'uk' => '&#127482;&#127462;',
        'zh' => '&#127464;&#127475;',
        'ar' => '&#127480;&#127462;',
        'ur' => '&#127477;&#127472;',
    ];
    return $flags[$lang] ?? '&#127987;';
}

function im_vocabulary(): array
{
    static $vocabulary = null;
    if ($vocabulary !== null) {
        return $vocabulary;
    }

    $vocabulary = [];
    $path = __DIR__ . '/comunicazioni_mail.tsv';
    if (!is_file($path)) {
        return $vocabulary;
    }

    $handle = fopen($path, 'r');
    if ($handle === false) {
        return $vocabulary;
    }

    $headers = fgetcsv($handle, 0, "\t");
    if (!is_array($headers)) {
        fclose($handle);
        return $vocabulary;
    }

    while (($row = fgetcsv($handle, 0, "\t")) !== false) {
        if (!is_array($row) || count($row) === 0) {
            continue;
        }
        $item = [];
        foreach ($headers as $index => $header) {
            $item[(string)$header] = isset($row[$index]) ? (string)$row[$index] : '';
        }
        $key = trim((string)($item['chiave'] ?? ''));
        if ($key !== '') {
            $vocabulary[$key] = $item;
        }
    }
    fclose($handle);

    return $vocabulary;
}

function im_text(string $key, string $lang): string
{
    $vocabulary = im_vocabulary();
    if (!isset($vocabulary[$key])) {
        return '';
    }

    $value = trim((string)($vocabulary[$key][$lang] ?? ''));
    if ($value !== '') {
        return $value;
    }

    return trim((string)($vocabulary[$key]['testo_it'] ?? ''));
}

function im_lang_has_translation(string $lang, array $keys): bool
{
    if ($lang === 'it') {
        return true;
    }

    $vocabulary = im_vocabulary();
    foreach ($keys as $key) {
        if (!isset($vocabulary[$key]) || trim((string)($vocabulary[$key][$lang] ?? '')) === '') {
            return false;
        }
    }

    return true;
}

function im_paragraph(string $key, string $lang): string
{
    $text = im_text($key, $lang);
    return $text !== '' ? '<p>' . nl2br(im_h(str_replace('\n', "\n", $text))) . '</p>' : '';
}

$languages = im_languages();
$lang = strtolower(trim((string)($_GET['lang'] ?? 'it')));
if (!isset($languages[$lang])) {
    $lang = 'it';
}

$tipo = strtolower(trim((string)($_GET['tipo'] ?? 'prime')));
$tipo = $tipo === 'terze' ? 'terze' : 'prime';
$dir = $languages[$lang]['dir'];

$subjectKey = $tipo === 'terze' ? 'email_subject_terze' : 'email_subject_prime';
$introKey = $tipo === 'terze' ? 'terze_intro' : 'prime_intro';
$reservedKey = $tipo === 'terze' ? 'reserved_link_terze' : 'reserved_link_prime';
$attachmentKey = $tipo === 'terze' ? 'attachment_terze' : 'attachment_prime';
$bulletKeys = $tipo === 'terze'
    ? ['terze_bullet_1', 'terze_bullet_2', 'terze_bullet_3', 'terze_bullet_4', 'terze_bullet_5', 'terze_bullet_6']
    : ['prime_bullet_1', 'prime_bullet_2', 'prime_bullet_3', 'prime_bullet_4'];
$neededKeys = array_merge([
    'page_title',
    'official_notice',
    'personal_link_notice',
    $subjectKey,
    'greeting',
    $introKey,
    'personal_link_placeholder',
    $reservedKey,
    'deadline',
    $attachmentKey,
    'paper_delivery',
    'contacts',
    'closing_common',
    'signature',
], $bulletKeys);
$hasTranslation = im_lang_has_translation($lang, $neededKeys);

$baseQuery = 'tipo=' . rawurlencode($tipo);
$istituto = trim((string)($__settings->local->nomeIstituto ?? 'Istituto'));
$logoSrc = ($__application_base_path ?? '') . '/img/logoB_google.png';
?>
<!DOCTYPE html>
<html lang="<?php echo im_h($lang); ?>" dir="<?php echo im_h($dir); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo im_h(im_text('page_title', $lang)); ?></title>
    <style>
        :root {
            --brand: #0f5f78;
            --accent: #0f766e;
            --ink: #172033;
            --muted: #526176;
            --line: #dbe4f0;
            --paper: #ffffff;
            --bg: #eef3f8;
            --soft: #e8f6fb;
            --warn: #fff7df;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.5;
        }
        .page {
            width: min(940px, calc(100% - 24px));
            margin: 0 auto;
            padding: 18px 0 34px;
        }
        .hero, .card {
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: 10px;
            box-shadow: 0 10px 24px rgba(15, 37, 56, .08);
        }
        .hero {
            padding: 18px;
            display: grid;
            grid-template-columns: 78px 1fr;
            gap: 16px;
            align-items: center;
        }
        .logo {
            width: 72px;
            height: 72px;
            object-fit: contain;
        }
        h1 {
            margin: 0;
            font-size: clamp(1.45rem, 3.6vw, 2.2rem);
            line-height: 1.1;
        }
        .school {
            color: var(--muted);
            font-weight: 700;
            margin-bottom: 4px;
        }
        .subtitle {
            color: var(--muted);
            margin-top: 8px;
            font-size: 1.05rem;
        }
        .tools {
            margin-top: 14px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto auto;
            gap: 10px;
            align-items: end;
        }
        label {
            display: block;
            color: var(--muted);
            font-weight: 700;
            margin-bottom: 4px;
        }
        select, .switch {
            width: 100%;
            border: 1px solid #b8c7d8;
            border-radius: 8px;
            background: #fff;
            color: var(--ink);
            font-size: 1rem;
            padding: 10px 12px;
        }
        .switch {
            display: inline-flex;
            width: auto;
            text-decoration: none;
            font-weight: 800;
        }
        .switch.active {
            background: var(--brand);
            color: #fff;
            border-color: var(--brand);
        }
        .notice {
            margin-top: 14px;
            border-left: 5px solid #0ea5e9;
            background: var(--soft);
            padding: 12px 14px;
            border-radius: 8px;
        }
        .notice.warning {
            border-left-color: #f59e0b;
            background: var(--warn);
        }
        .card {
            margin-top: 16px;
            padding: 22px;
        }
        .mail-subject {
            color: var(--brand);
            font-size: 1.4rem;
            margin: 0 0 18px;
        }
        .link-placeholder {
            border: 2px dashed #94a3b8;
            background: #f8fafc;
            border-radius: 10px;
            padding: 16px;
            font-weight: 800;
            text-align: center;
            margin: 18px 0;
        }
        ul {
            padding-left: 22px;
        }
        [dir="rtl"] ul {
            padding-left: 0;
            padding-right: 22px;
        }
        .signature {
            margin-top: 20px;
            font-weight: 700;
        }
        @media (max-width: 720px) {
            .hero { grid-template-columns: 58px 1fr; gap: 12px; }
            .logo { width: 54px; height: 54px; }
            .tools { grid-template-columns: 1fr; }
            .switch { width: 100%; justify-content: center; }
            .card { padding: 18px; }
        }
    </style>
</head>
<body>
<main class="page">
    <section class="hero">
        <img class="logo" src="<?php echo im_h($logoSrc); ?>" alt="">
        <div>
            <div class="school"><?php echo im_h($istituto); ?></div>
            <h1><?php echo im_h(im_text('page_title', $lang)); ?></h1>
            <div class="subtitle"><?php echo im_h(im_text($subjectKey, $lang)); ?></div>
        </div>
    </section>

    <form class="tools" method="get">
        <input type="hidden" name="tipo" value="<?php echo im_h($tipo); ?>">
        <div>
            <label for="lang"><?php echo im_h(im_text('language', $lang)); ?></label>
            <select id="lang" name="lang" onchange="this.form.submit()">
                <?php foreach ($languages as $code => $info) : ?>
                    <?php $label = $info['native'] . ' - ' . $info['italian']; ?>
                    <option value="<?php echo im_h($code); ?>" <?php echo $code === $lang ? 'selected' : ''; ?>>
                        <?php echo im_language_flag_html($code); ?> <?php echo im_h($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <a class="switch <?php echo $tipo === 'prime' ? 'active' : ''; ?>" href="?tipo=prime&amp;lang=<?php echo im_h($lang); ?>">Prime</a>
        <a class="switch <?php echo $tipo === 'terze' ? 'active' : ''; ?>" href="?tipo=terze&amp;lang=<?php echo im_h($lang); ?>">Terze</a>
    </form>

    <div class="notice"><?php echo im_h(im_text('official_notice', $lang)); ?></div>
    <div class="notice"><?php echo im_h(im_text('personal_link_notice', $lang)); ?></div>
    <?php if (!$hasTranslation) : ?>
        <div class="notice warning">Traduzione non ancora completa per questa lingua: viene mostrato il testo italiano dove manca la traduzione.</div>
    <?php endif; ?>

    <article class="card">
        <h2 class="mail-subject"><?php echo im_h(im_text($subjectKey, $lang)); ?></h2>
        <?php echo im_paragraph('greeting', $lang); ?>
        <?php echo im_paragraph($introKey, $lang); ?>

        <div class="link-placeholder"><?php echo im_h(im_text('personal_link_placeholder', $lang)); ?></div>
        <?php echo im_paragraph($reservedKey, $lang); ?>

        <ul>
            <?php foreach ($bulletKeys as $bulletKey) : ?>
                <li><?php echo im_h(im_text($bulletKey, $lang)); ?></li>
            <?php endforeach; ?>
        </ul>

        <?php echo im_paragraph('deadline', $lang); ?>
        <?php echo im_paragraph($attachmentKey, $lang); ?>
        <?php echo im_paragraph('paper_delivery', $lang); ?>
        <?php echo im_paragraph('contacts', $lang); ?>
        <?php echo im_paragraph('closing_common', $lang); ?>
        <div class="signature"><?php echo nl2br(im_h(str_replace('\n', "\n", im_text('signature', $lang)))); ?></div>
    </article>
</main>
</body>
</html>
