<?php
require __DIR__.'/vendor/autoload.php';
require __DIR__.'/config.php';

$cat = 

// Get the feed parameters.
$cat = (!empty($_GET['category'])) ? $_GET['category'] : $defaults['category'];
$url = (!empty($_GET['url'])) ? $_GET['url'] : $defaults['url'];
$numItems = (int) (!empty($_GET['num'])) ? $_GET['num'] : $defaults['num'];
$title = (!empty($_GET['title'])) ? $_GET['title'] : (isset($defaults['title']) ? $defaults['title'] : $cat);

// Construct the feed.
$feedBuilder = new \Samwilson\MediaWikiFeeds\FeedBuilder($url, $cat, $numItems, $title);
$noCache = isset($_GET['nocache']);
if (!$feedBuilder->hasCurrentCache() || $noCache) {
    $feedBuilder->buildAndCacheFeed();
}

// Output the feed. Should this use application/rss+xml?
header("Content-type:text/xml;charset=utf-8");
readfile($feedBuilder->getCachePath());
exit(0);
