<?php

use Kirby\CLI\CLI;
use Kirby\Http\Remote;
use Kirby\Http\Url;
use Kirby\Toolkit\Str;


return [
    'check:links' => [
        'description' => 'Checks for broken sites & links',
        'args' => [
            'duration' => [
                'prefix' => 'd',
                'longPrefix' => 'duration',
                'description' => 'Cache duration for URL checks (in minutes)',
                'defaultValue' => 5,
                'castTo' => 'int',
            ],
            'force' => [
                'prefix' => 'f',
                'longPrefix' => 'force',
                'description' => 'Force cache to be refreshed reload',
                'noValue' => true,
            ],
            'external' => [
                'prefix' => 'e',
                'longPrefix' => 'external',
                'description' => 'List only external URLs',
                'noValue' => true,
            ],
            'json' => [
                'prefix' => 'j',
                'longPrefix' => 'json',
                'description' => 'Output report as JSON',
                'noValue' => true,
            ],
            'strict' => [
                'prefix' => 's',
                'longPrefix' => 'strict',
                'description' => 'Stop execution upon page error',
                'noValue' => true,
            ],
            'timeout' => [
                'prefix' => 't',
                'longPrefix' => 'timeout',
                'description' => 'Request timeout (in seconds)',
                'defaultValue' => 60,
                'castTo' => 'int',
            ],
        ],
        'command' => static function (CLI $cli): void {
            # Retrieve current instance
            $kirby = $cli->kirby();
            $site = $kirby->site();

            # Check arguments
            # (1) Cache duration
            $duration = $cli->arg('duration');

            # If invalid ..
            if ($duration < 0) {
                # .. print error message
                $cli->backgroundRed()->black(sprintf('Invalid cache duration: "%s"', $duration));

                # .. abort execution
                exit;
            }

            # (2) Request timeout
            $timeout = $cli->arg('timeout');

            # If invalid ..
            if ($timeout < 0) {
                # .. print error message
                $cli->backgroundRed()->black(sprintf('Invalid request timeout: "%s"', $timeout));

                # .. abort execution
                exit;
            }

            # Fetch all pages
            $indexedPages = $site->index();

            # Create data array
            $internalPages = [];

            # Loop over all pages
            foreach ($indexedPages as $indexedPage) {
                # If multiple languages enabled ..
                if ($kirby->multilang()) {
                    # .. inspect each language
                    foreach ($kirby->languages() as $language) {
                        # Retrieve page version & store it
                        $langPage = $site->visit($indexedPage, $language->code());
                        $internalPages[Url::path($langPage->url())] = $langPage;
                    }
                }

                # .. otherwise, use its regular form
                else {
                    $internalPages[Url::path($indexedPage->url())] = $indexedPage;
                }
            }

            # Set defaults
            $totalPages = count($internalPages);
            $pageCount = 1;

            # Initiate progress bar
            # See https://climate.thephpleague.com/terminal-objects/progress-bar
            $pageProgress = $cli->progress()->total($totalPages);

            # Create data array
            $links = [];

            # Loop over pages
            foreach ($internalPages as $slug => $page) {
                # Attempt to ..
                try {
                    # .. loading their HTML contents
                    $dom = new \DOMDocument();
                    @$dom->loadHTML($page->render());

                    # For more information,
                    # see https://www.php.net/manual/en/class.domdocument.php

                    # Loop over 'a' tags
                    foreach ($dom->getElementsByTagName('a') as $link) {
                        # Get link target & set default target
                        $src = $link->getAttribute('href');
                        $target = 'external';

                        # Define URL comparison rules
                        $sameBaseURL = Url::stripPath(Url::to($src)) === Url::home();
                        $pathMatches = array_key_exists(Url::path($src), $internalPages) === true;

                        # If link has internal target ..
                        if ($src === '/' || ($sameBaseURL && $pathMatches)) {
                            # .. and they are to be ignored ..
                            if ($cli->arg('external') === true) {
                                # .. move on
                                continue;
                            }

                            # .. otherwise, adjust target
                            $target = 'internal';
                        }

                        # Skip emails
                        if (Str::startsWith($src, 'mailto:')) {
                            continue;
                        }

                        # Store data
                        $links[] = [
                            'link' => $src,
                            'target' => $target,
                            'foundAt' => $slug,
                        ];
                    }

                    # Define output message
                    $phrase = sprintf('[%s/%s] Processing page "%s"', $pageCount, $totalPages, $slug);
                }

                # If something breaks ..
                catch (\Exception $error) {
                    # .. while 'strict' mode enabled ..
                    if ($cli->arg('strict') === true) {
                        # .. print error message
                        $cli->error(sprintf('Something went wrong: "%s" (on page "%s")', $error->getMessage(), $slug));

                        # .. abort execution
                        exit;
                    }

                    # .. otherwise, change output message only
                    $phrase = sprintf('[%s/%s] Skipping page "%s"', $pageCount, $totalPages, $slug);
                }

                # Update progress bar & print output message
                $pageProgress->current($pageCount, $phrase);
                $pageCount++;
            }

            # Print newline
            $cli->br();

            # Set defaults
            $totalLinks = count($links);
            $linkCount = 1;

            # Initialize plugin cache
            $cache = $kirby->cache('ahmetbora.broken-links-checker');

            # Initiate progress bar
            $linkProgress = $cli->progress()->total($totalLinks);

            # Prepare GET requests
            # (1) UA strings
            $ua = [
                # Firefox
                'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome'
                .'/51.0.2704.103 Safari/537.36',
                # Opera
                'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome'
                .'/51.0.2704.106 Safari/537.36 OPR/38.0.2220.41',
                # Safari
                'Mozilla/5.0 (iPhone; CPU iPhone OS 13_5_1 like Mac OS X) AppleWebKit/605.1.15'
                .' (KHTML, like Gecko) Version/13.1.1 Mobile/15E148 Safari/604.1',
                # Internet Explorer
                'Mozilla/5.0 (compatible; MSIE 9.0; Windows Phone OS 7.5; Trident/5.0; IEMobil'
                .'e/9.0)',
            ];

            # (2) Define HTTP headers
            $headers = [
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Cache-Control'   => 'no-cache',
                'Pragma'          => 'no-cache',
                'Referer'         => 'https://google.com/',
                'User-Agent'      => array_rand($ua),
            ];

            # Create data array
            $results = [];

            # Loop over extracted links
            foreach ($links as $item) {
                # Get indicator where link was found
                $key = $item['foundAt'];

                # Create data subarray (if not present)
                if (array_key_exists($key, $results) === false) {
                    $results[$key] = [];
                }

                # Remove indicator
                unset($item['foundAt']);

                # Skip duplicates
                if (in_array($item['link'], A::pluck($results[$key], 'link')) === true) {
                    continue;
                }

                # Define output message
                $phrase = sprintf('[%s/%s] Checking "%s"', $linkCount, $totalLinks, $item['link']);

                # Check if page is internal ..
                if ($item['target'] === 'internal') {
                    # Adjust output message
                    $phrase = sprintf('[%s/%s] Checking "%s" (internal)', $linkCount, $totalLinks, $item['link']);

                    # Prefill information
                    $item['isAvailable'] = true;
                    $item['statusCode'] = 200;

                    # Store data
                    $results[$key][] = $item;
                }

                # .. otherwise, page is external
                else {
                    # Build cache entry key
                    $cacheKey = md5($item['link']);

                    # Fetch cache entry
                    $entry = $cache->get($cacheKey);

                    # Update cache if empty or 'force' mode activated
                    if ($entry === null || $cli->arg('force') === true) {
                        # Attempt to ..
                        try {
                            # .. request URL
                            $response = Remote::get($item['link'], ['headers' => $headers, 'timeout' => $timeout]);

                            # Store data
                            $item['isAvailable'] = true;
                            $item['statusCode'] = $response->code();
                        }

                        # .. while detecting URLs being unresolvable, mostly cURL errors like
                        # (1) .. invalid / non-existing targets
                        # (2) .. invalid / expired SSL certificates
                        catch (\Exception $error) {
                            # Store data
                            $item['isAvailable'] = false;
                            $item['statusCode'] = 404;
                            $item['error'] = sprintf('Error "%s": %s', $error->getCode(), $error->getMessage());
                        }

                        # Add data to cache
                        $cache->set($cacheKey, $item, $duration);
                    }

                    # .. otherwise ..
                    else {
                        # .. adjust output message
                        $phrase = sprintf('[%s/%s] Checking "%s" (from cache)', $linkCount, $totalLinks, $item['link']);
                    }

                    # Store data
                    $results[$key][] = $entry ?? $item;
                }

                # Update progress bar & print output message
                $linkProgress->current($linkCount, $phrase);
                $linkCount++;
            }

            if (count($results) > 0) {
                # Print newlines
                $cli->br();
                $cli->br();

                # Print padded results title
                $cli->flank('RESULTS');

                # If specified ..
                if ($cli->arg('json') === true) {
                    # .. output as JSON
                    $cli->out($cli->json($results));
                }

                # .. otherwise, pretty-print it
                else {
                    # Loop over pages
                    foreach ($results as $slug => $links) {
                        # Print newline & page title
                        $cli->br();
                        $cli->out(sprintf('Found on page "%s":', $slug));

                        # Loop over its links
                        foreach ($links as $idx => $item) {
                            # Print URLs as either ..
                            if ($item['isAvailable'] === true) {
                                # (1) .. available
                                $cli->green(sprintf('[%s]: %s (%s)', $idx + 1, $item['link'], $item['statusCode']));
                            }

                            # .. or ..
                            else {
                                # (2) .. unavailable
                                $cli->red(sprintf('[%s]: %s (%s)', $idx + 1, $item['link'], $item['statusCode']));
                            }
                        }
                    }
                }

                # Print newlines
                $cli->br();
                $cli->br();

                # Print closing statement
                $cli->success('Complete!');
            }

            # .. otherwise ..
            else {
                $cli->backgroundBlue()->black('No links found!');
            }
        },
    ],
];
