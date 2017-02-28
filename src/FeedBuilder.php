<?php

namespace Samwilson\MediaWikiFeeds;

use Exception;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\MediawikiFactory;
use Mediawiki\Api\SimpleRequest;
use Mediawiki\DataModel\Page;
use Mediawiki\DataModel\PageIdentifier;
use Mediawiki\DataModel\Pages;
use Mediawiki\DataModel\Title;
use Suin\RSSWriter\Channel;
use Suin\RSSWriter\Item;
use Symfony\Component\DomCrawler\Crawler;
use Suin\RSSWriter\Feed;

class FeedBuilder {

    /** @var string */
    protected $scriptUrl;

    /** @var string */
    protected $category;

    /** @var integer */
    protected $numItems;

    /** @var string */
    protected $title;

    /** @var string */
    protected $cacheDir;

    public function __construct($scriptUrl, $category, $numItems = 10, $title = null) {
        $this->scriptUrl = rtrim($scriptUrl, '/') . '/';
        $this->category = $category;
        $this->numItems = $numItems;
        $this->title = (!is_null($title)) ? $title : $category;
        $this->setCacheDir(__DIR__ . '/../feeds/');
    }

    public function getFeedId() {
        return md5($this->scriptUrl . $this->category . $this->numItems . $this->title);
    }

    public function setCacheDir($cacheDir) {
        $this->cacheDir = realpath($cacheDir);
        if (!is_dir($this->cacheDir)) {
            throw new Exception("Cache directory not found: $this->cacheDir");
        }
    }

    public function getCacheDir() {
        return $this->cacheDir;
    }

    /**
     * Get the full filesystem path to the cached RSS file.
     * @return string
     */
    public function getCachePath() {
        return $this->getCacheDir() . "/" . $this->getFeedId() . ".rss";
    }

    public function hasCurrentCache() {
        $feedFile = $this->getCachePath();
        $cacheTime = 60 * 60 * 1; // 1 hour.
        $hasCurrentCache = (file_exists($feedFile) && filemtime($feedFile) > (time() - $cacheTime));
        return $hasCurrentCache;
    }

    /**
     * The main action happens here.
     * Build the feed, and write it to a local cache file.
     */
    public function buildAndCacheFeed() {
        $api = MediawikiApi::newFromApiEndpoint($this->scriptUrl . '/api.php');
        $items = $this->getRecentNPages($this->scriptUrl, $api, $this->category, $this->numItems);
        $feed = $this->getFeed($this->scriptUrl, $this->category, $items);
        $feedFile = $this->getCachePath();
        if (!is_dir(dirname($feedFile))) {
            mkdir(dirname($feedFile));
        }
        file_put_contents($feedFile, $feed->render());
        chmod($feedFile, 0664); // For CLI's benefit (if it's the same group).
    }

    protected function getRecentNPages($url, MediawikiApi $api, $cat, $numItems) {
        $factory = new MediawikiFactory($api);

        // Find the category namespace ID.
        $catNs = $factory->newNamespaceGetter()->getNamespaceByCanonicalName('Category');

        // Get all the pages.
        $catTraverser = $factory->newCategoryTraverser();
        $catPage = new Page(new PageIdentifier(new Title($cat, $catNs->getId())));
        $allPages = $catTraverser->descend($catPage);

        // Sort them by publication date.
        $pages = [];
        $pageNum = 1;
        foreach ($allPages->toArray() as $page) {
            $info = $this->getPageInfo($url, $api, $page);
            // In case multiple posts have the exact same time, give them a decimal suffix.
            $pageKey = str_pad($pageNum, strlen(count($allPages->toArray())), '0', STR_PAD_LEFT);
            $pages[$info['pubdate'] . '.' . $pageKey] = $info;
            $pageNum++;
        }
        krsort($pages);

        // Select only the top N items.
        return array_slice($pages, 0, $numItems);
    }

    protected function getFeed($wiki, $cat, $items) {
        $channel = new Channel();
        $channel->title($this->title);
        $channel->url($wiki.'index.php?title='.urlencode($cat));
        foreach ($items as $info) {
            $item = new Item();
            $item->title($info['title'])
                    ->description($info['description'])
                    ->contentEncoded($info['content'])
                    ->url($info['url'])
                    ->author(join(', ', $info['authors']))
                    ->pubDate($info['pubdate'])
                    ->guid($info['guid'], true)
                    ->appendTo($channel);
            continue;
        }
        $feed = new Feed();
        $feed->addChannel($channel);
        return $feed;
    }

    protected function getPageInfo($url, MediawikiApi $api, Page $p) {
        $fact = new MediawikiFactory($api);
        $page = $fact->newPageGetter()->getFromPage($p);
        $pageName = $page->getPageIdentifier()->getTitle()->getText();

        // Get the page metadata.
        $params = [
            'prop' => 'revisions',
            'rvprop' => 'ids|timestamp',
            'titles' => $pageName,
        ];
        $queryResult = $api->getRequest(new SimpleRequest('query', $params));
        $revisionInfo = array_shift($queryResult['query']['pages']);

        // Get the page text, and categories etc.
        $parseResult = $fact->newParser()->parsePage($page->getPageIdentifier());
        $content = $parseResult['text']['*'];
        $pageCrawler = new Crawler;
        $pageCrawler->addHTMLContent($content, 'UTF-8');

        // Get the description
        // (either the description item property, or just the truncated content).
        $descriptionElements = $pageCrawler->filterXPath("//*[@itemprop='description']//text()");
        if ($descriptionElements->count() > 0) {
            $description = join('', $descriptionElements->each(function (Crawler $node) {
                return $node->text();
            }));
        } else {
            $description = trim(mb_substr(strip_tags($content), 0, 400, 'utf-8'));
        }

        // Try to get the publication date out of the HTML.
        $timeElements = $pageCrawler->filterXPath('//time');
        if ($timeElements->count() > 0 && $timeElements->first()->attr('datetime') !== null) {
            $time = strtotime($timeElements->first()->attr('datetime'));
        } else {
            $time = strtotime($revisionInfo['revisions'][0]['timestamp']);
        }

        // Get a list of contributors.
        $contribResult = $api->getRequest(new SimpleRequest('query', [
            'prop' => 'contributors',
            'titles' => $pageName,
        ]));
        $contribs = [];
        if (isset($contribResult['query']['pages'])) {
            $contribsTmp = array_shift($contribResult['query']['pages']);
            if (isset($contribsTmp['contributors'])) {
                foreach ( $contribsTmp['contributors'] as $c ) {
                    $contribs[] = $c['name'];
                }
            }
        }

        // Put all the above together.
        $feedItem = [
            'title' => $revisionInfo['title'],
            'description' => $description,
            'content' => $content,
            'url' => $url . 'index.php?curid=' . $revisionInfo['pageid'],
            'authors' => $contribs,
            'pubdate' => $time,
            'guid' => $url . 'index.php?oldid=' . $revisionInfo['revisions'][0]['revid'],
        ];
        return $feedItem;
    }

}
