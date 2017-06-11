<?php

namespace Samwilson\MediaWikiFeeds\Controllers;

use Psr\Container\ContainerInterface;
use Samwilson\MediaWikiFeeds\FeedBuilder;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;

class RssController
{

    /** @var ContainerInterface */
    protected $container;

    /** @var Twig */
    protected $view;
    
    public function __construct(ContainerInterface $container)
    {
        $this->view = $container->get('view');
        $this->container = $container;
    }

    public function home(Request $request, Response $response, $args)
    {
        $config = $this->container->get('settings')['config'];
        return $this->view->render($response, 'home.html.twig', [
            'url' => $config['defaults']['url'],
            'category' => $config['defaults']['category'],
            'num' => $config['defaults']['num'],
        ]);
    }

    public function feed(Request $request, Response $response, $args)
    {
        $defaults = $this->container->get('settings')['config']['defaults'];

        // Get the feed parameters.
        $cat = $request->getParam('category', $defaults['category']);
        $url = $request->getParam('url', $defaults['url']);
        $numItems = $request->getParam('num', $defaults['num']);
        $title = $request->getParam('title');
        if (empty($title) && !empty($defaults['title'])) {
            $title = $defaults['title'];
        }
        if (empty($title)) {
            $title = $cat;
        }

        // Construct the feed.
        $feedBuilder = new FeedBuilder($url, $cat, $numItems, $title);
        $feedBuilder->setCacheDir($this->container->get('settings')['config']['vardir'] . '/feeds');

        $noCache = ($request->getParam('nocache', null) !== null);
        if (!$feedBuilder->hasCurrentCache() || $noCache) {
            $feedBuilder->buildAndCacheFeed();
        }

        // Output the feed. Should this use application/rss+xml?
        $cachePath = $feedBuilder->getCachePath();
        $response->withHeader('Content-type', 'text/xml;charset=utf-8');
        $response->withHeader('Content-Length', (string)filesize($cachePath));
        $response->getBody()->write(file_get_contents($cachePath));
        return $response;
    }
}
