<?php

use Kirby\Cms\App as Kirby;

Kirby::plugin('ahmetbora/broken-links-checker', [
    'areas' => include __DIR__ . '/config/areas.php'
]);
