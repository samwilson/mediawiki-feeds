<?php

namespace Samwilson\MediaWikiFeeds;

use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;

class Controller
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
            'cdn' => $this->container->get('settings')['config']['cdn'],
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
        $type = $request->getParam('type', 'rss');

        // Construct the feed.
        $feedBuilder = FeedBuilder::factory($url, $cat, $numItems, $title, $type);
        $feedBuilder->setCacheDir($this->container->get('settings')['config']['vardir'] . '/feeds');
        $noCache = ($request->getParam('nocache', null) !== null);
        if (!$feedBuilder->hasCurrentCache() || $noCache) {
            $feedBuilder->buildAndCacheFeed();
        }

        // Output the feed.
        $cachePath = $feedBuilder->getCachePath();
        $response->getBody()->write(file_get_contents($cachePath));
        return $response
            ->withHeader('Content-Type', $feedBuilder->getContentType())
            ->withHeader('Content-Length', (string)filesize($cachePath));
    }
}
