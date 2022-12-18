<?php

use Kirby\Cms\App as Kirby;

@include_once __DIR__ . '/vendor/autoload.php';

Kirby::plugin('ahmetbora/broken-links-checker', [
    'areas' => include __DIR__ . '/config/areas.php'
]);
