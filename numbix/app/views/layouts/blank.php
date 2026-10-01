<?= partial('head', get_defined_vars() + ['noindex' => true]) ?>
<body class="no-bottom-nav">
<?= $content ?>
<?= partial('scripts', get_defined_vars()) ?>
</body>
</html>
