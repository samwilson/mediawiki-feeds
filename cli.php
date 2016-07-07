<?php

require 'vendor/autoload.php';

$args = new diversen\parseArgv();

if (!isset($args->flags['category'])) {
    echo "Please set the 'category' flag.\n";
    exit(1);
}
$cat = $args->flags['category'];
$wikiveristy = "https://en.wikiversity.org/w/";
$url = rtrim(((isset($args->flags['url'])) ? $args->flags['url'] : $wikiveristy), '/');
$numItems = (isset($args->flags['num']) && is_int($args->flags['num'])) ? $args->flags['num'] : 10;

// Construct the feed.
$feedBuilder = new \Samwilson\MediaWikiFeeds\FeedBuilder($url, $cat, $numItems);
$feedBuilder->buildAndCacheFeed();
