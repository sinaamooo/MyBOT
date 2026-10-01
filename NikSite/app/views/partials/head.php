<?php
$pageTitle = ($title ?? '') !== '' ? $title . ' | ' . site_name() : site_name() . ' | ' . setting('site_tagline', 'رشد واقعی در شبکه‌های اجتماعی');
$desc = $description ?? setting('site_description', '');
?>
<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?></title>
<?php if ($desc): ?><meta name="description" content="<?= e(str_limit($desc, 160)) ?>"><?php endif; ?>
<meta name="theme-color" content="#7C3AED">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(site_name()) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<?php if ($desc): ?><meta property="og:description" content="<?= e(str_limit($desc, 200)) ?>"><?php endif; ?>
<?php if (!empty($noindex)): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<?php if (!empty($canonical)): ?><link rel="canonical" href="<?= e($canonical) ?>"><?php endif; ?>
<link rel="icon" href="<?= e(base_path()) ?>/assets/img/favicon.svg" type="image/svg+xml">
<link rel="preload" href="<?= e(base_path()) ?>/assets/fonts/Vazirmatn-Variable.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<?php if (!empty($panelCss)): ?><link rel="stylesheet" href="<?= asset('css/panel.css') ?>"><?php endif; ?>
<script>(function(){var d=document.documentElement;try{var t=localStorage.getItem('nbx-th')||'<?= e(setting('site_theme', 'light')) ?>';if(t==='auto'){t=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';}d.dataset.theme=t;}catch(e){d.dataset.theme='light';}var n=navigator,c=n.connection||{};if((n.hardwareConcurrency&&n.hardwareConcurrency<=4)||(n.deviceMemory&&n.deviceMemory<=4)||c.saveData||matchMedia('(prefers-reduced-motion: reduce)').matches){d.classList.add('lite');}})();</script>
<?= setting('head_code', '') ?>
</head>
