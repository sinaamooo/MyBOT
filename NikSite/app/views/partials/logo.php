<?php
static $n = 0;
$n++;
$gid = 'nbxg' . $n;
?>
<a href="<?= url('/') ?>" class="logo" aria-label="<?= e(site_name()) ?>">
  <svg class="logo-mark" viewBox="0 0 48 48" aria-hidden="true">
    <defs>
      <radialGradient id="<?= $gid ?>p" cx="34%" cy="30%" r="75%"><stop offset="0" stop-color="#F5F3FF"/><stop offset=".28" stop-color="#A78BFA"/><stop offset=".62" stop-color="#7C3AED"/><stop offset="1" stop-color="#2E1065"/></radialGradient>
      <linearGradient id="<?= $gid ?>r" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#67E8F9"/><stop offset=".5" stop-color="#F0ABFC"/><stop offset="1" stop-color="#A78BFA"/></linearGradient>
    </defs>
    <path d="M5.5 30.5C2.6 26 9.6 19.1 21.2 15c11.6-4 22.4-3.6 25.3.9" fill="none" stroke="url(#<?= $gid ?>r)" stroke-width="2.4" stroke-linecap="round" opacity=".55"/>
    <circle cx="24" cy="24" r="15" fill="url(#<?= $gid ?>p)"/>
    <path d="M18 30V18l12 12V18" fill="none" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M46.5 15.9c2.9 4.5-4.1 11.4-15.7 15.5-11.6 4.1-22.4 3.7-25.3-.9" fill="none" stroke="url(#<?= $gid ?>r)" stroke-width="2.4" stroke-linecap="round"/>
    <circle cx="41" cy="9" r="2.2" fill="#fff"/><circle cx="6" cy="40" r="1.4" fill="#C4B5FD"/>
  </svg>
  <span class="logo-text"><span class="logo-name"><?= e(site_name()) ?></span><small>NUMBIX</small></span>
</a>
