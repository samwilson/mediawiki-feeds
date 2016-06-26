<?php


// Get the feed parameters.
if (!isset($_GET['category'])) {
    exit(1);
}
$cat = $_GET['category'];
$wikiveristy = "https://en.wikiversity.org/w/";
$url = rtrim(((isset($_GET['url'])) ? $_GET['url'] : $wikiveristy), '/');
$numItems = (isset($_GET['num']) && is_int($_GET['num'])) ? $_GET['num'] : 10;


// If the feed file is not too old, just use it.
$feedId = md5($url . $cat);
$feedFile = __DIR__ . "/feeds/$feedId.rss";
$cacheTime = 60 * 60 * 1;
$canUseCache = (file_exists($feedFile) && filemtime($feedFile) > (time() - $cacheTime));
$noCache = isset($_GET['nocache']);
if ($canUseCache && !$noCache) {
    outputFeedFile($feedFile);
}


// Otherwise, build the feed and output it.
require 'vendor/autoload.php';
try {
    $wikimate = new Wikimate($url . '/api.php');
    $items = getRecentNPages($url, $wikimate, $cat, $numItems);
    $feed = getFeed($url, $cat, $items);
    if (!is_dir(dirname($feedFile))) {
        mkdir(dirname($feedFile));
    }
    file_put_contents($feedFile, $feed->render());
    outputFeedFile($feedFile);
} catch (\Exception $e) {
    echo $e->getMessage();
    exit(1);
}


// -----------------------------------------------------------------------------
// Functions only below here.
// -----------------------------------------------------------------------------

function outputFeedFile($feedFile) {
    // application/rss+xml
    header("Content-type:text/xml");
    echo file_get_contents($feedFile);
    exit(0);
}

function getRecentNPages($url, $wikimate, $cat, $numItems) {
    // Get all the pages.
    $allPages = getCategoryPages($wikimate, $cat);

    // Sort them by publication date.
    $pages = [];
    $pageNum = 1;
    foreach ($allPages as $page) {
        $info = getPageInfo($url, $wikimate, $page);
        $pages[$info['pubdate'] . ' ' . $pageNum] = $info;
        $pageNum++;
    }
    krsort($pages);

    // Select only the top N items.
    return array_slice($pages, 0, $numItems);
}

function getFeed($wiki, $cat, $items) {
    //$info = getPageInfo($wiki, $wikimate, 'User:Samwilson/Blog/Calculating totals of joined tables');
    $channel = new \Suin\RSSWriter\Channel();
    $channel->title($cat);
    $channel->url($wiki . '/index.php?title=' . $cat);
    foreach ($items as $info) {
        $item = new Suin\RSSWriter\Item();
        $item->title($info['title'])
                ->description($info['description'])
                ->contentEncoded($info['content'])
                ->url($info['url'])
                ->pubDate($info['pubdate'])
                ->guid($info['guid'], true)
                ->appendTo($channel);
        foreach ($info['authors'] as $author) {
            $item->author($author);
        }
        continue;
    }
    $feed = new Suin\RSSWriter\Feed();
    $feed->addChannel($channel);
    return $feed;
}

function getPageInfo($url, \Wikimate $wikimate, $pageName) {
    // Get the page metadata.
    $queryResult = $wikimate->query([
        'prop' => 'info',
        'titles' => $pageName,
    ]);
    $pageInfo = array_shift($queryResult['query']['pages']);
    // Get the page text, and categories etc.
    $parseResult = $wikimate->parse([
        'pageid' => $pageInfo['pageid'],
    ]);
    $content = $parseResult['parse']['text']['*'];
    $description = substr(strip_tags($content), 0, 400);

    // Try to get the publication date out of the HTML.
    $html = new SimpleXMLElement('<div>' . $parseResult['parse']['text']['*'] . '</div>');
    $timeElements = $html->xpath('//time');
    $firstTime = array_shift($timeElements);
    if (isset($firstTime['datetime'])) {
        $time = strtotime($firstTime['datetime']);
    } else {
        $time = strtotime($pageInfo['touched']);
    }

    // Get a list of contributors.
    $contribResult = $wikimate->query([
        'prop' => 'contributors',
        'titles' => $pageName,
    ]);
    $contribs = array();
    if (isset($contribResult['query']['pages'])) {
        $contribsTmp = array_shift($contribResult['query']['pages']);
        foreach ($contribsTmp['contributors'] as $c) {
            $contribs[] = $c['name'];
        }
    }

    // Construct the feed title from the last part of the page title (i.e. the subpage title)
    //$title = substr($pageInfo['title'], strrpos($pageInfo['title'], '/') + 1);
    $title = $pageInfo['title'];

    // Put all the above together.
    $feedItem = [
        'title' => $title,
        'description' => $description,
        'content' => $content,
        'url' => $url . '/index.php?curid=' . $pageInfo['pageid'],
        'authors' => $contribs,
        'pubdate' => $time,
        'guid' => $url . '/index.php?oldid=' . $pageInfo['lastrevid'],
    ];
    return $feedItem;
}

/**
 * Get all pages in a category and its subcategories.
 *
 * @param type $wikimate
 * @param type $cat
 * @return type
 */
function getCategoryPages($wikimate, $cat) {
    // First get all pages in the root category.
    $pages = getCategoryMembers($wikimate, $cat, 'page');
    // Then get all pages in subcategories of the root.
    $subcats = getCategoryMembers($wikimate, $cat, 'subcat');
    foreach ($subcats as $subcat) {
        $subcatpages = getCategoryPages($wikimate, $subcat);
        $pages = array_merge($pages, $subcatpages);
    }
    return $pages;
}

/**
 * Get all items in a category, by type.
 *
 * @param \Wikimate $wikimate The mediawiki instance to query
 * @param string $cat The category to search within
 * @param string $type Either 'page', 'subcat', or 'type'
 * @return string[] Array of page titles.
 * @throws \Exception When a thing is exceptionally wrong.
 */
function getCategoryMembers($wikimate, $cat, $type = 'page') {
    $pages = [];
    do {
        $continue = (isset($queryResult['continue'])) ? $queryResult['continue']['cmcontinue'] : '';
        //var_dump($continue);
        $queryResult = $wikimate->query([
            'action' => 'query',
            //'format' => 'json',
            'list' => 'categorymembers',
            'cmtype' => $type,
            'cmtitle' => $cat,
            'cmcontinue' => $continue,
        ]);
        if (isset($queryResult['error'])) {
            var_dump($queryResult);
            throw new \Exception($queryResult['error']['message']);
        }
        foreach ($queryResult['query']['categorymembers'] as $catMember) {
            // Key the array by page ID so we don't duplicate
            // when pages are in more than one subcategory.
            $pages[$catMember['pageid']] = $catMember['title'];
        }
    } while (isset($queryResult['continue']));
    return $pages;
}
