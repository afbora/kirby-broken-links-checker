<?php

use Kirby\Cms\App as Kirby;
use Kirby\Cms\Find;
use Kirby\Toolkit\I18n;
use Oweb\GoogleTranslate\Request;

return [
    'broken-links-checker' => function ($kirby) {
        return [
            'label' => 'Broken links checker',
            'icon' => 'url',
            'menu' => true,
            'link' => 'broken-links-checker',
            'views' => [
                [
                    // the Panel patterns must not start with 'panel/',
                    // the `panel` slug is automatically prepended.
                    'pattern' => 'broken-links-checker',
                    'action' => function () use ($kirby) {
                        return [
                            // the Vue component can be defined in the
                            // `index.js` of your plugin
                            'component' => 'BrokenLinksChecker',

                            // the document title for the current view
                            'title' => 'Broken links checker',

                            // pass props to panel ui via fiber
                            'props' => [
                                'base' => $kirby->site()->url(),
                            ],
                        ];
                    }
                ]
            ]
        ];
    }
];
