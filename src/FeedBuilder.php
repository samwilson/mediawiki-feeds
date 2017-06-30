<?php

namespace Samwilson\MediaWikiFeeds;

use DateTime;
use Exception;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\MediawikiFactory;
use Mediawiki\Api\SimpleRequest;
use Mediawiki\DataModel\Page;
use Mediawiki\DataModel\PageIdentifier;
use Mediawiki\DataModel\Title;
use Symfony\Component\DomCrawler\Crawler;

abstract class FeedBuilder
{

    /** @var string The base URL, with trailing slash. */
    protected $scriptUrl;

    /** @var string */
    protected $category;

    /** @var integer */
    protected $numItems;

    /** @var string */
    protected $title;

    /** @var string */
    protected $cacheDir;

    public function __construct($scriptUrl, $category, $numItems = 10, $title = null)
    {
        $this->scriptUrl = rtrim($scriptUrl, '/') . '/';
        $this->category = $category;
        $this->numItems = $numItems;
        $this->title = (!is_null($title)) ? $title : $category;
    }

    /**
     * Get a new FeedBuilder of the given type.
     * @param string $type Either 'rss' or 'icalendar'.
     * @return FeedBuilder
     */
    public static function factory($url, $cat, $numItems, $title, $type = 'rss')
    {
        $builderClassName = 'Samwilson\\MediaWikiFeeds\\'.ucfirst(strtolower($type)).'Builder';
        /** @var FeedBuilder $feedBuilder */
        $feedBuilder = new $builderClassName($url, $cat, $numItems, $title);
        return $feedBuilder;
    }

    /**
     * Get the feed's ID, which is a hash of the URL, category, number of items, and title.
     * @return string
     */
    public function getFeedId()
    {
        return md5($this->scriptUrl . $this->category . $this->numItems . $this->title);
    }

    public function setCacheDir($cacheDir)
    {
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        $this->cacheDir = realpath($cacheDir);
        if (!is_dir($this->cacheDir)) {
            throw new Exception("Cache directory not found: $cacheDir");
        }
    }

    public function getCacheDir()
    {
        if (empty($this->cacheDir)) {
            throw new Exception('Please set cache directory first');
        }
        return $this->cacheDir;
    }

    /**
     * Get the full filesystem path to the cached RSS file.
     * @return string
     */
    public function getCachePath()
    {
        return $this->getCacheDir() . "/" . $this->getFeedId() . '.' . $this->getFileExtension();
    }
    
    /**
     * Does this feed have a currently cached copy?
     * @return bool
     */
    public function hasCurrentCache()
    {
        $feedFile = $this->getCachePath();
        $cacheTime = 60 * 60 * 1; // 1 hour.
        $hasCurrentCache = (file_exists($feedFile) && filemtime($feedFile) > (time() - $cacheTime));
        return $hasCurrentCache;
    }
    
    /**
     * Get the file extension.
     * @return string
     */
    abstract public function getFileExtension();

    /**
     * Build the feed, and write it to the local cache file. This is the main entry point.
     */
    public function buildAndCacheFeed()
    {
        $api = MediawikiApi::newFromApiEndpoint($this->scriptUrl . '/api.php');
        $items = $this->getPagesInCategory($this->scriptUrl, $api, $this->category, $this->numItems);
        $feedFile = $this->getCachePath();
        if (!is_dir(dirname($feedFile))) {
            mkdir(dirname($feedFile));
        }
        $feed = $this->getFeedContents($items);
        file_put_contents($feedFile, $feed);
        chmod($feedFile, 0664); // For CLI's benefit (if it's the same group).
    }

    /**
     * Get the text contents of the feed.
     * @param array $items The pages' data to create the feed from.
     * @return string
     */
    abstract protected function getFeedContents($items);

    protected function getPagesInCategory($url, MediawikiApi $api, $cat, $numItems)
    {
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
            $pages[$info['pubdate']->format('U') . '.' . $pageKey] = $info;
            $pageNum++;
        }
        return $pages;
    }
    
    protected function getPageInfo($url, MediawikiApi $api, Page $p)
    {
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
        // If there is only one time element, assume it's the publication date.
        if ($timeElements->count() === 1 && $timeElements->first()->attr('datetime') !== null) {
            $datePublished = DateTime::createFromFormat('U', strtotime($timeElements->first()->attr('datetime')));
        } else {
            $datePublished = DateTime::createFromFormat('U', strtotime($revisionInfo['revisions'][0]['timestamp']));
        }
        // Start date and time.
        $startDateElements = $timeElements->filterXPath("//*[@itemprop='startDate']");
        $startDate = '';
        if ($startDateElements->count() > 0) {
            $startDate = DateTime::createFromFormat('U', strtotime($startDateElements->attr('datetime')));
        }
        // End date and time.
        $endDateElements = $timeElements->filterXPath("//*[@itemprop='endDate']");
        $endDate = '';
        if ($endDateElements->count() > 0) {
            $endDate = DateTime::createFromFormat('U', strtotime($startDateElements->attr('datetime')));
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
                foreach ($contribsTmp['contributors'] as $c) {
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
            'pubdate' => $datePublished,
            'startdate' => $startDate,
            'enddate' => $endDate,
            'guid' => $url . 'index.php?oldid=' . $revisionInfo['revisions'][0]['revid'],
        ];
        return $feedItem;
    }

    /**
     * Get the content-type string for this feed type.
     * @return string
     */
    abstract public function getContentType();
}
