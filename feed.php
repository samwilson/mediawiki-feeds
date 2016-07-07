<?php
require 'vendor/autoload.php';

// Get the feed parameters.
if (!isset($_GET['category'])) {
    exit(1);
}
$cat = $_GET['category'];
$wikiveristy = "https://en.wikiversity.org/w/";
$url = rtrim(((isset($_GET['url'])) ? $_GET['url'] : $wikiveristy), '/');
$numItems = (isset($_GET['num']) && is_int($_GET['num'])) ? $_GET['num'] : 10;

// Construct the feed.
$feedBuilder = new \Samwilson\MediaWikiFeeds\FeedBuilder($url, $cat, $numItems);
$noCache = isset($_GET['nocache']);
if (!$feedBuilder->hasCurrentCache() || $noCache) {
    $feedBuilder->buildAndCacheFeed();
}

// Output the feed. Should this use application/rss+xml?
header("Content-type:text/xml");
echo file_get_contents($feedBuilder->getCachePath());
exit(0);
