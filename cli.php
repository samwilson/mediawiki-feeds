#!/usr/bin/env php
<?php

// Make sure this isn't being executed from somewhere else.
if (php_sapi_name() !== 'cli') {
    echo "This script must be called from the command line" . PHP_EOL;
    exit(1);
}
require __DIR__.'/vendor/autoload.php';

$args = new diversen\parseArgv();

if (!isset($args->flags['category'])) {
    echo "Please set the '--category' flag.\n";
    exit(1);
}
$cat = $args->flags['category'];
$wikiveristy = "https://en.wikiversity.org/w/";
$url = rtrim(((isset($args->flags['url'])) ? $args->flags['url'] : $wikiveristy), '/');
$numItems = (isset($args->flags['num']) && is_int($args->flags['num'])) ? $args->flags['num'] : 10;

// Construct the feed.
$feedBuilder = new \Samwilson\MediaWikiFeeds\FeedBuilder($url, $cat, $numItems);
$feedBuilder->buildAndCacheFeed();
