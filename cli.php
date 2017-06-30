#!/usr/bin/env php
<?php

use diversen\parseArgv;
use Samwilson\MediaWikiFeeds\FeedBuilder;

// Make sure this isn't being executed from somewhere else.
if (php_sapi_name() !== 'cli') {
    echo "This script must be called from the command line" . PHP_EOL;
    exit(1);
}
require __DIR__.'/vendor/autoload.php';
require __DIR__.'/config.php';

$args = new parseArgv();

foreach (['category', 'url', 'num'] as $param) {
    if (!isset($args->flags[$param]) && !isset($defaults[$param])) {
        echo "Please set the '--$param' flag (or set the '$param' value in the \$defaults array in config.php)\n";
        exit(1);
    }
}

$cat = (isset($args->flags['category'])) ? $args->flags['category'] : $defaults['category'];
$url = rtrim(((isset($args->flags['url'])) ? $args->flags['url'] : $defaults['url']), '/');
$numItems = (isset($args->flags['num']) && is_int($args->flags['num'])) ? $args->flags['num'] : 10;
$title = ((isset($args->flags['title'])) ? $args->flags['title'] : $cat);
$type = ((isset($args->flags['type'])) ? $args->flags['type'] : 'rss');

// Construct the feed.
$feedBuilder = FeedBuilder::factory($url, $cat, $numItems, $title, $type);
$feedBuilder->setCacheDir(__DIR__.'/var/feeds');
$feedBuilder->buildAndCacheFeed();
if (isset($args->flags['v'])) {
    echo "Feed has been written to: ".$feedBuilder->getCachePath()."\n";
}
