<?php
static $n = 0;
$n++;
$gid = 'nbxg' . $n;
?>
<a href="<?= url('/') ?>" class="logo" aria-label="<?= e(site_name()) ?>">
  <svg class="logo-mark" viewBox="0 0 48 48" aria-hidden="true">
    <defs>
      <linearGradient id="<?= $gid ?>a" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#A78BFA"/><stop offset=".5" stop-color="#6C4CF1"/><stop offset="1" stop-color="#4338CA"/></linearGradient>
      <linearGradient id="<?= $gid ?>b" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".45"/><stop offset=".55" stop-color="#fff" stop-opacity="0"/></linearGradient>
    </defs>
    <rect x="2" y="2" width="44" height="44" rx="14" fill="url(#<?= $gid ?>a)"/>
    <rect x="2" y="2" width="44" height="44" rx="14" fill="url(#<?= $gid ?>b)"/>
    <path d="M15 33V15l18 18V15" fill="none" stroke="#fff" stroke-width="4.6" stroke-linecap="round" stroke-linejoin="round"/>
    <circle cx="36.5" cy="11.5" r="3.4" fill="#67E8F9"/>
  </svg>
  <span class="logo-text"><span class="logo-name"><?= e(site_name()) ?></span><small>NUMBIX</small></span>
</a>
