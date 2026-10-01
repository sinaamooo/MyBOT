<?php
$nbx = [
    'base' => base_path(),
    'pretty' => (bool)config('app.pretty_urls', true),
    'csrf' => csrf_token(),
    'currency' => setting('currency', 'تومان'),
];
?>
<div class="toasts" aria-live="polite"></div>
<script>
window.NBX = <?= json_encode($nbx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;
NBX.url = function (p) { p = String(p || '').replace(/^\/+/, ''); return NBX.pretty ? NBX.base + '/' + p : NBX.base + '/index.php' + (p ? '?r=' + p : ''); };
window.__flashes = <?= json_encode(flashes(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
</script>
<script src="<?= asset('js/app.js') ?>" defer></script>
<?php if (!empty($charts)): ?><script src="<?= asset('vendor/chart.umd.min.js') ?>" defer></script><?php endif; ?>
<?php if (!empty($panelCss)): ?><script src="<?= asset('js/panel.js') ?>" defer></script><?php endif; ?>
<?= stack('scripts') ?>
