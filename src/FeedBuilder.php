<?php

namespace Samwilson\MediaWikiFeeds;

use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\SimpleRequest;

class FeedBuilder {

    private $scriptUrl, $category, $numItems, $cacheDir;

    public function __construct($scriptUrl, $category, $numItems = 10) {
        $this->scriptUrl = rtrim($scriptUrl, '/') . '/';
        $this->category = $category;
        $this->numItems = $numItems;
        $this->setCacheDir(__DIR__ . '/../feeds/');
    }

    public function getFeedId() {
        return md5($this->scriptUrl . $this->category . $this->numItems);
    }

    public function setCacheDir($cacheDir) {
        $this->cacheDir = realpath($cacheDir);
        if (!is_dir($this->cacheDir)) {
            throw new \Exception("Cache directory not found: $this->cacheDir");
        }
    }

    public function getCacheDir() {
        return $this->cacheDir;
    }

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
    }

    protected function getRecentNPages($url, MediawikiApi $api, $cat, $numItems) {
        // Get all the pages.
        $allPages = $this->getCategoryPages($api, $cat);

        // Sort them by publication date.
        $pages = [];
        $pageNum = 1;
        foreach ($allPages as $page) {
            $info = $this->getPageInfo($url, $api, $page);
            $pages[$info['pubdate'] . ' ' . $pageNum] = $info;
            $pageNum++;
        }
        krsort($pages);

        // Select only the top N items.
        return array_slice($pages, 0, $numItems);
    }

    protected function getFeed($wiki, $cat, $items) {
        $channel = new \Suin\RSSWriter\Channel();
        $channel->title($cat);
        $channel->url($wiki . '/index.php?title=' . $cat);
        foreach ($items as $info) {
            $item = new \Suin\RSSWriter\Item();
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
        $feed = new \Suin\RSSWriter\Feed();
        $feed->addChannel($channel);
        return $feed;
    }

    protected function getPageInfo($url, MediawikiApi $api, \Mediawiki\DataModel\Page $p) {
        $fact = new \Mediawiki\Api\MediawikiFactory($api);
        $page = $fact->newPageGetter()->getFromPage($p);
        $pageName = $page->getPageIdentifier()->getTitle()->getText();

        // Get the page metadata.
        $params = [
            'prop' => 'info',
            'titles' => $pageName,
        ];
        $queryResult = $api->getRequest(new SimpleRequest('query', $params));
        $pageInfo = array_shift($queryResult['query']['pages']);

        // Get the page text, and categories etc.
        $parseResult = $fact->newParser()->parsePage($page->getPageIdentifier());
        $content = $parseResult['text']['*'];
        $description = substr(strip_tags($content), 0, 400);

        // Try to get the publication date out of the HTML.
        $html = new \SimpleXMLElement("<div>$content</div>");
        $timeElements = $html->xpath('//time');
        $firstTime = array_shift($timeElements);
        if (isset($firstTime['datetime'])) {
            $time = strtotime($firstTime['datetime']);
        } else {
            $time = strtotime($pageInfo['touched']);
        }

        // Get a list of contributors.
        $contribResult = $api->getRequest(new SimpleRequest('query', [
            'prop' => 'contributors',
            'titles' => $pageName,
        ]));
        $contribs = array();
        if (isset($contribResult['pages'])) {
            $contribsTmp = array_shift($contribResult['pages']);
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
     * @param MediawikiApi $api
     * @param type $cat
     * @return type
     */
    protected function getCategoryPages(MediawikiApi $api, $cat) {
        // First get all pages in the root category.
        $pages = $this->getCategoryMembers($api, $cat, 'page');
        // Then get all pages in subcategories of the root.
        $subcats = $this->getCategoryMembers($api, $cat, 'subcat');
        foreach ($subcats as $subcat) {
            $subcatTitle = $subcat->getPageIdentifier()->getTitle()->getText();
            $subcatpages = $this->getCategoryPages($api, $subcatTitle);
            $pages = array_merge($pages, $subcatpages);
        }
        return $pages;
    }

    /**
     * Get all items in a category, by type.
     *
     * @param \Mediawiki\Api\MediawikiApi $api The mediawiki instance to query
     * @param string $cat The category to search within
     * @param string $type Either 'page', 'subcat', or 'file'
     * @return string[] Array of page titles.
     * @throws \Exception When a thing is exceptionally wrong.
     */
    protected function getCategoryMembers(MediawikiApi $api, $cat, $type = 'page') {
        $factory = new \Mediawiki\Api\MediawikiFactory($api);
        return $factory->newPageListGetter()
                ->getPageListFromCategoryName($cat, ['cmtype' => $type])
                ->toArray();
    }

}