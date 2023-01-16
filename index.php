<?php

use Kirby\Cms\App as Kirby;

Kirby::plugin('ahmetbora/broken-links-checker', [
    'areas' => include __DIR__ . '/config/areas.php',
    'commands' => include __DIR__ . '/config/commands.php',
    'options' => include __DIR__ . '/config/options.php',
]);
