<?php

date_default_timezone_set('Asia/Tehran');
mb_internal_encoding('UTF-8');

if (!function_exists('array_is_list')) {
    function array_is_list(array $a): bool
    {
        $i = 0;
        foreach ($a as $k => $_) {
            if ($k !== $i++) {
                return false;
            }
        }
        return true;
    }
}

foreach (['Core', 'Http', 'UI', 'Fmt', 'Prices', 'Cards'] as $f) {
    require_once __DIR__ . '/' . $f . '.php';
}
foreach (glob(__DIR__ . '/*.php') as $f) {
    require_once $f;
}
foreach (glob(__DIR__ . '/Handlers/*.php') as $f) {
    require_once $f;
}

Config::load(dirname(__DIR__));
